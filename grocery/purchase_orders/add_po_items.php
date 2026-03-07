<?php
/**
 * Add Items to Purchase Order
 * Allows adding line items to a draft purchase order
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';

$conn      = getDBConnection();
$store_id  = $_SESSION['store_id']  ?? null;
$user_id   = $_SESSION['user_id']   ?? null;

$po_id = (int) ($_GET['po_id'] ?? 0);
$error_message = '';
$success_message = '';

// ─── Validate PO exists and belongs to store ──────────────────────────────────
$po_info = null;
if ($po_id && $store_id) {
    $stmt = $conn->prepare("
        SELECT po.po_id, po.po_number, po.status, po.supplier_id,
               s.supplier_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        WHERE po.po_id = ? AND po.store_id = ?
    ");
    $stmt->bind_param('ii', $po_id, $store_id);
    $stmt->execute();
    $po_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$po_info) {
    die("Invalid purchase order or access denied.");
}

if ($po_info['status'] !== 'draft') {
    die("Items can only be added to draft purchase orders.");
}

// ─── Get supplier products for this PO's supplier ─────────────────────────────
$supplier_products = [];
$stmt = $conn->prepare("
    SELECT sp.supplier_product_id, sp.supplier_sku, sp.product_name,
           sp.unit_price, sp.minimum_order_quantity, sp.lead_time_days,
           pc.catalog_id, pc.product_name as catalog_name,
           c.category_name
    FROM supplier_products sp
    LEFT JOIN product_catalog pc ON sp.catalog_id = pc.catalog_id
    LEFT JOIN categories c ON pc.category_id = c.category_id
    WHERE sp.supplier_id = ? AND sp.is_available = 1
    ORDER BY sp.product_name ASC
");
$stmt->bind_param('i', $po_info['supplier_id']);
$stmt->execute();
$supplier_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Get existing PO items ─────────────────────────────────────────────────────
$existing_items = [];
$stmt = $conn->prepare("
    SELECT poi.po_item_id, poi.supplier_product_id, poi.product_name,
           poi.quantity_ordered, poi.unit_price, poi.total_price,
           sp.supplier_sku
    FROM purchase_order_items poi
    LEFT JOIN supplier_products sp ON poi.supplier_product_id = sp.supplier_product_id
    WHERE poi.po_id = ?
    ORDER BY poi.created_at ASC
");
$stmt->bind_param('i', $po_id);
$stmt->execute();
$existing_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Handle Add Item Form ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_product_id = (int) ($_POST['supplier_product_id'] ?? 0);
    $quantity_ordered = (float) ($_POST['quantity_ordered'] ?? 0);
    $custom_product_name = trim($_POST['custom_product_name'] ?? '');
    $custom_unit_price = (float) ($_POST['custom_unit_price'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if ($supplier_product_id <= 0 && (empty($custom_product_name) || $custom_unit_price <= 0)) {
        $error_message = "Please select a supplier product OR provide custom product details.";
    } elseif ($quantity_ordered <= 0) {
        $error_message = "Quantity must be greater than 0.";
    } else {
        // Get product info
        $product_name = '';
        $unit_price = 0;
        $minimum_order_quantity = 0;

        if ($supplier_product_id > 0) {
            // Use supplier product
            $stmt = $conn->prepare("
                SELECT product_name, unit_price, minimum_order_quantity
                FROM supplier_products
                WHERE supplier_product_id = ? AND supplier_id = ? AND is_available = 1
            ");
            $stmt->bind_param('ii', $supplier_product_id, $po_info['supplier_id']);
            $stmt->execute();
            $product_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product_info) {
                $error_message = "Invalid supplier product selected.";
            } else {
                $product_name = $product_info['product_name'];
                $unit_price = $product_info['unit_price'];
                $minimum_order_quantity = $product_info['minimum_order_quantity'];

                if ($quantity_ordered < $minimum_order_quantity) {
                    $error_message = "Minimum order quantity is {$minimum_order_quantity}.";
                }
            }
        } else {
            // Use custom product
            $product_name = $custom_product_name;
            $unit_price = $custom_unit_price;
        }

        if (empty($error_message)) {
            // Add item to PO
            $insert = $conn->prepare("
                INSERT INTO purchase_order_items
                    (po_id, supplier_product_id, product_name, 
                     quantity_ordered, unit_price, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param('iisids', 
                $po_id, $supplier_product_id, $product_name,
                $quantity_ordered, $unit_price, $notes
            );

            if ($insert->execute()) {
                $success_message = "Item added to purchase order successfully!";
                
                // Update PO total
                $update_total = $conn->prepare("
                    UPDATE purchase_orders
                    SET total_amount = (
                        SELECT COALESCE(SUM(total_price), 0)
                        FROM purchase_order_items
                        WHERE po_id = ?
                    ),
                    grand_total = total_amount
                    WHERE po_id = ?
                ");
                $update_total->bind_param('ii', $po_id, $po_id);
                $update_total->execute();
                $update_total->close();

                // Refresh existing items
                $stmt = $conn->prepare("
                    SELECT poi.po_item_id, poi.supplier_product_id, poi.product_name,
                           poi.quantity_ordered, poi.unit_price, poi.total_price,
                           sp.supplier_sku
                    FROM purchase_order_items poi
                    LEFT JOIN supplier_products sp ON poi.supplier_product_id = sp.supplier_product_id
                    WHERE poi.po_id = ?
                    ORDER BY poi.created_at ASC
                ");
                $stmt->bind_param('i', $po_id);
                $stmt->execute();
                $existing_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            } else {
                $error_message = "Failed to add item. Please try again.";
            }
            $insert->close();
        }
    }
}

// ─── Handle Delete Item ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item_id'])) {
    $item_id = (int) $_POST['delete_item_id'];
    
    $delete = $conn->prepare("DELETE FROM purchase_order_items WHERE po_item_id = ? AND po_id = ?");
    $delete->bind_param('ii', $item_id, $po_id);
    
    if ($delete->execute()) {
        $success_message = "Item removed from purchase order.";
        
        // Update PO total
        $update_total = $conn->prepare("
            UPDATE purchase_orders
            SET total_amount = (
                SELECT COALESCE(SUM(total_price), 0)
                FROM purchase_order_items
                WHERE po_id = ?
            ),
            grand_total = total_amount
            WHERE po_id = ?
        ");
        $update_total->bind_param('ii', $po_id, $po_id);
        $update_total->execute();
        $update_total->close();

        // Refresh existing items
        $stmt = $conn->prepare("
            SELECT poi.po_item_id, poi.supplier_product_id, poi.product_name,
                   poi.quantity_ordered, poi.unit_price, poi.total_price,
                   sp.supplier_sku
            FROM purchase_order_items poi
            LEFT JOIN supplier_products sp ON poi.supplier_product_id = sp.supplier_product_id
            WHERE poi.po_id = ?
            ORDER BY poi.created_at ASC
        ");
        $stmt->bind_param('i', $po_id);
        $stmt->execute();
        $existing_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error_message = "Failed to remove item.";
    }
    $delete->close();
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/purchase_orders.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@400&display=swap" rel="stylesheet">';
require_once __DIR__ . '/../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Items to PO — StockFlow</title>
</head>
<body>

<div class="po-page">
    <div class="po-container">

        <!-- Page Header -->
        <div class="po-page-header">
            <div class="po-breadcrumb">
                <a href="../grocery_dashboard.php">Dashboard</a>
                <span>›</span>
                <a href="index.php">Purchase Orders</a>
                <span>›</span>
                <a href="view_purchase_order.php?po_id=<?= $po_id ?>"><?= htmlspecialchars($po_info['po_number']) ?></a>
                <span>›</span>
                <span>Add Items</span>
            </div>
            <h1 class="po-page-title">Add Items to Purchase Order</h1>
            <p class="po-page-subtitle"><?= htmlspecialchars($po_info['po_number']) ?> — <?= htmlspecialchars($po_info['supplier_name']) ?></p>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div><?= htmlspecialchars($error_message) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div><?= htmlspecialchars($success_message) ?></div>
        </div>
        <?php endif; ?>

        <!-- Existing Items -->
        <?php if (!empty($existing_items)): ?>
        <div class="po-card" style="margin-bottom: 24px;">
            <div class="po-card-header">
                <h3>Current Items (<?= count($existing_items) ?>)</h3>
                <p>Items already added to this purchase order</p>
            </div>
            <div class="po-card-body">
                <div class="po-table-wrapper">
                    <table class="supplier-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existing_items as $item): ?>
                            <tr>
                                <td>
                                    <div><?= htmlspecialchars($item['product_name']) ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($item['supplier_sku'] ?? 'Custom') ?></div>
                                </td>
                                <td>
                                    <div><?= number_format($item['quantity_ordered'], 2) ?></div>
                                </td>
                                <td>
                                    <div>₱<?= number_format($item['unit_price'], 2) ?></div>
                                </td>
                                <td>
                                    <div>₱<?= number_format($item['total_price'], 2) ?></div>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="delete_item_id" value="<?= $item['po_item_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Remove this item from the purchase order?')"
                                                title="Remove Item">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Item Form -->
        <div class="po-card">
            <div class="po-card-header">
                <h3>Add New Item</h3>
                <p>Add products to this purchase order</p>
            </div>
            <div class="po-card-body">
                <form method="POST" id="addItemForm">
                    <div class="po-grid">
                        <div>
                            <!-- Supplier Product Selection -->
                            <div class="form-group">
                                <label class="form-label" for="supplier_product_id">Select Supplier Product</label>
                                <div class="select-wrapper">
                                    <select class="form-select" id="supplier_product_id" name="supplier_product_id" onchange="toggleCustomProduct()">
                                        <option value="">— Select a product —</option>
                                        <?php foreach ($supplier_products as $sp): ?>
                                        <option value="<?= $sp['supplier_product_id'] ?>"
                                                data-name="<?= htmlspecialchars($sp['product_name']) ?>"
                                                data-price="<?= $sp['unit_price'] ?>"
                                                data-moq="<?= $sp['minimum_order_quantity'] ?>">
                                            <?= htmlspecialchars($sp['product_name']) ?>
                                            (<?= htmlspecialchars($sp['supplier_sku'] ?? 'No SKU') ?>)
                                            — ₱<?= number_format($sp['unit_price'], 2) ?>
                                            (MOQ: <?= $sp['minimum_order_quantity'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <small class="form-hint">Select from the supplier's available products, or choose custom entry below</small>
                            </div>

                            <!-- Custom Product (hidden by default) -->
                            <div id="customProductSection" style="display: none;">
                                <div class="form-group">
                                    <label class="form-label" for="custom_product_name">Custom Product Name</label>
                                    <input type="text" class="form-control" id="custom_product_name" name="custom_product_name"
                                           placeholder="Enter custom product name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="custom_unit_price">Custom Unit Price</label>
                                    <div class="input-with-icon">
                                        <span class="input-icon">₱</span>
                                        <input type="number" class="form-control with-icon" id="custom_unit_price" name="custom_unit_price"
                                               step="0.01" min="0" placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="quantity_ordered">Quantity *</label>
                                <input type="number" class="form-control" id="quantity_ordered" name="quantity_ordered"
                                       step="0.01" min="0.01" placeholder="0.00" required>
                                <small class="form-hint" id="quantityHint">Enter quantity to order</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="notes">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                          placeholder="Any special instructions or notes for this item..."></textarea>
                            </div>
                        </div>

                        <div>
                            <!-- Product Preview -->
                            <div class="po-card" style="position: sticky; top: 100px;">
                                <div class="po-card-header">
                                    <h4>Item Preview</h4>
                                    <p>Review before adding</p>
                                </div>
                                <div class="po-card-body">
                                    <div class="summary-item">
                                        <span class="summary-label">Product</span>
                                        <span class="summary-value placeholder" id="previewProduct">—</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Unit Price</span>
                                        <span class="summary-value placeholder" id="previewPrice">—</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Quantity</span>
                                        <span class="summary-value placeholder" id="previewQuantity">—</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Total</span>
                                        <span class="summary-value placeholder" id="previewTotal">—</span>
                                    </div>
                                    <hr class="summary-divider">
                                    <div class="form-actions">
                                        <a href="view_purchase_order.php?po_id=<?= $po_id ?>" class="btn btn-secondary">Back to PO</a>
                                        <button type="submit" class="btn btn-primary">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            Add Item
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /.po-container -->
</div><!-- /.po-page -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
function toggleCustomProduct() {
    const select = document.getElementById('supplier_product_id');
    const customSection = document.getElementById('customProductSection');
    
    if (select.value === '') {
        customSection.style.display = 'block';
        updatePreview();
    } else {
        customSection.style.display = 'none';
        const option = select.options[select.selectedIndex];
        const name = option.dataset.name;
        const price = parseFloat(option.dataset.price);
        const moq = parseFloat(option.dataset.moq);
        
        document.getElementById('quantityHint').textContent = 
            moq > 0 ? `Minimum order quantity: ${moq}` : 'Enter quantity to order';
        
        updatePreview(name, price);
    }
}

function updatePreview(productName = '', unitPrice = 0) {
    const quantity = parseFloat(document.getElementById('quantity_ordered').value) || 0;
    
    if (productName === '') {
        productName = document.getElementById('custom_product_name').value;
    }
    if (unitPrice === 0) {
        unitPrice = parseFloat(document.getElementById('custom_unit_price').value) || 0;
    }
    
    const total = quantity * unitPrice;
    
    document.getElementById('previewProduct').textContent = productName || '—';
    document.getElementById('previewPrice').textContent = unitPrice > 0 ? `₱${unitPrice.toFixed(2)}` : '—';
    document.getElementById('previewQuantity').textContent = quantity > 0 ? quantity.toFixed(2) : '—';
    document.getElementById('previewTotal').textContent = total > 0 ? `₱${total.toFixed(2)}` : '—';
    
    // Update placeholder classes
    document.querySelectorAll('#previewProduct, #previewPrice, #previewQuantity, #previewTotal').forEach(el => {
        el.classList.toggle('placeholder', el.textContent === '—');
    });
}

// Event listeners
document.getElementById('supplier_product_id').addEventListener('change', toggleCustomProduct);
document.getElementById('custom_product_name').addEventListener('input', () => updatePreview());
document.getElementById('custom_unit_price').addEventListener('input', () => updatePreview());
document.getElementById('quantity_ordered').addEventListener('input', () => updatePreview());

// Initialize
toggleCustomProduct();
</script>
</body>
</html>
