<?php
// Test with a fresh user to see if the fix works
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

echo "Testing with fresh user data...\n";

$user_id = 999; // Use a different user ID for testing

$conn = getDBConnection();
if ($conn) {
    // Delete any existing preferences for this test user
    $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?")->bind_param('i', $user_id)->execute();
    
    // Test the exact same logic as in the handler with unchecked boxes
    $_POST = [
        'action' => 'save_preferences',
        'expiry_enabled' => '1',
        'low_stock_enabled' => '0',        // Unchecked
        'achievement_enabled' => '1',
        'system_enabled' => '0',          // Unchecked
        'expiry_days_before' => '7'
    ];
    
    echo "POST data: " . json_encode($_POST) . "\n";
    
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
    
    // Read back the preferences
    $saved_prefs = getNotificationPreferences($conn, $user_id);
    echo "Saved preferences: " . json_encode($saved_prefs) . "\n";
    
    // Verify the fix
    if ($saved_prefs['low_stock_enabled'] == 0 && $saved_prefs['system_enabled'] == 0) {
        echo "✅ FIX SUCCESSFUL: Unchecked boxes are now saved as 0!\n";
    } else {
        echo "❌ Fix failed: low_stock_enabled={$saved_prefs['low_stock_enabled']}, system_enabled={$saved_prefs['system_enabled']}\n";
    }
    
    // Clean up test data
    $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?")->bind_param('i', $user_id)->execute();
    
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
