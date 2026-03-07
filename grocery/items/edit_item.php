<?php
require_once __DIR__ . '/../../includes/admin_auth_check.php';

$conn    = getDBConnection();
$user_id = getCurrentUserId();

$store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
$store_stmt->bind_param("i", $user_id);
$store_stmt->execute();
$store_id = $store_stmt->get_result()->fetch_assoc()['store_id'];

$item_id = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
if (!$item_id) { header('Location: grocery_items.php'); exit; }

// ── Handle POST (save) ────────────────────────────────────
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name     = trim($_POST['item_name']     ?? '');
    $barcode       = trim($_POST['barcode']       ?? '') ?: null;
    $sku           = trim($_POST['sku']           ?? '') ?: null;
    $category_id   = (int)($_POST['category_id'] ?? 0)  ?: null;
    $quantity      = (float)($_POST['quantity']   ?? 0);
    $unit          = trim($_POST['unit']          ?? '') ?: null;
    $cost_price    = (float)($_POST['cost_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $reorder_level = (float)($_POST['reorder_level'] ?? 0);
    $expiry_date   = trim($_POST['expiry_date']   ?? '') ?: null;

    if (!$item_name) {
        $error_msg = 'Item name is required.';
    } else {
        // Verify ownership
        $check = $conn->prepare("SELECT item_id FROM grocery_items WHERE item_id = ? AND store_id = ?");
        $check->bind_param("ii", $item_id, $store_id);
        $check->execute();

        if (!$check->get_result()->fetch_assoc()) {
            $error_msg = 'Item not found or access denied.';
        } else {
            $stmt = $conn->prepare("
                UPDATE grocery_items
                SET item_name     = ?,
                    barcode       = ?,
                    sku           = ?,
                    category_id   = ?,
                    quantity      = ?,
                    unit          = ?,
                    cost_price    = ?,
                    selling_price = ?,
                    reorder_level = ?,
                    expiry_date   = ?,
                    last_updated  = NOW()
                WHERE item_id = ? AND store_id = ?
            ");
            $stmt->bind_param(
                "sssiisdddsii",
                $item_name, $barcode, $sku, $category_id,
                $quantity, $unit, $cost_price, $selling_price,
                $reorder_level, $expiry_date,
                $item_id, $store_id
            );

            if ($stmt->execute()) {
                $success_msg = 'Item updated successfully.';
            } else {
                $error_msg = 'Database error: ' . $conn->error;
            }
        }
    }
}

// ── Fetch current item data ───────────────────────────────
$fetch = $conn->prepare("
    SELECT gi.*, c.category_name, s.supplier_name,
           sp.unit_price AS supplier_unit_price, sp.lead_time_days
    FROM grocery_items gi
    LEFT JOIN categories c         ON gi.category_id        = c.category_id
    LEFT JOIN suppliers s          ON gi.supplier_id         = s.supplier_id
    LEFT JOIN supplier_products sp ON gi.supplier_product_id = sp.supplier_product_id
    WHERE gi.item_id = ? AND gi.store_id = ?
");
$fetch->bind_param("ii", $item_id, $store_id);
$fetch->execute();
$item = $fetch->get_result()->fetch_assoc();

if (!$item) { header('Location: grocery_items.php'); exit; }

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/dashboard.css">';
require_once __DIR__ . '/../../includes/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Edit page layout ────────────────────────────────────── */
.edit-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}
.form-card {
    background: #fff;
    border: 1px solid rgba(0,0,0,.08);
    padding: 36px;
}
.form-section-title {
    font-family: 'Playfair Display', serif;
    font-size: 16px;
    font-weight: 400;
    color: #0a0a0a;
    margin: 28px 0 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,0,0,.06);
}
.form-section-title:first-of-type { margin-top: 0; }
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
.form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
.form-group:last-child { margin-bottom: 0; }
.form-label {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,.6);
    font-weight: 600;
}
.form-label .req { color: #b91c1c; margin-left: 2px; }
.form-control {
    padding: 11px 15px;
    border: 1px solid rgba(0,0,0,.15);
    background: #fafafa;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #0a0a0a;
    transition: all .3s;
    width: 100%;
    box-sizing: border-box;
}
.form-control:focus { outline: none; border-color: #0a0a0a; background: #fff; }
.form-control[readonly] { background: #f0f0f0; color: rgba(0,0,0,.5); cursor: not-allowed; }

/* Sidebar summary card */
.summary-card {
    background: #fff;
    border: 1px solid rgba(0,0,0,.08);
    padding: 28px;
    position: sticky;
    top: 110px;
}
.summary-card-title {
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    font-weight: 400;
    color: #0a0a0a;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(0,0,0,.06);
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px 0;
    border-bottom: 1px solid rgba(0,0,0,.04);
    font-size: 12px;
    gap: 12px;
}
.summary-row:last-of-type { border-bottom: none; }
.summary-label { color: rgba(0,0,0,.5); letter-spacing: .3px; white-space: nowrap; }
.summary-value { font-weight: 600; color: #0a0a0a; text-align: right; word-break: break-word; }
.supplier-tag { display:inline-block; background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:3px; font-size:10px; }
.batch-tag    { display:inline-block; background:#f0f9ff; color:#0369a1; padding:2px 8px; border-radius:3px; font-size:10px; }

/* Alert banners */
.alert { padding: 14px 18px; font-size: 13px; margin-bottom: 24px; border-left: 3px solid; }
.alert-success { background: #f0fdf4; color: #166534; border-color: #166534; }
.alert-error   { background: #fef2f2; color: #b91c1c; border-color: #b91c1c; }

/* Action bar */
.form-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid rgba(0,0,0,.06);
    flex-wrap: wrap;
}
.btn {
    padding: 11px 24px;
    border: none;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all .3s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    font-family: 'Montserrat', sans-serif;
}
.btn-primary   { background: #0a0a0a; color: #fff; }
.btn-primary:hover { background: #1a1a1a; transform: translateY(-1px); }
.btn-secondary { background: #fafafa; color: #0a0a0a; border: 1px solid rgba(0,0,0,.15); }
.btn-secondary:hover { background: #fff; border-color: #0a0a0a; }
.btn:disabled  { opacity: .6; cursor: not-allowed; }

/* Live profit preview */
.profit-preview {
    background: #f9fafb;
    border: 1px solid rgba(0,0,0,.06);
    padding: 16px;
    margin-top: 4px;
    font-size: 12px;
}
.profit-positive { color: #059669; font-weight: 600; }
.profit-negative { color: #b91c1c; font-weight: 600; }

@media (max-width: 900px) {
    .edit-grid   { grid-template-columns: 1fr; }
    .summary-card { position: static; }
    .form-row    { grid-template-columns: 1fr; }
}
</style>

<main class="dashboard-page">
    <div class="dashboard-container">

        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="dashboard-title">Edit Item</h1>
                    <p class="dashboard-subtitle">
                        Updating <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                        <?php if ($item['sku']): ?>&nbsp;·&nbsp;SKU: <?= htmlspecialchars($item['sku']) ?><?php endif ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="grocery_items.php" class="action-btn" style="background:#fafafa;color:#0a0a0a;border:1px solid rgba(0,0,0,.15);">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Back to Inventory
                    </a>
                </div>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="edit-grid">

            <!-- ── Main form ─────────────────────────────── -->
            <form method="POST" action="edit_item.php" id="editForm">
                <input type="hidden" name="item_id" value="<?= $item_id ?>">

                <div class="form-card">

                    <!-- Identity -->
                    <p class="form-section-title">Item Identity</p>
                    <div class="form-group">
                        <label class="form-label" for="item_name">Item Name <span class="req">*</span></label>
                        <input type="text" class="form-control" id="item_name" name="item_name"
                               value="<?= htmlspecialchars($item['item_name']) ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="barcode">Barcode</label>
                            <input type="text" class="form-control" id="barcode" name="barcode"
                                   value="<?= htmlspecialchars($item['barcode'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="sku">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku"
                                   value="<?= htmlspecialchars($item['sku'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="category_id">Category</label>
                            <select class="form-control" id="category_id" name="category_id">
                                <option value="">— None —</option>
                                <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?= $cat['category_id'] ?>"
                                        <?= ($item['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="batch_display">Batch Number</label>
                            <input type="text" class="form-control" id="batch_display"
                                   value="<?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?>" readonly>
                        </div>
                    </div>

                    <!-- Stock -->
                    <p class="form-section-title">Stock</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="quantity">Quantity <span class="req">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity"
                                   step="0.01" min="0"
                                   value="<?= htmlspecialchars($item['quantity']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="unit">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit"
                                   value="<?= htmlspecialchars($item['unit'] ?? '') ?>"
                                   placeholder="e.g. pcs, kg, L">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reorder_level">Reorder Level</label>
                        <input type="number" class="form-control" id="reorder_level" name="reorder_level"
                               step="0.01" min="0"
                               value="<?= htmlspecialchars($item['reorder_level'] ?? '') ?>">
                    </div>

                    <!-- Pricing -->
                    <p class="form-section-title">Pricing</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="cost_price">Cost Price (₱) <span class="req">*</span></label>
                            <input type="number" class="form-control" id="cost_price" name="cost_price"
                                   step="0.01" min="0"
                                   value="<?= htmlspecialchars($item['cost_price']) ?>"
                                   oninput="updateProfit()" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="selling_price">Selling Price (₱) <span class="req">*</span></label>
                            <input type="number" class="form-control" id="selling_price" name="selling_price"
                                   step="0.01" min="0"
                                   value="<?= htmlspecialchars($item['selling_price']) ?>"
                                   oninput="updateProfit()" required>
                        </div>
                    </div>
                    <div class="profit-preview" id="profitPreview"></div>

                    <!-- Dates -->
                    <p class="form-section-title">Dates</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="expiry_date">Expiry Date</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date"
                                   value="<?= htmlspecialchars($item['expiry_date'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="received_display">Received Date</label>
                            <input type="text" class="form-control" id="received_display"
                                   value="<?= $item['received_date'] ? date('M d, Y', strtotime($item['received_date'])) : 'N/A' ?>" readonly>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="saveBtn">Save Changes</button>
                        <a href="grocery_items.php" class="btn btn-secondary">Cancel</a>
                    </div>

                </div><!-- /form-card -->
            </form>

            <!-- ── Sidebar summary ────────────────────────── -->
            <aside>
                <div class="summary-card">
                    <p class="summary-card-title">Item Overview</p>

                    <?php if ($item['supplier_name']): ?>
                    <div class="summary-row">
                        <span class="summary-label">Supplier</span>
                        <span class="summary-value"><span class="supplier-tag"><?= htmlspecialchars($item['supplier_name']) ?></span></span>
                    </div>
                    <?php if ($item['supplier_unit_price']): ?>
                    <div class="summary-row">
                        <span class="summary-label">Supplier Price</span>
                        <span class="summary-value">₱<?= number_format($item['supplier_unit_price'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($item['lead_time_days']): ?>
                    <div class="summary-row">
                        <span class="summary-label">Lead Time</span>
                        <span class="summary-value"><?= $item['lead_time_days'] ?> days</span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($item['batch_number']): ?>
                    <div class="summary-row">
                        <span class="summary-label">Batch</span>
                        <span class="summary-value"><span class="batch-tag"><?= htmlspecialchars($item['batch_number']) ?></span></span>
                    </div>
                    <?php endif; ?>

                    <div class="summary-row">
                        <span class="summary-label">Expiry Status</span>
                        <span class="summary-value">
                            <?php
                            $badge = match($item['expiry_status']) {
                                'fresh'       => 'badge-delivered',
                                'near_expiry' => 'badge-pending',
                                default       => 'badge-cancelled',
                            };
                            ?>
                            <span class="badge <?= $badge ?>"><?= ucfirst(str_replace('_', ' ', $item['expiry_status'])) ?></span>
                        </span>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Current Stock</span>
                        <span class="summary-value">
                            <?php
                            $qty = number_format($item['quantity'], 2) . ' ' . ($item['unit'] ?? '');
                            if ($item['quantity'] == 0)                            echo "<span style='color:#b91c1c'>$qty</span>";
                            elseif ($item['quantity'] <= $item['reorder_level'])   echo "<span style='color:#c2410c'>$qty</span>";
                            else                                                   echo $qty;
                            ?>
                        </span>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Date Added</span>
                        <span class="summary-value"><?= date('M d, Y', strtotime($item['date_added'])) ?></span>
                    </div>

                    <?php if ($item['last_updated']): ?>
                    <div class="summary-row">
                        <span class="summary-label">Last Updated</span>
                        <span class="summary-value"><?= date('M d, Y', strtotime($item['last_updated'])) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="summary-row" style="margin-top:6px;padding-top:14px;border-top:1px solid rgba(0,0,0,.08)">
                        <span class="summary-label">Inventory Value</span>
                        <span class="summary-value">₱<?= number_format($item['quantity'] * $item['selling_price'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Total Cost</span>
                        <span class="summary-value">₱<?= number_format($item['quantity'] * $item['cost_price'], 2) ?></span>
                    </div>
                    <?php $tp = ($item['selling_price'] - $item['cost_price']) * $item['quantity']; ?>
                    <div class="summary-row">
                        <span class="summary-label">Total Profit</span>
                        <span class="summary-value" style="color:<?= $tp >= 0 ? '#059669' : '#b91c1c' ?>">₱<?= number_format($tp, 2) ?></span>
                    </div>
                </div>
            </aside>

        </div><!-- /edit-grid -->
    </div>
</main>

<script>
function updateProfit() {
    const cost   = parseFloat(document.getElementById('cost_price').value)    || 0;
    const sell   = parseFloat(document.getElementById('selling_price').value) || 0;
    const profit = sell - cost;
    const margin = cost > 0 ? (profit / cost * 100).toFixed(1) : 0;
    const el     = document.getElementById('profitPreview');
    const cls    = profit >= 0 ? 'profit-positive' : 'profit-negative';
    const sign   = profit >= 0 ? '+' : '';
    el.innerHTML = `Margin per unit: <span class="${cls}">${sign}₱${profit.toFixed(2)} (${sign}${margin}%)</span>`;
}
updateProfit(); // run on load

// Prevent double-submit
document.getElementById('editForm').addEventListener('submit', function () {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving…';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>