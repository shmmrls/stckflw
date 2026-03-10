<?php
/**
 * Customer Notification Settings & Inbox
 * Route: /user/notification_settings.php
 */

session_start();

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

require_once '../includes/config.php';
require_once '../includes/customer_auth_check.php';
require_once '../includes/notifications_system.php';
require_once '../includes/expiry_alerts.php';

// ── Auth guard ──────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: /user/login.php');
    exit;
}
$user_id = (int) $_SESSION['user_id'];

$conn = getDBConnection();
ensureNotificationsTable($conn);

// ── Verify customer role ─────────────────────────────────────────────────────
$role_stmt = $conn->prepare("SELECT role, full_name FROM users WHERE user_id = ?");
$role_stmt->bind_param('i', $user_id);
$role_stmt->execute();
$current_user = $role_stmt->get_result()->fetch_assoc();
$role         = $current_user['role'] ?? 'customer';

if ($role === 'grocery_admin') {
    header('Location: /grocery/notification_settings.php');
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
            $save_data = [
                'expiry_enabled'              => ($_POST['expiry_enabled']              ?? '0') === '1' ? '1' : '0',
                'expiry_days_before'          => max(1, min(30, (int) ($_POST['expiry_days_before'] ?? 7))),
                'low_stock_enabled'           => ($_POST['low_stock_enabled']           ?? '0') === '1' ? '1' : '0',
                'achievement_enabled'         => ($_POST['achievement_enabled']         ?? '0') === '1' ? '1' : '0',
                'system_enabled'              => ($_POST['system_enabled']              ?? '0') === '1' ? '1' : '0',
                'group_notifications_enabled' => ($_POST['group_notifications_enabled'] ?? '0') === '1' ? '1' : '0',
                'email_enabled'               => ($_POST['email_enabled']               ?? '0') === '1' ? '1' : '0',
            ];
            echo json_encode(['success' => saveNotificationPreferences($conn, $user_id, $save_data)]);
            exit;
    }
}

// ── Regular page load ────────────────────────────────────────────────────────
runNotificationChecks($conn, $user_id);

$prefs          = getNotificationPreferences($conn, $user_id);
$notifications  = getNotifications($conn, $user_id, false, 50);
$unread_count   = countUnreadNotifications($conn, $user_id);
$expiry_summary = getExpirySummary($conn, $user_id, $role);

$filter        = $_GET['filter'] ?? 'all';
$valid_filters = ['all', 'unread', 'expiry', 'low_stock', 'achievement'];
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

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/notification-settings.css">';
require_once '../includes/header.php';
?>

<div class="catalog-page">
<div class="catalog-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <nav class="breadcrumb-nav">
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/dashboard.php" class="breadcrumb-link">Dashboard</a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Notifications</span>
                </nav>
                <h1 class="page-title">Notifications</h1>
                <p class="page-subtitle">
                    Personal inventory alerts, expiry reminders &amp; achievements
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

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card card-danger">
            <div class="card-label">Expired Items</div>
            <div class="card-value"><?= $expiry_summary['expired'] ?></div>
            <div class="card-sub">Items past expiry date</div>
        </div>
        <div class="summary-card card-warning">
            <div class="card-label">Expiring Today</div>
            <div class="card-value"><?= $expiry_summary['today'] ?></div>
            <div class="card-sub">Requires immediate action</div>
        </div>
        <div class="summary-card card-info">
            <div class="card-label">Within 7 Days</div>
            <div class="card-value"><?= $expiry_summary['within_3'] + $expiry_summary['within_7'] ?></div>
            <div class="card-sub">Items expiring this week</div>
        </div>
        <div class="summary-card card-neutral">
            <div class="card-label">Total Alerts</div>
            <div class="card-value"><?= count($notifications) ?></div>
            <div class="card-sub"><?= $unread_count ?> unread notifications</div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="main-grid">

        <!-- LEFT: Inbox -->
        <div>
            <div class="filters-section">
                <div class="filter-tabs">
                    <?php
                    $tabs = [
                        'all'         => ['label' => 'All',          'count' => count($notifications)],
                        'unread'      => ['label' => 'Unread',       'count' => $unread_count],
                        'expiry'      => ['label' => 'Expiry',       'count' => count(array_filter($notifications, fn($n) => $n['type'] === 'expiry'))],
                        'low_stock'   => ['label' => 'Low Stock',    'count' => count(array_filter($notifications, fn($n) => $n['type'] === 'low_stock'))],
                        'achievement' => ['label' => 'Achievements', 'count' => count(array_filter($notifications, fn($n) => $n['type'] === 'achievement'))],
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
                            ? 'No notifications yet. Your inventory alerts will appear here.'
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
                    <div class="panel-title">Alert Preferences</div>
                    <div class="panel-subtitle">Customize your personal notification preferences</div>
                </div>
                <div class="panel-body">

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

                    <form id="prefsForm">
                        <div class="settings-group">
                            <div class="settings-group-label">Notification Types</div>

                            <div class="toggle-row">
                                <div><div class="toggle-label">Personal Expiry Reminders</div><div class="toggle-desc">Your items expiring soon or expired</div></div>
                                <label class="toggle-switch"><input type="checkbox" name="expiry_enabled" value="1" <?= $prefs['expiry_enabled'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                            </div>
                            <div class="toggle-row">
                                <div><div class="toggle-label">Low Stock Alerts</div><div class="toggle-desc">Items running low in your inventory</div></div>
                                <label class="toggle-switch"><input type="checkbox" name="low_stock_enabled" value="1" <?= $prefs['low_stock_enabled'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                            </div>
                            <div class="toggle-row">
                                <div><div class="toggle-label">Group Notifications</div><div class="toggle-desc">Group invites &amp; shared item updates</div></div>
                                <label class="toggle-switch"><input type="checkbox" name="group_notifications_enabled" value="1" <?= $prefs['group_notifications_enabled'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                            </div>
                            <div class="toggle-row">
                                <div><div class="toggle-label">System Notifications</div><div class="toggle-desc">Welcome messages &amp; system updates</div></div>
                                <label class="toggle-switch"><input type="checkbox" name="system_enabled" value="1" <?= $prefs['system_enabled'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                            </div>
                            <div class="toggle-row">
                                <div><div class="toggle-label">Achievement Alerts</div><div class="toggle-desc">Badges, points &amp; milestones</div></div>
                                <label class="toggle-switch"><input type="checkbox" name="achievement_enabled" value="1" <?= $prefs['achievement_enabled'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                            </div>
                        </div>

                        <div class="settings-group">
                            <div class="settings-group-label">Expiry Window</div>
                            <p style="font-size:12px;color:rgba(0,0,0,0.5);line-height:1.6;font-family:'Montserrat',sans-serif;margin:0;">
                                Receive alerts when items are within this many days of expiry.
                            </p>
                            <div class="days-input-row">
                                <label for="expiryDays">Alert me</label>
                                <input type="number" id="expiryDays" name="expiry_days_before" class="days-input"
                                       value="<?= (int) $prefs['expiry_days_before'] ?>" min="1" max="30">
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

    </div>
</div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
function showToast(message, type = 'success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">${type === 'success' ? '<polyline points="20 6 9 17 4 12"/>' : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'}</svg>${message}`;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity 0.4s'; setTimeout(() => t.remove(), 400); }, 3000);
}

async function apiPost(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    const res = await fetch(window.location.pathname, { method: 'POST', body: fd });
    return res.json();
}

document.querySelectorAll('.btn-mark-read').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id   = btn.dataset.id;
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
        const id   = btn.dataset.id;
        const data = await apiPost({ action: 'dismiss', notification_id: id });
        if (data.success) {
            const item = document.getElementById('notif-' + id);
            item.style.opacity   = '0';
            item.style.maxHeight = item.offsetHeight + 'px';
            item.style.transition = 'opacity 0.3s, max-height 0.4s, margin 0.4s';
            item.style.overflow  = 'hidden';
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
    btn.disabled = true; btn.textContent = 'Checking…';
    const data = await apiPost({ action: 'run_checks' });
    if (data.success) {
        const r     = data.results;
        const total = (r.expiry || 0) + (r.low_stock || 0) + (r.achievement || 0);
        showToast(total > 0 ? `${total} new alert(s) generated` : 'All up to date — no new alerts');
        if (total > 0) setTimeout(() => location.reload(), 1200);
    }
    btn.disabled = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Refresh Alerts`;
});

document.getElementById('prefsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd           = new FormData(e.target);
    const body         = { action: 'save_preferences' };
    const checkboxes   = ['expiry_enabled','low_stock_enabled','achievement_enabled','system_enabled','group_notifications_enabled','email_enabled'];
    checkboxes.forEach(name => {
        const cb = document.querySelector(`input[name="${name}"]`);
        if (cb) body[name] = cb.checked ? '1' : '0';
    });
    fd.forEach((v, k) => { if (!checkboxes.includes(k)) body[k] = v; });
    const data = await apiPost(body);
    showToast(data.success ? 'Preferences saved!' : 'Failed to save preferences', data.success ? 'success' : 'error');
});
</script>

<?php require_once '../includes/footer.php'; ?>