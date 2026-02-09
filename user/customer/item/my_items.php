<?php
ob_start();
session_start();
require_once(__DIR__ . '/../../../includes/config.php');

// Initialize database connection
$conn = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $baseUrl . "/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Get filter parameters
$filter_group = $_GET['group'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'expiry_date';
$sort_order = $_GET['order'] ?? 'ASC';

// Fetch user's groups for filter dropdown
$groups_stmt = $conn->prepare("
    SELECT DISTINCT g.group_id, g.group_name
    FROM groups g
    INNER JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY g.group_name
");
$groups_stmt->bind_param("i", $user_id);
$groups_stmt->execute();
$user_groups = $groups_stmt->get_result();
$groups_stmt->close();

// Fetch all categories for filter
$categories_stmt = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");

// Build WHERE clause for filters
$where_conditions = ["gm.user_id = ?"];
$params = [$user_id];
$param_types = "i";

if ($filter_group !== 'all') {
    $where_conditions[] = "ci.group_id = ?";
    $params[] = $filter_group;
    $param_types .= "i";
}

if ($filter_category !== 'all') {
    $where_conditions[] = "ci.category_id = ?";
    $params[] = $filter_category;
    $param_types .= "i";
}

if ($filter_status !== 'all') {
    $where_conditions[] = "ci.expiry_status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if (!empty($search_query)) {
    $where_conditions[] = "ci.item_name LIKE ?";
    $params[] = "%$search_query%";
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Validate sort column
$allowed_sort = ['item_name', 'expiry_date', 'quantity', 'purchase_date', 'category_name'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'expiry_date';
}

$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Fetch items with filters and sorting
$items_query = "
    SELECT 
        ci.item_id,
        ci.item_name,
        ci.barcode,
        ci.quantity,
        ci.unit,
        ci.purchase_date,
        ci.expiry_date,
        ci.expiry_status,
        ci.purchased_from,
        c.category_name,
        g.group_name,
        g.group_id
    FROM customer_items ci
    INNER JOIN categories c ON ci.category_id = c.category_id
    INNER JOIN groups g ON ci.group_id = g.group_id
    INNER JOIN group_members gm ON ci.group_id = gm.group_id
    WHERE $where_clause
    ORDER BY $sort_by $sort_order
";

$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param($param_types, ...$params);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items_stmt->close();

// Get summary stats
$stats_query = "
    SELECT 
        COUNT(ci.item_id) as total_items,
        COUNT(CASE WHEN ci.expiry_status = 'fresh' THEN 1 END) as fresh_items,
        COUNT(CASE WHEN ci.expiry_status = 'near_expiry' THEN 1 END) as near_expiry_items,
        COUNT(CASE WHEN ci.expiry_status = 'expired' THEN 1 END) as expired_items,
        COALESCE(SUM(ci.quantity), 0) as total_quantity
    FROM customer_items ci
    INNER JOIN group_members gm ON ci.group_id = gm.group_id
    WHERE gm.user_id = ?
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/my_items.css">';
require_once(__DIR__ . '/../../../includes/header.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="my-items-page">
    <div class="my-items-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">My Inventory</h1>
                    <p class="page-subtitle">Track and manage all your items across groups</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/add_item.php" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add New Item
                    </a>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total_items']); ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>

            <div class="stat-card stat-fresh">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['fresh_items']); ?></div>
                    <div class="stat-label">Fresh</div>
                </div>
            </div>

            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['near_expiry_items']); ?></div>
                    <div class="stat-label">Near Expiry</div>
                </div>
            </div>

            <div class="stat-card stat-danger">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['expired_items']); ?></div>
                    <div class="stat-label">Expired</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="" class="filters-form">
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <div class="search-wrapper">
                        <!-- <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg> -->
                        <input type="text" 
                               id="search" 
                               name="search" 
                               placeholder="Search items..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>

                <div class="filter-group">
                    <label for="group">Group</label>
                    <select id="group" name="group">
                        <option value="all" <?php echo $filter_group === 'all' ? 'selected' : ''; ?>>All Groups</option>
                        <?php 
                        $user_groups->data_seek(0);
                        while ($group = $user_groups->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $group['group_id']; ?>" 
                                    <?php echo $filter_group == $group['group_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['group_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <?php while ($category = $categories_stmt->fetch_assoc()): ?>
                            <option value="<?php echo $category['category_id']; ?>" 
                                    <?php echo $filter_category == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="fresh" <?php echo $filter_status === 'fresh' ? 'selected' : ''; ?>>Fresh</option>
                        <option value="near_expiry" <?php echo $filter_status === 'near_expiry' ? 'selected' : ''; ?>>Near Expiry</option>
                        <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="sort">Sort By</label>
                    <select id="sort" name="sort">
                        <option value="expiry_date" <?php echo $sort_by === 'expiry_date' ? 'selected' : ''; ?>>Expiry Date</option>
                        <option value="item_name" <?php echo $sort_by === 'item_name' ? 'selected' : ''; ?>>Item Name</option>
                        <option value="category_name" <?php echo $sort_by === 'category_name' ? 'selected' : ''; ?>>Category</option>
                        <option value="quantity" <?php echo $sort_by === 'quantity' ? 'selected' : ''; ?>>Quantity</option>
                        <option value="purchase_date" <?php echo $sort_by === 'purchase_date' ? 'selected' : ''; ?>>Purchase Date</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="order">Order</label>
                    <select id="order" name="order">
                        <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-apply">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                        </svg>
                        Apply Filters
                    </button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn-reset">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                        </svg>
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Items List -->
        <div class="items-section">
            <div class="section-header">
                <h2 class="section-title">Items (<?php echo $items_result->num_rows; ?>)</h2>
            </div>

            <?php if ($items_result->num_rows > 0): ?>
                <div class="items-grid">
                    <?php while ($item = $items_result->fetch_assoc()): 
                        // Calculate days until expiry
                        $expiry_date = new DateTime($item['expiry_date']);
                        $today = new DateTime();
                        $days_diff = $today->diff($expiry_date)->days;
                        $is_expired = $today > $expiry_date;
                        
                        $status_class = 'status-' . $item['expiry_status'];
                        $status_icon = '🟢';
                        $status_text = 'Fresh';
                        
                        if ($item['expiry_status'] === 'near_expiry') {
                            $status_icon = '🟡';
                            $status_text = 'Expires Soon';
                        } elseif ($item['expiry_status'] === 'expired') {
                            $status_icon = '🔴';
                            $status_text = 'Expired';
                        }
                    ?>
                        <div class="item-card <?php echo $status_class; ?>">
                            <div class="item-header">
                                <div class="item-status-badge">
                                    <span class="status-icon"><?php echo $status_icon; ?></span>
                                    <span class="status-text"><?php echo $status_text; ?></span>
                                </div>
                                <div class="item-menu">
                                    <button class="menu-trigger">⋮</button>
                                    <div class="menu-dropdown">
                                        <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/edit_item.php?id=<?php echo $item['item_id']; ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                            Edit
                                        </a>
                                        <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/consume_item.php?id=<?php echo $item['item_id']; ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                            Consume
                                        </a>
                                        <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/delete_item.php?id=<?php echo $item['item_id']; ?>" class="danger">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                            Delete
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="item-body">
                                <h3 class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                
                                <div class="item-meta">
                                    <span class="meta-badge"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                    <span class="meta-badge"><?php echo htmlspecialchars($item['group_name']); ?></span>
                                </div>

                                <div class="item-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Quantity</span>
                                        <span class="detail-value"><?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Purchase Date</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($item['purchase_date'])); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Expiry Date</span>
                                        <span class="detail-value expiry-date">
                                            <?php echo date('M d, Y', strtotime($item['expiry_date'])); ?>
                                            <?php if (!$is_expired): ?>
                                                <small>(<?php echo $days_diff; ?> days)</small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($item['purchased_from'])): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">From</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($item['purchased_from']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="item-footer">
                                <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/consume_item.php?id=<?php echo $item['item_id']; ?>" class="btn-consume">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Consume (+3 pts)
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/>
                    </svg>
                    <h3>No items found</h3>
                    <p>Start adding items to your inventory or adjust your filters</p>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/add_item.php" class="btn btn-primary">Add Your First Item</a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once(__DIR__ . '/../../../includes/footer.php'); ?>
<?php ob_end_flush(); ?>