<?php
/**
 * Restock Item Handler
 * Handles AJAX requests to restock inventory items
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';

header('Content-Type: application/json');

$conn = getDBConnection();
$store_id = $_SESSION['store_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$item_id = (int) ($_POST['item_id'] ?? 0);
$restock_quantity = (float) ($_POST['restock_quantity'] ?? 0);
$restock_cost = (float) ($_POST['restock_cost'] ?? 0);

if (!$item_id || $restock_quantity <= 0 || $restock_cost < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Verify item belongs to user's store
$verify = $conn->prepare("SELECT item_id, quantity, cost_price FROM grocery_items WHERE item_id = ? AND store_id = ?");
$verify->bind_param('ii', $item_id, $store_id);
$verify->execute();
$item = $verify->get_result()->fetch_assoc();
$verify->close();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found or access denied']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Update item quantity and cost price
    $new_quantity = $item['quantity'] + $restock_quantity;
    $update = $conn->prepare("UPDATE grocery_items SET quantity = ?, cost_price = ?, updated_at = NOW() WHERE item_id = ?");
    $update->bind_param('ddi', $new_quantity, $restock_cost, $item_id);
    $update->execute();
    $update->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Item restocked successfully',
        'new_quantity' => $new_quantity
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Restock error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

$conn->close();
?>
