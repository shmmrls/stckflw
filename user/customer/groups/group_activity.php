<?php
require_once __DIR__ . '/../../../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$group_id) {
    header("Location: my_groups.php");
    exit;
}

// Verify user is member of group
$member_check = $conn->prepare("SELECT member_role FROM group_members WHERE group_id = ? AND user_id = ?");
$member_check->bind_param("ii", $group_id, $user_id);
$member_check->execute();
if ($member_check->get_result()->num_rows === 0) {
    header("Location: my_groups.php");
    exit;
}

// Get group details
$group_stmt = $conn->prepare("SELECT group_name, group_type FROM groups WHERE group_id = ?");
$group_stmt->bind_param("i", $group_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, added, consumed, spoiled, expired

// Build where clause
$where = "WHERE ci.group_id = ?";
$params = [$group_id];
$param_types = "i";

if ($filter !== 'all') {
    $where .= " AND ciu.update_type = ?";
    $params[] = $filter;
    $param_types .= "s";
}

// Get group activity
$activity_stmt = $conn->prepare("
    SELECT 
        ciu.update_id,
        ciu.update_type,
        ciu.quantity_change,
        ciu.update_date,
        ciu.notes,
        ci.item_name,
        c.category_name,
        ci.unit,
        u.full_name,
        u.img_name
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    INNER JOIN categories c ON ci.category_id = c.category_id
    INNER JOIN users u ON ciu.updated_by = u.user_id
    $where
    ORDER BY ciu.update_date DESC
    LIMIT 100
");

$activity_stmt->bind_param($param_types, ...$params);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();

// Get member stats
$member_stats_stmt = $conn->prepare("
    SELECT 
        u.user_id,
        u.full_name,
        u.img_name,
        COUNT(DISTINCT ciu.update_id) as total_actions,
        SUM(CASE WHEN ciu.update_type = 'added' THEN 1 ELSE 0 END) as items_added,
        SUM(CASE WHEN ciu.update_type = 'consumed' THEN 1 ELSE 0 END) as items_consumed
    FROM group_members gm
    INNER JOIN users u ON gm.user_id = u.user_id
    LEFT JOIN customer_items ci ON ci.created_by = u.user_id AND ci.group_id = ?
    LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id
    WHERE gm.group_id = ?
    GROUP BY u.user_id, u.full_name, u.img_name
    ORDER BY total_actions DESC
");
$member_stats_stmt->bind_param("ii", $group_id, $group_id);
$member_stats_stmt->execute();
$member_stats = $member_stats_stmt->get_result();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/group-activity.css">';
require_once __DIR__ . '/../../../includes/header.php';
?>

<main class="group-activity-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title"><?php echo htmlspecialchars($group['group_name']); ?> Activity</h1>
                <p class="page-subtitle"><?php echo ucfirst($group['group_type']); ?> Group · Inventory Tracking Feed</p>
            </div>
            <div class="header-actions">
                <a href="group_details.php?id=<?php echo $group_id; ?>" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Group
                </a>
            </div>
        </div>

        <div class="activity-grid">
            
            <!-- Main Activity Feed -->
            <div class="activity-feed-section">
                
                <!-- Filters -->
                <div class="activity-filters">
                    <a href="?id=<?php echo $group_id; ?>&filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?id=<?php echo $group_id; ?>&filter=added" class="filter-btn <?php echo $filter === 'added' ? 'active' : ''; ?>">Added</a>
                    <a href="?id=<?php echo $group_id; ?>&filter=consumed" class="filter-btn <?php echo $filter === 'consumed' ? 'active' : ''; ?>">Consumed</a>
                    <a href="?id=<?php echo $group_id; ?>&filter=spoiled" class="filter-btn <?php echo $filter === 'spoiled' ? 'active' : ''; ?>">Spoiled</a>
                    <a href="?id=<?php echo $group_id; ?>&filter=expired" class="filter-btn <?php echo $filter === 'expired' ? 'active' : ''; ?>">Expired</a>
                </div>

                <!-- Activity Timeline -->
                <div class="activity-timeline">
                    <h2>Recent Activity</h2>
                    
                    <?php if ($activity_result->num_rows === 0): ?>
                        <div class="empty-state">
                            <p>No activity yet</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php while ($activity = $activity_result->fetch_assoc()): ?>
                                <?php
                                    $icon_svg = '';
                                    $action = 'Unknown';
                                    $bg_class = 'neutral';
                                    
                                    switch ($activity['update_type']) {
                                        case 'added':
                                            $icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                                            $action = 'added';
                                            $bg_class = 'added';
                                            break;
                                        case 'consumed':
                                            $icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
                                            $action = 'consumed';
                                            $bg_class = 'consumed';
                                            break;
                                        case 'spoiled':
                                            $icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
                                            $action = 'spoiled';
                                            $bg_class = 'spoiled';
                                            break;
                                        case 'expired':
                                            $icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                                            $action = 'expired';
                                            $bg_class = 'expired';
                                            break;
                                    }
                                ?>
                                <div class="timeline-item item-<?php echo $bg_class; ?>">
                                    <div class="timeline-user">
                                        <img src="<?php echo htmlspecialchars($baseUrl); ?>/images/profile_pictures/<?php echo htmlspecialchars($activity['img_name']); ?>" 
                                             alt="<?php echo htmlspecialchars($activity['full_name']); ?>" 
                                             class="user-avatar">
                                    </div>
                                    <div class="timeline-body">
                                        <div class="action-header">
                                            <strong class="user-name"><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                            <span class="action-text"><?php echo ucfirst($action); ?></span>
                                            <span class="item-badge"><?php echo htmlspecialchars($activity['item_name']); ?></span>
                                        </div>
                                        <div class="action-meta">
                                            <span class="category"><?php echo htmlspecialchars($activity['category_name']); ?></span>
                                            <span class="quantity"><?php echo number_format($activity['quantity_change'], 2); ?> <?php echo htmlspecialchars($activity['unit']); ?></span>
                                        </div>
                                        <span class="timestamp"><?php echo date('M d, H:i', strtotime($activity['update_date'])); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leaderboard Sidebar -->
            <aside class="activity-sidebar">
                <div class="member-stats-card">
                    <h3>Member Activity</h3>
                    <div class="leaderboard">
                        <?php if ($member_stats->num_rows === 0): ?>
                            <p class="empty-text">No members</p>
                        <?php else: ?>
                            <?php $rank = 1; while ($member = $member_stats->fetch_assoc()): ?>
                                <div class="leaderboard-item">
                                    <div class="rank">
                                        <?php if ($rank === 1): ?>
                                            <span class="rank-badge gold">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                    <circle cx="12" cy="12" r="10" fill="#FFD700"/>
                                                    <text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#000">1</text>
                                                </svg>
                                            </span>
                                        <?php elseif ($rank === 2): ?>
                                            <span class="rank-badge silver">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                    <circle cx="12" cy="12" r="10" fill="#C0C0C0"/>
                                                    <text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#000">2</text>
                                                </svg>
                                            </span>
                                        <?php elseif ($rank === 3): ?>
                                            <span class="rank-badge bronze">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                    <circle cx="12" cy="12" r="10" fill="#CD7F32"/>
                                                    <text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#000">3</text>
                                                </svg>
                                            </span>
                                        <?php else: ?>
                                            <span class="rank-number"><?php echo $rank; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <img src="<?php echo htmlspecialchars($baseUrl); ?>/images/profile_pictures/<?php echo htmlspecialchars($member['img_name']); ?>" 
                                         alt="<?php echo htmlspecialchars($member['full_name']); ?>" 
                                         class="member-avatar">
                                    <div class="member-info">
                                        <p class="member-name"><?php echo htmlspecialchars($member['full_name']); ?></p>
                                        <p class="member-stats">
                                            <span class="stat"><?php echo $member['items_added'] ?? 0; ?> added</span>
                                            <span class="stat"><?php echo $member['items_consumed'] ?? 0; ?> consumed</span>
                                        </p>
                                    </div>
                                    <div class="action-count">
                                        <?php echo $member['total_actions'] ?? 0; ?> <span class="label">actions</span>
                                    </div>
                                </div>
                                <?php $rank++; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php 
$activity_stmt->close();
$member_stats_stmt->close();
$group_stmt->close();
$conn->close();
?>