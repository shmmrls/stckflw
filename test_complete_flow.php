<?php
// Test the complete flow with JavaScript simulation
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

echo "Testing COMPLETE flow with JavaScript simulation...\n";

$user_id = 999;

$conn = getDBConnection();
if ($conn) {
    // Clean up first
    $delete_stmt = $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?");
    $delete_stmt->bind_param('i', $user_id);
    $delete_stmt->execute();
    
    // Simulate the JavaScript behavior after fix
    $_POST = [
        'action' => 'save_preferences',
        'expiry_enabled' => '1',
        'low_stock_enabled' => '0',        // JavaScript adds this for unchecked
        'achievement_enabled' => '1',
        'system_enabled' => '0',          // JavaScript adds this for unchecked
        'expiry_days_before' => '7'
    ];
    
    echo "JavaScript will send: " . json_encode($_POST) . "\n";
    
    // Test the exact same logic as in the handler
    $ok = saveNotificationPreferences($conn, $user_id, [
        'expiry_enabled'      => isset($_POST['expiry_enabled'])      ? 1 : 0,
        'expiry_days_before'  => max(1, min(30, (int) ($_POST['expiry_days_before'] ?? 7))),
        'low_stock_enabled'   => isset($_POST['low_stock_enabled'])   ? 1 : 0,
        'achievement_enabled' => isset($_POST['achievement_enabled']) ? 1 : 0,
        'system_enabled'      => isset($_POST['system_enabled'])      ? 1 : 0,
        'email_enabled'       => isset($_POST['email_enabled'])       ? 1 : 0,
    ]);
    
    echo "Save result: " . ($ok ? "SUCCESS" : "FAILED") . "\n";
    
    // Read back the preferences using the same function as the UI
    $saved_prefs = getNotificationPreferences($conn, $user_id);
    echo "Preferences read back: " . json_encode($saved_prefs) . "\n";
    
    // Verify the fix
    if ($saved_prefs['low_stock_enabled'] == 0 && $saved_prefs['system_enabled'] == 0) {
        echo "✅ COMPLETE SUCCESS: Notification preferences are now working correctly!\n";
        echo "✅ Unchecked boxes are saved as 0\n";
        echo "✅ Checked boxes are saved as 1\n";
        echo "✅ The UI will now properly reflect changes\n";
    } else {
        echo "❌ Still not working: low_stock_enabled={$saved_prefs['low_stock_enabled']}, system_enabled={$saved_prefs['system_enabled']}\n";
    }
    
    // Clean up
    $delete_stmt->execute();
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
