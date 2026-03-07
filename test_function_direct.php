<?php
// Test the saveNotificationPreferences function directly
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

echo "Testing saveNotificationPreferences function directly...\n";

$user_id = 999;

$conn = getDBConnection();
if ($conn) {
    // Clean up first
    $delete_stmt = $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?");
    $delete_stmt->bind_param('i', $user_id);
    $delete_stmt->execute();
    
    // Call the function directly with the same data
    $prefs_data = [
        'expiry_enabled' => 1,
        'low_stock_enabled' => 0,
        'achievement_enabled' => 1,
        'system_enabled' => 0,
        'expiry_days_before' => 7,
        'email_enabled' => 0
    ];
    
    echo "Calling function with: " . json_encode($prefs_data) . "\n";
    
    $result = saveNotificationPreferences($conn, $user_id, $prefs_data);
    echo "Function result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    
    // Check what was actually saved
    $check_stmt = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    $saved = $check_stmt->get_result()->fetch_assoc();
    
    echo "Actually saved by function: " . json_encode($saved) . "\n";
    
    // Clean up
    $delete_stmt->execute();
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
