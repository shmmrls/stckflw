<?php
ob_start();
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/badge_system.php';

// Initialize database connection
$conn = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user data
$user_stmt = $conn->prepare("SELECT user_id, full_name, email, img_name, role, created_at, last_login FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Check if user data exists, redirect if not
if (!$user_data) {
    header("Location: login.php");
    exit;
}

// Set default values for missing fields
$user_data['full_name'] = $user_data['full_name'] ?? 'User';
$user_data['email'] = $user_data['email'] ?? '';
$user_data['img_name'] = $user_data['img_name'] ?? 'nopfp.jpg';
$user_data['role'] = $user_data['role'] ?? 'customer';
$user_data['created_at'] = $user_data['created_at'] ?? date('Y-m-d H:i:s');
$user_data['last_login'] = $user_data['last_login'] ?? null;

// Ensure default profile image is set in DB for users without one
if (empty($user_data['img_name'])) {
    $defaultImg = 'nopfp.jpg';
    $upd = $conn->prepare("UPDATE users SET img_name = ? WHERE user_id = ?");
    $upd->bind_param("si", $defaultImg, $user_id);
    if ($upd->execute()) {
        $user_data['img_name'] = $defaultImg;
    }
    $upd->close();
}

// Fetch user points
$points_stmt = $conn->prepare("SELECT total_points FROM user_points WHERE user_id = ?");
$points_stmt->bind_param("i", $user_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
$points_data = $points_result->fetch_assoc();
$points_stmt->close();

$total_points = $points_data['total_points'] ?? 0;

// Fetch badges
$badges_stmt = $conn->prepare("
    SELECT COUNT(*) as badges_unlocked 
    FROM user_badges 
    WHERE user_id = ?
");
$badges_stmt->bind_param("i", $user_id);
$badges_stmt->execute();
$badges_result = $badges_stmt->get_result();
$badges_data = $badges_result->fetch_assoc();
$badges_stmt->close();

$badges_unlocked = $badges_data['badges_unlocked'] ?? 0;

// Fetch inventory statistics (all groups user belongs to)
$inventory_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT ci.item_id) as total_items,
        COUNT(CASE WHEN ci.expiry_status = 'near_expiry' THEN 1 END) as near_expiry_items,
        COUNT(CASE WHEN ci.expiry_status = 'expired' THEN 1 END) as expired_items,
        COALESCE(SUM(ci.quantity), 0) as total_quantity
    FROM customer_items ci
    INNER JOIN group_members gm ON ci.group_id = gm.group_id
    WHERE gm.user_id = ?
");
$inventory_stats_stmt->bind_param("i", $user_id);
$inventory_stats_stmt->execute();
$inventory_stats = $inventory_stats_stmt->get_result()->fetch_assoc();
$inventory_stats_stmt->close();

$inventory_stats['total_items'] = $inventory_stats['total_items'] ?? 0;
$inventory_stats['near_expiry_items'] = $inventory_stats['near_expiry_items'] ?? 0;
$inventory_stats['expired_items'] = $inventory_stats['expired_items'] ?? 0;
$inventory_stats['total_quantity'] = $inventory_stats['total_quantity'] ?? 0;

// Fetch user's groups
$groups_stmt = $conn->prepare("
    SELECT 
        g.group_id,
        g.group_name,
        g.group_type,
        gm.member_role,
        gm.joined_at,
        COUNT(DISTINCT gm2.member_id) as member_count
    FROM groups g
    INNER JOIN group_members gm ON g.group_id = gm.group_id
    LEFT JOIN group_members gm2 ON g.group_id = gm2.group_id
    WHERE gm.user_id = ?
    GROUP BY g.group_id
    ORDER BY gm.joined_at DESC
    LIMIT 5
");
$groups_stmt->bind_param("i", $user_id);
$groups_stmt->execute();
$user_groups = $groups_stmt->get_result();
$groups_stmt->close();

// Fetch recent activities (items added)
$activities_stmt = $conn->prepare("
    SELECT 
        ci.item_id,
        ci.item_name,
        ci.quantity,
        ci.unit,
        ci.date_added,
        g.group_name,
        c.category_name
    FROM customer_items ci
    INNER JOIN groups g ON ci.group_id = g.group_id
    INNER JOIN categories c ON ci.category_id = c.category_id
    WHERE ci.created_by = ?
    ORDER BY ci.date_added DESC
    LIMIT 5
");
$activities_stmt->bind_param("i", $user_id);
$activities_stmt->execute();
$recent_activities = $activities_stmt->get_result();
$activities_stmt->close();

// Fetch barcode scan count
$scan_stmt = $conn->prepare("SELECT COUNT(*) as scan_count FROM barcode_scan_history WHERE user_id = ?");
$scan_stmt->bind_param("i", $user_id);
$scan_stmt->execute();
$scan_result = $scan_stmt->get_result();
$scan_data = $scan_result->fetch_assoc();
$scan_stmt->close();

$scan_count = $scan_data['scan_count'] ?? 0;

// Get user level and badges
$level_info = getUserLevel($conn, $user_id);
$user_badges = getUserBadges($conn, $user_id);

$pageCss = ''
  . '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/profile.css">' . "\n"
  . '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="profile-page">
    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-avatar-section">
                <div class="avatar-wrapper">
                    <?php 
                    if (!empty($user_data['img_name'])) {
                        $profile_pic = htmlspecialchars($baseUrl) . '/images/profile_pictures/' . htmlspecialchars($user_data['img_name']);
                    } else {
                        $profile_pic = htmlspecialchars($baseUrl) . '/images/profile_pictures/nopfp.jpg';
                    }
                    ?>
                    <img src="<?php echo $profile_pic; ?>" 
                         alt="Profile Picture" 
                         class="profile-avatar"
                         onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/user/images/profile_pictures/nopfp.jpg';">
                    <div class="avatar-badge"><?php echo strtoupper(substr($user_data['full_name'], 0, 1)); ?></div>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($user_data['full_name']); ?></h1>
                    <p class="profile-email"><?php echo htmlspecialchars($user_data['email']); ?></p>
                    <div class="profile-meta">
                        <span class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <?php echo ucfirst($user_data['role']); ?>
                        </span>
                        <span class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            Member since <?php echo date('M Y', strtotime($user_data['created_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="profile-actions">
                <a href="<?= htmlspecialchars($baseUrl) ?>/user/profile/edit_profile.php" class="btn btn-edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    <span>Edit Profile</span>
                </a>
                <a href="delete_profile.php" class="btn btn-delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
                    </svg>
                    <span>Delete Profile</span>
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/><path d="M3 7.6v12.8A1.6 1.6 0 0 0 4.6 22h9.8"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($inventory_stats['total_items']); ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($total_points); ?></div>
                    <div class="stat-label">Total Points</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($inventory_stats['near_expiry_items']); ?></div>
                    <div class="stat-label">Near Expiry</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($badges_unlocked); ?></div>
                    <div class="stat-label">Badges Earned</div>
                </div>
            </div>
        </div>

        <!-- Level & Badges Section -->
        <div class="profile-level-section">
            <div class="level-card-compact">
                <div class="level-header">
                    <span class="level-icon"><?php echo getLevelIcon($level_info['level']); ?></span>
                    <div class="level-title">
                        <h3><?php echo getLevelName($level_info['level']); ?></h3>
                        <p>Level <?php echo $level_info['level']; ?> of 10</p>
                    </div>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/rewards.php" class="btn-view-all">View Achievements →</a>
                </div>
                <div class="level-progress-mini">
                    <div class="progress-bar-small">
                        <div class="progress-fill-small" style="width: <?php echo $level_info['progress_percentage']; ?>%"></div>
                    </div>
                    <p class="progress-text-small"><?php echo $level_info['points_to_next_level']; ?> points to next level</p>
                </div>
            </div>

            <div class="badges-card-compact">
                <div class="badges-header">
                    <h3>Earned Badges (<?php echo $user_badges->num_rows; ?>/5)</h3>
                </div>
                <div class="badges-mini-grid">
                    <?php 
                    if ($user_badges->num_rows > 0) {
                        while ($badge = $user_badges->fetch_assoc()): ?>
                            <div class="badge-mini" title="<?php echo htmlspecialchars($badge['badge_name']); ?>">
                                <span class="badge-mini-icon">🏅</span>
                                <span class="badge-mini-name"><?php echo htmlspecialchars(substr($badge['badge_name'], 0, 3)); ?></span>
                            </div>
                        <?php endwhile;
                    } else {
                        echo '<p class="no-badges">No badges earned yet. Keep tracking to unlock achievements!</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="info-card">
                <div class="card-header">
                    <h2 class="card-title">My Groups</h2>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/customer/groups/my_groups.php" class="card-action">View All</a>
                </div>
                <div class="info-list">
                    <?php if ($user_groups->num_rows > 0): ?>
                        <?php while ($group = $user_groups->fetch_assoc()): ?>
                            <div class="info-item">
                                <span class="info-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    <?php echo htmlspecialchars($group['group_name']); ?>
                                </span>
                                <span class="info-value">
                                    <span class="group-badge"><?php echo ucfirst(str_replace('_', ' ', $group['group_type'])); ?></span>
                                    <span class="member-count"><?php echo $group['member_count']; ?> member<?php echo $group['member_count'] != 1 ? 's' : ''; ?></span>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <p>You haven't joined any groups yet</p>
                            <a href="create_group.php" class="btn-link">Create or Join Group</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card">
                <div class="card-header">
                    <h2 class="card-title">Activity Summary</h2>
                </div>
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 3h18v18H3zM21 9H3M21 15H3M12 3v18"/>
                            </svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-label">Items Added</div>
                            <div class="activity-value"><?php echo number_format($inventory_stats['total_items']); ?></div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M2 12h20"/>
                            </svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-label">Barcode Scans</div>
                            <div class="activity-value"><?php echo number_format($scan_count); ?></div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-label">Last Login</div>
                            <div class="activity-value"><?php echo $user_data['last_login'] ? date('M d, Y', strtotime($user_data['last_login'])) : 'First login'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title">Recent Activity</h2>
                <a href="my_inventory.php" class="section-link">View All Items →</a>
            </div>

            <?php if ($recent_activities->num_rows > 0): ?>
                <div class="orders-table-wrapper">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Group</th>
                                <th>Quantity</th>
                                <th>Date Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($activity['item_name']); ?></strong></td>
                                    <td><span class="category-badge"><?php echo htmlspecialchars($activity['category_name']); ?></span></td>
                                    <td><?php echo htmlspecialchars($activity['group_name']); ?></td>
                                    <td><?php echo number_format($activity['quantity'], 2); ?> <?php echo htmlspecialchars($activity['unit']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($activity['date_added'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/><path d="M3 7.6v12.8A1.6 1.6 0 0 0 4.6 22h9.8"/>
                    </svg>
                    <h3>No items yet</h3>
                    <p>Start adding items to your inventory to track them here</p>
                    <a href="../customer/item/add_item.php " class="btn btn-primary">Add Your First Item</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>