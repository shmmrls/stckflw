<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $baseUrl . '/user/customer/dashboard.php');
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

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';
$status_filter = $_GET['status'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

// Build query using the enhanced grocery_inventory_details view
$query = "
    SELECT gi.*, c.category_name, s.supplier_name, sp.unit_price as supplier_unit_price,
           sp.minimum_order_quantity, sp.lead_time_days
    FROM grocery_items gi
    LEFT JOIN categories c ON gi.category_id = c.category_id
    LEFT JOIN suppliers s ON gi.supplier_id = s.supplier_id
    LEFT JOIN supplier_products sp ON gi.supplier_product_id = sp.supplier_product_id
    WHERE gi.store_id = ?
";

$params = [$store_id];
$types = "i";

if (!empty($search)) {
    $query .= " AND (gi.item_name LIKE ? OR gi.barcode LIKE ? OR gi.sku LIKE ? OR gi.batch_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($category_filter)) {
    $query .= " AND gi.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if (!empty($supplier_filter)) {
    $query .= " AND gi.supplier_id = ?";
    $params[] = $supplier_filter;
    $types .= "i";
}

if (!empty($status_filter)) {
    $query .= " AND gi.expiry_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($stock_filter)) {
    if ($stock_filter === 'low') {
        $query .= " AND gi.quantity <= gi.reorder_level";
    } elseif ($stock_filter === 'out') {
        $query .= " AND gi.quantity = 0";
    }
}

$query .= " ORDER BY gi.date_added DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items_result = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Get suppliers for filter
$suppliers = $conn->query("
    SELECT DISTINCT s.* 
    FROM suppliers s
    INNER JOIN grocery_items gi ON s.supplier_id = gi.supplier_id
    WHERE gi.store_id = $store_id
    ORDER BY s.supplier_name
");

// Get enhanced summary stats
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN expiry_status = 'expired' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN expiry_status = 'near_expiry' THEN 1 ELSE 0 END) as near_expiry,
        SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock,
        SUM(quantity * selling_price) as total_value,
        SUM(quantity * cost_price) as total_cost,
        COUNT(DISTINCT supplier_id) as total_suppliers,
        COUNT(DISTINCT CASE WHEN batch_number IS NOT NULL THEN batch_number END) as tracked_batches
    FROM grocery_items
    WHERE store_id = ?
");
$stats_stmt->bind_param("i", $store_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$potential_profit = $stats['total_value'] - $stats['total_cost'];

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/dashboard.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<style>
.filters-section { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); padding: 30px; margin-bottom: 20px; }
.filters-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
.filter-group { display: flex; flex-direction: column; gap: 8px; }
.filter-label { font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.6); font-weight: 600; }
.filter-input, .filter-select { padding: 10px 15px; border: 1px solid rgba(0,0,0,0.15); background: #fafafa; font-family: 'Montserrat', sans-serif; font-size: 13px; color: #0a0a0a; transition: all 0.3s ease; }
.filter-input:focus, .filter-select:focus { outline: none; border-color: #0a0a0a; background: #ffffff; }
.filter-btn { padding: 10px 20px; background: #0a0a0a; color: #ffffff; border: none; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; font-weight: 500; cursor: pointer; transition: all 0.3s ease; }
.filter-btn:hover { background: #1a1a1a; }
.clear-filters { padding: 10px 20px; background: #fafafa; color: #0a0a0a; border: 1px solid rgba(0,0,0,0.15); text-decoration: none; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; font-weight: 500; display: inline-block; transition: all 0.3s ease; }
.clear-filters:hover { background: #ffffff; border-color: #0a0a0a; }
.stats-mini { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-mini { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); padding: 20px; text-align: center; }
.stat-mini-value { font-size: 24px; font-weight: 600; color: #0a0a0a; margin-bottom: 5px; }
.stat-mini-label { font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.5); }
.item-actions { display: flex; gap: 8px; }
.btn-small { padding: 6px 12px; font-size: 9px; letter-spacing: 1px; text-transform: uppercase; font-weight: 500; text-decoration: none; border: 1px solid; transition: all 0.3s ease; display: inline-block; }
.btn-edit { background: #ffffff; color: #0a0a0a; border-color: rgba(0,0,0,0.15); }
.btn-edit:hover { background: #0a0a0a; color: #ffffff; }
.btn-delete { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.btn-delete:hover { background: #b91c1c; color: #ffffff; border-color: #b91c1c; }
.stock-low { color: #c2410c; font-weight: 600; }
.stock-out { color: #b91c1c; font-weight: 600; }
.batch-tag { display: inline-block; background: #f0f9ff; color: #0369a1; padding: 2px 8px; border-radius: 3px; font-size: 10px; margin-top: 4px; }
.supplier-tag { display: inline-block; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 3px; font-size: 10px; margin-top: 4px; }
@media (max-width: 1200px) {
    .filters-grid { grid-template-columns: 1fr 1fr 1fr; }
    .filter-group.full-width { grid-column: 1 / -1; }
}
@media (max-width: 768px) {
    .filters-grid { grid-template-columns: 1fr; }
    .item-actions { flex-direction: column; }
    .btn-small { text-align: center; }
}
</style>

<main class="dashboard-page">
    <div class="dashboard-container">

        <!-- Page Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="dashboard-title">Inventory Management</h1>
                    <p class="dashboard-subtitle">Manage your store's product inventory with supplier tracking</p>
                </div>
                <div class="header-actions">
                    <a href="<?= htmlspecialchars($baseUrl) ?>/grocery/items/add_item.php" class="action-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add Item
                    </a>
                </div>
            </div>
        </div>

        <!-- Enhanced Quick Stats -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo number_format($stats['total_items']); ?></div>
                <div class="stat-mini-label">Total Items</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value" style="color: #b91c1c;"><?php echo number_format($stats['expired']); ?></div>
                <div class="stat-mini-label">Expired</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value" style="color: #c2410c;"><?php echo number_format($stats['near_expiry']); ?></div>
                <div class="stat-mini-label">Near Expiry</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value" style="color: #0369a1;"><?php echo number_format($stats['low_stock']); ?></div>
                <div class="stat-mini-label">Low Stock</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value">₱<?php echo number_format($stats['total_value'], 2); ?></div>
                <div class="stat-mini-label">Total Value</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value" style="color: #059669;">₱<?php echo number_format($potential_profit, 2); ?></div>
                <div class="stat-mini-label">Potential Profit</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo number_format($stats['total_suppliers']); ?></div>
                <div class="stat-mini-label">Suppliers</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo number_format($stats['tracked_batches']); ?></div>
                <div class="stat-mini-label">Tracked Batches</div>
            </div>
        </div>

        <!-- Enhanced Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Item name, barcode, SKU, batch" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo ($category_filter == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Supplier</label>
                        <select name="supplier" class="filter-select">
                            <option value="">All Suppliers</option>
                            <?php 
                            $suppliers->data_seek(0);
                            while ($sup = $suppliers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $sup['supplier_id']; ?>" <?php echo ($supplier_filter == $sup['supplier_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sup['supplier_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="fresh" <?php echo ($status_filter === 'fresh') ? 'selected' : ''; ?>>Fresh</option>
                            <option value="near_expiry" <?php echo ($status_filter === 'near_expiry') ? 'selected' : ''; ?>>Near Expiry</option>
                            <option value="expired" <?php echo ($status_filter === 'expired') ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Stock Level</label>
                        <select name="stock" class="filter-select">
                            <option value="">All Stock</option>
                            <option value="low" <?php echo ($stock_filter === 'low') ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo ($stock_filter === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="submit" class="filter-btn">Filter</button>
                    </div>
                </div>
            </form>
            
            <?php if (!empty($search) || !empty($category_filter) || !empty($supplier_filter) || !empty($status_filter) || !empty($stock_filter)): ?>
                <div style="margin-top: 15px;">
                    <a href="grocery_items.php" class="clear-filters">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Items List -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">Inventory Items (<?php echo $items_result->num_rows; ?>)</h2>
            </div>

            <?php if ($items_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Details</th>
                                <th>Supplier</th>
                                <th>Stock</th>
                                <th>Pricing</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $items_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <?php if ($item['barcode']): ?>
                                        <br><small style="color: rgba(0,0,0,0.5);">Barcode: <?php echo htmlspecialchars($item['barcode']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($item['sku']): ?>
                                        <br><small style="color: rgba(0,0,0,0.5);">SKU: <?php echo htmlspecialchars($item['sku']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($item['batch_number']): ?>
                                        <br><span class="batch-tag">Batch: <?php echo htmlspecialchars($item['batch_number']); ?></span>
                                    <?php endif; ?>
                                    <br><small style="color: rgba(0,0,0,0.5);"><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <?php if ($item['supplier_name']): ?>
                                        <span class="supplier-tag"><?php echo htmlspecialchars($item['supplier_name']); ?></span>
                                        <?php if ($item['supplier_unit_price']): ?>
                                            <br><small style="color: rgba(0,0,0,0.5);">Supplier Price: ₱<?php echo number_format($item['supplier_unit_price'], 2); ?></small>
                                        <?php endif; ?>
                                        <?php if ($item['lead_time_days']): ?>
                                            <br><small style="color: rgba(0,0,0,0.5);">Lead time: <?php echo $item['lead_time_days']; ?> days</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small style="color: rgba(0,0,0,0.5);">N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $qty = number_format($item['quantity'], 2);
                                    $reorder = $item['reorder_level'];
                                    if ($item['quantity'] == 0) {
                                        echo "<span class='stock-out'>{$qty} {$item['unit']}</span>";
                                    } elseif ($item['quantity'] <= $reorder) {
                                        echo "<span class='stock-low'>{$qty} {$item['unit']}</span>";
                                    } else {
                                        echo "{$qty} {$item['unit']}";
                                    }
                                    ?>
                                    <br><small style="color: rgba(0,0,0,0.5);">Reorder at: <?php echo number_format($item['reorder_level'], 2); ?></small>
                                </td>
                                <td>
                                    <strong>₱<?php echo number_format($item['selling_price'], 2); ?></strong>
                                    <br><small style="color: rgba(0,0,0,0.5);">Cost: ₱<?php echo number_format($item['cost_price'], 2); ?></small>
                                    <?php 
                                    $profit = $item['selling_price'] - $item['cost_price'];
                                    $margin = $item['cost_price'] > 0 ? ($profit / $item['cost_price'] * 100) : 0;
                                    ?>
                                    <br><small style="color: <?php echo $profit > 0 ? '#059669' : '#b91c1c'; ?>;">
                                        Profit: ₱<?php echo number_format($profit, 2); ?> (<?php echo number_format($margin, 1); ?>%)
                                    </small>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($item['expiry_date'])); ?>
                                    <?php if ($item['received_date']): ?>
                                        <br><small style="color: rgba(0,0,0,0.5);">Received: <?php echo date('M d, Y', strtotime($item['received_date'])); ?></small>
                                    <?php endif; ?>
                                </td>
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
                                    <div class="item-actions">
                                        <a href="edit_item.php?id=<?php echo $item['item_id']; ?>" class="btn-small btn-edit">Edit</a>
                                        <a href="delete_item.php?id=<?php echo $item['item_id']; ?>" class="btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this item?');">Delete</a>
                                    </div>
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
                    <p>No items found. <?php echo (!empty($search) || !empty($category_filter) || !empty($supplier_filter) || !empty($status_filter) || !empty($stock_filter)) ? 'Try adjusting your filters.' : 'Start by adding items to your inventory!'; ?></p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>