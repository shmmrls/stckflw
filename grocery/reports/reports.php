<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $baseUrl . '/user/dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user's store information
$store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
$store_stmt->bind_param("i", $user_id);
$store_stmt->execute();
$store_result = $store_stmt->get_result();
$user_store = $store_result->fetch_assoc();
$store_id = $user_store['store_id'];

// Get store details
$store_info_stmt = $conn->prepare("SELECT * FROM grocery_stores WHERE store_id = ?");
$store_info_stmt->bind_param("i", $store_id);
$store_info_stmt->execute();
$store_info = $store_info_stmt->get_result()->fetch_assoc();

// Get comprehensive inventory report data
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(gi.item_id) as total_items,
        SUM(CASE WHEN gi.expiry_status = 'near_expiry' THEN 1 ELSE 0 END) as near_expiry_items,
        SUM(CASE WHEN gi.expiry_status = 'expired' THEN 1 ELSE 0 END) as expired_items,
        SUM(CASE WHEN gi.quantity <= gi.reorder_level THEN 1 ELSE 0 END) as low_stock_items,
        SUM(CASE WHEN gi.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_items,
        SUM(gi.selling_price * gi.quantity) as revenue_potential,
        SUM(gi.cost_price * gi.quantity) as total_inventory_cost,
        SUM((gi.selling_price - gi.cost_price) * gi.quantity) as potential_profit,
        AVG((gi.selling_price - gi.cost_price) / gi.cost_price * 100) as avg_profit_margin,
        COUNT(DISTINCT gi.supplier_id) as total_suppliers,
        COUNT(DISTINCT CASE WHEN gi.batch_number IS NOT NULL THEN gi.batch_number END) as tracked_batches
    FROM grocery_items gi
    WHERE gi.store_id = ?
");
$summary_stmt->bind_param("i", $store_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Get supplier performance data
$supplier_perf_stmt = $conn->prepare("
    SELECT 
        s.supplier_id,
        s.supplier_name,
        s.supplier_type,
        s.rating,
        COUNT(DISTINCT gi.item_id) as items_supplied,
        SUM(gi.quantity) as total_quantity,
        SUM(gi.cost_price * gi.quantity) as total_purchase_value,
        SUM((gi.selling_price - gi.cost_price) * gi.quantity) as total_profit_generated,
        AVG(gi.selling_price - gi.cost_price) as avg_profit_per_unit
    FROM suppliers s
    LEFT JOIN grocery_items gi ON s.supplier_id = gi.supplier_id AND gi.store_id = ?
    WHERE s.is_active = 1
    GROUP BY s.supplier_id
    HAVING items_supplied > 0
    ORDER BY total_purchase_value DESC
    LIMIT 10
");
$supplier_perf_stmt->bind_param("i", $store_id);
$supplier_perf_stmt->execute();
$supplier_performance = $supplier_perf_stmt->get_result();

// Get reorder suggestions using the view
$reorder_stmt = $conn->prepare("
    SELECT * FROM v_reorder_suggestions
    WHERE store_id = ?
    ORDER BY shortage DESC
    LIMIT 20
");
$reorder_stmt->bind_param("i", $store_id);
$reorder_stmt->execute();
$reorder_items = $reorder_stmt->get_result();

// Get category breakdown
$category_stmt = $conn->prepare("
    SELECT 
        c.category_name,
        COUNT(gi.item_id) as item_count,
        SUM(gi.quantity) as total_quantity,
        SUM(gi.selling_price * gi.quantity) as category_value,
        SUM((gi.selling_price - gi.cost_price) * gi.quantity) as category_profit
    FROM categories c
    LEFT JOIN grocery_items gi ON c.category_id = gi.category_id AND gi.store_id = ?
    GROUP BY c.category_id
    HAVING item_count > 0
    ORDER BY category_value DESC
");
$category_stmt->bind_param("i", $store_id);
$category_stmt->execute();
$category_breakdown = $category_stmt->get_result();

// Get expiry forecast (next 30 days)
$expiry_forecast_stmt = $conn->prepare("
    SELECT 
        DATE(expiry_date) as expiry_day,
        COUNT(*) as items_expiring,
        SUM(quantity * selling_price) as value_at_risk
    FROM grocery_items
    WHERE store_id = ?
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND expiry_status != 'expired'
    GROUP BY DATE(expiry_date)
    ORDER BY expiry_date ASC
");
$expiry_forecast_stmt->bind_param("i", $store_id);
$expiry_forecast_stmt->execute();
$expiry_forecast = $expiry_forecast_stmt->get_result();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/reports.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<style>
.reports-page { background: #fafafa; min-height: 100vh; padding: 40px 20px; }
.page-container { max-width: 1400px; margin: 0 auto; }
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
.summary-card { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); padding: 25px; }
.summary-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; }
.summary-icon svg { width: 24px; height: 24px; }
.summary-icon.primary { background: #eff6ff; color: #1d4ed8; }
.summary-icon.success { background: #f0fdf4; color: #166534; }
.summary-icon.warning { background: #fff7ed; color: #c2410c; }
.summary-icon.danger { background: #fef2f2; color: #b91c1c; }
.summary-content h3 { font-size: 28px; font-weight: 600; color: #0a0a0a; margin-bottom: 5px; }
.summary-content p { font-size: 12px; color: rgba(0,0,0,0.6); text-transform: uppercase; letter-spacing: 1px; }
.report-section { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); padding: 30px; margin-bottom: 20px; }
.section-title { font-size: 18px; font-weight: 600; color: #0a0a0a; margin-bottom: 20px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 12px; background: #fafafa; border-bottom: 2px solid rgba(0,0,0,0.1); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: rgba(0,0,0,0.6); }
.data-table td { padding: 12px; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 13px; }
.data-table tr:hover { background: #fafafa; }
.progress-bar { width: 100%; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
.progress-fill { height: 100%; background: #3b82f6; transition: width 0.3s ease; }
.progress-fill.success { background: #10b981; }
.progress-fill.warning { background: #f59e0b; }
.progress-fill.danger { background: #ef4444; }
.badge-mini { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 10px; font-weight: 500; }
.badge-mini.success { background: #d1fae5; color: #065f46; }
.badge-mini.warning { background: #fed7aa; color: #92400e; }
.badge-mini.danger { background: #fecaca; color: #991b1b; }
.badge-mini.info { background: #dbeafe; color: #1e40af; }
.empty-state { text-align: center; padding: 60px 20px; color: rgba(0,0,0,0.5); }
.empty-state svg { margin-bottom: 20px; opacity: 0.3; }
</style>

<main class="reports-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Analytics & Reports</h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($store_info['store_name']); ?> - Comprehensive Business Intelligence</p>
            </div>
            <div class="header-actions">
                <a href="../grocery_dashboard.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon primary">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3><?php echo number_format($summary['total_items'] ?? 0); ?></h3>
                    <p>Total Items</p>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3>₱<?php echo number_format($summary['total_inventory_cost'] ?? 0, 2); ?></h3>
                    <p>Inventory Cost</p>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3>₱<?php echo number_format($summary['potential_profit'] ?? 0, 2); ?></h3>
                    <p>Potential Profit</p>
                    <small style="color: #059669;"><?php echo number_format($summary['avg_profit_margin'] ?? 0, 1); ?>% Avg Margin</small>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon warning">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3><?php echo number_format($summary['low_stock_items'] ?? 0); ?></h3>
                    <p>Low Stock Items</p>
                    <small style="color: #c2410c;"><?php echo number_format($summary['out_of_stock_items'] ?? 0); ?> Out of Stock</small>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon danger">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3><?php echo number_format($summary['expired_items'] ?? 0); ?></h3>
                    <p>Expired Items</p>
                    <small style="color: #c2410c;"><?php echo number_format($summary['near_expiry_items'] ?? 0); ?> Near Expiry</small>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon primary">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3><?php echo number_format($summary['total_suppliers'] ?? 0); ?></h3>
                    <p>Active Suppliers</p>
                </div>
            </div>
        </div>

        <!-- Supplier Performance Analysis -->
        <div class="report-section">
            <h2 class="section-title">Supplier Performance Analysis</h2>
            <p style="color: rgba(0,0,0,0.6); margin-bottom: 20px;">Top suppliers by purchase value and profitability</p>
            
            <?php if ($supplier_performance->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Type</th>
                                <th>Items</th>
                                <th>Purchase Value</th>
                                <th>Profit Generated</th>
                                <th>Rating</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $max_value = 0;
                            $perf_data = [];
                            while ($row = $supplier_performance->fetch_assoc()) {
                                $perf_data[] = $row;
                                if ($row['total_purchase_value'] > $max_value) {
                                    $max_value = $row['total_purchase_value'];
                                }
                            }
                            
                            foreach ($perf_data as $perf): 
                                $performance_pct = $max_value > 0 ? ($perf['total_purchase_value'] / $max_value * 100) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($perf['supplier_name']); ?></strong></td>
                                <td><?php echo ucwords(str_replace('_', ' ', $perf['supplier_type'])); ?></td>
                                <td><?php echo number_format($perf['items_supplied']); ?></td>
                                <td>₱<?php echo number_format($perf['total_purchase_value'], 2); ?></td>
                                <td style="color: #059669;">₱<?php echo number_format($perf['total_profit_generated'], 2); ?></td>
                                <td>
                                    <?php if ($perf['rating']): ?>
                                        <span class="badge-mini info"><?php echo number_format($perf['rating'], 1); ?> ★</span>
                                    <?php else: ?>
                                        <span style="color: rgba(0,0,0,0.3);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill success" style="width: <?php echo $performance_pct; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No supplier performance data available yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reorder Suggestions -->
        <div class="report-section">
            <h2 class="section-title">Reorder Suggestions</h2>
            <p style="color: rgba(0,0,0,0.6); margin-bottom: 20px;">Items that need restocking with supplier options and pricing</p>
            
            <?php if ($reorder_items->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Suggested Qty</th>
                                <th>Current Supplier</th>
                                <th>Best Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $reorder_items->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                <td><?php echo number_format($item['current_quantity'], 2); ?></td>
                                <td><?php echo number_format($item['reorder_level'], 2); ?></td>
                                <td><?php echo number_format($item['suggested_reorder_qty'], 2); ?></td>
                                <td>
                                    <?php if ($item['current_supplier']): ?>
                                        <?php echo htmlspecialchars($item['current_supplier']); ?>
                                        <br><small>₱<?php echo number_format($item['current_cost'], 2); ?></small>
                                    <?php else: ?>
                                        <span style="color: rgba(0,0,0,0.3);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['best_available_price']): ?>
                                        <strong style="color: #059669;">₱<?php echo number_format($item['best_available_price'], 2); ?></strong>
                                        <?php if ($item['current_cost'] && $item['best_available_price'] < $item['current_cost']): ?>
                                            <br><small style="color: #059669;">Save ₱<?php echo number_format($item['current_cost'] - $item['best_available_price'], 2); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: rgba(0,0,0,0.3);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $shortage = $item['shortage'];
                                    if ($shortage > 50) {
                                        echo '<span class="badge-mini danger">Critical</span>';
                                    } elseif ($shortage > 20) {
                                        echo '<span class="badge-mini warning">Low</span>';
                                    } else {
                                        echo '<span class="badge-mini info">Monitor</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <p>All items are well-stocked! No reorders needed at this time.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Category Breakdown -->
        <div class="report-section">
            <h2 class="section-title">Category Performance</h2>
            <p style="color: rgba(0,0,0,0.6); margin-bottom: 20px;">Inventory value and profitability by category</p>
            
            <?php if ($category_breakdown->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Items</th>
                                <th>Total Value</th>
                                <th>Potential Profit</th>
                                <th>Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_value = $summary['revenue_potential'];
                            while ($cat = $category_breakdown->fetch_assoc()): 
                                $pct = $total_value > 0 ? ($cat['category_value'] / $total_value * 100) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cat['category_name']); ?></strong></td>
                                <td><?php echo number_format($cat['item_count']); ?></td>
                                <td>₱<?php echo number_format($cat['category_value'], 2); ?></td>
                                <td style="color: #059669;">₱<?php echo number_format($cat['category_profit'], 2); ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                    <small style="color: rgba(0,0,0,0.5);"><?php echo number_format($pct, 1); ?>%</small>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No category data available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Expiry Forecast -->
        <div class="report-section">
            <h2 class="section-title">30-Day Expiry Forecast</h2>
            <p style="color: rgba(0,0,0,0.6); margin-bottom: 20px;">Items expiring in the next 30 days</p>
            
            <?php if ($expiry_forecast->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Expiry Date</th>
                                <th>Items Expiring</th>
                                <th>Value at Risk</th>
                                <th>Days Until Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($exp = $expiry_forecast->fetch_assoc()): 
                                $days_until = (strtotime($exp['expiry_day']) - time()) / (60 * 60 * 24);
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($exp['expiry_day'])); ?></td>
                                <td><?php echo number_format($exp['items_expiring']); ?></td>
                                <td style="color: #c2410c;">₱<?php echo number_format($exp['value_at_risk'], 2); ?></td>
                                <td>
                                    <?php 
                                    if ($days_until <= 3) {
                                        echo '<span class="badge-mini danger">' . ceil($days_until) . ' days</span>';
                                    } elseif ($days_until <= 7) {
                                        echo '<span class="badge-mini warning">' . ceil($days_until) . ' days</span>';
                                    } else {
                                        echo '<span class="badge-mini info">' . ceil($days_until) . ' days</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <p>No items expiring in the next 30 days!</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php 
$summary_stmt->close();
$supplier_perf_stmt->close();
$reorder_stmt->close();
$category_stmt->close();
$expiry_forecast_stmt->close();
$store_info_stmt->close();
$store_stmt->close();
$conn->close();
?>