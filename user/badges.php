<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/customer_auth_check.php';
require_once __DIR__ . '/../includes/badge_system.php';
requireLogin();
$conn    = getDBConnection();
$user_id = getCurrentUserId();

// Get all groups the user belongs to
$user_groups_query = $conn->prepare("
    SELECT g.group_id, g.group_name, g.group_type, gm.member_role
    FROM groups g
    JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY g.group_name
");
$user_groups_query->bind_param("i", $user_id);
$user_groups_query->execute();
$user_groups = $user_groups_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's level info
$level_info = getUserLevel($conn, $user_id);

// Get user's earned badges
$earned_badges_result = getUserBadges($conn, $user_id);
$earned_badges    = [];
$earned_badge_ids = [];
while ($badge = $earned_badges_result->fetch_assoc()) {
    $earned_badges[]    = $badge;
    $earned_badge_ids[] = $badge['badge_id'];
}

/* ─────────────────────────────────────────────────────────────────────────────
   SVG icon library  (Lucide-style, stroke-based, 24×24 viewBox)
   ───────────────────────────────────────────────────────────────────────────── */
function getBadgeSvg(string $key): string {
    $s  = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"';

    $icons = [
        /* badge icons */
        'family' => "<svg $s><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>",
        'recycle' => "<svg $s><polyline points='1 4 1 10 7 10'/><polyline points='23 20 23 14 17 14'/><path d='M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15'/></svg>",
        'cart'    => "<svg $s><circle cx='9' cy='21' r='1'/><circle cx='20' cy='21' r='1'/><path d='M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6'/></svg>",
        'star'    => "<svg $s><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>",
        'trophy'  => "<svg $s><polyline points='8 21 12 21 16 21'/><line x1='12' y1='17' x2='12' y2='21'/><path d='M7 4H17V13a5 5 0 0 1-10 0V4Z'/><path d='M7 9H4a2 2 0 0 1-2-2V6a1 1 0 0 1 1-1h4'/><path d='M17 9h3a2 2 0 0 0 2-2V6a1 1 0 0 0-1-1h-4'/></svg>",
        'home'    => "<svg $s><path d='M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/><polyline points='9 22 9 12 15 12 15 22'/></svg>",
        'handshake' => "<svg $s><path d='M20.42 4.58a5.4 5.4 0 0 0-7.65 0l-.77.78-.77-.78a5.4 5.4 0 0 0-7.65 0C1.46 6.7 1.33 10.28 4 13l8 8 8-8c2.67-2.72 2.54-6.3.42-8.42z'/></svg>",
        'community' => "<svg $s><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>",
        'team'    => "<svg $s><circle cx='12' cy='8' r='4'/><path d='M4 20c0-4 3.58-7 8-7s8 3 8 7'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/><path d='M8 3.13a4 4 0 0 0 0 7.75'/></svg>",
        'box'     => "<svg $s><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'/><polyline points='3.27 6.96 12 12.01 20.73 6.96'/><line x1='12' y1='22.08' x2='12' y2='12'/></svg>",
        'zap'     => "<svg $s><polygon points='13 2 3 14 12 14 11 22 21 10 12 10 13 2'/></svg>",
        'briefcase' => "<svg $s><rect x='2' y='7' width='20' height='14' rx='2' ry='2'/><path d='M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16'/></svg>",
        'badge-check' => "<svg $s><path d='M12 2l3.09 1.26L18 2l1.26 3.09L22 6l-1.26 3.09L22 12l-3.09 1.26L18 16l-3.09-1.26L12 16l-1.26-3.09L8 12l1.26-3.09L8 6l3.09-1.26z'/><polyline points='9 12 11 14 15 10'/></svg>",
        'sparkle' => "<svg $s><path d='M12 3v3m0 12v3M3 12h3m12 0h3M5.64 5.64l2.12 2.12m8.48 8.48 2.12 2.12M5.64 18.36l2.12-2.12m8.48-8.48 2.12-2.12'/><circle cx='12' cy='12' r='3'/></svg>",
        'sword'   => "<svg $s><polyline points='14.5 17.5 3 6 3 3 6 3 17.5 14.5'/><line x1='13' y1='19' x2='19' y2='13'/><line x1='16' y1='16' x2='20' y2='20'/><line x1='19' y1='21' x2='21' y2='19'/></svg>",
        'bar-chart' => "<svg $s><line x1='18' y1='20' x2='18' y2='10'/><line x1='12' y1='20' x2='12' y2='4'/><line x1='6' y1='20' x2='6' y2='14'/></svg>",
        'users'   => "<svg $s><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>",
        /* UI chrome */
        'check'  => "<svg $s stroke-width='2'><polyline points='20 6 9 17 4 12'/></svg>",
        'lock'   => "<svg $s><rect x='3' y='11' width='18' height='11' rx='2' ry='2'/><path d='M7 11V7a5 5 0 0 1 10 0v4'/></svg>",
        'target' => "<svg $s><circle cx='12' cy='12' r='10'/><circle cx='12' cy='12' r='6'/><circle cx='12' cy='12' r='2'/></svg>",
        'award'  => "<svg $s><circle cx='12' cy='8' r='6'/><path d='M15.477 12.89 17 22l-5-3-5 3 1.523-9.11'/></svg>",
    ];

    return $icons[$key] ?? $icons['star'];
}

/* ─────────────────────────────────────────────────────────────────────────────
   Badge definitions  (icon key → SVG lookup above)
   ───────────────────────────────────────────────────────────────────────────── */
function getGroupBadgeDetails(string $group_type): array {
    switch ($group_type) {
        case 'household':
            return [
                1 => ['name' => 'Family Organizer', 'description' => 'Add 5 items to your family inventory',                      'icon' => 'family',     'requirement' => 'Add 5 items',      'category' => 'Organization'],
                2 => ['name' => 'Waste Reducer',     'description' => 'Log consumption of 15+ items to reduce food waste',         'icon' => 'recycle',    'requirement' => 'Consume 15 items', 'category' => 'Sustainability'],
                3 => ['name' => 'Smart Shopper',     'description' => 'Earn 150+ points through efficient inventory management',   'icon' => 'cart',       'requirement' => 'Earn 150 points',  'category' => 'Efficiency'],
                4 => ['name' => 'Active Member',     'description' => 'Consume 8+ items to show active participation',             'icon' => 'star',       'requirement' => 'Consume 8 items',  'category' => 'Engagement'],
                5 => ['name' => 'Household Hero',    'description' => 'Earn 300+ points and become a household management expert', 'icon' => 'trophy',     'requirement' => 'Earn 300 points',  'category' => 'Mastery'],
            ];
        case 'co_living':
            return [
                1 => ['name' => 'Roommate Coordinator',    'description' => 'Add 3 items to shared inventory',                    'icon' => 'home',       'requirement' => 'Add 3 items',      'category' => 'Coordination'],
                2 => ['name' => 'Shared Resource Manager', 'description' => 'Log consumption of 25+ items in shared space',       'icon' => 'handshake',  'requirement' => 'Consume 25 items', 'category' => 'Collaboration'],
                3 => ['name' => 'Community Leader',        'description' => 'Earn 200+ points through community contribution',    'icon' => 'community',  'requirement' => 'Earn 200 points',  'category' => 'Leadership'],
                4 => ['name' => 'Team Player',             'description' => 'Consume 12+ items showing teamwork',                 'icon' => 'team',       'requirement' => 'Consume 12 items', 'category' => 'Teamwork'],
                5 => ['name' => 'Co-living Champion',      'description' => 'Earn 400+ points as co-living expert',               'icon' => 'trophy',     'requirement' => 'Earn 400 points',  'category' => 'Excellence'],
            ];
        case 'small_business':
            return [
                1 => ['name' => 'Inventory Manager', 'description' => 'Add 10 items to business inventory',               'icon' => 'box',          'requirement' => 'Add 10 items',     'category' => 'Management'],
                2 => ['name' => 'Efficiency Expert', 'description' => 'Log consumption of 30+ items efficiently',         'icon' => 'zap',          'requirement' => 'Consume 30 items', 'category' => 'Performance'],
                3 => ['name' => 'Business Pro',      'description' => 'Earn 250+ points through business operations',     'icon' => 'briefcase',    'requirement' => 'Earn 250 points',  'category' => 'Professional'],
                4 => ['name' => 'Staff Leader',      'description' => 'Consume 15+ items showing leadership',             'icon' => 'badge-check',  'requirement' => 'Consume 15 items', 'category' => 'Leadership'],
                5 => ['name' => 'Operations Master', 'description' => 'Earn 500+ points as operations expert',            'icon' => 'trophy',       'requirement' => 'Earn 500 points',  'category' => 'Mastery'],
            ];
        default:
            return [
                1 => ['name' => 'Newbie Organizer', 'description' => 'Add 5 items to get started',                        'icon' => 'sparkle',    'requirement' => 'Add 5 items',      'category' => 'Getting Started'],
                2 => ['name' => 'Waste Warrior',    'description' => 'Log consumption of 20+ items',                      'icon' => 'sword',      'requirement' => 'Consume 20 items', 'category' => 'Sustainability'],
                3 => ['name' => 'Inventory Master', 'description' => 'Earn 200+ points through inventory management',     'icon' => 'bar-chart',  'requirement' => 'Earn 200 points',  'category' => 'Expertise'],
                4 => ['name' => 'Active Helper',    'description' => 'Consume 10+ items regularly',                       'icon' => 'users',      'requirement' => 'Consume 10 items', 'category' => 'Activity'],
                5 => ['name' => 'Power User',       'description' => 'Earn 500+ points as advanced user',                 'icon' => 'trophy',     'requirement' => 'Earn 500 points',  'category' => 'Mastery'],
            ];
    }
}

function getUserStatsForGroup($conn, int $user_id, int $group_id): array {
    $stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT ci.item_id) as total_items_added,
            SUM(CASE WHEN ciu.update_type = 'consumed' THEN 1 ELSE 0 END) as total_consumed,
            (SELECT total_points FROM user_points WHERE user_id = ?) as total_points
        FROM customer_items ci
        LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id
        WHERE ci.created_by = ? AND ci.group_id = ?
    ");
    $stmt->bind_param("iii", $user_id, $user_id, $group_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?? [];
}

function getBadgeRequirementMet(int $badge_id, string $group_type, array $stats): bool {
    $items    = $stats['total_items_added'] ?? 0;
    $consumed = $stats['total_consumed']    ?? 0;
    $points   = $stats['total_points']      ?? 0;
    $map = [
        'household'      => [1=>[$items,5],    2=>[$consumed,15], 3=>[$points,150], 4=>[$consumed,8],  5=>[$points,300]],
        'co_living'      => [1=>[$items,3],    2=>[$consumed,25], 3=>[$points,200], 4=>[$consumed,12], 5=>[$points,400]],
        'small_business' => [1=>[$items,10],   2=>[$consumed,30], 3=>[$points,250], 4=>[$consumed,15], 5=>[$points,500]],
    ];
    if (!isset($map[$group_type][$badge_id])) return false;
    [$value, $threshold] = $map[$group_type][$badge_id];
    return $value >= $threshold;
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/badges.css">';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="badges-page">
<div class="page-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">All Badges</h1>
            <p class="page-subtitle">View badges for all your groups. Each group has different achievements tailored to its context.</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/dashboard.php" class="back-btn">
                <span class="back-btn__icon"><?php echo getBadgeSvg('home'); ?></span>
                Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-overview">
        <div class="stat-card">
            <p class="stat-value"><?php echo count($earned_badges); ?></p>
            <p class="stat-label">Badges Earned</p>
        </div>
        <div class="stat-card">
            <p class="stat-value"><?php echo count($user_groups); ?></p>
            <p class="stat-label">Active Groups</p>
        </div>
        <div class="stat-card">
            <p class="stat-value"><?php echo $level_info['current_points']; ?></p>
            <p class="stat-label">Total Points</p>
        </div>
        <div class="stat-card">
            <p class="stat-value"><?php echo getLevelName($level_info['level']); ?></p>
            <p class="stat-label">Current Level</p>
        </div>
    </div>

    <!-- Group Tabs -->
    <?php if (count($user_groups) > 1): ?>
    <div class="group-selector">
        <div class="group-tabs">
            <?php foreach ($user_groups as $index => $group): ?>
                <button class="group-tab <?php echo $index === 0 ? 'active' : ''; ?>"
                        onclick="switchGroup(<?php echo $index; ?>)">
                    <?php echo htmlspecialchars($group['group_name']); ?>
                    <span class="group-tab-type">(<?php echo ucfirst(str_replace('_', ' ', $group['group_type'])); ?>)</span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Badge Sections for Each Group -->
    <?php foreach ($user_groups as $index => $group):
        $group_badge_details = getGroupBadgeDetails($group['group_type']);
        $group_stats         = getUserStatsForGroup($conn, $user_id, $group['group_id']);

        $earned_group_badges = [];
        foreach ($group_badge_details as $badge_id => $_) {
            if (getBadgeRequirementMet($badge_id, $group['group_type'], $group_stats)) {
                $earned_group_badges[] = $badge_id;
            }
        }
    ?>
    <div class="group-content <?php echo $index === 0 ? 'active' : ''; ?>" id="group-<?php echo $index; ?>">
        <div class="badges-section">

            <!-- Group Header -->
            <div class="group-header">
                <div class="group-info">
                    <h3 class="group-name"><?php echo htmlspecialchars($group['group_name']); ?></h3>
                    <span class="group-type-badge"><?php echo ucfirst(str_replace('_', ' ', $group['group_type'])); ?></span>
                </div>
            </div>

            <!-- Earned Badges -->
            <h4 class="subsection-title subsection-title--earned">
                <span class="subsection-title__icon"><?php echo getBadgeSvg('award'); ?></span>
                Earned Badges
            </h4>

            <?php if (!empty($earned_group_badges)): ?>
                <div class="badges-grid badges-grid--earned">
                    <?php foreach ($earned_group_badges as $badge_id):
                        $badge_info       = $group_badge_details[$badge_id];
                        $earned_badge_info = null;
                        foreach ($earned_badges as $eb) {
                            if ($eb['badge_id'] == $badge_id) { $earned_badge_info = $eb; break; }
                        }
                    ?>
                    <div class="badge-card badge-card--earned">
                        <span class="badge-status badge-status--earned">
                            <span class="badge-status__icon"><?php echo getBadgeSvg('check'); ?></span>
                            Unlocked
                        </span>
                        <div class="badge-header">
                            <div class="badge-icon badge-icon--earned">
                                <?php echo getBadgeSvg($badge_info['icon']); ?>
                            </div>
                            <div class="badge-meta">
                                <p class="badge-name"><?php echo htmlspecialchars($badge_info['name']); ?></p>
                                <span class="badge-category"><?php echo htmlspecialchars($badge_info['category']); ?></span>
                            </div>
                        </div>
                        <p class="badge-description"><?php echo htmlspecialchars($badge_info['description']); ?></p>
                        <p class="badge-requirement badge-requirement--earned">
                            <span class="badge-req__icon"><?php echo getBadgeSvg('check'); ?></span>
                            Earned on <?php echo date('M j, Y', strtotime($earned_badge_info['unlocked_at'])); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon"><?php echo getBadgeSvg('lock'); ?></div>
                    <h3 class="empty-state__title">No badges earned yet in this group</h3>
                    <p class="empty-state__text">Start adding items and tracking consumption to unlock your first badges in <?php echo htmlspecialchars($group['group_name']); ?>!</p>
                </div>
            <?php endif; ?>

            <!-- Available Badges -->
            <h4 class="subsection-title subsection-title--available">
                <span class="subsection-title__icon"><?php echo getBadgeSvg('target'); ?></span>
                Available Badges
            </h4>
            <div class="badges-grid">
                <?php foreach ($group_badge_details as $badge_id => $badge_info):
                    $is_earned        = in_array($badge_id, $earned_group_badges);
                    $req_met          = getBadgeRequirementMet($badge_id, $group['group_type'], $group_stats);
                    $earned_badge_info = null;
                    if ($is_earned) {
                        foreach ($earned_badges as $eb) {
                            if ($eb['badge_id'] == $badge_id) { $earned_badge_info = $eb; break; }
                        }
                    }
                ?>
                <div class="badge-card <?php echo $is_earned ? 'badge-card--earned' : 'badge-card--locked'; ?>">
                    <span class="badge-status <?php echo $is_earned ? 'badge-status--earned' : 'badge-status--locked'; ?>">
                        <span class="badge-status__icon">
                            <?php echo $is_earned ? getBadgeSvg('check') : getBadgeSvg('lock'); ?>
                        </span>
                        <?php echo $is_earned ? 'Unlocked' : 'Locked'; ?>
                    </span>

                    <div class="badge-header">
                        <div class="badge-icon <?php echo $is_earned ? 'badge-icon--earned' : ''; ?>">
                            <?php echo getBadgeSvg($badge_info['icon']); ?>
                        </div>
                        <div class="badge-meta">
                            <p class="badge-name"><?php echo htmlspecialchars($badge_info['name']); ?></p>
                            <span class="badge-category"><?php echo htmlspecialchars($badge_info['category']); ?></span>
                        </div>
                    </div>

                    <p class="badge-description"><?php echo htmlspecialchars($badge_info['description']); ?></p>

                    <p class="badge-requirement <?php echo $is_earned ? 'badge-requirement--earned' : 'badge-requirement--locked'; ?>">
                        <span class="badge-req__icon">
                            <?php echo $is_earned ? getBadgeSvg('check') : getBadgeSvg('lock'); ?>
                        </span>
                        <?php if ($is_earned): ?>
                            Earned on <?php echo date('M j, Y', strtotime($earned_badge_info['unlocked_at'])); ?>
                        <?php else: ?>
                            <?php echo htmlspecialchars($badge_info['requirement']); ?>
                            <?php if ($req_met): ?>
                                <span class="badge-ready">— Ready to unlock!</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>

                    <?php if (!$is_earned && $req_met): ?>
                    <div class="progress-section">
                        <p class="progress-label">Requirement met — Ready to unlock!</p>
                        <div class="progress-bar">
                            <div class="progress-fill progress-fill--full"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

        </div><!-- /.badges-section -->
    </div><!-- /.group-content -->
    <?php endforeach; ?>

</div><!-- /.page-container -->
</main>

<script>
function switchGroup(groupIndex) {
    document.querySelectorAll('.group-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.group-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('group-' + groupIndex).classList.add('active');
    document.querySelectorAll('.group-tab')[groupIndex].classList.add('active');
}
</script>

<?php
$user_groups_query->close();
$earned_badges_result->close();
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>