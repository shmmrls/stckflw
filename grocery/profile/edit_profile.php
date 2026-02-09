<?php
ob_start();
session_start();
require_once __DIR__ . '/../../includes/config.php';

// Initialize database connection
$conn = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Verify user is grocery admin
if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $baseUrl . '/user/customer/dashboard.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch user data
$user_stmt = $conn->prepare("SELECT user_id, full_name, email, img_name, role, store_id, created_at FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Check if user data exists
if (!$user_data) {
    header("Location: ../auth/login.php");
    exit;
}

// Fetch store information
$store_id = $user_data['store_id'];
$store_data = null;
if ($store_id) {
    $store_stmt = $conn->prepare("SELECT * FROM grocery_stores WHERE store_id = ?");
    $store_stmt->bind_param("i", $store_id);
    $store_stmt->execute();
    $store_data = $store_stmt->get_result()->fetch_assoc();
    $store_stmt->close();
}

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        // Handle profile picture upload
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } elseif ($file_size > $max_size) {
            $error = "File size exceeds 5MB limit.";
        } else {
            $upload_dir = __DIR__ . '/../../images/profile_pictures/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if it exists and is not default
                if (!empty($user_data['img_name']) && $user_data['img_name'] !== 'nopfp.jpg') {
                    $old_file = $upload_dir . $user_data['img_name'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                // Update database
                $img_update_stmt = $conn->prepare("UPDATE users SET img_name = ? WHERE user_id = ?");
                $img_update_stmt->bind_param("si", $new_filename, $user_id);
                
                if ($img_update_stmt->execute()) {
                    $user_data['img_name'] = $new_filename;
                    $success = "Profile picture updated successfully!";
                } else {
                    $error = "Failed to update profile picture in database";
                }
            } else {
                $error = "Failed to upload profile picture";
            }
        }
    } else {
        // Handle profile information update
        if (isset($_POST['full_name']) && isset($_POST['email'])) {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate inputs
            if (empty($full_name)) {
                $error = "Full name is required";
            } elseif (empty($email)) {
                $error = "Email is required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format";
            } else {
                // Check if email is already taken by another user
                $email_check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $email_check_stmt->bind_param("si", $email, $user_id);
                $email_check_stmt->execute();
                $email_check_result = $email_check_stmt->get_result();
                
                if ($email_check_result->num_rows > 0) {
                    $error = "Email is already in use by another account";
                } else {
                    // Handle password update if provided
                    $password_updated = false;
                    if (!empty($new_password)) {
                        if (empty($current_password)) {
                            $error = "Current password is required to set a new password";
                        } elseif ($new_password !== $confirm_password) {
                            $error = "New passwords do not match";
                        } elseif (strlen($new_password) < 6) {
                            $error = "New password must be at least 6 characters";
                        } else {
                            // Verify current password
                            $password_verify_stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
                            $password_verify_stmt->bind_param("i", $user_id);
                            $password_verify_stmt->execute();
                            $password_result = $password_verify_stmt->get_result();
                            $password_data = $password_result->fetch_assoc();
                            
                            if (!password_verify($current_password, $password_data['password'])) {
                                $error = "Current password is incorrect";
                            } else {
                                $password_updated = true;
                            }
                        }
                    }
                    
                    // Update user data if no errors
                    if (empty($error)) {
                        if ($password_updated) {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE user_id = ?");
                            $update_stmt->bind_param("sssi", $full_name, $email, $hashed_password, $user_id);
                        } else {
                            $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
                            $update_stmt->bind_param("ssi", $full_name, $email, $user_id);
                        }
                        
                        if ($update_stmt->execute()) {
                            $success = "Profile updated successfully!";
                            // Refresh user data
                            $user_data['full_name'] = $full_name;
                            $user_data['email'] = $email;
                            $_SESSION['full_name'] = $full_name;
                        } else {
                            $error = "Failed to update profile";
                        }
                    }
                }
            }
        }
    }
}

$pageCss = ''
  . '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/profile.css">' . "\n"
  . '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="profile-page">
    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-avatar-section">
                <div class="profile-info" style="flex: none;">
                    <h1 class="profile-name">Edit Profile</h1>
                    <p class="profile-email">Update your account information</p>
                </div>
            </div>
            <div class="profile-actions">
                <a href="profile.php" class="btn btn-delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    <span>Cancel</span>
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 16px 20px; margin-bottom: 25px; font-size: 13px; letter-spacing: 0.3px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 16px 20px; margin-bottom: 25px; font-size: 13px; letter-spacing: 0.3px;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Profile Picture Section -->
            <div class="info-card">
                <div class="card-header">
                    <h2 class="card-title">Profile Picture</h2>
                </div>
                
                <div style="text-align: center;">
                    <div class="avatar-wrapper" style="display: inline-block; margin-bottom: 20px;">
                        <?php 
                        if (!empty($user_data['img_name'])) {
                            $profile_pic = htmlspecialchars($baseUrl) . '/images/profile_pictures/' . htmlspecialchars($user_data['img_name']);
                        } else {
                            $profile_pic = htmlspecialchars($baseUrl) . '/images/profile_pictures/nopfp.jpg';
                        }
                        ?>
                        <img src="<?php echo $profile_pic; ?>" 
                             alt="Profile Picture" 
                             class="profile-avatar"
                             id="preview-image"
                             onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/images/profile_pictures/nopfp.jpg';">
                        <div class="avatar-badge"><?php echo strtoupper(substr($user_data['full_name'], 0, 1)); ?></div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="picture-form">
                        <div class="form-group">
                            <label for="profile_picture" style="display: block; margin-bottom: 10px;">Upload New Picture</label>
                            <input type="file" 
                                   id="profile_picture" 
                                   name="profile_picture" 
                                   accept="image/jpeg,image/png,image/jpg,image/gif"
                                   style="width: 100%; padding: 12px; border: 1px solid rgba(0,0,0,0.1); background: #fafafa; font-family: 'Montserrat', sans-serif; font-size: 13px;">
                            <small style="display: block; margin-top: 8px; font-size: 11px; color: rgba(0,0,0,0.5);">
                                JPG, PNG, or GIF. Max 5MB.
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            Upload Picture
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Information Section -->
            <div class="info-card">
                <div class="card-header">
                    <h2 class="card-title">Account Information</h2>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="full_name" style="display: block; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.6); margin-bottom: 10px; font-weight: 600;">
                            Full Name *
                        </label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               value="<?php echo htmlspecialchars($user_data['full_name']); ?>"
                               required
                               style="width: 100%; padding: 14px 18px; font-size: 14px; font-family: 'Montserrat', sans-serif; background: #fafafa; border: 1px solid rgba(0,0,0,0.1); transition: all 0.3s ease;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="email" style="display: block; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.6); margin-bottom: 10px; font-weight: 600;">
                            Email Address *
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($user_data['email']); ?>"
                               required
                               style="width: 100%; padding: 14px 18px; font-size: 14px; font-family: 'Montserrat', sans-serif; background: #fafafa; border: 1px solid rgba(0,0,0,0.1); transition: all 0.3s ease;">
                    </div>
                    
                    <div style="border-top: 1px solid rgba(0,0,0,0.06); padding-top: 20px; margin-top: 30px; margin-bottom: 20px;">
                        <h3 style="font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 400; margin-bottom: 15px; color: #0a0a0a;">
                            Change Password
                        </h3>
                        <p style="font-size: 12px; color: rgba(0,0,0,0.5); margin-bottom: 20px;">
                            Leave blank to keep current password
                        </p>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="current_password" style="display: block; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.6); margin-bottom: 10px; font-weight: 600;">
                            Current Password
                        </label>
                        <input type="password" 
                               id="current_password" 
                               name="current_password"
                               style="width: 100%; padding: 14px 18px; font-size: 14px; font-family: 'Montserrat', sans-serif; background: #fafafa; border: 1px solid rgba(0,0,0,0.1); transition: all 0.3s ease;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="new_password" style="display: block; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.6); margin-bottom: 10px; font-weight: 600;">
                            New Password
                        </label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password"
                               style="width: 100%; padding: 14px 18px; font-size: 14px; font-family: 'Montserrat', sans-serif; background: #fafafa; border: 1px solid rgba(0,0,0,0.1); transition: all 0.3s ease;">
                        <small style="display: block; margin-top: 8px; font-size: 11px; color: rgba(0,0,0,0.5);">
                            Minimum 6 characters
                        </small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 30px;">
                        <label for="confirm_password" style="display: block; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(0,0,0,0.6); margin-bottom: 10px; font-weight: 600;">
                            Confirm New Password
                        </label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password"
                               style="width: 100%; padding: 14px 18px; font-size: 14px; font-family: 'Montserrat', sans-serif; background: #fafafa; border: 1px solid rgba(0,0,0,0.1); transition: all 0.3s ease;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Store Information (Read-only) -->
        <?php if ($store_data): ?>
        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title">Store Information</h2>
                <a href="../store/settings.php" class="section-link">Manage Store →</a>
            </div>
            
            <div class="info-list" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <div class="info-item" style="display: block; padding: 20px; background: #fafafa; border: 1px solid rgba(0,0,0,0.04);">
                    <div class="info-label" style="margin-bottom: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        Store Name
                    </div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($store_data['store_name']); ?>
                    </div>
                </div>
                
                <div class="info-item" style="display: block; padding: 20px; background: #fafafa; border: 1px solid rgba(0,0,0,0.04);">
                    <div class="info-label" style="margin-bottom: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        Contact Number
                    </div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($store_data['contact_number'] ?? 'N/A'); ?>
                    </div>
                </div>
                
                <div class="info-item" style="display: block; padding: 20px; background: #fafafa; border: 1px solid rgba(0,0,0,0.04); grid-column: 1 / -1;">
                    <div class="info-label" style="margin-bottom: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        Business Address
                    </div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($store_data['business_address']); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Account Details -->
        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title">Account Details</h2>
            </div>
            
            <div class="info-list" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <div class="info-item" style="display: block; padding: 20px; background: #fafafa; border: 1px solid rgba(0,0,0,0.04);">
                    <div class="info-label" style="margin-bottom: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        User ID
                    </div>
                    <div class="info-value" style="font-family: 'Courier New', monospace; color: rgba(0,0,0,0.8);">
                        #<?php echo $user_data['user_id']; ?>
                    </div>
                </div>
                
                <div class="info-item" style="display: block; padding: 20px; background: #fafafa; border: 1px solid rgba(0,0,0,0.04);">
                    <div class="info-label" style="margin-bottom: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/>
                        </svg>
                        Account Role
                    </div>
                    <div class="info-value" style="text-transform: capitalize;">
                        Grocery Admin
                    </div>
                </div>
                
                <div class="info-item" style="display: block; padding: 20px; background: #fafafa; border: 1px solid rgba(0,0,0,0.04);">
                    <div class="info-label" style="margin-bottom: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Member Since
                    </div>
                    <div class="info-value">
                        <?php echo date('F d, Y', strtotime($user_data['created_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item" style="display: block; padding: 20px; background: #fafafa; border: 1px solid rgba(0,0,0,0.04);">
                    <div class="info-label" style="margin-bottom: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Account Status
                    </div>
                    <div class="info-value">
                        <span class="badge badge-fresh">Active</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Preview profile picture before upload
document.getElementById('profile_picture').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});

// Add focus styles to inputs
const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.style.background = '#ffffff';
        this.style.borderColor = '#0a0a0a';
        this.style.boxShadow = '0 0 0 3px rgba(0,0,0,0.05)';
    });
    
    input.addEventListener('blur', function() {
        this.style.background = '#fafafa';
        this.style.borderColor = 'rgba(0,0,0,0.1)';
        this.style.boxShadow = 'none';
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>