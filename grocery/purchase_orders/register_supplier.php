<?php
/**
 * Register Store with Supplier
 * Manages store_suppliers relationships: register new ones, deactivate existing ones.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';

$conn     = getDBConnection();
$store_id = $_SESSION['store_id'] ?? null;
$user_id  = $_SESSION['user_id']  ?? null;

$error_message   = '';
$success_message = '';

// ─── Handle POST Actions ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Register new relationship ───────────────────────────────────────────
    if ($action === 'register') {
        $supplier_id        = (int)   ($_POST['supplier_id']        ?? 0);
        $preferred_supplier = isset($_POST['preferred_supplier']) ? 1 : 0;
        $credit_limit       = !empty($_POST['credit_limit'])
                                ? (float) $_POST['credit_limit']
                                : null;
        $notes              = trim($_POST['notes'] ?? '');

        if (!$supplier_id) {
            $error_message = 'Please select a supplier.';
        } elseif (!$store_id) {
            $error_message = 'Session expired. Please log in again.';
        } else {
            // Confirm the supplier actually exists and is active
            $chk = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ? AND is_active = 1");
            $chk->bind_param('i', $supplier_id);
            $chk->execute();
            $supplier_exists = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$supplier_exists) {
                $error_message = 'Supplier not found or is inactive.';
            } else {
                // Check for existing (even inactive) relationship
                $dup = $conn->prepare("
                    SELECT store_supplier_id, is_active
                    FROM store_suppliers
                    WHERE store_id = ? AND supplier_id = ?
                    LIMIT 1
                ");
                $dup->bind_param('ii', $store_id, $supplier_id);
                $dup->execute();
                $existing = $dup->get_result()->fetch_assoc();
                $dup->close();

                if ($existing && $existing['is_active']) {
                    $error_message = 'Your store is already registered with this supplier.';
                } elseif ($existing && !$existing['is_active']) {
                    // Reactivate
                    $reactivate = $conn->prepare("
                        UPDATE store_suppliers
                        SET is_active = 1,
                            preferred_supplier = ?,
                            credit_limit = ?,
                            notes = ?,
                            updated_at = NOW()
                        WHERE store_supplier_id = ?
                    ");
                    $reactivate->bind_param('idsi', $preferred_supplier, $credit_limit, $notes, $existing['store_supplier_id']);
                    if ($reactivate->execute()) {
                        $success_message = 'Supplier relationship reactivated successfully.';
                    } else {
                        $error_message = 'Failed to reactivate supplier. Please try again.';
                    }
                    $reactivate->close();
                } else {
                    // Insert new
                    $insert = $conn->prepare("
                        INSERT INTO store_suppliers
                            (store_id, supplier_id, is_active, preferred_supplier, credit_limit, current_balance, notes)
                        VALUES (?, ?, 1, ?, ?, 0.00, ?)
                    ");
                    $insert->bind_param('iiids', $store_id, $supplier_id, $preferred_supplier, $credit_limit, $notes);
                    if ($insert->execute()) {
                        $success_message = 'Supplier registered successfully. You can now create purchase orders with this supplier.';
                    } else {
                        $error_message = 'Failed to register supplier. Please try again.';
                    }
                    $insert->close();
                }
            }
        }
    }

    // ── Deactivate relationship ─────────────────────────────────────────────
    if ($action === 'deactivate') {
        $ss_id = (int) ($_POST['store_supplier_id'] ?? 0);
        if ($ss_id && $store_id) {
            $deact = $conn->prepare("
                UPDATE store_suppliers
                SET is_active = 0, updated_at = NOW()
                WHERE store_supplier_id = ? AND store_id = ?
            ");
            $deact->bind_param('ii', $ss_id, $store_id);
            if ($deact->execute() && $deact->affected_rows > 0) {
                $success_message = 'Supplier relationship deactivated.';
            } else {
                $error_message = 'Could not deactivate this supplier.';
            }
            $deact->close();
        }
    }

    // ── Toggle preferred ────────────────────────────────────────────────────
    if ($action === 'toggle_preferred') {
        $ss_id   = (int) ($_POST['store_supplier_id'] ?? 0);
        $new_val = (int) ($_POST['new_value']         ?? 0);
        if ($ss_id && $store_id) {
            $tog = $conn->prepare("
                UPDATE store_suppliers
                SET preferred_supplier = ?, updated_at = NOW()
                WHERE store_supplier_id = ? AND store_id = ?
            ");
            $tog->bind_param('iii', $new_val, $ss_id, $store_id);
            $tog->execute();
            $tog->close();
            $success_message = $new_val ? 'Marked as preferred supplier.' : 'Removed preferred status.';
        }
    }
}

// ─── Fetch: Current active relationships ────────────────────────────────────
$current_relationships = [];
if ($store_id) {
    $stmt = $conn->prepare("
        SELECT
            ss.store_supplier_id,
            ss.preferred_supplier,
            ss.credit_limit,
            ss.current_balance,
            ss.last_order_date,
            ss.notes         AS relationship_notes,
            ss.created_at    AS relationship_since,
            s.supplier_id,
            s.supplier_name,
            s.supplier_type,
            s.company_name,
            s.contact_person,
            s.contact_number,
            s.email,
            s.payment_terms,
            s.delivery_schedule,
            s.minimum_order_amount,
            s.rating
        FROM store_suppliers ss
        JOIN suppliers s ON ss.supplier_id = s.supplier_id
        WHERE ss.store_id = ?
          AND ss.is_active = 1
          AND s.is_active  = 1
        ORDER BY ss.preferred_supplier DESC, s.supplier_name ASC
    ");
    $stmt->bind_param('i', $store_id);
    $stmt->execute();
    $current_relationships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ─── Fetch: Available suppliers not yet registered by this store ─────────────
$registered_ids    = array_column($current_relationships, 'supplier_id');
$available_suppliers = [];
$all_stmt = $conn->prepare("
    SELECT supplier_id, supplier_name, supplier_type,
           company_name, contact_person, email,
           payment_terms, delivery_schedule, minimum_order_amount, rating
    FROM suppliers
    WHERE is_active = 1
    ORDER BY supplier_name ASC
");
$all_stmt->execute();
$all_suppliers = $all_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$all_stmt->close();

foreach ($all_suppliers as $s) {
    if (!in_array($s['supplier_id'], $registered_ids)) {
        $available_suppliers[] = $s;
    }
}

// ─── Fetch store name ────────────────────────────────────────────────────────
$store_name = 'Your Store';
if ($store_id) {
    $sn = $conn->prepare("SELECT store_name FROM grocery_stores WHERE store_id = ?");
    $sn->bind_param('i', $store_id);
    $sn->execute();
    $store_name = $sn->get_result()->fetch_assoc()['store_name'] ?? 'Your Store';
    $sn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Supplier — StockFlow</title>
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
                <span>Register Supplier</span>
            </div>
            <h1 class="po-page-title">Supplier Relationships</h1>
            <p class="po-page-subtitle">Manage which suppliers <?= htmlspecialchars($store_name) ?> can order from</p>
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

            <!-- Left: Register Form -->
            <div>

                <!-- Register New -->
                <div class="po-card" style="margin-bottom: 24px;">
                    <div class="po-card-header">
                        <h3>Register New Supplier</h3>
                        <p>Add a supplier your store can order from</p>
                    </div>
                    <div class="po-card-body">
                        <?php if (empty($available_suppliers)): ?>
                        <div class="no-suppliers-notice">
                            <p>All available suppliers are already registered with your store.<br>
                            New suppliers can be added by a system administrator.</p>
                        </div>
                        <?php else: ?>
                        <form method="POST" id="registerForm">
                            <input type="hidden" name="action" value="register">

                            <div class="form-group">
                                <label class="form-label" for="supplier_id">
                                    Supplier <span class="required">*</span>
                                </label>
                                <div class="select-wrapper">
                                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                                        <option value="">Select a supplier…</option>
                                        <?php foreach ($available_suppliers as $s): ?>
                                        <option
                                            value="<?= $s['supplier_id'] ?>"
                                            data-type="<?= htmlspecialchars($s['supplier_type']) ?>"
                                            data-contact="<?= htmlspecialchars($s['contact_person'] ?? '') ?>"
                                            data-email="<?= htmlspecialchars($s['email'] ?? '') ?>"
                                            data-payment="<?= htmlspecialchars($s['payment_terms'] ?? '') ?>"
                                            data-schedule="<?= htmlspecialchars($s['delivery_schedule'] ?? '') ?>"
                                            data-minorder="<?= $s['minimum_order_amount'] ?? '' ?>"
                                        >
                                            <?= htmlspecialchars($s['supplier_name']) ?>
                                            — <?= ucfirst(str_replace('_', ' ', $s['supplier_type'])) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <span class="form-hint">Only suppliers not yet registered with your store are shown.</span>
                            </div>

                            <!-- Supplier Preview (populated by JS) -->
                            <div id="supplierPreview" style="display:none; margin-bottom:20px;">
                                <div class="alert alert-info" style="margin-bottom:0;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <div id="supplierPreviewText" style="font-size:12px;line-height:1.7;"></div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="credit_limit">Credit Limit (₱)</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="credit_limit"
                                        name="credit_limit"
                                        step="0.01"
                                        min="0"
                                        placeholder="e.g. 50000.00"
                                        value="<?= htmlspecialchars($_POST['credit_limit'] ?? '') ?>"
                                    >
                                    <span class="form-hint">Leave blank for no credit limit.</span>
                                </div>
                                <div class="form-group" style="display:flex;align-items:center;padding-top:28px;">
                                    <label class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="preferred_supplier"
                                            name="preferred_supplier"
                                            <?= isset($_POST['preferred_supplier']) ? 'checked' : '' ?>
                                        >
                                        <span class="form-check-label">
                                            Mark as preferred supplier
                                            <small>Will be highlighted in PO creation and auto-reordering</small>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="reg_notes">Notes</label>
                                <textarea
                                    class="form-control"
                                    id="reg_notes"
                                    name="notes"
                                    placeholder="e.g. Preferred for dairy products, contact sales rep directly…"
                                    style="min-height:80px;"
                                ><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                            </div>

                            <div class="form-actions">
                                <a href="create_purchase_order.php" class="btn btn-secondary">Back to POs</a>
                                <button type="submit" class="btn btn-primary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Register Supplier
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Relationships Table -->
                <div class="po-card">
                    <div class="po-card-header">
                        <h3>Registered Suppliers</h3>
                        <p><?= count($current_relationships) ?> active supplier relationship<?= count($current_relationships) !== 1 ? 's' : '' ?></p>
                    </div>
                    <div class="po-card-body" style="padding-top: 0; padding-bottom: 0;">
                        <?php if (empty($current_relationships)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <h4>No Suppliers Yet</h4>
                            <p>Use the form above to register your first supplier relationship.</p>
                        </div>
                        <?php else: ?>
                        <div class="supplier-table-wrapper">
                            <table class="supplier-table">
                                <thead>
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Type</th>
                                        <th>Contact</th>
                                        <th>Terms</th>
                                        <th>Credit</th>
                                        <th>Since</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($current_relationships as $r): ?>
                                <tr>
                                    <td>
                                        <div class="td-name">
                                            <?= htmlspecialchars($r['supplier_name']) ?>
                                            <?php if ($r['preferred_supplier']): ?>
                                                <span class="badge badge-preferred" style="margin-left:6px;">Preferred</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($r['company_name'] && $r['company_name'] !== $r['supplier_name']): ?>
                                        <div class="td-sub"><?= htmlspecialchars($r['company_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($r['rating']): ?>
                                        <div class="td-sub">★ <?= number_format($r['rating'], 1) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-type"><?= ucfirst(str_replace('_', ' ', $r['supplier_type'])) ?></span>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($r['contact_person'] ?? '—') ?></div>
                                        <?php if ($r['email']): ?>
                                        <div class="td-sub"><?= htmlspecialchars($r['email']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($r['contact_number']): ?>
                                        <div class="td-sub"><?= htmlspecialchars($r['contact_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($r['payment_terms'] ?? '—') ?></div>
                                        <?php if ($r['delivery_schedule']): ?>
                                        <div class="td-sub"><?= htmlspecialchars($r['delivery_schedule']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['credit_limit']): ?>
                                            <div>₱<?= number_format($r['credit_limit'], 2) ?></div>
                                            <?php if ($r['current_balance'] > 0): ?>
                                            <div class="td-sub">Balance: ₱<?= number_format($r['current_balance'], 2) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:rgba(0,0,0,0.3);font-size:12px;">No limit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['relationship_since']): ?>
                                        <div style="font-size:12px;"><?= date('M j, Y', strtotime($r['relationship_since'])) ?></div>
                                        <?php endif; ?>
                                        <?php if ($r['last_order_date']): ?>
                                        <div class="td-sub">Last order: <?= date('M j', strtotime($r['last_order_date'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="td-actions">
                                            <!-- Toggle preferred -->
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_preferred">
                                                <input type="hidden" name="store_supplier_id" value="<?= $r['store_supplier_id'] ?>">
                                                <input type="hidden" name="new_value" value="<?= $r['preferred_supplier'] ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary" title="<?= $r['preferred_supplier'] ? 'Remove preferred status' : 'Mark as preferred' ?>">
                                                    <?php if ($r['preferred_supplier']): ?>
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                                    <?php else: ?>
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                            <!-- Create PO -->
                                            <a href="create_purchase_order.php?supplier_id=<?= $r['supplier_id'] ?>" class="btn btn-sm btn-secondary" title="Create PO">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            </a>
                                            <!-- Deactivate -->
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this supplier relationship? You will not be able to create new POs with them until it is reactivated.');">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="store_supplier_id" value="<?= $r['store_supplier_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Deactivate">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /left -->

            <!-- Right: Info panel -->
            <div>
                <div class="po-card" style="position:sticky;top:100px;">
                    <div class="po-card-header">
                        <h3>About Supplier Relationships</h3>
                    </div>
                    <div class="po-card-body">
                        <div class="summary-item">
                            <span class="summary-label">Registered</span>
                            <span class="summary-value"><?= count($current_relationships) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Available to Add</span>
                            <span class="summary-value"><?= count($available_suppliers) ?></span>
                        </div>
                        <hr class="summary-divider">
                        <p style="font-size:12px;color:rgba(0,0,0,0.5);line-height:1.8;margin-bottom:16px;">
                            Only suppliers registered here can be selected when creating purchase orders for this store.
                        </p>
                        <p style="font-size:12px;color:rgba(0,0,0,0.5);line-height:1.8;margin-bottom:16px;">
                            <strong style="color:#0a0a0a;">Preferred suppliers</strong> are highlighted during PO creation and will be prioritised in auto-reorder suggestions.
                        </p>
                        <p style="font-size:12px;color:rgba(0,0,0,0.5);line-height:1.8;">
                            <strong style="color:#0a0a0a;">Credit limits</strong> track the maximum outstanding balance allowed with a supplier. Current balance is updated as purchase orders are paid.
                        </p>
                        <hr class="summary-divider">
                        <a href="create_purchase_order.php" class="btn btn-primary btn-block">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Create Purchase Order
                        </a>
                    </div>
                </div>
            </div>

        </div><!-- /.po-grid -->
    </div><!-- /.po-container -->
</div><!-- /.po-page -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// Populate supplier preview card when dropdown changes
(function () {
    const select  = document.getElementById('supplier_id');
    const preview = document.getElementById('supplierPreview');
    const text    = document.getElementById('supplierPreviewText');

    if (!select) return;

    select.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!this.value) { preview.style.display = 'none'; return; }

        const parts = [];
        if (opt.dataset.contact)  parts.push('<strong>Contact:</strong> ' + opt.dataset.contact);
        if (opt.dataset.email)    parts.push('<strong>Email:</strong> '   + opt.dataset.email);
        if (opt.dataset.payment)  parts.push('<strong>Payment:</strong> ' + opt.dataset.payment);
        if (opt.dataset.schedule) parts.push('<strong>Delivery:</strong> '+ opt.dataset.schedule);
        if (opt.dataset.minorder) parts.push('<strong>Min. Order:</strong> ₱' + parseFloat(opt.dataset.minorder).toLocaleString('en-PH', {minimumFractionDigits:2}));

        text.innerHTML = parts.join(' &nbsp;·&nbsp; ');
        preview.style.display = parts.length ? 'block' : 'none';
    });
})();
</script>
</body>
</html>