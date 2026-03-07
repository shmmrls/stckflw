<?php
/**
 * View Purchase Order Details
 * Shows complete PO information with items and allows status management
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
        SELECT po.*, s.supplier_name, s.supplier_type, s.contact_person, 
               s.contact_number, s.email, s.payment_terms as supplier_payment_terms,
               u.full_name as created_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        JOIN users u ON po.created_by = u.user_id
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

// ─── Get PO items ─────────────────────────────────────────────────────────────
$po_items = [];
$stmt = $conn->prepare("
    SELECT poi.*, sp.supplier_sku, sp.minimum_order_quantity,
           pc.catalog_id, pc.product_name as catalog_name,
           c.category_name
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

// ─── Handle Status Updates ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'submit':
            if ($po_info['status'] === 'draft' && !empty($po_items)) {
                $update = $conn->prepare("UPDATE purchase_orders SET status = 'submitted' WHERE po_id = ?");
                $update->bind_param('i', $po_id);
                if ($update->execute()) {
                    $success_message = "Purchase order submitted to supplier.";
                    $po_info['status'] = 'submitted';
                } else {
                    $error_message = "Failed to submit purchase order.";
                }
                $update->close();
            } else {
                $error_message = "Cannot submit: PO must be in draft status and have items.";
            }
            break;
            
        case 'confirm':
            if ($po_info['status'] === 'submitted') {
                $update = $conn->prepare("UPDATE purchase_orders SET status = 'confirmed' WHERE po_id = ?");
                $update->bind_param('i', $po_id);
                if ($update->execute()) {
                    $success_message = "Purchase order confirmed by supplier.";
                    $po_info['status'] = 'confirmed';
                } else {
                    $error_message = "Failed to confirm purchase order.";
                }
                $update->close();
            } else {
                $error_message = "Cannot confirm: PO must be in submitted status.";
            }
            break;
            
        case 'cancel':
            if (in_array($po_info['status'], ['draft', 'submitted'])) {
                $update = $conn->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE po_id = ?");
                $update->bind_param('i', $po_id);
                if ($update->execute()) {
                    $success_message = "Purchase order cancelled.";
                    $po_info['status'] = 'cancelled';
                } else {
                    $error_message = "Failed to cancel purchase order.";
                }
                $update->close();
            } else {
                $error_message = "Cannot cancel: PO must be in draft or submitted status.";
            }
            break;
            
        case 'update_payment_status':
            $new_status = $_POST['payment_status'] ?? '';
            
            if (in_array($new_status, ['unpaid', 'partially_paid', 'paid'])) {
                $update = $conn->prepare("UPDATE purchase_orders SET payment_status = ? WHERE po_id = ?");
                $update->bind_param('si', $new_status, $po_id);
                if ($update->execute()) {
                    $success_message = "Payment status updated successfully.";
                    $po_info['payment_status'] = $new_status;
                } else {
                    $error_message = "Failed to update payment status.";
                }
                $update->close();
            } else {
                $error_message = "Invalid payment status.";
            }
            break;
    }
    
    // Refresh PO info after update
    $stmt = $conn->prepare("
        SELECT po.*, s.supplier_name, s.supplier_type, s.contact_person, 
               s.contact_number, s.email, s.payment_terms as supplier_payment_terms,
               u.full_name as created_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        JOIN users u ON po.created_by = u.user_id
        WHERE po.po_id = ? AND po.store_id = ?
    ");
    $stmt->bind_param('ii', $po_id, $store_id);
    $stmt->execute();
    $po_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ─── Calculate totals ───────────────────────────────────────────────────────────
$total_quantity = array_sum(array_column($po_items, 'quantity_ordered'));
$total_received = array_sum(array_column($po_items, 'quantity_received'));
$total_amount = array_sum(array_column($po_items, 'total_price'));

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
    <title>View Purchase Order — StockFlow</title>
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
                <span><?= htmlspecialchars($po_info['po_number']) ?></span>
            </div>
            <h1 class="po-page-title"><?= htmlspecialchars($po_info['po_number']) ?></h1>
            <p class="po-page-subtitle"><?= htmlspecialchars($po_info['supplier_name']) ?></p>
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

        <div class="po-grid">
            <!-- Left Column -->
            <div>
                <!-- PO Details -->
                <div class="po-card" style="margin-bottom: 24px;">
                    <div class="po-card-header">
                        <h3>Order Details</h3>
                        <p>Basic information about this purchase order</p>
                    </div>
                    <div class="po-card-body">
                        <div class="summary-item">
                            <span class="summary-label">PO Number</span>
                            <span class="summary-value"><?= htmlspecialchars($po_info['po_number']) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Status</span>
                            <span class="summary-value">
                                <span class="badge badge-<?= $po_info['status'] === 'draft' ? 'inactive' : ($po_info['status'] === 'received' ? 'active' : 'type') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $po_info['status'])) ?>
                                </span>
                            </span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Order Date</span>
                            <span class="summary-value"><?= date('M j, Y', strtotime($po_info['order_date'])) ?></span>
                        </div>
                        <?php if ($po_info['expected_delivery_date']): ?>
                        <div class="summary-item">
                            <span class="summary-label">Expected Delivery</span>
                            <span class="summary-value"><?= date('M j, Y', strtotime($po_info['expected_delivery_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($po_info['actual_delivery_date']): ?>
                        <div class="summary-item">
                            <span class="summary-label">Actual Delivery</span>
                            <span class="summary-value"><?= date('M j, Y', strtotime($po_info['actual_delivery_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-item">
                            <span class="summary-label">Payment Terms</span>
                            <span class="summary-value"><?= htmlspecialchars($po_info['payment_terms'] ?: $po_info['supplier_payment_terms']) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Payment Status</span>
                            <span class="summary-value">
                                <div class="payment-status-wrapper">
                                    <span class="badge badge-<?= $po_info['payment_status'] === 'paid' ? 'active' : ($po_info['payment_status'] === 'partially_paid' ? 'type' : 'inactive') ?>" id="payment-badge-detail">
                                        <?= ucfirst($po_info['payment_status'] ?: 'unpaid') ?>
                                    </span>
                                    <button class="btn btn-sm btn-secondary payment-edit-btn" onclick="showPaymentEditDetail()" title="Edit Payment Status">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                </div>
                                <form id="payment-form-detail" class="payment-edit-form" style="display: none;" method="POST">
                                    <input type="hidden" name="action" value="update_payment_status">
                                    <div class="select-wrapper">
                                        <select name="payment_status" class="form-select form-select-sm">
                                            <option value="unpaid" <?= ($po_info['payment_status'] ?: 'unpaid') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                            <option value="partially_paid" <?= ($po_info['payment_status'] ?: 'unpaid') === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                                            <option value="paid" <?= ($po_info['payment_status'] ?: 'unpaid') === 'paid' ? 'selected' : '' ?>>Paid</option>
                                        </select>
                                    </div>
                                    <div class="payment-edit-actions">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="hidePaymentEditDetail()">Cancel</button>
                                    </div>
                                </form>
                            </span>
                        </div>
                        <?php if ($po_info['notes']): ?>
                        <div class="summary-item">
                            <span class="summary-label">Notes</span>
                            <span class="summary-value"><?= htmlspecialchars($po_info['notes']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Supplier Information -->
                <div class="po-card" style="margin-bottom: 24px;">
                    <div class="po-card-header">
                        <h3>Supplier Information</h3>
                        <p>Details about the supplier</p>
                    </div>
                    <div class="po-card-body">
                        <div class="summary-item">
                            <span class="summary-label">Supplier Name</span>
                            <span class="summary-value"><?= htmlspecialchars($po_info['supplier_name']) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Supplier Type</span>
                            <span class="summary-value"><?= ucfirst(str_replace('_', ' ', $po_info['supplier_type'])) ?></span>
                        </div>
                        <?php if ($po_info['contact_person']): ?>
                        <div class="summary-item">
                            <span class="summary-label">Contact Person</span>
                            <span class="summary-value"><?= htmlspecialchars($po_info['contact_person']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($po_info['contact_number']): ?>
                        <div class="summary-item">
                            <span class="summary-label">Contact Number</span>
                            <span class="summary-value"><?= htmlspecialchars($po_info['contact_number']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($po_info['email']): ?>
                        <div class="summary-item">
                            <span class="summary-label">Email</span>
                            <span class="summary-value"><?= htmlspecialchars($po_info['email']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PO Items -->
                <div class="po-card">
                    <div class="po-card-header">
                        <h3>Order Items (<?= count($po_items) ?>)</h3>
                        <p>Products included in this purchase order</p>
                    </div>
                    <div class="po-card-body">
                        <?php if (empty($po_items)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                            </div>
                            <h4>No Items Added</h4>
                            <p>This purchase order doesn't have any items yet.</p>
                            <?php if ($po_info['status'] === 'draft'): ?>
                            <a href="add_po_items.php?po_id=<?= $po_id ?>" class="btn btn-primary">Add Items</a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="po-table-wrapper">
                            <table class="supplier-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Ordered</th>
                                        <th>Received</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                        <th>Status</th>
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
                                            <?php if ($item['quantity_received'] < $item['quantity_ordered'] && $item['quantity_received'] > 0): ?>
                                            <div class="td-sub">Partial</div>
                                            <?php elseif ($item['quantity_received'] >= $item['quantity_ordered']): ?>
                                            <div class="td-sub">Complete</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>₱<?= number_format($item['unit_price'], 2) ?></div>
                                        </td>
                                        <td>
                                            <div>₱<?= number_format($item['total_price'], 2) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($item['quantity_received'] >= $item['quantity_ordered']): ?>
                                            <span class="badge badge-active">Received</span>
                                            <?php elseif ($item['quantity_received'] > 0): ?>
                                            <span class="badge badge-type">Partial</span>
                                            <?php else: ?>
                                            <span class="badge badge-inactive">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Order Summary -->
                <div class="po-card" style="margin-bottom: 24px;">
                    <div class="po-card-header">
                        <h3>Order Summary</h3>
                        <p>Financial summary</p>
                    </div>
                    <div class="po-card-body">
                        <div class="summary-item">
                            <span class="summary-label">Total Items</span>
                            <span class="summary-value"><?= count($po_items) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Total Quantity</span>
                            <span class="summary-value"><?= number_format($total_quantity, 2) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Quantity Received</span>
                            <span class="summary-value"><?= number_format($total_received, 2) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Subtotal</span>
                            <span class="summary-value">₱<?= number_format($total_amount, 2) ?></span>
                        </div>
                        <?php if ($po_info['tax_amount'] > 0): ?>
                        <div class="summary-item">
                            <span class="summary-label">Tax</span>
                            <span class="summary-value">₱<?= number_format($po_info['tax_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($po_info['discount_amount'] > 0): ?>
                        <div class="summary-item">
                            <span class="summary-label">Discount</span>
                            <span class="summary-value">-₱<?= number_format($po_info['discount_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <hr class="summary-divider">
                        <div class="summary-item" style="font-weight: 600;">
                            <span class="summary-label">Grand Total</span>
                            <span class="summary-value">₱<?= number_format($po_info['grand_total'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="po-card">
                    <div class="po-card-header">
                        <h3>Actions</h3>
                        <p>Manage this purchase order</p>
                    </div>
                    <div class="po-card-body">
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php if ($po_info['status'] === 'draft'): ?>
                                <a href="add_po_items.php?po_id=<?= $po_id ?>" class="btn btn-secondary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add Items
                                </a>
                                <?php if (!empty($po_items)): ?>
                                <form method="POST" style="margin: 0; padding: 0; width: 100%;">
                                    <input type="hidden" name="action" value="submit">
                                    <button type="button" class="btn btn-primary" style="width: 100%;" onclick="showSubmitModal()">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                        </svg>
                                        Submit to Supplier
                                    </button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($po_info['status'] === 'submitted'): ?>
                                <form method="POST" style="margin: 0; padding: 0; width: 100%;">
                                    <input type="hidden" name="action" value="confirm">
                                    <button type="button" class="btn btn-primary" style="width: 100%;" onclick="showConfirmModal()">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                        </svg>
                                        Confirm Order
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($po_info['status'], ['draft', 'submitted'])): ?>
                                <form method="POST" style="margin: 0; padding: 0; width: 100%;">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Cancel this purchase order? This action cannot be undone.')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                        Cancel Order
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($po_info['status'] === 'confirmed'): ?>
                                <a href="receive_purchase_order.php?po_id=<?= $po_id ?>" class="btn btn-primary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                                    </svg>
                                    Receive Items
                                </a>
                            <?php endif; ?>

                            <?php if ($po_info['status'] === 'partially_received'): ?>
                                <a href="receive_purchase_order.php?po_id=<?= $po_id ?>" class="btn btn-primary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                                    </svg>
                                    Receive More Items
                                </a>
                            <?php endif; ?>

                            <?php if ($po_info['status'] === 'confirmed'): ?>
                                <button class="btn btn-secondary" disabled>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                                    </svg>
                                    Awaiting Delivery
                                </button>
                            <?php endif; ?>

                            <a href="index.php" class="btn btn-secondary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                                </svg>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Created By -->
                <div class="po-card">
                    <div class="po-card-header">
                        <h3>Created By</h3>
                        <p>Who created this purchase order</p>
                    </div>
                    <div class="po-card-body">
                        <div class="summary-item">
                            <span class="summary-label">Name</span>
                            <span class="summary-value"><?= htmlspecialchars($po_info['created_by_name']) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Created Date</span>
                            <span class="summary-value"><?= date('M j, Y g:i A', strtotime($po_info['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.po-container -->
</div><!-- /.po-page -->

<!-- Confirm Order Modal -->
<div id="confirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Confirm Purchase Order</h3>
            <button type="button" class="modal-close" onclick="hideConfirmModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-icon" style="background: rgba(39, 174, 96, 0.1); color: #27ae60;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <h4>Confirm Order Acceptance?</h4>
            <p>Has <strong><?= htmlspecialchars($po_info['supplier_name']) ?></strong> confirmed and accepted purchase order <strong><?= htmlspecialchars($po_info['po_number']) ?></strong>?</p>
            
            <div class="modal-details">
                <div class="detail-row">
                    <span class="detail-label">Items:</span>
                    <span class="detail-value"><?= count($po_items) ?> items</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">₱<?= number_format($po_info['grand_total'], 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Expected Delivery:</span>
                    <span class="detail-value"><?= $po_info['expected_delivery_date'] ? date('M j, Y', strtotime($po_info['expected_delivery_date'])) : 'Not set' ?></span>
                </div>
            </div>
            
            <div class="modal-warning">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span>Once confirmed, the order will be marked as accepted and you can begin receiving items when they arrive.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideConfirmModal()">Cancel</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Confirm Order
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Submit Confirmation Modal -->
<div id="submitModal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Submit Purchase Order</h3>
            <button type="button" class="modal-close" onclick="hideSubmitModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <h4>Submit to Supplier?</h4>
            <p>Are you sure you want to submit purchase order <strong><?= htmlspecialchars($po_info['po_number']) ?></strong> to <strong><?= htmlspecialchars($po_info['supplier_name']) ?></strong>?</p>
            
            <div class="modal-details">
                <div class="detail-row">
                    <span class="detail-label">Items:</span>
                    <span class="detail-value"><?= count($po_items) ?> items</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">₱<?= number_format($po_info['grand_total'], 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Terms:</span>
                    <span class="detail-value"><?= htmlspecialchars($po_info['payment_terms'] ?: $po_info['supplier_payment_terms']) ?></span>
                </div>
            </div>
            
            <div class="modal-warning">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span>Once submitted, this purchase order cannot be modified. You'll need to contact the supplier to make any changes.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideSubmitModal()">Cancel</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="submit">
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Submit Order
                </button>
            </form>
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
function showSubmitModal() {
    const modal = document.getElementById('submitModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function hideSubmitModal() {
    const modal = document.getElementById('submitModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function showConfirmModal() {
    const modal = document.getElementById('confirmModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function hideConfirmModal() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

// Payment status editing functions for detail page
function showPaymentEditDetail() {
    // Hide the badge and edit button
    document.getElementById('payment-badge-detail').style.display = 'none';
    document.querySelector('.payment-status-wrapper .payment-edit-btn').style.display = 'none';
    
    // Show the edit form
    document.getElementById('payment-form-detail').style.display = 'block';
}

function hidePaymentEditDetail() {
    // Show the badge and edit button
    document.getElementById('payment-badge-detail').style.display = 'inline-block';
    document.querySelector('.payment-status-wrapper .payment-edit-btn').style.display = 'inline-block';
    
    // Hide the edit form
    document.getElementById('payment-form-detail').style.display = 'none';
}

// Handle payment form submission with AJAX
document.getElementById('payment-form-detail').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const newStatus = formData.get('payment_status');
    
    fetch('view_purchase_order.php?po_id=<?= $po_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Update the badge
        const badge = document.getElementById('payment-badge-detail');
        badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
        badge.className = 'badge badge-' + (newStatus === 'paid' ? 'active' : (newStatus === 'partially_paid' ? 'type' : 'inactive'));
        
        // Hide the form and show the badge
        hidePaymentEditDetail();
        
        // Show success message
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success';
        successDiv.style.position = 'fixed';
        successDiv.style.top = '20px';
        successDiv.style.right = '20px';
        successDiv.style.zIndex = '9999';
        successDiv.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><div>Payment status updated successfully.</div>';
        document.body.appendChild(successDiv);
        
        setTimeout(() => {
            successDiv.remove();
        }, 3000);
    })
    .catch(error => {
        console.error('Error:', error);
        // Show error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-error';
        errorDiv.style.position = 'fixed';
        errorDiv.style.top = '20px';
        errorDiv.style.right = '20px';
        errorDiv.style.zIndex = '9999';
        errorDiv.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><div>Failed to update payment status.</div>';
        document.body.appendChild(errorDiv);
        
        setTimeout(() => {
            errorDiv.remove();
        }, 3000);
    });
});

// Close modals when clicking overlay
document.getElementById('submitModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideSubmitModal();
    }
});

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideConfirmModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideSubmitModal();
        hideConfirmModal();
        hidePaymentEditDetail();
    }
});
</script>
</body>
</html>
