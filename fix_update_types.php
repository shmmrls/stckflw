<?php
require_once __DIR__ . '/includes/config.php';

// Fix empty update_type values in grocery_inventory_updates
$conn = getDBConnection();

// Update records where update_type is empty or null
$update_stmt = $conn->prepare("
    UPDATE grocery_inventory_updates 
    SET update_type = 'added' 
    WHERE (update_type = '' OR update_type IS NULL)
");

if ($update_stmt->execute()) {
    $affected_rows = $update_stmt->affected_rows;
    echo "Fixed $affected_rows records with empty update_type values.\n";
} else {
    echo "Error updating records: " . $conn->error . "\n";
}

$update_stmt->close();
$conn->close();

echo "Update type fix completed.";
?>
