<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';

$conn = getDBConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? 'active';

// Build query with filters using enhanced supplier table
$query = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = s.supplier_id) as total_orders,
           (SELECT COUNT(*) FROM supplier_products WHERE supplier_id = s.supplier_id AND is_available = 1) as active_products
    FROM suppliers s 
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (s.supplier_name LIKE ? OR s.company_name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($type_filter)) {
    $query .= " AND s.supplier_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($status_filter === 'active') {
    $query .= " AND s.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $query .= " AND s.is_active = 0";
}

$query .= " ORDER BY s.supplier_name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $suppliers_result = $stmt->get_result();
} else {
    $suppliers_result = $conn->query($query);
}

// Get enhanced stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN supplier_type = 'manufacturer' THEN 1 ELSE 0 END) as manufacturers,
        SUM(CASE WHEN supplier_type = 'distributor' THEN 1 ELSE 0 END) as distributors,
        SUM(CASE WHEN supplier_type = 'wholesaler' THEN 1 ELSE 0 END) as wholesalers,
        SUM(CASE WHEN supplier_type = 'local_supplier' THEN 1 ELSE 0 END) as local_suppliers,
        AVG(rating) as avg_rating
    FROM suppliers
")->fetch_assoc();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/suppliers.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="suppliers-page">
    <div class="suppliers-container">

        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Supplier Management</h1>
                    <p class="page-subtitle">Manage supplier relationships and performance. Register suppliers you've known and loved.</p>
                </div>
                <div class="header-actions">
                    <a href="add_supplier.php" class="btn-primary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add Supplier
                    </a>
                </div>
            </div>
        </div>

        <!-- Enhanced Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Suppliers</div>
                    <small style="color: #059669;"><?php echo number_format($stats['active']); ?> Active</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['manufacturers']); ?></div>
                    <div class="stat-label">Manufacturers</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['distributors']); ?></div>
                    <div class="stat-label">Distributors</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l9 4.9V12c0 5.5-3.8 10.7-9 12-5.2-1.3-9-6.5-9-12V6.9L12 2z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : 'N/A'; ?></div>
                    <div class="stat-label">Avg Rating</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Name, company, contact, email" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Type</label>
                        <select name="type" class="filter-select">
                            <option value="">All Types</option>
                            <option value="manufacturer" <?php echo ($type_filter === 'manufacturer') ? 'selected' : ''; ?>>Manufacturer</option>
                            <option value="distributor" <?php echo ($type_filter === 'distributor') ? 'selected' : ''; ?>>Distributor</option>
                            <option value="wholesaler" <?php echo ($type_filter === 'wholesaler') ? 'selected' : ''; ?>>Wholesaler</option>
                            <option value="local_supplier" <?php echo ($type_filter === 'local_supplier') ? 'selected' : ''; ?>>Local Supplier</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="submit" class="filter-btn">Filter</button>
                    </div>
                </div>
            </form>
            
            <?php if (!empty($search) || !empty($type_filter) || $status_filter !== 'active'): ?>
                <div style="margin-top: 15px;">
                    <a href="view_suppliers.php" class="clear-filters">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Suppliers List -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">Suppliers (<?php echo $suppliers_result->num_rows; ?>)</h2>
            </div>

            <?php if ($suppliers_result->num_rows > 0): ?>
                <div class="suppliers-grid">
                    <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                        <div class="supplier-card <?php echo !$supplier['is_active'] ? 'inactive' : ''; ?>">
                            <div class="supplier-header">
                                <div class="supplier-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <?php
                                        switch($supplier['supplier_type']) {
                                            case 'manufacturer':
                                                echo '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>';
                                                break;
                                            case 'distributor':
                                                echo '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>';
                                                break;
                                            case 'wholesaler':
                                                echo '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>';
                                                break;
                                            default:
                                                echo '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>';
                                        }
                                        ?>
                                    </svg>
                                </div>
                                <span class="badge badge-<?php echo $supplier['supplier_type']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $supplier['supplier_type'])); ?>
                                </span>
                                <?php if (!$supplier['is_active']): ?>
                                    <span class="badge badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="supplier-body">
                                <h3 class="supplier-name"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h3>
                                <?php if ($supplier['company_name']): ?>
                                    <div class="supplier-company"><?php echo htmlspecialchars($supplier['company_name']); ?></div>
                                <?php endif; ?>
                                
                                <div class="supplier-info">
                                    <?php if ($supplier['contact_person']): ?>
                                        <div class="info-row">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                            </svg>
                                            <span><?php echo htmlspecialchars($supplier['contact_person']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($supplier['contact_number']): ?>
                                        <div class="info-row">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                            </svg>
                                            <span><?php echo htmlspecialchars($supplier['contact_number']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($supplier['email']): ?>
                                        <div class="info-row">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                            </svg>
                                            <span><?php echo htmlspecialchars($supplier['email']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($supplier['payment_terms']): ?>
                                        <div class="info-row">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                            </svg>
                                            <span><?php echo htmlspecialchars($supplier['payment_terms']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="supplier-stats">
                                    <?php if ($supplier['rating']): ?>
                                        <div class="stat-badge">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                            </svg>
                                            <?php echo number_format($supplier['rating'], 1); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($supplier['total_orders'] > 0): ?>
                                        <div class="stat-badge"><?php echo $supplier['total_orders']; ?> Orders</div>
                                    <?php endif; ?>
                                    <?php if ($supplier['active_products'] > 0): ?>
                                        <div class="stat-badge"><?php echo $supplier['active_products']; ?> Products</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="supplier-actions">
                                <a href="../supplier_products/view_supplier_products.php?supplier_id=<?php echo $supplier['supplier_id']; ?>" class="btn-view">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    View Products
                                </a>
                                <a href="edit_supplier.php?id=<?php echo $supplier['supplier_id']; ?>" class="btn-edit">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    Edit
                                </a>
                                <button class="btn-delete" onclick="showDeleteModal(<?php echo $supplier['supplier_id']; ?>, '<?php echo htmlspecialchars($supplier['supplier_name']); ?>')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; font-size: 11px; font-weight: 500; text-decoration: none; border-radius: 6px; transition: all 0.3s ease; border: 1px solid transparent; cursor: pointer; background: #dc2626; color: white; border-color: #dc2626;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <p>No suppliers found. <?php echo (!empty($search) || !empty($type_filter)) ? 'Try adjusting your filters.' : 'Start by adding suppliers!'; ?></p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- Delete Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Delete Supplier</h3>
            <button type="button" class="modal-close" onclick="hideDeleteModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="width: 48px; height: 48px; margin: 0 auto 20px; background: rgba(185, 28, 28, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #b91c1c;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </div>
            <h4 style="margin: 0 0 12px; font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 400; color: #0a0a0a;">Delete Supplier?</h4>
            <p style="margin: 0 0 20px; font-size: 14px; color: rgba(0, 0, 0, 0.6); line-height: 1.6;">Are you sure you want to delete <strong id="deleteSupplierName"></strong>? This will also remove all related products and purchase orders. This action cannot be undone.</p>
            
            <form id="deleteForm" method="POST" action="delete_supplier.php">
                <input type="hidden" id="deleteSupplierId" name="supplier_id">
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
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
}

.btn {
    padding: 10px 20px;
    border: none;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-secondary {
    background: #fafafa;
    color: #0a0a0a;
    border: 1px solid rgba(0,0,0,0.15);
}

.btn-secondary:hover {
    background: #ffffff;
    border-color: #0a0a0a;
}

.btn-danger {
    background: #b91c1c;
    color: #ffffff;
}

.btn-danger:hover {
    background: #dc2626;
}

@media (max-width: 600px) {
    .modal-container {
        width: 95%;
        margin: 20px;
    }
    
    .modal-header,
    .modal-body {
        padding-left: 20px;
        padding-right: 20px;
    }
}
</style>

<script>
function showDeleteModal(supplierId, supplierName) {
    document.getElementById('deleteSupplierId').value = supplierId;
    document.getElementById('deleteSupplierName').textContent = supplierName;
    
    const modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

// Close modal when clicking overlay
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) hideDeleteModal();
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDeleteModal();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>