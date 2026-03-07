<?php
ob_start();
session_start();
require_once(__DIR__ . '/../../../includes/config.php');
require_once(__DIR__ . '/../../../includes/customer_auth_check.php');

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $baseUrl . "/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$item_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$item_id) {
    header('Location: my_items.php');
    exit;
}

// Fetch item — ensure user belongs to the group that owns it
$stmt = $conn->prepare("
    SELECT ci.*, c.category_name, g.group_name
    FROM customer_items ci
    INNER JOIN categories c ON ci.category_id = c.category_id
    INNER JOIN groups g ON ci.group_id = g.group_id
    INNER JOIN group_members gm ON ci.group_id = gm.group_id
    WHERE ci.item_id = ? AND gm.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: my_items.php');
    exit;
}

$item = $result->fetch_assoc();
$stmt->close();

$error   = '';
$success = '';

// ── Handle form submission ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name      = trim($_POST['item_name']      ?? '');
    $category_id    = (int)   ($_POST['category_id']   ?? 0);
    $group_id       = (int)   ($_POST['group_id']      ?? 0);
    $quantity       = (float) ($_POST['quantity']      ?? 0);
    $unit           = trim($_POST['unit']           ?? '');
    $purchase_date  = trim($_POST['purchase_date']  ?? '');
    $expiry_date    = trim($_POST['expiry_date']    ?? '');
    $purchased_from = trim($_POST['purchased_from'] ?? '');

    // Validation
    if (empty($item_name)) {
        $error = 'Item name is required.';
    } elseif ($category_id <= 0) {
        $error = 'Please select a valid category.';
    } elseif ($group_id <= 0) {
        $error = 'Please select a valid group.';
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
    } else {
        // Verify user is member of the target group
        $group_check = $conn->prepare("
            SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1
        ");
        $group_check->bind_param("ii", $group_id, $user_id);
        $group_check->execute();
        if ($group_check->get_result()->num_rows === 0) {
            $error = 'You do not have access to the selected group.';
        }
        $group_check->close();
    }

    if (empty($error)) {
        // s = item_name, i = category_id, i = group_id, d = quantity,
        // s = unit, s = purchase_date, s = expiry_date, s = purchased_from, i = item_id
        $update = $conn->prepare("
            UPDATE customer_items
            SET item_name      = ?,
                category_id    = ?,
                group_id       = ?,
                quantity       = ?,
                unit           = ?,
                purchase_date  = ?,
                expiry_date    = ?,
                purchased_from = ?
            WHERE item_id = ?
        ");
        $update->bind_param("siidsssi",
            $item_name,
            $category_id,
            $group_id,
            $quantity,
            $unit,
            $purchase_date,
            $expiry_date,
            $purchased_from,
            $item_id
        );

        if ($update->execute()) {
            // Log the update
            $log = $conn->prepare("
                INSERT INTO customer_inventory_updates
                    (item_id, update_type, quantity_change, updated_by, notes)
                VALUES (?, 'edited', ?, ?, 'Item details updated')
            ");
            $log->bind_param("idi", $item_id, $quantity, $user_id);
            $log->execute();
            $log->close();

            $success = 'Item updated successfully!';

            // Refresh item data for display
            $refresh = $conn->prepare("
                SELECT ci.*, c.category_name, g.group_name
                FROM customer_items ci
                INNER JOIN categories c ON ci.category_id = c.category_id
                INNER JOIN groups g ON ci.group_id = g.group_id
                WHERE ci.item_id = ?
            ");
            $refresh->bind_param("i", $item_id);
            $refresh->execute();
            $item = $refresh->get_result()->fetch_assoc();
            $refresh->close();
        } else {
            $error = 'Failed to update item. Please try again.';
        }
        $update->close();
    }
}

// Fetch categories and user's groups for selects
$categories  = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$groups_stmt = $conn->prepare("
    SELECT g.group_id, g.group_name
    FROM groups g
    INNER JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY g.group_name
");
$groups_stmt->bind_param("i", $user_id);
$groups_stmt->execute();
$user_groups = $groups_stmt->get_result();
$groups_stmt->close();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/item_forms.css">';
require_once(__DIR__ . '/../../../includes/header.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="item-form-page">
    <div class="item-form-container">

        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1>Edit Item</h1>
                    <p>Update the details for this inventory item</p>
                </div>
                <a href="my_items.php" class="btn-back">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Back to Inventory
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
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

        <!-- Form -->
        <div class="form-card">
            <p class="form-card-label">Item Information</p>

            <form method="POST" action="">
                <div class="form-grid">

                    <!-- Item Name -->
                    <div class="form-group full-width">
                        <label class="form-label" for="item_name">Item Name *</label>
                        <input type="text"
                               id="item_name"
                               name="item_name"
                               class="form-input"
                               value="<?php echo htmlspecialchars($item['item_name']); ?>"
                               placeholder="e.g., Whole Milk"
                               required>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label class="form-label" for="category_id">Category *</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            <option value="">Select category…</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $cat['category_id']; ?>"
                                    <?php echo (int)$item['category_id'] === (int)$cat['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Group -->
                    <div class="form-group">
                        <label class="form-label" for="group_id">Group *</label>
                        <select id="group_id" name="group_id" class="form-select" required>
                            <option value="">Select group…</option>
                            <?php while ($grp = $user_groups->fetch_assoc()): ?>
                                <option value="<?php echo $grp['group_id']; ?>"
                                    <?php echo (int)$item['group_id'] === (int)$grp['group_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grp['group_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="form-group">
                        <label class="form-label" for="quantity">Quantity *</label>
                        <input type="number"
                               id="quantity"
                               name="quantity"
                               class="form-input"
                               step="0.01"
                               min="0.01"
                               value="<?php echo htmlspecialchars($item['quantity']); ?>"
                               required>
                    </div>

                    <!-- Unit -->
                    <div class="form-group">
                        <label class="form-label" for="unit">Unit *</label>
                        <select id="unit" name="unit" class="form-select" required>
                            <?php foreach (['pcs','kg','g','L','mL','box','pack','can','bottle','dozen','lb','oz'] as $u): ?>
                                <option value="<?php echo $u; ?>" <?php echo $item['unit'] === $u ? 'selected' : ''; ?>>
                                    <?php echo $u; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Purchase Date -->
                    <div class="form-group">
                        <label class="form-label" for="purchase_date">Purchase Date *</label>
                        <input type="date"
                               id="purchase_date"
                               name="purchase_date"
                               class="form-input"
                               value="<?php echo htmlspecialchars($item['purchase_date']); ?>"
                               required>
                    </div>

                    <!-- Expiry Date -->
                    <div class="form-group">
                        <label class="form-label" for="expiry_date">Expiry Date *</label>
                        <input type="date"
                               id="expiry_date"
                               name="expiry_date"
                               class="form-input"
                               value="<?php echo htmlspecialchars($item['expiry_date']); ?>"
                               required>
                    </div>

                    <!-- Purchased From -->
                    <div class="form-group full-width">
                        <label class="form-label" for="purchased_from">Purchased From</label>
                        <input type="text"
                               id="purchased_from"
                               name="purchased_from"
                               class="form-input"
                               value="<?php echo htmlspecialchars($item['purchased_from'] ?? ''); ?>"
                               placeholder="e.g., SM Supermarket">
                    </div>

                    <!-- Barcode (read-only) -->
                    <?php if (!empty($item['barcode'])): ?>
                    <div class="form-group">
                        <label class="form-label">Barcode</label>
                        <input type="text"
                               class="form-input"
                               value="<?php echo htmlspecialchars($item['barcode']); ?>"
                               readonly>
                        <small class="form-hint">Barcode cannot be changed after creation.</small>
                    </div>
                    <?php endif; ?>

                    <!-- Current Expiry Status (display only) -->
                    <div class="form-group <?php echo empty($item['barcode']) ? 'full-width' : ''; ?>">
                        <label class="form-label">Current Status</label>
                        <?php
                        $status_labels = [
                            'fresh'       => '● Fresh',
                            'near_expiry' => '▲ Near Expiry',
                            'expired'     => '✕ Expired',
                        ];
                        ?>
                        <div class="status-display status-<?php echo $item['expiry_status']; ?>">
                            <?php echo $status_labels[$item['expiry_status']] ?? ucfirst($item['expiry_status']); ?>
                        </div>
                        <small class="form-hint">Updates automatically when expiry date is saved.</small>
                    </div>

                </div><!-- /.form-grid -->

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save Changes
                    </button>
                    <a href="my_items.php" class="btn btn-cancel">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                        Cancel
                    </a>
                </div>

            </form>
        </div>

    </div>
</main>

<?php require_once(__DIR__ . '/../../../includes/footer.php'); ?>
<?php ob_end_flush(); ?>