<?php
require_once __DIR__ . '/../includes/admin_auth_check.php';
require_once __DIR__ . '/../includes/config.php';

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

// Get dashboard summary for the store
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(gi.item_id) as total_items,
        SUM(CASE WHEN gi.expiry_status = 'near_expiry' THEN 1 ELSE 0 END) as near_expiry_items,
        SUM(CASE WHEN gi.expiry_status = 'expired' THEN 1 ELSE 0 END) as expired_items,
        SUM(CASE WHEN gi.quantity <= gi.reorder_level THEN 1 ELSE 0 END) as low_stock_items,
        SUM(gi.selling_price * gi.quantity) as revenue_potential,
        SUM(gi.cost_price * gi.quantity) as total_inventory_value
    FROM grocery_items gi
    WHERE gi.store_id = ?
");
$summary_stmt->bind_param("i", $store_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Get recent items
$items_stmt = $conn->prepare("
    SELECT gi.*, c.category_name
    FROM grocery_items gi
    INNER JOIN categories c ON gi.category_id = c.category_id
    WHERE gi.store_id = ?
    ORDER BY gi.date_added DESC
    LIMIT 10
");
$items_stmt->bind_param("i", $store_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// Get inventory alerts - direct query to avoid collation issues
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
    LIMIT 5
");
$alerts_stmt->bind_param("i", $store_id);
$alerts_stmt->execute();
$alerts_result = $alerts_stmt->get_result();

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
                    <h1 class="dashboard-title">Store Dashboard</h1>
                    <p class="dashboard-subtitle"><?php echo htmlspecialchars($store_info['store_name']); ?> - Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                </div>
                <div class="header-actions">
                    <div class="points-badge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7h-9"></path>
                            <path d="M14 17H5"></path>
                            <circle cx="17" cy="17" r="3"></circle>
                            <circle cx="7" cy="7" r="3"></circle>
                        </svg>
                        <span>₱<?php echo number_format($summary['total_inventory_value'] ?? 0, 2); ?> Total Value</span>
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
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value">₱<?php echo number_format($summary['revenue_potential'] ?? 0, 2); ?></div>
                    <div class="stat-label">Revenue Potential</div>
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

            <div class="stat-card stat-card-highlight">
                <div class="stat-icon stat-icon-info">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($summary['low_stock_items'] ?? 0); ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2 class="section-title">Quick Actions</h2>
            <div class="actions-grid">
                <a href="items/add_item.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </div>
                    <div class="action-label">Add Item</div>
                </a>

                <a href="items/grocery_items.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                    </div>
                    <div class="action-label">View Inventory</div>
                </a>

                <a href="<?= htmlspecialchars($baseUrl) ?>/grocery/items/alerts.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </div>
                    <div class="action-label">Alerts</div>
                </a>
                <a href="<?= htmlspecialchars($baseUrl) ?>/grocery/suppliers/view_suppliers.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg></div>
                    <div class="action-label">Suppliers</div>
                </a>

                <a href="purchase_orders/index.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/>
                        </svg>
                    </div>
                    <div class="action-label">Purchase Orders</div>
                </a>

                <a href="reports/reports.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                    <div class="action-label">Reports</div>
                </a>

                <a href="store/settings.php" class="action-card">
                    <div class="action-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 1v6m0 6v6"/>
                            <path d="M17 12h6M1 12h6"/>
                            <path d="m4.93 4.93 4.24 4.24m5.66 5.66 4.24 4.24"/>
                            <path d="m4.93 19.07 4.24-4.24m5.66-5.66 4.24-4.24"/>
                        </svg>
                    </div>
                    <div class="action-label">Settings</div>
                </a>
            </div>
        </div>

        <!-- Inventory Alerts Section -->
        <?php if ($alerts_result->num_rows > 0): ?>
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">Inventory Alerts</h2>
                <a href="<?= htmlspecialchars($baseUrl) ?>/grocery/items/alerts.php" class="section-link">View All →</a>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Alert Type</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Days Until Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($alert = $alerts_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($alert['item_name']); ?></strong></td>
                            <td>
                                <?php 
                                $badge_class = '';
                                $alert_text = '';
                                switch($alert['alert_type']) {
                                    case 'EXPIRED':
                                        $badge_class = 'badge-cancelled';
                                        $alert_text = 'Expired';
                                        break;
                                    case 'NEAR_EXPIRY':
                                        $badge_class = 'badge-pending';
                                        $alert_text = 'Near Expiry';
                                        break;
                                    case 'LOW_STOCK':
                                        $badge_class = 'badge-personal';
                                        $alert_text = 'Low Stock';
                                        break;
                                    default:
                                        $badge_class = 'badge-delivered';
                                        $alert_text = 'OK';
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $alert_text; ?></span>
                            </td>
                            <td><?php echo number_format($alert['quantity'], 2); ?> / <?php echo number_format($alert['reorder_level'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($alert['expiry_date'])); ?></td>
                            <td>
                                <?php 
                                $days = $alert['days_until_expiry'];
                                if ($days < 0) {
                                    echo '<span style="color: #b91c1c;">Expired ' . abs($days) . ' days ago</span>';
                                } else {
                                    echo $days . ' days';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?= htmlspecialchars($baseUrl) ?>/grocery/items/edit_item.php?id=<?php echo $alert['item_id']; ?>" class="action-btn">Manage</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Manage Alert Modal -->
        <div id="manageAlertModal" class="modal-overlay" style="display: none;">
            <div class="modal-container">
                <div class="modal-header">
                    <h3>Manage Inventory Alert</h3>
                    <button type="button" class="modal-close" onclick="hideManageModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert-item-info">
                        <h4 id="modalItemName">Item Name</h4>
                        <div class="alert-details">
                            <div class="detail-row">
                                <span class="detail-label">Alert Type:</span>
                                <span id="modalAlertType" class="badge">Type</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Current Quantity:</span>
                                <span id="modalQuantity">0</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Reorder Level:</span>
                                <span id="modalReorderLevel">0</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Expiry Date:</span>
                                <span id="modalExpiryDate">N/A</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Days Until Expiry:</span>
                                <span id="modalDaysUntilExpiry">0</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-actions-section">
                        <h5>Quick Actions</h5>
                        <div class="action-buttons">
                            <button id="restockBtn" class="btn btn-primary" onclick="handleRestock()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v20m8-12H4"/>
                                </svg>
                                Restock Item
                            </button>
                            <button id="discountBtn" class="btn btn-secondary" onclick="handleDiscount()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v20m8-12H4"/>
                                </svg>
                                Apply Discount
                            </button>
                            <button id="removeBtn" class="btn btn-danger" onclick="handleRemove()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18m-2 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                </svg>
                                Remove Item
                            </button>
                        </div>
                    </div>
                    
                    <div class="modal-form-section" id="restockForm" style="display: none;">
                        <h5>Restock Item</h5>
                        <form id="restockFormElement">
                            <div class="form-group">
                                <label for="restockQuantity">Quantity to Add:</label>
                                <input type="number" id="restockQuantity" name="restock_quantity" min="0.01" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="restockCost">Cost per Unit:</label>
                                <input type="number" id="restockCost" name="restock_cost" min="0" step="0.01" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Add Stock</button>
                                <button type="button" class="btn btn-secondary" onclick="hideManageModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="modal-form-section" id="discountForm" style="display: none;">
                        <h5>Apply Discount</h5>
                        <form id="discountFormElement">
                            <div class="form-group">
                                <label for="discountPercentage">Discount Percentage:</label>
                                <input type="number" id="discountPercentage" name="discount_percentage" min="1" max="100" required>
                            </div>
                            <div class="form-group">
                                <label for="discountReason">Reason:</label>
                                <textarea id="discountReason" name="discount_reason" rows="3" placeholder="e.g., Near expiry, clearance sale"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Apply Discount</button>
                                <button type="button" class="btn btn-secondary" onclick="hideManageModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Items Section -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">Recent Items</h2>
                <a href="<?= htmlspecialchars($baseUrl) ?>/grocery/items/grocery_items.php" class="section-link">View All →</a>
            </div>

            <?php if ($items_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Cost Price</th>
                                <th>Selling Price</th>
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
                                <td><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($item['quantity'], 2) . ' ' . htmlspecialchars($item['unit']); ?></td>
                                <td>₱<?php echo number_format($item['cost_price'], 2); ?></td>
                                <td>₱<?php echo number_format($item['selling_price'], 2); ?></td>
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
                                    <a href="<?= htmlspecialchars($baseUrl) ?>/grocery/items/edit_item.php?id=<?php echo $item['item_id']; ?>" class="action-btn">Edit</a>
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

    </div>
</main>

<script>
let currentItemId = null;

function showManageModal(itemId, itemName, alertType, quantity, reorderLevel, expiryDate, daysUntilExpiry) {
    currentItemId = itemId;
    
    // Populate modal with item details
    document.getElementById('modalItemName').textContent = itemName;
    document.getElementById('modalQuantity').textContent = quantity + ' units';
    document.getElementById('modalReorderLevel').textContent = reorderLevel + ' units';
    document.getElementById('modalExpiryDate').textContent = expiryDate;
    document.getElementById('modalDaysUntilExpiry').textContent = daysUntilExpiry >= 0 ? daysUntilExpiry + ' days' : 'Expired ' + Math.abs(daysUntilExpiry) + ' days ago';
    
    // Set alert type badge
    const alertBadge = document.getElementById('modalAlertType');
    alertBadge.textContent = alertType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    alertBadge.className = 'badge';
    switch(alertType) {
        case 'EXPIRED':
            alertBadge.classList.add('badge-cancelled');
            break;
        case 'NEAR_EXPIRY':
            alertBadge.classList.add('badge-pending');
            break;
        case 'LOW_STOCK':
            alertBadge.classList.add('badge-personal');
            break;
        default:
            alertBadge.classList.add('badge-delivered');
    }
    
    // Show/hide relevant action buttons based on alert type
    const restockBtn = document.getElementById('restockBtn');
    const discountBtn = document.getElementById('discountBtn');
    const removeBtn = document.getElementById('removeBtn');
    
    // Show all buttons by default
    restockBtn.style.display = 'inline-flex';
    discountBtn.style.display = 'inline-flex';
    removeBtn.style.display = 'inline-flex';
    
    // Hide restock for expired items
    if (alertType === 'EXPIRED') {
        restockBtn.style.display = 'none';
    }
    
    // Hide discount for low stock items
    if (alertType === 'LOW_STOCK') {
        discountBtn.style.display = 'none';
    }
    
    // Show modal
    document.getElementById('manageAlertModal').style.display = 'flex';
    setTimeout(() => {
        document.getElementById('manageAlertModal').classList.add('show');
    }, 10);
}

function hideManageModal() {
    document.getElementById('manageAlertModal').classList.remove('show');
    setTimeout(() => {
        document.getElementById('manageAlertModal').style.display = 'none';
        // Reset forms
        document.getElementById('restockForm').style.display = 'none';
        document.getElementById('discountForm').style.display = 'none';
        document.getElementById('restockFormElement').reset();
        document.getElementById('discountFormElement').reset();
    }, 300);
}

function handleRestock() {
    document.getElementById('restockForm').style.display = 'block';
    document.getElementById('discountForm').style.display = 'none';
}

function handleDiscount() {
    document.getElementById('discountForm').style.display = 'block';
    document.getElementById('restockForm').style.display = 'none';
}

function handleRemove() {
    if (confirm('Are you sure you want to remove this item from inventory? This action cannot be undone.')) {
        // Redirect to delete/remove functionality
        window.location.href = 'items/remove_item.php?id=' + currentItemId;
    }
}

// Handle form submissions
document.getElementById('restockFormElement').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('item_id', currentItemId);
    
    fetch('items/restock_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Item restocked successfully!', 'success');
            hideManageModal();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Failed to restock item', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while restocking', 'error');
    });
});

document.getElementById('discountFormElement').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('item_id', currentItemId);
    
    fetch('items/apply_discount.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Discount applied successfully!', 'success');
            hideManageModal();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Failed to apply discount', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while applying discount', 'error');
    });
});

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = 'alert alert-' + (type === 'success' ? 'success' : 'error');
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            ${type === 'success' 
                ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
                : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
            }
        </svg>
        <div>${message}</div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Close modal when clicking overlay
document.getElementById('manageAlertModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideManageModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideManageModal();
    }
});
</script>

<style>
/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
}

.modal-container {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.show .modal-container {
    transform: scale(1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
}

.modal-close {
    background: none;
    border: none;
    padding: 4px;
    border-radius: 4px;
    cursor: pointer;
    color: #6b7280;
    transition: color 0.2s ease;
}

.modal-close:hover {
    color: #374151;
}

.modal-body {
    padding: 24px;
}

.alert-item-info h4 {
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
}

.alert-details {
    margin-bottom: 24px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.modal-actions-section h5 {
    margin: 0 0 16px 0;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.action-buttons .btn {
    flex: 1;
    min-width: 120px;
    justify-content: center;
    padding: 10px 16px;
    font-size: 13px;
}

.modal-form-section {
    background: #f9fafb;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 16px;
}

.modal-form-section h5 {
    margin: 0 0 16px 0;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border: 1px solid;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.btn-primary:hover {
    background: #2563eb;
    border-color: #2563eb;
}

.btn-secondary {
    background: white;
    color: #374151;
    border-color: #d1d5db;
}

.btn-secondary:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.btn-danger {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
}

.btn-danger:hover {
    background: #dc2626;
    border-color: #dc2626;
}

@media (max-width: 640px) {
    .modal-container {
        width: 95%;
        margin: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        min-width: auto;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php $conn->close(); ?>