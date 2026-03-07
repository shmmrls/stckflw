<?php
require_once __DIR__ . '/../../includes/admin_auth_check.php';

$conn    = getDBConnection();
$user_id = getCurrentUserId();

$store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
$store_stmt->bind_param("i", $user_id);
$store_stmt->execute();
$store_id = $store_stmt->get_result()->fetch_assoc()['store_id'];

// ── AJAX: Delete ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');

    $item_id = (int) ($_POST['item_id'] ?? 0);
    if (!$item_id) { echo json_encode(['success' => false, 'message' => 'Invalid item.']); exit; }

    $check = $conn->prepare("SELECT item_id FROM grocery_items WHERE item_id = ? AND store_id = ?");
    $check->bind_param("ii", $item_id, $store_id);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) { echo json_encode(['success' => false, 'message' => 'Item not found.']); exit; }

    $stmt = $conn->prepare("DELETE FROM grocery_items WHERE item_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $item_id, $store_id);
    echo $stmt->execute()
        ? json_encode(['success' => true,  'message' => 'Item deleted successfully.'])
        : json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);

    $conn->close(); exit;
}

// ── Filters ───────────────────────────────────────────────
$search          = $_GET['search']   ?? '';
$category_filter = $_GET['category'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';
$status_filter   = $_GET['status']   ?? '';
$stock_filter    = $_GET['stock']    ?? '';

$query  = "
    SELECT gi.*, c.category_name, s.supplier_name,
           sp.unit_price AS supplier_unit_price,
           sp.minimum_order_quantity, sp.lead_time_days
    FROM grocery_items gi
    LEFT JOIN categories c         ON gi.category_id         = c.category_id
    LEFT JOIN suppliers s          ON gi.supplier_id          = s.supplier_id
    LEFT JOIN supplier_products sp ON gi.supplier_product_id  = sp.supplier_product_id
    WHERE gi.store_id = ?
";
$params = [$store_id]; $types = "i";

if (!empty($search)) {
    $query .= " AND (gi.item_name LIKE ? OR gi.barcode LIKE ? OR gi.sku LIKE ? OR gi.batch_number LIKE ?)";
    $sp = "%$search%"; array_push($params, $sp, $sp, $sp, $sp); $types .= "ssss";
}
if (!empty($category_filter)) { $query .= " AND gi.category_id = ?";   $params[] = $category_filter; $types .= "i"; }
if (!empty($supplier_filter)) { $query .= " AND gi.supplier_id = ?";   $params[] = $supplier_filter; $types .= "i"; }
if (!empty($status_filter))   { $query .= " AND gi.expiry_status = ?"; $params[] = $status_filter;   $types .= "s"; }
if ($stock_filter === 'low')  { $query .= " AND gi.quantity <= gi.reorder_level"; }
elseif ($stock_filter === 'out') { $query .= " AND gi.quantity = 0"; }
$query .= " ORDER BY gi.date_added DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items_result = $stmt->get_result();

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
$suppliers  = $conn->query("
    SELECT DISTINCT s.* FROM suppliers s
    INNER JOIN grocery_items gi ON s.supplier_id = gi.supplier_id
    WHERE gi.store_id = $store_id ORDER BY s.supplier_name
");

$stats_stmt = $conn->prepare("
    SELECT COUNT(*) as total_items,
        SUM(CASE WHEN expiry_status = 'expired'     THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN expiry_status = 'near_expiry' THEN 1 ELSE 0 END) as near_expiry,
        SUM(CASE WHEN quantity <= reorder_level      THEN 1 ELSE 0 END) as low_stock,
        SUM(quantity * selling_price) as total_value,
        SUM(quantity * cost_price)    as total_cost,
        COUNT(DISTINCT supplier_id)   as total_suppliers,
        COUNT(DISTINCT CASE WHEN batch_number IS NOT NULL THEN batch_number END) as tracked_batches
    FROM grocery_items WHERE store_id = ?
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
.filters-section{background:#fff;border:1px solid rgba(0,0,0,.08);padding:30px;margin-bottom:20px}
.filters-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:15px;align-items:end}
.filter-group{display:flex;flex-direction:column;gap:8px}
.filter-label{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:rgba(0,0,0,.6);font-weight:600}
.filter-input,.filter-select{padding:10px 15px;border:1px solid rgba(0,0,0,.15);background:#fafafa;font-family:'Montserrat',sans-serif;font-size:13px;color:#0a0a0a;transition:all .3s}
.filter-input:focus,.filter-select:focus{outline:none;border-color:#0a0a0a;background:#fff}
.filter-btn{padding:10px 20px;background:#0a0a0a;color:#fff;border:none;font-size:11px;letter-spacing:1px;text-transform:uppercase;font-weight:500;cursor:pointer;transition:all .3s}
.filter-btn:hover{background:#1a1a1a}
.clear-filters{padding:10px 20px;background:#fafafa;color:#0a0a0a;border:1px solid rgba(0,0,0,.15);text-decoration:none;font-size:11px;letter-spacing:1px;text-transform:uppercase;font-weight:500;display:inline-block;transition:all .3s}
.clear-filters:hover{background:#fff;border-color:#0a0a0a}
.stats-mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px}
.stat-mini{background:#fff;border:1px solid rgba(0,0,0,.08);padding:20px;text-align:center}
.stat-mini-value{font-size:24px;font-weight:600;color:#0a0a0a;margin-bottom:5px}
.stat-mini-label{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:rgba(0,0,0,.5)}
.stock-low{color:#c2410c;font-weight:600}.stock-out{color:#b91c1c;font-weight:600}
.batch-tag{display:inline-block;background:#f0f9ff;color:#0369a1;padding:2px 8px;border-radius:3px;font-size:10px;margin-top:4px}
.supplier-tag{display:inline-block;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:3px;font-size:10px;margin-top:4px}
.item-actions{display:flex;gap:8px}
.btn-small{padding:6px 12px;font-size:9px;letter-spacing:1px;text-transform:uppercase;font-weight:500;text-decoration:none;border:1px solid;transition:all .3s;display:inline-block;cursor:pointer;background:none}
.btn-edit{background:#fff;color:#0a0a0a;border-color:rgba(0,0,0,.15)}.btn-edit:hover{background:#0a0a0a;color:#fff}
.btn-delete{background:#fef2f2;color:#b91c1c;border-color:#fecaca}.btn-delete:hover{background:#b91c1c;color:#fff;border-color:#b91c1c}
/* Modal */
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;visibility:hidden;transition:all .3s}
.modal-overlay.show{opacity:1;visibility:visible}
.modal-container{background:#fff;border-radius:8px;max-width:460px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);transform:scale(.9);transition:transform .3s}
.modal-overlay.show .modal-container{transform:scale(1)}
.modal-header{padding:24px 24px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(0,0,0,.08)}
.modal-header h3{font-family:'Playfair Display',serif;font-size:20px;font-weight:400;color:#0a0a0a;margin:0}
.modal-close{background:none;border:none;padding:8px;cursor:pointer;color:rgba(0,0,0,.4);border-radius:4px;transition:all .2s}
.modal-close:hover{background:rgba(0,0,0,.1);color:#0a0a0a}
.modal-body{padding:24px}
.btn{padding:10px 20px;border:none;font-size:11px;letter-spacing:1px;text-transform:uppercase;font-weight:500;cursor:pointer;transition:all .3s;text-decoration:none;display:inline-block;text-align:center}
.btn-secondary{background:#fafafa;color:#0a0a0a;border:1px solid rgba(0,0,0,.15)}.btn-secondary:hover{background:#fff;border-color:#0a0a0a}
.btn-danger{background:#b91c1c;color:#fff}.btn-danger:hover{background:#dc2626}
.btn:disabled{opacity:.6;cursor:not-allowed}
/* Toast */
.toast{position:fixed;bottom:30px;right:30px;padding:14px 20px;border-radius:6px;font-size:13px;font-weight:500;color:#fff;z-index:9999;transform:translateY(20px);opacity:0;transition:all .3s;pointer-events:none;max-width:320px}
.toast.show{transform:translateY(0);opacity:1}
.toast-success{background:#059669}.toast-error{background:#b91c1c}
@media(max-width:1200px){.filters-grid{grid-template-columns:1fr 1fr 1fr}}
@media(max-width:768px){.filters-grid{grid-template-columns:1fr}.item-actions{flex-direction:column}}
</style>

<main class="dashboard-page">
    <div class="dashboard-container">

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

        <!-- Stats -->
        <div class="stats-mini">
            <div class="stat-mini"><div class="stat-mini-value"><?= number_format($stats['total_items']) ?></div><div class="stat-mini-label">Total Items</div></div>
            <div class="stat-mini"><div class="stat-mini-value" style="color:#b91c1c"><?= number_format($stats['expired']) ?></div><div class="stat-mini-label">Expired</div></div>
            <div class="stat-mini"><div class="stat-mini-value" style="color:#c2410c"><?= number_format($stats['near_expiry']) ?></div><div class="stat-mini-label">Near Expiry</div></div>
            <div class="stat-mini"><div class="stat-mini-value" style="color:#0369a1"><?= number_format($stats['low_stock']) ?></div><div class="stat-mini-label">Low Stock</div></div>
            <div class="stat-mini"><div class="stat-mini-value">₱<?= number_format($stats['total_value'], 2) ?></div><div class="stat-mini-label">Total Value</div></div>
            <div class="stat-mini"><div class="stat-mini-value" style="color:#059669">₱<?= number_format($potential_profit, 2) ?></div><div class="stat-mini-label">Potential Profit</div></div>
            <div class="stat-mini"><div class="stat-mini-value"><?= number_format($stats['total_suppliers']) ?></div><div class="stat-mini-label">Suppliers</div></div>
            <div class="stat-mini"><div class="stat-mini-value"><?= number_format($stats['tracked_batches']) ?></div><div class="stat-mini-label">Tracked Batches</div></div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Item name, barcode, SKU, batch" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($category_filter == $cat['category_id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Supplier</label>
                        <select name="supplier" class="filter-select">
                            <option value="">All Suppliers</option>
                            <?php $suppliers->data_seek(0); while ($sup = $suppliers->fetch_assoc()): ?>
                                <option value="<?= $sup['supplier_id'] ?>" <?= ($supplier_filter == $sup['supplier_id']) ? 'selected' : '' ?>><?= htmlspecialchars($sup['supplier_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="fresh"       <?= ($status_filter === 'fresh')       ? 'selected' : '' ?>>Fresh</option>
                            <option value="near_expiry" <?= ($status_filter === 'near_expiry') ? 'selected' : '' ?>>Near Expiry</option>
                            <option value="expired"     <?= ($status_filter === 'expired')     ? 'selected' : '' ?>>Expired</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Stock Level</label>
                        <select name="stock" class="filter-select">
                            <option value="">All Stock</option>
                            <option value="low" <?= ($stock_filter === 'low') ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out" <?= ($stock_filter === 'out') ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="submit" class="filter-btn">Filter</button>
                    </div>
                </div>
            </form>
            <?php if (!empty($search) || !empty($category_filter) || !empty($supplier_filter) || !empty($status_filter) || !empty($stock_filter)): ?>
                <div style="margin-top:15px;"><a href="grocery_items.php" class="clear-filters">Clear Filters</a></div>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">Inventory Items (<?= $items_result->num_rows ?>)</h2>
            </div>

            <?php if ($items_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Details</th><th>Supplier</th><th>Stock</th>
                                <th>Pricing</th><th>Expiry</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($item = $items_result->fetch_assoc()): ?>
                            <tr id="row-<?= $item['item_id'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                    <?php if ($item['barcode']): ?><br><small style="color:rgba(0,0,0,.5)">Barcode: <?= htmlspecialchars($item['barcode']) ?></small><?php endif ?>
                                    <?php if ($item['sku']):     ?><br><small style="color:rgba(0,0,0,.5)">SKU: <?= htmlspecialchars($item['sku']) ?></small><?php endif ?>
                                    <?php if ($item['batch_number']): ?><br><span class="batch-tag">Batch: <?= htmlspecialchars($item['batch_number']) ?></span><?php endif ?>
                                    <br><small style="color:rgba(0,0,0,.5)"><?= htmlspecialchars($item['category_name'] ?? 'N/A') ?></small>
                                </td>
                                <td>
                                    <?php if ($item['supplier_name']): ?>
                                        <span class="supplier-tag"><?= htmlspecialchars($item['supplier_name']) ?></span>
                                        <?php if ($item['supplier_unit_price']): ?><br><small style="color:rgba(0,0,0,.5)">Supplier Price: ₱<?= number_format($item['supplier_unit_price'], 2) ?></small><?php endif ?>
                                        <?php if ($item['lead_time_days']):      ?><br><small style="color:rgba(0,0,0,.5)">Lead time: <?= $item['lead_time_days'] ?> days</small><?php endif ?>
                                    <?php else: ?><small style="color:rgba(0,0,0,.5)">N/A</small><?php endif ?>
                                </td>
                                <td>
                                    <?php
                                    $qty = number_format($item['quantity'], 2);
                                    if ($item['quantity'] == 0)                             echo "<span class='stock-out'>{$qty} {$item['unit']}</span>";
                                    elseif ($item['quantity'] <= $item['reorder_level'])     echo "<span class='stock-low'>{$qty} {$item['unit']}</span>";
                                    else                                                     echo "{$qty} {$item['unit']}";
                                    ?>
                                    <br><small style="color:rgba(0,0,0,.5)">Reorder at: <?= number_format($item['reorder_level'], 2) ?></small>
                                </td>
                                <td>
                                    <strong>₱<?= number_format($item['selling_price'], 2) ?></strong>
                                    <br><small style="color:rgba(0,0,0,.5)">Cost: ₱<?= number_format($item['cost_price'], 2) ?></small>
                                    <?php $profit = $item['selling_price'] - $item['cost_price']; $margin = $item['cost_price'] > 0 ? ($profit / $item['cost_price'] * 100) : 0; ?>
                                    <br><small style="color:<?= $profit > 0 ? '#059669' : '#b91c1c' ?>">Profit: ₱<?= number_format($profit, 2) ?> (<?= number_format($margin, 1) ?>%)</small>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                    <?php if ($item['received_date']): ?><br><small style="color:rgba(0,0,0,.5)">Received: <?= date('M d, Y', strtotime($item['received_date'])) ?></small><?php endif ?>
                                </td>
                                <td>
                                    <?php $badge = match($item['expiry_status']) { 'fresh' => 'badge-delivered', 'near_expiry' => 'badge-pending', default => 'badge-cancelled' }; ?>
                                    <span class="badge <?= $badge ?>"><?= ucfirst(str_replace('_', ' ', $item['expiry_status'])) ?></span>
                                </td>
                                <td>
                                    <div class="item-actions">
                                        <a class="btn-small btn-edit"
                                           href="edit_item.php?item_id=<?= $item['item_id'] ?>">Edit</a>
                                        <button class="btn-small btn-delete"
                                                onclick="showDeleteModal(<?= $item['item_id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name'])) ?>')">Delete</button>
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
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <p>No items found. <?= (!empty($search)||!empty($category_filter)||!empty($supplier_filter)||!empty($status_filter)||!empty($stock_filter)) ? 'Try adjusting your filters.' : 'Start by adding items to your inventory!' ?></p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- Delete Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Delete Item</h3>
            <button type="button" class="modal-close" onclick="hideDeleteModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" style="text-align:center">
            <div style="width:48px;height:48px;margin:0 auto 20px;background:rgba(185,28,28,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#b91c1c">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
            </div>
            <h4 style="margin:0 0 12px;font-family:'Playfair Display',serif;font-size:18px;font-weight:400;color:#0a0a0a">Delete Item?</h4>
            <p style="margin:0 0 20px;font-size:14px;color:rgba(0,0,0,.6);line-height:1.6">
                Are you sure you want to delete <strong id="deleteItemName"></strong>? This action cannot be undone.
            </p>
            <input type="hidden" id="deleteItemId">
            <div style="display:flex;gap:12px;justify-content:center">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="submitDelete()">Delete Item</button>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast toast-${type} show`;
    setTimeout(() => t.classList.remove('show'), 3500);
}
function showDeleteModal(itemId, itemName) {
    document.getElementById('deleteItemId').value         = itemId;
    document.getElementById('deleteItemName').textContent = itemName;
    const m = document.getElementById('deleteModal');
    m.style.display = 'flex';
    setTimeout(() => m.classList.add('show'), 10);
}
function hideDeleteModal() {
    const m = document.getElementById('deleteModal');
    m.classList.remove('show');
    setTimeout(() => m.style.display = 'none', 300);
}
async function submitDelete() {
    const itemId = document.getElementById('deleteItemId').value;
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true; btn.textContent = 'Deleting…';
    try {
        const fd = new FormData();
        fd.append('action', 'delete'); fd.append('item_id', itemId);
        const data = await (await fetch(window.location.pathname, { method: 'POST', body: fd })).json();
        if (data.success) {
            hideDeleteModal();
            showToast(data.message, 'success');
            const row = document.getElementById(`row-${itemId}`);
            if (row) { row.style.transition = 'opacity .4s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 400); }
        } else { showToast(data.message || 'Delete failed.', 'error'); }
    } catch { showToast('Network error. Please try again.', 'error'); }
    finally  { btn.disabled = false; btn.textContent = 'Delete Item'; }
}
document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) hideDeleteModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') hideDeleteModal(); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>