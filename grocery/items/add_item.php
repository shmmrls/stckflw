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

// Get user's store information
$store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
$store_stmt->bind_param("i", $user_id);
$store_stmt->execute();
$store_result = $store_stmt->get_result();
$user_store = $store_result->fetch_assoc();
$store_id = $user_store['store_id'];

if (!$store_id) {
    die("Error: No store assigned to your account. Please contact support.");
}

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Get suppliers with their product count
$suppliers = $conn->query("
    SELECT s.*, 
           COUNT(sp.supplier_product_id) as product_count 
    FROM suppliers s
    LEFT JOIN supplier_products sp ON s.supplier_id = sp.supplier_id AND sp.is_available = 1
    WHERE s.is_active = 1
    GROUP BY s.supplier_id
    ORDER BY s.supplier_name
");

// Pre-fill from barcode scan
$barcode = $_GET['barcode'] ?? '';
$prefill_name = $_GET['item_name'] ?? '';
$prefill_category = $_GET['category_id'] ?? '';
$prefill_catalog = $_GET['catalog_id'] ?? '';
$prefill_unit = $_GET['unit'] ?? '';
$prefill_shelf_life = $_GET['shelf_life'] ?? '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $barcode = trim($_POST['barcode']);
    $catalog_id = !empty($_POST['catalog_id']) ? $_POST['catalog_id'] : null;
    $category_id = $_POST['category_id'];
    $supplier_id = $_POST['supplier_id'] ?: NULL;
    $supplier_product_id = !empty($_POST['supplier_product_id']) ? $_POST['supplier_product_id'] : NULL;
    $batch_number = trim($_POST['batch_number']);
    $quantity = $_POST['quantity'];
    $unit = trim($_POST['unit']);
    $purchase_date = $_POST['purchase_date'];
    $received_date = $_POST['received_date'];
    $expiry_date = $_POST['expiry_date'];
    $cost_price = $_POST['cost_price'];
    $selling_price = $_POST['selling_price'];
    $reorder_level = $_POST['reorder_level'];
    $reorder_quantity = $_POST['reorder_quantity'];
    $sku = trim($_POST['sku']);
    
    // Calculate expiry status
    $days_until_expiry = (strtotime($expiry_date) - time()) / (60 * 60 * 24);
    if ($days_until_expiry < 0) {
        $expiry_status = 'expired';
    } elseif ($days_until_expiry <= 7) {
        $expiry_status = 'near_expiry';
    } else {
        $expiry_status = 'fresh';
    }
    
    $alert_flag = ($expiry_status !== 'fresh') ? 1 : 0;
    
    // Insert item with enhanced supplier tracking
    $stmt = $conn->prepare("
        INSERT INTO grocery_items 
        (item_name, barcode, catalog_id, category_id, supplier_id, supplier_product_id,
         batch_number, quantity, unit, purchase_date, received_date, expiry_date, 
         expiry_status, alert_flag, cost_price, selling_price, reorder_level, reorder_quantity, 
         sku, created_by, store_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssiiisdsssssiiddddii", 
        $item_name, $barcode, $catalog_id, $category_id, $supplier_id, $supplier_product_id,
        $batch_number, $quantity, $unit, $purchase_date, $received_date, $expiry_date, 
        $expiry_status, $alert_flag, $cost_price, $selling_price, $reorder_level, $reorder_quantity,
        $sku, $user_id, $store_id
    );
    
    if ($stmt->execute()) {
        $item_id = $conn->insert_id();
        
        // Log inventory update
        $update_stmt = $conn->prepare("
            INSERT INTO grocery_inventory_updates 
            (item_id, store_id, update_type, quantity_change, updated_by, notes) 
            VALUES (?, ?, 'added', ?, ?, ?)
        ");
        $notes = $batch_number ? "Initial addition - Batch: $batch_number" : "Initial addition";
        $update_stmt->bind_param("iidis", $item_id, $store_id, $quantity, $user_id, $notes);
        $update_stmt->execute();
        
        // Log barcode scan if barcode was used
        if (!empty($barcode)) {
            $scan_log = $conn->prepare("
                INSERT INTO barcode_scan_history (user_id, barcode, scan_type, product_found, item_id) 
                VALUES (?, ?, 'add_item', 1, ?)
            ");
            $scan_log->bind_param("isi", $user_id, $barcode, $item_id);
            $scan_log->execute();
        }
        
        $success = "Item added successfully to inventory!";
        
        // Clear form
        $item_name = $barcode = $batch_number = $sku = '';
        $catalog_id = $supplier_id = $supplier_product_id = null;
    } else {
        $error = "Failed to add item. Please try again.";
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
                    <h1 class="page-title">Add New Item</h1>
                    <p class="page-subtitle">Add products to your store inventory with supplier tracking</p>
                </div>
                <div class="header-actions">
                    <a href="barcode_scanner_for_grocery_items.php" class="btn-scan">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
                        </svg>
                        Scan Barcode
                    </a>
                    <a href="../../grocery/items/grocery_items.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Inventory
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" action="" class="create-form">
            
            <!-- Product Information -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Product Information</h2>
                    <p class="section-description">Basic details about the product</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Barcode (Optional)</label>
                        <div class="input-with-icon">
                            <span class="input-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                            </span>
                            <input type="text" id="barcode" name="barcode" class="form-input with-icon" 
                                   value="<?php echo htmlspecialchars($barcode); ?>" 
                                   placeholder="Scan or enter barcode number">
                        </div>
                        <input type="hidden" name="catalog_id" value="<?php echo htmlspecialchars($prefill_catalog); ?>">
                        <small class="form-hint">Use the scanner above or enter manually</small>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Product Name *</label>
                        <input type="text" id="item_name" name="item_name" class="form-input" 
                               value="<?php echo htmlspecialchars($prefill_name); ?>" 
                               placeholder="Enter product name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">SKU (Stock Keeping Unit)</label>
                        <input type="text" id="sku" name="sku" class="form-input" placeholder="e.g., SKU-001">
                        <small class="form-hint">Optional internal product code</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            <option value="">Select category</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['category_id']; ?>" 
                                    <?php echo ($prefill_category == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Supplier Information (Enhanced) -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Supplier Information</h2>
                    <p class="section-description">Track product source and pricing</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="form-select">
                            <option value="">Select supplier (optional)</option>
                            <?php 
                            if ($suppliers->num_rows > 0):
                                $suppliers->data_seek(0);
                                while ($sup = $suppliers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $sup['supplier_id']; ?>" 
                                        data-type="<?php echo $sup['supplier_type']; ?>"
                                        data-products="<?php echo $sup['product_count']; ?>">
                                    <?php echo htmlspecialchars($sup['supplier_name']); ?>
                                    <?php if ($sup['product_count'] > 0): ?>
                                        (<?php echo $sup['product_count']; ?> products)
                                    <?php endif; ?>
                                </option>
                            <?php 
                                endwhile;
                            endif;
                            ?>
                        </select>
                        <small class="form-hint">Where you source this product from</small>
                    </div>

                    <div class="form-group" id="supplier_product_group" style="display: none;">
                        <label class="form-label">Supplier Product</label>
                        <select id="supplier_product_id" name="supplier_product_id" class="form-select">
                            <option value="">Select supplier product</option>
                        </select>
                        <small class="form-hint">Specific product from this supplier with pricing</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Batch/Lot Number</label>
                        <input type="text" id="batch_number" name="batch_number" class="form-input" 
                               placeholder="e.g., BATCH-2026-001">
                        <small class="form-hint">For traceability and quality control</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Received Date</label>
                        <input type="date" id="received_date" name="received_date" class="form-input">
                        <small class="form-hint">When items were received from supplier</small>
                    </div>
                </div>

                <div id="supplier_info_display" style="display: none; margin-top: 15px; padding: 15px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 4px;">
                    <div style="font-size: 12px; color: #0369a1;">
                        <strong>Supplier Details:</strong>
                        <div id="supplier_details" style="margin-top: 8px;"></div>
                    </div>
                </div>
            </div>

            <!-- Quantity & Unit -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Quantity & Unit</h2>
                    <p class="section-description">Stock levels and measurements</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" class="form-input" 
                               step="0.01" min="0.01" placeholder="0.00" required>
                        <small class="form-hint">Current stock quantity</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Unit *</label>
                        <input type="text" id="unit" name="unit" class="form-input" 
                               value="<?php echo htmlspecialchars($prefill_unit ?: 'pcs'); ?>" 
                               placeholder="e.g., pcs, kg, liters" required>
                        <small class="form-hint">Measurement unit</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reorder Level *</label>
                        <input type="number" id="reorder_level" name="reorder_level" class="form-input" 
                               step="0.01" min="0" value="10.00" required>
                        <small class="form-hint">Alert when stock reaches this level</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reorder Quantity *</label>
                        <input type="number" id="reorder_quantity" name="reorder_quantity" class="form-input" 
                               step="0.01" min="0" value="50.00" required>
                        <small class="form-hint">Suggested reorder amount</small>
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Pricing</h2>
                    <p class="section-description">Cost and selling prices</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Cost Price *</label>
                        <div class="input-with-icon">
                            <span class="input-icon">₱</span>
                            <input type="number" id="cost_price" name="cost_price" class="form-input with-icon" 
                                   step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <small class="form-hint">How much you paid per unit</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Selling Price *</label>
                        <div class="input-with-icon">
                            <span class="input-icon">₱</span>
                            <input type="number" id="selling_price" name="selling_price" class="form-input with-icon" 
                                   step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <small class="form-hint">Price you'll sell at</small>
                    </div>

                    <div class="form-group full-width">
                        <div id="profit-margin" style="padding: 15px; background: #f0f9ff; border: 1px solid #bfdbfe; font-size: 12px; color: #1e40af; display: none;">
                            <strong>Profit Margin:</strong> <span id="margin-amount">₱0.00</span> (<span id="margin-percent">0%</span>) per unit
                            <span id="total-profit" style="margin-left: 15px;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dates -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Dates</h2>
                    <p class="section-description">Purchase and expiration dates</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Purchase Date *</label>
                        <input type="date" id="purchase_date" name="purchase_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expiry Date *</label>
                        <input type="date" id="expiry_date" name="expiry_date" class="form-input" required>
                        <small class="form-hint">We'll alert you when it's near expiry</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Add Item to Inventory
                </button>
                <a href="../../grocery/grocery_dashboard.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
// Set today as default purchase and received date
document.getElementById('purchase_date').valueAsDate = new Date();
document.getElementById('received_date').valueAsDate = new Date();

// Auto-calculate expiry date based on shelf life
<?php if ($prefill_shelf_life): ?>
const shelfLife = <?php echo (int)$prefill_shelf_life; ?>;
if (shelfLife > 0) {
    const purchaseDate = new Date();
    const expiryDate = new Date(purchaseDate);
    expiryDate.setDate(expiryDate.getDate() + shelfLife);
    document.getElementById('expiry_date').valueAsDate = expiryDate;
}
<?php endif; ?>

// Supplier selection handler
document.getElementById('supplier_id').addEventListener('change', function() {
    const supplierId = this.value;
    const supplierProductGroup = document.getElementById('supplier_product_group');
    const supplierProductSelect = document.getElementById('supplier_product_id');
    const supplierInfoDisplay = document.getElementById('supplier_info_display');
    const supplierDetails = document.getElementById('supplier_details');
    
    if (supplierId) {
        // Fetch supplier products
        fetch(`get_supplier_products.php?supplier_id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                supplierProductSelect.innerHTML = '<option value="">Select supplier product</option>';
                
                if (data.products && data.products.length > 0) {
                    data.products.forEach(product => {
                        const option = document.createElement('option');
                        option.value = product.supplier_product_id;
                        option.textContent = `${product.product_name} - ₱${parseFloat(product.unit_price).toFixed(2)} (${product.unit_size || ''})`;
                        option.dataset.price = product.unit_price;
                        option.dataset.minQty = product.minimum_order_quantity;
                        supplierProductSelect.appendChild(option);
                    });
                    supplierProductGroup.style.display = 'block';
                } else {
                    supplierProductGroup.style.display = 'none';
                }
                
                // Display supplier info
                if (data.supplier) {
                    let info = `<strong>${data.supplier.supplier_name}</strong> (${data.supplier.supplier_type})<br>`;
                    if (data.supplier.payment_terms) info += `Payment: ${data.supplier.payment_terms}<br>`;
                    if (data.supplier.delivery_schedule) info += `Delivery: ${data.supplier.delivery_schedule}`;
                    supplierDetails.innerHTML = info;
                    supplierInfoDisplay.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error fetching supplier products:', error);
                supplierProductGroup.style.display = 'none';
                supplierInfoDisplay.style.display = 'none';
            });
    } else {
        supplierProductGroup.style.display = 'none';
        supplierInfoDisplay.style.display = 'none';
    }
});

// Supplier product selection handler
document.getElementById('supplier_product_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        const price = selectedOption.dataset.price;
        const minQty = selectedOption.dataset.minQty;
        
        // Auto-fill cost price
        if (price) {
            document.getElementById('cost_price').value = parseFloat(price).toFixed(2);
            calculateProfitMargin();
        }
        
        // Show minimum order quantity warning
        if (minQty && parseInt(minQty) > 1) {
            alert(`Note: This supplier requires a minimum order quantity of ${minQty} units.`);
        }
    }
});

// Calculate profit margin
function calculateProfitMargin() {
    const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
    const quantity = parseFloat(document.getElementById('quantity').value) || 0;
    
    if (costPrice > 0 && sellingPrice > 0) {
        const profit = sellingPrice - costPrice;
        const marginPercent = ((profit / costPrice) * 100).toFixed(2);
        const totalProfit = profit * quantity;
        
        document.getElementById('margin-amount').textContent = '₱' + profit.toFixed(2);
        document.getElementById('margin-percent').textContent = marginPercent + '%';
        
        if (quantity > 0) {
            document.getElementById('total-profit').textContent = `Total: ₱${totalProfit.toFixed(2)}`;
        }
        
        document.getElementById('profit-margin').style.display = 'block';
        
        // Change color based on margin
        const marginDiv = document.getElementById('profit-margin');
        if (profit < 0) {
            marginDiv.style.background = '#fef2f2';
            marginDiv.style.borderColor = '#fecaca';
            marginDiv.style.color = '#b91c1c';
        } else if (marginPercent < 10) {
            marginDiv.style.background = '#fff7ed';
            marginDiv.style.borderColor = '#fed7aa';
            marginDiv.style.color = '#c2410c';
        } else {
            marginDiv.style.background = '#f0fdf4';
            marginDiv.style.borderColor = '#bbf7d0';
            marginDiv.style.color = '#166534';
        }
    } else {
        document.getElementById('profit-margin').style.display = 'none';
    }
}

document.getElementById('cost_price').addEventListener('input', calculateProfitMargin);
document.getElementById('selling_price').addEventListener('input', calculateProfitMargin);
document.getElementById('quantity').addEventListener('input', calculateProfitMargin);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>