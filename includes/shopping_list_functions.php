<?php
/**
 * Shopping List System
 * Auto-generate from low stock items and manage manually
 */

if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Ensure shopping list tables exist
 */
function ensureShoppingListTables($conn) {
    // Shopping list items table
    $create_items = "CREATE TABLE IF NOT EXISTS shopping_list_items (
        list_item_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 1.00,
        unit VARCHAR(50) DEFAULT 'piece',
        category_id INT,
        is_purchased TINYINT(1) DEFAULT 0,
        is_auto_generated TINYINT(1) DEFAULT 0,
        source_item_id INT DEFAULT NULL,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
        FOREIGN KEY (source_item_id) REFERENCES customer_items(item_id) ON DELETE SET NULL,
        INDEX idx_user_purchased (user_id, is_purchased),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($create_items);
    
    // Shopping list settings table
    $create_settings = "CREATE TABLE IF NOT EXISTS shopping_list_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        auto_add_low_stock TINYINT(1) DEFAULT 1,
        low_stock_threshold DECIMAL(10,2) DEFAULT 2.00,
        auto_add_expiring TINYINT(1) DEFAULT 0,
        expiring_days INT DEFAULT 3,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_settings (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($create_settings);
}

/**
 * Get user's shopping list settings
 */
function getShoppingListSettings($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM shopping_list_settings WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Return defaults if no settings exist
    if (!$result) {
        return [
            'auto_add_low_stock' => 1,
            'low_stock_threshold' => 2.00,
            'auto_add_expiring' => 0,
            'expiring_days' => 3
        ];
    }
    
    return $result;
}

/**
 * Save shopping list settings
 */
function saveShoppingListSettings($conn, $user_id, $settings) {
    $stmt = $conn->prepare("
        INSERT INTO shopping_list_settings 
        (user_id, auto_add_low_stock, low_stock_threshold, auto_add_expiring, expiring_days)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        auto_add_low_stock = VALUES(auto_add_low_stock),
        low_stock_threshold = VALUES(low_stock_threshold),
        auto_add_expiring = VALUES(auto_add_expiring),
        expiring_days = VALUES(expiring_days)
    ");
    
    $stmt->bind_param('iidii',
        $user_id,
        $settings['auto_add_low_stock'],
        $settings['low_stock_threshold'],
        $settings['auto_add_expiring'],
        $settings['expiring_days']
    );
    
    return $stmt->execute();
}

/**
 * Get low stock items for a user
 */
function getLowStockItems($conn, $user_id, $threshold = 2.00) {
    $query = "
        SELECT 
            ci.item_id,
            ci.item_name,
            ci.quantity,
            ci.unit,
            ci.category_id,
            c.category_name
        FROM customer_items ci
        JOIN categories c ON ci.category_id = c.category_id
        JOIN groups g ON ci.group_id = g.group_id
        JOIN group_members gm ON g.group_id = gm.group_id
        WHERE gm.user_id = ?
        AND ci.quantity <= ?
        AND ci.quantity > 0
        AND ci.expiry_date >= CURDATE()
        GROUP BY ci.item_name, ci.category_id
        ORDER BY ci.quantity ASC, ci.item_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('id', $user_id, $threshold);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get expiring items that should be replaced
 */
function getExpiringItemsForReplacement($conn, $user_id, $days = 3) {
    $query = "
        SELECT 
            ci.item_id,
            ci.item_name,
            ci.quantity,
            ci.unit,
            ci.category_id,
            c.category_name,
            DATEDIFF(ci.expiry_date, CURDATE()) as days_until_expiry
        FROM customer_items ci
        JOIN categories c ON ci.category_id = c.category_id
        JOIN groups g ON ci.group_id = g.group_id
        JOIN group_members gm ON g.group_id = gm.group_id
        WHERE gm.user_id = ?
        AND ci.expiry_date >= CURDATE()
        AND ci.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        AND ci.quantity > 0
        GROUP BY ci.item_name, ci.category_id
        ORDER BY ci.expiry_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $user_id, $days);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Auto-generate shopping list items from low stock
 */
function autoGenerateShoppingList($conn, $user_id) {
    $settings = getShoppingListSettings($conn, $user_id);
    $added_count = 0;
    
    // Add low stock items
    if ($settings['auto_add_low_stock']) {
        $low_stock = getLowStockItems($conn, $user_id, $settings['low_stock_threshold']);
        
        foreach ($low_stock as $item) {
            // Check if already in list
            $check = $conn->prepare("
                SELECT list_item_id FROM shopping_list_items 
                WHERE user_id = ? AND item_name = ? AND is_purchased = 0
            ");
            $check->bind_param('is', $user_id, $item['item_name']);
            $check->execute();
            
            if ($check->get_result()->num_rows == 0) {
                // Add to shopping list
                $add = $conn->prepare("
                    INSERT INTO shopping_list_items 
                    (user_id, item_name, quantity, unit, category_id, is_auto_generated, source_item_id, priority)
                    VALUES (?, ?, ?, ?, ?, 1, ?, 'high')
                ");
                $add->bind_param('isdsii',
                    $user_id,
                    $item['item_name'],
                    $item['quantity'],
                    $item['unit'],
                    $item['category_id'],
                    $item['item_id']
                );
                
                if ($add->execute()) {
                    $added_count++;
                }
            }
        }
    }
    
    // Add expiring items for replacement
    if ($settings['auto_add_expiring']) {
        $expiring = getExpiringItemsForReplacement($conn, $user_id, $settings['expiring_days']);
        
        foreach ($expiring as $item) {
            // Check if already in list
            $check = $conn->prepare("
                SELECT list_item_id FROM shopping_list_items 
                WHERE user_id = ? AND item_name = ? AND is_purchased = 0
            ");
            $check->bind_param('is', $user_id, $item['item_name']);
            $check->execute();
            
            if ($check->get_result()->num_rows == 0) {
                // Add to shopping list
                $add = $conn->prepare("
                    INSERT INTO shopping_list_items 
                    (user_id, item_name, quantity, unit, category_id, is_auto_generated, source_item_id, priority, notes)
                    VALUES (?, ?, ?, ?, ?, 1, ?, 'medium', ?)
                ");
                
                $note = "Expiring in " . $item['days_until_expiry'] . " day(s) - replacement";
                $add->bind_param('isdsii s',
                    $user_id,
                    $item['item_name'],
                    $item['quantity'],
                    $item['unit'],
                    $item['category_id'],
                    $item['item_id'],
                    $note
                );
                
                if ($add->execute()) {
                    $added_count++;
                }
            }
        }
    }
    
    return $added_count;
}

/**
 * Get shopping list items for a user
 */
function getShoppingListItems($conn, $user_id, $include_purchased = false) {
    $query = "
        SELECT 
            sl.*,
            c.category_name,
            CASE 
                WHEN sl.is_auto_generated = 1 THEN 'Auto-added'
                ELSE 'Manual'
            END as source_type
        FROM shopping_list_items sl
        LEFT JOIN categories c ON sl.category_id = c.category_id
        WHERE sl.user_id = ?
    ";
    
    if (!$include_purchased) {
        $query .= " AND sl.is_purchased = 0";
    }
    
    $query .= " ORDER BY 
        sl.is_purchased ASC,
        FIELD(sl.priority, 'high', 'medium', 'low'),
        c.category_name ASC,
        sl.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get shopping list items grouped by category
 */
function getShoppingListByCategory($conn, $user_id) {
    $items = getShoppingListItems($conn, $user_id, false);
    $grouped = [];
    
    foreach ($items as $item) {
        $category = $item['category_name'] ?? 'Uncategorized';
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $item;
    }
    
    return $grouped;
}

/**
 * Add item to shopping list
 */
function addShoppingListItem($conn, $user_id, $data) {
    $stmt = $conn->prepare("
        INSERT INTO shopping_list_items 
        (user_id, item_name, quantity, unit, category_id, priority, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('isdsiss',
        $user_id,
        $data['item_name'],
        $data['quantity'],
        $data['unit'],
        $data['category_id'],
        $data['priority'],
        $data['notes']
    );
    
    return $stmt->execute();
}

/**
 * Update shopping list item
 */
function updateShoppingListItem($conn, $list_item_id, $user_id, $data) {
    $stmt = $conn->prepare("
        UPDATE shopping_list_items 
        SET item_name = ?, quantity = ?, unit = ?, category_id = ?, priority = ?, notes = ?
        WHERE list_item_id = ? AND user_id = ?
    ");
    
    $stmt->bind_param('sdsiss ii',
        $data['item_name'],
        $data['quantity'],
        $data['unit'],
        $data['category_id'],
        $data['priority'],
        $data['notes'],
        $list_item_id,
        $user_id
    );
    
    return $stmt->execute();
}

/**
 * Mark item as purchased
 */
function markItemPurchased($conn, $list_item_id, $user_id) {
    $stmt = $conn->prepare("
        UPDATE shopping_list_items 
        SET is_purchased = 1, updated_at = CURRENT_TIMESTAMP
        WHERE list_item_id = ? AND user_id = ?
    ");
    
    $stmt->bind_param('ii', $list_item_id, $user_id);
    return $stmt->execute();
}

/**
 * Mark item as unpurchased
 */
function markItemUnpurchased($conn, $list_item_id, $user_id) {
    $stmt = $conn->prepare("
        UPDATE shopping_list_items 
        SET is_purchased = 0, updated_at = CURRENT_TIMESTAMP
        WHERE list_item_id = ? AND user_id = ?
    ");
    
    $stmt->bind_param('ii', $list_item_id, $user_id);
    return $stmt->execute();
}

/**
 * Delete shopping list item
 */
function deleteShoppingListItem($conn, $list_item_id, $user_id) {
    $stmt = $conn->prepare("
        DELETE FROM shopping_list_items 
        WHERE list_item_id = ? AND user_id = ?
    ");
    
    $stmt->bind_param('ii', $list_item_id, $user_id);
    return $stmt->execute();
}

/**
 * Clear all purchased items
 */
function clearPurchasedItems($conn, $user_id) {
    $stmt = $conn->prepare("
        DELETE FROM shopping_list_items 
        WHERE user_id = ? AND is_purchased = 1
    ");
    
    $stmt->bind_param('i', $user_id);
    return $stmt->execute();
}

/**
 * Clear entire shopping list
 */
function clearShoppingList($conn, $user_id) {
    $stmt = $conn->prepare("
        DELETE FROM shopping_list_items 
        WHERE user_id = ?
    ");
    
    $stmt->bind_param('i', $user_id);
    return $stmt->execute();
}

/**
 * Get shopping list statistics
 */
function getShoppingListStats($conn, $user_id) {
    $query = "
        SELECT 
            COUNT(*) as total_items,
            SUM(CASE WHEN is_purchased = 0 THEN 1 ELSE 0 END) as pending_items,
            SUM(CASE WHEN is_purchased = 1 THEN 1 ELSE 0 END) as purchased_items,
            SUM(CASE WHEN is_auto_generated = 1 AND is_purchased = 0 THEN 1 ELSE 0 END) as auto_generated_items,
            SUM(CASE WHEN priority = 'high' AND is_purchased = 0 THEN 1 ELSE 0 END) as high_priority_items
        FROM shopping_list_items
        WHERE user_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Calculate completion percentage
    $total = $result['total_items'] ?? 0;
    $purchased = $result['purchased_items'] ?? 0;
    $result['completion_percentage'] = $total > 0 ? round(($purchased / $total) * 100) : 0;
    
    return $result;
}

/**
 * Get categories for dropdown
 */
function getCategories($conn) {
    $query = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}