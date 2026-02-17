<?php
/**
 * Shopping List
 * Route: /user/shopping_list.php
 * Auto-generate from low stock items and manage manually
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/shopping_list_functions.php';

// Auth guard
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$conn = getDBConnection();

// Ensure tables exist
ensureShoppingListTables($conn);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'auto_generate':
            $count = autoGenerateShoppingList($conn, $user_id);
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
            
        case 'add_item':
            $data = [
                'item_name' => trim($_POST['item_name'] ?? ''),
                'quantity' => floatval($_POST['quantity'] ?? 1),
                'unit' => trim($_POST['unit'] ?? 'piece'),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'priority' => $_POST['priority'] ?? 'medium',
                'notes' => trim($_POST['notes'] ?? '')
            ];
            
            if (empty($data['item_name'])) {
                echo json_encode(['success' => false, 'message' => 'Item name is required']);
                exit;
            }
            
            $success = addShoppingListItem($conn, $user_id, $data);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'update_item':
            $list_item_id = (int) ($_POST['list_item_id'] ?? 0);
            $data = [
                'item_name' => trim($_POST['item_name'] ?? ''),
                'quantity' => floatval($_POST['quantity'] ?? 1),
                'unit' => trim($_POST['unit'] ?? 'piece'),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'priority' => $_POST['priority'] ?? 'medium',
                'notes' => trim($_POST['notes'] ?? '')
            ];
            
            $success = updateShoppingListItem($conn, $list_item_id, $user_id, $data);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'toggle_purchased':
            $list_item_id = (int) ($_POST['list_item_id'] ?? 0);
            $is_purchased = (int) ($_POST['is_purchased'] ?? 0);
            
            if ($is_purchased) {
                $success = markItemPurchased($conn, $list_item_id, $user_id);
            } else {
                $success = markItemUnpurchased($conn, $list_item_id, $user_id);
            }
            
            echo json_encode(['success' => $success]);
            exit;
            
        case 'delete_item':
            $list_item_id = (int) ($_POST['list_item_id'] ?? 0);
            $success = deleteShoppingListItem($conn, $list_item_id, $user_id);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'clear_purchased':
            $success = clearPurchasedItems($conn, $user_id);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'clear_all':
            $success = clearShoppingList($conn, $user_id);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'save_settings':
            $settings = [
                'auto_add_low_stock' => isset($_POST['auto_add_low_stock']) ? 1 : 0,
                'low_stock_threshold' => floatval($_POST['low_stock_threshold'] ?? 2.00),
                'auto_add_expiring' => isset($_POST['auto_add_expiring']) ? 1 : 0,
                'expiring_days' => (int) ($_POST['expiring_days'] ?? 3)
            ];
            
            $success = saveShoppingListSettings($conn, $user_id, $settings);
            echo json_encode(['success' => $success]);
            exit;
    }
}

// Get data for page
$settings = getShoppingListSettings($conn, $user_id);
$items_by_category = getShoppingListByCategory($conn, $user_id);
$stats = getShoppingListStats($conn, $user_id);
$categories = getCategories($conn);
$low_stock_items = getLowStockItems($conn, $user_id, $settings['low_stock_threshold']);

?>

<?php require_once '../includes/header.php'; ?>

<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/includes/style/pages/shopping_list.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<div class="shopping-page">
<div class="shopping-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-info">
                <nav class="breadcrumb-nav">
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/dashboard.php" class="breadcrumb-link">Dashboard</a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Shopping List</span>
                </nav>
                
                <h1 class="page-title">Shopping List</h1>
                <p class="page-subtitle">Auto-generate from low stock items or add manually</p>
            </div>
            <div class="header-actions">
                <?php if ($stats['pending_items'] > 0): ?>
                <button class="btn-danger" id="btnClearPurchased">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    Clear Purchased
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card card-primary">
            <div class="stat-label">Total Items</div>
            <div class="stat-value"><?= $stats['total_items'] ?></div>
            <div class="stat-sub">In shopping list</div>
        </div>
        <div class="stat-card card-warning">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= $stats['pending_items'] ?></div>
            <div class="stat-sub">Not yet purchased</div>
        </div>
        <div class="stat-card card-success">
            <div class="stat-label">Purchased</div>
            <div class="stat-value"><?= $stats['purchased_items'] ?></div>
            <div class="stat-sub"><?= $stats['completion_percentage'] ?>% complete</div>
        </div>
        <div class="stat-card card-info">
            <div class="stat-label">Low Stock</div>
            <div class="stat-value"><?= count($low_stock_items) ?></div>
            <div class="stat-sub">Items need restocking</div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="main-grid">
        
        <!-- LEFT: Shopping List -->
        <div>
            <div class="list-section">
                <div class="section-header">
                    <h2 class="section-title">Your List</h2>
                    <div class="section-actions">
                        <?php if ($stats['total_items'] > 0): ?>
                        <button class="btn-icon btn-danger" id="btnClearAll" title="Clear all items">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (empty($items_by_category)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke-width="1.5">
                                <path d="M9 2v2m6-2v2M4 8h16M4 8v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M4 8l1-4h14l1 4"/>
                            </svg>
                        </div>
                        <h3 class="empty-title">Your list is empty</h3>
                        <p class="empty-description">Add items manually or auto-generate from low stock items</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($items_by_category as $category => $items): ?>
                        <div class="category-group">
                            <div class="category-header">
                                <span class="category-name"><?= htmlspecialchars($category) ?></span>
                                <span class="category-count"><?= count($items) ?> item<?= count($items) != 1 ? 's' : '' ?></span>
                            </div>
                            <div class="list-items">
                                <?php foreach ($items as $item): ?>
                                    <div class="list-item <?= $item['is_purchased'] ? 'purchased' : '' ?>" data-id="<?= $item['list_item_id'] ?>">
                                        <div class="item-checkbox">
                                            <input type="checkbox" 
                                                   class="checkbox-purchased" 
                                                   data-id="<?= $item['list_item_id'] ?>"
                                                   <?= $item['is_purchased'] ? 'checked' : '' ?>>
                                        </div>
                                        
                                        <div class="item-info">
                                            <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                            <div class="item-details">
                                                <span class="item-quantity">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                                        <line x1="1" y1="10" x2="23" y2="10"/>
                                                    </svg>
                                                    <?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?>
                                                </span>
                                                <span class="item-badge <?= $item['is_auto_generated'] ? 'badge-auto' : 'badge-manual' ?>">
                                                    <?= $item['source_type'] ?>
                                                </span>
                                                <span class="priority-badge priority-<?= htmlspecialchars($item['priority']) ?>">
                                                    <?= ucfirst($item['priority']) ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($item['notes'])): ?>
                                                <div class="item-notes"><?= htmlspecialchars($item['notes']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="item-actions">
                                            <button class="btn-small btn-delete" 
                                                    data-id="<?= $item['list_item_id'] ?>"
                                                    title="Delete item">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- RIGHT: Add Item & Settings -->
        <div>
            <!-- Auto-Generate Section -->
            <div class="auto-generate-section">
                <div class="auto-section-header">
                    <div class="auto-section-title">Auto-Generate List</div>
                    <div class="auto-section-subtitle">Add items from your inventory automatically</div>
                </div>
                <div class="auto-section-body">
                    <div class="auto-stats">
                        <div class="auto-stat-row">
                            <span class="auto-stat-label">Low stock items available:</span>
                            <span class="auto-stat-value"><?= count($low_stock_items) ?></span>
                        </div>
                        <div class="auto-stat-row">
                            <span class="auto-stat-label">Already in list:</span>
                            <span class="auto-stat-value"><?= $stats['auto_generated_items'] ?></span>
                        </div>
                    </div>
                    <button class="btn-primary btn-generate" id="btnAutoGenerate">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>
                        Generate Shopping List
                    </button>
                </div>
            </div>
            
            <!-- Add Item Section -->
            <div class="add-item-section">
                <h3 class="add-form-title">Add Item Manually</h3>
                <form class="add-item-form" id="addItemForm">
                    <div class="form-group">
                        <label class="form-label">Item Name *</label>
                        <input type="text" 
                               name="item_name" 
                               class="form-input" 
                               placeholder="e.g., Milk, Eggs, Bread"
                               required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Quantity</label>
                            <input type="number" 
                                   name="quantity" 
                                   class="form-input" 
                                   value="1" 
                                   step="0.01"
                                   min="0.01">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unit</label>
                            <input type="text" 
                                   name="unit" 
                                   class="form-input" 
                                   value="piece"
                                   placeholder="kg, L, piece">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>">
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" 
                                  class="form-textarea" 
                                  placeholder="Any additional notes..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary btn-add">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add to List
                    </button>
                </form>
            </div>
            
            <!-- Settings Section -->
            <div class="settings-section">
                <div class="section-header">
                    <h2 class="section-title">Auto-Generate Settings</h2>
                </div>
                <div class="settings-body">
                    <form id="settingsForm">
                        <div class="settings-group">
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label-text">Auto-add low stock items</div>
                                    <div class="toggle-desc">Add items below threshold automatically</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" 
                                           name="auto_add_low_stock" 
                                           <?= $settings['auto_add_low_stock'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div style="padding: 12px 0; font-size: 11px; color: rgba(0,0,0,0.6);">
                                <label>Low stock threshold: 
                                    <input type="number" 
                                           name="low_stock_threshold" 
                                           class="form-input threshold-input"
                                           value="<?= $settings['low_stock_threshold'] ?>"
                                           step="0.1"
                                           min="0.1">
                                    units
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-group">
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-label-text">Auto-add expiring items</div>
                                    <div class="toggle-desc">Add replacements for expiring items</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" 
                                           name="auto_add_expiring"
                                           <?= $settings['auto_add_expiring'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div style="padding: 12px 0; font-size: 11px; color: rgba(0,0,0,0.6);">
                                <label>Alert when expiring in: 
                                    <input type="number" 
                                           name="expiring_days" 
                                           class="form-input threshold-input"
                                           value="<?= $settings['expiring_days'] ?>"
                                           min="1"
                                           max="30">
                                    days
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
    </div>

</div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// Toast helper
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            ${type === 'success' ? '<polyline points="20 6 9 17 4 12"/>' : 
              type === 'error' ? '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>' :
              '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>'}
        </svg>
        ${message}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s';
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

// Generic POST
async function apiPost(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    const res = await fetch(window.location.pathname, { method: 'POST', body: fd });
    return res.json();
}

// Auto-generate list
document.getElementById('btnAutoGenerate').addEventListener('click', async () => {
    const btn = document.getElementById('btnAutoGenerate');
    btn.disabled = true;
    btn.textContent = 'Generating...';
    
    const data = await apiPost({ action: 'auto_generate' });
    
    if (data.success) {
        showToast(`${data.count} item(s) added to shopping list`, 'success');
        if (data.count > 0) {
            setTimeout(() => location.reload(), 1200);
        }
    } else {
        showToast('Failed to generate list', 'error');
    }
    
    btn.disabled = false;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Generate Shopping List';
});

// Add item
document.getElementById('addItemForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = { action: 'add_item' };
    fd.forEach((v, k) => data[k] = v);
    
    const result = await apiPost(data);
    
    if (result.success) {
        showToast('Item added to shopping list', 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(result.message || 'Failed to add item', 'error');
    }
});

// Toggle purchased
document.querySelectorAll('.checkbox-purchased').forEach(checkbox => {
    checkbox.addEventListener('change', async (e) => {
        const id = e.target.dataset.id;
        const is_purchased = e.target.checked ? 1 : 0;
        
        const data = await apiPost({ 
            action: 'toggle_purchased', 
            list_item_id: id, 
            is_purchased: is_purchased 
        });
        
        if (data.success) {
            const item = document.querySelector(`.list-item[data-id="${id}"]`);
            if (is_purchased) {
                item.classList.add('purchased');
                showToast('Item marked as purchased', 'success');
            } else {
                item.classList.remove('purchased');
                showToast('Item marked as unpurchased', 'info');
            }
        }
    });
});

// Delete item
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        if (!confirm('Delete this item from shopping list?')) return;
        
        const id = e.target.closest('.btn-delete').dataset.id;
        const data = await apiPost({ action: 'delete_item', list_item_id: id });
        
        if (data.success) {
            const item = document.querySelector(`.list-item[data-id="${id}"]`);
            item.style.opacity = '0';
            item.style.maxHeight = item.offsetHeight + 'px';
            item.style.transition = 'opacity 0.3s, max-height 0.4s';
            requestAnimationFrame(() => item.style.maxHeight = '0');
            setTimeout(() => item.remove(), 400);
            showToast('Item deleted', 'success');
        }
    });
});

// Clear purchased
document.getElementById('btnClearPurchased')?.addEventListener('click', async () => {
    if (!confirm('Clear all purchased items?')) return;
    
    const data = await apiPost({ action: 'clear_purchased' });
    
    if (data.success) {
        showToast('Purchased items cleared', 'success');
        setTimeout(() => location.reload(), 800);
    }
});

// Clear all
document.getElementById('btnClearAll')?.addEventListener('click', async () => {
    if (!confirm('Clear entire shopping list? This cannot be undone.')) return;
    
    const data = await apiPost({ action: 'clear_all' });
    
    if (data.success) {
        showToast('Shopping list cleared', 'success');
        setTimeout(() => location.reload(), 800);
    }
});

// Save settings
document.getElementById('settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = { action: 'save_settings' };
    fd.forEach((v, k) => data[k] = v);
    
    // Ensure unchecked checkboxes are included
    ['auto_add_low_stock', 'auto_add_expiring'].forEach(name => {
        if (!(name in data)) data[name] = '0';
    });
    
    const result = await apiPost(data);
    
    if (result.success) {
        showToast('Settings saved', 'success');
    } else {
        showToast('Failed to save settings', 'error');
    }
});
</script>

</body>
</html>