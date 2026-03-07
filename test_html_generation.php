<?php
// Test the exact same logic as the notification settings page
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';
require_once 'includes/expiry_alerts.php';

$user_id = 1;
$role = 'customer';

$conn = getDBConnection();
if ($conn) {
    // Load preferences exactly like the page does
    $prefs = getNotificationPreferences($conn, $user_id);
    
    echo "Loaded preferences: " . json_encode($prefs) . "\n";
    
    // Test the checkbox rendering logic
    echo "Checkbox rendering test:\n";
    echo "expiry_enabled: " . ($prefs['expiry_enabled'] ? 'checked' : 'NOT checked') . "\n";
    echo "low_stock_enabled: " . ($prefs['low_stock_enabled'] ? 'checked' : 'NOT checked') . "\n";
    echo "achievement_enabled: " . ($prefs['achievement_enabled'] ? 'checked' : 'NOT checked') . "\n";
    echo "system_enabled: " . ($prefs['system_enabled'] ? 'checked' : 'NOT checked') . "\n";
    
    // Generate the actual HTML like the page does
    echo "\nGenerated HTML:\n";
    echo '<input type="checkbox" name="expiry_enabled" value="1" ' . ($prefs['expiry_enabled'] ? 'checked' : '') . '>' . "\n";
    echo '<input type="checkbox" name="low_stock_enabled" value="1" ' . ($prefs['low_stock_enabled'] ? 'checked' : '') . '>' . "\n";
    echo '<input type="checkbox" name="achievement_enabled" value="1" ' . ($prefs['achievement_enabled'] ? 'checked' : '') . '>' . "\n";
    echo '<input type="checkbox" name="system_enabled" value="1" ' . ($prefs['system_enabled'] ? 'checked' : '') . '>' . "\n";
    
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
