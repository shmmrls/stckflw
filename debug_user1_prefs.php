<?php
// Debug what's actually happening with user 1's preferences
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

echo "Debugging user 1's current preferences...\n";

$user_id = 1;

$conn = getDBConnection();
if ($conn) {
    // Check raw database
    $check = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $check->bind_param('i', $user_id);
    $check->execute();
    $raw = $check->get_result()->fetch_assoc();
    
    echo "Raw database for user 1: " . json_encode($raw) . "\n";
    
    // Check what getNotificationPreferences returns
    $prefs = getNotificationPreferences($conn, $user_id);
    echo "getNotificationPreferences result: " . json_encode($prefs) . "\n";
    
    // Check if there are any issues
    if (!$raw) {
        echo "❌ No record found in database for user 1\n";
    } else {
        echo "✅ Database record exists\n";
        echo "   expiry_enabled: " . ($raw['expiry_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   low_stock_enabled: " . ($raw['low_stock_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   achievement_enabled: " . ($raw['achievement_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   system_enabled: " . ($raw['system_enabled'] ? 'ON' : 'OFF') . "\n";
    }
    
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
