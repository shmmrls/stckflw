<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./includes/config.php');

// Check if user is customer
$is_customer = isset($_SESSION['role']) && $_SESSION['role'] === 'customer';

$pageCss = (
    '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/no-access.css">' . "\n" .
    '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">'
);

if ($is_customer) {
    include('./includes/header.php');
} else {
    // If not customer, redirect to appropriate page
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ./admin/dashboard.php');
    } else {
        header('Location: ./index.php');
    }
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
            
            <h1 class="no-access-title">Admin Access Required</h1>
            
            <p class="no-access-message">
                This section is restricted to administrators only. As a customer, 
                you have access to StockFlow inventory management and your account dashboard.
            </p>
            
            <p class="no-access-submessage">
                If you believe you should have access to this area, please contact support.
            </p>
            
            <div class="no-access-actions">
    
                <a href="./user/dashboard.php" class="btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Return Home
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
                        <div class="info-box-title">Need Administrative Access?</div>
                        If you require admin privileges, please contact the system administrator or visit our support page for assistance.
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('./includes/footer.php'); ?>