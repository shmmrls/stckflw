<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in and is a grocery admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'grocery_admin') {
    header("Location: ../../login.php");
    exit();
}

$conn = getDBConnection();

$errors = [];
$success = '';

// Get supplier_id from URL or POST
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : (isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0);

// Fetch supplier details
$supplier = null;
if ($supplier_id > 0) {
    $query = "SELECT * FROM suppliers WHERE supplier_id = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    
    if (!$supplier) {
        header("Location: ../suppliers/view_suppliers.php");
        exit();
    }
}

// Fetch all categories
$query = "SELECT * FROM categories ORDER BY category_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);

// Fetch all products from catalog for selection
$query = "SELECT catalog_id, product_name, brand, barcode FROM product_catalog ORDER BY product_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$catalog_products = $result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)$_POST['supplier_id'];
    $catalog_id = !empty($_POST['catalog_id']) ? (int)$_POST['catalog_id'] : null;
    $supplier_sku = trim($_POST['supplier_sku']);
    $product_name = trim($_POST['product_name']);
    $brand = trim($_POST['brand']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $unit_price = floatval($_POST['unit_price']);
    $unit_size = trim($_POST['unit_size']);
    $minimum_order_quantity = (int)$_POST['minimum_order_quantity'];
    $lead_time_days = !empty($_POST['lead_time_days']) ? (int)$_POST['lead_time_days'] : null;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $notes = trim($_POST['notes']);
    
    // Validation
    if (empty($product_name)) {
        $errors[] = "Product name is required";
    }
    if ($unit_price <= 0) {
        $errors[] = "Unit price must be greater than 0";
    }
    if ($minimum_order_quantity < 1) {
        $errors[] = "Minimum order quantity must be at least 1";
    }
    
    // If creating new product in catalog
    if (empty($catalog_id) && isset($_POST['create_new_catalog'])) {
        $barcode = trim($_POST['barcode']);
        
        if (!empty($barcode)) {
            // Check if barcode already exists
            $query = "SELECT catalog_id FROM product_catalog WHERE barcode = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $barcode);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->fetch_assoc()) {
                $errors[] = "Barcode already exists in catalog";
            }
        }
        
        if (empty($errors)) {
            try {
                // Insert into product_catalog
                $query = "INSERT INTO product_catalog (barcode, product_name, brand, category_id, is_verified) 
                         VALUES (?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('sssi', $barcode, $product_name, $brand, $category_id);
                $stmt->execute();
                $catalog_id = $conn->insert_id;
            } catch (Exception $e) {
                $errors[] = "Error creating catalog entry: " . $e->getMessage();
            }
        }
    }
    
    if (empty($errors)) {
        try {
            // Insert into supplier_products
            $query = "INSERT INTO supplier_products 
                     (supplier_id, catalog_id, supplier_sku, product_name, brand, category_id, 
                      unit_price, unit_size, minimum_order_quantity, lead_time_days, 
                      is_available, last_price_update, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iisssiddiiss', $supplier_id, $catalog_id, $supplier_sku, $product_name, $brand, $category_id, $unit_price, $unit_size, $minimum_order_quantity, $lead_time_days, $is_available, $notes);
            $stmt->execute();
            
            $success = "Supplier product added successfully!";
            
            // Redirect to supplier products view
            header("Location: view_supplier_products.php?supplier_id=" . $supplier_id . "&success=1");
            exit();
            
        } catch (Exception $e) {
            $errors[] = "Error adding supplier product: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Supplier Product - StockFlow</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../includes/style/pages/supplier_products.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="supplier-products-page">
        <div class="supplier-products-container">
        <div class="breadcrumb">
            <a href="../dashboard.php">Dashboard</a>
            <span>/</span>
            <a href="../suppliers/view_suppliers.php">Suppliers</a>
            <span>/</span>
            <a href="view_supplier_products.php?supplier_id=<?php echo $supplier_id; ?>">Products</a>
            <span>/</span>
            <span class="current">Add Product</span>
        </div>

        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Add Supplier Product</h1>
                    <?php if ($supplier): ?>
                    <p class="page-subtitle">
                        Adding product for: <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <a href="view_supplier_products.php?supplier_id=<?php echo $supplier_id; ?>" class="btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Back to Products
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <div>
                <strong>Please correct the following errors:</strong>
                <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <div><?php echo htmlspecialchars($success); ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>">
            
            <!-- Product Selection Section -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    Product Selection
                </h3>
                
                <div class="form-grid">
                    <div class="form-group form-grid-full">
                        <label class="form-label">Select Existing Product from Catalog</label>
                        <select class="form-select" name="catalog_id" id="catalog_id">
                            <option value="">-- Select Product or Create New --</option>
                            <?php foreach ($catalog_products as $product): ?>
                            <option value="<?php echo $product['catalog_id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    data-brand="<?php echo htmlspecialchars($product['brand']); ?>"
                                    data-barcode="<?php echo htmlspecialchars($product['barcode']); ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                                <?php if ($product['brand']): ?>
                                    - <?php echo htmlspecialchars($product['brand']); ?>
                                <?php endif; ?>
                                (<?php echo htmlspecialchars($product['barcode']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-help">Or fill in the details below to create a new product</small>
                    </div>

                    <div class="form-group form-grid-full" style="display: flex; align-items: center; gap: 8px; margin-top: 10px;">
                        <input type="checkbox" name="create_new_catalog" id="create_new_catalog" style="width: auto; margin: 0;">
                        <label for="create_new_catalog" style="margin: 0; font-size: 13px; cursor: pointer; font-weight: normal; text-transform: none; letter-spacing: normal;">
                            Create new product in catalog (if not found above)
                        </label>
                    </div>
                </div>
            </div>

            <!-- Product Details Section -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                    Product Details
                </h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="product_name" class="form-label required">Product Name</label>
                        <input type="text" class="form-input" id="product_name" name="product_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" class="form-input" id="brand" name="brand">
                    </div>
                    
                    <div class="form-group">
                        <label for="barcode" class="form-label">Barcode</label>
                        <input type="text" class="form-input" id="barcode" name="barcode">
                        <small class="form-help">Only needed if creating new catalog entry</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Supplier-Specific Information -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                    Supplier-Specific Information
                </h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="supplier_sku" class="form-label">Supplier SKU</label>
                        <input type="text" class="form-input" id="supplier_sku" name="supplier_sku">
                        <small class="form-help">Supplier's product code</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="unit_size" class="form-label">Unit Size</label>
                        <input type="text" class="form-input" id="unit_size" name="unit_size" placeholder="e.g., 1L, 500g, 12pcs">
                    </div>
                    
                    <div class="form-group">
                        <label for="unit_price" class="form-label required">Unit Price (₱)</label>
                        <input type="number" class="form-input" id="unit_price" name="unit_price" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="minimum_order_quantity" class="form-label required">Minimum Order Qty</label>
                        <input type="number" class="form-input" id="minimum_order_quantity" name="minimum_order_quantity" min="1" value="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lead_time_days" class="form-label">Lead Time (Days)</label>
                        <input type="number" class="form-input" id="lead_time_days" name="lead_time_days" min="0">
                        <small class="form-help">Days needed for delivery</small>
                    </div>
                    
                    <div class="form-group form-grid-full">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-textarea" id="notes" name="notes" rows="3"></textarea>
                    </div>

                    <div class="form-group form-grid-full" style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="is_available" id="is_available" checked style="width: auto; margin: 0;">
                        <label for="is_available" style="margin: 0; font-size: 13px; cursor: pointer; font-weight: normal; text-transform: none; letter-spacing: normal;">
                            Product is available for ordering
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="view_supplier_products.php?supplier_id=<?php echo $supplier_id; ?>" class="btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    Add Product
                </button>
            </div>
        </form>
        </div>
    </div>

    <script>
        // Auto-fill product details when selecting from catalog
        document.getElementById('catalog_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                document.getElementById('product_name').value = selectedOption.dataset.name || '';
                document.getElementById('brand').value = selectedOption.dataset.brand || '';
                document.getElementById('barcode').value = selectedOption.dataset.barcode || '';
                
                // Disable create new catalog checkbox
                document.getElementById('create_new_catalog').checked = false;
                document.getElementById('create_new_catalog').disabled = true;
            } else {
                // Enable create new catalog checkbox
                document.getElementById('create_new_catalog').disabled = false;
            }
        });

        // Clear catalog selection when editing product name manually
        document.getElementById('product_name').addEventListener('input', function() {
            if (document.getElementById('catalog_id').value) {
                // User is manually editing, suggest creating new
                document.getElementById('create_new_catalog').disabled = false;
            }
        });
    </script>
</body>
</html>