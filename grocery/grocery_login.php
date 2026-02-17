<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/store_registration_email.php';  // ADD THIS LINE

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // Check user role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'grocery_admin') {
        ob_end_clean();
        header("Location: grocery_dashboard.php");
        exit();
    } else {
        ob_end_clean();
        header("Location: ../pages/dashboard.php");
        exit();
    }
}

// Check if redirected from unauthorized access
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $_SESSION['error'] = "You must be logged in to access that page.";
}

if (isset($_GET['error']) && $_GET['error'] === 'customer_only') {
    $_SESSION['error'] = "This area is for grocery administrators only.";
}

$redirect_url = isset($_GET['redirect']) ? strtok($_GET['redirect'], '#') : '';

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
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email)) {
            $_SESSION['error'] = "Email address is required.";
        } elseif (empty($password)) {
            $_SESSION['error'] = "Password is required.";
        } elseif (!validateEmail($email)) {
            $_SESSION['error'] = "Please enter a valid email address.";
        } else {
            $stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.email, u.password, u.role, u.is_active, u.store_id, gs.store_name, gs.is_verified 
                                   FROM users u 
                                   LEFT JOIN grocery_stores gs ON u.store_id = gs.store_id 
                                   WHERE u.email = ? AND u.role = 'grocery_admin' AND gs.is_verified = 1");
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
                        $_SESSION['store_id'] = $user['store_id'];
                        $_SESSION['store_name'] = $user['store_name'];
                        
                        ob_end_clean();
                        if (!empty($redirect_url)) {
                            header("Location: " . $redirect_url);
                        } else {
                            header("Location: grocery_dashboard.php");
                        }
                        exit();
                    }
                } else {
                    $_SESSION['error'] = "Invalid email or password.";
                }
            } else {
                $_SESSION['error'] = "Invalid email or password. Please ensure you have verified your email address before logging in.";
            }

            $stmt->close();
        }
    } elseif (isset($_POST['register'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $store_name = trim($_POST['store_name']);
        $business_address = trim($_POST['business_address']);
        $contact_number = trim($_POST['contact_number']);
        $store_email = trim($_POST['store_email']);
        
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
        } elseif (empty($store_name)) {
            $_SESSION['error'] = "Store name is required.";
        } elseif (empty($business_address)) {
            $_SESSION['error'] = "Business address is required.";
        } elseif (empty($contact_number)) {
            $_SESSION['error'] = "Contact number is required.";
        } elseif (empty($store_email)) {
            $_SESSION['error'] = "Store email is required.";
        } elseif (!validateEmail($store_email)) {
            $_SESSION['error'] = "Please enter a valid store email address.";
        } else {
            // Check if email already exists in users table
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Email already registered.";
            } else {
                // Check if store email already exists in grocery_stores table
                $store_email_check = $conn->prepare("SELECT store_id FROM grocery_stores WHERE email = ?");
                $store_email_check->bind_param("s", $store_email);
                $store_email_check->execute();
                $store_email_result = $store_email_check->get_result();
                
                if ($store_email_result->num_rows > 0) {
                    $_SESSION['error'] = "Store email already registered.";
                    $store_email_check->close();
                } else {
                    $store_email_check->close();
                    
                    // Check if store name already exists
                    $store_check = $conn->prepare("SELECT store_id FROM grocery_stores WHERE store_name = ?");
                    $store_check->bind_param("s", $store_name);
                    $store_check->execute();
                    $store_result = $store_check->get_result();
                    
                    if ($store_result->num_rows > 0) {
                        $_SESSION['error'] = "Store name already exists. Please choose a different name.";
                        $store_check->close();
                    } else {
                        $store_check->close();
                    
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    try {
                        // Insert grocery store first
                        $store_stmt = $conn->prepare("INSERT INTO grocery_stores (store_name, business_address, contact_number, email, is_verified, is_active) VALUES (?, ?, ?, ?, 0, 1)");
                        $store_stmt->bind_param("ssss", $store_name, $business_address, $contact_number, $store_email);
                        
                        if ($store_stmt->execute()) {
                            $new_store_id = $conn->insert_id;
                            $store_stmt->close();
                            
                            // Insert user as grocery admin
                            $user_stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, store_id, is_active, img_name) VALUES (?, ?, ?, 'grocery_admin', ?, 1, 'nopfp.jpg')");
                            $user_stmt->bind_param("sssi", $name, $email, $hashed_password, $new_store_id);
                            
                            if ($user_stmt->execute()) {
                                $conn->commit();
                                
                                // SEND REGISTRATION EMAIL
                                $email_result = sendStoreRegistrationEmail($conn, $new_store_id, $name, $email, $store_name);
                                
                                if ($email_result['success']) {
                                    $_SESSION['success'] = "Registration successful! A confirmation email has been sent to your address.";
                                } else {
                                    $_SESSION['success'] = "Registration successful! You can now login. (Note: Email notification could not be sent)";
                                    error_log("Store registration email failed: " . $email_result['message']);
                                }
                            } else {
                                $conn->rollback();
                                $_SESSION['error'] = "Registration failed. Please try again.";
                            }
                            
                            $user_stmt->close();
                        } else {
                            $conn->rollback();
                            $_SESSION['error'] = "Failed to register store. Please try again.";
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['error'] = "Registration failed. Please try again.";
                        error_log("Store registration error: " . $e->getMessage());
                    }
                }
            }
            
            $stmt->close();
        }
    }
    }
}

$conn->close();

// Provide page-specific CSS to header
if (!isset($baseUrl)) {
    $baseUrl = '/StockFlowExp';
}
$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/login.css">';
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
                <span>Store Login</span>
            </button>
            <button class="luxury-tab" data-tab="register">
                <span>Register Store</span>
            </button>
        </div>

        <!-- Login Form -->
        <div class="luxury-form-wrapper active" id="login-form">
            <div class="form-header">
                <h2 class="luxury-auth-title">Grocery Admin Portal</h2>
                <p class="luxury-auth-subtitle">Sign in to manage your store inventory</p>
            </div>
            
            <form method="POST" action="" class="luxury-auth-form" novalidate>
                <?php if (!empty($redirect_url)): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">
                <?php endif; ?>
                
                <!-- Email Field -->
                <div class="luxury-form-group">
                    <label for="login-email" class="luxury-label">Admin Email Address</label>
                    <input type="text" id="login-email" name="email" class="luxury-input" placeholder="Enter your admin email">
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
                
                <div class="auth-footer">
                    <p style="font-size: 12px; color: #666; margin-top: 20px;">
                        Customer? <a href="<?php echo htmlspecialchars($baseUrl); ?>/pages/login.php" style="color: #000; font-weight: 500;">Sign in here</a>
                    </p>
                </div>
            </form>
        </div>

        <!-- Register Form -->
        <div class="luxury-form-wrapper" id="register-form">
            <div class="form-header">
                <h2 class="luxury-auth-title">Register Your Store</h2>
                <p class="luxury-auth-subtitle">Join StockFlow as a grocery partner</p>
            </div>
            
            <form method="POST" action="" class="luxury-auth-form" novalidate>
                <!-- Admin Information -->
                <div class="luxury-form-group">
                    <label for="register-name" class="luxury-label">Admin Full Name <span class="required">*</span></label>
                    <input type="text" id="register-name" name="name" class="luxury-input" placeholder="Your Full Name">
                    <span class="error-message" id="register-name-error"></span>
                </div>
                
                <div class="luxury-form-group">
                    <label for="register-email" class="luxury-label">Admin Email <span class="required">*</span></label>
                    <input type="text" id="register-email" name="email" class="luxury-input" placeholder="your@email.com">
                    <span class="error-message" id="register-email-error"></span>
                </div>

                <!-- Store Information -->
                <div class="luxury-form-group" style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e5e5e5;">
                    <label for="register-store-name" class="luxury-label">Store Name <span class="required">*</span></label>
                    <input type="text" id="register-store-name" name="store_name" class="luxury-input" placeholder="Your Store Name">
                    <span class="error-message" id="register-store-name-error"></span>
                </div>

                <div class="luxury-form-group">
                    <label for="register-address" class="luxury-label">Business Address <span class="required">*</span></label>
                    <input type="text" id="register-address" name="business_address" class="luxury-input" placeholder="Complete Business Address">
                    <span class="error-message" id="register-address-error"></span>
                </div>

                <div class="luxury-form-group">
                    <label for="register-contact" class="luxury-label">Contact Number <span class="required">*</span></label>
                    <input type="tel" id="register-contact" name="contact_number" class="luxury-input" placeholder="+63 XXX XXX XXXX">
                    <span class="error-message" id="register-contact-error"></span>
                </div>

                <div class="luxury-form-group">
                    <label for="register-store-email" class="luxury-label">Store Email <span class="required">*</span></label>
                    <input type="email" id="register-store-email" name="store_email" class="luxury-input" placeholder="store@email.com">
                    <span class="error-message" id="register-store-email-error"></span>
                </div>
                
                <!-- Password Fields -->
                <div class="luxury-form-group" style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e5e5e5;">
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
                    <span>Register Store</span>
                </button>
                
                <div class="auth-footer">
                    <p style="font-size: 11px; color: #999; margin-top: 15px; line-height: 1.6;">
                        Your account will be pending verification after registration. You will be able to login but some features may be restricted until verified.
                    </p>
                </div>
            </form>
        </div>
    </div>
</section>

<script src="<?= htmlspecialchars($baseUrl) ?>/includes/js/pages/login.js" defer></script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>