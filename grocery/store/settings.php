<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $baseUrl . '/user/dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

$store_stmt = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
$store_stmt->bind_param("i", $user_id);
$store_stmt->execute();
$store_result = $store_stmt->get_result();
$user_store = $store_result->fetch_assoc();
$store_id = $user_store['store_id'];

$store_info_stmt = $conn->prepare("SELECT * FROM grocery_stores WHERE store_id = ?");
$store_info_stmt->bind_param("i", $store_id);
$store_info_stmt->execute();
$store_info = $store_info_stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_store'])) {
    $store_name = trim($_POST['store_name']);
    $business_address = trim($_POST['business_address']);
    $contact_number = trim($_POST['contact_number']);
    $store_email = trim($_POST['store_email']);
    
    $errors = [];
    
    if (empty($store_name)) $errors[] = "Store name is required.";
    if (empty($business_address)) $errors[] = "Business address is required.";
    if (empty($contact_number)) $errors[] = "Contact number is required.";
    if (empty($store_email)) $errors[] = "Store email is required.";
    elseif (!filter_var($store_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required.";
    
    if (empty($errors)) {
        $update_stmt = $conn->prepare("UPDATE grocery_stores SET store_name = ?, business_address = ?, contact_number = ?, email = ? WHERE store_id = ?");
        $update_stmt->bind_param("ssssi", $store_name, $business_address, $contact_number, $store_email, $store_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Store updated successfully!";
            header("Location: settings.php");
            exit();
        } else {
            $_SESSION['error'] = "Update failed. Please try again.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/settings.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="settings-page">
    <div class="page-container">
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Store Settings</h1>
                <p class="page-subtitle">Manage your store information</p>
            </div>
            <div class="header-actions">
                <a href="../grocery_dashboard.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <div class="settings-content">
            <div class="settings-section">
                <div class="section-header">
                    <h2>Store Information</h2>
                    <p>Update your store's basic information</p>
                </div>
                
                <form method="POST" action="" class="settings-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="store_name" class="form-label">Store Name *</label>
                            <input type="text" id="store_name" name="store_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($store_info['store_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="store_email" class="form-label">Store Email *</label>
                            <input type="email" id="store_email" name="store_email" class="form-input" 
                                   value="<?php echo htmlspecialchars($store_info['email']); ?>" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="business_address" class="form-label">Business Address *</label>
                            <textarea id="business_address" name="business_address" class="form-input" rows="3" required><?php echo htmlspecialchars($store_info['business_address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_number" class="form-label">Contact Number *</label>
                            <input type="tel" id="contact_number" name="contact_number" class="form-input" 
                                   value="<?php echo htmlspecialchars($store_info['contact_number']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Store Status</label>
                            <div class="status-display">
                                <span class="badge badge-<?php echo $store_info['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $store_info['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <span class="badge badge-<?php echo $store_info['is_verified'] ? 'success' : 'warning'; ?>">
                                    <?php echo $store_info['is_verified'] ? 'Verified' : 'Pending Verification'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_store" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <div class="settings-section">
                <div class="section-header">
                    <h2>Account Settings</h2>
                    <p>Manage your administrator account</p>
                </div>
                
                <div class="settings-links">
                    <a href="../profile/profile.php" class="settings-link">
                        <div class="link-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <div class="link-content">
                            <h3>Profile Settings</h3>
                            <p>Update your personal information and password</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php 
$store_info_stmt->close();
$store_stmt->close();
$conn->close();
?>
