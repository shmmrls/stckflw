<!-- <?php
?>
</main>

<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/includes/style/footer.css">

<footer class="footer">
    <div class="footer-content">
        <div class="footer-grid">
            <div class="footer-column">
                <div class="footer-logo">
                    <img src="<?= htmlspecialchars($baseUrl) ?>/assets/logo/logo2.png" alt="StockFlow" class="logo-img">
                </div>
                <p class="footer-tagline">Track what you buy. Track what you use. Earn points.</p>
            </div>

            <div class="footer-column">
                <h4 class="footer-title">Collections</h4>
                <ul class="footer-links">
                    <?php
                    // Determine product list page based on user role
                    $product_page = 'product-list.php'; // default for guests
                    if (isset($_SESSION['user_role'])) {
                        if ($_SESSION['user_role'] === 'admin') {
                            $product_page = 'admin/product-list.php';
                        } elseif ($_SESSION['user_role'] === 'customer') {
                            $product_page = 'customer/product-list.php';
                        }
                    }
                    ?>
                    <li><a href="<?= htmlspecialchars($baseUrl) ?>/<?= $product_page ?>">All Products</a></li>
                    <?php
                    if (isset($conn) && $conn) {
                        $cat_query = "SELECT category_id, category_name FROM categories ORDER BY category_id";
                        $cat_result = $conn->query($cat_query);
                        if ($cat_result && $cat_result->num_rows > 0) {
                            while ($cat = $cat_result->fetch_assoc()) {
                                echo '<li><a href="' . htmlspecialchars($baseUrl) . '/' . $product_page . '?category=' . $cat['category_id'] . '">' . htmlspecialchars($cat['category_name']) . '</a></li>';
                            }
                        }
                    }
                    ?>
                </ul>
            </div>

            <div class="footer-column">
                <h4 class="footer-title">Information</h4>
                <ul class="footer-links">
                    <li><a href="<?= htmlspecialchars($baseUrl) ?>/about.php">About</a></li>
                    <li><a href="<?= htmlspecialchars($baseUrl) ?>/contact.php">Contact Us</a></li>
                    <li><a href="<?= htmlspecialchars($baseUrl) ?>/shipping.php">Shipping & Delivery</a></li>
                    <li><a href="<?= htmlspecialchars($baseUrl) ?>/faq.php">FAQ</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h4 class="footer-title">Account</h4>
                <ul class="footer-links">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li><a href="<?= htmlspecialchars($baseUrl) ?>/admin/profile.php">My Profile</a></li>
                            <li><a href="<?= htmlspecialchars($baseUrl) ?>/admin/dashboard.php">Dashboard</a></li>
                            <li><a href="<?= htmlspecialchars($baseUrl) ?>/user/logout.php">Logout</a></li>
                        <?php else: ?>
                            <li><a href="<?= htmlspecialchars($baseUrl) ?>/user/profile.php">My Profile</a></li>
                            <li><a href="<?= htmlspecialchars($baseUrl) ?>/customer/orders.php">Order History</a></li>
                            <li><a href="<?= htmlspecialchars($baseUrl) ?>/customer/cart/view_cart.php">Shopping Cart</a></li>
                            <li><a href="<?= htmlspecialchars($baseUrl) ?>/user/logout.php">Logout</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li><a href="<?= htmlspecialchars($baseUrl) ?>/user/login.php">Login/Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> GlamEssentials. All Rights Reserved.</p>
            <div class="footer-legal">
                <a href="<?= htmlspecialchars($baseUrl) ?>/privacy.php">Privacy Policy</a>
                <span class="separator">|</span>
                <a href="<?= htmlspecialchars($baseUrl) ?>/terms.php">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html> -->