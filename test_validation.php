<?php
/**
 * Test Store-Supplier Validation Functions
 */

require_once 'includes/store_supplier_validation.php';

echo "<h1>Testing Store-Supplier Validation Functions</h1>";

// Test 1: Check if store is registered with supplier
echo "<h2>Test 1: Store-Supplier Relationship Check</h2>";
$store_id = 1;
$supplier_id = 1;

$is_registered = isStoreRegisteredWithSupplier($store_id, $supplier_id);
echo "<p>Store $store_id registered with Supplier $supplier_id: " . ($is_registered ? "Yes" : "No") . "</p>";

// Test 2: Get valid suppliers for store
echo "<h2>Test 2: Get Valid Suppliers for Store</h2>";
$suppliers = getValidSuppliersForStore($store_id);
echo "<p>Found " . count($suppliers) . " valid suppliers for Store $store_id</p>";
if (!empty($suppliers)) {
    echo "<ul>";
    foreach (array_slice($suppliers, 0, 3) as $supplier) {
        echo "<li>" . htmlspecialchars($supplier['supplier_name']) . " (" . htmlspecialchars($supplier['supplier_type']) . ")</li>";
    }
    if (count($suppliers) > 3) {
        echo "<li>... and " . (count($suppliers) - 3) . " more</li>";
    }
    echo "</ul>";
}

// Test 3: Validate purchase order data
echo "<h2>Test 3: Purchase Order Validation</h2>";
$po_data = [
    'store_id' => $store_id,
    'supplier_id' => $supplier_id
];

$validation = validatePurchaseOrderData($po_data);
echo "<p>Validation result: " . ($validation['valid'] ? "Valid" : "Invalid") . "</p>";
if (!$validation['valid']) {
    echo "<p>Error: " . htmlspecialchars($validation['message']) . "</p>";
}

// Test 4: Try to create a purchase order (if validation passes)
echo "<h2>Test 4: Purchase Order Creation</h2>";
if ($validation['valid']) {
    $po_result = createValidatedPurchaseOrder([
        'store_id' => $store_id,
        'supplier_id' => $supplier_id,
        'created_by' => 1,
        'notes' => 'Test purchase order'
    ]);
    
    echo "<p>Creation result: " . ($po_result['success'] ? "Success" : "Failed") . "</p>";
    if ($po_result['success']) {
        echo "<p>PO Number: " . htmlspecialchars($po_result['po_number']) . "</p>";
        echo "<p>PO ID: " . $po_result['po_id'] . "</p>";
    } else {
        echo "<p>Error: " . htmlspecialchars($po_result['message']) . "</p>";
    }
} else {
    echo "<p>Skipping PO creation due to validation failure</p>";
}

// Test 5: Generate PO number
echo "<h2>Test 5: PO Number Generation</h2>";
$po_number = generatePONumber();
echo "<p>Generated PO Number: " . htmlspecialchars($po_number) . "</p>";

echo "<h2>Test Complete</h2>";
echo "<p>All validation functions have been tested. Check the results above.</p>";
?>
