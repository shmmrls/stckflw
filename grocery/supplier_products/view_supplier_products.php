<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

// Verify user is grocery admin
if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $GLOBALS['baseUrl'] . '/user/customer/dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

// Redirect if no supplier ID provided
if ($supplier_id <= 0) {
    header("Location: ../suppliers/view_suppliers.php");
    exit();
}

// Fetch supplier details
$supplier = null;
$query = "SELECT * FROM suppliers WHERE supplier_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$supplier = $result->fetch_assoc();

if (!$supplier) {
    header("Location: ../suppliers/view_suppliers.php");
    exit();
}

// Fetch supplier products with category info
$query = "SELECT sp.*, c.category_name, pc.barcode,
          (SELECT COUNT(*) FROM grocery_items gi 
           WHERE gi.supplier_product_id = sp.supplier_product_id) as items_in_stock
          FROM supplier_products sp
          LEFT JOIN categories c ON sp.category_id = c.category_id
          LEFT JOIN product_catalog pc ON sp.catalog_id = pc.catalog_id
          WHERE sp.supplier_id = ?
          ORDER BY sp.product_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_products,
    AVG(unit_price) as avg_price,
    AVG(lead_time_days) as avg_lead_time
    FROM supplier_products 
    WHERE supplier_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Set page title for header
$pageTitle = "Supplier Products - StockFlow";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../includes/style/pages/supplier_products1.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> 
            <span>Product added successfully!</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> 
            <span>Product updated successfully!</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="bi bi-info-circle"></i> 
            <span>Product removed from supplier catalog.</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Supplier Header -->
        <div class="page-header">
            <div>
                <h2><?php echo htmlspecialchars($supplier['supplier_name']); ?></h2>
                <p class="text-muted mb-0">
                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($supplier['supplier_type']); ?>
                    <?php if ($supplier['contact_number']): ?>
                        <span style="margin-left: 15px;"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($supplier['contact_number']); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="header-actions">
                <a href="add_supplier_product.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Product
                </a>
                <a href="import_supplier_products.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-secondary">
                    <i class="bi bi-upload"></i> Bulk Import
                </a>
                <a href="../suppliers/view_suppliers.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['available_products']; ?></div>
                <div class="stat-label">Available Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₱<?php echo number_format($stats['avg_price'], 2); ?></div>
                <div class="stat-label">Avg. Unit Price</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo round($stats['avg_lead_time']); ?> days</div>
                <div class="stat-label">Avg. Lead Time</div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="bi bi-box-seam"></i> Product Catalog
                    <span class="badge bg-primary"><?php echo count($products); ?> items</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>No products added yet.</p>
                    <a href="add_supplier_product.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Your First Product
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Unit Price</th>
                                <th>Unit Size</th>
                                <th>Min. Order</th>
                                <th>Lead Time</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                    <?php if ($product['brand']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($product['brand']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($product['barcode']): ?>
                                        <br><small class="text-muted"><i class="bi bi-upc-scan"></i> <?php echo htmlspecialchars($product['barcode']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['supplier_sku']): ?>
                                        <code><?php echo htmlspecialchars($product['supplier_sku']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['category_name']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>₱<?php echo number_format($product['unit_price'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($product['unit_size'] ?: '-'); ?></td>
                                <td><?php echo $product['minimum_order_quantity']; ?></td>
                                <td>
                                    <?php if ($product['lead_time_days']): ?>
                                        <?php echo $product['lead_time_days']; ?> days
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['items_in_stock'] > 0): ?>
                                        <span class="badge bg-success"><?php echo $product['items_in_stock']; ?> items</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['is_available']): ?>
                                        <span class="badge badge-available">Available</span>
                                    <?php else: ?>
                                        <span class="badge badge-unavailable">Unavailable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit_supplier_product.php?id=<?php echo $product['supplier_product_id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $product['supplier_product_id']; ?>)"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
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
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to remove this product from the supplier catalog?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" action="delete_supplier_product.php" style="display: inline;">
                        <input type="hidden" name="supplier_product_id" id="deleteProductId">
                        <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(productId) {
            document.getElementById('deleteProductId').value = productId;
            const deleteModal = document.getElementById('deleteModal');
            deleteModal.classList.add('show');
            deleteModal.style.display = 'flex';
        }

        // Close modal functionality
        document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                modal.classList.remove('show');
                modal.style.display = 'none';
            });
        });

        // Auto-dismiss alerts
        document.querySelectorAll('.alert-dismissible .btn-close').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.alert').style.display = 'none';
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>