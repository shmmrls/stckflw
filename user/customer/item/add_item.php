<?php
require_once __DIR__ . '/../../../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Get user's groups
$groups_stmt = $conn->prepare("
    SELECT g.group_id, g.group_name 
    FROM groups g
    INNER JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
");
$groups_stmt->bind_param("i", $user_id);
$groups_stmt->execute();
$groups = $groups_stmt->get_result();

// Pre-fill from barcode scan
$barcode = $_GET['barcode'] ?? '';
$prefill_name = $_GET['product_name'] ?? '';
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
    $group_id = $_POST['group_id'];
    $quantity = $_POST['quantity'];
    $unit = trim($_POST['unit']);
    $purchase_date = $_POST['purchase_date'];
    $expiry_date = $_POST['expiry_date'];
    $purchased_from = trim($_POST['purchased_from']);
    
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
    
    // Insert item with barcode
    $stmt = $conn->prepare("
        INSERT INTO customer_items 
        (item_name, barcode, catalog_id, category_id, group_id, quantity, unit, purchase_date, expiry_date, 
         expiry_status, alert_flag, purchased_from, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssiidssssiis", $item_name, $barcode, $catalog_id, $category_id, $group_id, $quantity, 
                      $unit, $purchase_date, $expiry_date, $expiry_status, $alert_flag, 
                      $purchased_from, $user_id);
    
    if ($stmt->execute()) {
        $item_id = $conn->insert_id();
        
        // Award points (+5 for adding item)
        $points_stmt = $conn->prepare("
            INSERT INTO user_points (user_id, total_points) 
            VALUES (?, 5) 
            ON DUPLICATE KEY UPDATE total_points = total_points + 5
        ");
        $points_stmt->bind_param("i", $user_id);
        $points_stmt->execute();
        
        // Log points
        $log_stmt = $conn->prepare("
            INSERT INTO points_log (user_id, action_type, points_earned, item_id) 
            VALUES (?, 'ADD_ITEM', 5, ?)
        ");
        $log_stmt->bind_param("ii", $user_id, $item_id);
        $log_stmt->execute();
        
        // Log inventory update
        $update_stmt = $conn->prepare("
            INSERT INTO customer_inventory_updates 
            (item_id, update_type, quantity_change, updated_by, notes) 
            VALUES (?, 'added', ?, ?, 'Initial addition')
        ");
        $update_stmt->bind_param("idi", $item_id, $quantity, $user_id);
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
        
        $success = "Item added successfully! You earned 5 points!";
    } else {
        $error = "Failed to add item. Please try again.";
    }
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/add_item.css">';
require_once __DIR__ . '/../../../includes/header.php';
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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Add New Item</h1>
                    <p class="page-subtitle">Track your inventory and earn points</p>
                </div>
                <div class="header-actions">
                    <a href="barcode_scanner.php" class="btn-scan">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
                        </svg>
                        Scan Barcode
                    </a>
                    <a href="../dashboard.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" action="" class="create-form">
            
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Item Information</h2>
                    <p class="section-description">Basic details about your item</p>
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
                            <input type="text" id="barcode" name="barcode" class="form-input with-icon" value="<?php echo htmlspecialchars($barcode); ?>" placeholder="Scan or enter barcode number">
                        </div>
                        <input type="hidden" name="catalog_id" value="<?php echo htmlspecialchars($prefill_catalog); ?>">
                        <small class="form-hint">Use the scanner above or enter manually</small>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Item Name *</label>
                        <input type="text" id="item_name" name="item_name" class="form-input" value="<?php echo htmlspecialchars($prefill_name); ?>" placeholder="Enter item name" required>
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

                    <div class="form-group">
                        <label class="form-label">Group *</label>
                        <select id="group_id" name="group_id" class="form-select" required>
                            <option value="">Select group</option>
                            <?php 
                            $groups->data_seek(0);
                            while ($grp = $groups->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $grp['group_id']; ?>">
                                    <?php echo htmlspecialchars($grp['group_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Quantity & Unit</h2>
                    <p class="section-description">Specify amount and measurement</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" class="form-input" step="0.01" min="0.01" placeholder="0.00" required>
                        <small class="form-hint">Amount you're adding</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Unit *</label>
                        <input type="text" id="unit" name="unit" class="form-input" value="<?php echo htmlspecialchars($prefill_unit ?: 'pcs'); ?>" placeholder="e.g., pcs, kg, liters" required>
                        <small class="form-hint">Measurement unit</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Dates & Purchase Info</h2>
                    <p class="section-description">Track when and where you bought this item</p>
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

                    <div class="form-group full-width">
                        <label class="form-label">Purchased From</label>
                        <div class="input-with-icon">
                            <span class="input-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                                </svg>
                            </span>
                            <input type="text" id="purchased_from" name="purchased_from" class="form-input with-icon" placeholder="e.g., SM Supermarket, 7-Eleven">
                        </div>
                        <small class="form-hint">Optional: Store or location name</small>
                    </div>
                </div>
            </div>

            <div class="form-section points-section">
                <div class="points-info">
                    <div class="points-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </div>
                    <div class="points-text">
                        <div class="points-title">Earn Rewards!</div>
                        <div class="points-description">You'll earn <strong>5 points</strong> for adding this item to your inventory</div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Add Item & Earn Points
                </button>
                <a href="../dashboard.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
    // Set today as default purchase date
    document.getElementById('purchase_date').valueAsDate = new Date();
    
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
    
    // Update file name display for file inputs if any added in future
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function() {
            const fileNameSpan = this.parentElement.querySelector('.file-name');
            if (this.files.length > 0) {
                if (this.files.length === 1) {
                    fileNameSpan.textContent = this.files[0].name;
                } else {
                    fileNameSpan.textContent = `${this.files.length} files selected`;
                }
            } else {
                fileNameSpan.textContent = 'No file chosen';
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php $conn->close(); ?>