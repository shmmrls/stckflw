<?php
/**
 * Email Verification Page for Store Registration
 * Handles email verification via token link
 */

ob_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/verification_confirmation_email.php';

$verification_status = null;
$message = '';
$store_name = '';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    $conn  = getDBConnection();

    $stmt = $conn->prepare("
        SELECT store_id, store_name, is_verified, verification_token_expires
        FROM grocery_stores
        WHERE BINARY verification_token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $store = $result->fetch_assoc();

        if ($store['is_verified'] == 1) {
            $verification_status = 'already_verified';
            $message    = 'This email has already been verified. You can log in to your account.';
            $store_name = $store['store_name'];
        } elseif (strtotime($store['verification_token_expires']) < time()) {
            $verification_status = 'expired';
            $message    = 'This verification link has expired. Please contact support or register again.';
            $store_name = $store['store_name'];
        } else {
            $update_stmt = $conn->prepare("
                UPDATE grocery_stores
                SET is_verified = 1,
                    verification_token = NULL,
                    verification_token_expires = NULL
                WHERE store_id = ?
            ");
            $update_stmt->bind_param("i", $store['store_id']);

            if ($update_stmt->execute()) {
                $verification_status = 'success';
                $message    = 'Your email has been successfully verified. You may now log in to your store dashboard.';
                $store_name = $store['store_name'];

                $user_stmt = $conn->prepare("
                    SELECT email, full_name FROM users
                    WHERE store_id = ? AND role = 'grocery_admin' LIMIT 1
                ");
                $user_stmt->bind_param("i", $store['store_id']);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();

                if ($user_result->num_rows === 1) {
                    $user         = $user_result->fetch_assoc();
                    $email_result = sendVerificationConfirmationEmail(
                        $conn, $store['store_id'],
                        $user['full_name'], $user['email'], $store['store_name']
                    );
                    if ($email_result['success']) {
                        error_log("Verification confirmation email sent to: {$user['email']}");
                    } else {
                        error_log("Failed to send verification confirmation email: " . $email_result['message']);
                    }
                }
                $user_stmt->close();
                error_log("Store verified: ID={$store['store_id']}, Name={$store['store_name']}");
            } else {
                $verification_status = 'error';
                $message = 'An error occurred during verification. Please try again or contact support.';
            }
            $update_stmt->close();
        }
    } else {
        $verification_status = 'invalid';
        $message = 'Invalid verification link. Please check your email or contact support.';
    }

    $stmt->close();
    $conn->close();
} else {
    $verification_status = 'no_token';
    $message = 'No verification token provided.';
}

if (!isset($baseUrl)) {
    $baseUrl = '/StockFlowExp';
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/login.css">';
ob_end_flush();
require_once __DIR__ . '/../includes/header.php';

// ─── Status config ────────────────────────────────────────────────────────────
$statuses = [
    'success'          => ['symbol' => '✓', 'label' => 'Verified',        'title' => 'Email Verified',       'accent' => '#7ed957'],
    'already_verified' => ['symbol' => '◎', 'label' => 'Already Active',  'title' => 'Already Verified',     'accent' => '#1a1a1a'],
    'expired'          => ['symbol' => '◷', 'label' => 'Link Expired',     'title' => 'Link Expired',         'accent' => '#666666'],
    'error'            => ['symbol' => '✕', 'label' => 'Error',            'title' => 'Verification Failed',  'accent' => '#1a1a1a'],
    'invalid'          => ['symbol' => '✕', 'label' => 'Invalid',          'title' => 'Verification Failed',  'accent' => '#1a1a1a'],
    'no_token'         => ['symbol' => '✕', 'label' => 'No Token',         'title' => 'Verification Failed',  'accent' => '#1a1a1a'],
];

$cfg    = $statuses[$verification_status] ?? $statuses['error'];
$is_ok  = in_array($verification_status, ['success', 'already_verified']);
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ─── Verify page overrides / extensions ──────────────────────────────────── */
.verify-section {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 160px);
    padding: 60px 20px 40px;
    background: #fafafa;
    animation: fadeIn 0.6s ease;
}

@keyframes fadeIn  { from { opacity: 0; } to { opacity: 1; } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }

.verify-card {
    width: 100%;
    max-width: 520px;
    background: #ffffff;
    border: 1px solid #e5e5e5;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    animation: fadeInUp 0.7s ease;
}

/* Top accent line */
.verify-card__accent {
    height: 3px;
    background: var(--status-accent, #7ed957);
}

/* Header band */
.verify-card__header {
    padding: 36px 48px 32px;
    border-bottom: 1px solid #e5e5e5;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.verify-card__brand {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 22px;
    font-weight: 400;
    color: #1a1a1a;
    letter-spacing: -0.5px;
}

.verify-card__brand-sub {
    font-size: 9px;
    font-weight: 600;
    color: #999;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 3px;
}

.verify-card__badge {
    display: inline-block;
    padding: 6px 14px;
    background: var(--status-accent, #7ed957);
    color: #1a1a1a;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

/* Icon + title block */
.verify-card__hero {
    padding: 48px 48px 32px;
    text-align: center;
}

.verify-card__icon {
    width: 60px;
    height: 60px;
    border: 2px solid var(--status-accent, #1a1a1a);
    border-radius: 50%;
    margin: 0 auto 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: var(--status-accent, #1a1a1a);
    font-family: 'Montserrat', sans-serif;
    font-weight: 300;
}

.verify-card__title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 30px;
    font-weight: 400;
    color: #1a1a1a;
    margin: 0 0 10px;
    letter-spacing: -0.5px;
}

.verify-card__subtitle {
    font-size: 13px;
    font-weight: 300;
    color: #666;
    letter-spacing: 0.3px;
    margin: 0;
}

/* Body */
.verify-card__body {
    padding: 0 48px 48px;
}

/* Store name pill */
.verify-store-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border: 1px solid #e5e5e5;
    border-left: 3px solid var(--status-accent, #7ed957);
    margin-bottom: 28px;
}

.verify-store-pill__label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #999;
}

.verify-store-pill__value {
    font-size: 14px;
    font-weight: 500;
    color: #1a1a1a;
    text-align: right;
}

/* Message text */
.verify-message {
    font-size: 14px;
    font-weight: 300;
    color: #666;
    line-height: 1.8;
    margin: 0 0 32px;
}

/* Primary button */
.verify-btn {
    display: block;
    width: 100%;
    padding: 16px 24px;
    border: 1px solid #1a1a1a;
    background: #1a1a1a;
    color: #ffffff;
    text-align: center;
    text-decoration: none;
    font-family: 'Montserrat', sans-serif;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.3s ease;
    box-sizing: border-box;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}

.verify-btn::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
    transition: left 0.5s ease;
}

.verify-btn:hover::before { left: 100%; }

.verify-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.18);
}

.verify-btn--ghost {
    background: transparent;
    color: #1a1a1a;
}

.verify-btn--ghost:hover {
    background: #f5f5f5;
    box-shadow: none;
    transform: none;
}

/* Divider */
.verify-divider {
    border: none;
    border-top: 1px solid #e5e5e5;
    margin: 32px 0;
}

/* Info block */
.verify-info-block {
    padding: 20px 24px;
    border: 1px solid #e5e5e5;
    border-left: 3px solid #1a1a1a;
    margin-bottom: 16px;
}

.verify-info-block__heading {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #1a1a1a;
    margin: 0 0 10px;
}

.verify-info-block__body {
    font-size: 13px;
    font-weight: 300;
    color: #666;
    line-height: 1.7;
    margin: 0;
}

.verify-info-block__body a {
    color: #1a1a1a;
    font-weight: 500;
}

/* Next-step checklist */
.verify-steps {
    list-style: none;
    padding: 0;
    margin: 0;
}

.verify-steps li {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid #e5e5e5;
    font-size: 13px;
    font-weight: 300;
    color: #666;
    line-height: 1.6;
}

.verify-steps li:last-child {
    border-bottom: none;
}

.verify-steps__num {
    flex-shrink: 0;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1px;
    color: #7ed957;
    padding-top: 2px;
    min-width: 20px;
}

/* Bottom confirmation email notice */
.verify-notice {
    padding: 18px 20px;
    border: 1px solid #e5e5e5;
    border-left: 3px solid #7ed957;
    margin-bottom: 0;
}

.verify-notice p {
    margin: 0;
    font-size: 13px;
    font-weight: 300;
    color: #666;
    line-height: 1.7;
}

.verify-notice strong {
    font-weight: 500;
    color: #1a1a1a;
}

@media (max-width: 480px) {
    .verify-card__header,
    .verify-card__hero,
    .verify-card__body {
        padding-left: 24px;
        padding-right: 24px;
    }
}
</style>

<section class="verify-section">
    <div class="verify-card" style="--status-accent: <?php echo htmlspecialchars($cfg['accent']); ?>;">

        <!-- Top accent -->
        <div class="verify-card__accent"></div>

        <!-- Header -->
        <div class="verify-card__header">
            <div>
                <div class="verify-card__brand">StockFlow</div>
                <div class="verify-card__brand-sub">Grocery Management</div>
            </div>
            <span class="verify-card__badge"><?php echo htmlspecialchars($cfg['label']); ?></span>
        </div>

        <!-- Hero -->
        <div class="verify-card__hero">
            <div class="verify-card__icon"><?php echo $cfg['symbol']; ?></div>
            <h1 class="verify-card__title"><?php echo htmlspecialchars($cfg['title']); ?></h1>
            <p class="verify-card__subtitle">
                <?php if ($verification_status === 'success'): ?>
                    Welcome to StockFlow, <?php echo htmlspecialchars(isset($user['full_name']) ? $user['full_name'] : 'Admin'); ?>
                <?php elseif ($verification_status === 'already_verified'): ?>
                    Your account is active
                <?php elseif ($verification_status === 'expired'): ?>
                    Please request a new link
                <?php else: ?>
                    Something went wrong
                <?php endif; ?>
            </p>
        </div>

        <!-- Body -->
        <div class="verify-card__body">

            <?php if (!empty($store_name)): ?>
            <div class="verify-store-pill">
                <span class="verify-store-pill__label">Store</span>
                <span class="verify-store-pill__value"><?php echo htmlspecialchars($store_name); ?></span>
            </div>
            <?php endif; ?>

            <p class="verify-message"><?php echo htmlspecialchars($message); ?></p>

            <?php if ($is_ok): ?>

                <!-- CTA -->
                <a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/grocery_login.php"
                   class="verify-btn">
                    Login to Dashboard
                </a>

                <?php if ($verification_status === 'success'): ?>
                <!-- Confirmation email notice -->
                <div class="verify-notice" style="margin-bottom: 28px;">
                    <p><strong>Confirmation email sent.</strong> We've emailed you a summary with next steps and your login link. Check your inbox.</p>
                </div>
                <?php endif; ?>

                <hr class="verify-divider">

                <!-- Next steps -->
                <p style="font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:#999;margin:0 0 16px;">
                    Next Steps
                </p>
                <ul class="verify-steps">
                    <li>
                        <span class="verify-steps__num">01</span>
                        <span>Log in with your registered email and password.</span>
                    </li>
                    <li>
                        <span class="verify-steps__num">02</span>
                        <span>Complete your store profile — add business hours, delivery areas, and policies.</span>
                    </li>
                    <li>
                        <span class="verify-steps__num">03</span>
                        <span>Our admin team will review your store within 24–48 hours.</span>
                    </li>
                    <li>
                        <span class="verify-steps__num">04</span>
                        <span>Full features unlock after admin approval. You'll receive a notification email.</span>
                    </li>
                </ul>

            <?php elseif ($verification_status === 'expired'): ?>

                <div class="verify-info-block">
                    <p class="verify-info-block__heading">What to do next</p>
                    <p class="verify-info-block__body">
                        Contact our support team at
                        <a href="mailto:support@stockflow.com">support@stockflow.com</a>
                        to request a new verification link.
                    </p>
                </div>

                <a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/grocery_login.php"
                   class="verify-btn verify-btn--ghost">
                    Back to Login
                </a>

            <?php else: ?>

                <div class="verify-info-block">
                    <p class="verify-info-block__heading">Need Help?</p>
                    <p class="verify-info-block__body">
                        If you continue to experience issues, please contact us at
                        <a href="mailto:support@stockflow.com">support@stockflow.com</a>.
                    </p>
                </div>

                <a href="<?php echo htmlspecialchars($baseUrl); ?>/grocery/grocery_login.php"
                   class="verify-btn verify-btn--ghost">
                    Back to Login
                </a>

            <?php endif; ?>

        </div><!-- /.verify-card__body -->

        <!-- Bottom accent -->
        <div class="verify-card__accent"></div>

    </div><!-- /.verify-card -->
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>