<?php
// Debug the save function step by step
require_once 'includes/config.php';

echo "Debugging save function...\n";

$user_id = 999;

$conn = getDBConnection();
if ($conn) {
    // Clean up first
    $delete_stmt = $conn->prepare("DELETE FROM notification_preferences WHERE user_id = ?");
    $delete_stmt->bind_param('i', $user_id);
    $delete_stmt->execute();
    
    // Test the raw SQL that saveNotificationPreferences should generate
    $expiry_enabled = 1;
    $expiry_days_before = 7;
    $low_stock_enabled = 0;  // This should be 0
    $achievement_enabled = 1;
    $system_enabled = 0;     // This should be 0
    $email_enabled = 0;
    
    echo "Values to save: expiry_enabled=$expiry_enabled, low_stock_enabled=$low_stock_enabled, system_enabled=$system_enabled\n";
    
    // Execute the exact same SQL as the function
    $stmt = $conn->prepare("
        INSERT INTO notification_preferences
            (user_id, expiry_enabled, expiry_days_before, low_stock_enabled,
             achievement_enabled, system_enabled, email_enabled)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            expiry_enabled      = VALUES(expiry_enabled),
            expiry_days_before  = VALUES(expiry_days_before),
            low_stock_enabled   = VALUES(low_stock_enabled),
            achievement_enabled = VALUES(achievement_enabled),
            system_enabled      = VALUES(system_enabled),
            email_enabled       = VALUES(email_enabled)
    ");
    
    $stmt->bind_param('iiiiiii',
        $user_id, $expiry_enabled, $expiry_days_before,
        $low_stock_enabled, $achievement_enabled, $system_enabled, $email_enabled
    );
    
    $result = $stmt->execute();
    echo "Direct SQL result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    
    // Check what was actually saved
    $check_stmt = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    $saved = $check_stmt->get_result()->fetch_assoc();
    
    echo "Actually saved: " . json_encode($saved) . "\n";
    
    // Clean up
    $delete_stmt->execute();
    $conn->close();
} else {
    echo "Database connection failed\n";
}
?>
