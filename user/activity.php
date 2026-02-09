<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, added, consumed, spoiled, expired
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent'; // recent, oldest

// Build query based on filter
$where_clause = "WHERE ciu.updated_by = ?";
$query_params = [$user_id];
$param_types = "i";

if ($filter !== 'all') {
    $where_clause .= " AND ciu.update_type = ?";
    $query_params[] = $filter;
    $param_types .= "s";
}

$order_clause = ($sort === 'oldest') ? "ORDER BY ciu.update_date ASC" : "ORDER BY ciu.update_date DESC";

// Get inventory updates (consumption, waste logs)
$activity_stmt = $conn->prepare("
    SELECT 
        ciu.update_id,
        ciu.update_type,
        ciu.quantity_change,
        ciu.update_date,
        ciu.notes,
        ci.item_name,
        c.category_name,
        g.group_name,
        ci.unit
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    INNER JOIN categories c ON ci.category_id = c.category_id
    INNER JOIN groups g ON ci.group_id = g.group_id
    $where_clause
    $order_clause
    LIMIT 100
");

// Bind parameters dynamically
if ($filter !== 'all') {
    $activity_stmt->bind_param($param_types, ...$query_params);
} else {
    $activity_stmt->bind_param($param_types, ...$query_params);
}

$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();

// Get points log for additional context
$points_stmt = $conn->prepare("
    SELECT 
        pl.log_id,
        pl.action_type,
        pl.points_earned,
        pl.action_date,
        ci.item_name,
        c.category_name
    FROM points_log pl
    LEFT JOIN customer_items ci ON pl.item_id = ci.item_id
    LEFT JOIN categories c ON ci.category_id = c.category_id
    WHERE pl.user_id = ?
    ORDER BY pl.action_date DESC
    LIMIT 50
");
$points_stmt->bind_param("i", $user_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();

// Get summary stats
$stats_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN update_type = 'added' THEN 1 ELSE 0 END) as items_added,
        SUM(CASE WHEN update_type = 'consumed' THEN 1 ELSE 0 END) as items_consumed,
        SUM(CASE WHEN update_type = 'spoiled' THEN 1 ELSE 0 END) as items_spoiled,
        SUM(CASE WHEN update_type = 'expired' THEN 1 ELSE 0 END) as items_expired,
        COUNT(*) as total_actions
    FROM customer_inventory_updates
    WHERE updated_by = ?
");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get total points
$points_total_stmt = $conn->prepare("SELECT total_points FROM user_points WHERE user_id = ?");
$points_total_stmt->bind_param("i", $user_id);
$points_total_stmt->execute();
$points_total = $points_total_stmt->get_result()->fetch_assoc();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/activity.css">';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="activity-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Activity Timeline</h1>
                <p class="page-subtitle">Track all your inventory actions and point rewards</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="activity-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($points_total['total_points'] ?? 0); ?></div>
                    <div class="stat-label">Total Points</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['items_added'] ?? 0); ?></div>
                    <div class="stat-label">Items Added</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['items_consumed'] ?? 0); ?></div>
                    <div class="stat-label">Items Consumed</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format(($stats['items_spoiled'] ?? 0) + ($stats['items_expired'] ?? 0)); ?></div>
                    <div class="stat-label">Waste Logged</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="activity-filters">
            <div class="filter-group">
                <label>Filter by Action:</label>
                <div class="filter-buttons">
                    <a href="?filter=all&sort=<?php echo $sort; ?>" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?filter=added&sort=<?php echo $sort; ?>" class="filter-btn <?php echo $filter === 'added' ? 'active' : ''; ?>">Added</a>
                    <a href="?filter=consumed&sort=<?php echo $sort; ?>" class="filter-btn <?php echo $filter === 'consumed' ? 'active' : ''; ?>">Consumed</a>
                    <a href="?filter=spoiled&sort=<?php echo $sort; ?>" class="filter-btn <?php echo $filter === 'spoiled' ? 'active' : ''; ?>">Spoiled</a>
                    <a href="?filter=expired&sort=<?php echo $sort; ?>" class="filter-btn <?php echo $filter === 'expired' ? 'active' : ''; ?>">Expired</a>
                </div>
            </div>
            
            <div class="filter-group">
                <label>Sort Order:</label>
                <div class="filter-buttons">
                    <a href="?filter=<?php echo $filter; ?>&sort=recent" class="filter-btn <?php echo $sort === 'recent' ? 'active' : ''; ?>">Newest First</a>
                    <a href="?filter=<?php echo $filter; ?>&sort=oldest" class="filter-btn <?php echo $sort === 'oldest' ? 'active' : ''; ?>">Oldest First</a>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="activity-timeline">
            <h2 class="timeline-title">Your Actions</h2>
            
            <?php if ($activity_result->num_rows === 0): ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <h3>No activity yet</h3>
                    <p>Start adding items or logging consumption to see your activity timeline here.</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php while ($activity = $activity_result->fetch_assoc()): ?>
                        <?php
                            // Determine icon and color based on action type
                            $icon_svg = '';
                            $action_label = 'Unknown';
                            $action_class = 'neutral';
                            
                            switch ($activity['update_type']) {
                                case 'added':
                                    $icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                                    $action_label = 'Added';
                                    $action_class = 'added';
                                    break;
                                case 'consumed':
                                    $icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
                                    $action_label = 'Consumed';
                                    $action_class = 'consumed';
                                    break;
                                case 'spoiled':
                                    $icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
                                    $action_label = 'Spoiled';
                                    $action_class = 'spoiled';
                                    break;
                                case 'expired':
                                    $icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                                    $action_label = 'Expired';
                                    $action_class = 'expired';
                                    break;
                            }
                        ?>
                        <div class="timeline-item activity-<?php echo $action_class; ?>">
                            <div class="timeline-marker">
                                <span class="timeline-icon"><?php echo $icon_svg; ?></span>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <h3 class="activity-action">
                                        <span class="action-label"><?php echo $action_label; ?></span>
                                        <span class="item-name"><?php echo htmlspecialchars($activity['item_name']); ?></span>
                                    </h3>
                                    <span class="timeline-date"><?php echo date('M d, Y H:i', strtotime($activity['update_date'])); ?></span>
                                </div>
                                <div class="timeline-meta">
                                    <span class="badge badge-category"><?php echo htmlspecialchars($activity['category_name']); ?></span>
                                    <span class="badge badge-group"><?php echo htmlspecialchars($activity['group_name']); ?></span>
                                    <span class="quantity">
                                        <?php echo number_format($activity['quantity_change'], 2); ?> <?php echo htmlspecialchars($activity['unit']); ?>
                                    </span>
                                </div>
                                <?php if (!empty($activity['notes'])): ?>
                                    <p class="timeline-notes"><?php echo htmlspecialchars($activity['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Points History -->
        <div class="points-history">
            <h2 class="timeline-title">Point Rewards</h2>
            
            <?php if ($points_result->num_rows === 0): ?>
                <p class="empty-text">No points earned yet. Start tracking items to earn points!</p>
            <?php else: ?>
                <div class="points-list">
                    <?php while ($point = $points_result->fetch_assoc()): ?>
                        <?php
                            $action_text = '';
                            $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
                            
                            if ($point['action_type'] === 'ADD_ITEM') {
                                $action_text = 'Added ' . htmlspecialchars($point['item_name'] ?? 'item');
                                $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                            } elseif ($point['action_type'] === 'CONSUME_ITEM' || $point['action_type'] === 'LOG_CONSUMPTION') {
                                $action_text = 'Consumed ' . htmlspecialchars($point['item_name'] ?? 'item');
                                $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
                            }
                        ?>
                        <div class="points-item">
                            <div class="points-icon"><?php echo $icon_svg; ?></div>
                            <div class="points-info">
                                <p class="points-action"><?php echo $action_text; ?></p>
                                <p class="points-timestamp"><?php echo date('M d, Y H:i', strtotime($point['action_date'])); ?></p>
                            </div>
                            <div class="points-amount">
                                <span class="points-value">+<?php echo $point['points_earned']; ?></span>
                                <span class="points-unit">pts</span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php 
$activity_stmt->close();
$points_stmt->close();
$stats_stmt->close();
$points_total_stmt->close();
$conn->close();
?>