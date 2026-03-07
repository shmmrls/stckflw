<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./includes/config.php');

// Check if user is guest (not logged in)
$is_guest = !isset($_SESSION['user_id']);

// If logged in, redirect based on role BEFORE any output
if (!$is_guest) {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'grocery_admin') {
            header('Location: ./admin/dashboard.php');
        } else {
            header('Location: ./product-list.php');
        }
    } else {
        header('Location: ./index.php');
    }
}

// Only include header if we're actually showing the page (user is guest)
$pageCss = (
    '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/no-access.css">' . "\n" .
    '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">'
);

include('./includes/header.php');

// Get current page for redirect after login
$current_page = isset($_SERVER['HTTP_REFERER']) ? basename(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH)) : '';
$redirect_param = !empty($current_page) ? 'redirect=' . urlencode($current_page) : '';
?>

<main class="no-access-page">
    <div class="no-access-container">
        <div class="no-access-card">
            <!-- Icon -->
            <div class="no-access-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                </svg>
            </div>
            
            <!-- Title -->
            <h1 class="no-access-title">Account Required</h1>
            
            <!-- Message -->
            <p class="no-access-message">
                Please log in or create an account to access this feature. 
                This page requires authentication for both customers and administrators to manage their inventory and track stock levels.
            </p>
            
            <!-- Role Info Badges -->
            <div class="role-info">
                <div class="role-badge customer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Customer Access
                </div>
                <div class="role-badge admin">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7v6c0 5.5 3.8 10.7 10 12 6.2-1.3 10-6.5 10-12V7L12 2z"></path>
                    </svg>
                    Admin Access
                </div>
            </div>
            
            <!-- Submessage -->
            <p class="no-access-submessage">
                Join our community today and enjoy exclusive benefits for managing your inventory, tracking stock levels, and reducing waste through smart notifications!
            </p>
            
            <!-- Action Buttons -->
            <div class="no-access-actions">
                <a href="./user/login.php<?php echo !empty($redirect_param) ? '?' . $redirect_param : ''; ?>" class="btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    Log In
                </a>
                <a href="./user/login.php?tab=register<?php echo !empty($redirect_param) ? '&' . $redirect_param : ''; ?>" class="btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    Create Account
                </a>
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <div class="info-box-content">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div class="info-box-text">
                        <div class="info-box-title">Account Information</div>
                        <strong>For Customers:</strong> Having an account allows you to manage inventory, track stock levels, and monitor waste reduction. Simply register and start using StockFlow!
                        <br><br>
                        <strong>For Admin Access:</strong> Only existing administrators can grant admin privileges. 
                    </div>
                </div>
            </div>
            
            <!-- Back Link -->
            <div class="back-link">
                <a href="./index.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</main>

<?php include('./includes/footer.php'); ?>