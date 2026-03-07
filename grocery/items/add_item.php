<?php

require_once __DIR__ . '/../../includes/admin_auth_check.php';



$conn    = getDBConnection();

$user_id = getCurrentUserId();



// ── Store guard ────────────────────────────────────────────────────────────

$store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");

$store_stmt->bind_param("i", $user_id);

$store_stmt->execute();

$store_id = $store_stmt->get_result()->fetch_assoc()['store_id'] ?? null;

$store_stmt->close();



if (!$store_id) {

    die("Error: No store assigned to your account. Please contact support.");

}



// ── Reference data ─────────────────────────────────────────────────────────

$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");



// Only active suppliers that actually have products in supplier_products

$suppliers = $conn->query("

    SELECT s.supplier_id, s.supplier_name, s.supplier_type,

           COUNT(sp.supplier_product_id) AS product_count

    FROM suppliers s

    INNER JOIN supplier_products sp ON s.supplier_id = sp.supplier_id AND sp.is_available = 1

    WHERE s.is_active = 1

    GROUP BY s.supplier_id

    ORDER BY s.supplier_name

");



// ── URL pre-fill (from barcode scanner) ───────────────────────────────────

$prefill_barcode    = htmlspecialchars($_GET['barcode']      ?? '');

$prefill_name       = htmlspecialchars($_GET['item_name']    ?? '');

$prefill_category   = (int) ($_GET['category_id']           ?? 0);

$prefill_catalog    = (int) ($_GET['catalog_id']            ?? 0);

$prefill_unit       = htmlspecialchars($_GET['unit']        ?? 'pcs');

$prefill_shelf_life = (int) ($_GET['shelf_life']            ?? 0);



$error   = '';

$success = '';



// ── POST handling ──────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    // Determine which path the user took

    $add_mode = $_POST['add_mode'] ?? 'manual'; // 'supplier' | 'manual'



    // ── Common fields ──────────────────────────────────────────────────────

    $item_name      = trim($_POST['item_name']     ?? '');

    $barcode        = trim($_POST['barcode']       ?? '');

    $sku            = trim($_POST['sku']           ?? '');

    $category_id    = (int) ($_POST['category_id'] ?? 0);

    $quantity       = (float) ($_POST['quantity']  ?? 0);

    $unit           = trim($_POST['unit']          ?? 'pcs');

    $purchase_date  = $_POST['purchase_date']       ?? '';

    $received_date  = $_POST['received_date']       ?? '' ?: null;

    $expiry_date    = $_POST['expiry_date']         ?? '';

    $cost_price     = (float) ($_POST['cost_price']    ?? 0);

    $selling_price  = (float) ($_POST['selling_price'] ?? 0);

    $reorder_level  = (float) ($_POST['reorder_level'] ?? 10);

    $reorder_qty    = (float) ($_POST['reorder_quantity'] ?? 50);

    $batch_number   = trim($_POST['batch_number']  ?? '');



    // ── Supplier-path extras ───────────────────────────────────────────────

    $supplier_id         = ($add_mode === 'supplier' && !empty($_POST['supplier_id']))

                           ? (int) $_POST['supplier_id'] : null;

    $supplier_product_id = ($add_mode === 'supplier' && !empty($_POST['supplier_product_id']))

                           ? (int) $_POST['supplier_product_id'] : null;

    $catalog_id          = !empty($_POST['catalog_id'])

                           ? (int) $_POST['catalog_id'] : null;



    // ── Validation ─────────────────────────────────────────────────────────

    if (empty($item_name)) {

        $error = 'Product name is required.';

    } elseif ($category_id <= 0) {

        $error = 'Please select a category.';

    } elseif ($quantity <= 0) {

        $error = 'Quantity must be greater than 0.';

    } elseif (empty($unit)) {

        $error = 'Unit is required.';

    } elseif (empty($purchase_date)) {

        $error = 'Purchase date is required.';

    } elseif (empty($expiry_date)) {

        $error = 'Expiry date is required.';

    } elseif (strtotime($expiry_date) < strtotime($purchase_date)) {

        $error = 'Expiry date cannot be before purchase date.';

    } elseif ($cost_price < 0) {

        $error = 'Cost price cannot be negative.';

    } elseif ($selling_price < 0) {

        $error = 'Selling price cannot be negative.';

    } elseif ($add_mode === 'supplier' && !$supplier_id) {

        $error = 'Please select a supplier, or switch to manual entry.';

    } elseif ($add_mode === 'supplier' && !$supplier_product_id) {

        $error = 'Please select a supplier product, or switch to manual entry.';

    }



    // ── Supplier product ownership check ──────────────────────────────────

    if (empty($error) && $supplier_id && $supplier_product_id) {

        $sp_check = $conn->prepare("

            SELECT unit_price, catalog_id FROM supplier_products

            WHERE supplier_product_id = ? AND supplier_id = ? AND is_available = 1

            LIMIT 1

        ");

        $sp_check->bind_param("ii", $supplier_product_id, $supplier_id);

        $sp_check->execute();

        $sp_row = $sp_check->get_result()->fetch_assoc();

        $sp_check->close();



        if (!$sp_row) {

            $error = 'The selected supplier product is invalid or no longer available.';

        } else {

            // Trust catalog_id from supplier_products if none was passed

            if (!$catalog_id && $sp_row['catalog_id']) {

                $catalog_id = (int) $sp_row['catalog_id'];

            }

        }

    }



    // ── Compute expiry status ──────────────────────────────────────────────

    if (empty($error)) {

        $days_until_expiry = (strtotime($expiry_date) - time()) / 86400;

        if ($days_until_expiry < 0) {

            $expiry_status = 'expired';

        } elseif ($days_until_expiry <= 7) {

            $expiry_status = 'near_expiry';

        } else {

            $expiry_status = 'fresh';

        }

        $alert_flag = ($expiry_status !== 'fresh') ? 1 : 0;



        // ── Insert ─────────────────────────────────────────────────────────

        $insert = $conn->prepare("

            INSERT INTO grocery_items

                (item_name, barcode, catalog_id, category_id,

                 supplier_id, supplier_product_id,

                 batch_number, quantity, unit,

                 purchase_date, received_date, expiry_date,

                 expiry_status, alert_flag,

                 cost_price, selling_price,

                 reorder_level, reorder_quantity,

                 sku, created_by, store_id)

            VALUES

                (?, ?, ?, ?,

                 ?, ?,

                 ?, ?, ?,

                 ?, ?, ?,

                 ?, ?,

                 ?, ?,

                 ?, ?,

                 ?, ?, ?)

        ");



        // s  s  i  i  i  i  s  d  s  s  s  s  s  i  d  d  d  d  s  i  i

        $insert->bind_param(

            "ssiiiisdsssssiiddddii",

            $item_name, $barcode, $catalog_id, $category_id,

            $supplier_id, $supplier_product_id,

            $batch_number, $quantity, $unit,

            $purchase_date, $received_date, $expiry_date,

            $expiry_status, $alert_flag,

            $cost_price, $selling_price,

            $reorder_level, $reorder_qty,

            $sku, $user_id, $store_id

        );



        if ($insert->execute()) {

            $new_item_id = $conn->insert_id;



            // Log inventory update

            $log = $conn->prepare("

                INSERT INTO grocery_inventory_updates

                    (item_id, store_id, update_type, quantity_change, updated_by, notes)

                VALUES (?, ?, 'added', ?, ?, ?)

            ");

            $notes = $batch_number

                ? "Initial addition — Batch: $batch_number"

                : "Initial addition";

            $log->bind_param("iidis", $new_item_id, $store_id, $quantity, $user_id, $notes);

            $log->execute();

            $log->close();



            // Log barcode scan if applicable

            if (!empty($barcode)) {

                $scan_log = $conn->prepare("

                    INSERT INTO barcode_scan_history

                        (user_id, barcode, scan_type, product_found, item_id)

                    VALUES (?, ?, 'add_item', 1, ?)

                ");

                $scan_log->bind_param("isi", $user_id, $barcode, $new_item_id);

                $scan_log->execute();

                $scan_log->close();

            }



            $success = "Item added successfully to inventory!";



            // Reset only the per-item fields; keep page usable for next entry

            $item_name = $barcode = $sku = $batch_number = '';

            $catalog_id = $supplier_product_id = null;

            $quantity = 0;

        } else {

            $error = "Database error while saving. Please try again.";

        }

        $insert->close();

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



    <!-- Page Header -->

    <div class="page-header">

        <div class="header-content">

            <div class="header-info">

                <h1 class="page-title">Add New Item</h1>

                <p class="page-subtitle">Add products to your store inventory</p>

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



    <!-- ── Mode Toggle ─────────────────────────────────────────────────── -->

    <div class="mode-toggle-section">

        <p class="mode-toggle-label">How are you adding this item?</p>

        <div class="mode-toggle">

            <button type="button" class="mode-btn active" id="mode-btn-supplier" onclick="setMode('supplier')">

                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>

                </svg>

                From a Supplier

                <span class="mode-badge">Recommended</span>

            </button>

            <button type="button" class="mode-btn" id="mode-btn-manual" onclick="setMode('manual')">

                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>

                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>

                </svg>

                Manual Entry

            </button>

        </div>

        <p class="mode-hint" id="mode-hint-supplier">

            Select a supplier, then pick one of their products — details like price and unit fill in automatically.

        </p>

        <p class="mode-hint hidden" id="mode-hint-manual">

            Enter all product details yourself. You can link a supplier later.

        </p>

    </div>



    <form method="POST" action="" class="create-form" id="main-form">

        <input type="hidden" name="add_mode" id="add_mode" value="supplier">

        <input type="hidden" name="catalog_id" id="catalog_id" value="<?php echo $prefill_catalog; ?>">



        <!-- ══════════════════════════════════════════════════════════════ -->

        <!-- SUPPLIER PATH                                                 -->

        <!-- ══════════════════════════════════════════════════════════════ -->

        <div id="section-supplier" class="form-section">

            <div class="section-header">

                <h2 class="section-title">

                    <span class="step-num">1</span>

                    Select Supplier

                </h2>

                <p class="section-description">Choose which supplier you're ordering from</p>

            </div>



            <div class="form-grid">

                <div class="form-group full-width">

                    <label class="form-label" for="supplier_id">Supplier *</label>

                    <select id="supplier_id" name="supplier_id" class="form-select" onchange="loadSupplierProducts(this.value)">

                        <option value="">— Select a supplier —</option>

                        <?php while ($sup = $suppliers->fetch_assoc()): ?>

                            <option value="<?php echo $sup['supplier_id']; ?>"

                                    data-type="<?php echo htmlspecialchars($sup['supplier_type']); ?>">

                                <?php echo htmlspecialchars($sup['supplier_name']); ?>

                                <span>(<?php echo $sup['product_count']; ?> products)</span>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

            </div>



            <!-- Supplier info card (hidden until supplier chosen) -->

            <div id="supplier-card" class="supplier-card hidden">

                <div class="supplier-card-inner">

                    <div class="supplier-meta">

                        <span class="supplier-type-badge" id="sc-type"></span>

                        <span class="supplier-meta-item" id="sc-contact"></span>

                        <span class="supplier-meta-item" id="sc-payment"></span>

                        <span class="supplier-meta-item" id="sc-delivery"></span>

                    </div>

                </div>

            </div>

        </div>



        <!-- Step 2: Pick product from supplier -->

        <div id="section-supplier-product" class="form-section hidden">

            <div class="section-header">

                <h2 class="section-title">

                    <span class="step-num">2</span>

                    Select Product

                </h2>

                <p class="section-description">Pick the specific product from this supplier</p>

            </div>



            <div class="form-grid">

                <div class="form-group full-width">

                    <label class="form-label" for="supplier_product_id">Supplier Product *</label>

                    <select id="supplier_product_id" name="supplier_product_id" class="form-select" onchange="applySupplierProduct(this.value)">

                        <option value="">— Select a product —</option>

                    </select>

                    <small class="form-hint">Selecting a product auto-fills name, price, category, and unit below.</small>

                </div>

            </div>



            <!-- Product preview card -->

            <div id="product-preview" class="product-preview hidden">

                <div class="preview-row">

                    <span class="preview-label">Unit Price</span>

                    <span class="preview-value" id="pv-price">—</span>

                </div>

                <div class="preview-row">

                    <span class="preview-label">Unit Size</span>

                    <span class="preview-value" id="pv-size">—</span>

                </div>

                <div class="preview-row">

                    <span class="preview-label">Min. Order Qty</span>

                    <span class="preview-value" id="pv-moq">—</span>

                </div>

                <div class="preview-row">

                    <span class="preview-label">Lead Time</span>

                    <span class="preview-value" id="pv-lead">—</span>

                </div>

                <div class="preview-row" id="pv-notes-row">

                    <span class="preview-label">Notes</span>

                    <span class="preview-value" id="pv-notes">—</span>

                </div>

            </div>

        </div>



        <!-- ══════════════════════════════════════════════════════════════ -->

        <!-- PRODUCT DETAILS (shared by both paths)                        -->

        <!-- ══════════════════════════════════════════════════════════════ -->

        <div id="section-product" class="form-section">

            <div class="section-header">

                <h2 class="section-title">

                    <span class="step-num" id="product-step-num">3</span>

                    Product Details

                </h2>

                <p class="section-description">Review or enter product information</p>

            </div>



            <div class="form-grid">



                <!-- Barcode -->

                <div class="form-group full-width">

                    <label class="form-label" for="barcode">Barcode</label>

                    <div class="input-with-icon">

                        <span class="input-icon">

                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>

                            </svg>

                        </span>

                        <input type="text" id="barcode" name="barcode" class="form-input with-icon"

                               value="<?php echo $prefill_barcode; ?>"

                               placeholder="Scan or enter barcode (optional)">

                    </div>

                </div>



                <!-- Product Name -->

                <div class="form-group full-width">

                    <label class="form-label" for="item_name">Product Name *</label>

                    <input type="text" id="item_name" name="item_name" class="form-input"

                           value="<?php echo $prefill_name; ?>"

                           placeholder="e.g., Alaska Evaporated Milk 370ml"

                           required>

                </div>



                <!-- Category -->

                <div class="form-group">

                    <label class="form-label" for="category_id">Category *</label>

                    <select id="category_id" name="category_id" class="form-select" required>

                        <option value="">Select category</option>

                        <?php

                        $categories->data_seek(0);

                        while ($cat = $categories->fetch_assoc()):

                        ?>

                            <option value="<?php echo $cat['category_id']; ?>"

                                <?php echo $prefill_category == $cat['category_id'] ? 'selected' : ''; ?>>

                                <?php echo htmlspecialchars($cat['category_name']); ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>



                <!-- SKU -->

                <div class="form-group">

                    <label class="form-label" for="sku">SKU</label>

                    <input type="text" id="sku" name="sku" class="form-input" placeholder="e.g., SKU-001">

                    <small class="form-hint">Optional internal code</small>

                </div>



            </div>

        </div>



        <!-- ══════════════════════════════════════════════════════════════ -->

        <!-- STOCK & UNIT                                                   -->

        <!-- ══════════════════════════════════════════════════════════════ -->

        <div class="form-section">

            <div class="section-header">

                <h2 class="section-title">

                    <span class="step-num" id="stock-step-num">4</span>

                    Stock & Unit

                </h2>

                <p class="section-description">How much are you adding, and in what unit?</p>

            </div>



            <div class="form-grid">

                <div class="form-group">

                    <label class="form-label" for="quantity">Quantity *</label>

                    <input type="number" id="quantity" name="quantity" class="form-input"

                           step="0.01" min="0.01" placeholder="0" required

                           oninput="recalcProfit()">

                </div>

                <div class="form-group">

                    <label class="form-label" for="unit">Unit *</label>

                    <input type="text" id="unit" name="unit" class="form-input"

                           value="<?php echo $prefill_unit; ?>"

                           placeholder="pcs / kg / L / can…" required>

                </div>

                <div class="form-group">

                    <label class="form-label" for="reorder_level">Reorder Level *</label>

                    <input type="number" id="reorder_level" name="reorder_level" class="form-input"

                           step="0.01" min="0" value="10" required>

                    <small class="form-hint">Alert when stock reaches this</small>

                </div>

                <div class="form-group">

                    <label class="form-label" for="reorder_quantity">Reorder Quantity *</label>

                    <input type="number" id="reorder_quantity" name="reorder_quantity" class="form-input"

                           step="0.01" min="0" value="50" required>

                    <small class="form-hint">Suggested quantity to re-order</small>

                </div>

            </div>

        </div>



        <!-- ══════════════════════════════════════════════════════════════ -->

        <!-- PRICING                                                        -->

        <!-- ══════════════════════════════════════════════════════════════ -->

        <div class="form-section">

            <div class="section-header">

                <h2 class="section-title">

                    <span class="step-num" id="pricing-step-num">5</span>

                    Pricing

                </h2>

                <p class="section-description">Cost from supplier and selling price to customers</p>

            </div>



            <div class="form-grid">

                <div class="form-group">

                    <label class="form-label" for="cost_price">Cost Price *</label>

                    <div class="input-with-icon">

                        <span class="input-icon">₱</span>

                        <input type="number" id="cost_price" name="cost_price" class="form-input with-icon"

                               step="0.01" min="0" placeholder="0.00" required

                               oninput="recalcProfit()">

                    </div>

                    <small class="form-hint">What you paid the supplier per unit</small>

                </div>

                <div class="form-group">

                    <label class="form-label" for="selling_price">Selling Price *</label>

                    <div class="input-with-icon">

                        <span class="input-icon">₱</span>

                        <input type="number" id="selling_price" name="selling_price" class="form-input with-icon"

                               step="0.01" min="0" placeholder="0.00" required

                               oninput="recalcProfit()">

                    </div>

                    <small class="form-hint">Price you'll sell to customers</small>

                </div>



                <!-- Live profit display -->

                <div class="form-group full-width" id="profit-display" style="display:none;">

                    <div class="profit-bar" id="profit-bar">

                        <div class="profit-stat">

                            <span class="profit-label">Margin per unit</span>

                            <span class="profit-value" id="p-margin">₱0.00</span>

                        </div>

                        <div class="profit-stat">

                            <span class="profit-label">Markup %</span>

                            <span class="profit-value" id="p-pct">0%</span>

                        </div>

                        <div class="profit-stat" id="p-total-wrap">

                            <span class="profit-label">Total profit</span>

                            <span class="profit-value" id="p-total">₱0.00</span>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- ══════════════════════════════════════════════════════════════ -->

        <!-- DATES & TRACKING                                               -->

        <!-- ══════════════════════════════════════════════════════════════ -->

        <div class="form-section">

            <div class="section-header">

                <h2 class="section-title">

                    <span class="step-num" id="dates-step-num">6</span>

                    Dates & Tracking

                </h2>

                <p class="section-description">Purchase, receipt, and expiry dates plus batch info</p>

            </div>



            <div class="form-grid">

                <div class="form-group">

                    <label class="form-label" for="purchase_date">Purchase Date *</label>

                    <input type="date" id="purchase_date" name="purchase_date" class="form-input" required>

                </div>

                <div class="form-group">

                    <label class="form-label" for="received_date">Received Date</label>

                    <input type="date" id="received_date" name="received_date" class="form-input">

                    <small class="form-hint">When goods arrived at your store</small>

                </div>

                <div class="form-group">

                    <label class="form-label" for="expiry_date">Expiry Date *</label>

                    <input type="date" id="expiry_date" name="expiry_date" class="form-input" required>

                    <small class="form-hint">We'll alert you when this is near</small>

                </div>

                <div class="form-group">

                    <label class="form-label" for="batch_number">Batch / Lot Number</label>

                    <input type="text" id="batch_number" name="batch_number" class="form-input"

                           placeholder="e.g., BATCH-2026-001">

                    <small class="form-hint">For traceability and quality control</small>

                </div>

            </div>

        </div>



        <!-- ── Form Actions ──────────────────────────────────────────── -->

        <div class="form-actions">

            <button type="submit" class="btn-primary">

                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                    <polyline points="9 11 12 14 22 4"/>

                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>

                </svg>

                Add Item to Inventory

            </button>

            <a href="../../grocery/grocery_dashboard.php" class="btn-cancel">Cancel</a>

        </div>

    </form>



</div><!-- /.create-container -->

</main>



<script>

// ──────────────────────────────────────────────────────────────────────────

// State

// ──────────────────────────────────────────────────────────────────────────

let currentMode      = 'supplier';

let supplierProducts = [];  // cache from last AJAX call



// ──────────────────────────────────────────────────────────────────────────

// Init

// ──────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {

    // Default dates

    const today = new Date().toISOString().split('T')[0];

    document.getElementById('purchase_date').value = today;

    document.getElementById('received_date').value  = today;



    <?php if ($prefill_shelf_life > 0): ?>

    // Pre-calculate expiry from shelf life

    const exp = new Date();

    exp.setDate(exp.getDate() + <?php echo $prefill_shelf_life; ?>);

    document.getElementById('expiry_date').value = exp.toISOString().split('T')[0];

    <?php endif; ?>



    setMode('supplier');

});



// ──────────────────────────────────────────────────────────────────────────

// Mode toggle

// ──────────────────────────────────────────────────────────────────────────

function setMode(mode) {

    currentMode = mode;

    document.getElementById('add_mode').value = mode;



    document.getElementById('mode-btn-supplier').classList.toggle('active', mode === 'supplier');

    document.getElementById('mode-btn-manual').classList.toggle('active', mode === 'manual');



    document.getElementById('mode-hint-supplier').classList.toggle('hidden', mode !== 'supplier');

    document.getElementById('mode-hint-manual').classList.toggle('hidden', mode !== 'manual');



    const supplierSection = document.getElementById('section-supplier');

    const productSection  = document.getElementById('section-supplier-product');



    if (mode === 'supplier') {

        supplierSection.classList.remove('hidden');

        // product section shown only once a supplier is chosen

        updateStepNumbers(3);

    } else {

        supplierSection.classList.add('hidden');

        productSection.classList.add('hidden');

        // Clear supplier selections so they won't POST

        document.getElementById('supplier_id').value          = '';

        document.getElementById('supplier_product_id').value  = '';

        document.getElementById('supplier-card').classList.add('hidden');

        document.getElementById('product-preview').classList.add('hidden');

        updateStepNumbers(1);

    }

}



function updateStepNumbers(productStart) {

    document.getElementById('product-step-num').textContent  = productStart;

    document.getElementById('stock-step-num').textContent    = productStart + 1;

    document.getElementById('pricing-step-num').textContent  = productStart + 2;

    document.getElementById('dates-step-num').textContent    = productStart + 3;

}



// ──────────────────────────────────────────────────────────────────────────

// Load supplier products via AJAX

// ──────────────────────────────────────────────────────────────────────────

async function loadSupplierProducts(supplierId) {

    const card           = document.getElementById('supplier-card');

    const productSection = document.getElementById('section-supplier-product');

    const productSelect  = document.getElementById('supplier_product_id');

    const preview        = document.getElementById('product-preview');



    // Reset

    card.classList.add('hidden');

    productSection.classList.add('hidden');

    preview.classList.add('hidden');

    productSelect.innerHTML = '<option value="">— Select a product —</option>';

    supplierProducts = [];

    document.getElementById('catalog_id').value = '';



    if (!supplierId) return;



    try {

        const res  = await fetch(`get_supplier_products.php?supplier_id=${supplierId}`);

        const data = await res.json();



        if (data.error) throw new Error(data.error);



        // ── Supplier info card ─────────────────────────────────────────

        const s = data.supplier;

        document.getElementById('sc-type').textContent     = s.supplier_type.replace('_', ' ');

        document.getElementById('sc-contact').textContent  = s.contact_person

            ? `${s.contact_person}${s.contact_number ? ' · ' + s.contact_number : ''}`

            : (s.contact_number || '');

        document.getElementById('sc-payment').textContent  = s.payment_terms  ? `Payment: ${s.payment_terms}`  : '';

        document.getElementById('sc-delivery').textContent = s.delivery_schedule ? `Delivery: ${s.delivery_schedule}` : '';

        card.classList.remove('hidden');



        // ── Product dropdown ───────────────────────────────────────────

        supplierProducts = data.products;



        if (supplierProducts.length === 0) {

            productSelect.innerHTML = '<option value="">No products on file for this supplier</option>';

        } else {

            supplierProducts.forEach(p => {

                const opt   = document.createElement('option');

                opt.value   = p.supplier_product_id;

                opt.textContent = `${p.product_name}${p.brand ? ' — ' + p.brand : ''} · ₱${parseFloat(p.unit_price).toFixed(2)} / ${p.unit_size || p.catalog_unit || '—'}`;

                productSelect.appendChild(opt);

            });

        }



        productSection.classList.remove('hidden');



    } catch (err) {

        console.error('Failed to load supplier products:', err);

        alert('Could not load supplier products. Check console for details.');

    }

}



// ──────────────────────────────────────────────────────────────────────────

// Apply a supplier product's data to the form

// ──────────────────────────────────────────────────────────────────────────

function applySupplierProduct(supplierProductId) {

    const preview = document.getElementById('product-preview');



    if (!supplierProductId) {

        preview.classList.add('hidden');

        return;

    }



    const p = supplierProducts.find(x => x.supplier_product_id == supplierProductId);

    if (!p) return;



    // ── Auto-fill product fields ───────────────────────────────────────

    if (p.product_name)  document.getElementById('item_name').value    = p.product_name;

    if (p.unit_size || p.catalog_unit)

                         document.getElementById('unit').value          = p.unit_size || p.catalog_unit;

    if (p.unit_price)    document.getElementById('cost_price').value    = parseFloat(p.unit_price).toFixed(2);

    if (p.category_id) {

        const sel = document.getElementById('category_id');

        if ([...sel.options].some(o => o.value == p.category_id)) {

            sel.value = p.category_id;

        }

    }

    if (p.catalog_id)    document.getElementById('catalog_id').value   = p.catalog_id;

    if (p.catalog_barcode && !document.getElementById('barcode').value)

                         document.getElementById('barcode').value      = p.catalog_barcode;



    // Shelf life → expiry date

    if (p.typical_shelf_life_days) {

        const purchaseDateVal = document.getElementById('purchase_date').value;

        const base = purchaseDateVal ? new Date(purchaseDateVal) : new Date();

        base.setDate(base.getDate() + parseInt(p.typical_shelf_life_days));

        document.getElementById('expiry_date').value = base.toISOString().split('T')[0];

    }



    if (p.lead_time_days) {

        const receivedBase = new Date();

        receivedBase.setDate(receivedBase.getDate() + parseInt(p.lead_time_days));

        document.getElementById('received_date').value = receivedBase.toISOString().split('T')[0];

    }



    // ── Preview card ───────────────────────────────────────────────────

    document.getElementById('pv-price').textContent = `₱${parseFloat(p.unit_price).toFixed(2)}`;

    document.getElementById('pv-size').textContent  = p.unit_size || '—';

    document.getElementById('pv-moq').textContent   = p.minimum_order_quantity

        ? `${p.minimum_order_quantity} units`

        : '—';

    document.getElementById('pv-lead').textContent  = p.lead_time_days

        ? `${p.lead_time_days} days`

        : '—';



    const notesRow = document.getElementById('pv-notes-row');

    if (p.notes) {

        document.getElementById('pv-notes').textContent = p.notes;

        notesRow.style.display = 'flex';

    } else {

        notesRow.style.display = 'none';

    }



    preview.classList.remove('hidden');



    // Min order warning

    if (p.minimum_order_quantity && parseInt(p.minimum_order_quantity) > 1) {

        const qty = parseFloat(document.getElementById('quantity').value) || 0;

        if (qty > 0 && qty < parseInt(p.minimum_order_quantity)) {

            alert(`⚠ This supplier requires a minimum order of ${p.minimum_order_quantity} units.`);

        }

    }



    recalcProfit();

}



// ──────────────────────────────────────────────────────────────────────────

// Live profit / margin calculation

// ──────────────────────────────────────────────────────────────────────────

function recalcProfit() {

    const cost   = parseFloat(document.getElementById('cost_price').value)    || 0;

    const sell   = parseFloat(document.getElementById('selling_price').value) || 0;

    const qty    = parseFloat(document.getElementById('quantity').value)      || 0;

    const disp   = document.getElementById('profit-display');

    const bar    = document.getElementById('profit-bar');



    if (cost <= 0 || sell <= 0) { disp.style.display = 'none'; return; }



    const margin  = sell - cost;

    const pct     = ((margin / cost) * 100).toFixed(1);

    const total   = margin * qty;



    document.getElementById('p-margin').textContent = `₱${margin.toFixed(2)}`;

    document.getElementById('p-pct').textContent    = `${pct}%`;



    const totalWrap = document.getElementById('p-total-wrap');

    if (qty > 0) {

        document.getElementById('p-total').textContent = `₱${total.toFixed(2)}`;

        totalWrap.style.display = '';

    } else {

        totalWrap.style.display = 'none';

    }



    // Colour coding

    bar.className = 'profit-bar';

    if (margin < 0)       bar.classList.add('profit-loss');

    else if (pct < 10)    bar.classList.add('profit-low');

    else                  bar.classList.add('profit-ok');



    disp.style.display = 'block';

}

</script>



<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php $conn->close(); ?>