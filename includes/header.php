<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockFlow</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/style.css">
    <?php if (isset($pageCss)) echo $pageCss; ?>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/script.js" defer></script>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/includes/style/components/header-extras.css">
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/includes/js/components/header-extras.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/alert.php'; ?>

<?php
// Initialize database connection if not already established
if (!isset($conn) || $conn === null) {
    $conn = getDBConnection();
}
?>

<?php if (isLoggedIn()): 
    // Fetch user data for dropdown
    $header_user_id = (int) $_SESSION['user_id'];
    $header_stmt = $conn->prepare("SELECT full_name, img_name, role FROM users WHERE user_id = ?");
    $header_stmt->bind_param("i", $header_user_id);
    $header_stmt->execute();
    $header_result = $header_stmt->get_result();
    $header_user = $header_result->fetch_assoc();
    $header_stmt->close();

    if (empty($header_user['img_name'])) {
        $header_user['img_name'] = 'nopfp.jpg';
    }

    $profile_pic = htmlspecialchars($baseUrl) . '/images/profile_pictures/' . htmlspecialchars($header_user['img_name']);
    $user_name = htmlspecialchars($header_user['full_name'] ?? 'User');
    $user_role = ucfirst($header_user['role'] ?? 'customer');
    $current_role = getCurrentUserRole();
?>

<header class="header-container">
    <div class="header-main">
        <a href="<?php echo htmlspecialchars($baseUrl); ?>/index.php" class="logo">
            <img src="<?php echo htmlspecialchars($baseUrl); ?>/assets/logo/logo1.png" alt="StockFlow" class="logo-img">
        </a>

        <nav class="nav-container" id="mobile-nav">
            <ul class="main-nav">
                <?php if ($current_role === 'grocery_admin'): ?>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/grocery_dashboard.php" class="nav-link">Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/items/grocery_items.php" class="nav-link">Inventory</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/product_catalog/view_product_catalog.php" class="nav-link">Product Catalog</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/suppliers/view_suppliers.php" class="nav-link">Suppliers</a></li>
                <?php else: ?>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/dashboard.php" class="nav-link">Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/my_items.php" class="nav-link">My Items</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/item/add_item.php" class="nav-link">Add Item</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/customer/groups/my_groups.php" class="nav-link">Groups</a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/profile/profile.php" class="nav-link">Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="header-right-actions">
            <?php include __DIR__ . '/notification_bell.php'; ?>

            <div class="account-dropdown-wrapper">
                <button class="icon-btn account-dropdown-btn" aria-label="Account" type="button">
                    <img src="<?php echo $profile_pic; ?>" 
                         alt="Profile" 
                         class="account-profile-img"
                         onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/user/images/profile_pictures/nopfp.jpg';">
                </button>
                <div class="account-dropdown-menu">
                    <div class="account-dropdown-header">
                        <img src="<?php echo $profile_pic; ?>" 
                             alt="Profile" 
                             class="account-dropdown-avatar"
                             onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/user/images/profile_pictures/nopfp.jpg';">
                        <div>
                            <div class="account-dropdown-name"><?php echo $user_name; ?></div>
                            <div class="account-dropdown-role"><?php echo $user_role; ?></div>
                        </div>
                    </div>
                    <div class="account-dropdown-divider"></div>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/<?php echo $current_role === 'grocery_admin' ? 'grocery' : 'user'; ?>/profile/profile.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span>Profile</span>
                    </a>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/<?php echo $current_role === 'grocery_admin' ? 'grocery/grocery_dashboard' : 'user/dashboard'; ?>.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                    <div class="account-dropdown-divider"></div>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/logout.php" class="account-dropdown-item" style="color: #fc8181;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>
        </div>

        <button class="hamburger-btn" aria-label="Menu">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>
    </div>
</header>

<?php else: ?>
<!-- Not logged in - show minimal header -->
<header class="header-container">
    <div class="header-main">
        <a href="<?php echo htmlspecialchars($baseUrl); ?>/index.php" class="logo">
            <img src="<?php echo htmlspecialchars($baseUrl); ?>/assets/logo/logo1.png" alt="StockFlow" class="logo-img">
        </a>

        <nav class="nav-container" id="mobile-nav">
            <ul class="main-nav">
                <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/index.php" class="nav-link">Home</a></li>
                <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/about.php" class="nav-link">About</a></li>
                <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/features.php" class="nav-link">Features</a></li>
            </ul>
        </nav>

        <div class="header-right-actions">
            <div class="account-dropdown-wrapper">
                <button class="icon-btn account-dropdown-btn" aria-label="Account" type="button">
                    <svg width="24" height="24" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1">
                        <circle cx="10" cy="7" r="3.5"/>
                        <path d="M4 18c0-3.5 2.5-6 6-6s6 2.5 6 6"/>
                    </svg>
                </button>
                <div class="account-dropdown-menu">
                    <div class="account-dropdown-header">
                        <div class="account-dropdown-role" style="text-align: center;">Please sign in or create an account</div>
                    </div>
                    <div class="account-dropdown-divider"></div>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/login.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <polyline points="10 17 15 12 10 7"/>
                            <line x1="15" y1="12" x2="3" y2="12"/>
                        </svg>
                        <span>Login</span>
                    </a>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/user/register.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        <span>Sign Up</span>
                    </a>
                </div>
            </div>
        </div>

        <button class="hamburger-btn" aria-label="Menu">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>
    </div>
</header>
<?php endif; ?>

</body>
</html>