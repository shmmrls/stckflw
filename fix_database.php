<?php
require_once __DIR__ . '/includes/config.php';

$conn = getDBConnection();

echo "Fixing database user_id issue...\n";

try {
    $conn->begin_transaction();
    
    $stmt = $conn->prepare("UPDATE groups SET created_by = 1 WHERE created_by = 0");
    $stmt->execute();
    $affected_groups = $stmt->affected_rows;
    $stmt->close();
    
    $stmt = $conn->prepare("UPDATE group_members SET user_id = 1 WHERE user_id = 0");
    $stmt->execute();
    $affected_members = $stmt->affected_rows;
    $stmt->close();
    
    $stmt = $conn->prepare("UPDATE user_points SET user_id = 1 WHERE user_id = 0");
    $stmt->execute();
    $affected_points = $stmt->affected_rows;
    $stmt->close();
    
    $stmt = $conn->prepare("UPDATE users SET user_id = 1 WHERE user_id = 0");
    $stmt->execute();
    $affected_users = $stmt->affected_rows;
    $stmt->close();
    
    $stmt = $conn->prepare("ALTER TABLE users AUTO_INCREMENT = 2");
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    echo "Database fixed successfully!\n";
    echo "Updated records:\n";
    echo "- Users: $affected_users\n";
    echo "- Groups: $affected_groups\n";
    echo "- Group members: $affected_members\n";
    echo "- User points: $affected_points\n";
    echo "AUTO_INCREMENT reset to start from 2\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "Error fixing database: " . $e->getMessage() . "\n";
}

$conn->close();
?>
