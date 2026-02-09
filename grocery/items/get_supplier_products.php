<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

// Verify user is grocery admin
if ($_SESSION['role'] !== 'grocery_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getDBConnection();
$supplier_id = $_GET['supplier_id'] ?? 0;

if (!$supplier_id) {
    echo json_encode(['error' => 'No supplier ID provided']);
    exit();
}

// Get supplier details
$supplier_stmt = $conn->prepare("
    SELECT supplier_id, supplier_name, supplier_type, payment_terms, delivery_schedule, minimum_order_amount
    FROM suppliers 
    WHERE supplier_id = ? AND is_active = 1
");
$supplier_stmt->bind_param("i", $supplier_id);
$supplier_stmt->execute();
$supplier = $supplier_stmt->get_result()->fetch_assoc();

// Get supplier products
$products_stmt = $conn->prepare("
    SELECT 
        sp.supplier_product_id,
        sp.product_name,
        sp.brand,
        sp.unit_price,
        sp.unit_size,
        sp.minimum_order_quantity,
        sp.lead_time_days,
        c.category_name
    FROM supplier_products sp
    LEFT JOIN categories c ON sp.category_id = c.category_id
    WHERE sp.supplier_id = ? AND sp.is_available = 1
    ORDER BY sp.product_name ASC
");
$products_stmt->bind_param("i", $supplier_id);
$products_stmt->execute();
$result = $products_stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'supplier' => $supplier,
    'products' => $products
]);

$conn->close();
?>