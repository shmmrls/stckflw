<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/customer_auth_check.php';
require_once __DIR__ . '/../../../includes/badge_system.php';
requireLogin();

$conn    = getDBConnection();
$user_id = getCurrentUserId();
$item_id = $_GET['id'] ?? null;

if (!$item_id) { header('Location: ../../dashboard.php'); exit(); }

$stmt = $conn->prepare("
    SELECT ci.*, c.category_name, g.group_name
    FROM customer_items ci
    INNER JOIN categories c      ON ci.category_id = c.category_id
    INNER JOIN groups g          ON ci.group_id    = g.group_id
    INNER JOIN group_members gm  ON ci.group_id    = gm.group_id
    WHERE ci.item_id = ? AND gm.user_id = ?
");
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) { header('Location: ../../dashboard.php'); exit(); }

$item    = $result->fetch_assoc();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spoil_quantity = $_POST['spoil_quantity'];
    $notes           = trim($_POST['notes']);

    if ($spoil_quantity <= 0) {
        $error = "Quantity must be greater than 0";
    } elseif ($spoil_quantity > $item['quantity']) {
        $error = "Cannot spoil more than available quantity";
    } else {
        $new_quantity = $item['quantity'] - $spoil_quantity;

        $update_stmt = $conn->prepare("UPDATE customer_items SET quantity = ? WHERE item_id = ?");
        $update_stmt->bind_param("di", $new_quantity, $item_id);
        $update_stmt->execute();

        $log_stmt = $conn->prepare("
            INSERT INTO customer_inventory_updates
            (item_id, update_type, quantity_change, updated_by, notes)
            VALUES (?, 'spoiled', ?, ?, ?)
        ");
        $log_stmt->bind_param("idis", $item_id, $spoil_quantity, $user_id, $notes);
        $log_stmt->execute();

        // Give 1 point for spoiled item (less than consume)
        $points_stmt = $conn->prepare("
            INSERT INTO user_points (user_id, total_points)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE total_points = total_points + 1
        ");
        $points_stmt->bind_param("i", $user_id);
        $points_stmt->execute();

        $points_log_stmt = $conn->prepare("
            INSERT INTO points_log (user_id, action_type, points_earned, item_id)
            VALUES (?, 'SPOIL_ITEM', 1, ?)
        ");
        $points_log_stmt->bind_param("ii", $user_id, $item_id);
        $points_log_stmt->execute();

        if ($new_quantity <= 0) {
            $delete_stmt = $conn->prepare("DELETE FROM customer_items WHERE item_id = ?");
            $delete_stmt->bind_param("i", $item_id);
            $delete_stmt->execute();
        }

        $newly_unlocked = checkAndAwardBadges($conn, $user_id);

        $success = "Item marked as spoiled! You earned 1 point for tracking waste.";
        if (!empty($newly_unlocked)) {
            $success .= " 🎉 Badge unlocked: " . implode(", ", $newly_unlocked);
        }

        if ($new_quantity > 0) {
            $item['quantity'] = $new_quantity;
        }
    }
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/consume.css">';
require_once __DIR__ . '/../../../includes/header.php';
?>

<main class="consume-page">
    <div class="consume-container">

        <!-- Page Header -->
        <div class="consume-header">
            <h2>Mark Item as Spoiled</h2>
            <p>Track waste and earn points for monitoring your inventory</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <!-- Success State -->
            <div class="success-card">
                <div class="alert alert-success" style="margin-bottom:0; border:none; padding:0; background:transparent;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <?= htmlspecialchars($success) ?>
                </div>
            </div>
            <a href="../../dashboard.php?t=<?php echo time(); ?>" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Dashboard
            </a>

        <?php else: ?>

            <!-- Item Info Card -->
            <div class="item-card">
                <h3 class="item-card-title"><?= htmlspecialchars($item['item_name']) ?></h3>

                <div class="item-detail-row">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="item-detail-label">Category</span>
                    <span class="item-detail-value"><?= htmlspecialchars($item['category_name']) ?></span>
                </div>

                <div class="item-detail-row">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span class="item-detail-label">Group</span>
                    <span class="item-detail-value"><?= htmlspecialchars($item['group_name']) ?></span>
                </div>

                <div class="item-detail-row">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                    <span class="item-detail-label">Available</span>
                    <span class="item-detail-value"><?= $item['quantity'] . ' ' . htmlspecialchars($item['unit']) ?></span>
                </div>

                <div class="item-detail-row">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8"  y1="2" x2="8"  y2="6"/>
                        <line x1="3"  y1="10" x2="21" y2="10"/>
                    </svg>
                    <span class="item-detail-label">Expiry</span>
                    <span class="item-detail-value"><?= date('M d, Y', strtotime($item['expiry_date'])) ?></span>
                </div>
            </div>

            <!-- Form Card -->
            <div class="form-card">
                <p class="form-card-title">Spoilage Details</p>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="spoil_quantity">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            Quantity to Spoil <span style="color:#b91c1c">*</span>
                        </label>
                        <input class="form-input"
                               type="number"
                               id="spoil_quantity"
                               name="spoil_quantity"
                               step="0.01"
                               min="0.01"
                               max="<?= $item['quantity'] ?>"
                               required>
                        <span class="form-hint">Max: <?= $item['quantity'] . ' ' . htmlspecialchars($item['unit']) ?></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="notes">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Reason <span style="color:rgba(0,0,0,.35)">(Optional)</span>
                        </label>
                        <textarea class="form-textarea"
                                  id="notes"
                                  name="notes"
                                  rows="3"
                                  placeholder="e.g., Found mold, bad smell, expired early"></textarea>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-warning">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            Mark as Spoiled (+1 Point)
                        </button>
                        <a href="../../dashboard.php" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6"  x2="6"  y2="18"/>
                                <line x1="6"  y1="6"  x2="18" y2="18"/>
                            </svg>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php $conn->close(); ?>
