<?php
require_once __DIR__ . '/includes/config.php';

// Clean up existing notes that contain "via supplier ID"
$conn = getDBConnection();

// Update records where notes contain "via supplier ID"
$update_stmt = $conn->prepare("
    UPDATE grocery_inventory_updates 
    SET notes = 
        CASE 
            WHEN notes LIKE '% — via supplier ID %' THEN 
                SUBSTRING_INDEX(notes, ' — via supplier ID', 1)
            WHEN notes LIKE '%via supplier ID%' THEN 
                REPLACE(notes, CONCAT(' — via supplier ID ', SUBSTRING_INDEX(SUBSTRING_INDEX(notes, 'via supplier ID ', -1), ' ', 1)), '')
            ELSE notes
        END
    WHERE notes LIKE '%via supplier ID%'
");

if ($update_stmt->execute()) {
    $affected_rows = $update_stmt->affected_rows;
    echo "Cleaned up $affected_rows records with 'via supplier ID' in notes.\n";
} else {
    echo "Error updating records: " . $conn->error . "\n";
}

$update_stmt->close();
$conn->close();

echo "Notes cleanup completed.";
?>
