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
    $stmt->bind_param('issssisi', $user_id, $type, $priority, $title, $message, $reference_id, $reference_type);
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
    $where = $unread_only
        ? 'WHERE user_id = ? AND is_read = 0 AND is_dismissed = 0'
        : 'WHERE user_id = ? AND is_dismissed = 0';

    $stmt = $conn->prepare("
        SELECT * FROM notifications
        $where
        ORDER BY
            FIELD(priority, 'high', 'medium', 'low'),
            created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $user_id, $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function countUnreadNotifications($conn, int $user_id): int {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM notifications
        WHERE user_id = ? AND is_read = 0 AND is_dismissed = 0
    ");
    $stmt->bind_param('i', $user_id);
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
        'email_enabled'       => 0,
    ];
}

function saveNotificationPreferences($conn, int $user_id, array $prefs): bool {
    $expiry_enabled      = (int) ($prefs['expiry_enabled']      ?? 1);
    $expiry_days_before  = (int) ($prefs['expiry_days_before']  ?? 7);
    $low_stock_enabled   = (int) ($prefs['low_stock_enabled']   ?? 1);
    $achievement_enabled = (int) ($prefs['achievement_enabled'] ?? 1);
    $system_enabled      = (int) ($prefs['system_enabled']      ?? 1);
    $email_enabled       = (int) ($prefs['email_enabled']       ?? 0);

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
    return $stmt->execute();
}

// ──────────────────────────────────────────────
// Master runner: generate all pending alerts
// ──────────────────────────────────────────────
function runNotificationChecks($conn, int $user_id): array {
    ensureNotificationsTable($conn);
    require_once __DIR__ . '/expiry_alerts.php';

    $prefs   = getNotificationPreferences($conn, $user_id);
    $results = ['expiry' => 0, 'low_stock' => 0, 'achievement' => 0];

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

    if ($prefs['low_stock_enabled'] && $role === 'grocery_admin') {
        $results['low_stock'] = generateLowStockNotifications($conn, $user_id);
    }

    if ($prefs['achievement_enabled']) {
        $results['achievement'] = generateAchievementNotifications($conn, $user_id);
    }

    return $results;
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
// Notification bell HTML widget (include in nav)
// ──────────────────────────────────────────────
function renderNotificationBell($conn, int $user_id): string {
    $unread = countUnreadNotifications($conn, $user_id);
    $badge  = $unread > 0
        ? '<span class="notif-badge">' . ($unread > 99 ? '99+' : $unread) . '</span>'
        : '';

    return '
    <div class="notif-bell-wrapper">
        <a href="' . htmlspecialchars($GLOBALS['baseUrl'] ?? '') . '/user/notification_settings.php" class="notif-bell-btn" id="notifBellBtn" aria-label="Notifications">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            ' . $badge . '
        </a>
    </div>';
}