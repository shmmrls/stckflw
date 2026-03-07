<?php
// Debug why getNotificationPreferences returns wrong values
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

echo "Debugging getNotificationPreferences...\n";

$user_id = 999;

$conn = getDBConnection();
if ($conn) {
    // First, save known values
    $stmt = $conn->prepare("INSERT INTO notification_preferences (user_id, expiry_enabled, low_stock_enabled, system_enabled, achievement_enabled, expiry_days_before, email_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiiiiii', $user_id, $expiry_enabled=1, $low_stock_enabled=0, $system_enabled=0, $achievement_enabled=1, $expiry_days_before=7, $email_enabled=0);
    $stmt->execute();
    
    echo "Saved: expiry_enabled=1, low_stock_enabled=0, system_enabled=0\n";
    
    // Check raw database
    $check = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $check->bind_param('i', $user_id);
    $check->execute();
    $raw = $check->get_result()->fetch_assoc();
    echo "Raw database: " . json_encode($raw) . "\n";
    
    // Now test the function
    $func_result = getNotificationPreferences($conn, $user_id);
    echo "Function result: " . json_encode($func_result) . "\n";
    
    // Clean up
    $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?")->bind_param('i', $user_id)->execute();
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
