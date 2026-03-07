<?php
/**
 * Purchase Orders Index
 * Lists all purchase orders for the current store with filtering and management options.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';

$conn      = getDBConnection();
$store_id  = $_SESSION['store_id']  ?? null;
$user_id   = $_SESSION['user_id']   ?? null;

$error_message   = '';
$success_message = '';

// ─── Handle GET Filters ───────────────────────────────────────────────────────
$status_filter  = $_GET['status']  ?? 'all';
$supplier_filter = $_GET['supplier'] ?? 'all';
$date_filter    = $_GET['date']    ?? 'all';
$search_query   = trim($_GET['search'] ?? '');

// Build WHERE conditions
$where_conditions = ["po.store_id = ?"];
$params = [$store_id];
$types = 'i';

if ($status_filter !== 'all') {
    $where_conditions[] = "po.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($supplier_filter !== 'all' && is_numeric($supplier_filter)) {
    $where_conditions[] = "po.supplier_id = ?";
    $params[] = (int)$supplier_filter;
    $types .= 'i';
}

if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "po.order_date = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "po.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "po.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'quarter':
            $where_conditions[] = "po.order_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
            break;
    }
}

if (!empty($search_query)) {
    $where_conditions[] = "(po.po_number LIKE ? OR s.supplier_name LIKE ? OR po.notes LIKE ?)";
    $search_term = "%{$search_query}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// ─── Fetch Purchase Orders ─────────────────────────────────────────────────────
$purchase_orders = [];
$total_count = 0;

if ($store_id) {
    // Count query
    $count_sql = "
        SELECT COUNT(*) as total
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        {$where_clause}
    ";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_count = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    // Data query with pagination
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    $sql = "
        SELECT
            po.po_id,
            po.po_number,
            po.order_date,
            po.expected_delivery_date,
            po.actual_delivery_date,
            po.status,
            po.grand_total,
            po.payment_status,
            po.payment_terms,
            po.notes,
            po.created_at,
            s.supplier_id,
            s.supplier_name,
            s.supplier_type,
            u.full_name,
            COUNT(poi.po_item_id) as item_count,
            SUM(poi.quantity_ordered) as total_quantity,
            SUM(poi.quantity_received) as received_quantity
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        JOIN users u ON po.created_by = u.user_id
        LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
        {$where_clause}
        GROUP BY po.po_id
        ORDER BY po.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $purchase_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ─── Fetch Filter Options ───────────────────────────────────────────────────────
$suppliers = [];
if ($store_id) {
    $supp_stmt = $conn->prepare("
        SELECT DISTINCT s.supplier_id, s.supplier_name
        FROM suppliers s
        JOIN purchase_orders po ON s.supplier_id = po.supplier_id
        WHERE po.store_id = ?
        ORDER BY s.supplier_name ASC
    ");
    $supp_stmt->bind_param('i', $store_id);
    $supp_stmt->execute();
    $suppliers = $supp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $supp_stmt->close();
}

// ─── Fetch Store Name ───────────────────────────────────────────────────────────
$store_name = 'Your Store';
if ($store_id) {
    $sn = $conn->prepare("SELECT store_name FROM grocery_stores WHERE store_id = ?");
    $sn->bind_param('i', $store_id);
    $sn->execute();
    $store_name = $sn->get_result()->fetch_assoc()['store_name'] ?? 'Your Store';
    $sn->close();
}

// ─── Calculate Pagination ───────────────────────────────────────────────────────
if ($store_id && $total_count > 0) {
    $total_pages = ceil($total_count / $per_page);
    $current_page = min($page, $total_pages);
} else {
    $total_pages = 1;
    $current_page = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders — StockFlow</title>
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
                <span>Purchase Orders</span>
            </div>
            <h1 class="po-page-title">Purchase Orders</h1>
            <p class="po-page-subtitle">Manage purchase orders for <?= htmlspecialchars($store_name) ?></p>
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

        <!-- Filters and Actions -->
        <div class="po-card" style="margin-bottom: 24px;">
            <div class="po-card-body">
                <form method="GET" id="filterForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="search">Search</label>
                            <input
                                type="text"
                                class="form-control"
                                id="search"
                                name="search"
                                placeholder="PO number, supplier, notes..."
                                value="<?= htmlspecialchars($search_query) ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="status">Status</label>
                            <div class="select-wrapper">
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                    <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="submitted" <?= $status_filter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="partially_received" <?= $status_filter === 'partially_received' ? 'selected' : '' ?>>Partially Received</option>
                                    <option value="received" <?= $status_filter === 'received' ? 'selected' : '' ?>>Received</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="supplier">Supplier</label>
                            <div class="select-wrapper">
                                <select class="form-select" id="supplier" name="supplier">
                                    <option value="all" <?= $supplier_filter === 'all' ? 'selected' : '' ?>>All Suppliers</option>
                                    <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['supplier_id'] ?>" <?= $supplier_filter == $s['supplier_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['supplier_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="date">Date Range</label>
                            <div class="select-wrapper">
                                <select class="form-select" id="date" name="date">
                                    <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                                    <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                                    <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                                    <option value="quarter" <?= $date_filter === 'quarter' ? 'selected' : '' ?>>Last 90 Days</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="index.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Purchase Orders List -->
        <div class="po-card">
            <div class="po-card-header">
                <h3>Purchase Orders (<?= number_format($total_count) ?>)</h3>
                <p><?= $total_count ?> purchase order<?= $total_count !== 1 ? 's' : '' ?> found</p>
            </div>
            <div class="po-card-body" style="padding-top: 0; padding-bottom: 0;">
                <?php if (empty($purchase_orders)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                    </div>
                    <h4>No Purchase Orders Found</h4>
                    <p>No purchase orders match your current filters. Try adjusting your search criteria or <a href="create_purchase_order.php">create a new purchase order</a>.</p>
                </div>
                <?php else: ?>
                <div class="po-table-wrapper">
                    <table class="supplier-table">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($purchase_orders as $po): ?>
                        <tr>
                            <td>
                                <div class="td-name"><?= htmlspecialchars($po['po_number']) ?></div>
                                <div class="td-sub">Created <?= date('M j, Y', strtotime($po['created_at'])) ?></div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($po['supplier_name']) ?></div>
                                <div class="td-sub"><?= ucfirst(str_replace('_', ' ', $po['supplier_type'])) ?></div>
                            </td>
                            <td>
                                <div><?= date('M j, Y', strtotime($po['order_date'])) ?></div>
                                <?php if ($po['expected_delivery_date']): ?>
                                <div class="td-sub">Expected: <?= date('M j', strtotime($po['expected_delivery_date'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $po['status'] === 'draft' ? 'inactive' : ($po['status'] === 'received' ? 'active' : 'type') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $po['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <div><?= number_format($po['item_count']) ?> items</div>
                                <?php if ($po['total_quantity'] > 0): ?>
                                <div class="td-sub">
                                    <?= number_format($po['received_quantity']) ?>/<?= number_format($po['total_quantity']) ?> received
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>₱<?= number_format($po['grand_total'], 2) ?></div>
                                <?php if ($po['payment_terms']): ?>
                                <div class="td-sub"><?= htmlspecialchars($po['payment_terms']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $po['payment_status'] === 'paid' ? 'active' : ($po['payment_status'] === 'partially_paid' ? 'type' : 'inactive') ?>">
                                    <?= ucfirst($po['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="td-actions">
                                    <a href="view_purchase_order.php?po_id=<?= $po['po_id'] ?>" class="btn btn-sm btn-secondary" title="View Details">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <?php if ($po['status'] === 'draft'): ?>
                                    <a href="add_po_items.php?po_id=<?= $po['po_id'] ?>" class="btn btn-sm btn-secondary" title="Add Items">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (in_array($po['status'], ['confirmed', 'partially_received'])): ?>
                                    <a href="receive_purchase_order.php?po_id=<?= $po['po_id'] ?>" class="btn btn-sm btn-primary" title="Receive Items">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper" style="padding: 24px 0 0; display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 12px; color: rgba(0,0,0,0.5);">
                        Showing <?= number_format(($current_page - 1) * $per_page + 1) ?>-<?= number_format(min($current_page * $per_page, $total_count)) ?> of <?= number_format($total_count) ?>
                    </div>
                    <div class="pagination-links" style="display: flex; gap: 8px;">
                        <?php if ($current_page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" class="btn btn-sm btn-secondary">Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="btn btn-sm <?= $i === $current_page ? 'btn-primary' : 'btn-secondary' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" class="btn btn-sm btn-secondary">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="po-grid" style="margin-top: 24px;">
            <div>
                <div class="po-card">
                    <div class="po-card-header">
                        <h3>Quick Actions</h3>
                        <p>Common tasks and shortcuts</p>
                    </div>
                    <div class="po-card-body">
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <a href="create_purchase_order.php" class="btn btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Create New Purchase Order
                            </a>
                            <a href="register_supplier.php" class="btn btn-secondary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                Register New Supplier
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="po-card">
                    <div class="po-card-header">
                        <h3>Summary Statistics</h3>
                        <p>Overview of purchase orders</p>
                    </div>
                    <div class="po-card-body">
                        <div class="summary-item">
                            <span class="summary-label">Total Orders</span>
                            <span class="summary-value"><?= number_format($total_count) ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Draft Orders</span>
                            <span class="summary-value">
                                <?= number_format(count(array_filter($purchase_orders, fn($po) => $po['status'] === 'draft'))) ?>
                            </span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Active Orders</span>
                            <span class="summary-value">
                                <?= number_format(count(array_filter($purchase_orders, fn($po) => in_array($po['status'], ['submitted', 'confirmed', 'partially_received'])))) ?>
                            </span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Completed</span>
                            <span class="summary-value">
                                <?= number_format(count(array_filter($purchase_orders, fn($po) => $po['status'] === 'received'))) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.po-container -->
</div><!-- /.po-page -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// Auto-submit filters when changed (except search)
document.querySelectorAll('#filterForm select').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Search on Enter key
document.getElementById('search')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('filterForm').submit();
    }
});
</script>
</body>
</html>
