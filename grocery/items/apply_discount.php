    <?php
/**
 * Apply Discount Handler
 * Handles AJAX requests to apply discounts to inventory items
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
$discount_percentage = (float) ($_POST['discount_percentage'] ?? 0);
$discount_reason = trim($_POST['discount_reason'] ?? '');

if (!$item_id || $discount_percentage <= 0 || $discount_percentage > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Verify item belongs to user's store
$verify = $conn->prepare("SELECT item_id, selling_price FROM grocery_items WHERE item_id = ? AND store_id = ?");
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
    
    // Calculate new selling price
    $original_price = $item['selling_price'];
    $discount_amount = $original_price * ($discount_percentage / 100);
    $new_selling_price = $original_price - $discount_amount;
    
    // Update item selling price
    $update = $conn->prepare("UPDATE grocery_items SET selling_price = ?, updated_at = NOW() WHERE item_id = ?");
    $update->bind_param('di', $new_selling_price, $item_id);
    $update->execute();
    $update->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Discount applied successfully',
        'original_price' => $original_price,
        'new_price' => $new_selling_price,
        'discount_percentage' => $discount_percentage
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Discount error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

$conn->close();
?>
