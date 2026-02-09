<?php
require_once __DIR__ . '/includes/config.php';

$pageCss = '<link rel="stylesheet" href="./includes/style/features.css">';

if (isLoggedIn()) {
    if (getCurrentUserRole() === 'grocery_admin') {
        header('Location: grocery_dashboard.php');
    } else {
        header('Location: ' . $baseUrl . '/user/dashboard.php');
    }
    exit();
}

require_once __DIR__ . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="features-page">
    <div class="features-container">
        
        <section class="hero-section">
            <h1 class="hero-title">Complete Feature Guide</h1>
            <p class="hero-subtitle">Everything you need to track groceries, manage inventory, and optimize operations</p>
        </section>
        
        <section class="how-it-works-section">
            <div class="section-header">
                <h2 class="section-title">How StockFlow Works</h2>
                <p class="section-subtitle">Simple, efficient grocery management in four steps</p>
            </div>
            
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3 class="step-title">Create Your Account</h3>
                        <p class="step-description">Sign up with your name, email, and password. Choose your profile type: Household, Co-Living, or Small Business to get started.</p>
                    </div>
                </div>
                
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3 class="step-title">Add Grocery Items</h3>
                        <p class="step-description">Log items using barcode scanning or manual entry. Include item name, category, quantity, purchase date, and expiry date. Earn +5 points per item added.</p>
                    </div>
                </div>
                
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3 class="step-title">Log Consumption</h3>
                        <p class="step-description">Record when you use items to update stock levels automatically. Track your consumption patterns and earn +3 points every time you log usage.</p>
                    </div>
                </div>
                
                <div class="step-card">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3 class="step-title">Earn & Track Progress</h3>
                        <p class="step-description">Accumulate points, unlock achievement badges, and level up. View your progress on the dashboard with consumption trends and inventory insights.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="profile-types-section">
            <div class="section-header">
                <h2 class="section-title">Choose Your Profile</h2>
                <p class="section-subtitle">Tailored features for different tracking needs</p>
            </div>
            
            <div class="types-grid">
                <div class="type-card">
                    <div class="type-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </div>
                    <h3 class="type-title">Household</h3>
                    <p class="type-description">Perfect for families managing home groceries and tracking household consumption patterns.</p>
                    <div class="type-features">
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Personal inventory tracking</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Family meal suggestions</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Shopping list generation</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Points & badges system</span>
                        </div>
                    </div>
                </div>
                
                <div class="type-card">
                    <div class="type-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <h3 class="type-title">Co-Living</h3>
                    <p class="type-description">Ideal for roommates and shared living spaces managing communal grocery inventory together.</p>
                    <div class="type-features">
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Shared inventory access</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Individual activity logs</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Group leaderboards</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Fair contribution tracking</span>
                        </div>
                    </div>
                </div>
                
                <div class="type-card">
                    <div class="type-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg>
                    </div>
                    <h3 class="type-title">Small Business</h3>
                    <p class="type-description">Designed for cafes, restaurants, and small businesses tracking inventory and staff performance.</p>
                    <div class="type-features">
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Staff performance metrics</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Stock turnover reports</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Inventory efficiency tracking</span>
                        </div>
                        <div class="type-feature-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Team collaboration tools</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <section class="core-features-section">
            <div class="section-header">
                <h2 class="section-title">Core Features</h2>
                <p class="section-subtitle">Essential tools for inventory and consumption tracking</p>
            </div>
            
            <div class="feature-details-grid">
                <div class="feature-detail-card">
                    <div class="feature-detail-header">
                        <div class="feature-detail-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 3h18v18H3zM21 9H3M21 15H3M12 3v18"/>
                            </svg>
                        </div>
                        <h3 class="feature-detail-title">Item Logging & Management</h3>
                    </div>
                    <p class="feature-detail-description">Add items through barcode scanning or manual input with complete details: name, quantity, purchase date, and expiry date. Organize by categories (dairy, meat, produce) for better tracking. Automatic stock updates based on consumption logs.</p>
                </div>

                <div class="feature-detail-card">
                    <div class="feature-detail-header">
                        <div class="feature-detail-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                        <h3 class="feature-detail-title">Consumption Tracking</h3>
                    </div>
                    <p class="feature-detail-description">Log consumed items to reflect actual usage and update inventory automatically. Track consumption patterns over time to understand your usage rates. Earn +3 points every time you record consumption.</p>
                </div>

                <div class="feature-detail-card">
                    <div class="feature-detail-header">
                        <div class="feature-detail-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <h3 class="feature-detail-title">Gamification System</h3>
                    </div>
                    <p class="feature-detail-description">Earn points for tracking: +5 points when adding items, +3 points when logging consumption. Unlock digital badges for achievements. Level up your profile and compete on group leaderboards. Context-sensitive rules based on profile type.</p>
                </div>

                <div class="feature-detail-card">
                    <div class="feature-detail-header">
                        <div class="feature-detail-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <h3 class="feature-detail-title">Expiry Monitoring</h3>
                    </div>
                    <p class="feature-detail-description">Visual alerts with color-coded status: Fresh (7+ days), Near Expiry (≤7 days), Expired. Get notifications for items approaching expiration. Monitor all expiry dates from your dashboard to maintain fresh inventory.</p>
                </div>

                <div class="feature-detail-card">
                    <div class="feature-detail-header">
                        <div class="feature-detail-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                        </div>
                        <h3 class="feature-detail-title">Shopping List Generation</h3>
                    </div>
                    <p class="feature-detail-description">Automatic list creation based on low stock levels and consumption patterns. Includes items approaching expiry that need replacement. Manually edit lists to add or remove items as needed.</p>
                </div>

                <div class="feature-detail-card">
                    <div class="feature-detail-header">
                        <div class="feature-detail-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                        <h3 class="feature-detail-title">Visual Dashboard</h3>
                    </div>
                    <p class="feature-detail-description">View consumption trends, inventory status, and points earned at a glance. Track progress with charts and indicators. See summary cards for total items, near-expiry alerts, and achievement milestones.</p>
                </div>
            </div>
        </section>

        <section class="advanced-features-section">
            <div class="section-header">
                <h2 class="section-title">Advanced Features</h2>
                <p class="section-subtitle">Additional capabilities for power users</p>
            </div>

            <div class="advanced-grid">
                <div class="advanced-card">
                    <div class="advanced-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <div class="advanced-content">
                        <h3 class="advanced-title">Multi-User Collaboration</h3>
                        <p class="advanced-description">Multiple users manage the same inventory with individual activity tracking. Create or join groups using invite codes. View activity logs for transparency and accountability.</p>
                    </div>
                </div>

                <div class="advanced-card">
                    <div class="advanced-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                    <div class="advanced-content">
                        <h3 class="advanced-title">Reports & Analytics</h3>
                        <p class="advanced-description">Weekly and monthly reports on inventory efficiency and consumption behavior. Export reports for documentation. View stock turnover rates and performance metrics.</p>
                    </div>
                </div>

                <div class="advanced-card">
                    <div class="advanced-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
                        </svg>
                    </div>
                    <div class="advanced-content">
                        <h3 class="advanced-title">Inventory Logs (Optional)</h3>
                        <p class="advanced-description">Record item disposal with quantity and reason for comprehensive tracking. View disposal summaries to identify patterns. Maintain historical records for accountability and operational improvement.</p>
                    </div>
                </div>

                <div class="advanced-card">
                    <div class="advanced-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <div class="advanced-content">
                        <h3 class="advanced-title">Leaderboards</h3>
                        <p class="advanced-description">Group rankings based on points earned. Compare participation levels within your household or team. Promote friendly competition and engagement.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <h2 class="cta-title">Ready to Experience All Features?</h2>
            <p class="cta-description">Start managing your groceries with StockFlow's complete feature set. Earn points, unlock badges, and build better inventory habits.</p>
            <div class="cta-buttons">
                <a href="user/register.php" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                    </svg>
                    Get Started Free
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Back to Home
                </a>
            </div>
        </section>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>