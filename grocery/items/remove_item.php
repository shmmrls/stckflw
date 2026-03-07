<?php
/**
 * Remove Item Handler
 * Handles requests to remove items from inventory
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';

$conn = getDBConnection();
$store_id = $_SESSION['store_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

$item_id = (int) ($_GET['id'] ?? 0);

if (!$item_id) {
    header('Location: ../grocery_dashboard.php?error=invalid_item');
    exit;
}

// Verify item belongs to user's store
$verify = $conn->prepare("SELECT item_id, item_name, quantity, selling_price FROM grocery_items WHERE item_id = ? AND store_id = ?");
$verify->bind_param('ii', $item_id, $store_id);
$verify->execute();
$item = $verify->get_result()->fetch_assoc();
$verify->close();

if (!$item) {
    header('Location: ../grocery_dashboard.php?error=item_not_found');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm_removal = $_POST['confirm_removal'] ?? '';
    $removal_reason = trim($_POST['removal_reason'] ?? '');
    
    if ($confirm_removal === 'YES') {
        try {
            $conn->begin_transaction();
            
            // Delete the item
            $delete = $conn->prepare("DELETE FROM grocery_items WHERE item_id = ? AND store_id = ?");
            $delete->bind_param('ii', $item_id, $store_id);
            $delete->execute();
            $delete->close();
            
            $conn->commit();
            
            header('Location: ../grocery_dashboard.php?success=item_removed');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Remove item error: " . $e->getMessage());
            header('Location: ../grocery_dashboard.php?error=remove_failed');
            exit;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remove Item - StockFlow</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/includes/style/pages/dashboard.css">
    <style>
        .remove-container {
            max-width: 500px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .remove-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .remove-header h2 {
            color: #dc2626;
            margin-bottom: 10px;
        }
        .item-info {
            background: #fef2f2;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .warning-text {
            color: #dc2626;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="remove-container">
        <div class="remove-header">
            <h2>⚠️ Remove Item from Inventory</h2>
            <p>This action cannot be undone</p>
        </div>
        
        <div class="item-info">
            <h3><?= htmlspecialchars($item['item_name']) ?></h3>
            <p><strong>Current Quantity:</strong> <?= number_format($item['quantity'], 2) ?> units</p>
            <p><strong>Selling Price:</strong> ₱<?= number_format($item['selling_price'], 2) ?></p>
        </div>
        
        <p class="warning-text">Are you sure you want to permanently remove this item from your inventory?</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="removal_reason">Reason for removal (optional):</label>
                <textarea id="removal_reason" name="removal_reason" rows="3" placeholder="e.g., Expired, damaged, discontinued..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Type "YES" to confirm removal:</label>
                <input type="text" name="confirm_removal" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 4px;">
            </div>
            
            <div class="form-actions">
                <a href="../grocery_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-danger">Remove Item Permanently</button>
            </div>
        </form>
    </div>
</body>
</html>
