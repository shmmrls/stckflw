<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

// Verify user is grocery admin
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

// Get all inventory alerts
$alerts_stmt = $conn->prepare("
    SELECT 
        gi.item_id,
        gi.store_id,
        gs.store_name,
        gi.item_name,
        gi.quantity,
        gi.reorder_level,
        gi.expiry_date,
        gi.expiry_status,
        CASE 
            WHEN gi.quantity <= gi.reorder_level THEN 'LOW_STOCK'
            WHEN gi.expiry_status = 'expired' THEN 'EXPIRED'
            WHEN gi.expiry_status = 'near_expiry' THEN 'NEAR_EXPIRY'
            ELSE 'OK'
        END as alert_type,
        DATEDIFF(gi.expiry_date, CURDATE()) as days_until_expiry
    FROM grocery_items gi
    JOIN grocery_stores gs ON gi.store_id = gs.store_id
    WHERE gi.store_id = ?
    AND (gi.quantity <= gi.reorder_level OR gi.expiry_status IN ('near_expiry', 'expired'))
    ORDER BY 
        CASE 
            WHEN gi.expiry_status = 'expired' THEN 1
            WHEN gi.expiry_status = 'near_expiry' THEN 2
            WHEN gi.quantity <= gi.reorder_level THEN 3
            ELSE 4
        END,
        gi.expiry_date ASC
");
$alerts_stmt->bind_param("i", $store_id);
$alerts_stmt->execute();
$alerts_result = $alerts_stmt->get_result();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/alerts.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="alerts-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Inventory Alerts</h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($store_info['store_name']); ?> - Active Notifications</p>
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

        <!-- Alert Summary -->
        <div class="alert-summary">
            <?php
            $expired_count = 0;
            $near_expiry_count = 0;
            $low_stock_count = 0;
            
            // Reset result pointer and count
            $alerts_stmt->data_seek(0);
            while ($alert = $alerts_result->fetch_assoc()) {
                switch($alert['alert_type']) {
                    case 'EXPIRED': $expired_count++; break;
                    case 'NEAR_EXPIRY': $near_expiry_count++; break;
                    case 'LOW_STOCK': $low_stock_count++; break;
                }
            }
            
            // Reset result pointer again for display
            $alerts_stmt->data_seek(0);
            ?>
            
            <div class="summary-card danger">
                <div class="summary-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3><?php echo $expired_count; ?></h3>
                    <p>Expired Items</p>
                </div>
            </div>

            <div class="summary-card warning">
                <div class="summary-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3><?php echo $near_expiry_count; ?></h3>
                    <p>Near Expiry Items</p>
                </div>
            </div>

            <div class="summary-card info">
                <div class="summary-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
                <div class="summary-content">
                    <h3><?php echo $low_stock_count; ?></h3>
                    <p>Low Stock Items</p>
                </div>
            </div>
        </div>

        <!-- Alerts List -->
        <div class="alerts-content">
            <?php if ($alerts_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Alert Type</th>
                                <th>Current Quantity</th>
                                <th>Reorder Level</th>
                                <th>Expiry Date</th>
                                <th>Days Until Expiry</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($alert = $alerts_result->fetch_assoc()): ?>
                            <tr class="alert-row alert-<?php echo strtolower($alert['alert_type']); ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($alert['item_name']); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $badge_class = '';
                                    $alert_text = '';
                                    switch($alert['alert_type']) {
                                        case 'EXPIRED':
                                            $badge_class = 'badge-danger';
                                            $alert_text = 'Expired';
                                            break;
                                        case 'NEAR_EXPIRY':
                                            $badge_class = 'badge-warning';
                                            $alert_text = 'Near Expiry';
                                            break;
                                        case 'LOW_STOCK':
                                            $badge_class = 'badge-info';
                                            $alert_text = 'Low Stock';
                                            break;
                                        default:
                                            $badge_class = 'badge-success';
                                            $alert_text = 'OK';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $alert_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="quantity"><?php echo number_format($alert['quantity'], 2); ?></span>
                                </td>
                                <td>
                                    <span class="reorder-level"><?php echo number_format($alert['reorder_level'], 2); ?></span>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($alert['expiry_date'])); ?>
                                </td>
                                <td>
                                    <?php 
                                    $days = $alert['days_until_expiry'];
                                    if ($days < 0) {
                                        echo '<span class="expired-text">Expired ' . abs($days) . ' days ago</span>';
                                    } else {
                                        echo '<span class="days-text">' . $days . ' days</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="add_item.php?edit=<?php echo $alert['item_id']; ?>" class="btn btn-sm btn-primary">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                            Edit
                                        </a>
                                        <a href="#" class="btn btn-sm btn-secondary" onclick="handleAlert(<?php echo $alert['item_id']; ?>, '<?php echo $alert['alert_type']; ?>')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                            </svg>
                                            Resolve
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-alerts">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <h3>No Active Alerts</h3>
                    <p>Great job! Your inventory is in good condition with no immediate alerts.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
function handleAlert(itemId, alertType) {
    let message = '';
    switch(alertType) {
        case 'EXPIRED':
            message = 'Mark this expired item as disposed?';
            break;
        case 'NEAR_EXPIRY':
            message = 'Mark this item for discount or promotion?';
            break;
        case 'LOW_STOCK':
            message = 'Create a reorder request for this item?';
            break;
    }
    
    if (confirm(message)) {
        // Here you would typically send an AJAX request to handle the alert
        alert('Alert handling feature coming soon!');
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php 
$alerts_stmt->close();
$store_info_stmt->close();
$store_stmt->close();
$conn->close();
?>
