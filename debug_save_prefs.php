<?php
// Debug script to test notification preference saving
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

// Simulate form submission data
$_POST['action'] = 'save_preferences';
$_POST['expiry_enabled'] = '1';
$_POST['low_stock_enabled'] = '0';  // Turned off
$_POST['achievement_enabled'] = '1';
$_POST['system_enabled'] = '0';    // Turned off
$_POST['expiry_days_before'] = '7';

$user_id = 1; // Test user

echo "Testing preference saving...\n";
echo "POST data: " . json_encode($_POST) . "\n";

$conn = getDBConnection();
if ($conn) {
    // Test the same logic as in the handler
    $ok = saveNotificationPreferences($conn, $user_id, [
        'expiry_enabled'      => isset($_POST['expiry_enabled'])      ? 1 : 0,
        'expiry_days_before'  => max(1, min(30, (int) ($_POST['expiry_days_before'] ?? 7))),
        'low_stock_enabled'   => isset($_POST['low_stock_enabled'])   ? 1 : 0,
        'achievement_enabled' => isset($_POST['achievement_enabled']) ? 1 : 0,
        'system_enabled'      => isset($_POST['system_enabled'])      ? 1 : 0,
        'email_enabled'       => isset($_POST['email_enabled'])       ? 1 : 0,
    ]);
    
    echo "Save result: " . ($ok ? "SUCCESS" : "FAILED") . "\n";
    
    // Read back the preferences
    $saved_prefs = getNotificationPreferences($conn, $user_id);
    echo "Saved preferences: " . json_encode($saved_prefs) . "\n";
    
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
