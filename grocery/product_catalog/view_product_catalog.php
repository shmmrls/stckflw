<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';
$conn = getDBConnection();

// Handle AJAX request to get product data for editing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_product'])) {
    $catalog_id = intval($_GET['get_product']);
    
    $stmt = $conn->prepare("
        SELECT pc.*, c.category_name
        FROM product_catalog pc
        LEFT JOIN categories c ON pc.category_id = c.category_id
        WHERE pc.catalog_id = ?
    ");
    $stmt->bind_param("i", $catalog_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
    exit();
}

// Handle AJAX edit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $catalog_id = intval($_POST['catalog_id']);
    $product_name = trim($_POST['product_name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $category_id = (isset($_POST['category_id']) && $_POST['category_id'] !== '') ? intval($_POST['category_id']) : null;
    $default_unit = trim($_POST['default_unit'] ?? 'pcs');
    $shelf_life = (isset($_POST['shelf_life']) && $_POST['shelf_life'] !== '') ? intval($_POST['shelf_life']) : null;
    $description = trim($_POST['description'] ?? '');
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;

    if (empty($product_name)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Product name is required.']);
        exit();
    }

    $update_stmt = $conn->prepare("
        UPDATE product_catalog 
        SET product_name = ?, brand = ?, category_id = ?, default_unit = ?, 
            typical_shelf_life_days = ?, description = ?, is_verified = ?, updated_at = NOW()
        WHERE catalog_id = ?
    ");

    $update_stmt->bind_param("sssisssi", $product_name, $brand, $category_id, $default_unit, $shelf_life, $description, $is_verified, $catalog_id);

    if ($update_stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $conn->error]);
    }
    exit();
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_product'])) {
    $catalog_id = intval($_POST['catalog_id']);
    
    $delete_stmt = $conn->prepare("DELETE FROM product_catalog WHERE catalog_id = ?");
    $delete_stmt->bind_param("i", $catalog_id);
    
    if ($delete_stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error deleting product: ' . $conn->error]);
    }
    exit();
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $catalog_id = intval($_POST['catalog_id']);
    
    $delete_stmt = $conn->prepare("DELETE FROM product_catalog WHERE catalog_id = ?");
    $delete_stmt->bind_param("i", $catalog_id);
    
    if ($delete_stmt->execute()) {
        $success_message = "Product deleted successfully!";
    } else {
        $error_message = "Error deleting product: " . $conn->error;
    }
}

// Pagination settings
$items_per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(pc.product_name LIKE ? OR pc.brand LIKE ? OR pc.barcode LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($category_filter > 0) {
    $where_conditions[] = "pc.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM product_catalog pc {$where_clause}";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get products
$query = "
    SELECT pc.*, c.category_name 
    FROM product_catalog pc
    LEFT JOIN categories c ON pc.category_id = c.category_id
    {$where_clause}
    ORDER BY pc.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/product_catalog.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="catalog-page">
    <div class="catalog-container">
        
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Product Catalog</h1>
                    <p class="page-subtitle">You are privileged! You may control the master product database. Any store may access this for their inventory management.</p>
                </div>
                <div class="header-actions">
                    <a href="barcode_scanner_prod_catalog.php" class="btn-scan">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
                        </svg>
                        Scan Barcode
                    </a>
                    <a href="add_product_catalog.php" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add Product
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <div class="search-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" 
                               name="search" 
                               class="search-input" 
                               placeholder="Search by name, brand, or barcode..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="filter-group">
                    <select name="category" class="filter-select">
                        <option value="0">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>" 
                                    <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    Filter
                </button>

                <?php if (!empty($search) || $category_filter > 0): ?>
                    <a href="view_product_catalog.php" class="btn-clear">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="results-summary">
                <span class="results-count">
                    <?php echo $total_items; ?> product<?php echo $total_items != 1 ? 's' : ''; ?> found
                </span>
            </div>
        </div>

        <?php if ($products->num_rows > 0): ?>
            <div class="catalog-table-wrapper">
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th>Barcode</th>
                            <th>Unit</th>
                            <th>Shelf Life</th>
                            <th class="text-center">Verified</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Product Name">
                                    <div class="product-name-cell">
                                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                    </div>
                                </td>
                                <td data-label="Brand"><?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?></td>
                                <td data-label="Category">
                                    <?php if ($product['category_name']): ?>
                                        <span class="category-badge">
                                            <?php echo htmlspecialchars($product['category_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Uncategorized</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Barcode">
                                    <code class="barcode-display">
                                        <?php echo htmlspecialchars($product['barcode']); ?>
                                    </code>
                                </td>
                                <td data-label="Unit"><?php echo htmlspecialchars($product['default_unit']); ?></td>
                                <td data-label="Shelf Life">
                                    <?php if ($product['typical_shelf_life_days']): ?>
                                        <?php echo $product['typical_shelf_life_days']; ?> days
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Verified" class="text-center">
                                    <?php if ($product['is_verified']): ?>
                                        <span class="status-badge verified">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                            Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge unverified">Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions" class="text-center">
                                    <div class="action-buttons">
                                        <button type="button" 
                                                class="btn-icon" 
                                                onclick="openEditModal(<?php echo $product['catalog_id']; ?>)"
                                                title="Edit">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <button type="button" 
                                                class="btn-icon btn-delete" 
                                                onclick="openDeleteModal(<?php echo $product['catalog_id']; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>', '<?php echo htmlspecialchars(addslashes($product['brand'] ?? 'N/A')); ?>', '<?php echo htmlspecialchars(addslashes($product['barcode'])); ?>')"
                                                title="Delete">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?>" 
                           class="pagination-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                            Previous
                        </a>
                    <?php endif; ?>

                    <div class="pagination-numbers">
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?>" 
                               class="pagination-number">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?>" 
                               class="pagination-number <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?>" 
                               class="pagination-number"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                    </div>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category_filter > 0 ? '&category=' . $category_filter : ''; ?>" 
                           class="pagination-btn">
                            Next
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h3 class="empty-title">No Products Found</h3>
                <p class="empty-description">
                    <?php if (!empty($search) || $category_filter > 0): ?>
                        No products match your search criteria. Try adjusting your filters.
                    <?php else: ?>
                        Start building your product catalog by scanning barcodes or adding products manually.
                    <?php endif; ?>
                </p>
                <div class="empty-actions">
                    <?php if (!empty($search) || $category_filter > 0): ?>
                        <a href="view_product_catalog.php" class="btn-secondary">Clear Filters</a>
                    <?php endif; ?>
                    <a href="../items/barcode_scanner_prod_catalog.php" class="btn-primary">Scan Barcode</a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Edit Product Modal -->
<div id="edit-modal" class="modal">
    <div class="modal-backdrop" onclick="closeEditModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Product</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <form id="edit-form" class="modal-form">
            <input type="hidden" id="edit-catalog-id" name="catalog_id">
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Product Name *</label>
                    <input type="text" id="edit-product-name" name="product_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Brand</label>
                    <input type="text" id="edit-brand" name="brand" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select id="edit-category" name="category_id" class="form-select">
                        <option value="">Select Category</option>
                        <?php 
                        // Reset categories result pointer
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Default Unit *</label>
                    <select id="edit-unit" name="default_unit" class="form-select" required>
                        <option value="pcs">Pieces</option>
                        <option value="kg">Kilograms</option>
                        <option value="g">Grams</option>
                        <option value="L">Liters</option>
                        <option value="mL">Milliliters</option>
                        <option value="box">Box</option>
                        <option value="pack">Pack</option>
                        <option value="can">Can</option>
                        <option value="bottle">Bottle</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Typical Shelf Life (days)</label>
                    <input type="number" id="edit-shelf-life" name="shelf_life" class="form-input" min="0">
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox-label">
                        <input type="checkbox" id="edit-verified" name="is_verified" class="form-checkbox">
                        <span class="checkbox-text">Verified Product</span>
                    </label>
                </div>
                
                <div class="form-group full-width">
                    <label class="form-label">Description</label>
                    <textarea id="edit-description" name="description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal">
    <div class="modal-backdrop" onclick="closeDeleteModal()"></div>
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h3 class="modal-title">Delete Product</h3>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="delete-warning">
                <div class="warning-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <p class="warning-message">Are you sure you want to delete this product?</p>
            </div>
            
            <div class="product-details">
                <div class="detail-row">
                    <span class="detail-label">Product Name:</span>
                    <span class="detail-value" id="delete-product-name"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Brand:</span>
                    <span class="detail-value" id="delete-product-brand"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Barcode:</span>
                    <span class="detail-value barcode-value" id="delete-product-barcode"></span>
                </div>
            </div>
            
            <div class="delete-confirmation">
                <p class="confirmation-text">This action cannot be undone. The product will be permanently removed from the catalog.</p>
            </div>
        </div>
        
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn-danger" onclick="confirmDelete()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
                Delete Product
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form id="delete-form" method="POST" style="display: none;">
    <input type="hidden" name="delete_product" value="1">
    <input type="hidden" name="catalog_id" id="delete-catalog-id">
</form>

<script>
// Global variables
let currentDeleteId = null;

// Edit Modal Functions
function openEditModal(catalogId) {
    // Fetch product data via AJAX
    fetch(`view_product_catalog.php?get_product=${catalogId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate form fields
                document.getElementById('edit-catalog-id').value = data.product.catalog_id;
                document.getElementById('edit-product-name').value = data.product.product_name;
                document.getElementById('edit-brand').value = data.product.brand || '';
                document.getElementById('edit-category').value = data.product.category_id || '';
                document.getElementById('edit-unit').value = data.product.default_unit;
                document.getElementById('edit-shelf-life').value = data.product.typical_shelf_life_days || '';
                document.getElementById('edit-description').value = data.product.description || '';
                document.getElementById('edit-verified').checked = data.product.is_verified == 1;
                
                // Show modal
                document.getElementById('edit-modal').classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                showAlert('error', data.message || 'Error loading product data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Error loading product data');
        });
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.remove('show');
    document.body.style.overflow = '';
    // Reset form
    document.getElementById('edit-form').reset();
}

// Delete Modal Functions
function openDeleteModal(catalogId, productName, brand, barcode) {
    currentDeleteId = catalogId;
    
    // Populate delete modal with product details
    document.getElementById('delete-product-name').textContent = productName;
    document.getElementById('delete-product-brand').textContent = brand;
    document.getElementById('delete-product-barcode').textContent = barcode;
    
    // Show modal
    document.getElementById('delete-modal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.remove('show');
    document.body.style.overflow = '';
    currentDeleteId = null;
}

function confirmDelete() {
    if (!currentDeleteId) return;
    
    // Show loading state
    const deleteBtn = document.querySelector('#delete-modal .btn-danger');
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> Deleting...';
    deleteBtn.disabled = true;
    
    // Send AJAX request
    const formData = new FormData();
    formData.append('ajax_delete_product', '1');
    formData.append('catalog_id', currentDeleteId);
    
    fetch('view_product_catalog.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the table row
            const row = document.querySelector(`tr:has(button[onclick*="${currentDeleteId}"])`);
            if (row) {
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            
            // Close modal and show success message
            closeDeleteModal();
            showAlert('success', data.message);
            
            // Update results count if needed
            updateResultsCount();
        } else {
            showAlert('error', data.message || 'Error deleting product');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Error deleting product');
    })
    .finally(() => {
        // Reset button state
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
    });
}

// Edit Form Submission
document.getElementById('edit-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> Saving...';
    submitBtn.disabled = true;
    
    // Send AJAX request
    const formData = new FormData(this);
    formData.append('edit_product', '1');
    
    fetch('view_product_catalog.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the table row with new data
            updateTableRow(document.getElementById('edit-catalog-id').value, formData);
            
            // Close modal and show success message
            closeEditModal();
            showAlert('success', data.message);
        } else {
            showAlert('error', data.message || 'Error updating product');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Error updating product');
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Helper Functions
function updateTableRow(catalogId, formData) {
    const row = document.querySelector(`tr:has(button[onclick*="${catalogId}"])`);
    if (!row) return;
    
    // Update cells with new data
    const cells = row.cells;
    cells[0].querySelector('strong').textContent = formData.get('product_name');
    cells[1].textContent = formData.get('brand') || 'N/A';
    
    // Update category
    const categorySelect = document.getElementById('edit-category');
    const categoryText = categorySelect.options[categorySelect.selectedIndex]?.text || 'Uncategorized';
    if (categoryText === 'Uncategorized') {
        cells[2].innerHTML = '<span class="text-muted">Uncategorized</span>';
    } else {
        cells[2].innerHTML = `<span class="category-badge">${categoryText}</span>`;
    }
    
    // Update unit
    cells[4].textContent = formData.get('default_unit');
    
    // Update shelf life
    const shelfLife = formData.get('shelf_life');
    cells[5].innerHTML = shelfLife ? `${shelfLife} days` : '<span class="text-muted">N/A</span>';
    
    // Update verified status
    const isVerified = formData.get('is_verified') === 'on';
    if (isVerified) {
        cells[6].innerHTML = `
            <span class="status-badge verified">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Verified
            </span>
        `;
    } else {
        cells[6].innerHTML = '<span class="status-badge unverified">Unverified</span>';
    }
}

function updateResultsCount() {
    const countElement = document.querySelector('.results-count');
    if (countElement) {
        const currentCount = parseInt(countElement.textContent.match(/\d+/)[0]);
        const newCount = currentCount - 1;
        countElement.textContent = `${newCount} product${newCount !== 1 ? 's' : ''} found`;
    }
}

function showAlert(type, message) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    
    const icon = type === 'success' 
        ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'
        : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    
    alert.innerHTML = `${icon} ${message}`;
    
    // Insert after page header
    const pageHeader = document.querySelector('.page-header');
    pageHeader.parentNode.insertBefore(alert, pageHeader.nextSibling);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        alert.style.transition = 'opacity 0.3s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('edit-modal').classList.contains('show')) {
            closeEditModal();
        }
        if (document.getElementById('delete-modal').classList.contains('show')) {
            closeDeleteModal();
        }
    }
});

// Close modals on backdrop click (already handled by onclick on backdrop elements)
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>