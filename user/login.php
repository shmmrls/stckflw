<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
// session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // Check user role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'grocery_admin') {
        ob_end_clean();
        header("Location: " . $baseUrl . "/grocery/grocery_dashboard.php");
        exit();
    } else {
        ob_end_clean();
        header("Location: dashboard.php");
        exit();
    }
}

// Check if redirected from unauthorized access
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $_SESSION['error'] = "You must be logged in to access that page.";
}

if (isset($_GET['error']) && $_GET['error'] === 'admin_only') {
    $_SESSION['error'] = "Access denied. Admin privileges required.";
}

// Check if redirected because need to login (e.g., from cart)
if (isset($_GET['login_required']) && !isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please sign in first to continue.";
}

$redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : '';

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    $length = strlen($password) >= 8 && strlen($password) <= 12;
    $uppercase = preg_match('/[A-Z]/', $password);
    $lowercase = preg_match('/[a-z]/', $password);
    $number = preg_match('/[0-9]/', $password);
    $special = preg_match('/[!@#$%^&*]/', $password);
    
    return $length && $uppercase && $lowercase && $number && $special;
}

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        //  detects role from database
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email)) {
            $_SESSION['error'] = "Email address is required.";
        } elseif (empty($password)) {
            $_SESSION['error'] = "Password is required.";
        } elseif (!validateEmail($email)) {
            $_SESSION['error'] = "Please enter a valid email address.";
        } else {
            $stmt = $conn->prepare("SELECT user_id, full_name, email, password, role, is_active FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $valid = false;
                if (substr($user['password'], 0, 4) === '$2y$') {
                    $valid = password_verify($password, $user['password']);
                } else {
                    $valid = hash('sha256', $password) === $user['password'];
                }
                
                if ($valid) {
                    if ($user['is_active'] != 1) {
                        header("Location: deactivated.php");
                        exit();
                    } else {
                        $update_login = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                        $update_login->bind_param("i", $user['user_id']);
                        $update_login->execute();
                        $update_login->close();
                        
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        
                        if ($user['role'] === 'grocery_admin') {
                            ob_end_clean();
                            header("Location: " . $baseUrl . "/grocery/grocery_dashboard.php");
                            exit();
                        } else {
                            ob_end_clean();
                            if (!empty($redirect_url)) {
                                header("Location: " . $redirect_url);
                            } else {
                                header("Location: dashboard.php");
                            }
                            exit();
                        }
                    }
                } else {
                    $_SESSION['error'] = "Invalid email or password.";
                }
            } else {
                $_SESSION['error'] = "Invalid email or password.";
            }

            $stmt->close();
        }
    } elseif (isset($_POST['register'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $invitation_code = !empty($_POST['invitation_code']) ? trim($_POST['invitation_code']) : null;
        
        if (empty($name)) {
            $_SESSION['error'] = "Name is required.";
        } elseif (strlen($name) < 2) {
            $_SESSION['error'] = "Name must be at least 2 characters long.";
        } elseif (empty($email)) {
            $_SESSION['error'] = "Email address is required.";
        } elseif (!validateEmail($email)) {
            $_SESSION['error'] = "Please enter a valid email address.";
        } elseif (empty($password)) {
            $_SESSION['error'] = "Password is required.";
        } elseif (!validatePassword($password)) {
            $_SESSION['error'] = "Password must be 8-12 characters and include uppercase, lowercase, number, and special character (!@#$%^&*).";
        } elseif (empty($confirm_password)) {
            $_SESSION['error'] = "Please confirm your password.";
        } elseif ($password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match.";
        } else {
            // Validate invitation code if provided
            $group_id = null;
            $group_type = null;
            
            if ($invitation_code) {
                $group_check = $conn->prepare("SELECT group_id, group_type FROM groups WHERE invitation_code = ?");
                $group_check->bind_param("s", $invitation_code);
                $group_check->execute();
                $group_result = $group_check->get_result();
                
                if ($group_result->num_rows === 0) {
                    $_SESSION['error'] = "Invalid invitation code.";
                    $group_check->close();
                } else {
                    $group_data = $group_result->fetch_assoc();
                    $group_id = $group_data['group_id'];
                    $group_type = $group_data['group_type'];
                    $group_check->close();
                }
            }
            
            // Only proceed if no error from invitation code validation
            if (!isset($_SESSION['error'])) {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['error'] = "Email already registered.";
                } else {
                    // Handle profile picture upload
                    $img_name = 'nopfp.jpg'; // Default profile picture
                    
                    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                        $file_type = $_FILES['profile_picture']['type'];
                        $file_size = $_FILES['profile_picture']['size'];
                        
                        if (!in_array($file_type, $allowed_types)) {
                            $_SESSION['error'] = "Only JPG, PNG, and GIF images are allowed.";
                        } elseif ($file_size > 5242880) { // 5MB
                            $_SESSION['error'] = "Image size must be less than 5MB.";
                        } else {
                           $upload_dir = realpath(__DIR__ . '/../images/profile_pictures') . DIRECTORY_SEPARATOR; 
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            if (!$upload_dir) {
                                $upload_dir = __DIR__ . '/../images/profile_pictures/';
                            }

                            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                            $new_filename = 'user_' . time() . '_' . uniqid() . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                                $img_name = $new_filename;
                            } else {
                                $_SESSION['error'] = "Failed to upload profile picture.";
                            }
                        }
                    }

                    // Only proceed if no errors from file upload
                    if (!isset($_SESSION['error'])) {
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        
                        $conn->begin_transaction();
                        
                        try {
                            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, is_active, img_name, store_id) VALUES (?, ?, ?, 'customer', 1, ?, NULL)");
                            $stmt->bind_param("sssss", $name, $email, $hashed_password, $img_name, NULL);
                            
                            if ($stmt->execute()) {
                                $new_user_id = $conn->insert_id;
                                
                                // Update filename with actual user_id
                                if ($img_name !== 'nopfp.jpg') {
                                    $old_path = $upload_dir . $img_name;
                                    $final_filename = 'user_' . $new_user_id . '_' . time() . '.' . $file_extension;
                                    $new_path = $upload_dir . $final_filename;
                                    
                                    if (rename($old_path, $new_path)) {
                                        $img_name = $final_filename;
                                        
                                        // Update the img_name in users table
                                        $update_img = $conn->prepare("UPDATE users SET img_name = ? WHERE user_id = ?");
                                        $update_img->bind_param("si", $img_name, $new_user_id);
                                        $update_img->execute();
                                        $update_img->close();
                                    }
                                }
                                
                                // Add user to group if invitation code was provided
                                if ($group_id) {
                                    // Determine default role based on group type
                                    $default_role = 'member';
                                    if ($group_type === 'household') {
                                        $default_role = 'child';
                                    } elseif ($group_type === 'small_business') {
                                        $default_role = 'staff';
                                    }
                                    
                                    $join_group = $conn->prepare("INSERT INTO group_members (group_id, user_id, member_role) VALUES (?, ?, ?)");
                                    $join_group->bind_param("iis", $group_id, $new_user_id, $default_role);
                                    $join_group->execute();
                                    $join_group->close();
                                }
                                
                                $conn->commit();
                                
                                if ($group_id) {
                                    $_SESSION['success'] = "Registration successful! You've been added to the group. You can now login.";
                                } else {
                                    $_SESSION['success'] = "Registration successful! You can now login.";
                                }
                            } else {
                                $conn->rollback();
                                $_SESSION['error'] = "Registration failed. Please try again.";
                                
                                // Delete uploaded file if registration fails
                                if ($img_name !== 'nopfp.jpg' && file_exists($upload_dir . $img_name)) {
                                    unlink($upload_dir . $img_name);
                                }
                            }
                            
                            $stmt->close();
                        } catch (Exception $e) {
                            $conn->rollback();
                            $_SESSION['error'] = "Registration failed. Please try again.";
                            
                            // Delete uploaded file if registration fails
                            if ($img_name !== 'nopfp.jpg' && file_exists($upload_dir . $img_name)) {
                                unlink($upload_dir . $img_name);
                            }
                        }
                    }
                }
            }
        }
    }
}

$conn->close();

// Provide page-specific CSS to header
if (!isset($baseUrl)) {
    $baseUrl = '/StockFlowExp';
}
$pageCss = '<link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/includes/style/pages/login.css">';
ob_end_flush();
require_once __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/alert.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<section class="luxury-auth-section">
    <div class="auth-background-overlay"></div>
    
    <div class="luxury-auth-container">
        <!-- Tab Navigation -->
        <div class="luxury-auth-toggle">
            <button class="luxury-tab active" data-tab="login">
                <span>Sign In</span>
            </button>
            <button class="luxury-tab" data-tab="register">
                <span>Create Account</span>
            </button>
        </div>

        <!-- Login Form -->
        <div class="luxury-form-wrapper active" id="login-form">
            <div class="form-header">
                <h2 class="luxury-auth-title">Welcome Back</h2>
                <p class="luxury-auth-subtitle">Sign in to continue your journey</p>
            </div>
            
            <form method="POST" action="" class="luxury-auth-form" novalidate>
                <?php if (!empty($redirect_url)): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">
                <?php endif; ?>
                
                <!-- Email Field -->
                <div class="luxury-form-group">
                    <label for="login-email" class="luxury-label">Email Address</label>
                    <input type="text" id="login-email" name="email" class="luxury-input" placeholder="Enter your email">
                    <span class="error-message" id="login-email-error"></span>
                </div>
                
                <!-- Password Field -->
                <div class="luxury-form-group">
                    <label for="login-password" class="luxury-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="login-password" name="password" class="luxury-input" placeholder="Enter your password">
                        <button type="button" class="password-toggle" onclick="toggleLoginPassword()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <span class="error-message" id="login-password-error"></span>
                </div>
                
                <button type="submit" name="login" class="luxury-btn luxury-btn-primary">
                    <span>Sign In</span>
                </button>
            </form>
        </div>

        <!-- Register Form -->
        <div class="luxury-form-wrapper" id="register-form">
            <div class="form-header">
                <h2 class="luxury-auth-title">Create Account</h2>
                <p class="luxury-auth-subtitle">Join StockFlow</p>
            </div>
            
            <form method="POST" action="" class="luxury-auth-form" enctype="multipart/form-data" novalidate>
                <!-- Profile Picture Upload -->
                <div class="luxury-form-group">
                    <label class="luxury-label">Profile Picture <span class="optional">(Optional)</span></label>
                    <div class="profile-picture-upload">
                        <div class="current-avatar-section">
                            <div class="avatar-preview">
                                <img id="register-avatar-preview" 
                     src="<?php echo htmlspecialchars($baseUrl); ?>/images/profile_pictures/nopfp.jpg" 
                     alt="Profile Preview"
                     class="current-avatar"
                     onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/images/profile_pictures/nopfp.jpg';">
                            </div>
                        </div>
        
                        <div class="file-upload-group">
                            <label for="register-profile-picture" class="file-upload-label">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                <span class="upload-text">Choose New Picture</span>
                                <span class="upload-hint">JPG, PNG, GIF • Max 5MB</span>
                            </label>
                            <input type="file" 
                   id="register-profile-picture" 
                   name="profile_picture" 
                   accept="image/jpeg,image/png,image/jpg,image/gif" 
                   class="file-input"
                   onchange="previewRegisterImage(this)">
                            <div class="file-name" id="register-file-name"></div>
                        </div>
                    </div>
                    <span class="error-message" id="register-picture-error"></span>
                </div>

                <div class="luxury-form-group">
                    <label for="register-name" class="luxury-label">Full Name <span class="required">*</span></label>
                    <input type="text" id="register-name" name="name" class="luxury-input" placeholder="Your Full Name">
                    <span class="error-message" id="register-name-error"></span>
                </div>
                
                <div class="luxury-form-group">
                    <label for="register-email" class="luxury-label">Email Address <span class="required">*</span></label>
                    <input type="text" id="register-email" name="email" class="luxury-input" placeholder="your@email.com">
                    <span class="error-message" id="register-email-error"></span>
                </div>

                <!-- Invitation Code Field -->
                <div class="luxury-form-group">
                    <label for="register-invitation" class="luxury-label">Invitation Code <span class="optional">(Optional)</span></label>
                    <input type="text" 
                           id="register-invitation" 
                           name="invitation_code" 
                           class="luxury-input invitation-input" 
                           placeholder="Enter 8-digit code"
                           maxlength="8"
                           pattern="[0-9]{8}">
                    <div class="field-hint">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        <span>Have an invitation code? Enter it to join an existing group automatically</span>
                    </div>
                    <span class="error-message" id="register-invitation-error"></span>
                </div>
                
                <div class="luxury-form-group">
                    <label for="register-password" class="luxury-label">Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="register-password" name="password" class="luxury-input" placeholder="Enter secure password">
                        <button type="button" class="password-toggle" onclick="toggleRegisterPassword()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <div class="password-requirements" id="passwordRequirements">
                        <div class="req" data-requirement="length">* 8-12 characters</div>
                        <div class="req" data-requirement="uppercase">* At least one uppercase letter (A-Z)</div>
                        <div class="req" data-requirement="lowercase">* At least one lowercase letter (a-z)</div>
                        <div class="req" data-requirement="number">* At least one number (0-9)</div>
                        <div class="req" data-requirement="special">* At least one special character (!@#$%^&*)</div>
                    </div>
                    <span class="error-message" id="register-password-error"></span>
                </div>
                
                <div class="luxury-form-group">
                    <label for="register-confirm" class="luxury-label">Confirm Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="register-confirm" name="confirm_password" class="luxury-input" placeholder="Re-enter password">
                        <button type="button" class="password-toggle" onclick="toggleConfirmPassword()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <span class="error-message" id="register-confirm-error"></span>
                </div>
                
                <button type="submit" name="register" class="luxury-btn luxury-btn-primary">
                    <span>Create Account</span>
                </button>
            </form>
        </div>
    </div>
</section>

<script src="<?= htmlspecialchars($baseUrl) ?>/includes/js/pages/login.js" defer></script>

<script>
// Format invitation code input - only allow numbers
document.getElementById('register-invitation')?.addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '').slice(0, 8);
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>