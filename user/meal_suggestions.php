<?php
/**
 * Meal & Usage Suggestions
 * Route: /user/meal_suggestions.php
 * Purpose: Reduce waste and improve item usage with recipe suggestions
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/usage_recommendations.php';

// ── Auth guard ──────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$conn = getDBConnection();

// Get recommendations
$recommendations = getUsageRecommendations($conn, $user_id);
$stats = getUsageStats($conn, $user_id);
$items_by_category = getItemsByCategory($conn, $user_id);

// Active view
$view = $_GET['view'] ?? 'recipes';
$valid_views = ['recipes', 'tips', 'inventory'];
if (!in_array($view, $valid_views)) $view = 'recipes';

?>

<?php require_once '../includes/header.php'; ?>

<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/includes/style/pages/meal_suggestions.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<div class="catalog-page">
<div class="catalog-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <nav class="breadcrumb-nav">
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/dashboard.php" class="breadcrumb-link">Dashboard</a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Meal Suggestions</span>
                </nav>
                
                <h1 class="page-title">Meal & Usage Suggestions</h1>
                <p class="page-subtitle">Reduce waste with smart recipe ideas and usage tips</p>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card card-danger">
            <div class="stat-label">Expiring Today</div>
            <div class="stat-value"><?= $stats['expiring_today'] ?></div>
            <div class="stat-sub">Requires immediate use</div>
        </div>
        <div class="stat-card card-warning">
            <div class="stat-label">Within 3 Days</div>
            <div class="stat-value"><?= $stats['expiring_3days'] ?></div>
            <div class="stat-sub">Plan to use soon</div>
        </div>
        <div class="stat-card card-info">
            <div class="stat-label">This Week</div>
            <div class="stat-value"><?= $stats['expiring_week'] ?></div>
            <div class="stat-sub">Add to meal plan</div>
        </div>
        <div class="stat-card card-neutral">
            <div class="stat-label">At Risk</div>
            <div class="stat-value"><?= $stats['at_risk_percentage'] ?>%</div>
            <div class="stat-sub">Of total inventory</div>
        </div>
    </div>

    <!-- View Tabs -->
    <div class="view-tabs">
        <a href="?view=recipes" class="view-tab <?= $view === 'recipes' ? 'active' : '' ?>">
            Recipe Suggestions (<?= count($recommendations['recipe_suggestions']) ?>)
        </a>
        <a href="?view=tips" class="view-tab <?= $view === 'tips' ? 'active' : '' ?>">
            Usage Tips (<?= $recommendations['total_expiring'] ?>)
        </a>
        <a href="?view=inventory" class="view-tab <?= $view === 'inventory' ? 'active' : '' ?>">
            My Inventory
        </a>
    </div>

    <!-- RECIPES VIEW -->
    <?php if ($view === 'recipes'): ?>
        <?php if (empty($recommendations['recipe_suggestions'])): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke-width="1.5">
                        <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/>
                        <path d="M7 2v20"/>
                        <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>
                    </svg>
                </div>
                <h3 class="empty-title">No recipes yet</h3>
                <p class="empty-description">Add more items to your inventory to get personalized recipe suggestions</p>
            </div>
        <?php else: ?>
            <div class="recipe-grid">
                <?php foreach ($recommendations['recipe_suggestions'] as $suggestion): ?>
                    <?php $recipe = $suggestion['recipe']; ?>
                    <div class="recipe-card" onclick="openRecipeModal(<?= htmlspecialchars(json_encode($recipe)) ?>)">
                        <div class="recipe-header">
                            <div class="recipe-category"><?= htmlspecialchars($recipe['category']) ?></div>
                            <h3 class="recipe-name"><?= htmlspecialchars($recipe['name']) ?></h3>
                            <p class="recipe-description"><?= htmlspecialchars($recipe['description']) ?></p>
                        </div>
                        
                        <div class="recipe-meta">
                            <div class="meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <?= htmlspecialchars($recipe['prep_time']) ?>
                            </div>
                            <div class="meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                <?= $recipe['servings'] ?> servings
                            </div>
                            <div class="meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                                <?= htmlspecialchars($recipe['difficulty']) ?>
                            </div>
                        </div>
                        
                        <?php if ($suggestion['has_expiring_items']): ?>
                        <div class="recipe-match">
                            <div class="match-label">Uses expiring items</div>
                            <div class="match-items">
                                <?= implode(', ', array_map('htmlspecialchars', array_slice($suggestion['matched_items'], 0, 3))) ?>
                                <span class="expiring-badge">Use Soon</span>
                            </div>
                        </div>
                        <?php elseif (!empty($suggestion['matched_items'])): ?>
                        <div class="recipe-match" style="background: #fafafa; border-color: rgba(0,0,0,0.06);">
                            <div class="match-label" style="color: rgba(0,0,0,0.6);">You have</div>
                            <div class="match-items" style="color: rgba(0,0,0,0.7);">
                                <?= implode(', ', array_map('htmlspecialchars', array_slice($suggestion['matched_items'], 0, 3))) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="recipe-body">
                            <div class="ingredients-preview">
                                <div class="ingredients-label">Categories Needed</div>
                                <div class="ingredient-tags">
                                    <?php foreach ($recipe['ingredients'] as $ing): ?>
                                        <span class="ingredient-tag"><?= htmlspecialchars($ing) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="recipe-footer">
                            <button class="btn-view-recipe" type="button">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                View Recipe
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- TIPS VIEW -->
    <?php if ($view === 'tips'): ?>
        <?php if (empty($recommendations['expiring_items'])): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke-width="1.5">
                        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                    </svg>
                </div>
                <h3 class="empty-title">All good!</h3>
                <p class="empty-description">No items expiring soon. Check back later for usage tips.</p>
            </div>
        <?php else: ?>
            <div class="tips-grid">
                <?php foreach ($recommendations['expiring_items'] as $rec): ?>
                    <?php $item = $rec['item']; $tips = $rec['tips'][0]; ?>
                    <div class="tip-card urgency-<?= $tips['urgency'] ?>">
                        <div class="tip-header">
                            <div class="tip-item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                            <div class="tip-expiry">
                                <?= htmlspecialchars($item['quantity']) ?> <?= htmlspecialchars($item['unit']) ?> • 
                                Expires in <?= $item['days_until_expiry'] ?> day<?= $item['days_until_expiry'] != 1 ? 's' : '' ?>
                            </div>
                            <span class="tip-urgency <?= $tips['urgency'] ?>">
                                <?= htmlspecialchars($tips['title']) ?>
                            </span>
                        </div>
                        
                        <div class="tip-body">
                            <div class="tip-title"><?= htmlspecialchars($item['category_name']) ?> Storage</div>
                            <div class="tip-message"><?= htmlspecialchars($tips['message']) ?></div>
                        </div>
                        
                        <?php if (!empty($tips['ideas'])): ?>
                        <div class="tip-ideas">
                            <div class="ideas-label">Usage Ideas</div>
                            <ul class="idea-list">
                                <?php foreach ($tips['ideas'] as $idea): ?>
                                    <li><?= htmlspecialchars($idea) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- INVENTORY VIEW -->
    <?php if ($view === 'inventory'): ?>
        <?php if (empty($items_by_category)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke-width="1.5">
                        <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                        <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                    </svg>
                </div>
                <h3 class="empty-title">No items yet</h3>
                <p class="empty-description">Start adding items to your inventory to see them organized by category</p>
            </div>
        <?php else: ?>
            <?php foreach ($items_by_category as $category => $items): ?>
                <div class="category-section">
                    <div class="category-header">
                        <div class="category-name"><?= htmlspecialchars($category) ?></div>
                        <div class="category-count"><?= count($items) ?> item<?= count($items) != 1 ? 's' : '' ?></div>
                    </div>
                    <div class="items-list">
                        <?php foreach ($items as $item): ?>
                            <div class="item-row">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <div class="item-quantity">
                                        <?= htmlspecialchars($item['quantity']) ?> <?= htmlspecialchars($item['unit']) ?>
                                    </div>
                                </div>
                                <div class="item-status">
                                    <span class="status-badge <?= htmlspecialchars($item['expiry_status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $item['expiry_status'])) ?>
                                    </span>
                                    <span class="days-left">
                                        <?php if ($item['days_until_expiry'] < 0): ?>
                                            Expired
                                        <?php elseif ($item['days_until_expiry'] == 0): ?>
                                            Today
                                        <?php else: ?>
                                            <?= $item['days_until_expiry'] ?> days
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

</div>
</div>

<!-- Recipe Modal -->
<div class="modal" id="recipeModal" onclick="if(event.target === this) closeRecipeModal()">
    <div class="modal-content" style="position: relative;">
        <button class="modal-close" onclick="closeRecipeModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <div class="modal-header">
            <div class="modal-category" id="modalCategory"></div>
            <h2 class="modal-title" id="modalTitle"></h2>
        </div>
        <div class="modal-body">
            <div class="recipe-detail-meta" id="modalMeta"></div>
            
            <div class="recipe-section">
                <h3 class="section-title">Required Categories</h3>
                <div id="modalIngredients"></div>
            </div>
            
            <div class="recipe-section">
                <h3 class="section-title">Instructions</h3>
                <ol class="instructions-list" id="modalInstructions"></ol>
            </div>
            
            <div id="modalTip"></div>
        </div>
    </div>
</div>

<script>
function openRecipeModal(recipe) {
    document.getElementById('modalCategory').textContent = recipe.category;
    document.getElementById('modalTitle').textContent = recipe.name;
    
    // Meta info
    document.getElementById('modalMeta').innerHTML = `
        <div class="detail-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            <div>
                <div class="meta-label">Prep Time</div>
                <div class="meta-value">${recipe.prep_time}</div>
            </div>
        </div>
        <div class="detail-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            </svg>
            <div>
                <div class="meta-label">Servings</div>
                <div class="meta-value">${recipe.servings}</div>
            </div>
        </div>
        <div class="detail-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            <div>
                <div class="meta-label">Difficulty</div>
                <div class="meta-value">${recipe.difficulty}</div>
            </div>
        </div>
    `;
    
    // Ingredients
    document.getElementById('modalIngredients').innerHTML = `
        <p style="font-size: 12px; color: rgba(0,0,0,0.6); margin-bottom: 12px;">${recipe.description}</p>
        <div class="ingredient-tags">
            ${recipe.ingredients.map(ing => `<span class="ingredient-tag">${ing}</span>`).join('')}
        </div>
    `;
    
    // Instructions
    document.getElementById('modalInstructions').innerHTML = recipe.instructions
        .map(step => `<li>${step}</li>`)
        .join('');
    
    // Tip
    if (recipe.tips) {
        document.getElementById('modalTip').innerHTML = `
            <div class="recipe-tip">
                <div class="tip-icon">💡</div>
                <p><strong>Chef's Tip:</strong> ${recipe.tips}</p>
            </div>
        `;
    } else {
        document.getElementById('modalTip').innerHTML = '';
    }
    
    document.getElementById('recipeModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeRecipeModal() {
    document.getElementById('recipeModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRecipeModal();
});
</script>

</body>
</html>