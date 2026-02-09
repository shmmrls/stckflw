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

$errors = [];
$success = '';

$supplier_product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch product details
$query = "SELECT sp.*, s.supplier_id, s.supplier_name, pc.barcode 
          FROM supplier_products sp
          JOIN suppliers s ON sp.supplier_id = s.supplier_id
          LEFT JOIN product_catalog pc ON sp.catalog_id = pc.catalog_id
          WHERE sp.supplier_product_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $supplier_product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: ../suppliers/view_suppliers.php");
    exit();
}

// Fetch all categories
$query = "SELECT * FROM categories ORDER BY category_name";
$result = $conn->query($query);
$categories = $result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    if (empty($errors)) {
        // Check if price changed
        $price_changed = ($product['unit_price'] != $unit_price);
        
        // Update supplier_products
        $query = "UPDATE supplier_products 
                 SET supplier_sku = ?, 
                     product_name = ?, 
                     brand = ?, 
                     category_id = ?, 
                     unit_price = ?, 
                     unit_size = ?, 
                     minimum_order_quantity = ?, 
                     lead_time_days = ?, 
                     is_available = ?, 
                     notes = ?,
                     last_price_update = " . ($price_changed ? "CURDATE()" : "last_price_update") . "
                 WHERE supplier_product_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssidissssi", 
            $supplier_sku, $product_name, $brand, $category_id,
            $unit_price, $unit_size, $minimum_order_quantity, $lead_time_days,
            $is_available, $notes, $supplier_product_id
        );
        $stmt->execute();
        
        // Update product catalog if linked
        if ($product['catalog_id']) {
            $query = "UPDATE product_catalog 
                     SET product_name = ?, 
                         brand = ?, 
                         category_id = ?
                     WHERE catalog_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $product_name, $brand, $category_id, $product['catalog_id']);
            $stmt->execute();
        }
        
        $success = "Product updated successfully!";
        
        // Redirect back to supplier products
        header("Location: view_supplier_products.php?supplier_id=" . $product['supplier_id'] . "&updated=1");
        exit();
    }
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($GLOBALS['baseUrl']) . '/includes/style/pages/supplier_products.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="supplier-products-page">
    <div class="supplier-products-container">
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Edit Supplier Product</h1>
                    <p class="page-subtitle">
                        Supplier: <strong><?php echo htmlspecialchars($product['supplier_name']); ?></strong>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="view_supplier_products.php?supplier_id=<?php echo $product['supplier_id']; ?>" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Products
                    </a>
                </div>
            </div>
        </div>

        <!-- Price Update Notice -->
        <?php if ($product['last_price_update']): ?>
        <div class="alert alert-warning">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/>
            </svg>
            <strong>Last price update:</strong> <?php echo date('F d, Y', strtotime($product['last_price_update'])); ?>
            | Current Price: <strong>₱<?php echo number_format($product['unit_price'], 2); ?></strong>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="create-form">
            <!-- Product Details Section -->
            <div class="form-section">
                <div class="section-title">Product Details</div>
                
                <?php if ($product['catalog_id']): ?>
                <div class="alert alert-info">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-.07l-3 3a5 5 0 0 0 7.07.07l3-3a5 5 0 0 0-.07-.07z"/>
                    </svg>
                    This product is linked to main catalog (Catalog ID: <?php echo $product['catalog_id']; ?>)
                    <?php if ($product['barcode']): ?>
                        | Barcode: <strong><?php echo htmlspecialchars($product['barcode']); ?></strong>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="product_name" class="form-label">Product Name *</label>
                        <input type="text" id="product_name" name="product_name" class="form-input" 
                               value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" id="brand" name="brand" class="form-input" 
                               value="<?php echo htmlspecialchars($product['brand']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="category_id" class="form-label">Category</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                    <?php echo ($product['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Supplier-Specific Information -->
            <div class="form-section">
                <div class="section-title">Supplier-Specific Information</div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="supplier_sku" class="form-label">Supplier SKU</label>
                        <input type="text" id="supplier_sku" name="supplier_sku" class="form-input" 
                               value="<?php echo htmlspecialchars($product['supplier_sku']); ?>">
                        <small class="form-hint">Supplier's product code</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="unit_size" class="form-label">Unit Size</label>
                        <input type="text" id="unit_size" name="unit_size" class="form-input" 
                               value="<?php echo htmlspecialchars($product['unit_size']); ?>" 
                               placeholder="e.g., 1L, 500g, 12pcs">
                    </div>

                    <div class="form-group">
                        <label for="unit_price" class="form-label">Unit Price (₱) *</label>
                        <input type="number" id="unit_price" name="unit_price" 
                               step="0.01" min="0.01" value="<?php echo $product['unit_price']; ?>" class="form-input" required>
                        <small class="form-hint">Changing this will update last price update date</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="minimum_order_quantity" class="form-label">Minimum Order Quantity *</label>
                        <input type="number" id="minimum_order_quantity" name="minimum_order_quantity" 
                               min="1" value="<?php echo $product['minimum_order_quantity']; ?>" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lead_time_days" class="form-label">Lead Time (Days)</label>
                        <input type="number" id="lead_time_days" name="lead_time_days" 
                               min="0" value="<?php echo $product['lead_time_days']; ?>" class="form-input">
                        <small class="form-hint">Days needed for delivery</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" class="form-textarea" rows="3"><?php echo htmlspecialchars($product['notes']); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <div class="form-check">
                            <input type="checkbox" name="is_available" id="is_available" class="form-check-input" 
                                   <?php echo $product['is_available'] ? 'checked' : ''; ?>>
                            <label for="is_available" class="form-check-label">
                                Product is available for ordering
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Update Product
                </button>
                <a href="view_supplier_products.php?supplier_id=<?php echo $product['supplier_id']; ?>" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
// Warn user if changing price
const originalPrice = <?php echo $product['unit_price']; ?>;
document.getElementById('unit_price').addEventListener('change', function() {
    if (parseFloat(this.value) !== originalPrice) {
        const priceChange = ((parseFloat(this.value) - originalPrice) / originalPrice * 100).toFixed(2);
        const changeType = priceChange > 0 ? 'increase' : 'decrease';
        
        // Create warning alert
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning';
        alertDiv.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86c-.45-.38-.81-.38-1.26 0a1 1 0 0 0-.19.11l-.38.52a1 1 0 0 0-.11.38L10 17.25a1 1 0 0 0-1.25.12l-.38-.52a1 1 0 0 0-.11-.38L10.29 3.86z"/>
            </svg>
            Price ${changeType} of ${Math.abs(priceChange)}% detected. 
            New price: ₱${parseFloat(this.value).toFixed(2)} 
            (was ₱${originalPrice.toFixed(2)})
        `;
        
        // Insert after the input
        this.parentElement.appendChild(alertDiv);
        
        // Remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.parentElement.removeChild(alertDiv);
            }
        }, 5000);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>
</body>
</html>