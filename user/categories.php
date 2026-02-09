<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$conn = getDBConnection();
$categories_stmt = $conn->prepare("SELECT * FROM categories ORDER BY category_name");
$categories_stmt->execute();
$categories = $categories_stmt->get_result();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="categories-page">
    <div class="page-container">
        <h1>Categories</h1>
        <div class="categories-grid">
            <?php while ($category = $categories->fetch_assoc()): ?>
                <div class="category-card">
                    <h3><?= htmlspecialchars($category['category_name']) ?></h3>
                    <p><?= htmlspecialchars($category['description']) ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
