<?php
require_once __DIR__ . '/includes/config.php';

$pageCss = '<link rel="stylesheet" href="./includes/style/index.css">';

if (isLoggedIn()) {
    if (getCurrentUserRole() === 'grocery_admin') {
        header('Location: ' . $baseUrl . '/grocery/grocery_dashboard.php');
    } else {
        header('Location: ' . $baseUrl . '/user/dashboard.php');
    }
    exit();
}

require_once __DIR__ . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="landing-page">
    <div class="landing-container">
        
        <section class="hero-section">
            <img src="<?= htmlspecialchars($baseUrl) ?>/assets/logo/logo1.png" alt="StockFlow Logo" class="hero-logo">
            <h1 class="hero-tagline">Revolutionize Your Grocery Management</h1>
            <p class="hero-subtitle">Track inventory. Monitor consumption. Stay organized. Maximize efficiency.</p>
        </section>
        
        <section class="user-types-section">
            <div class="section-header">
                <h2 class="section-title">Choose Your Path</h2>
                <p class="section-subtitle">Whether you're managing a household or running a grocery store, StockFlow has you covered</p>
            </div>
            
            <div class="user-types-grid">
                <div class="user-type-card consumer-card">
                    <div class="user-type-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </div>
                    <h3 class="user-type-title">For Consumers</h3>
                    <p class="user-type-description">
                        Perfect for households, co-living spaces, and small organizations. Track your grocery purchases, monitor consumption patterns, and maintain organized inventory management.
                    </p>
                    
                    <div class="user-type-features">
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Inventory tracking with expiry alerts</span>
                        </div>
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Consumption logging & usage insights</span>
                        </div>
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Gamified rewards (points & badges)</span>
                        </div>
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Group collaboration for shared households</span>
                        </div>
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Visual dashboard with consumption trends</span>
                        </div>
                    </div>
                    
                    <div class="user-type-actions">
                        <a href="user/register.php" class="btn btn-primary btn-block">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <line x1="20" y1="8" x2="20" y2="14"/>
                                <line x1="23" y1="11" x2="17" y2="11"/>
                            </svg>
                            Sign Up as Consumer
                        </a>
                        <a href="user/login.php" class="btn btn-secondary btn-block">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                <polyline points="10 17 15 12 10 7"/>
                                <line x1="15" y1="12" x2="3" y2="12"/>
                            </svg>
                            Consumer Login
                        </a>
                    </div>
                </div>
                
                <!-- Grocery Store Box -->
                <div class="user-type-card grocery-card">
                    <div class="user-type-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                        </svg>
                    </div>
                    <h3 class="user-type-title">For Grocery Stores</h3>
                    <p class="user-type-description">
                        Streamline your store operations with real-time inventory management, expiry tracking, and data-driven insights to minimize waste and maximize profitability.
                    </p>
                    
                    <div class="user-type-features">
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Real-time inventory tracking & updates</span>
                        </div>
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Automated expiry date monitoring</span>
                        </div>
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Stock level alerts & reorder notifications</span>
                        </div>
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Sales & consumption analytics</span>
                        </div>
                        <div class="feature-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Optimize inventory & reduce losses</span>
                        </div>
                    </div>
                    
                    <div class="user-type-actions">
                        <a href="grocery/register.php" class="btn btn-primary btn-block">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17"/>
                            </svg>
                            Register Your Store
                        </a>
                        <a href="grocery/grocery_login.php" class="btn btn-secondary btn-block">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                <polyline points="10 17 15 12 10 7"/>
                                <line x1="15" y1="12" x2="3" y2="12"/>
                            </svg>
                            Grocery Admin Login
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Value Props -->
        <section class="quick-value-section">
            <div class="value-stats">
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                    <h4>Real-Time Tracking</h4>
                    <p>Monitor inventory and consumption as it happens</p>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                        </svg>
                    </div>
                    <h4>Improve Efficiency</h4>
                    <p>Optimize inventory management and operations</p>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <h4>Stay Organized</h4>
                    <p>Keep track of everything in one central platform</p>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </div>
                    <h4>Gamified Experience</h4>
                    <p>Earn points and badges for consistent tracking</p>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <h2 class="cta-title">Ready to Transform Your Grocery Management?</h2>
            <p class="cta-description">Join StockFlow today and experience the future of inventory tracking—whether you're managing a household or running a store.</p>
            <div class="cta-buttons">
                <a href="about.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Learn More About StockFlow
                </a>
            </div>
        </section>
        
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>