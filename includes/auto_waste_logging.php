<?php
/**
 * Automatic Waste Logging System
 * Automatically logs expired items as waste in customer_inventory_updates table
 */

/**
 * Automatically log expired items as waste for a user
 * This should be called periodically (daily) or when user visits dashboard
 */
function autoLogExpiredItems($conn, $user_id): array {
    $logged_count = 0;
    $results = [];
    
    // Get user's groups
    $groups_stmt = $conn->prepare("SELECT group_id FROM group_members WHERE user_id = ?");
    $groups_stmt->bind_param("i", $user_id);
    $groups_stmt->execute();
    $groups = $groups_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($groups)) {
        return ['logged_count' => 0, 'items' => []];
    }
    
    $group_ids = array_column($groups, 'group_id');
    $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
    $types = str_repeat('i', count($group_ids));
    
    // Find expired items that haven't been logged as expired yet
    $expired_stmt = $conn->prepare("
        SELECT ci.item_id, ci.item_name, ci.quantity, ci.unit, ci.expiry_date, 
               ci.category_id, c.category_name, ci.group_id, g.group_name
        FROM customer_items ci
        LEFT JOIN categories c ON ci.category_id = c.category_id
        LEFT JOIN groups g ON ci.group_id = g.group_id
        WHERE ci.group_id IN ($placeholders)
          AND ci.expiry_status = 'expired'
          AND ci.item_id NOT IN (
              SELECT DISTINCT ciu.item_id 
              FROM customer_inventory_updates ciu 
              WHERE ciu.update_type = 'expired' 
              AND ciu.updated_by = ?
          )
        ORDER BY ci.expiry_date ASC
    ");
    
    $params = array_merge($group_ids, [$user_id]);
    $expired_stmt->bind_param($types . 'i', ...$params);
    $expired_stmt->execute();
    $expired_items = $expired_stmt->get_result();
    
    foreach ($expired_items as $item) {
        // Calculate days expired
        $days_expired = floor((strtotime('today') - strtotime($item['expiry_date'])) / 86400);
        
        // Log as expired waste
        $log_stmt = $conn->prepare("
            INSERT INTO customer_inventory_updates 
            (item_id, update_type, quantity_change, updated_by, notes, update_date)
            VALUES (?, 'expired', ?, ?, ?, NOW())
        ");
        
        $notes = "Auto-logged: Expired {$days_expired} day(s) ago";
        $log_stmt->bind_param("idis", $item['item_id'], $item['quantity'], $user_id, $notes);
        
        if ($log_stmt->execute()) {
            $logged_count++;
            $results[] = [
                'item_name' => $item['item_name'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'days_expired' => $days_expired,
                'category' => $item['category_name'],
                'group' => $item['group_name']
            ];
            
            // Award points for waste logging (1 point for expired items)
            $points_stmt = $conn->prepare("
                INSERT INTO user_points (user_id, total_points) 
                VALUES (?, 1) 
                ON DUPLICATE KEY UPDATE total_points = total_points + 1
            ");
            $points_stmt->bind_param("i", $user_id);
            $points_stmt->execute();
            
            // Log points
            $points_log_stmt = $conn->prepare("
                INSERT INTO points_log (user_id, action_type, points_earned, item_id)
                VALUES (?, 'EXPIRED_ITEM', 1, ?)
            ");
            $points_log_stmt->bind_param("ii", $user_id, $item['item_id']);
            $points_log_stmt->execute();
        }
    }
    
    return [
        'logged_count' => $logged_count,
        'items' => $results
    ];
}

/**
 * Check for items that should be marked as expired but aren't yet
 * Updates expiry_status for items that have passed their expiry date
 */
function updateExpiredStatus($conn): int {
    $update_stmt = $conn->prepare("
        UPDATE customer_items 
        SET expiry_status = 'expired', 
            alert_flag = 1 
        WHERE expiry_date < CURDATE() 
          AND expiry_status != 'expired'
    ");
    
    $update_stmt->execute();
    return $update_stmt->affected_rows;
}

/**
 * Get waste statistics summary for dashboard
 */
function getWasteStatistics($conn, $user_id): array {
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN ciu.update_type = 'expired' THEN 1 END) as auto_logged_expired,
            COUNT(CASE WHEN ciu.update_type = 'spoiled' THEN 1 END) as manually_logged_spoiled,
            COUNT(CASE WHEN ciu.update_type = 'expired' AND ciu.notes LIKE 'Auto-logged%' THEN 1 END) as auto_expired_count,
            COUNT(CASE WHEN ciu.update_type = 'expired' AND ciu.notes NOT LIKE 'Auto-logged%' THEN 1 END) as manual_expired_count,
            SUM(CASE WHEN ciu.update_type IN ('expired', 'spoiled') THEN ciu.quantity_change ELSE 0 END) as total_waste_quantity,
            DATE(ciu.update_date) as last_waste_date
        FROM customer_inventory_updates ciu
        WHERE ciu.updated_by = ? 
          AND ciu.update_type IN ('expired', 'spoiled')
        ORDER BY ciu.update_date DESC
        LIMIT 1
    ");
    
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    return $stats_stmt->get_result()->fetch_assoc() ?: [];
}
?>
