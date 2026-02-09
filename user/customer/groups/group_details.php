<?php
require_once __DIR__ . '/../../../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get group ID from URL
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$group_id) {
    header("Location: my_groups.php");
    exit;
}

// Verify user is a member of this group
$member_check = $conn->prepare("SELECT member_role FROM group_members WHERE group_id = ? AND user_id = ?");
$member_check->bind_param("ii", $group_id, $user_id);
$member_check->execute();
$member_result = $member_check->get_result();

if ($member_result->num_rows === 0) {
    header("Location: my_groups.php");
    exit;
}

$current_member = $member_result->fetch_assoc();
$is_admin = ($current_member['member_role'] === 'parent' || $current_member['member_role'] === 'manager');

// Get group details with proper expiry status calculation
$group_stmt = $conn->prepare("
    SELECT 
        g.group_id,
        g.group_name,
        g.group_type,
        g.invitation_code,
        g.created_at,
        u.full_name as created_by_name,
        COUNT(DISTINCT gm.user_id) as member_count,
        COUNT(DISTINCT ci.item_id) as total_items,
        SUM(CASE 
            WHEN ci.expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 
            ELSE 0 
        END) as fresh_items,
        SUM(CASE 
            WHEN ci.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
            AND ci.expiry_date >= CURDATE() THEN 1 
            ELSE 0 
        END) as near_expiry_items,
        SUM(CASE 
            WHEN ci.expiry_date < CURDATE() THEN 1 
            ELSE 0 
        END) as expired_items,
        SUM(ci.quantity) as total_quantity
    FROM groups g
    LEFT JOIN users u ON g.created_by = u.user_id
    LEFT JOIN group_members gm ON g.group_id = gm.group_id
    LEFT JOIN customer_items ci ON g.group_id = ci.group_id
    WHERE g.group_id = ?
    GROUP BY g.group_id
");
$group_stmt->bind_param("i", $group_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();

if (!$group) {
    header("Location: my_groups.php");
    exit;
}

// Get all members
$members_stmt = $conn->prepare("
    SELECT 
        gm.member_id,
        gm.user_id,
        gm.member_role,
        gm.joined_at,
        u.full_name,
        u.email,
        u.img_name,
        COUNT(DISTINCT ci.item_id) as items_added
    FROM group_members gm
    JOIN users u ON gm.user_id = u.user_id
    LEFT JOIN customer_items ci ON gm.user_id = ci.created_by AND ci.group_id = ?
    WHERE gm.group_id = ?
    GROUP BY gm.member_id, gm.user_id, gm.member_role, gm.joined_at, u.full_name, u.email, u.img_name
    ORDER BY gm.joined_at ASC
");
$members_stmt->bind_param("ii", $group_id, $group_id);
$members_stmt->execute();
$members = $members_stmt->get_result();

// Get recent items with proper expiry calculation
$items_stmt = $conn->prepare("
    SELECT 
        ci.item_id,
        ci.item_name,
        ci.quantity,
        ci.unit,
        ci.expiry_date,
        ci.purchase_date,
        ci.date_added,
        c.category_name,
        u.full_name as added_by_name,
        CASE 
            WHEN ci.expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'fresh'
            WHEN ci.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                 AND ci.expiry_date >= CURDATE() THEN 'near_expiry'
            WHEN ci.expiry_date < CURDATE() THEN 'expired'
        END as expiry_status
    FROM customer_items ci
    LEFT JOIN categories c ON ci.category_id = c.category_id
    LEFT JOIN users u ON ci.created_by = u.user_id
    WHERE ci.group_id = ?
    ORDER BY ci.date_added DESC
    LIMIT 10
");
$items_stmt->bind_param("i", $group_id);
$items_stmt->execute();
$recent_items = $items_stmt->get_result();

// Handle member removal
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member']) && $is_admin) {
    $member_id = (int)$_POST['member_id'];
    
    // Don't allow removing yourself
    $check_self = $conn->prepare("SELECT user_id FROM group_members WHERE member_id = ?");
    $check_self->bind_param("i", $member_id);
    $check_self->execute();
    $check_result = $check_self->get_result()->fetch_assoc();
    
    if ($check_result['user_id'] == $user_id) {
        $error = "You cannot remove yourself from the group";
    } else {
        $remove_stmt = $conn->prepare("DELETE FROM group_members WHERE member_id = ? AND group_id = ?");
        $remove_stmt->bind_param("ii", $member_id, $group_id);
        
        if ($remove_stmt->execute()) {
            $success = "Member removed successfully";
            header("Refresh:0");
        } else {
            $error = "Failed to remove member";
        }
    }
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/group_details.css">';
require_once __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="group-details-page">
    <div class="group-details-container">
        
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="my_groups.php" class="breadcrumb-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                </svg>
                Back to My Groups
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Group Header -->
        <div class="group-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="group-title"><?php echo htmlspecialchars($group['group_name']); ?></h1>
                    <div class="group-meta">
                        <span class="meta-badge badge-type">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <?php if ($group['group_type'] === 'household'): ?>
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <?php elseif ($group['group_type'] === 'co_living'): ?>
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                <?php else: ?>
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <?php endif; ?>
                            </svg>
                            <?php echo ucfirst(str_replace('_', ' ', $group['group_type'])); ?>
                        </span>
                        <span class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            Created <?php echo date('M d, Y', strtotime($group['created_at'])); ?>
                        </span>
                        <span class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            by <?php echo htmlspecialchars($group['created_by_name']); ?>
                        </span>
                    </div>
                </div>
                <div class="invitation-card">
                    <div class="invitation-label">Invitation Code</div>
                    <div class="invitation-code"><?php echo $group['invitation_code']; ?></div>
                    <button class="btn-copy" onclick="copyInvitationCode('<?php echo $group['invitation_code']; ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        Copy
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $group['total_items'] ?? 0; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>

            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $group['fresh_items'] ?? 0; ?></div>
                    <div class="stat-label">Fresh Items</div>
                </div>
            </div>

            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $group['near_expiry_items'] ?? 0; ?></div>
                    <div class="stat-label">Near Expiry</div>
                </div>
            </div>

            <div class="stat-card stat-danger">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $group['expired_items'] ?? 0; ?></div>
                    <div class="stat-label">Expired Items</div>
                </div>
            </div>

            <div class="stat-card stat-info">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $group['member_count']; ?></div>
                    <div class="stat-label">Members</div>
                </div>
            </div>

            <div class="stat-card stat-secondary">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($group['total_quantity'] ?? 0, 0); ?></div>
                    <div class="stat-label">Total Quantity</div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            
            <!-- Members Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        Members (<?php echo $group['member_count']; ?>)
                    </h2>
                </div>

                <div class="members-list">
                    <?php while ($member = $members->fetch_assoc()): ?>
                    <div class="member-item">
                        <div class="member-avatar">
                            <?php 
                            if (!empty($member['img_name'])) {
                                $profile_pic = htmlspecialchars($baseUrl) . '/images/profile_pictures/' . htmlspecialchars($member['img_name']);
                            } else {
                                $profile_pic = htmlspecialchars($baseUrl) . '/images/profile_pictures/nopfp.jpg';
                            }
                            ?>
                            <img src="<?php echo $profile_pic; ?>" 
                                 alt="<?php echo htmlspecialchars($member['full_name']); ?>"
                                 onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/images/profile_pictures/nopfp.jpg';">
                        </div>
                        <div class="member-info">
                            <div class="member-name"><?php echo htmlspecialchars($member['full_name']); ?></div>
                            <div class="member-email"><?php echo htmlspecialchars($member['email']); ?></div>
                        </div>
                        <div class="member-meta">
                            <span class="member-role">
                                <?php echo ucfirst($member['member_role']); ?>
                            </span>
                            <span class="member-stats">
                                <?php echo $member['items_added']; ?> items added
                            </span>
                        </div>
                        <?php if ($is_admin && $member['user_id'] != $user_id): ?>
                        <div class="member-actions">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove this member?');">
                                <input type="hidden" name="remove_member" value="1">
                                <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                <button type="submit" class="btn-remove">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Remove
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Recent Items Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        Recent Activity
                    </h2>
                    <div class="header-links">
                        <a href="group_activity.php?id=<?php echo $group_id; ?>" class="btn-link">
                            Activity Feed
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                            </svg>
                        </a>
                        <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/my_items.php?group=<?php echo $group_id; ?>" class="btn-link">
                            View All Inventory
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <?php if ($recent_items->num_rows === 0): ?>
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        <p>No items added yet. Start tracking your inventory!</p>
                    </div>
                <?php else: ?>
                    <div class="items-list">
                        <?php while ($item = $recent_items->fetch_assoc()): 
                            // Calculate expiry status based on development plan
                            $expiry_date = strtotime($item['expiry_date']);
                            $today = strtotime(date('Y-m-d'));
                            $days_until = floor(($expiry_date - $today) / 86400);
                            
                            // Determine status icon and label
                            if ($days_until < 0) {
                                $status_icon = '🔴';
                                $status_label = 'Expired';
                                $status_class = 'expired';
                            } elseif ($days_until <= 7) {
                                $status_icon = '🟡';
                                $status_label = $days_until == 0 ? 'Expires today' : ($days_until == 1 ? 'Expires tomorrow' : $days_until . ' days left');
                                $status_class = 'near_expiry';
                            } else {
                                $status_icon = '🟢';
                                $status_label = 'Fresh (' . $days_until . ' days)';
                                $status_class = 'fresh';
                            }
                        ?>
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="item-meta">
                                    <span class="item-category"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                    <span class="item-separator">•</span>
                                    <span class="item-added-by">Added by <?php echo htmlspecialchars($item['added_by_name']); ?></span>
                                    <span class="item-separator">•</span>
                                    <span class="item-date"><?php echo date('M d, Y', strtotime($item['date_added'])); ?></span>
                                </div>
                            </div>
                            <div class="item-details">
                                <div class="item-quantity"><?php echo $item['quantity'] . ' ' . $item['unit']; ?></div>
                                <div class="item-expiry">
                                    <span class="expiry-badge expiry-<?php echo $status_class; ?>">
                                        <?php echo $status_icon . ' ' . $status_label; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</main>

<script>
function copyInvitationCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target.closest('.btn-copy');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = `
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Copied!
        `;
        setTimeout(() => {
            btn.innerHTML = originalHTML;
        }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php $conn->close(); ?>