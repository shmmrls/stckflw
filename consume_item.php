<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/badge_system.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();
$item_id = $_GET['id'] ?? null;

if (!$item_id) {
    header('Location: dashboard.php');
    exit();
}

$stmt = $conn->prepare("
    SELECT ci.*, c.category_name, g.group_name
    FROM customer_items ci
    INNER JOIN categories c ON ci.category_id = c.category_id
    INNER JOIN groups g ON ci.group_id = g.group_id
    INNER JOIN group_members gm ON ci.group_id = gm.group_id
    WHERE ci.item_id = ? AND gm.user_id = ?
");
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit();
}

$item = $result->fetch_assoc();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consume_quantity = $_POST['consume_quantity'];
    $notes = trim($_POST['notes']);
    
    if ($consume_quantity <= 0) {
        $error = "Quantity must be greater than 0";
    } elseif ($consume_quantity > $item['quantity']) {
        $error = "Cannot consume more than available quantity";
    } else {
        $new_quantity = $item['quantity'] - $consume_quantity;
        
        $update_stmt = $conn->prepare("UPDATE customer_items SET quantity = ? WHERE item_id = ?");
        $update_stmt->bind_param("di", $new_quantity, $item_id);
        $update_stmt->execute();
        
        $log_stmt = $conn->prepare("
            INSERT INTO customer_inventory_updates 
            (item_id, update_type, quantity_change, updated_by, notes) 
            VALUES (?, 'consumed', ?, ?, ?)
        ");
        $log_stmt->bind_param("idis", $item_id, $consume_quantity, $user_id, $notes);
        $log_stmt->execute();
        
        $points_stmt = $conn->prepare("
            INSERT INTO user_points (user_id, total_points) 
            VALUES (?, 3) 
            ON DUPLICATE KEY UPDATE total_points = total_points + 3
        ");
        $points_stmt->bind_param("i", $user_id);
        $points_stmt->execute();
        
        $points_log_stmt = $conn->prepare("
            INSERT INTO points_log (user_id, action_type, points_earned, item_id) 
            VALUES (?, 'CONSUME_ITEM', 3, ?)
        ");
        $points_log_stmt->bind_param("ii", $user_id, $item_id);
        $points_log_stmt->execute();
        
        if ($new_quantity <= 0) {
            $delete_stmt = $conn->prepare("DELETE FROM customer_items WHERE item_id = ?");
            $delete_stmt->bind_param("i", $item_id);
            $delete_stmt->execute();
        }
        
        // Check and award badges
        $newly_unlocked = checkAndAwardBadges($conn, $user_id);
        
        $success = "Item consumed successfully! You earned 3 points!";
        if (!empty($newly_unlocked)) {
            $success .= "\n🎉 Badge unlocked: " . implode(", ", $newly_unlocked);
        }
        
        if ($new_quantity > 0) {
            $item['quantity'] = $new_quantity;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consume Item - StockFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    
    <div class="container">
        <div class="section" style="max-width: 600px; margin: 0 auto;">
            <h2>Consume Item</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 8px;">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 8px;">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <a href="dashboard.php" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            <?php else: ?>
            
            <div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                <p>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    <strong>Category:</strong> <?php echo htmlspecialchars($item['category_name']); ?>
                </p>
                <p>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <strong>Group:</strong> <?php echo htmlspecialchars($item['group_name']); ?>
                </p>
                <p>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                    <strong>Available Quantity:</strong> <?php echo $item['quantity'] . ' ' . htmlspecialchars($item['unit']); ?>
                </p>
                <p>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <strong>Expiry Date:</strong> <?php echo date('M d, Y', strtotime($item['expiry_date'])); ?>
                </p>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="consume_quantity">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        Quantity to Consume *
                    </label>
                    <input type="number" 
                           id="consume_quantity" 
                           name="consume_quantity" 
                           step="0.01" 
                           min="0.01" 
                           max="<?php echo $item['quantity']; ?>" 
                           required>
                    <small style="color: #666;">Max: <?php echo $item['quantity'] . ' ' . htmlspecialchars($item['unit']); ?></small>
                </div>
                
                <div class="form-group">
                    <label for="notes">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Notes (Optional)
                    </label>
                    <textarea id="notes" name="notes" rows="3" placeholder="e.g., Used for dinner"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    Consume Item (+3 Points)
                </button>
                <a href="dashboard.php" class="btn" style="background: #e2e8f0; color: #333; margin-top: 10px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 6px;">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    Cancel
                </a>
            </form>
            
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php $conn->close(); ?>