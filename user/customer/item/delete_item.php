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

$error = '';

// ── Handle confirmed deletion ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {

    // Double-check ownership before deleting
    $check = $conn->prepare("
        SELECT ci.item_id FROM customer_items ci
        INNER JOIN group_members gm ON ci.group_id = gm.group_id
        WHERE ci.item_id = ? AND gm.user_id = ?
        LIMIT 1
    ");
    $check->bind_param("ii", $item_id, $user_id);
    $check->execute();
    $valid = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$valid) {
        $error = 'You do not have permission to delete this item.';
    } else {
        // Log deletion before removing (FK cascade will wipe inventory_updates)
        $log = $conn->prepare("
            INSERT INTO customer_inventory_updates
                (item_id, update_type, quantity_change, updated_by, notes)
            VALUES (?, 'deleted', ?, ?, ?)
        ");
        $note = 'Item deleted: ' . $item['item_name'];
        $log->bind_param("idis", $item_id, $item['quantity'], $user_id, $note);
        $log->execute();
        $log->close();

        $del = $conn->prepare("DELETE FROM customer_items WHERE item_id = ?");
        $del->bind_param("i", $item_id);

        if ($del->execute()) {
            $del->close();
            header('Location: my_items.php?deleted=1');
            exit;
        } else {
            $error = 'Failed to delete item. Please try again.';
            $del->close();
        }
    }
}

// Helpers
$expiry_date = new DateTime($item['expiry_date']);
$today       = new DateTime();
$days_diff   = (int) $today->diff($expiry_date)->days;
$is_expired  = $today > $expiry_date;

$status_labels = [
    'fresh'       => 'Fresh',
    'near_expiry' => 'Near Expiry',
    'expired'     => 'Expired',
];

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
                    <h1>Delete Item</h1>
                    <p>This action is permanent and cannot be undone</p>
                </div>
                <a href="my_items.php" class="btn-back">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Back to Inventory
                </a>
            </div>
        </div>

        <!-- Error -->
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

        <!-- Delete Confirmation Card -->
        <div class="delete-card">

            <!-- Warning Banner -->
            <div class="delete-warning">
                <div class="delete-warning-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="delete-warning-text">
                    <h3>Are you sure you want to delete this item?</h3>
                    <p>
                        Deleting this item will permanently remove it from your inventory
                        along with all associated update history. This cannot be reversed.
                    </p>
                </div>
            </div>

            <!-- Item Summary -->
            <div class="item-summary">
                <h3 class="item-summary-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>

                <div class="summary-row">
                    <span class="summary-label">Category</span>
                    <span class="summary-value"><?php echo htmlspecialchars($item['category_name']); ?></span>
                </div>

                <div class="summary-row">
                    <span class="summary-label">Group</span>
                    <span class="summary-value"><?php echo htmlspecialchars($item['group_name']); ?></span>
                </div>

                <div class="summary-row">
                    <span class="summary-label">Quantity</span>
                    <span class="summary-value">
                        <?php echo number_format($item['quantity'], 2) . ' ' . htmlspecialchars($item['unit']); ?>
                    </span>
                </div>

                <div class="summary-row">
                    <span class="summary-label">Purchase Date</span>
                    <span class="summary-value">
                        <?php echo date('M d, Y', strtotime($item['purchase_date'])); ?>
                    </span>
                </div>

                <div class="summary-row">
                    <span class="summary-label">Expiry Date</span>
                    <span class="summary-value">
                        <?php echo date('M d, Y', strtotime($item['expiry_date'])); ?>
                        <?php if (!$is_expired): ?>
                            <small style="color: rgba(0,0,0,0.4); font-size: 11px;">(<?php echo $days_diff; ?> days left)</small>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if (!empty($item['purchased_from'])): ?>
                <div class="summary-row">
                    <span class="summary-label">Purchased From</span>
                    <span class="summary-value"><?php echo htmlspecialchars($item['purchased_from']); ?></span>
                </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span class="summary-label">Status</span>
                    <span class="summary-badge badge-<?php echo $item['expiry_status']; ?>">
                        <?php echo $status_labels[$item['expiry_status']] ?? ucfirst($item['expiry_status']); ?>
                    </span>
                </div>

                <?php if (!empty($item['barcode'])): ?>
                <div class="summary-row">
                    <span class="summary-label">Barcode</span>
                    <span class="summary-value" style="font-family: monospace; letter-spacing: 1px;">
                        <?php echo htmlspecialchars($item['barcode']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Confirm / Cancel -->
            <div class="delete-actions">
                <form method="POST" action="" style="display: contents;">
                    <input type="hidden" name="confirm_delete" value="1">
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Final confirmation: permanently delete \'<?php echo addslashes($item['item_name']); ?>\'?')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                        Yes, Delete Permanently
                    </button>
                </form>

                <a href="my_items.php" class="btn btn-cancel">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    Cancel
                </a>

                <a href="edit_item.php?id=<?php echo $item_id; ?>" class="btn btn-cancel">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit Instead
                </a>
            </div>

        </div><!-- /.delete-card -->

    </div>
</main>

<?php require_once(__DIR__ . '/../../../includes/footer.php'); ?>
<?php ob_end_flush(); ?>
