<?php
/**
 * Notification Bell Widget
 * Include in navigation/header of all user pages
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notifications_system.php';

if (!empty($_SESSION['user_id'])) {
    echo renderNotificationBell($conn, (int)$_SESSION['user_id']);
}
?>
