<?php
/**
 * Create Purchase Order
 * Validates that the store has a registered relationship with the chosen supplier
 * before allowing PO creation.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';

$conn      = getDBConnection();
$store_id  = $_SESSION['store_id']  ?? null;
$user_id   = $_SESSION['user_id']   ?? null;

$error_message   = '';
$success_message = '';
$created_po      = null;

// ─── Fetch: valid suppliers for this store ──────────────────────────────────
// Joins store_suppliers → suppliers so only registered relationships appear.
$valid_suppliers = [];
if ($store_id) {
    $stmt = $conn->prepare("
        SELECT
            s.supplier_id,
            s.supplier_name,
            s.supplier_type,
            s.contact_person,
            s.contact_number,
            s.email,
            s.payment_terms,
            s.delivery_schedule,
            s.minimum_order_amount,
            s.rating,
            ss.preferred_supplier,
            ss.credit_limit,
            ss.current_balance
        FROM store_suppliers ss
        JOIN suppliers s ON ss.supplier_id = s.supplier_id
        WHERE ss.store_id = ?
          AND ss.is_active  = 1
          AND s.is_active   = 1
        ORDER BY ss.preferred_supplier DESC, s.supplier_name ASC
    ");
    $stmt->bind_param('i', $store_id);
    $stmt->execute();
    $valid_suppliers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ─── Handle Form Submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id          = (int) ($_POST['supplier_id']          ?? 0);
    $expected_delivery    =        $_POST['expected_delivery']    ?? '';
    $payment_terms        =        $_POST['payment_terms']        ?? '';
    $notes                =        $_POST['notes']                ?? '';

    // 1. Basic validation
    if (!$supplier_id) {
        $error_message = 'Please select a supplier.';
    } elseif (!$store_id) {
        $error_message = 'Session expired. Please log in again.';
    } else {
        // 2. Database-level enforcement: confirm store-supplier relationship exists
        $chk = $conn->prepare("
            SELECT store_supplier_id
            FROM store_suppliers
            WHERE store_id   = ?
              AND supplier_id = ?
              AND is_active   = 1
            LIMIT 1
        ");
        $chk->bind_param('ii', $store_id, $supplier_id);
        $chk->execute();
        $relationship = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$relationship) {
            $error_message = 'Your store does not have an active relationship with this supplier. Please <a href="register_supplier.php">register the supplier</a> first.';
        } else {
            // 3. Generate PO number  e.g. PO-2026-000001
            $max_stmt = $conn->query("SELECT COALESCE(MAX(po_id), 0) + 1 AS next_id FROM purchase_orders");
            $next_id  = (int) $max_stmt->fetch_assoc()['next_id'];
            $po_number = 'PO-' . date('Y') . '-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);

            // 4. Determine expected delivery using supplier avg lead time
            if (empty($expected_delivery)) {
                $ld_stmt = $conn->prepare("
                    SELECT COALESCE(AVG(lead_time_days), 3) AS avg_lead
                    FROM supplier_products
                    WHERE supplier_id = ?
                ");
                $ld_stmt->bind_param('i', $supplier_id);
                $ld_stmt->execute();
                $avg_lead = (int) $ld_stmt->get_result()->fetch_assoc()['avg_lead'];
                $ld_stmt->close();
                $expected_delivery = date('Y-m-d', strtotime("+{$avg_lead} days"));
            }

            // 5. Resolve payment terms: use supplier default if not provided
            if (empty($payment_terms)) {
                $pt_stmt = $conn->prepare("SELECT payment_terms FROM suppliers WHERE supplier_id = ?");
                $pt_stmt->bind_param('i', $supplier_id);
                $pt_stmt->execute();
                $payment_terms = $pt_stmt->get_result()->fetch_assoc()['payment_terms'] ?? '';
                $pt_stmt->close();
            }

            // 6. Insert PO
            $insert = $conn->prepare("
                INSERT INTO purchase_orders
                    (po_number, store_id, supplier_id, order_date, expected_delivery_date,
                     status, payment_terms, created_by, notes)
                VALUES (?, ?, ?, CURDATE(), ?, 'draft', ?, ?, ?)
            ");
            $insert->bind_param(
                'siiisss',
                $po_number, $store_id, $supplier_id,
                $expected_delivery, $payment_terms, $user_id, $notes
            );

            if ($insert->execute()) {
                $new_po_id = $conn->insert_id;

                // Update last_order_date on store_suppliers
                $upd = $conn->prepare("
                    UPDATE store_suppliers
                    SET last_order_date = CURDATE()
                    WHERE store_id = ? AND supplier_id = ?
                ");
                $upd->bind_param('ii', $store_id, $supplier_id);
                $upd->execute();
                $upd->close();

                $created_po = [
                    'po_id'     => $new_po_id,
                    'po_number' => $po_number,
                ];
                $success_message = "Purchase Order <strong>{$po_number}</strong> created successfully as a draft.";
            } else {
                $error_message = 'Failed to create purchase order. Please try again.';
            }
            $insert->close();
        }
    }
}

// ─── Fetch store name for display ───────────────────────────────────────────
$store_name = 'Your Store';
if ($store_id) {
    $sn = $conn->prepare("SELECT store_name FROM grocery_stores WHERE store_id = ?");
    $sn->bind_param('i', $store_id);
    $sn->execute();
    $store_name = $sn->get_result()->fetch_assoc()['store_name'] ?? 'Your Store';
    $sn->close();
}

// Pre-select supplier if passed via GET (e.g. from register page redirect)
$preselected_supplier = (int) ($_GET['supplier_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Order — StockFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../includes/style/pages/purchase_orders.css">
</head>
<body>

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="po-page">
    <div class="po-container">

        <!-- Page Header -->
        <div class="po-page-header">
            <div class="po-breadcrumb">
                <a href="../grocery_dashboard.php">Dashboard</a>
                <span>›</span>
                <a href="index.php">Purchase Orders</a>
                <span>›</span>
                <span>Create New</span>
            </div>
            <h1 class="po-page-title">Create Purchase Order</h1>
            <p class="po-page-subtitle">New draft PO for <?= htmlspecialchars($store_name) ?></p>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div><?= $error_message ?></div>
        </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>
                <?= $success_message ?>
                <?php if ($created_po): ?>
                — <a href="view_purchase_order.php?po_id=<?= $created_po['po_id'] ?>">View PO</a>
                or <a href="add_po_items.php?po_id=<?= $created_po['po_id'] ?>">Add Items</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($valid_suppliers)): ?>
        <!-- No suppliers state -->
        <div class="po-card">
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                </div>
                <h4>No Registered Suppliers</h4>
                <p>Your store hasn't registered with any suppliers yet. Register a supplier relationship before creating purchase orders.</p>
                <a href="register_supplier.php" class="btn btn-primary">Register a Supplier</a>
            </div>
        </div>

        <?php else: ?>
        <!-- Main Form -->
        <form method="POST" id="poForm">
            <div class="po-grid">

                <!-- Left: Form -->
                <div>
                    <!-- Supplier Selection -->
                    <div class="po-card" style="margin-bottom: 24px;">
                        <div class="po-card-header">
                            <h3>Select Supplier</h3>
                            <p>Only suppliers registered with your store are listed below</p>
                        </div>
                        <div class="po-card-body">
                            <?php foreach ($valid_suppliers as $s): ?>
                            <label class="supplier-option <?= ($preselected_supplier === (int)$s['supplier_id']) ? 'selected' : '' ?>" id="label-<?= $s['supplier_id'] ?>">
                                <input
                                    class="supplier-radio"
                                    type="radio"
                                    name="supplier_id"
                                    value="<?= $s['supplier_id'] ?>"
                                    <?= ($preselected_supplier === (int)$s['supplier_id']) ? 'checked' : '' ?>
                                    required
                                >
                                <div class="supplier-info">
                                    <div class="supplier-name">
                                        <?= htmlspecialchars($s['supplier_name']) ?>
                                        <?php if ($s['preferred_supplier']): ?>
                                            <span class="badge badge-preferred">Preferred</span>
                                        <?php endif; ?>
                                        <span class="badge badge-type"><?= ucfirst(str_replace('_', ' ', $s['supplier_type'])) ?></span>
                                    </div>
                                    <div class="supplier-meta">
                                        <?php if ($s['contact_person']): ?>
                                        <span>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                            <?= htmlspecialchars($s['contact_person']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($s['payment_terms']): ?>
                                        <span>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                            <?= htmlspecialchars($s['payment_terms']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($s['delivery_schedule']): ?>
                                        <span>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            <?= htmlspecialchars($s['delivery_schedule']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($s['credit_limit']): ?>
                                        <span>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                            Credit: ₱<?= number_format($s['credit_limit'], 2) ?>
                                            <?php if ($s['current_balance'] > 0): ?>
                                                (Balance: ₱<?= number_format($s['current_balance'], 2) ?>)
                                            <?php endif; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($s['minimum_order_amount']): ?>
                                    <div class="supplier-price-hint">Minimum order: ₱<?= number_format($s['minimum_order_amount'], 2) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($s['rating']): ?>
                                <div style="font-size:11px;color:rgba(0,0,0,0.4);white-space:nowrap;padding-top:2px;">
                                    ★ <?= number_format($s['rating'], 1) ?>
                                </div>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>

                            <div class="form-hint" style="margin-top:16px;">
                                Not seeing a supplier?
                                <a href="register_supplier.php">Register a new supplier relationship</a>
                            </div>
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div class="po-card">
                        <div class="po-card-header">
                            <h3>Order Details</h3>
                            <p>Delivery date and additional information</p>
                        </div>
                        <div class="po-card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="expected_delivery">
                                        Expected Delivery Date
                                    </label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        id="expected_delivery"
                                        name="expected_delivery"
                                        min="<?= date('Y-m-d') ?>"
                                        value="<?= htmlspecialchars($_POST['expected_delivery'] ?? '') ?>"
                                    >
                                    <span class="form-hint">Leave blank to auto-calculate from supplier lead time.</span>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="payment_terms">
                                        Payment Terms
                                    </label>
                                    <div class="select-wrapper">
                                        <select class="form-select" id="payment_terms" name="payment_terms">
                                            <option value="">Use supplier default</option>
                                            <option value="COD"       <?= ($_POST['payment_terms'] ?? '') === 'COD'    ? 'selected' : '' ?>>Cash on Delivery (COD)</option>
                                            <option value="Net 7"     <?= ($_POST['payment_terms'] ?? '') === 'Net 7'  ? 'selected' : '' ?>>Net 7</option>
                                            <option value="Net 15"    <?= ($_POST['payment_terms'] ?? '') === 'Net 15' ? 'selected' : '' ?>>Net 15</option>
                                            <option value="Net 30"    <?= ($_POST['payment_terms'] ?? '') === 'Net 30' ? 'selected' : '' ?>>Net 30</option>
                                            <option value="Net 45"    <?= ($_POST['payment_terms'] ?? '') === 'Net 45' ? 'selected' : '' ?>>Net 45</option>
                                            <option value="Net 60"    <?= ($_POST['payment_terms'] ?? '') === 'Net 60' ? 'selected' : '' ?>>Net 60</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="notes">Notes</label>
                                <textarea
                                    class="form-control"
                                    id="notes"
                                    name="notes"
                                    placeholder="Any special instructions or notes for this purchase order..."
                                ><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                            </div>

                            <div class="form-actions">
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Create Purchase Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Summary -->
                <div>
                    <div class="po-card" style="position: sticky; top: 100px;">
                        <div class="po-card-header">
                            <h3>Order Summary</h3>
                            <p>Review before creating</p>
                        </div>
                        <div class="po-card-body">
                            <div class="summary-item">
                                <span class="summary-label">Store</span>
                                <span class="summary-value"><?= htmlspecialchars($store_name) ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Supplier</span>
                                <span class="summary-value placeholder" id="summarySupplier">None selected</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Type</span>
                                <span class="summary-value placeholder" id="summaryType">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Order Date</span>
                                <span class="summary-value"><?= date('M j, Y') ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Expected Delivery</span>
                                <span class="summary-value placeholder" id="summaryDelivery">Auto-calculated</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Payment Terms</span>
                                <span class="summary-value placeholder" id="summaryPayment">Supplier default</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Status</span>
                                <span class="summary-value"><span class="badge badge-type">Draft</span></span>
                            </div>
                            <hr class="summary-divider">
                            <p style="font-size:11px;color:rgba(0,0,0,0.4);line-height:1.6;margin-top:8px;">
                                The PO will be created as a <strong>draft</strong>. You can add line items, review, and submit it to the supplier afterwards.
                            </p>
                        </div>
                    </div>
                </div>

            </div><!-- /.po-grid -->
        </form>
        <?php endif; ?>

    </div><!-- /.po-container -->
</div><!-- /.po-page -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// Live-update summary card from form selections
(function () {
    const radios   = document.querySelectorAll('.supplier-radio');
    const labels   = document.querySelectorAll('.supplier-option');
    const delivery = document.getElementById('expected_delivery');
    const payment  = document.getElementById('payment_terms');

    // Supplier data map from PHP
    const suppliers = {
        <?php foreach ($valid_suppliers as $s): ?>
        <?= $s['supplier_id'] ?>: {
            name: <?= json_encode($s['supplier_name']) ?>,
            type: <?= json_encode(ucfirst(str_replace('_', ' ', $s['supplier_type']))) ?>,
            payment: <?= json_encode($s['payment_terms'] ?? '') ?>
        },
        <?php endforeach; ?>
    };

    function updateSummary() {
        const checked = document.querySelector('.supplier-radio:checked');

        // Highlight selected card
        labels.forEach(l => l.classList.remove('selected'));
        if (checked) {
            document.getElementById('label-' + checked.value)?.classList.add('selected');
            const s = suppliers[checked.value];
            document.getElementById('summarySupplier').textContent = s.name;
            document.getElementById('summarySupplier').classList.remove('placeholder');
            document.getElementById('summaryType').textContent = s.type;
            document.getElementById('summaryType').classList.remove('placeholder');
            if (!payment.value && s.payment) {
                document.getElementById('summaryPayment').textContent = s.payment + ' (default)';
                document.getElementById('summaryPayment').classList.remove('placeholder');
            }
        }
        if (delivery?.value) {
            const d = new Date(delivery.value + 'T00:00:00');
            document.getElementById('summaryDelivery').textContent =
                d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
            document.getElementById('summaryDelivery').classList.remove('placeholder');
        } else {
            document.getElementById('summaryDelivery').textContent = 'Auto-calculated';
            document.getElementById('summaryDelivery').classList.add('placeholder');
        }
        if (payment?.value) {
            document.getElementById('summaryPayment').textContent = payment.value;
            document.getElementById('summaryPayment').classList.remove('placeholder');
        } else if (document.querySelector('.supplier-radio:checked')) {
            const s = suppliers[document.querySelector('.supplier-radio:checked').value];
            document.getElementById('summaryPayment').textContent = s.payment ? s.payment + ' (default)' : 'Supplier default';
        }
    }

    radios.forEach(r  => r.addEventListener('change', updateSummary));
    delivery?.addEventListener('input', updateSummary);
    payment?.addEventListener('change', updateSummary);

    // Trigger on load if preselected
    updateSummary();

    // Validate: require supplier selection before submit
    document.getElementById('poForm')?.addEventListener('submit', function (e) {
        if (!document.querySelector('.supplier-radio:checked')) {
            e.preventDefault();
            alert('Please select a supplier to continue.');
        }
    });
})();
</script>
</body>
</html>