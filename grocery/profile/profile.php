<?php
ob_start();
session_start();
require_once __DIR__ . '/../../includes/config.php';

// Initialize database connection
$conn = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Verify user is grocery admin
if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $baseUrl . '/user/customer/dashboard.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user data
$user_stmt = $conn->prepare("SELECT user_id, full_name, email, img_name, role, store_id, created_at, last_login FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Check if user data exists
if (!$user_data) {
    header("Location: ../auth/login.php");
    exit;
}

// Set default values
$user_data['full_name'] = $user_data['full_name'] ?? 'User';
$user_data['email'] = $user_data['email'] ?? '';
$user_data['img_name'] = $user_data['img_name'] ?? 'nopfp.jpg';
$user_data['created_at'] = $user_data['created_at'] ?? date('Y-m-d H:i:s');
$user_data['last_login'] = $user_data['last_login'] ?? null;

// Fetch store information
$store_id = $user_data['store_id'];
$store_data = null;
if ($store_id) {
    $store_stmt = $conn->prepare("SELECT * FROM grocery_stores WHERE store_id = ?");
    $store_stmt->bind_param("i", $store_id);
    $store_stmt->execute();
    $store_data = $store_stmt->get_result()->fetch_assoc();
    $store_stmt->close();
}

// Fetch inventory statistics for the store
$inventory_stats = ['total_items' => 0, 'near_expiry_items' => 0, 'expired_items' => 0, 'low_stock_items' => 0, 'total_value' => 0];
if ($store_id) {
    $inventory_stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_items,
            SUM(CASE WHEN expiry_status = 'near_expiry' THEN 1 ELSE 0 END) as near_expiry_items,
            SUM(CASE WHEN expiry_status = 'expired' THEN 1 ELSE 0 END) as expired_items,
            SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock_items,
            SUM(cost_price * quantity) as total_value
        FROM grocery_items
        WHERE store_id = ?
    ");
    $inventory_stats_stmt->bind_param("i", $store_id);
    $inventory_stats_stmt->execute();
    $inventory_stats = $inventory_stats_stmt->get_result()->fetch_assoc();
    $inventory_stats_stmt->close();
}

// Fetch recent inventory updates
$recent_updates_stmt = $conn->prepare("
    SELECT 
        giu.update_id,
        giu.update_type,
        giu.quantity_change,
        giu.update_date,
        giu.notes,
        gi.item_name
    FROM grocery_inventory_updates giu
    INNER JOIN grocery_items gi ON giu.item_id = gi.item_id
    WHERE giu.store_id = ? AND giu.updated_by = ?
    ORDER BY giu.update_date DESC
    LIMIT 5
");
$recent_updates_stmt->bind_param("ii", $store_id, $user_id);
$recent_updates_stmt->execute();
$recent_updates = $recent_updates_stmt->get_result();
$recent_updates_stmt->close();

// Fetch barcode scan count
$scan_stmt = $conn->prepare("SELECT COUNT(*) as scan_count FROM barcode_scan_history WHERE user_id = ?");
$scan_stmt->bind_param("i", $user_id);
$scan_stmt->execute();
$scan_data = $scan_stmt->get_result()->fetch_assoc();
$scan_stmt->close();
$scan_count = $scan_data['scan_count'] ?? 0;

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
                         onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/images/profile_pictures/nopfp.jpg';">
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
                            Store Admin
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
                <a href="edit_profile.php" class="btn btn-edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    <span>Edit Profile</span>
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
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
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value">₱<?php echo number_format($inventory_stats['total_value'], 2); ?></div>
                    <div class="stat-label">Inventory Value</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
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
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($inventory_stats['low_stock_items']); ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <?php if ($store_data): ?>
            <div class="info-card">
                <div class="card-header">
                    <h2 class="card-title">Store Information</h2>
                    <a href="../store/settings.php" class="card-action">Manage Store</a>
                </div>
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            Store Name
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($store_data['store_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            Address
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($store_data['business_address']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                            Contact
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($store_data['contact_number'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                            Status
                        </span>
                        <span class="info-value">
                            <span class="group-badge"><?php echo $store_data['is_verified'] ? 'Verified' : 'Pending'; ?></span>
                            <span class="member-count"><?php echo $store_data['is_active'] ? 'Active' : 'Inactive'; ?></span>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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
                            <div class="activity-label">Items Managed</div>
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
                <h2 class="section-title">Recent Inventory Updates</h2>
                <a href="../items/grocery_items.php" class="section-link">View All Items →</a>
            </div>

            <?php if ($recent_updates->num_rows > 0): ?>
                <div class="orders-table-wrapper">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Update Type</th>
                                <th>Quantity Change</th>
                                <th>Notes</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($update = $recent_updates->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($update['item_name']); ?></strong></td>
                                    <td><span class="category-badge"><?php echo ucfirst($update['update_type']); ?></span></td>
                                    <td><?php echo ($update['quantity_change'] > 0 ? '+' : '') . number_format($update['quantity_change'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($update['notes'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($update['update_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <h3>No inventory updates yet</h3>
                    <p>Start managing your inventory to see activity here</p>
                    <a href="../items/add_item.php" class="btn btn-primary">Add Your First Item</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>