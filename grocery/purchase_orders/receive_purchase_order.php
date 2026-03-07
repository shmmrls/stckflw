<?php
/**
 * Receive Purchase Order
 * Process received items and add them to inventory
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
        SELECT po.*, s.supplier_name
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

if (!in_array($po_info['status'], ['confirmed', 'partially_received'])) {
    die("Items can only be received for confirmed or partially received purchase orders.");
}

// ─── Get PO items ─────────────────────────────────────────────────────────────
$po_items = [];
$stmt = $conn->prepare("
    SELECT poi.*, sp.supplier_sku, sp.supplier_product_id,
           pc.catalog_id, pc.product_name as catalog_name,
           c.category_name, c.category_id
    FROM purchase_order_items poi
    LEFT JOIN supplier_products sp ON poi.supplier_product_id = sp.supplier_product_id
    LEFT JOIN product_catalog pc ON sp.catalog_id = pc.catalog_id
    LEFT JOIN categories c ON pc.category_id = c.category_id
    WHERE poi.po_id = ?
    ORDER BY poi.created_at ASC
");
$stmt->bind_param('i', $po_id);
$stmt->execute();
$po_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Handle Receiving Form ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiving_date = $_POST['receiving_date'] ?? date('Y-m-d');
    $batch_number = trim($_POST['batch_number'] ?? '');
    
    $total_received = 0;
    $all_items_received = true;
    $conn->begin_transaction();
    
    try {
        foreach ($po_items as $item) {
            $item_id = $item['po_item_id'];
            $quantity_received = (float) ($_POST["quantity_received_{$item_id}"] ?? 0);
            $expiry_date = $_POST["expiry_date_{$item_id}"] ?? '';
            $cost_price = $item['unit_price'];
            
            // Validate
            if ($quantity_received < 0) {
                throw new Exception("Quantity received cannot be negative.");
            }
            
            if ($quantity_received > ($item['quantity_ordered'] - $item['quantity_received'])) {
                throw new Exception("Cannot receive more than ordered quantity for item: {$item['product_name']}");
            }
            
            if ($quantity_received > 0 && empty($expiry_date)) {
                throw new Exception("Expiry date is required for received items: {$item['product_name']}");
            }
            
            // Update PO item
            $new_quantity_received = $item['quantity_received'] + $quantity_received;
            $update_item = $conn->prepare("
                UPDATE purchase_order_items 
                SET quantity_received = ?, expiry_date = ?
                WHERE po_item_id = ?
            ");
            $update_item->bind_param('dsi', $new_quantity_received, $expiry_date, $item_id);
            $update_item->execute();
            $update_item->close();
            
            // Add to grocery_items if quantity received > 0
            if ($quantity_received > 0) {
                // Determine selling price (you might want to add pricing logic here)
                $selling_price = $cost_price * 1.25; // 25% markup default
                
                // Calculate expiry status
                $days_until_expiry = (strtotime($expiry_date) - time()) / 86400;
                if ($days_until_expiry < 0) {
                    $expiry_status = 'expired';
                } elseif ($days_until_expiry <= 7) {
                    $expiry_status = 'near_expiry';
                } else {
                    $expiry_status = 'fresh';
                }
                $alert_flag = ($expiry_status !== 'fresh') ? 1 : 0;
                
                // Insert into grocery_items
                $insert_item = $conn->prepare("
                    INSERT INTO grocery_items
                        (item_name, barcode, catalog_id, category_id,
                         supplier_id, supplier_product_id, purchase_order_id,
                         batch_number, quantity, unit,
                         purchase_date, received_date, expiry_date,
                         expiry_status, alert_flag,
                         cost_price, selling_price,
                         reorder_level, reorder_quantity,
                         sku, created_by, store_id)
                    VALUES
                        (?, ?, ?, ?,
                         ?, ?, ?,
                         ?, ?, ?,
                         ?, ?, ?,
                         ?, ?,
                         ?, ?,
                         ?, ?,
                         ?, ?, ?)
                ");
                
                $item_name = $item['product_name'];
                $barcode = ''; // Empty barcode for received items
                $catalog_id = $item['catalog_id'] ?: null;
                $category_id = $item['category_id'] ?: 1; // Default category
                $supplier_id = $po_info['supplier_id'];
                $supplier_product_id = $item['supplier_product_id'];
                $unit = 'pcs'; // Default unit, you might want to track this
                $purchase_date = $po_info['order_date'];
                $reorder_level = 10; // Default values
                $reorder_quantity = 50;
                $sku = ''; // Can be generated or left empty
                
                $insert_item->bind_param(
                    "ssiiiiisdssssiddiisiii",
                    $item_name, $barcode, $catalog_id, $category_id,
                    $supplier_id, $supplier_product_id, $po_id,
                    $batch_number, $quantity_received, $unit,
                    $purchase_date, $receiving_date, $expiry_date,
                    $expiry_status, $alert_flag,
                    $cost_price, $selling_price,
                    $reorder_level, $reorder_quantity,
                    $sku, $user_id, $store_id
                );
                
                $insert_item->execute();
                $new_grocery_item_id = $conn->insert_id;
                $insert_item->close();
                
                // Log inventory update
                $log_update = $conn->prepare("
                    INSERT INTO grocery_inventory_updates
                        (item_id, store_id, update_type, quantity_change, updated_by, notes)
                    VALUES (?, ?, 'received', ?, ?, ?)
                ");
                $notes = "Received via PO {$po_info['po_number']}";
                if ($batch_number) {
                    $notes .= " — Batch: $batch_number";
                }
                $log_update->bind_param('iidis', $new_grocery_item_id, $store_id, $quantity_received, $user_id, $notes);
                $log_update->execute();
                $log_update->close();
                
                $total_received += $quantity_received;
            }
            
            // Check if this item is fully received
            if ($new_quantity_received < $item['quantity_ordered']) {
                $all_items_received = false;
            }
        }
        
        // Update PO status and delivery date
        $new_status = $all_items_received ? 'received' : 'partially_received';
        $update_po = $conn->prepare("
            UPDATE purchase_orders
            SET status = ?, actual_delivery_date = ?
            WHERE po_id = ?
        ");
        $update_po->bind_param('ssi', $new_status, $receiving_date, $po_id);
        $update_po->execute();
        $update_po->close();
        
        $conn->commit();
        
        $success_message = "Successfully received {$total_received} items. Purchase order status updated to '{$new_status}'.";
        
        // Refresh PO items data
        $stmt = $conn->prepare("
            SELECT poi.*, sp.supplier_sku, sp.supplier_product_id,
                   pc.catalog_id, pc.product_name as catalog_name,
                   c.category_name, c.category_id
            FROM purchase_order_items poi
            LEFT JOIN supplier_products sp ON poi.supplier_product_id = sp.supplier_product_id
            LEFT JOIN product_catalog pc ON sp.catalog_id = pc.catalog_id
            LEFT JOIN categories c ON pc.category_id = c.category_id
            WHERE poi.po_id = ?
            ORDER BY poi.created_at ASC
        ");
        $stmt->bind_param('i', $po_id);
        $stmt->execute();
        $po_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Refresh PO info
        $stmt = $conn->prepare("
            SELECT po.*, s.supplier_name
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.supplier_id
            WHERE po.po_id = ? AND po.store_id = ?
        ");
        $stmt->bind_param('ii', $po_id, $store_id);
        $stmt->execute();
        $po_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error processing receipt: " . $e->getMessage();
    }
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
    <title>Receive Purchase Order — StockFlow</title>
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
                <span>Receive Items</span>
            </div>
            <h1 class="po-page-title">Receive Purchase Order</h1>
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

        <?php if ($po_info['status'] === 'received'): ?>
        <div class="po-card">
            <div class="po-card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                        </svg>
                    </div>
                    <h4>Order Fully Received</h4>
                    <p>This purchase order has been completely received and processed.</p>
                    <a href="view_purchase_order.php?po_id=<?= $po_id ?>" class="btn btn-primary">View Order Details</a>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- Receiving Form -->
        <form method="POST" id="receiveForm">
            <div class="po-card" style="margin-bottom: 24px;">
                <div class="po-card-header">
                    <h3>Receiving Information</h3>
                    <p>General information about this delivery</p>
                </div>
                <div class="po-card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="receiving_date">Receiving Date *</label>
                            <input type="date" class="form-control" id="receiving_date" name="receiving_date" 
                                   value="<?= htmlspecialchars($_POST['receiving_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="batch_number">Batch/Lot Number</label>
                            <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                   value="<?= htmlspecialchars($_POST['batch_number'] ?? '') ?>"
                                   placeholder="e.g., BATCH-2026-001">
                            <small class="form-hint">Optional - applies to all items in this delivery</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items to Receive -->
            <div class="po-card">
                <div class="po-card-header">
                    <h3>Items to Receive</h3>
                    <p>Enter quantities received and expiry dates</p>
                </div>
                <div class="po-card-body">
                    <div class="po-table-wrapper">
                        <table class="supplier-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Ordered</th>
                                    <th>Already Received</th>
                                    <th>Quantity to Receive</th>
                                    <th>Expiry Date</th>
                                    <th>Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($po_items as $item): ?>
                                <tr>
                                    <td>
                                        <div><?= htmlspecialchars($item['product_name']) ?></div>
                                        <?php if ($item['catalog_name']): ?>
                                        <div class="td-sub"><?= htmlspecialchars($item['catalog_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($item['supplier_sku'] ?? 'Custom') ?></div>
                                    </td>
                                    <td>
                                        <div><?= number_format($item['quantity_ordered'], 2) ?></div>
                                    </td>
                                    <td>
                                        <div><?= number_format($item['quantity_received'], 2) ?></div>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control" 
                                               name="quantity_received_<?= $item['po_item_id'] ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="<?= $item['quantity_ordered'] - $item['quantity_received'] ?>"
                                               placeholder="0.00"
                                               onchange="updateRemaining(<?= $item['po_item_id'] ?>, <?= $item['quantity_ordered'] ?>, <?= $item['quantity_received'] ?>)">
                                    </td>
                                    <td>
                                        <input type="date" 
                                               class="form-control" 
                                               name="expiry_date_<?= $item['po_item_id'] ?>"
                                               min="<?= date('Y-m-d') ?>">
                                    </td>
                                    <td>
                                        <span id="remaining_<?= $item['po_item_id'] ?>">
                                            <?= number_format($item['quantity_ordered'] - $item['quantity_received'], 2) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-actions" style="margin-top: 24px;">
                        <a href="view_purchase_order.php?po_id=<?= $po_id ?>" class="btn btn-secondary">Cancel</a>
                        <button type="button" class="btn btn-primary" onclick="showReceiveModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                            </svg>
                            Process Receipt
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <?php endif; ?>

    </div><!-- /.po-container -->
</div><!-- /.po-page -->

<!-- Receive Confirmation Modal -->
<div id="receiveModal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Process Receipt</h3>
            <button type="button" class="modal-close" onclick="hideReceiveModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-icon" style="background: rgba(39, 174, 96, 0.1); color: #27ae60;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                </svg>
            </div>
            <h4>Process Item Receipt?</h4>
            <p>Are you ready to process the receipt for purchase order <strong><?= htmlspecialchars($po_info['po_number']) ?></strong> from <strong><?= htmlspecialchars($po_info['supplier_name']) ?></strong>?</p>
            
            <div class="modal-details">
                <div class="detail-row">
                    <span class="detail-label">Receiving Date:</span>
                    <span class="detail-value"><?= htmlspecialchars($_POST['receiving_date'] ?? date('Y-m-d')) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Batch Number:</span>
                    <span class="detail-value"><?= htmlspecialchars($_POST['batch_number'] ?? 'Not specified') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Items to Process:</span>
                    <span class="detail-value"><?= count($po_items) ?> items</span>
                </div>
            </div>
            
            <div class="modal-warning">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span>This action will add received items to your inventory and update the purchase order status. This cannot be undone.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideReceiveModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitReceiveForm()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                </svg>
                Process Receipt
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

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
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    background: #ffffff;
    border-radius: 8px;
    max-width: 480px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.show .modal-container {
    transform: scale(1);
}

.modal-header {
    padding: 24px 24px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    padding-bottom: 20px;
}

.modal-header h3 {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    padding: 8px;
    cursor: pointer;
    color: rgba(0, 0, 0, 0.4);
    border-radius: 4px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: rgba(0, 0, 0, 0.1);
    color: #0a0a0a;
}

.modal-body {
    padding: 24px;
    text-align: center;
}

.modal-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 20px;
    background: rgba(10, 10, 10, 0.05);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0a0a0a;
}

.modal-body h4 {
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    font-weight: 400;
    color: #0a0a0a;
    margin: 0 0 12px;
}

.modal-body p {
    font-size: 14px;
    color: rgba(0, 0, 0, 0.6);
    line-height: 1.6;
    margin: 0 0 20px;
}

.modal-details {
    background: #fafafa;
    border-radius: 6px;
    padding: 16px;
    margin: 20px 0;
    text-align: left;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-size: 12px;
    color: rgba(0, 0, 0, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 500;
}

.detail-value {
    font-size: 13px;
    color: #0a0a0a;
    font-weight: 600;
}

.modal-warning {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    background: #fdfaf4;
    border: 1px solid rgba(230, 176, 70, 0.3);
    border-radius: 6px;
    margin: 20px 0 0;
}

.modal-warning svg {
    flex-shrink: 0;
    color: #e6b046;
    margin-top: 2px;
}

.modal-warning span {
    font-size: 12px;
    color: #7d5a10;
    line-height: 1.5;
}

.modal-footer {
    padding: 0 24px 24px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.modal-footer .btn {
    margin: 0;
}

@media (max-width: 600px) {
    .modal-container {
        width: 95%;
        margin: 20px;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
}
</style>

<script>
function updateRemaining(itemId, ordered, received) {
    const quantityInput = document.querySelector(`input[name="quantity_received_${itemId}"]`);
    const remainingSpan = document.getElementById(`remaining_${itemId}`);
    
    const toReceive = parseFloat(quantityInput.value) || 0;
    const remaining = ordered - received - toReceive;
    
    remainingSpan.textContent = remaining.toFixed(2);
    
    // Update styling based on remaining
    if (remaining < 0) {
        remainingSpan.style.color = '#dc3545';
        quantityInput.setCustomValidity('Cannot receive more than ordered quantity');
    } else {
        remainingSpan.style.color = '';
        quantityInput.setCustomValidity('');
    }
}

function showReceiveModal() {
    const modal = document.getElementById('receiveModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function hideReceiveModal() {
    const modal = document.getElementById('receiveModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function submitReceiveForm() {
    hideReceiveModal();
    document.getElementById('receiveForm').submit();
}

// Close modal when clicking overlay
document.getElementById('receiveModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideReceiveModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideReceiveModal();
    }
});

// Initialize all remaining calculations
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($po_items as $item): ?>
    updateRemaining(<?= $item['po_item_id'] ?>, <?= $item['quantity_ordered'] ?>, <?= $item['quantity_received'] ?>);
    <?php endforeach; ?>
});
</script>
</body>
</html>
