<?php

/**

 * Grocery Admin Notification Settings & Inbox

 * Route: /grocery/notification_settings.php

 */



session_start();

require_once '../includes/config.php';

require_once '../includes/admin_auth_check.php';

require_once '../includes/notifications_system.php';

require_once '../includes/expiry_alerts.php';



// ── Auth guard ──────────────────────────────────────────────────────────────

if (empty($_SESSION['user_id'])) {

    header('Location: /grocery/grocery_login.php');

    exit;

}

$user_id = (int) $_SESSION['user_id'];



// Initialize database connection

$conn = getDBConnection();

ensureNotificationsTable($conn);



// ── Verify grocery_admin role ────────────────────────────────────────────────

$role_stmt = $conn->prepare("SELECT role, full_name FROM users WHERE user_id = ?");

$role_stmt->bind_param('i', $user_id);

$role_stmt->execute();

$current_user = $role_stmt->get_result()->fetch_assoc();

$role         = $current_user['role'] ?? '';



if ($role !== 'grocery_admin') {

    header('Location: /user/notification_settings.php');

    exit;

}



// ── AJAX handlers ────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json');



    switch ($_POST['action']) {

        case 'mark_read':

            $nid = (int) ($_POST['notification_id'] ?? 0);

            echo json_encode(['success' => markNotificationRead($conn, $nid, $user_id)]);

            exit;



        case 'mark_all_read':

            echo json_encode(['success' => markAllNotificationsRead($conn, $user_id)]);

            exit;



        case 'dismiss':

            $nid = (int) ($_POST['notification_id'] ?? 0);

            echo json_encode(['success' => dismissNotification($conn, $nid, $user_id)]);

            exit;



        case 'run_checks':

            $results = runNotificationChecks($conn, $user_id);

            echo json_encode(['success' => true, 'results' => $results]);

            exit;



        case 'save_preferences':

            $ok = saveNotificationPreferences($conn, $user_id, [
                'expiry_enabled'      => ($_POST['expiry_enabled'] ?? '0') === '1' ? '1' : '0',
                'expiry_days_before'  => max(1, min(30, (int) ($_POST['expiry_days_before'] ?? 7))),
                'low_stock_enabled'   => ($_POST['low_stock_enabled'] ?? '0') === '1' ? '1' : '0',
                'achievement_enabled' => ($_POST['achievement_enabled'] ?? '0') === '1' ? '1' : '0',
                'system_enabled'      => ($_POST['system_enabled'] ?? '0') === '1' ? '1' : '0',
                'group_notifications_enabled' => '0', // Grocery admin doesn't have this field
                'email_enabled'       => '0', // Grocery admin doesn't have this field
            ]);

            echo json_encode(['success' => $ok]);

            exit;

    }

}



// ── Regular page load ────────────────────────────────────────────────────────

runNotificationChecks($conn, $user_id);



$prefs          = getNotificationPreferences($conn, $user_id);

$notifications  = getNotifications($conn, $user_id, false, 50);

$unread_count   = countUnreadNotifications($conn, $user_id);

$expiry_summary = getExpirySummary($conn, $user_id, $role);



// Active filter — admin-specific

$filter        = $_GET['filter'] ?? 'all';

$valid_filters = ['all', 'unread', 'low_stock', 'expiry', 'system'];

if (!in_array($filter, $valid_filters)) $filter = 'all';



$filtered = array_filter($notifications, function($n) use ($filter) {

    if ($filter === 'all')    return true;

    if ($filter === 'unread') return !$n['is_read'];

    return $n['type'] === $filter;

});



// ── Helpers ──────────────────────────────────────────────────────────────────

function typeIcon(string $type): string {

    return match($type) {

        'expiry'      => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',

        'low_stock'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',

        'achievement' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>',

        default       => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',

    };

}

function priorityClass(string $p): string {

    return match($p) { 'high' => 'priority-high', 'low' => 'priority-low', default => 'priority-medium' };

}

function timeAgo(string $ts): string {

    $diff = time() - strtotime($ts);

    if ($diff < 60)     return 'Just now';

    if ($diff < 3600)   return floor($diff / 60) . 'm ago';

    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';

    if ($diff < 604800) return floor($diff / 86400) . 'd ago';

    return date('M j', strtotime($ts));

}

?>



<?php require_once '../includes/header.php'; ?>



<style>

    /* ── Base ──────────────────────────────────────────────────────────────── */

    .catalog-page { padding: 100px 30px 60px; background: #fafafa; min-height: 100vh; }

    .catalog-container { max-width: 1400px; margin: 0 auto; }



    .page-header { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); padding: 40px 45px; margin-bottom: 30px; }

    .header-content { display: flex; align-items: flex-start; justify-content: space-between; gap: 30px; }

    .header-info { flex: 1; }

    .header-actions { display: flex; gap: 12px; align-items: center; }



    /* Breadcrumb */

    .breadcrumb-nav { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 11px; letter-spacing: 0.5px; }

    .breadcrumb-link { color: rgba(0,0,0,0.45); text-decoration: none; transition: color 0.2s; }

    .breadcrumb-link:hover { color: #0a0a0a; }

    .breadcrumb-separator { color: rgba(0,0,0,0.25); }

    .breadcrumb-current { color: #0a0a0a; font-weight: 500; }



    .page-title { font-family: 'Playfair Display', serif; font-size: 36px; font-weight: 400; color: #0a0a0a; margin: 0 0 6px; letter-spacing: -0.5px; }

    .page-subtitle { font-size: 13px; color: rgba(0,0,0,0.5); letter-spacing: 0.3px; margin: 0; }



    /* Buttons */

    .btn-primary { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #0a0a0a; color: #ffffff; text-decoration: none; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; font-weight: 500; border: 1px solid #0a0a0a; transition: all 0.3s ease; cursor: pointer; font-family: 'Montserrat', sans-serif; }

    .btn-primary:hover { background: transparent; color: #0a0a0a; }

    .btn-secondary { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #fafafa; color: #0a0a0a; text-decoration: none; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; font-weight: 500; border: 1px solid rgba(0,0,0,0.15); transition: all 0.3s ease; cursor: pointer; font-family: 'Montserrat', sans-serif; }

    .btn-secondary:hover { background: #0a0a0a; color: #ffffff; border-color: #0a0a0a; }



    /* ── Summary Cards ─────────────────────────────────────────────────────── */

    .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 30px; }

    .summary-card { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); padding: 28px 24px; transition: all 0.3s ease; cursor: default; }

    .summary-card:hover { border-color: rgba(0,0,0,0.15); box-shadow: 0 6px 20px rgba(0,0,0,0.06); transform: translateY(-2px); }

    .summary-card.card-danger  { border-left: 3px solid #dc2626; }

    .summary-card.card-warning { border-left: 3px solid #f59e0b; }

    .summary-card.card-info    { border-left: 3px solid #7ed957; }

    .summary-card.card-neutral { border-left: 3px solid #0a0a0a; }

    .card-label { font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.5); margin-bottom: 10px; }

    .card-value { font-family: 'Playfair Display', serif; font-size: 36px; font-weight: 400; color: #0a0a0a; line-height: 1; }

    .card-sub   { font-size: 11px; color: rgba(0,0,0,0.4); margin-top: 6px; letter-spacing: 0.3px; }



    /* ── Two-column layout ─────────────────────────────────────────────────── */

    .main-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }



    /* ── Filter Tabs ───────────────────────────────────────────────────────── */

    .filters-section { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); margin-bottom: 20px; overflow: hidden; }

    .filter-tabs { display: flex; border-bottom: 1px solid rgba(0,0,0,0.08); }

    .filter-tab { flex: 1; padding: 14px 10px; text-align: center; font-size: 10px; letter-spacing: 1.2px; text-transform: uppercase; font-weight: 500; text-decoration: none; color: rgba(0,0,0,0.5); border-right: 1px solid rgba(0,0,0,0.06); transition: all 0.2s ease; cursor: pointer; background: none; border-bottom: none; font-family: 'Montserrat', sans-serif; }

    .filter-tab:last-child { border-right: none; }

    .filter-tab:hover { background: #fafafa; color: #0a0a0a; }

    .filter-tab.active { background: #0a0a0a; color: #ffffff; }

    .filter-tab .tab-count { display: inline-block; min-width: 18px; height: 18px; line-height: 18px; padding: 0 5px; background: rgba(255,255,255,0.2); font-size: 9px; margin-left: 5px; }

    .filter-tab:not(.active) .tab-count { background: rgba(0,0,0,0.08); color: #0a0a0a; }



    /* ── Notification Items ────────────────────────────────────────────────── */

    .notif-item { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); margin-bottom: 8px; display: flex; transition: all 0.25s ease; overflow: hidden; }

    .notif-item:hover { border-color: rgba(0,0,0,0.15); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

    .notif-item.unread { border-left: 3px solid #0a0a0a; }

    .notif-item.unread.priority-high { border-left-color: #dc2626; }

    .notif-item.unread.priority-medium { border-left-color: #7ed957; }



    .notif-icon-col { width: 52px; display: flex; align-items: flex-start; justify-content: center; padding: 18px 0; flex-shrink: 0; }

    .notif-type-dot { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; }

    .notif-type-dot.type-expiry      { color: #dc2626; background: #fef2f2; }

    .notif-type-dot.type-low_stock   { color: #f59e0b; background: #fef3c7; }

    .notif-type-dot.type-achievement { color: #7ed957; background: #f0fdf4; }

    .notif-type-dot.type-system      { color: #666;    background: #f4f4f5; }



    .notif-body { flex: 1; padding: 16px 16px 16px 0; min-width: 0; }

    .notif-title { font-size: 13px; font-weight: 500; color: #0a0a0a; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .notif-message { font-size: 12px; color: rgba(0,0,0,0.55); line-height: 1.5; }

    .notif-meta { display: flex; align-items: center; gap: 10px; margin-top: 8px; }

    .notif-time { font-size: 10px; color: rgba(0,0,0,0.35); letter-spacing: 0.3px; }

    .priority-tag { font-size: 9px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; padding: 2px 7px; }

    .priority-tag.priority-high   { background: #fef2f2; color: #dc2626; }

    .priority-tag.priority-medium { background: #f0fdf4; color: #166534; }

    .priority-tag.priority-low    { background: #f4f4f5; color: #666; }



    .notif-actions-col { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 12px 14px; flex-shrink: 0; }

    .btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #fafafa; border: 1px solid rgba(0,0,0,0.12); cursor: pointer; transition: all 0.2s ease; }

    .btn-icon:hover { background: #0a0a0a; border-color: #0a0a0a; }

    .btn-icon:hover svg { stroke: #ffffff; }

    .btn-icon.btn-dismiss:hover { background: #dc2626; border-color: #dc2626; }

    .unread-dot { width: 7px; height: 7px; border-radius: 50%; background: #0a0a0a; }



    /* ── Empty State ───────────────────────────────────────────────────────── */

    .empty-state { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); padding: 80px 40px; text-align: center; }

    .empty-icon { width: 100px; height: 100px; margin: 0 auto 25px; background: #fafafa; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

    .empty-icon svg { stroke: rgba(0,0,0,0.3); }

    .empty-title { font-family: 'Playfair Display', serif; font-size: 24px; font-weight: 400; color: #0a0a0a; margin-bottom: 10px; }

    .empty-description { font-size: 13px; color: rgba(0,0,0,0.5); letter-spacing: 0.3px; max-width: 400px; margin: 0 auto; line-height: 1.8; }



    /* ── Settings Panel ────────────────────────────────────────────────────── */

    .settings-panel { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); }

    .panel-header { padding: 24px 28px; border-bottom: 1px solid rgba(0,0,0,0.07); }

    .panel-title { font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 400; color: #0a0a0a; }

    .panel-subtitle { font-size: 11px; color: rgba(0,0,0,0.45); letter-spacing: 0.3px; margin-top: 3px; }

    .panel-body { padding: 24px 28px; }



    .settings-group { margin-bottom: 28px; }

    .settings-group:last-child { margin-bottom: 0; }

    .settings-group-label { font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.4); margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid rgba(0,0,0,0.06); }



    .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.04); }

    .toggle-row:last-child { border-bottom: none; }

    .toggle-label { font-size: 13px; color: #0a0a0a; }

    .toggle-desc  { font-size: 11px; color: rgba(0,0,0,0.4); margin-top: 1px; }



    /* Toggle switch */

    .toggle-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; }

    .toggle-switch input { opacity: 0; width: 0; height: 0; }

    .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: rgba(0,0,0,0.15); transition: 0.3s; border-radius: 22px; }

    .toggle-slider:before { content: ''; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: white; transition: 0.3s; border-radius: 50%; }

    input:checked + .toggle-slider { background: #0a0a0a; }

    input:checked + .toggle-slider:before { transform: translateX(18px); }



    /* Days input */

    .days-input-row { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding: 14px 0 0; border-top: 1px solid rgba(0,0,0,0.05); }

    .days-input-row label { font-size: 12px; color: rgba(0,0,0,0.6); white-space: nowrap; }

    .days-input { width: 60px; padding: 6px 10px; border: 1px solid rgba(0,0,0,0.15); background: #fafafa; font-family: 'Montserrat', sans-serif; font-size: 13px; text-align: center; color: #0a0a0a; }

    .days-input:focus { outline: none; border-color: #0a0a0a; background: #fff; }

    .days-unit { font-size: 12px; color: rgba(0,0,0,0.5); }



    .btn-save { width: 100%; justify-content: center; margin-top: 20px; padding: 12px; font-size: 11px; }



    /* Expiry mini-chart */

    .expiry-breakdown { margin-bottom: 28px; }

    .breakdown-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.04); }

    .breakdown-row:last-child { border-bottom: none; }

    .breakdown-bar-wrap { flex: 1; height: 4px; background: rgba(0,0,0,0.06); }

    .breakdown-bar { height: 4px; transition: width 0.6s ease; }

    .breakdown-label { font-size: 11px; color: rgba(0,0,0,0.5); width: 80px; flex-shrink: 0; }

    .breakdown-count { font-size: 12px; font-weight: 500; color: #0a0a0a; width: 24px; text-align: right; flex-shrink: 0; }



    /* ── Toast ─────────────────────────────────────────────────────────────── */

    .toast-container { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }

    .toast { padding: 14px 20px; background: #0a0a0a; color: #ffffff; font-size: 12px; letter-spacing: 0.3px; display: flex; align-items: center; gap: 10px; max-width: 320px; animation: slideUp 0.3s ease; }

    .toast.toast-success { border-left: 3px solid #7ed957; }

    .toast.toast-error   { border-left: 3px solid #dc2626; }

    @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }



    /* ── Responsive ────────────────────────────────────────────────────────── */

    @media (max-width: 1100px) {

        .main-grid { grid-template-columns: 1fr; }

        .settings-panel { order: -1; }

        .summary-grid { grid-template-columns: repeat(2, 1fr); }

    }

    @media (max-width: 768px) {

        .catalog-page { padding: 80px 20px 50px; }

        .page-header { padding: 30px 25px; }

        .header-content { flex-direction: column; align-items: flex-start; }

        .header-actions { width: 100%; flex-direction: column; }

        .btn-primary, .btn-secondary { width: 100%; justify-content: center; }

        .page-title { font-size: 26px; }

        .filter-tabs { overflow-x: auto; }

        .filter-tab { white-space: nowrap; flex: none; padding: 12px 14px; }

    }

    @media (max-width: 480px) {

        .summary-grid { grid-template-columns: 1fr 1fr; gap: 10px; }

        .card-value { font-size: 28px; }

        .page-title { font-size: 22px; }

    }

</style>



<div class="catalog-page">

<div class="catalog-container">



    <!-- ── Page Header ──────────────────────────────────────────────────────── -->

    <div class="page-header">

        <div class="header-content">

            <div class="header-info">

                <nav class="breadcrumb-nav">

                    <a href="<?= htmlspecialchars($baseUrl) ?>/grocery/grocery_dashboard.php" class="breadcrumb-link">Dashboard</a>

                    <span class="breadcrumb-separator">/</span>

                    <span class="breadcrumb-current">Notifications</span>

                </nav>

                <h1 class="page-title">Store Notifications</h1>

                <p class="page-subtitle">

                    Store management alerts, inventory &amp; supplier updates

                    <?php if ($unread_count > 0): ?>

                        &mdash; <strong><?= $unread_count ?> unread</strong>

                    <?php endif; ?>

                </p>

            </div>

            <div class="header-actions">

                <?php if ($unread_count > 0): ?>

                <button class="btn-secondary" id="btnMarkAllRead">

                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>

                    Mark All Read

                </button>

                <?php endif; ?>

                <button class="btn-primary" id="btnRefresh">

                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>

                    Refresh Alerts

                </button>

            </div>

        </div>

    </div>



    <!-- ── Summary Cards ────────────────────────────────────────────────────── -->

    <div class="summary-grid">

        <div class="summary-card card-danger">

            <div class="card-label">Store Items Expired</div>

            <div class="card-value"><?= $expiry_summary['expired'] ?></div>

            <div class="card-sub">Requires immediate attention</div>

        </div>

        <div class="summary-card card-warning">

            <div class="card-label">Low Stock Items</div>

            <div class="card-value"><?= $expiry_summary['today'] ?></div>

            <div class="card-sub">Below reorder level</div>

        </div>

        <div class="summary-card card-info">

            <div class="card-label">Expiring This Week</div>

            <div class="card-value"><?= $expiry_summary['within_3'] + $expiry_summary['within_7'] ?></div>

            <div class="card-sub">Store inventory alert</div>

        </div>

        <div class="summary-card card-neutral">

            <div class="card-label">Total Alerts</div>

            <div class="card-value"><?= count($notifications) ?></div>

            <div class="card-sub"><?= $unread_count ?> unread notifications</div>

        </div>

    </div>



    <!-- ── Main Grid ─────────────────────────────────────────────────────────── -->

    <div class="main-grid">



        <!-- LEFT: Inbox -->

        <div>

            <div class="filters-section">

                <div class="filter-tabs">

                    <?php

                    $tabs = [

                        'all'       => ['label' => 'All',              'count' => count($notifications)],

                        'unread'    => ['label' => 'Unread',           'count' => $unread_count],

                        'low_stock' => ['label' => 'Low Stock',        'count' => count(array_filter($notifications, fn($n) => $n['type'] === 'low_stock'))],

                        'expiry'    => ['label' => 'Expiry',           'count' => count(array_filter($notifications, fn($n) => $n['type'] === 'expiry'))],

                        'system'    => ['label' => 'Purchase Orders',  'count' => count(array_filter($notifications, fn($n) => $n['type'] === 'system'))],

                    ];

                    foreach ($tabs as $key => $tab):

                        $active = $filter === $key ? 'active' : '';

                    ?>

                    <a href="?filter=<?= $key ?>" class="filter-tab <?= $active ?>">

                        <?= $tab['label'] ?><span class="tab-count"><?= $tab['count'] ?></span>

                    </a>

                    <?php endforeach; ?>

                </div>

            </div>



            <div class="notif-list" id="notifList">

                <?php if (empty($filtered)): ?>

                <div class="empty-state">

                    <div class="empty-icon">

                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke-width="1.5">

                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>

                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>

                        </svg>

                    </div>

                    <h3 class="empty-title">All clear</h3>

                    <p class="empty-description">

                        <?= $filter === 'all'

                            ? 'No store alerts yet. Inventory notifications will appear here.'

                            : 'No ' . htmlspecialchars($filter) . ' notifications at this time.' ?>

                    </p>

                </div>

                <?php else: ?>

                    <?php foreach ($filtered as $n):

                        $unread_class   = !$n['is_read'] ? 'unread' : '';

                        $priority_class = priorityClass($n['priority']);

                        $type_slug      = htmlspecialchars($n['type']);

                    ?>

                    <div class="notif-item <?= $unread_class ?> <?= $priority_class ?>"

                         id="notif-<?= $n['notification_id'] ?>"

                         data-id="<?= $n['notification_id'] ?>">



                        <div class="notif-icon-col">

                            <div class="notif-type-dot type-<?= $type_slug ?>">

                                <?= typeIcon($n['type']) ?>

                            </div>

                        </div>



                        <div class="notif-body">

                            <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>

                            <div class="notif-message"><?= htmlspecialchars($n['message']) ?></div>

                            <div class="notif-meta">

                                <span class="notif-time"><?= timeAgo($n['created_at']) ?></span>

                                <span class="priority-tag <?= $priority_class ?>"><?= ucfirst($n['priority']) ?></span>

                            </div>

                        </div>



                        <div class="notif-actions-col">

                            <?php if (!$n['is_read']): ?>

                            <div class="unread-dot" title="Unread"></div>

                            <button class="btn-icon btn-mark-read" title="Mark as read" data-id="<?= $n['notification_id'] ?>">

                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>

                            </button>

                            <?php endif; ?>

                            <button class="btn-icon btn-dismiss" title="Dismiss" data-id="<?= $n['notification_id'] ?>">

                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>

                            </button>

                        </div>

                    </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>



        <!-- RIGHT: Settings -->

        <div>

            <div class="settings-panel">

                <div class="panel-header">

                    <div class="panel-title">Store Alert Settings</div>

                    <div class="panel-subtitle">Manage store-wide notification preferences</div>

                </div>

                <div class="panel-body">



                    <!-- Expiry breakdown -->

                    <?php

                    $total_exp = max(1, $expiry_summary['expired'] + $expiry_summary['today'] + $expiry_summary['within_3'] + $expiry_summary['within_7'] + $expiry_summary['within_30']);

                    $breakdown = [

                        ['label' => 'Expired',   'count' => $expiry_summary['expired'],   'color' => '#dc2626'],

                        ['label' => 'Today',     'count' => $expiry_summary['today'],     'color' => '#f59e0b'],

                        ['label' => '1–3 days',  'count' => $expiry_summary['within_3'],  'color' => '#f97316'],

                        ['label' => '4–7 days',  'count' => $expiry_summary['within_7'],  'color' => '#7ed957'],

                        ['label' => '8–30 days', 'count' => $expiry_summary['within_30'], 'color' => '#0a0a0a'],

                    ];

                    ?>

                    <div class="settings-group expiry-breakdown">

                        <div class="settings-group-label">Expiry Breakdown</div>

                        <?php foreach ($breakdown as $b): ?>

                        <div class="breakdown-row">

                            <div class="breakdown-label"><?= $b['label'] ?></div>

                            <div class="breakdown-bar-wrap">

                                <div class="breakdown-bar" style="width:<?= round($b['count'] / $total_exp * 100) ?>%;background:<?= $b['color'] ?>;"></div>

                            </div>

                            <div class="breakdown-count"><?= $b['count'] ?></div>

                        </div>

                        <?php endforeach; ?>

                    </div>



                    <!-- Preferences form -->

                    <form id="prefsForm">

                        <div class="settings-group">

                            <div class="settings-group-label">Notification Types</div>



                            <div class="toggle-row">

                                <div>

                                    <div class="toggle-label">Low Stock Alerts</div>

                                    <div class="toggle-desc">Items at or below reorder level</div>

                                </div>

                                <label class="toggle-switch">

                                    <input type="checkbox" name="low_stock_enabled" value="1" <?= $prefs['low_stock_enabled'] ? 'checked' : '' ?>>

                                    <span class="toggle-slider"></span>

                                </label>

                            </div>



                            <div class="toggle-row">

                                <div>

                                    <div class="toggle-label">Store Expiry Alerts</div>

                                    <div class="toggle-desc">Store inventory expiring soon</div>

                                </div>

                                <label class="toggle-switch">

                                    <input type="checkbox" name="expiry_enabled" value="1" <?= $prefs['expiry_enabled'] ? 'checked' : '' ?>>

                                    <span class="toggle-slider"></span>

                                </label>

                            </div>



                            <div class="toggle-row">

                                <div>

                                    <div class="toggle-label">Purchase Order Updates</div>

                                    <div class="toggle-desc">PO status changes &amp; deliveries</div>

                                </div>

                                <label class="toggle-switch">

                                    <input type="checkbox" name="system_enabled" value="1" <?= $prefs['system_enabled'] ? 'checked' : '' ?>>

                                    <span class="toggle-slider"></span>

                                </label>

                            </div>



                            <div class="toggle-row">

                                <div>

                                    <div class="toggle-label">Supplier Alerts</div>

                                    <div class="toggle-desc">Supplier &amp; pricing updates</div>

                                </div>

                                <label class="toggle-switch">

                                    <input type="checkbox" name="achievement_enabled" value="1" <?= $prefs['achievement_enabled'] ? 'checked' : '' ?>>

                                    <span class="toggle-slider"></span>

                                </label>

                            </div>

                        </div>



                        <div class="settings-group">

                            <div class="settings-group-label">Expiry Window</div>

                            <div style="font-size:12px;color:rgba(0,0,0,0.5);line-height:1.6;">

                                Receive alerts when store items are within this many days of expiry.

                            </div>

                            <div class="days-input-row">

                                <label for="expiryDays">Alert me</label>

                                <input type="number" id="expiryDays" name="expiry_days_before"

                                       class="days-input"

                                       value="<?= (int) $prefs['expiry_days_before'] ?>"

                                       min="1" max="30">

                                <span class="days-unit">days before expiry</span>

                            </div>

                        </div>



                        <button type="submit" class="btn-primary btn-save">

                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>

                            Save Preferences

                        </button>

                    </form>

                </div>

            </div>

        </div>



    </div><!-- /main-grid -->

</div>

</div>



<div class="toast-container" id="toastContainer"></div>



<script>

function showToast(message, type = 'success') {

    const container = document.getElementById('toastContainer');

    const toast = document.createElement('div');

    toast.className = `toast toast-${type}`;

    toast.innerHTML = `

        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">

            ${type === 'success' ? '<polyline points="20 6 9 17 4 12"/>' : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'}

        </svg>${message}`;

    container.appendChild(toast);

    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3000);

}



async function apiPost(data) {

    const fd = new FormData();

    Object.entries(data).forEach(([k, v]) => fd.append(k, v));

    const res = await fetch(window.location.pathname, { method: 'POST', body: fd });

    return res.json();

}



document.querySelectorAll('.btn-mark-read').forEach(btn => {

    btn.addEventListener('click', async () => {

        const id = btn.dataset.id;

        const data = await apiPost({ action: 'mark_read', notification_id: id });

        if (data.success) {

            const item = document.getElementById('notif-' + id);

            item.classList.remove('unread', 'priority-high', 'priority-medium', 'priority-low');

            item.querySelector('.unread-dot')?.remove();

            btn.remove();

            showToast('Marked as read');

        }

    });

});



document.querySelectorAll('.btn-dismiss').forEach(btn => {

    btn.addEventListener('click', async () => {

        const id = btn.dataset.id;

        const data = await apiPost({ action: 'dismiss', notification_id: id });

        if (data.success) {

            const item = document.getElementById('notif-' + id);

            item.style.opacity = '0';

            item.style.maxHeight = item.offsetHeight + 'px';

            item.style.transition = 'opacity 0.3s, max-height 0.4s, margin 0.4s';

            item.style.overflow = 'hidden';

            requestAnimationFrame(() => { item.style.maxHeight = '0'; item.style.margin = '0'; });

            setTimeout(() => item.remove(), 450);

            showToast('Notification dismissed');

        }

    });

});



document.getElementById('btnMarkAllRead')?.addEventListener('click', async () => {

    const data = await apiPost({ action: 'mark_all_read' });

    if (data.success) {

        document.querySelectorAll('.notif-item.unread').forEach(el => {

            el.classList.remove('unread', 'priority-high', 'priority-medium', 'priority-low');

            el.querySelector('.unread-dot')?.remove();

            el.querySelector('.btn-mark-read')?.remove();

        });

        document.getElementById('btnMarkAllRead')?.remove();

        showToast('All notifications marked as read');

    }

});



document.getElementById('btnRefresh').addEventListener('click', async () => {

    const btn = document.getElementById('btnRefresh');

    btn.disabled = true;

    btn.textContent = 'Checking…';

    const data = await apiPost({ action: 'run_checks' });

    if (data.success) {

        const r = data.results;

        const total = (r.expiry || 0) + (r.low_stock || 0) + (r.system || 0);

        showToast(total > 0 ? `${total} new alert(s) generated` : 'All up to date — no new alerts');

        if (total > 0) setTimeout(() => location.reload(), 1200);

    }

    btn.disabled = false;

    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Refresh Alerts`;

});



document.getElementById('prefsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd   = new FormData(e.target);
    const body = { action: 'save_preferences' };
    
    // Get the actual state of all checkboxes from the DOM (grocery admin specific)
    const checkboxNames = ['expiry_enabled','low_stock_enabled','achievement_enabled','system_enabled'];
    
    checkboxNames.forEach(name => {
        const checkbox = document.querySelector(`input[name="${name}"]`);
        if (checkbox) {
            body[name] = checkbox.checked ? '1' : '0';
        }
    });
    
    // Get non-checkbox form data (like expiry_days_before)
    fd.forEach((v, k) => {
        if (!checkboxNames.includes(k)) {
            body[k] = v;
        }
    });
    
    const data = await apiPost(body);
    showToast(data.success ? 'Preferences saved! Refresh page to see changes.' : 'Failed to save preferences', data.success ? 'success' : 'error');
});

</script>



<?php require_once '../includes/footer.php'; ?>