<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_auth_check.php';
require_once __DIR__ . '/../../includes/store_supplier_validation.php';

header('Content-Type: application/json');

$conn        = getDBConnection();
$user_id     = getCurrentUserId();
$supplier_id = isset($_GET['supplier_id']) ? (int) $_GET['supplier_id'] : 0;

if (!$supplier_id) {
    echo json_encode(['error' => 'No supplier ID provided']);
    exit();
}

// Get store ID
$store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
$store_stmt->bind_param("i", $user_id);
$store_stmt->execute();
$store_id = $store_stmt->get_result()->fetch_assoc()['store_id'] ?? null;
$store_stmt->close();

if (!$store_id) {
    echo json_encode(['error' => 'Store not found']);
    exit;
}

// Validate that this store is registered with the supplier
if (!isStoreRegisteredWithSupplier($store_id, $supplier_id)) {
    echo json_encode(['error' => 'Your store is not registered with this supplier']);
    exit;
}

// Get supplier details from valid suppliers list
$valid_suppliers = getValidSuppliersForStore($store_id);
$supplier = null;
foreach ($valid_suppliers as $sup) {
    if ($sup['supplier_id'] == $supplier_id) {
        $supplier = $sup;
        break;
    }
}

if (!$supplier) {
    echo json_encode(['error' => 'Supplier not found or not active']);
    exit();
}

// Supplier products — added category_id, catalog_id, supplier_sku,
// and catalog fields (barcode, unit, shelf life, description)
$products_stmt = $conn->prepare("
    SELECT
        sp.supplier_product_id,
        sp.supplier_id,
        sp.catalog_id,
        sp.supplier_sku,
        sp.product_name,
        sp.brand,
        sp.category_id,
        sp.unit_price,
        sp.unit_size,
        sp.minimum_order_quantity,
        sp.lead_time_days,
        sp.notes,
        c.category_name,
        pc.barcode              AS catalog_barcode,
        pc.default_unit         AS catalog_unit,
        pc.typical_shelf_life_days,
        pc.description          AS catalog_description
    FROM supplier_products sp
    LEFT JOIN categories c       ON sp.category_id = c.category_id
    LEFT JOIN product_catalog pc ON sp.catalog_id  = pc.catalog_id
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
$products_stmt->close();

header('Content-Type: application/json');
echo json_encode([
    'supplier' => $supplier,
    'products' => $products,
]);

$conn->close();
?>