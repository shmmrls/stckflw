<?php
// Verify the preferences were actually saved
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

echo "=== VERIFYING PREFERENCES WERE SAVED ===\n";

$user_id = 1;

$conn = getDBConnection();
if ($conn) {
    // Check current state in database
    $current = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $current->bind_param('i', $user_id);
    $current->execute();
    $prefs = $current->get_result()->fetch_assoc();
    
    if ($prefs) {
        echo "✅ Current preferences in database:\n";
        echo "   expiry_enabled: " . ($prefs['expiry_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   low_stock_enabled: " . ($prefs['low_stock_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   achievement_enabled: " . ($prefs['achievement_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   system_enabled: " . ($prefs['system_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   group_notifications_enabled: " . ($prefs['group_notifications_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   email_enabled: " . ($prefs['email_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "   expiry_days_before: " . $prefs['expiry_days_before'] . "\n";
        echo "   Last updated: " . $prefs['updated_at'] . "\n";
        
        // Check if it matches what we expect from the debug
        $expected = [
            'expiry_enabled' => 0,      // Should be OFF
            'low_stock_enabled' => 0,   // Should be OFF
            'achievement_enabled' => 1, // Should be ON
            'system_enabled' => 1,      // Should be ON
            'group_notifications_enabled' => 1, // Should be ON
            'email_enabled' => 0        // Should be OFF
        ];
        
        echo "\n=== COMPARING WITH EXPECTED VALUES ===\n";
        $all_correct = true;
        foreach ($expected as $field => $expected_value) {
            $actual = $prefs[$field];
            $status = ($actual == $expected_value) ? '✅' : '❌';
            echo "   $status $field: Expected $expected_value, Got $actual\n";
            if ($actual != $expected_value) $all_correct = false;
        }
        
        if ($all_correct) {
            echo "\n🎉 SUCCESS: All preferences saved correctly!\n";
        } else {
            echo "\n❌ ISSUE: Some preferences not saved correctly\n";
        }
    } else {
        echo "❌ No preferences found for user $user_id\n";
    }
    
    $conn->close();
} else {
    echo "❌ Database connection failed\n";
}

echo "\n=== END VERIFICATION ===\n";
?>
