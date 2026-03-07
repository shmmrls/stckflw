<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./includes/config.php');

// Check if user is admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'grocery_admin';

$pageCss = (
    '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/no-access.css">' . "\n" .
    '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">'
);

if ($is_admin) {
    include('./includes/header.php');
} else {
    // If not admin, redirect to product list
    header('Location: ./product-list.php');
    exit;
}
?>

<main class="no-access-page">
    <div class="no-access-container">
        <div class="no-access-card">
            <div class="no-access-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                </svg>
            </div>
            
            <h1 class="no-access-title">Consumer Access Restricted</h1>
            
            <p class="no-access-message">
                As an administrator, you have access to the admin dashboard and management tools, 
                but cannot access consumer features and customer accounts.
            </p>
            
            <p class="no-access-submessage">
                This restriction ensures administrative accounts remain separate from consumer activities.
            </p>
            
            <div class="no-access-actions">
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/index.php" class="btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Go to Dashboard
                </a>
            </div>
            
            <div class="info-box">
                <div class="info-box-content">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div class="info-box-text">
                        <div class="info-box-title">Need Consumer Access?</div>
                        If you need to access consumer features, please use a regular customer account or contact another administrator for assistance.
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('./includes/footer.php'); ?>