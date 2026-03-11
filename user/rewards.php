<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/customer_auth_check.php';
require_once __DIR__ . '/../includes/badge_system.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user level with group context
$level_info = getUserLevelWithGroup($conn, $user_id);
$user_group_type = $level_info['group_type'];

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

// Get user's earned badges globally
$earned_badges_result = getUserBadges($conn, $user_id);
$earned_badges = [];
$earned_badge_ids = [];
while ($badge = $earned_badges_result->fetch_assoc()) {
    $earned_badges[] = $badge;
    $earned_badge_ids[] = $badge['badge_id'];
}

// Calculate total possible badges based on user's groups
$total_possible_badges = 0;
foreach ($user_groups as $group) {
    $group_type = $group['group_type'];
    // Each group type has 5 possible badges
    $total_possible_badges += 5;
}

// Calculate total earned badges across all groups (per-group logic)
$total_earned_badges = 0;
foreach ($user_groups as $group) {
    $group_id = $group['group_id'];
    $group_type = $group['group_type'];
    
    // Get stats for this specific group
    $group_stats_stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT ci.item_id) as total_items_added,
            SUM(CASE WHEN ciu.update_type = 'consumed' THEN 1 ELSE 0 END) as total_consumed,
            (SELECT total_points FROM user_points WHERE user_id = ?) as total_points
        FROM customer_items ci
        LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id
        WHERE ci.created_by = ? AND ci.group_id = ?
    ");
    $group_stats_stmt->bind_param("iii", $user_id, $user_id, $group_id);
    $group_stats_stmt->execute();
    $group_stats = $group_stats_stmt->get_result()->fetch_assoc();
    $group_stats_stmt->close();
    
    // Count earned badges for this group
    $group_earned_count = 0;
    
    switch ($group_type) {
        case 'household':
            if (($group_stats['total_items_added'] ?? 0) >= 5) $group_earned_count++;
            if (($group_stats['total_consumed'] ?? 0) >= 15) $group_earned_count++;
            if (($group_stats['total_points'] ?? 0) >= 150) $group_earned_count++;
            if (($group_stats['total_consumed'] ?? 0) >= 8) $group_earned_count++;
            if (($group_stats['total_points'] ?? 0) >= 300) $group_earned_count++;
            break;
        case 'co_living':
            if (($group_stats['total_items_added'] ?? 0) >= 3) $group_earned_count++;
            if (($group_stats['total_consumed'] ?? 0) >= 25) $group_earned_count++;
            if (($group_stats['total_points'] ?? 0) >= 200) $group_earned_count++;
            if (($group_stats['total_consumed'] ?? 0) >= 12) $group_earned_count++;
            if (($group_stats['total_points'] ?? 0) >= 400) $group_earned_count++;
            break;
        case 'small_business':
            if (($group_stats['total_items_added'] ?? 0) >= 10) $group_earned_count++;
            if (($group_stats['total_consumed'] ?? 0) >= 30) $group_earned_count++;
            if (($group_stats['total_points'] ?? 0) >= 250) $group_earned_count++;
            if (($group_stats['total_consumed'] ?? 0) >= 15) $group_earned_count++;
            if (($group_stats['total_points'] ?? 0) >= 500) $group_earned_count++;
            break;
    }
    
    $total_earned_badges += $group_earned_count;
}

// Get all available badges
$all_badges_result = getAllBadges($conn);

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/rewards.css">';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="badges-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Achievements & Levels</h1>
                <p class="page-subtitle">Track your progress and unlock badges</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>

        <!-- Level Section -->
        <div class="level-section">
            <div class="level-card">
                <div class="level-display">
                    <div class="level-icon"><?php echo getLevelIcon($level_info['level']); ?></div>
                    <div class="level-info">
                        <h2 class="level-name">
                            <?php echo getLevelName($level_info['level'], $user_group_type); ?>
                        </h2>
                        <p class="level-number">Level <?php echo $level_info['level']; ?> of 10</p>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-section">
                    <div class="progress-header">
                        <span class="progress-label">Progress to Next Level</span>
                        <span class="progress-text">
                            <?php echo $level_info['current_points']; ?> / <?php echo $level_info['next_level_points'] ?? 'Max'; ?> points
                        </span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $level_info['progress_percentage']; ?>%"></div>
                    </div>
                    <div class="progress-footer">
                        <?php if ($level_info['next_level_points']): ?>
                            <span class="points-remaining">
                                <?php echo $level_info['points_to_next_level']; ?> points to level <?php echo $level_info['level'] + 1; ?>
                            </span>
                        <?php else: ?>
                            <span class="max-level">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle;">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                                You've reached the maximum level!
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats -->
                <div class="level-stats">
                    <div class="stat">
                        <p class="stat-value"><?php echo $level_info['current_points']; ?></p>
                        <p class="stat-label">Total Points</p>
                    </div>
                    <div class="stat">
                        <p class="stat-value"><?php echo $total_earned_badges; ?></p>
                        <p class="stat-label">Badges Earned</p>
                    </div>
                    <div class="stat">
                        <p class="stat-value"><?php echo ($total_possible_badges - $total_earned_badges); ?></p>
                        <p class="stat-label">Badges Left</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Badges Section -->
        <div class="badges-section">
            <h2 class="section-title">Achievements</h2>
            <p class="section-subtitle">Unlock badges by hitting milestones</p>

            <div class="badges-grid">
                <?php 
                $all_badges_result->data_seek(0); // Reset pointer
                while ($badge = $all_badges_result->fetch_assoc()): 
                    $is_earned = in_array($badge['badge_id'], $earned_badge_ids);
                ?>
                    <div class="badge-card <?php echo $is_earned ? 'earned' : 'locked'; ?>">
                        <div class="badge-icon-container">
                            <div class="badge-icon">
                                <?php if ($is_earned): ?>
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="8" r="7"/>
                                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                                    </svg>
                                <?php else: ?>
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="badge-info">
                            <h3 class="badge-name"><?php echo htmlspecialchars($badge['badge_name']); ?></h3>
                            <p class="badge-description"><?php echo htmlspecialchars($badge['badge_description']); ?></p>
                            <p class="badge-requirement">
                                <?php if ($badge['points_required']): ?>
                                    Requires <?php echo $badge['points_required']; ?> points
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="badge-status">
                            <?php if ($is_earned): ?>
                                <span class="badge-earned">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="display: inline; vertical-align: middle; margin-right: 4px;">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Unlocked
                                </span>
                            <?php else: ?>
                                <span class="badge-locked">Locked</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Tips Section -->
        <div class="tips-section">
            <h2 class="section-title">How to Earn Points</h2>
            <div class="tips-grid">
                <div class="tip-card">
                    <div class="tip-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </div>
                    <div class="tip-content">
                        <h4>Add Items</h4>
                        <p>+5 points every time you add a grocery item to your inventory</p>
                    </div>
                </div>
                <div class="tip-card">
                    <div class="tip-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                    </div>
                    <div class="tip-content">
                        <h4>Log Consumption</h4>
                        <p>+3 points every time you record consuming an item</p>
                    </div>
                </div>
                <div class="tip-card">
                    <div class="tip-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="tip-content">
                        <h4>Stay Consistent</h4>
                        <p>Regular tracking helps you earn badges and climb levels faster</p>
                    </div>
                </div>
                <div class="tip-card">
                    <div class="tip-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <div class="tip-content">
                        <h4>Level Up</h4>
                        <p>Each level requires more points, unlocking new achievements</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php 
$earned_badges_result->close();
$all_badges_result->close();
$conn->close();
?>