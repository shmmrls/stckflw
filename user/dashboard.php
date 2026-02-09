<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user points
$points_stmt = $conn->prepare("SELECT total_points FROM user_points WHERE user_id = ?");
$points_stmt->bind_param("i", $user_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
$user_points = $points_result->fetch_assoc()['total_points'] ?? 0;

// Get user's groups
$groups_stmt = $conn->prepare("
    SELECT g.group_id, g.group_name, g.group_type 
    FROM groups g
    INNER JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
");
$groups_stmt->bind_param("i", $user_id);
$groups_stmt->execute();
$groups_result = $groups_stmt->get_result();

// Get dashboard summary for user's groups
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(ci.item_id) as total_items,
        SUM(CASE WHEN ci.expiry_status = 'near_expiry' THEN 1 ELSE 0 END) as near_expiry_items,
        SUM(CASE WHEN ci.expiry_status = 'expired' THEN 1 ELSE 0 END) as expired_items,
        SUM(ci.quantity) as total_quantity
    FROM customer_items ci
    INNER JOIN group_members gm ON ci.group_id = gm.group_id
    WHERE gm.user_id = ?
");
$summary_stmt->bind_param("i", $user_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Get recent items
$items_stmt = $conn->prepare("
    SELECT ci.*, c.category_name, g.group_name
    FROM customer_items ci
    INNER JOIN categories c ON ci.category_id = c.category_id
    INNER JOIN groups g ON ci.group_id = g.group_id
    INNER JOIN group_members gm ON ci.group_id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY ci.date_added DESC
    LIMIT 10
");
$items_stmt->bind_param("i", $user_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// Get recent activity (last 5 actions)
$recent_activity_stmt = $conn->prepare("
    SELECT 
        ciu.update_type,
        ciu.quantity_change,
        ciu.update_date,
        ci.item_name,
        c.category_name,
        ci.unit
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    INNER JOIN categories c ON ci.category_id = c.category_id
    WHERE ciu.updated_by = ?
    ORDER BY ciu.update_date DESC
    LIMIT 5
");
$recent_activity_stmt->bind_param("i", $user_id);
$recent_activity_stmt->execute();
$recent_activity_result = $recent_activity_stmt->get_result();

// Get waste stats (spoiled and expired items)
$waste_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'spoiled' THEN ciu.update_id END) as items_spoiled,
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'expired' THEN ciu.update_id END) as items_expired,
        ROUND(COUNT(DISTINCT CASE WHEN ciu.update_type IN ('spoiled', 'expired') THEN ciu.update_id END) * 100.0 / 
              NULLIF(COUNT(DISTINCT CASE WHEN ciu.update_type IN ('consumed', 'spoiled', 'expired') THEN ciu.update_id END), 0), 2) as waste_percentage
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ciu.updated_by = ? AND ciu.update_type IN ('spoiled', 'expired', 'consumed')
");
$waste_stats_stmt->bind_param("i", $user_id);
$waste_stats_stmt->execute();
$waste_data = $waste_stats_stmt->get_result()->fetch_assoc();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/dashboard.css">';
require_once __DIR__ . '/../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="dashboard-page">
    <div class="dashboard-container">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="dashboard-title">Dashboard</h1>
                    <p class="dashboard-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                </div>
                <div class="header-actions">
                    <div class="points-badge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <span><?php echo number_format($user_points); ?> Points</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($summary['total_items'] ?? 0); ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($summary['total_quantity'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Quantity</div>
                </div>
            </div>

            <div class="stat-card stat-card-highlight">
                <div class="stat-icon stat-icon-warning">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($summary['near_expiry_items'] ?? 0); ?></div>
                    <div class="stat-label">Near Expiry</div>
                </div>
            </div>

            <div class="stat-card stat-card-highlight">
                <div class="stat-icon stat-icon-danger">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($summary['expired_items'] ?? 0); ?></div>
                    <div class="stat-label">Expired Items</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2 class="section-title">Quick Actions</h2>
            <div class="actions-grid">
                <a href="customer/item/add_item.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </div>
                    <div class="action-label">Add Item</div>
                </a>

                <a href="customer/item/my_items.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                    </div>
                    <div class="action-label">View Items</div>
                </a>

                <a href="customer/groups/my_groups.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <div class="action-label">My Groups</div>
                </a>

                <a href="categories.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                    </div>
                    <div class="action-label">Categories</div>
                </a>

                <a href="reports.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                    <div class="action-label">Reports</div>
                </a>

                <a href="rewards.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                        </svg>
                    </div>
                    <div class="action-label">Rewards</div>
                </a>

                <a href="activity.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="action-label">Activity</div>
                </a>

                <a href="waste_tracking.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M4 6l2 14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l2-14M10 11v6M14 11v6"/>
                        </svg>
                    </div>
                    <div class="action-label">Waste Tracking</div>
                </a>

                <a href="analytics.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                    <div class="action-label">Analytics</div>
                </a>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">Recent Activity</h2>
                <a href="activity.php" class="section-link">View All →</a>
            </div>

            <?php if ($recent_activity_result->num_rows > 0): ?>
                <div class="activity-feed-mini">
                    <?php while ($activity = $recent_activity_result->fetch_assoc()): ?>
                        <?php
                            $icon_svg = '';
                            $action_label = 'Unknown';
                            
                            switch ($activity['update_type']) {
                                case 'added':
                                    $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                                    $action_label = 'Added';
                                    break;
                                case 'consumed':
                                    $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>';
                                    $action_label = 'Consumed';
                                    break;
                                case 'spoiled':
                                    $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>';
                                    $action_label = 'Spoiled';
                                    break;
                                case 'expired':
                                    $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                                    $action_label = 'Expired';
                                    break;
                            }
                        ?>
                        <div class="activity-item">
                            <span class="activity-icon"><?php echo $icon_svg; ?></span>
                            <div class="activity-info">
                                <p class="activity-text">
                                    <strong><?php echo $action_label; ?></strong> 
                                    <?php echo htmlspecialchars($activity['item_name']); ?>
                                </p>
                                <p class="activity-meta">
                                    <span class="meta-item"><?php echo htmlspecialchars($activity['category_name']); ?></span>
                                    <span class="meta-item"><?php echo number_format($activity['quantity_change'], 2); ?> <?php echo htmlspecialchars($activity['unit']); ?></span>
                                    <span class="meta-time"><?php echo date('M d, H:i', strtotime($activity['update_date'])); ?></span>
                                </p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No activity yet. Start by adding items or logging consumption!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Waste Stats Section -->
        <div class="content-section waste-section">
            <div class="section-header">
                <h2 class="section-title">Waste Overview</h2>
                <a href="waste_tracking.php" class="section-link">View Details →</a>
            </div>

            <div class="waste-stats-grid">
                <div class="waste-stat-card">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <p class="stat-value"><?php echo $waste_data['items_spoiled'] ?? 0; ?></p>
                        <p class="stat-label">Items Spoiled</p>
                    </div>
                </div>

                <div class="waste-stat-card">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <p class="stat-value"><?php echo $waste_data['items_expired'] ?? 0; ?></p>
                        <p class="stat-label">Items Expired</p>
                    </div>
                </div>

                <div class="waste-stat-card">
                    <div class="stat-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <p class="stat-value"><?php echo $waste_data['waste_percentage'] ?? 0; ?>%</p>
                        <p class="stat-label">Waste Ratio</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Items Section -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">Recent Items</h2>
                <a href="<?= htmlspecialchars($baseUrl) ?>/user/customer/item/my_items.php" class="section-link">View All →</a>
            </div>

            <?php if ($items_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Group</th>
                                <th>Quantity</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $items_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['group_name']); ?></td>
                                <td><?php echo $item['quantity'] . ' ' . htmlspecialchars($item['unit']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($item['expiry_date'])); ?></td>
                                <td>
                                    <?php 
                                    $badge_class = '';
                                    if ($item['expiry_status'] === 'fresh') {
                                        $badge_class = 'badge-delivered';
                                    } elseif ($item['expiry_status'] === 'near_expiry') {
                                        $badge_class = 'badge-pending';
                                    } else {
                                        $badge_class = 'badge-cancelled';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['expiry_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="consume_item.php?id=<?php echo $item['item_id']; ?>" class="action-btn">Consume</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <p>No items found. Start by adding items to your inventory!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- My Groups Section -->
        <?php if ($groups_result->num_rows > 0): ?>
        <div class="groups-section">
            <div class="section-header">
                <h2 class="section-title">My Groups</h2>
                <a href="<?= htmlspecialchars($baseUrl) ?>/user/customer/groups/my_groups.php" class="section-link">Manage Groups →</a>
            </div>

            <div class="groups-grid">
                <?php while ($group = $groups_result->fetch_assoc()): ?>
                    <div class="group-card">
                        <div class="group-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div class="group-name"><?php echo htmlspecialchars($group['group_name']); ?></div>
                        <div class="group-type">
                            <span class="badge badge-<?php echo strtolower($group['group_type']); ?>">
                                <?php echo ucfirst($group['group_type']); ?>
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php 
$items_stmt->close();
$groups_stmt->close();
$summary_stmt->close();
$points_stmt->close();
$recent_activity_stmt->close();
$waste_stats_stmt->close();
$conn->close(); 
?>