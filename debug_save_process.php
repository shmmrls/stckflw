<?php
// Debug the actual save process
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';

echo "=== DEBUGGING PREFERENCE SAVE PROCESS ===\n";

// Simulate exactly what happens when form is submitted
$_POST['action'] = 'save_preferences';
$_POST['expiry_enabled'] = '1';
// low_stock_enabled should be missing when unchecked (simulating real form submission)
$_POST['achievement_enabled'] = '1';
$_POST['system_enabled'] = '1'; // User turns this off - it should be missing
$_POST['expiry_days_before'] = '7';

echo "1. Simulated POST data (user turns off system_enabled):\n";
echo "   " . json_encode($_POST) . "\n";

$user_id = 1;

$conn = getDBConnection();
if ($conn) {
    // Check current state before save
    $current = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $current->bind_param('i', $user_id);
    $current->execute();
    $before = $current->get_result()->fetch_assoc();
    echo "2. Current database state:\n";
    echo "   " . json_encode($before) . "\n";
    
    // Apply the exact same logic as the PHP handler
    $save_data = [
        'expiry_enabled'      => isset($_POST['expiry_enabled'])      ? 1 : 0,
        'expiry_days_before'  => max(1, min(30, (int) ($_POST['expiry_days_before'] ?? 7))),
        'low_stock_enabled'   => isset($_POST['low_stock_enabled'])   ? 1 : 0,
        'achievement_enabled' => isset($_POST['achievement_enabled']) ? 1 : 0,
        'system_enabled'      => isset($_POST['system_enabled'])      ? 1 : 0,
        'email_enabled'       => isset($_POST['email_enabled'])       ? 1 : 0,
    ];
    
    echo "3. Data that will be saved:\n";
    echo "   " . json_encode($save_data) . "\n";
    
    // Save it
    $result = saveNotificationPreferences($conn, $user_id, $save_data);
    echo "4. Save result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    
    // Check state after save
    $after = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $after->bind_param('i', $user_id);
    $after->execute();
    $after_state = $after->get_result()->fetch_assoc();
    echo "5. Database state after save:\n";
    echo "   " . json_encode($after_state) . "\n";
    
    // Compare before and after
    if ($before && $after_state) {
        echo "6. CHANGES DETECTED:\n";
        foreach (['expiry_enabled', 'low_stock_enabled', 'achievement_enabled', 'system_enabled'] as $field) {
            $before_val = $before[$field] ?? 'missing';
            $after_val = $after_state[$field] ?? 'missing';
            $changed = ($before_val != $after_val) ? "CHANGED" : "SAME";
            echo "   $field: $before_val → $after_val ($changed)\n";
        }
    }
    
    $conn->close();
} else {
    echo "Database connection failed\n";
}

echo "\n=== END DEBUG ===\n";
?>
