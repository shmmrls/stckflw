<?php
require_once __DIR__ . '/includes/config.php';

$pageCss = '<link rel="stylesheet" href="./includes/style/about.css">';

require_once __DIR__ . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="about-page">
    <div class="about-container">
        
        <section class="page-header">
            <h1 class="page-title">About StockFlow</h1>
            <p class="page-subtitle">A comprehensive grocery inventory and consumption tracking system designed to improve organization, enhance efficiency, and promote accountability through gamification.</p>
        </section>

        <section class="background-section">
            <h2 class="section-title">The Challenge</h2>
            <p class="section-text">
                Grocery management plays a critical role in both retail stores and households. Grocery stores face challenges with real-time inventory tracking, expiry date monitoring, and balancing stocks with actual consumption rates. These inefficiencies lead to overstocking, understocking, and unnecessary product disposal—affecting operational efficiency and profitability.
            </p>
            <p class="section-text">
                Consumers face similar challenges when managing groceries at home, especially in shared households or small organizations where multiple people are responsible for inventory. Traditional tracking methods often fail due to lack of engagement and accountability, leading to disorganized inventory and inefficient resource management.
            </p>
            <p class="section-text">
                StockFlow addresses these challenges through a gamified approach that makes inventory management engaging, accurate, and sustainable for both grocery stores and consumers.
            </p>
        </section>

        <section class="features-dropdown-section">
            <h2 class="section-title">Explore Features by User Type</h2>
            
            <div class="dropdown-container">
                <div class="dropdown-item">
                    <button class="dropdown-header" onclick="toggleDropdown('consumer')">
                        <div class="dropdown-title-group">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            <h3>Consumer Features</h3>
                        </div>
                        <svg class="dropdown-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    
                    <div class="dropdown-content" id="consumer-dropdown">
                        <div class="features-grid">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/><path d="M3 7.6v12.8A1.6 1.6 0 0 0 4.6 22h9.8"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Personal Inventory Tracking</h3>
                                <p class="feature-description">Log grocery items with purchase dates, quantities, and expiry dates. Keep everything organized in one central place with barcode scanning support for quick item entry.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Consumption Logging</h3>
                                <p class="feature-description">Record when you use items to automatically update stock levels. Track your actual consumption patterns and usage rates over time to make better shopping decisions.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Gamification & Rewards</h3>
                                <p class="feature-description">Earn +5 points for adding items and +3 points for logging consumption. Unlock achievement badges, level up your profile, and compete with household members through engaging challenges.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Expiry Monitoring</h3>
                                <p class="feature-description">Visual alerts for items nearing expiration with color-coded status indicators. Get notified before items expire so you can prioritize their use and maintain fresh inventory.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Group Collaboration</h3>
                                <p class="feature-description">Create or join groups for households, co-living spaces, or small organizations. Share inventory access while tracking individual contributions and maintaining accountability.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Visual Analytics Dashboard</h3>
                                <p class="feature-description">See your inventory at a glance with consumption trends, points earned, and item status. Track your progress with intuitive charts and make data-driven decisions about your grocery habits.</p>
                            </div>
                        </div>
                        
                        <div class="value-props">
                            <h3>Why Consumers Choose StockFlow</h3>
                            <div class="value-grid">
                                <div class="value-item">
                                    <span class="value-number">+5 Points</span>
                                    <p>Per item added to inventory</p>
                                </div>
                                <div class="value-item">
                                    <span class="value-number">+3 Points</span>
                                    <p>Per consumption log entry</p>
                                </div>
                                <div class="value-item">
                                    <span class="value-number">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle;">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                        </svg>
                                    </span>
                                    <p>Unlock achievements & milestones</p>
                                </div>
                                <div class="value-item">
                                    <span class="value-number">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle;">
                                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                                        </svg>
                                    </span>
                                    <p>Track usage patterns & trends</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dropdown-item">
                    <button class="dropdown-header" onclick="toggleDropdown('grocery')">
                        <div class="dropdown-title-group">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                            </svg>
                            <h3>Grocery Store Features</h3>
                        </div>
                        <svg class="dropdown-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    
                    <div class="dropdown-content" id="grocery-dropdown">
                        <div class="features-grid">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Real-Time Inventory Management</h3>
                                <p class="feature-description">Track all products from procurement to shelf to sale. Monitor stock levels in real-time, receive automatic updates, and maintain accurate inventory counts across your entire store.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Automated Expiry Tracking</h3>
                                <p class="feature-description">Never lose revenue to expired products. Get automated alerts for items approaching expiry dates, prioritize sales of near-expiry items, and maintain optimal inventory freshness through proactive management.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Smart Stock Alerts</h3>
                                <p class="feature-description">Receive notifications when stock levels fall below customizable thresholds. Get reorder recommendations based on consumption patterns and sales velocity to prevent stockouts.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Sales & Performance Analytics</h3>
                                <p class="feature-description">Access comprehensive reports on sales trends, consumption rates, and inventory turnover. Make data-driven decisions about purchasing, pricing, and product placement.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 3h18v18H3zM9 3v18"/><path d="M9 9h12"/><path d="M9 15h12"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Category Management</h3>
                                <p class="feature-description">Organize inventory by departments, categories, and subcategories. Track performance across different product lines and optimize shelf space allocation.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                </div>
                                <h3 class="feature-title">Inventory Optimization</h3>
                                <p class="feature-description">Track disposal reasons, identify patterns in inventory losses, and implement strategies to optimize stock management. Monitor the financial impact and improve overall profitability through better inventory control.</p>
                            </div>
                        </div>
                        
                        <div class="value-props">
                            <h3>Why Grocery Stores Choose StockFlow</h3>
                            <div class="value-grid">
                                <div class="value-item">
                                    <span class="value-number">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle;">
                                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                                        </svg>
                                    </span>
                                    <p>Instant inventory updates & tracking</p>
                                </div>
                                <div class="value-item">
                                    <span class="value-number">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle;">
                                            <line x1="23" y1="6" x2="13.5" y2="15.5"/><line x1="14.5" y1="8.5" x2="21" y2="3"/><polyline points="3.5 13 3.5 19 9.5 19"/><polyline points="14 15 11 18 3 18 3 10 6 7"/>
                                        </svg>
                                    </span>
                                    <p>Optimize stock & prevent overstocking</p>
                                </div>
                                <div class="value-item">
                                    <span class="value-number">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle;">
                                            <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                        </svg>
                                    </span>
                                    <p>Prevent losses & optimize purchasing</p>
                                </div>
                                <div class="value-item">
                                    <span class="value-number">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle;">
                                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                                        </svg>
                                    </span>
                                    <p>Make informed business decisions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <h2 class="cta-title">Ready to Get Started?</h2>
            <p class="cta-description">Join StockFlow today and transform the way you manage groceries.</p>
            <div class="cta-buttons">
                <a href="user/register.php" class="btn btn-primary">
                    Sign Up as Consumer
                </a>
                <a href="grocery/register.php" class="btn btn-primary">
                    Register Your Store
                </a>
            </div>
        </section>
        
    </div>
</main>

<script>
function toggleDropdown(type) {
    const dropdown = document.getElementById(type + '-dropdown');
    const allDropdowns = document.querySelectorAll('.dropdown-content');
    const allHeaders = document.querySelectorAll('.dropdown-header');
    
    allDropdowns.forEach(d => {
        if (d.id !== type + '-dropdown') {
            d.classList.remove('active');
        }
    });
    
    allHeaders.forEach(h => {
        if (h !== event.currentTarget) {
            h.classList.remove('active');
        }
    });
    
    dropdown.classList.toggle('active');
    event.currentTarget.classList.toggle('active');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>