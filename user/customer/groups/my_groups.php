<?php
require_once __DIR__ . '/../../../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user's groups with summary
$stmt = $conn->prepare("
    SELECT 
        g.group_id,
        g.group_name,
        g.group_type,
        g.invitation_code,
        gm.member_role,
        COUNT(DISTINCT gm2.user_id) as member_count,
        COUNT(DISTINCT ci.item_id) as total_items,
        SUM(CASE WHEN ci.expiry_status = 'near_expiry' THEN 1 ELSE 0 END) as near_expiry_items,
        SUM(CASE WHEN ci.expiry_status = 'expired' THEN 1 ELSE 0 END) as expired_items
    FROM groups g
    INNER JOIN group_members gm ON g.group_id = gm.group_id
    LEFT JOIN group_members gm2 ON g.group_id = gm2.group_id
    LEFT JOIN customer_items ci ON g.group_id = ci.group_id
    WHERE gm.user_id = ?
    GROUP BY g.group_id, g.group_name, g.group_type, g.invitation_code, gm.member_role
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$groups = $stmt->get_result();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/my_groups.css">';
require_once __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="groups-page">
    <div class="groups-container">
        
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">My Groups</h1>
                    <p class="page-subtitle">Manage and collaborate with your inventory groups</p>
                </div>
                <div class="header-actions">
                    <a href="create_group.php" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Create or Join Group
                    </a>
                </div>
            </div>
        </div>

        <?php if ($groups->num_rows === 0): ?>
            <div class="empty-state-section">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <h2 class="empty-title">No Groups Yet</h2>
                <p class="empty-description">You haven't joined any groups yet. Create your first group to start collaborating on inventory management.</p>
                <a href="create_group.php" class="btn-primary btn-large">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Create Your First Group
                </a>
            </div>
        <?php else: ?>
            <div class="groups-grid">
                <?php while ($group = $groups->fetch_assoc()): ?>
                <div class="group-card">
                    <div class="group-header">
                        <div class="group-info">
                            <h3 class="group-name"><?php echo htmlspecialchars($group['group_name']); ?></h3>
                            <div class="group-badges">
                                <span class="badge badge-type">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <?php if ($group['group_type'] === 'family'): ?>
                                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        <?php elseif ($group['group_type'] === 'shared'): ?>
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                        <?php else: ?>
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                        <?php endif; ?>
                                    </svg>
                                    <?php echo ucfirst(str_replace('_', ' ', $group['group_type'])); ?>
                                </span>
                                <span class="badge badge-role">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <?php if ($group['member_role'] === 'admin'): ?>
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                        <?php else: ?>
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                        <?php endif; ?>
                                    </svg>
                                    <?php echo ucfirst($group['member_role']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="invitation-code">
                            <div class="code-label">Invitation Code</div>
                            <div class="code-value"><?php echo $group['invitation_code']; ?></div>
                        </div>
                    </div>

                    <div class="group-stats">
                        <div class="stat-item">
                            <div class="stat-icon stat-icon-primary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $group['member_count']; ?></div>
                                <div class="stat-label">Members</div>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon stat-icon-success">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                                </svg>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $group['total_items'] ?? 0; ?></div>
                                <div class="stat-label">Items</div>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon stat-icon-warning">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $group['near_expiry_items'] ?? 0; ?></div>
                                <div class="stat-label">Near Expiry</div>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon stat-icon-danger">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $group['expired_items'] ?? 0; ?></div>
                                <div class="stat-label">Expired</div>
                            </div>
                        </div>
                    </div>

                    <div class="group-actions">
                        <a href="group_details.php?id=<?php echo $group['group_id']; ?>" class="btn-view-details">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                            View Details
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php $conn->close(); ?>