<?php

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/includes/style/footer.css">



<footer class="footer">

    <div class="footer-content">

        <div class="footer-grid">

            <div class="footer-column">

                <div class="footer-logo">

                    <img src="<?php echo htmlspecialchars($baseUrl); ?>/assets/logo/logo2.png" alt="StockFlow" class="logo-img">

                </div>

                <p class="footer-tagline">Track what you buy. Track what you use. Earn points.</p>

            </div>



            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'grocery_admin'): ?>

            <div class="footer-column">

                <h4 class="footer-title">Admin Tools</h4>

                <ul class="footer-links">

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/reports/reports.php">Reports</a></li>

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/store/settings.php">Settings</a></li>

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/suppliers/view_suppliers.php">Suppliers</a></li>

                </ul>

            </div>

            <?php elseif (isset($_SESSION['user_id'])): ?>

            <div class="footer-column">

                <h4 class="footer-title">Features</h4>

                <ul class="footer-links">

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/meal_suggestions.php">Meal Ideas</a></li>

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/reports/reports.php">Reports</a></li>

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/shopping_list.php">Shopping List</a></li>

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/activity.php">Activity</a></li>

                </ul>

            </div>

            <?php else: ?>

            <div class="footer-column">

                <h4 class="footer-title">About Us</h4>

                <ul class="footer-links">

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/about.php">About</a></li>

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/features.php">Features</a></li>

                    <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/contact.php">Contact</a></li>

                </ul>

            </div>

            <?php endif; ?>



            <div class="footer-column">

                <h4 class="footer-title">Account</h4>

                <ul class="footer-links">

                    <?php if (isset($_SESSION['user_id'])): ?>

                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'grocery_admin'): ?>

                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/profile/profile.php">My Profile</a></li>

                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/grocery_dashboard.php">Dashboard</a></li>

                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/logout.php">Logout</a></li>

                        <?php else: ?>

                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/profile/profile.php">My Profile</a></li>

                            <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/logout.php">Logout</a></li>

                        <?php endif; ?>

                    <?php else: ?>

                        <li><a href="<?php echo htmlspecialchars($baseUrl); ?>/user/login.php">Login/Register</a></li>

                    <?php endif; ?>

                </ul>

            </div>

        </div>



        <div class="footer-bottom">

            <p>&copy; <?php echo date("Y"); ?> StockFlow. All Rights Reserved.</p>

            <div class="footer-legal">

                <a href="<?php echo htmlspecialchars($baseUrl); ?>/about.php">Privacy Policy</a>

                <span class="separator">|</span>

                <a href="<?php echo htmlspecialchars($baseUrl); ?>/features.php">Terms of Service</a>

            </div>

        </div>

    </div>

</footer>