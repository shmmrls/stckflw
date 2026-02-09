<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get waste summary stats
$waste_summary = $conn->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'spoiled' THEN ciu.update_id END) as items_spoiled,
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'expired' THEN ciu.update_id END) as items_expired,
        SUM(CASE WHEN ciu.update_type IN ('spoiled', 'expired') THEN ciu.quantity_change ELSE 0 END) as total_waste_qty,
        COUNT(DISTINCT ciu.item_id) as unique_items_wasted
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ciu.updated_by = ? AND ciu.update_type IN ('spoiled', 'expired')
");
$waste_summary->bind_param("i", $user_id);
$waste_summary->execute();
$waste_stats = $waste_summary->get_result()->fetch_assoc();

// Get waste by category
$waste_by_category = $conn->prepare("
    SELECT 
        c.category_id,
        c.category_name,
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'spoiled' THEN ciu.update_id END) as spoiled_count,
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'expired' THEN ciu.update_id END) as expired_count,
        SUM(ciu.quantity_change) as total_qty,
        COUNT(DISTINCT ciu.update_id) as total_waste_actions
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    INNER JOIN categories c ON ci.category_id = c.category_id
    WHERE ciu.updated_by = ? AND ciu.update_type IN ('spoiled', 'expired')
    GROUP BY c.category_id, c.category_name
    ORDER BY total_waste_actions DESC
");
$waste_by_category->bind_param("i", $user_id);
$waste_by_category->execute();
$category_waste = $waste_by_category->get_result();

// Get monthly waste trend
$monthly_waste = $conn->prepare("
    SELECT 
        DATE_FORMAT(ciu.update_date, '%Y-%m') as month,
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'spoiled' THEN ciu.update_id END) as spoiled,
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'expired' THEN ciu.update_id END) as expired,
        COUNT(DISTINCT ciu.update_id) as total_waste
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ciu.updated_by = ? AND ciu.update_type IN ('spoiled', 'expired')
    GROUP BY DATE_FORMAT(ciu.update_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$monthly_waste->bind_param("i", $user_id);
$monthly_waste->execute();
$monthly_result = $monthly_waste->get_result();

// Get recent waste items
$recent_waste = $conn->prepare("
    SELECT 
        ci.item_name,
        c.category_name,
        ciu.update_type,
        ciu.quantity_change,
        ci.unit,
        ciu.update_date,
        ciu.notes
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    INNER JOIN categories c ON ci.category_id = c.category_id
    WHERE ciu.updated_by = ? AND ciu.update_type IN ('spoiled', 'expired')
    ORDER BY ciu.update_date DESC
    LIMIT 20
");
$recent_waste->bind_param("i", $user_id);
$recent_waste->execute();
$recent_waste_result = $recent_waste->get_result();

// Calculate consumed vs wasted ratio
$consumption_stats = $conn->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'consumed' THEN ciu.update_id END) as items_consumed,
        COUNT(DISTINCT CASE WHEN ciu.update_type IN ('spoiled', 'expired') THEN ciu.update_id END) as items_wasted,
        ROUND(COUNT(DISTINCT CASE WHEN ciu.update_type IN ('spoiled', 'expired') THEN ciu.update_id END) * 100.0 / 
              NULLIF(COUNT(DISTINCT CASE WHEN ciu.update_type IN ('consumed', 'spoiled', 'expired') THEN ciu.update_id END), 0), 2) as waste_percentage
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ciu.updated_by = ? AND ciu.update_type IN ('consumed', 'spoiled', 'expired')
");
$consumption_stats->bind_param("i", $user_id);
$consumption_stats->execute();
$consumption_data = $consumption_stats->get_result()->fetch_assoc();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/waste-tracking.css">';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="waste-tracking-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Waste Tracking</h1>
                <p class="page-subtitle">Monitor spoiled and expired items in your inventory</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Waste Stats Grid -->
        <div class="waste-stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-value"><?php echo $waste_stats['items_spoiled'] ?? 0; ?></p>
                    <p class="stat-label">Items Spoiled</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-value"><?php echo $waste_stats['items_expired'] ?? 0; ?></p>
                    <p class="stat-label">Items Expired</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-value"><?php echo number_format($waste_stats['total_waste_qty'] ?? 0, 1); ?></p>
                    <p class="stat-label">Total Waste (Units)</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-value"><?php echo $consumption_data['waste_percentage'] ?? 0; ?>%</p>
                    <p class="stat-label">Waste Ratio</p>
                </div>
            </div>
        </div>

        <div class="waste-content-grid">
            
            <!-- Waste by Category -->
            <section class="waste-section">
                <div class="section-header">
                    <h2>Waste by Category</h2>
                </div>
                <?php if ($category_waste->num_rows === 0): ?>
                    <div class="empty-state">
                        <p>No waste recorded yet</p>
                    </div>
                <?php else: ?>
                    <div class="category-list">
                        <?php while ($cat = $category_waste->fetch_assoc()): ?>
                            <div class="category-item">
                                <div class="category-info">
                                    <p class="category-name"><?php echo htmlspecialchars($cat['category_name']); ?></p>
                                    <p class="category-meta">
                                        <span class="meta-badge spoiled"><?php echo $cat['spoiled_count']; ?> spoiled</span>
                                        <span class="meta-badge expired"><?php echo $cat['expired_count']; ?> expired</span>
                                    </p>
                                </div>
                                <div class="category-stats">
                                    <p class="quantity"><?php echo number_format($cat['total_qty'], 1); ?> units</p>
                                    <div class="waste-bar">
                                        <div class="bar-fill"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Prevention Tips -->
            <aside class="prevention-sidebar">
                <div class="tips-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 8px;">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Prevention Tips
                    </h3>
                    <ul class="tips-list">
                        <li><strong>Store Smart:</strong> Keep items in cool, dry places away from direct sunlight</li>
                        <li><strong>FIFO Method:</strong> Use "First In, First Out" - older items first</li>
                        <li><strong>Check Regularly:</strong> Review expiry dates weekly to catch items early</li>
                        <li><strong>Share with Groups:</strong> Give perishables to group members approaching expiry</li>
                        <li><strong>Portion Control:</strong> Buy what you'll actually use within expiry window</li>
                        <li><strong>Usage Tracking:</strong> Log consumed items to monitor consumption patterns</li>
                    </ul>
                </div>

                <div class="insights-card">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 8px;">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                        Your Insights
                    </h3>
                    <div class="insight-item">
                        <p class="insight-label">Consumed vs Wasted</p>
                        <div class="insight-values">
                            <span class="value good"><?php echo $consumption_data['items_consumed'] ?? 0; ?></span>
                            <span class="label">consumed</span>
                            <span class="value warn"><?php echo $consumption_data['items_wasted'] ?? 0; ?></span>
                            <span class="label">wasted</span>
                        </div>
                    </div>
                    <?php if (($consumption_data['waste_percentage'] ?? 0) < 10): ?>
                        <p class="insight-message positive">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle;">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Great job! Your waste is below 10%. Keep it up!
                        </p>
                    <?php elseif (($consumption_data['waste_percentage'] ?? 0) < 20): ?>
                        <p class="insight-message neutral">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle;">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            Average waste at <?php echo $consumption_data['waste_percentage']; ?>%. Room for improvement!
                        </p>
                    <?php else: ?>
                        <p class="insight-message alert">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle;">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            High waste detected at <?php echo $consumption_data['waste_percentage']; ?>%. Review storage practices!
                        </p>
                    <?php endif; ?>
                </div>
            </aside>

        </div>

        <!-- Recent Waste Log -->
        <section class="waste-log-section">
            <div class="section-header">
                <h2>Recent Waste Log</h2>
            </div>
            <?php if ($recent_waste_result->num_rows === 0): ?>
                <div class="empty-state">
                    <p>No waste recorded yet</p>
                </div>
            <?php else: ?>
                <div class="waste-table">
                    <div class="table-header">
                        <div class="col-item">Item</div>
                        <div class="col-category">Category</div>
                        <div class="col-type">Type</div>
                        <div class="col-quantity">Quantity</div>
                        <div class="col-date">Date</div>
                        <div class="col-notes">Notes</div>
                    </div>
                    <?php while ($waste = $recent_waste_result->fetch_assoc()): ?>
                        <div class="table-row">
                            <div class="col-item">
                                <span class="item-name"><?php echo htmlspecialchars($waste['item_name']); ?></span>
                            </div>
                            <div class="col-category">
                                <span class="category-tag"><?php echo htmlspecialchars($waste['category_name']); ?></span>
                            </div>
                            <div class="col-type">
                                <span class="type-badge <?php echo $waste['update_type']; ?>">
                                    <?php if ($waste['update_type'] === 'spoiled'): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 4px;">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="15" y1="9" x2="9" y2="15"/>
                                            <line x1="9" y1="9" x2="15" y2="15"/>
                                        </svg>
                                        Spoiled
                                    <?php else: ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 4px;">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        Expired
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="col-quantity">
                                <?php echo number_format($waste['quantity_change'], 1); ?> <?php echo htmlspecialchars($waste['unit']); ?>
                            </div>
                            <div class="col-date">
                                <?php echo date('M d, Y', strtotime($waste['update_date'])); ?>
                            </div>
                            <div class="col-notes">
                                <?php echo htmlspecialchars($waste['notes'] ? substr($waste['notes'], 0, 30) : '-'); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php 
$waste_summary->close();
$waste_by_category->close();
$monthly_waste->close();
$recent_waste->close();
$consumption_stats->close();
$conn->close();
?>