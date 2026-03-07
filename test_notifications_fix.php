<?php
require_once 'includes/config.php';
require_once 'includes/notifications_system.php';
require_once 'includes/expiry_alerts.php';

echo "Testing notification system..." . PHP_EOL;

$conn = getDBConnection();
if ($conn) {
    echo "✅ Database connected" . PHP_EOL;
    
    // Test get preferences
    $prefs = getNotificationPreferences($conn, 1);
    echo "✅ getNotificationPreferences works" . PHP_EOL;
    
    // Test save preferences
    $result = saveNotificationPreferences($conn, 1, [
        'expiry_enabled' => 1, 
        'low_stock_enabled' => 1, 
        'achievement_enabled' => 1, 
        'system_enabled' => 0
    ]);
    echo $result ? "✅ saveNotificationPreferences works" : "❌ save failed";
    echo PHP_EOL;
    
    // Test run checks (this was failing before)
    try {
        $results = runNotificationChecks($conn, 1);
        echo "✅ runNotificationChecks works!" . PHP_EOL;
        echo "Results: " . json_encode($results) . PHP_EOL;
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    }
    
    $conn->close();
} else {
    echo "❌ Database connection failed" . PHP_EOL;
}

echo "Test completed!" . PHP_EOL;
?>
