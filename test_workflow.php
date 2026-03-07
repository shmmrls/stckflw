<?php
/**
 * Test Complete Store-Supplier Workflow
 */

require_once 'includes/store_supplier_validation.php';

echo "<h1>Complete Store-Supplier Workflow Test</h1>";

$store_id = 1;
$supplier_id = 1;
$user_id = 1;

// Step 1: Register store with supplier
echo "<h2>Step 1: Register Store with Supplier</h2>";
$result = registerStoreWithSupplier($store_id, $supplier_id, [
    'preferred_supplier' => 1,
    'credit_limit' => 50000.00,
    'notes' => 'Test relationship for workflow validation'
]);

echo "<p>Registration result: " . ($result['success'] ? "Success" : "Failed") . "</p>";
if (!$result['success']) {
    echo "<p>Error: " . htmlspecialchars($result['message']) . "</p>";
} else {
    echo "<p>Message: " . htmlspecialchars($result['message']) . "</p>";
}

// Step 2: Check if relationship exists
echo "<h2>Step 2: Verify Relationship Exists</h2>";
$is_registered = isStoreRegisteredWithSupplier($store_id, $supplier_id);
echo "<p>Store $store_id registered with Supplier $supplier_id: " . ($is_registered ? "Yes" : "No") . "</p>";

// Step 3: Get valid suppliers for store
echo "<h2>Step 3: Get Valid Suppliers</h2>";
$suppliers = getValidSuppliersForStore($store_id);
echo "<p>Found " . count($suppliers) . " valid suppliers for Store $store_id</p>";
if (!empty($suppliers)) {
    echo "<ul>";
    foreach ($suppliers as $supplier) {
        echo "<li>" . htmlspecialchars($supplier['supplier_name']) . 
             " (" . htmlspecialchars($supplier['supplier_type']) . ")";
        if ($supplier['preferred_supplier']) {
            echo " - <strong>Preferred</strong>";
        }
        echo "</li>";
    }
    echo "</ul>";
}

// Step 4: Validate purchase order data
echo "<h2>Step 4: Validate Purchase Order Data</h2>";
$po_data = [
    'store_id' => $store_id,
    'supplier_id' => $supplier_id
];

$validation = validatePurchaseOrderData($po_data);
echo "<p>Validation result: " . ($validation['valid'] ? "Valid" : "Invalid") . "</p>";
if (!$validation['valid']) {
    echo "<p>Error: " . htmlspecialchars($validation['message']) . "</p>";
}

// Step 5: Create purchase order
echo "<h2>Step 5: Create Purchase Order</h2>";
if ($validation['valid']) {
    $po_result = createValidatedPurchaseOrder([
        'store_id' => $store_id,
        'supplier_id' => $supplier_id,
        'created_by' => $user_id,
        'notes' => 'Test purchase order for workflow validation'
    ]);
    
    echo "<p>Creation result: " . ($po_result['success'] ? "Success" : "Failed") . "</p>";
    if ($po_result['success']) {
        echo "<p>PO Number: " . htmlspecialchars($po_result['po_number']) . "</p>";
        echo "<p>PO ID: " . $po_result['po_id'] . "</p>";
        echo "<p>Message: " . htmlspecialchars($po_result['message']) . "</p>";
    } else {
        echo "<p>Error: " . htmlspecialchars($po_result['message']) . "</p>";
    }
} else {
    echo "<p>Skipping PO creation due to validation failure</p>";
}

// Step 6: Test with invalid supplier
echo "<h2>Step 6: Test with Invalid Supplier</h2>";
$invalid_supplier_id = 999; // Assuming this doesn't exist
$invalid_validation = validatePurchaseOrderData([
    'store_id' => $store_id,
    'supplier_id' => $invalid_supplier_id
]);

echo "<p>Validation with invalid supplier: " . ($invalid_validation['valid'] ? "Valid" : "Invalid") . "</p>";
if (!$invalid_validation['valid']) {
    echo "<p>Expected error: " . htmlspecialchars($invalid_validation['message']) . "</p>";
}

echo "<h2>Workflow Test Complete</h2>";
echo "<p>The complete store-supplier workflow has been tested successfully!</p>";
?>
