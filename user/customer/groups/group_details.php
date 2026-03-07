<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/customer_auth_check.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Update expired status for items that have passed expiry date
require_once __DIR__ . '/../../../includes/auto_waste_logging.php';
updateExpiredStatus($conn);

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
        g.created_by,
        u.full_name as created_by_name,
        (SELECT COUNT(DISTINCT gm.user_id) FROM group_members gm WHERE gm.group_id = g.group_id) as member_count,
        (SELECT COUNT(DISTINCT ci.item_id) FROM customer_items ci WHERE ci.group_id = g.group_id) as total_items,
        (SELECT COUNT(DISTINCT ci.item_id) FROM customer_items ci WHERE ci.group_id = g.group_id AND ci.expiry_status = 'fresh') as fresh_items,
        (SELECT COUNT(DISTINCT ci.item_id) FROM customer_items ci WHERE ci.group_id = g.group_id AND ci.expiry_status = 'near_expiry') as near_expiry_items,
        (SELECT COUNT(DISTINCT ci.item_id) FROM customer_items ci WHERE ci.group_id = g.group_id AND ci.expiry_status = 'expired') as expired_items,
        (SELECT COALESCE(SUM(ci.quantity), 0) FROM customer_items ci WHERE ci.group_id = g.group_id) as total_quantity
    FROM groups g
    LEFT JOIN users u ON g.created_by = u.user_id
    WHERE g.group_id = ?
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

// Get recent activities (consumption, additions, etc.) with proper expiry calculation
$items_stmt = $conn->prepare("
    SELECT 
        ciu.update_id,
        ciu.update_type,
        ciu.quantity_change,
        ciu.update_date,
        ciu.notes,
        ci.item_id,
        ci.item_name,
        ci.quantity,
        ci.unit,
        ci.expiry_date,
        ci.expiry_status,
        ci.date_added,
        c.category_name,
        u.full_name as added_by_name
    FROM customer_inventory_updates ciu
    INNER JOIN customer_items ci ON ciu.item_id = ci.item_id
    LEFT JOIN categories c ON ci.category_id = c.category_id
    LEFT JOIN users u ON ciu.updated_by = u.user_id
    WHERE ci.group_id = ?
    ORDER BY ciu.update_date DESC
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

// Handle leave group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_group'])) {
    // Check if user is the creator
    $creator_check = $conn->prepare("SELECT created_by FROM groups WHERE group_id = ?");
    $creator_check->bind_param("i", $group_id);
    $creator_check->execute();
    $creator_result = $creator_check->get_result()->fetch_assoc();
    
    if ($creator_result['created_by'] == $user_id) {
        $error = "As the group creator, you must delete the group instead of leaving it. This will remove all members and delete the group permanently.";
    } else {
        $leave_stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        $leave_stmt->bind_param("ii", $group_id, $user_id);
        
        if ($leave_stmt->execute()) {
            $success = "You have left the group successfully";
            header("Location: my_groups.php");
            exit;
        } else {
            $error = "Failed to leave group";
        }
    }
}

// Handle delete group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group'])) {
    // Check if user is the creator
    $creator_check = $conn->prepare("SELECT created_by FROM groups WHERE group_id = ?");
    $creator_check->bind_param("i", $group_id);
    $creator_check->execute();
    $creator_result = $creator_check->get_result()->fetch_assoc();
    
    if ($creator_result['created_by'] != $user_id) {
        $error = "Only the group creator can delete the group";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete all group members
            $delete_members = $conn->prepare("DELETE FROM group_members WHERE group_id = ?");
            $delete_members->bind_param("i", $group_id);
            $delete_members->execute();
            
            // Delete all customer items in this group
            $delete_items = $conn->prepare("DELETE FROM customer_items WHERE group_id = ?");
            $delete_items->bind_param("i", $group_id);
            $delete_items->execute();
            
            // Delete the group
            $delete_group = $conn->prepare("DELETE FROM groups WHERE group_id = ?");
            $delete_group->bind_param("i", $group_id);
            $delete_group->execute();
            
            $conn->commit();
            $success = "Group deleted successfully";
            header("Location: my_groups.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to delete group: " . $e->getMessage();
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

        <!-- Group Actions -->
        <div class="group-actions">
            <?php if ($group['created_by'] == $user_id): ?>
                <!-- Delete Group Button for Creator -->
                <button type="button" class="btn btn-danger" onclick="openDeleteModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    Delete Group
                </button>
            <?php else: ?>
                <!-- Leave Group Button for Members -->
                <button type="button" class="btn btn-secondary" onclick="openLeaveModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Leave Group
                </button>
            <?php endif; ?>
        </div>

        <!-- Delete Group Modal -->
        <div id="deleteModal" class="modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <div class="modal-icon modal-icon-danger">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </div>
                    <h3 class="modal-title">Delete Group</h3>
                </div>
                <div class="modal-body">
                    <p class="modal-message">Are you sure you want to delete this group?</p>
                    <p class="modal-warning">This will permanently delete the group and remove all members. This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="delete_group" value="1">
                        <button type="submit" class="btn btn-danger">Delete Group</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Leave Group Modal -->
        <div id="leaveModal" class="modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <div class="modal-icon modal-icon-warning">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </div>
                    <h3 class="modal-title">Leave Group</h3>
                </div>
                <div class="modal-body">
                    <p class="modal-message">Are you sure you want to leave this group?</p>
                    <p class="modal-warning">You will lose access to all items and activities in this group.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeLeaveModal()">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="leave_group" value="1">
                        <button type="submit" class="btn btn-secondary">Leave Group</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Remove Member Modal -->
        <div id="removeMemberModal" class="modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <div class="modal-icon modal-icon-warning">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </div>
                    <h3 class="modal-title">Remove Member</h3>
                </div>
                <div class="modal-body">
                    <p class="modal-message">Are you sure you want to remove <span id="removeMemberName" class="member-name-highlight"></span> from this group?</p>
                    <p class="modal-warning">They will lose access to all items and activities in this group. They can rejoin using the invitation code if needed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRemoveMemberModal()">Cancel</button>
                    <form id="removeMemberForm" method="POST" style="display: inline;">
                        <input type="hidden" name="remove_member" value="1">
                        <input type="hidden" id="removeMemberId" name="member_id" value="">
                        <button type="submit" class="btn btn-danger">Remove Member</button>
                    </form>
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
                            <button type="button" class="btn-remove" onclick="openRemoveMemberModal(<?php echo $member['member_id']; ?>, '<?php echo htmlspecialchars($member['full_name']); ?>')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                                Remove
                            </button>
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
                        <p>No activity yet. Start adding or consuming items!</p>
                    </div>
                <?php else: ?>
                    <div class="items-list">
                        <?php while ($activity = $recent_items->fetch_assoc()): 
                            // Determine action icon and label
                            $action_icon = '';
                            $action_label = '';
                            $action_class = '';
                            
                            switch ($activity['update_type']) {
                                case 'added':
                                    $action_icon = '➕';
                                    $action_label = 'Added';
                                    $action_class = 'added';
                                    break;
                                case 'consumed':
                                    $action_icon = '✅';
                                    $action_label = 'Consumed';
                                    $action_class = 'consumed';
                                    break;
                                case 'spoiled':
                                    $action_icon = '🗑️';
                                    $action_label = 'Spoiled';
                                    $action_class = 'wasted';
                                    break;
                                case 'expired':
                                    $action_icon = '⏰';
                                    $action_label = 'Expired';
                                    $action_class = 'wasted';
                                    break;
                            }
                            
                            // Determine status icon and label based on database expiry_status
                            $status_icon = '🟢';
                            $status_label = 'Fresh';
                            $status_class = 'fresh';
                            
                            if ($activity['expiry_status'] === 'near_expiry') {
                                $status_icon = '🟡';
                                $status_label = 'Expires Soon';
                                $status_class = 'near_expiry';
                            } elseif ($activity['expiry_status'] === 'expired') {
                                $status_icon = '�';
                                $status_label = 'Expired';
                                $status_class = 'expired';
                            }
                        ?>
                        <div class="item-row activity-<?php echo $action_class; ?>">
                            <div class="item-info">
                                <div class="item-name">
                                    <?php echo $action_icon . ' ' . htmlspecialchars($activity['item_name']); ?>
                                    <span class="action-badge action-<?php echo $action_class; ?>"><?php echo $action_label; ?></span>
                                </div>
                                <div class="item-meta">
                                    <span class="item-category"><?php echo htmlspecialchars($activity['category_name']); ?></span>
                                    <span class="item-separator">•</span>
                                    <span class="item-added-by">by <?php echo htmlspecialchars($activity['added_by_name']); ?></span>
                                    <span class="item-separator">•</span>
                                    <span class="item-date"><?php echo date('M d, Y H:i', strtotime($activity['update_date'])); ?></span>
                                </div>
                                <?php if (!empty($activity['notes'])): ?>
                                    <div class="item-notes"><?php echo htmlspecialchars($activity['notes']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="item-details">
                                <div class="item-quantity">
                                    <?php 
                                    if ($activity['update_type'] === 'consumed' || $activity['update_type'] === 'spoiled' || $activity['update_type'] === 'expired') {
                                        echo '-' . $activity['quantity_change'] . ' ' . $activity['unit'];
                                    } else {
                                        echo $activity['quantity'] . ' ' . $activity['unit'];
                                    }
                                    ?>
                                </div>
                                <?php if ($activity['update_type'] === 'added'): ?>
                                <div class="item-expiry">
                                    <span class="expiry-badge expiry-<?php echo $status_class; ?>">
                                        <?php echo $status_icon . ' ' . $status_label; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
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

// Modal functions
function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

function openLeaveModal() {
    document.getElementById('leaveModal').classList.add('active');
}

function closeLeaveModal() {
    document.getElementById('leaveModal').classList.remove('active');
}

function openRemoveMemberModal(memberId, memberName) {
    document.getElementById('removeMemberId').value = memberId;
    document.getElementById('removeMemberName').textContent = memberName;
    document.getElementById('removeMemberModal').classList.add('active');
}

function closeRemoveMemberModal() {
    document.getElementById('removeMemberModal').classList.remove('active');
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php $conn->close(); ?>