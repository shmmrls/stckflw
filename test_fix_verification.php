<?php
// Test the fixed preference saving logic
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

echo "Testing FIXED preference saving...\n";

// Simulate the JavaScript behavior after fix
$_POST['action'] = 'save_preferences';
$_POST['expiry_enabled'] = '1';
// low_stock_enabled is intentionally missing (simulating unchecked box)
$_POST['achievement_enabled'] = '1';
// system_enabled is intentionally missing (simulating unchecked box)
$_POST['expiry_days_before'] = '7';

echo "POST data (unchecked boxes missing): " . json_encode($_POST) . "\n";

$user_id = 1;

$conn = getDBConnection();
if ($conn) {
    // This is what the JavaScript now sends (includes 0 for unchecked)
    $test_data = [
        'action' => 'save_preferences',
        'expiry_enabled' => '1',
        'low_stock_enabled' => '0',        // JavaScript adds this
        'achievement_enabled' => '1',
        'system_enabled' => '0',          // JavaScript adds this
        'expiry_days_before' => '7'
    ];
    
    echo "Data JavaScript will send: " . json_encode($test_data) . "\n";
    
    // Test the exact same logic as in the handler
    $ok = saveNotificationPreferences($conn, $user_id, [
        'expiry_enabled'      => isset($test_data['expiry_enabled'])      ? 1 : 0,
        'expiry_days_before'  => max(1, min(30, (int) ($test_data['expiry_days_before'] ?? 7))),
        'low_stock_enabled'   => isset($test_data['low_stock_enabled'])   ? 1 : 0,
        'achievement_enabled' => isset($test_data['achievement_enabled']) ? 1 : 0,
        'system_enabled'      => isset($test_data['system_enabled'])      ? 1 : 0,
        'email_enabled'       => isset($test_data['email_enabled'])       ? 1 : 0,
    ]);
    
    echo "Save result: " . ($ok ? "SUCCESS" : "FAILED") . "\n";
    
    // Read back the preferences
    $saved_prefs = getNotificationPreferences($conn, $user_id);
    echo "Saved preferences: " . json_encode($saved_prefs) . "\n";
    
    // Verify the fix
    if ($saved_prefs['low_stock_enabled'] == 0 && $saved_prefs['system_enabled'] == 0) {
        echo "✅ FIX SUCCESSFUL: Unchecked boxes are now saved as 0!\n";
    } else {
        echo "❌ Fix failed: Unchecked boxes not properly saved\n";
    }
    
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
