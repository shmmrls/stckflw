<?php

/**

 * Notification System

 * Generates, stores, and retrieves in-app notifications for inventory events.

 * Works with: users, customer_items, grocery_items, user_points, user_badges, badges

 */



// ──────────────────────────────────────────────

// Constants

// ──────────────────────────────────────────────

define('NOTIF_TYPE_EXPIRY',       'expiry');

define('NOTIF_TYPE_LOW_STOCK',    'low_stock');

define('NOTIF_TYPE_ACHIEVEMENT',  'achievement');

define('NOTIF_TYPE_SYSTEM',       'system');



define('NOTIF_PRIORITY_HIGH',     'high');

define('NOTIF_PRIORITY_MEDIUM',   'medium');

define('NOTIF_PRIORITY_LOW',      'low');



// ──────────────────────────────────────────────

// Schema bootstrap: create notifications table if absent

// ──────────────────────────────────────────────

function ensureNotificationsTable($conn): void {

    $conn->query("

        CREATE TABLE IF NOT EXISTS `notifications` (

            `notification_id` INT AUTO_INCREMENT PRIMARY KEY,

            `user_id`         INT          NOT NULL,

            `type`            VARCHAR(50)  NOT NULL,

            `priority`        ENUM('high','medium','low') DEFAULT 'medium',

            `title`           VARCHAR(255) NOT NULL,

            `message`         TEXT         NOT NULL,

            `reference_id`    INT          DEFAULT NULL COMMENT 'item_id / badge_id etc.',

            `reference_type`  VARCHAR(50)  DEFAULT NULL COMMENT 'customer_item / grocery_item / badge',

            `is_read`         TINYINT(1)   DEFAULT 0,

            `is_dismissed`    TINYINT(1)   DEFAULT 0,

            `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

            INDEX `idx_notif_user`     (`user_id`),

            INDEX `idx_notif_type`     (`type`),

            INDEX `idx_notif_read`     (`is_read`),

            INDEX `idx_notif_created`  (`created_at`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

    ");



    // Notification preferences table

    $conn->query("

        CREATE TABLE IF NOT EXISTS `notification_preferences` (

            `pref_id`              INT AUTO_INCREMENT PRIMARY KEY,

            `user_id`              INT         NOT NULL UNIQUE,

            `expiry_enabled`       TINYINT(1)  DEFAULT 1,

            `expiry_days_before`   INT         DEFAULT 7,

            `low_stock_enabled`    TINYINT(1)  DEFAULT 1,

            `achievement_enabled`  TINYINT(1)  DEFAULT 1,

            `system_enabled`       TINYINT(1)  DEFAULT 1,

            `group_notifications_enabled` TINYINT(1)  DEFAULT 1,

            `email_enabled`        TINYINT(1)  DEFAULT 0,

            `updated_at`           TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX `idx_notif_pref_user` (`user_id`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

    ");

}



// ──────────────────────────────────────────────

// Core: create a notification

// ──────────────────────────────────────────────

function createNotification(

    $conn,

    int    $user_id,

    string $type,

    string $title,

    string $message,

    string $priority      = NOTIF_PRIORITY_MEDIUM,

    ?int   $reference_id  = null,

    ?string $reference_type = null

): bool {

    // Avoid duplicates: skip if identical unread notification exists (same user+type+ref)

    if ($reference_id && $reference_type) {

        $check = $conn->prepare("

            SELECT notification_id FROM notifications

            WHERE user_id = ? AND type = ? AND reference_id = ?

              AND reference_type = ? AND is_read = 0 AND is_dismissed = 0

            LIMIT 1

        ");

        $check->bind_param('isis', $user_id, $type, $reference_id, $reference_type);

        $check->execute();

        if ($check->get_result()->num_rows > 0) return false;

    }



    $stmt = $conn->prepare("

        INSERT INTO notifications

            (user_id, type, priority, title, message, reference_id, reference_type)

        VALUES (?, ?, ?, ?, ?, ?, ?)

    ");

    $stmt->bind_param('issssis', $user_id, $type, $priority, $title, $message, $reference_id, $reference_type);

    return $stmt->execute();

}



// ──────────────────────────────────────────────

// Fetch notifications for a user

// ──────────────────────────────────────────────

function getNotifications(

    $conn,

    int  $user_id,

    bool $unread_only = false,

    int  $limit       = 50,

    int  $offset      = 0

): array {

    // Get user preferences first

    $prefs = getNotificationPreferences($conn, $user_id);

    // Build enabled types array

    $enabled_types = [];

    if ($prefs['expiry_enabled']) $enabled_types[] = 'expiry';

    if ($prefs['low_stock_enabled']) $enabled_types[] = 'low_stock';

    if ($prefs['achievement_enabled']) $enabled_types[] = 'achievement';

    if ($prefs['system_enabled']) $enabled_types[] = 'system';

    // If no types enabled, return empty array

    if (empty($enabled_types)) {

        return [];

    }

    $types_placeholders = str_repeat('?,', count($enabled_types) - 1) . '?';

    $where_types = "AND type IN ($types_placeholders)";

    $where = $unread_only

        ? "WHERE user_id = ? AND is_read = 0 AND is_dismissed = 0 $where_types"

        : "WHERE user_id = ? AND is_dismissed = 0 $where_types";

    $stmt = $conn->prepare("

        SELECT * FROM notifications

        $where

        ORDER BY

            FIELD(priority, 'high', 'medium', 'low'),

            created_at DESC

        LIMIT ? OFFSET ?

    ");

    // Merge parameters: user_id + enabled_types + limit + offset

    $params = array_merge([$user_id], $enabled_types, [$limit, $offset]);

    $types = 'i' . str_repeat('s', count($enabled_types)) . 'ii'; // user_id(i), types(s), limit(i), offset(i)

    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

}



function countUnreadNotifications($conn, int $user_id): int {

    // Get user preferences first
    $prefs = getNotificationPreferences($conn, $user_id);
    
    // Build enabled types array
    $enabled_types = [];
    if ($prefs['expiry_enabled']) $enabled_types[] = 'expiry';
    if ($prefs['low_stock_enabled']) $enabled_types[] = 'low_stock';
    if ($prefs['achievement_enabled']) $enabled_types[] = 'achievement';
    if ($prefs['system_enabled']) $enabled_types[] = 'system';
    
    // If no types enabled, return 0
    if (empty($enabled_types)) {
        return 0;
    }
    
    $types_placeholders = str_repeat('?,', count($enabled_types) - 1) . '?';
    $where_types = "AND type IN ($types_placeholders)";

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM notifications
        WHERE user_id = ? AND is_read = 0 AND is_dismissed = 0 $where_types
    ");

    // Merge parameters: user_id + enabled_types
    $params = array_merge([$user_id], $enabled_types);
    $types = 'i' . str_repeat('s', count($enabled_types)); // user_id(i), types(s)
    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['cnt'];

}



function markNotificationRead($conn, int $notification_id, int $user_id): bool {

    $stmt = $conn->prepare("

        UPDATE notifications SET is_read = 1

        WHERE notification_id = ? AND user_id = ?

    ");

    $stmt->bind_param('ii', $notification_id, $user_id);

    return $stmt->execute();

}



function markAllNotificationsRead($conn, int $user_id): bool {

    $stmt = $conn->prepare("

        UPDATE notifications SET is_read = 1

        WHERE user_id = ? AND is_read = 0

    ");

    $stmt->bind_param('i', $user_id);

    return $stmt->execute();

}



function dismissNotification($conn, int $notification_id, int $user_id): bool {

    $stmt = $conn->prepare("

        UPDATE notifications SET is_dismissed = 1

        WHERE notification_id = ? AND user_id = ?

    ");

    $stmt->bind_param('ii', $notification_id, $user_id);

    return $stmt->execute();

}



// ──────────────────────────────────────────────

// Preferences helpers

// ──────────────────────────────────────────────

function getNotificationPreferences($conn, int $user_id): array {

    $stmt = $conn->prepare("

        SELECT * FROM notification_preferences WHERE user_id = ?

    ");

    $stmt->bind_param('i', $user_id);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();



    // Return defaults if no row yet

    return $row ?: [

        'user_id'             => $user_id,

        'expiry_enabled'      => 1,

        'expiry_days_before'  => 7,

        'low_stock_enabled'   => 1,

        'achievement_enabled' => 1,

        'system_enabled'      => 1,

        'group_notifications_enabled' => 1,

        'email_enabled'       => 0,

    ];

}



function saveNotificationPreferences($conn, int $user_id, array $prefs): bool {

    $expiry_enabled      = ($prefs['expiry_enabled'] ?? '0') === '1' ? 1 : 0;

    $expiry_days_before  = (int) ($prefs['expiry_days_before'] ?? 7);

    $low_stock_enabled   = ($prefs['low_stock_enabled'] ?? '0') === '1' ? 1 : 0;

    $achievement_enabled = ($prefs['achievement_enabled'] ?? '0') === '1' ? 1 : 0;

    $system_enabled      = ($prefs['system_enabled'] ?? '0') === '1' ? 1 : 0;

    $group_notifications_enabled = ($prefs['group_notifications_enabled'] ?? '0') === '1' ? 1 : 0;

    $email_enabled       = ($prefs['email_enabled'] ?? '0') === '1' ? 1 : 0;



    $stmt = $conn->prepare("

        INSERT INTO notification_preferences

            (user_id, expiry_enabled, expiry_days_before, low_stock_enabled,

             achievement_enabled, system_enabled, group_notifications_enabled, email_enabled)

        VALUES (?, ?, ?, ?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE

            expiry_enabled      = VALUES(expiry_enabled),

            expiry_days_before  = VALUES(expiry_days_before),

            low_stock_enabled   = VALUES(low_stock_enabled),

            achievement_enabled = VALUES(achievement_enabled),

            system_enabled      = VALUES(system_enabled),

            group_notifications_enabled = VALUES(group_notifications_enabled),

            email_enabled       = VALUES(email_enabled)

    ");

    $stmt->bind_param('iiiiiiii',

        $user_id, $expiry_enabled, $expiry_days_before,

        $low_stock_enabled, $achievement_enabled, $system_enabled, $group_notifications_enabled, $email_enabled

    );

    return $stmt->execute();

}



// ──────────────────────────────────────────────

// Master runner: generate all pending alerts

// ──────────────────────────────────────────────

function runNotificationChecks($conn, int $user_id): array {

    ensureNotificationsTable($conn);

    require_once __DIR__ . '/expiry_alerts.php';



    $prefs   = getNotificationPreferences($conn, $user_id);

    $results = ['expiry' => 0, 'low_stock' => 0, 'achievement' => 0, 'system' => 0];



    // Determine if this user is a grocery_admin or customer

    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");

    $stmt->bind_param('i', $user_id);

    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    $role = $user['role'] ?? 'customer';



    if ($prefs['expiry_enabled']) {

        $results['expiry'] = generateExpiryNotifications(

            $conn, $user_id, $role, (int) $prefs['expiry_days_before']

        );

    }



    if ($prefs['low_stock_enabled']) {

        if ($role === 'grocery_admin') {

            $results['low_stock'] = generateLowStockNotifications($conn, $user_id);

        } else {

            $results['low_stock'] = generateCustomerLowStockNotifications($conn, $user_id);

        }

    }



    if ($prefs['achievement_enabled']) {

        if ($role === 'grocery_admin') {
            // For grocery admin, achievement notifications include supplier alerts
            $results['achievement'] = generateAchievementNotifications($conn, $user_id) + 
                                    generateSupplierNotifications($conn, $user_id);
        } else {
            $results['achievement'] = generateAchievementNotifications($conn, $user_id);
        }

    }



    if ($prefs['system_enabled']) {

        if ($role === 'grocery_admin') {
            // For grocery admin, system notifications include purchase order updates
            $results['system'] = generateSystemNotifications($conn, $user_id) + 
                               generatePurchaseOrderNotifications($conn, $user_id);
        } else {
            $results['system'] = generateSystemNotifications($conn, $user_id);
        }

    }



    return $results;

}


// ──────────────────────────────────────────────

// Purchase Order notifications
// ──────────────────────────────────────────────

function generatePurchaseOrderNotifications($conn, int $user_id): int {
    $count = 0;
    
    // Get the store_id for this grocery admin
    $store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
    $store_stmt->bind_param('i', $user_id);
    $store_stmt->execute();
    $store = $store_stmt->get_result()->fetch_assoc();
    
    if (!$store) return $count;
    
    $store_id = $store['store_id'];
    
    // Check for recently created purchase orders (last 24 hours)
    $recent_pos = $conn->prepare("
        SELECT po.po_id, po.po_number, po.status, po.created_at,
               COUNT(poi.po_item_id) as item_count
        FROM purchase_orders po
        LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
        WHERE po.store_id = ? 
          AND po.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND po.status IN ('submitted', 'confirmed', 'partially_received', 'received')
        GROUP BY po.po_id
        ORDER BY po.created_at DESC
        LIMIT 10
    ");
    $recent_pos->bind_param('i', $store_id);
    $recent_pos->execute();
    
    foreach ($recent_pos->get_result()->fetch_all(MYSQLI_ASSOC) as $po) {
        // Check if we already notified about this PO
        $notified_check = $conn->prepare("
            SELECT 1 FROM notifications 
            WHERE user_id = ? AND type = 'system' 
              AND reference_id = ? AND reference_type = 'purchase_order'
            LIMIT 1
        ");
        $notified_check->bind_param('ii', $user_id, $po['po_id']);
        $notified_check->execute();
        
        if ($notified_check->get_result()->num_rows === 0) {
            $status_text = ucfirst($po['status']);
            $created = createNotification(
                $conn, $user_id,
                NOTIF_TYPE_SYSTEM,
                '📦 Purchase Order ' . $status_text . ': ' . $po['po_number'],
                'Purchase Order ' . $po['po_number'] . ' is ' . $status_text . 
                ' with ' . $po['item_count'] . ' items.',
                NOTIF_PRIORITY_MEDIUM,
                $po['po_id'],
                'purchase_order'
            );
            if ($created) $count++;
        }
    }
    
    return $count;
}
// ──────────────────────────────────────────────
// Supplier notifications
// ──────────────────────────────────────────────

function generateSupplierNotifications($conn, int $user_id): int {
    $count = 0;
    
    // Get the store_id for this grocery admin
    $store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
    $store_stmt->bind_param('i', $user_id);
    $store_stmt->execute();
    $store = $store_stmt->get_result()->fetch_assoc();
    
    if (!$store) return $count;
    
    $store_id = $store['store_id'];
    
    // Check for recently added suppliers (last 7 days)
    $recent_suppliers = $conn->prepare("
        SELECT s.supplier_id, s.supplier_name, s.created_at
        FROM suppliers s
        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $recent_suppliers->execute();
    
    foreach ($recent_suppliers->get_result()->fetch_all(MYSQLI_ASSOC) as $supplier) {
        // Check if we already notified about this supplier
        $notified_check = $conn->prepare("
            SELECT 1 FROM notifications 
            WHERE user_id = ? AND type = 'achievement' 
              AND reference_id = ? AND reference_type = 'supplier'
            LIMIT 1
        ");
        $notified_check->bind_param('ii', $user_id, $supplier['supplier_id']);
        $notified_check->execute();
        
        if ($notified_check->get_result()->num_rows === 0) {
            $created = createNotification(
                $conn, $user_id,
                NOTIF_TYPE_ACHIEVEMENT,
                '🤝 New Supplier Added: ' . $supplier['supplier_name'],
                'New supplier "' . $supplier['supplier_name'] . '" has been added to the system.',
                NOTIF_PRIORITY_LOW,
                $supplier['supplier_id'],
                'supplier'
            );
            if ($created) $count++;
        }
    }
    
    // Check for low stock items that need reordering (supplier alerts)
    $reorder_alerts = $conn->prepare("
        SELECT gi.item_id, gi.item_name, gi.quantity, gi.unit,
               gi.reorder_level, gi.reorder_quantity,
               s.supplier_name, s.supplier_id
        FROM grocery_items gi
        LEFT JOIN suppliers s ON gi.supplier_id = s.supplier_id
        WHERE gi.store_id = ?
          AND gi.quantity <= gi.reorder_level
          AND gi.supplier_id IS NOT NULL
        ORDER BY gi.quantity ASC
        LIMIT 5
    ");
    $reorder_alerts->bind_param('i', $store_id);
    $reorder_alerts->execute();
    
    foreach ($reorder_alerts->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
        // Create a unique reference for this reorder alert
        $ref_id = 'reorder_' . $item['item_id'] . '_' . date('Y-m-d');
        
        // Check if we already notified about this reorder need today
        $notified_check = $conn->prepare("
            SELECT 1 FROM notifications 
            WHERE user_id = ? AND type = 'achievement' 
              AND reference_type = 'reorder_alert'
              AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        $notified_check->bind_param('i', $user_id);
        $notified_check->execute();
        
        if ($notified_check->get_result()->num_rows === 0) {
            $created = createNotification(
                $conn, $user_id,
                NOTIF_TYPE_ACHIEVEMENT,
                '📋 Reorder Needed: ' . $item['item_name'],
                $item['item_name'] . ' needs reordering. Current stock: ' . $item['quantity'] . ' ' . $item['unit'] .
                '. Suggested: ' . $item['reorder_quantity'] . ' ' . $item['unit'] .
                ($item['supplier_name'] ? ' from ' . $item['supplier_name'] : ''),
                NOTIF_PRIORITY_MEDIUM,
                $item['item_id'],
                'reorder_alert'
            );
            if ($created) $count++;
        }
    }
    
    return $count;
}

// ──────────────────────────────────────────────
// Achievement notifications (badges + points)
// ──────────────────────────────────────────────

function generateAchievementNotifications($conn, int $user_id): int {

    $count = 0;



    // Check for newly earned badges not yet notified

    $stmt = $conn->prepare("

        SELECT ub.user_badge_id, b.badge_name, b.badge_description, b.points_required

        FROM user_badges ub

        JOIN badges b ON ub.badge_id = b.badge_id

        WHERE ub.user_id = ?

          AND NOT EXISTS (

              SELECT 1 FROM notifications n

              WHERE n.user_id = ? AND n.type = 'achievement'

                AND n.reference_id = ub.user_badge_id

                AND n.reference_type = 'badge'

          )

        ORDER BY ub.unlocked_at DESC

    ");

    $stmt->bind_param('ii', $user_id, $user_id);

    $stmt->execute();

    $badges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



    foreach ($badges as $badge) {

        $created = createNotification(

            $conn,

            $user_id,

            NOTIF_TYPE_ACHIEVEMENT,

            '🏆 Badge Unlocked: ' . $badge['badge_name'],

            $badge['badge_description'] . ' (' . $badge['points_required'] . ' pts required)',

            NOTIF_PRIORITY_MEDIUM,

            $badge['user_badge_id'],

            'badge'

        );

        if ($created) $count++;

    }



    // Points milestone check

    $pts_stmt = $conn->prepare("

        SELECT total_points FROM user_points WHERE user_id = ?

    ");

    $pts_stmt->bind_param('i', $user_id);

    $pts_stmt->execute();

    $pts_row = $pts_stmt->get_result()->fetch_assoc();



    if ($pts_row) {

        $milestones = [100, 250, 500, 1000, 2500, 5000];

        foreach ($milestones as $milestone) {

            if ($pts_row['total_points'] >= $milestone) {

                // Check if this milestone was already notified

                $mcheck = $conn->prepare("

                    SELECT 1 FROM notifications

                    WHERE user_id = ? AND type = 'achievement'

                      AND reference_id = ? AND reference_type = 'points_milestone'

                    LIMIT 1

                ");

                $mcheck->bind_param('ii', $user_id, $milestone);

                $mcheck->execute();

                if ($mcheck->get_result()->num_rows === 0) {

                    createNotification(

                        $conn, $user_id, NOTIF_TYPE_ACHIEVEMENT,

                        '⭐ ' . number_format($milestone) . ' Points Reached!',

                        'You\'ve accumulated ' . number_format($milestone) . ' tracking points. Keep up the great work!',

                        NOTIF_PRIORITY_LOW,

                        $milestone,

                        'points_milestone'

                    );

                    $count++;

                }

            }

        }

    }

    return $count;

}


// ──────────────────────────────────────────────

// System notifications (group invites, updates, etc.)

// ──────────────────────────────────────────────

function generateSystemNotifications($conn, int $user_id): int {

    $count = 0;

    

    // For now, system notifications are minimal
    // Can be extended later for other system events
    
    // Example: Welcome message for new users (only once)
    $welcome_check = $conn->prepare("
        SELECT 1 FROM notifications 
        WHERE user_id = ? AND type = 'system' AND reference_type = 'welcome'
        LIMIT 1
    ");
    $welcome_check->bind_param('i', $user_id);
    $welcome_check->execute();
    
    if ($welcome_check->get_result()->num_rows === 0) {
        // Get user info for personalized welcome
        $user_stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $user_stmt->bind_param('i', $user_id);
        $user_stmt->execute();
        $user_info = $user_stmt->get_result()->fetch_assoc();
        
        if ($user_info) {
            $created = createNotification(
                $conn, $user_id,
                NOTIF_TYPE_SYSTEM,
                '� Welcome to StockFlow!',
                'Welcome ' . $user_info['full_name'] . '! Start tracking your inventory and never waste food again.',
                NOTIF_PRIORITY_LOW,
                $user_id,
                'welcome'
            );
            if ($created) $count++;
        }
    }

    return $count;

}


// ──────────────────────────────────────────────

// Notification bell HTML widget (include in nav)

// ──────────────────────────────────────────────

function renderNotificationBell($conn, int $user_id): string {
    $unread = countUnreadNotifications($conn, $user_id);
    $badge  = $unread > 0
        ? '<span class="notif-badge">' . ($unread > 99 ? '99+' : $unread) . '</span>'
        : '';

    // Determine the correct notification settings path based on user role
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $role = $user['role'] ?? 'customer';
    
    $notification_path = ($role === 'grocery_admin') 
        ? '/grocery/notification_settings.php' 
        : '/user/notification_settings.php';

    return '
    <div class="notif-bell-wrapper">
        <a href="' . htmlspecialchars($GLOBALS['baseUrl'] ?? '') . $notification_path . '" class="notif-bell-btn" id="notifBellBtn" aria-label="Notifications">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            ' . $badge . '
        </a>
    </div>';
}