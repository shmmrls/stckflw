<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/customer_auth_check.php';
require_once __DIR__ . '/../includes/badge_system.php';
requireLogin();

$conn    = getDBConnection();
$user_id = getCurrentUserId();

// Get current user's groups
$user_groups_query = $conn->prepare("
    SELECT g.group_id, g.group_name, g.group_type
    FROM groups g
    JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY g.group_name
");
$user_groups_query->bind_param("i", $user_id);
$user_groups_query->execute();
$user_groups = $user_groups_query->get_result()->fetch_all(MYSQLI_ASSOC);

// URL params
$leaderboard_type = $_GET['type']   ?? 'items';
$time_filter      = $_GET['period'] ?? 'all-time';
$group_filter     = $_GET['group']  ?? 'global';

$valid_types   = ['items', 'actions'];
$valid_periods = ['all-time', 'monthly', 'weekly'];
$leaderboard_type = in_array($leaderboard_type, $valid_types)   ? $leaderboard_type : 'items';
$time_filter      = in_array($time_filter,      $valid_periods) ? $time_filter      : 'all-time';

// Time condition
$time_condition = match($time_filter) {
    'weekly'  => "AND u.last_login >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
    'monthly' => "AND u.last_login >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
    default   => "",
};

// Build & execute query
$leaderboard_data = [];
$is_global        = ($group_filter === 'global');

switch ($leaderboard_type) {
    case 'items':
        $sql = $is_global
            ? "SELECT u.user_id, u.full_name, u.img_name,
                      COUNT(DISTINCT ci.item_id) as total_items,
                      ROW_NUMBER() OVER (ORDER BY COUNT(DISTINCT ci.item_id) DESC) as position
               FROM users u
               LEFT JOIN customer_items ci ON u.user_id = ci.created_by
               WHERE u.is_active = 1 AND u.role = 'customer' $time_condition
               GROUP BY u.user_id, u.full_name, u.img_name
               ORDER BY total_items DESC, u.user_id ASC LIMIT 50"
            : "SELECT u.user_id, u.full_name, u.img_name,
                      COUNT(DISTINCT ci.item_id) as total_items,
                      ROW_NUMBER() OVER (ORDER BY COUNT(DISTINCT ci.item_id) DESC) as position
               FROM users u
               JOIN group_members gm ON u.user_id = gm.user_id
               LEFT JOIN customer_items ci ON u.user_id = ci.created_by AND ci.group_id = gm.group_id
               WHERE gm.group_id = ? AND u.is_active = 1 AND u.role = 'customer' $time_condition
               GROUP BY u.user_id, u.full_name, u.img_name
               ORDER BY total_items DESC, u.user_id ASC LIMIT 50";
        break;

    case 'actions':
        $sql = $is_global
            ? "SELECT u.user_id, u.full_name, u.img_name,
                      COUNT(DISTINCT ciu.update_id) as total_actions,
                      ROW_NUMBER() OVER (ORDER BY COUNT(DISTINCT ciu.update_id) DESC) as position
               FROM users u
               LEFT JOIN customer_inventory_updates ciu ON u.user_id = ciu.updated_by
               WHERE u.is_active = 1 AND u.role = 'customer' $time_condition
               GROUP BY u.user_id, u.full_name, u.img_name
               ORDER BY total_actions DESC, u.user_id ASC LIMIT 50"
            : "SELECT u.user_id, u.full_name, u.img_name,
                      COUNT(DISTINCT ciu.update_id) as total_actions,
                      ROW_NUMBER() OVER (ORDER BY COUNT(DISTINCT ciu.update_id) DESC) as position
               FROM users u
               JOIN group_members gm ON u.user_id = gm.user_id
               LEFT JOIN customer_items ci ON u.user_id = ci.created_by AND ci.group_id = gm.group_id
               LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id
               WHERE gm.group_id = ? AND u.is_active = 1 AND u.role = 'customer' $time_condition
               GROUP BY u.user_id, u.full_name, u.img_name
               ORDER BY total_actions DESC, u.user_id ASC LIMIT 50";
        break;
}

if (isset($sql)) {
    $stmt = $conn->prepare($sql);
    if (!$is_global) $stmt->bind_param("i", $group_filter);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Deduplicate by user_id
    $seen = [];
    foreach ($rows as $row) {
        if (!isset($seen[$row['user_id']])) {
            $seen[$row['user_id']] = true;
            $leaderboard_data[] = $row;
        }
    }
}

// Current user's rank
$current_user_rank = null;
foreach ($leaderboard_data as $user) {
    if ($user['user_id'] == $user_id) {
        $current_user_rank = [
            'rank'  => $user['position'],
            'value' => $user['total_items'] ?? $user['total_actions'] ?? 0,
            'user'  => $user,
        ];
        break;
    }
}

/* ── SVG helpers ──────────────────────────────────────────── */
function svgIcon(string $key): string {
    $s = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
       . 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"';
    $icons = [
        'arrow-left'  => "<svg $s><path d='M19 12H5M12 19l-7-7 7-7'/></svg>",
        'globe'       => "<svg $s><circle cx='12' cy='12' r='10'/><path d='M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z'/></svg>",
        'home'        => "<svg $s><path d='M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/><polyline points='9 22 9 12 15 12 15 22'/></svg>",
        'building'    => "<svg $s><rect x='3' y='3' width='18' height='18' rx='0'/><path d='M9 3v18M15 3v18M3 9h18M3 15h18'/></svg>",
        'box'         => "<svg $s><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'/><polyline points='3.27 6.96 12 12.01 20.73 6.96'/><line x1='12' y1='22.08' x2='12' y2='12'/></svg>",
        'zap'         => "<svg $s><polygon points='13 2 3 14 12 14 11 22 21 10 12 10 13 2'/></svg>",
        'bar-chart'   => "<svg $s><line x1='18' y1='20' x2='18' y2='10'/><line x1='12' y1='20' x2='12' y2='4'/><line x1='6' y1='20' x2='6' y2='14'/></svg>",
        'medal-gold'  => "<svg $s stroke='#c9a84c'><circle cx='12' cy='8' r='6'/><path d='M15.477 12.89 17 22l-5-3-5 3 1.523-9.11'/></svg>",
        'medal-silver'=> "<svg $s stroke='#9ca3af'><circle cx='12' cy='8' r='6'/><path d='M15.477 12.89 17 22l-5-3-5 3 1.523-9.11'/></svg>",
        'medal-bronze'=> "<svg $s stroke='#a0714f'><circle cx='12' cy='8' r='6'/><path d='M15.477 12.89 17 22l-5-3-5 3 1.523-9.11'/></svg>",
        'crown'       => "<svg $s stroke='#c9a84c'><path d='M2 20h20M5 20 3 8l4.5 4L12 4l4.5 8L21 8l-2 12'/></svg>",
        'chart'       => "<svg $s><line x1='18' y1='20' x2='18' y2='10'/><line x1='12' y1='20' x2='12' y2='4'/><line x1='6' y1='20' x2='6' y2='14'/></svg>",
        'trophy'      => "<svg $s><polyline points='8 21 12 21 16 21'/><line x1='12' y1='17' x2='12' y2='21'/><path d='M7 4H17V13a5 5 0 0 1-10 0V4Z'/><path d='M7 9H4a2 2 0 0 1-2-2V6a1 1 0 0 1 1-1h4'/><path d='M17 9h3a2 2 0 0 0 2-2V6a1 1 0 0 0-1-1h-4'/></svg>",
    ];
    return $icons[$key] ?? '';
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/leaderboard.css">';
require_once __DIR__ . '/../includes/header.php';

// Label maps
$type_labels = ['household' => 'Household', 'co_living' => 'Co-living', 'small_business' => 'Business'];
$group_type_icons = ['household' => 'home', 'co_living' => 'building', 'small_business' => 'building'];
$value_headers = ['items' => 'Items', 'actions' => 'Actions'];
$titles    = ['items' => 'Item Managers', 'actions' => 'Most Active Users'];
$subtitles = ['items' => 'Users who have added the most items', 'actions' => 'Users with the most inventory actions'];
?>

<main class="leaderboard-page">
<div class="page-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">Leaderboard</h1>
            <p class="page-subtitle">Compete with other users and climb the rankings</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/dashboard.php" class="btn btn-secondary">
                <?php echo svgIcon('arrow-left'); ?>
                Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Controls -->
    <div class="leaderboard-controls">

        <div class="control-group">
            <span class="control-label">Ranking Type</span>
            <div class="control-tabs">
                <a href="?type=items&period=<?php echo $time_filter; ?>&group=<?php echo $group_filter; ?>"
                   class="control-tab <?php echo $leaderboard_type === 'items' ? 'active' : ''; ?>">
                    <span class="tab-icon"><?php echo svgIcon('box'); ?></span> Items
                </a>
                <a href="?type=actions&period=<?php echo $time_filter; ?>&group=<?php echo $group_filter; ?>"
                   class="control-tab <?php echo $leaderboard_type === 'actions' ? 'active' : ''; ?>">
                    <span class="tab-icon"><?php echo svgIcon('zap'); ?></span> Actions
                </a>
            </div>
        </div>

        <div class="control-group">
            <span class="control-label">Time Period</span>
            <div class="control-tabs">
                <?php foreach (['all-time' => 'All Time', 'monthly' => 'Monthly', 'weekly' => 'Weekly'] as $val => $label): ?>
                <a href="?type=<?php echo $leaderboard_type; ?>&period=<?php echo $val; ?>&group=<?php echo $group_filter; ?>"
                   class="control-tab <?php echo $time_filter === $val ? 'active' : ''; ?>">
                    <?php echo $label; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="control-group">
            <span class="control-label">Scope</span>
            <div class="control-tabs">
                <a href="?type=<?php echo $leaderboard_type; ?>&period=<?php echo $time_filter; ?>&group=global"
                   class="control-tab <?php echo $group_filter === 'global' ? 'active' : ''; ?>">
                    <span class="tab-icon"><?php echo svgIcon('globe'); ?></span> Global
                </a>
                <?php foreach ($user_groups as $group): ?>
                <a href="?type=<?php echo $leaderboard_type; ?>&period=<?php echo $time_filter; ?>&group=<?php echo $group['group_id']; ?>"
                   class="control-tab <?php echo $group_filter == $group['group_id'] ? 'active' : ''; ?>">
                    <span class="tab-icon"><?php echo svgIcon($group_type_icons[$group['group_type']] ?? 'home'); ?></span>
                    <?php echo htmlspecialchars($group['group_name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Current User Rank -->
    <?php if ($current_user_rank): ?>
    <div class="user-rank-card">
        <div class="rank-content">
            <div class="rank-position">#<?php echo $current_user_rank['rank']; ?></div>
            <div class="rank-info">
                <div class="rank-avatar">
                    <img src="<?php echo !empty($current_user_rank['user']['img_name'])
                        ? htmlspecialchars($baseUrl) . '/images/profile_pictures/' . htmlspecialchars($current_user_rank['user']['img_name'])
                        : htmlspecialchars($baseUrl) . '/images/profile_pictures/nopfp.jpg'; ?>" alt="Profile">
                </div>
                <div class="rank-details">
                    <h3><?php echo htmlspecialchars($current_user_rank['user']['full_name']); ?></h3>
                    <p class="rank-value">
                        <?php echo number_format($current_user_rank['value'])
                            . ' ' . ($leaderboard_type === 'items' ? 'items' : 'actions'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Leaderboard Table -->
    <div class="leaderboard-table-container">
        <div class="table-header">
            <h2><?php echo $titles[$leaderboard_type] ?? 'Leaderboard'; ?></h2>
            <p class="table-subtitle"><?php echo $subtitles[$leaderboard_type] ?? ''; ?></p>
        </div>

        <div class="leaderboard-table">
            <?php if (!empty($leaderboard_data)): ?>

                <div class="table-headers">
                    <div class="header-rank">Rank</div>
                    <div class="header-user">User</div>
                    <div class="header-value"><?php echo $value_headers[$leaderboard_type] ?? 'Score'; ?></div>
                </div>

                <?php foreach ($leaderboard_data as $index => $user):
                    // Fetch user's group type (once per row)
                    $gt_stmt = $conn->prepare("SELECT g.group_type FROM groups g JOIN group_members gm ON g.group_id = gm.group_id WHERE gm.user_id = ? LIMIT 1");
                    $gt_stmt->bind_param("i", $user['user_id']);
                    $gt_stmt->execute();
                    $user_group_type = $gt_stmt->get_result()->fetch_column();
                    $gt_stmt->close();
                ?>
                <div class="table-row <?php echo $user['user_id'] == $user_id ? 'current-user' : ''; ?>">

                    <div class="rank-cell">
                        <span class="rank-number">#<?php echo $user['position']; ?></span>
                        <?php if ($user['position'] === 1): ?>
                            <span class="rank-medal rank-medal--gold"><?php echo svgIcon('medal-gold'); ?></span>
                        <?php elseif ($user['position'] === 2): ?>
                            <span class="rank-medal rank-medal--silver"><?php echo svgIcon('medal-silver'); ?></span>
                        <?php elseif ($user['position'] === 3): ?>
                            <span class="rank-medal rank-medal--bronze"><?php echo svgIcon('medal-bronze'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="user-cell">
                        <div class="user-avatar">
                            <img src="<?php echo !empty($user['img_name'])
                                ? htmlspecialchars($baseUrl) . '/images/profile_pictures/' . htmlspecialchars($user['img_name'])
                                : htmlspecialchars($baseUrl) . '/images/profile_pictures/nopfp.jpg'; ?>" alt="Profile">
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <span class="user-type"><?php echo $type_labels[$user_group_type] ?? 'User'; ?></span>
                        </div>
                    </div>

                    <div class="value-cell">
                        <span class="value-number">
                            <?php echo number_format($user['total_items'] ?? $user['total_actions'] ?? 0); ?>
                        </span>
                        <?php if ($index === 0): ?>
                            <span class="crown"><?php echo svgIcon('crown'); ?></span>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon"><?php echo svgIcon('bar-chart'); ?></div>
                    <h3>No data available</h3>
                    <p>There's no activity data for this time period and category yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<?php
$user_groups_query->close();
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>