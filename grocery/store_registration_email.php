<?php
/**
 * Store Registration Email Notification with Email Verification
 * Sends confirmation email to newly registered grocery stores with verification link
 */

require_once 'store_email_config.php';

/**
 * Send registration confirmation email with verification link
 */
function sendStoreRegistrationEmail($conn, $store_id, $admin_name, $admin_email, $store_name) {
    // Generate verification token
    $verification_token = generateVerificationToken();

    // Save token to database
    if (!saveVerificationToken($conn, $store_id, $verification_token)) {
        error_log("Failed to save verification token for store_id: $store_id");
        return [
            'success' => false,
            'message' => 'Failed to generate verification token'
        ];
    }

    // Build verification URL
    $base_url         = getBaseUrl();
    $verification_url = $base_url . "/grocery/verify_email.php?token=" . urlencode($verification_token);

    // Build the email HTML
    $email_html = buildStoreRegistrationEmailHTML($admin_name, $store_name, $store_id, $verification_url);

    $subject         = "Welcome to StockFlow — Please Verify Your Email";
    $results         = [];
    $overall_success = false;

    // Send to Mailtrap (for testing)
    if (SEND_TO_MAILTRAP) {
        try {
            $mailtrap_result = sendSMTPEmail(
                MAILTRAP_HOST, MAILTRAP_PORT, MAILTRAP_USERNAME, MAILTRAP_PASSWORD,
                EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME,
                $admin_email, $admin_name, $subject, $email_html, 'none'
            );
            if ($mailtrap_result) {
                $results[] = "✓ Sent to Mailtrap ({$admin_email})";
                logStoreEmail($conn, $store_id, $admin_email, $subject, 'mailtrap_sent');
            } else {
                $results[] = "✗ Failed to send to Mailtrap";
                logStoreEmail($conn, $store_id, $admin_email, $subject, 'mailtrap_failed');
            }
        } catch (Exception $e) {
            $results[] = "✗ Mailtrap error: " . $e->getMessage();
            error_log("Mailtrap send error: " . $e->getMessage());
        }
    }

    // Send to real email (if enabled and valid email)
    if (SEND_TO_REAL_EMAIL && isRealEmail($admin_email)) {
        try {
            $results[] = "ℹ Production email not configured - only Mailtrap active";
            logStoreEmail($conn, $store_id, $admin_email, $subject, 'production_not_configured');
        } catch (Exception $e) {
            $results[] = "✗ Production email error: " . $e->getMessage();
            error_log("Production email send error: " . $e->getMessage());
        }
    } elseif (SEND_TO_REAL_EMAIL) {
        $results[] = "ℹ Skipped real email (test/invalid address detected)";
        logStoreEmail($conn, $store_id, $admin_email, $subject, 'skipped_test_email');
    }

    $message = implode(" | ", $results);
    return [
        'success' => $overall_success || (SEND_TO_MAILTRAP && count($results) > 0),
        'message' => $message
    ];
}

/**
 * Build HTML email template — styled to match the StockFlow luxury auth aesthetic
 */
function buildStoreRegistrationEmailHTML($admin_name, $store_name, $store_id, $verification_url) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to StockFlow — Verify Your Email</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
    </head>
    <body style="margin:0;padding:0;background-color:#fafafa;font-family:\'Montserrat\',Arial,sans-serif;">

        <!-- Wrapper -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa;padding:48px 20px;">
            <tr>
                <td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border:1px solid #e5e5e5;box-shadow:0 2px 8px rgba(0,0,0,0.04);">

                        <!-- Top accent border -->
                        <tr>
                            <td style="height:3px;background-color:#7ed957;font-size:0;line-height:0;">&nbsp;</td>
                        </tr>

                        <!-- Header -->
                        <tr>
                            <td style="padding:40px 48px 32px;border-bottom:1px solid #e5e5e5;">
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td>
                                            <p style="margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:26px;font-weight:400;color:#1a1a1a;letter-spacing:-0.5px;">
                                                StockFlow
                                            </p>
                                            <p style="margin:4px 0 0;font-size:10px;font-weight:500;color:#666666;letter-spacing:2px;text-transform:uppercase;">
                                                Grocery Management Platform
                                            </p>
                                        </td>
                                        <td align="right" style="vertical-align:middle;">
                                            <span style="display:inline-block;padding:6px 14px;background-color:#1a1a1a;color:#ffffff;font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;">
                                                New Registration
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Hero: icon + title -->
                        <tr>
                            <td style="padding:48px 48px 32px;text-align:center;">
                                <div style="width:64px;height:64px;border:2px solid #1a1a1a;border-radius:50%;margin:0 auto 24px;line-height:60px;text-align:center;font-size:24px;color:#1a1a1a;">
                                    ✉
                                </div>
                                <h1 style="margin:0 0 10px;font-family:\'Playfair Display\',Georgia,serif;font-size:32px;font-weight:400;color:#1a1a1a;letter-spacing:-0.5px;">
                                    Verify Your Email
                                </h1>
                                <p style="margin:0;font-size:13px;font-weight:300;color:#666666;letter-spacing:0.3px;">
                                    One step away from activating your account
                                </p>
                            </td>
                        </tr>

                        <!-- Greeting + body copy -->
                        <tr>
                            <td style="padding:0 48px 32px;">
                                <p style="margin:0 0 18px;font-size:14px;font-weight:400;color:#1a1a1a;line-height:1.7;">
                                    Hello <strong>' . htmlspecialchars($admin_name) . '</strong>,
                                </p>
                                <p style="margin:0;font-size:14px;font-weight:300;color:#666666;line-height:1.8;">
                                    Thank you for registering <strong style="color:#1a1a1a;">' . htmlspecialchars($store_name) . '</strong> with StockFlow.
                                    To complete your registration and access the platform, please verify your email address
                                    using the button below.
                                </p>
                            </td>
                        </tr>

                        <!-- Verification required notice -->
                        <tr>
                            <td style="padding:0 48px 32px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;border-left:3px solid #1a1a1a;">
                                    <tr>
                                        <td style="padding:20px 24px;">
                                            <p style="margin:0 0 8px;font-size:10px;font-weight:600;color:#1a1a1a;letter-spacing:1.5px;text-transform:uppercase;">
                                                Verification Required
                                            </p>
                                            <p style="margin:0;font-size:13px;font-weight:300;color:#666666;line-height:1.7;">
                                                You must verify your email before you can log in to your store dashboard.
                                                This link will expire in <strong style="color:#1a1a1a;">24 hours</strong>.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- CTA button -->
                        <tr>
                            <td style="padding:0 48px 40px;text-align:center;">
                                <a href="' . htmlspecialchars($verification_url) . '"
                                   style="display:inline-block;padding:16px 48px;background-color:#1a1a1a;color:#ffffff;text-decoration:none;font-family:\'Montserrat\',Arial,sans-serif;font-size:11px;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;">
                                    Verify Email Address
                                </a>
                                <p style="margin:16px 0 0;font-size:11px;color:#999999;">
                                    Or copy this link:
                                    <a href="' . htmlspecialchars($verification_url) . '" style="color:#666666;word-break:break-all;">' . htmlspecialchars($verification_url) . '</a>
                                </p>
                            </td>
                        </tr>

                        <!-- Divider -->
                        <tr>
                            <td style="padding:0 48px;"><hr style="border:none;border-top:1px solid #e5e5e5;margin:0;"></td>
                        </tr>

                        <!-- Store details -->
                        <tr>
                            <td style="padding:40px 48px 32px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;border-left:3px solid #7ed957;">
                                    <tr>
                                        <td style="padding:20px 24px;">
                                            <p style="margin:0 0 14px;font-size:10px;font-weight:600;color:#666666;letter-spacing:1.5px;text-transform:uppercase;">
                                                Store Details
                                            </p>
                                            <table width="100%" cellpadding="4" cellspacing="0">
                                                <tr>
                                                    <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#999999;width:40%;">Store ID</td>
                                                    <td style="font-size:13px;font-weight:500;color:#1a1a1a;text-align:right;">#' . str_pad($store_id, 5, '0', STR_PAD_LEFT) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#999999;">Store Name</td>
                                                    <td style="font-size:13px;font-weight:500;color:#1a1a1a;text-align:right;">' . htmlspecialchars($store_name) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#999999;">Admin</td>
                                                    <td style="font-size:13px;font-weight:500;color:#1a1a1a;text-align:right;">' . htmlspecialchars($admin_name) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#999999;">Status</td>
                                                    <td style="text-align:right;">
                                                        <span style="font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#1a1a1a;border:1px solid #1a1a1a;padding:3px 8px;">
                                                            Pending Verification
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- After verification section heading -->
                        <tr>
                            <td style="padding:0 48px 20px;">
                                <p style="margin:0;font-size:10px;font-weight:600;color:#666666;letter-spacing:2px;text-transform:uppercase;">
                                    After Verification
                                </p>
                            </td>
                        </tr>

                        <!-- Step 01 -->
                        <tr>
                            <td style="padding:0 48px 10px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;">
                                    <tr>
                                        <td style="width:48px;padding:16px 0 16px 20px;vertical-align:top;">
                                            <span style="font-size:11px;font-weight:600;color:#7ed957;letter-spacing:1px;">01</span>
                                        </td>
                                        <td style="padding:16px 20px 16px 0;vertical-align:top;">
                                            <p style="margin:0 0 4px;font-size:13px;font-weight:500;color:#1a1a1a;">Full Login Access</p>
                                            <p style="margin:0;font-size:12px;font-weight:300;color:#999999;line-height:1.6;">Log in to your grocery admin dashboard immediately after verifying.</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Step 02 -->
                        <tr>
                            <td style="padding:0 48px 10px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;">
                                    <tr>
                                        <td style="width:48px;padding:16px 0 16px 20px;vertical-align:top;">
                                            <span style="font-size:11px;font-weight:600;color:#7ed957;letter-spacing:1px;">02</span>
                                        </td>
                                        <td style="padding:16px 20px 16px 0;vertical-align:top;">
                                            <p style="margin:0 0 4px;font-size:13px;font-weight:500;color:#1a1a1a;">Store Management</p>
                                            <p style="margin:0;font-size:12px;font-weight:300;color:#999999;line-height:1.6;">Access inventory management, purchase orders, and supplier tools.</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Step 03 -->
                        <tr>
                            <td style="padding:0 48px 40px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;">
                                    <tr>
                                        <td style="width:48px;padding:16px 0 16px 20px;vertical-align:top;">
                                            <span style="font-size:11px;font-weight:600;color:#7ed957;letter-spacing:1px;">03</span>
                                        </td>
                                        <td style="padding:16px 20px 16px 0;vertical-align:top;">
                                            <p style="margin:0 0 4px;font-size:13px;font-weight:500;color:#1a1a1a;">Pending Admin Review</p>
                                            <p style="margin:0;font-size:12px;font-weight:300;color:#999999;line-height:1.6;">Our team will review your store for final approval — usually within 24–48 hours.</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Security notice -->
                        <tr>
                            <td style="padding:0 48px 48px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;border-left:3px solid #7ed957;">
                                    <tr>
                                        <td style="padding:20px 24px;">
                                            <p style="margin:0 0 8px;font-size:10px;font-weight:600;color:#666666;letter-spacing:1.5px;text-transform:uppercase;">
                                                Security Notice
                                            </p>
                                            <p style="margin:0;font-size:13px;font-weight:300;color:#666666;line-height:1.7;">
                                                If you did not create an account with StockFlow, please ignore this email or contact us at
                                                <a href="mailto:support@stockflow.com" style="color:#1a1a1a;font-weight:500;text-decoration:none;">support@stockflow.com</a>.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Bottom accent border -->
                        <tr>
                            <td style="height:3px;background-color:#7ed957;font-size:0;line-height:0;">&nbsp;</td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="padding:24px 48px;text-align:center;border-top:1px solid #e5e5e5;">
                                <p style="margin:0 0 8px;font-size:11px;font-weight:300;color:#999999;">
                                    This verification link will expire in 24 hours.
                                </p>
                                <p style="margin:0 0 12px;font-size:11px;color:#cccccc;">
                                    &copy; ' . date('Y') . ' StockFlow. All rights reserved.
                                </p>
                                <a href="#" style="color:#666666;text-decoration:none;font-size:11px;margin:0 10px;">Privacy Policy</a>
                                <span style="color:#e5e5e5;">|</span>
                                <a href="#" style="color:#666666;text-decoration:none;font-size:11px;margin:0 10px;">Terms of Service</a>
                                <span style="color:#e5e5e5;">|</span>
                                <a href="#" style="color:#666666;text-decoration:none;font-size:11px;margin:0 10px;">Support</a>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>

    </body>
    </html>';

    return $html;
}