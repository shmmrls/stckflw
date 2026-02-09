<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

// Verify user is grocery admin
if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $baseUrl . '/user/customer/dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Pre-fill from barcode scan
$barcode = $_GET['barcode'] ?? '';
$prefill_name = $_GET['product_name'] ?? '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim($_POST['barcode']);
    $product_name = trim($_POST['product_name']);
    $brand = trim($_POST['brand']);
    $category_id = $_POST['category_id'] ?: NULL;
    $default_unit = trim($_POST['default_unit']);
    $typical_shelf_life_days = $_POST['typical_shelf_life_days'] ?: NULL;
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']);
    
    // Check if barcode already exists
    $check_stmt = $conn->prepare("SELECT catalog_id FROM product_catalog WHERE barcode = ?");
    $check_stmt->bind_param("s", $barcode);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        $error = "A product with this barcode already exists in the catalog.";
    } else {
        // Insert product into catalog
        $stmt = $conn->prepare("
            INSERT INTO product_catalog 
            (barcode, product_name, brand, category_id, default_unit, typical_shelf_life_days, 
             description, image_url, is_verified) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("sssisiss", 
            $barcode, $product_name, $brand, $category_id, $default_unit, 
            $typical_shelf_life_days, $description, $image_url
        );
        
        if ($stmt->execute()) {
            $catalog_id = $conn->insert_id;
            
            // Log barcode scan
            if (!empty($barcode)) {
                $scan_log = $conn->prepare("
                    INSERT INTO barcode_scan_history (user_id, barcode, scan_type, product_found) 
                    VALUES (?, ?, 'add_item', 1)
                ");
                $scan_log->bind_param("is", $user_id, $barcode);
                $scan_log->execute();
            }
            
            $success = "Product added to catalog successfully! Other stores can now use this product data.";
        } else {
            $error = "Failed to add product to catalog. Please try again.";
        }
    }
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/add_item.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="create-page">
    <div class="create-container">
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
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
                    <h1 class="page-title">Add to Product Catalog</h1>
                    <p class="page-subtitle">Create a master product record for barcode scanning</p>
                </div>
                <div class="header-actions">
                    <a href="barcode_scanner_prod_catalog.php" class="btn-scan">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
                        </svg>
                        Scan Barcode
                    </a>
                    <a href="view_product_catalog.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Catalog
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" action="" class="create-form">
            
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Barcode & Identification</h2>
                    <p class="section-description">Unique identifiers for this product</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Barcode *</label>
                        <div class="input-with-icon">
                            <span class="input-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                            </span>
                            <input type="text" id="barcode" name="barcode" class="form-input with-icon" value="<?php echo htmlspecialchars($barcode); ?>" placeholder="Scan or enter barcode number" required>
                        </div>
                        <small class="form-hint">This barcode will be used for quick product lookup</small>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Product Name *</label>
                        <input type="text" id="product_name" name="product_name" class="form-input" value="<?php echo htmlspecialchars($prefill_name); ?>" placeholder="Enter full product name" required>
                        <small class="form-hint">Official product name as it appears on packaging</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <input type="text" id="brand" name="brand" class="form-input" placeholder="e.g., Alaska, Del Monte, Nestle">
                        <small class="form-hint">Manufacturer or brand name</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">Select category (optional)</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Default Product Specs</h2>
                    <p class="section-description">Standard information for this product</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Default Unit *</label>
                        <input type="text" id="default_unit" name="default_unit" class="form-input" value="pcs" placeholder="e.g., pcs, kg, liters, bottles" required>
                        <small class="form-hint">Common measurement unit</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Typical Shelf Life (Days)</label>
                        <input type="number" id="typical_shelf_life_days" name="typical_shelf_life_days" class="form-input" min="0" placeholder="e.g., 365, 30, 7">
                        <small class="form-hint">Average shelf life from production date</small>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Product Description</label>
                        <textarea id="description" name="description" class="form-textarea" rows="4" placeholder="Enter product description, ingredients, or additional details..."></textarea>
                        <small class="form-hint">Optional details about the product</small>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Product Image URL</label>
                        <div class="input-with-icon">
                            <span class="input-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                                </svg>
                            </span>
                            <input type="url" id="image_url" name="image_url" class="form-input with-icon" placeholder="https://example.com/product-image.jpg">
                        </div>
                        <small class="form-hint">Optional: URL to product image for reference</small>
                    </div>
                </div>
            </div>

            <div class="form-section points-section">
                <div class="points-info">
                    <div class="points-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                    </div>
                    <div class="points-text">
                        <div class="points-title">About Product Catalog</div>
                        <div class="points-description">Products added to the catalog become available for <strong>all stores</strong> to use. When scanning a barcode, if it exists in the catalog, product details will auto-fill to save time.</div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Add to Product Catalog
                </button>
                <a href="product_catalog.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
    // Auto-calculate expiry suggestion based on shelf life
    document.getElementById('typical_shelf_life_days').addEventListener('input', function() {
        const shelfLife = parseInt(this.value) || 0;
        if (shelfLife > 0) {
            const hint = this.parentElement.querySelector('.form-hint');
            const years = Math.floor(shelfLife / 365);
            const months = Math.floor((shelfLife % 365) / 30);
            const days = shelfLife % 30;
            
            let readableTime = '';
            if (years > 0) readableTime += years + ' year' + (years > 1 ? 's' : '');
            if (months > 0) {
                if (readableTime) readableTime += ', ';
                readableTime += months + ' month' + (months > 1 ? 's' : '');
            }
            if (days > 0 && !years) {
                if (readableTime) readableTime += ', ';
                readableTime += days + ' day' + (days > 1 ? 's' : '');
            }
            
            if (readableTime) {
                hint.textContent = 'Approximately ' + readableTime + ' shelf life';
            }
        }
    });
    
    // Preview image URL
    document.getElementById('image_url').addEventListener('blur', function() {
        const url = this.value.trim();
        const existingPreview = document.getElementById('image-preview');
        
        if (existingPreview) {
            existingPreview.remove();
        }
        
        if (url && url.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
            const preview = document.createElement('div');
            preview.id = 'image-preview';
            preview.style.cssText = 'margin-top: 10px; padding: 10px; background: #fafafa; border: 1px solid rgba(0,0,0,0.08); text-align: center;';
            preview.innerHTML = `
                <img src="${url}" alt="Product preview" 
                     style="max-width: 200px; max-height: 200px; object-fit: contain; border: 1px solid rgba(0,0,0,0.1);"
                     onerror="this.parentElement.innerHTML='<small style=color:rgba(0,0,0,0.5)>Failed to load image</small>'">
            `;
            this.parentElement.parentElement.appendChild(preview);
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>