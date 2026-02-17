<?php
/**
 * Email Verification Confirmation
 * Sends confirmation email after successful email verification
 */

require_once 'store_email_config.php';

/**
 * Send email verification confirmation
 */
function sendVerificationConfirmationEmail($conn, $store_id, $admin_name, $admin_email, $store_name) {
    $email_html = buildVerificationConfirmationEmailHTML($admin_name, $store_name, $store_id);
    
    $subject = "Email Verified — Welcome to StockFlow";
    
    $results = [];
    $overall_success = false;
    
    if (SEND_TO_MAILTRAP) {
        try {
            $mailtrap_result = sendSMTPEmail(
                MAILTRAP_HOST, MAILTRAP_PORT, MAILTRAP_USERNAME, MAILTRAP_PASSWORD,
                EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME,
                $admin_email, $admin_name, $subject, $email_html, 'none'
            );
            if ($mailtrap_result) {
                $results[] = "✓ Confirmation sent to Mailtrap ({$admin_email})";
                logStoreEmail($conn, $store_id, $admin_email, $subject, 'mailtrap_sent');
            } else {
                $results[] = "✗ Failed to send confirmation to Mailtrap";
                logStoreEmail($conn, $store_id, $admin_email, $subject, 'mailtrap_failed');
            }
        } catch (Exception $e) {
            $results[] = "✗ Mailtrap error: " . $e->getMessage();
            error_log("Mailtrap send error: " . $e->getMessage());
        }
    }
    
    if (SEND_TO_REAL_EMAIL && isRealEmail($admin_email)) {
        try {
            // Note: Production SMTP not configured - add your SMTP provider here
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
function buildVerificationConfirmationEmailHTML($admin_name, $store_name, $store_id) {
    $base_url  = getBaseUrl();
    $login_url = $base_url . "/grocery/grocery_login.php";

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verified — StockFlow</title>
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
                                            <span style="display:inline-block;padding:6px 14px;background-color:#7ed957;color:#1a1a1a;font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;">
                                                Verified
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Status Icon + Title -->
                        <tr>
                            <td style="padding:48px 48px 32px;text-align:center;">
                                <!-- Minimal checkmark circle -->
                                <div style="width:64px;height:64px;border:2px solid #1a1a1a;border-radius:50%;margin:0 auto 24px;display:inline-block;line-height:60px;text-align:center;font-size:26px;color:#1a1a1a;">
                                    ✓
                                </div>
                                <h1 style="margin:0 0 10px;font-family:\'Playfair Display\',Georgia,serif;font-size:32px;font-weight:400;color:#1a1a1a;letter-spacing:-0.5px;">
                                    Email Verified
                                </h1>
                                <p style="margin:0;font-size:13px;font-weight:300;color:#666666;letter-spacing:0.3px;">
                                    Your account is now active
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
                                    Your email has been successfully verified. You can now log in to your
                                    <strong style="color:#1a1a1a;">' . htmlspecialchars($store_name) . '</strong>
                                    dashboard and start managing your grocery store inventory.
                                </p>
                            </td>
                        </tr>

                        <!-- Store info block -->
                        <tr>
                            <td style="padding:0 48px 32px;">
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
                                                    <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#999999;">Access</td>
                                                    <td style="text-align:right;">
                                                        <span style="font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#1a1a1a;border:1px solid #1a1a1a;padding:3px 8px;">
                                                            Full Dashboard
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- CTA Button -->
                        <tr>
                            <td style="padding:0 48px 40px;text-align:center;">
                                <a href="' . htmlspecialchars($login_url) . '"
                                   style="display:inline-block;padding:16px 48px;background-color:#1a1a1a;color:#ffffff;text-decoration:none;font-size:11px;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;">
                                    Login to Dashboard
                                </a>
                                <p style="margin:16px 0 0;font-size:11px;color:#999999;">
                                    Or copy this link:
                                    <a href="' . htmlspecialchars($login_url) . '" style="color:#666666;word-break:break-all;">' . htmlspecialchars($login_url) . '</a>
                                </p>
                            </td>
                        </tr>

                        <!-- Divider -->
                        <tr>
                            <td style="padding:0 48px;"><hr style="border:none;border-top:1px solid #e5e5e5;margin:0;"></td>
                        </tr>

                        <!-- Getting Started steps -->
                        <tr>
                            <td style="padding:40px 48px 8px;">
                                <p style="margin:0 0 24px;font-size:10px;font-weight:600;color:#666666;letter-spacing:2px;text-transform:uppercase;">
                                    Getting Started
                                </p>
                            </td>
                        </tr>

                        <!-- Step rows -->
                        ' . buildEmailStep('01', 'Complete Your Store Profile', 'Add business hours, delivery areas, and store policies so customers can find you.') . '
                        ' . buildEmailStep('02', 'Set Up Your Inventory', 'Add grocery items, configure reorder levels, and manage stock quantities.') . '
                        ' . buildEmailStep('03', 'Connect with Suppliers', 'Add vendors and enable automated purchase orders for streamlined restocking.') . '
                        ' . buildEmailStep('04', 'Await Admin Verification', 'Our team will review your store within 24–48 hours for final approval.') . '

                        <!-- Notice: pending admin review -->
                        <tr>
                            <td style="padding:16px 48px 40px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;border-left:3px solid #1a1a1a;">
                                    <tr>
                                        <td style="padding:20px 24px;">
                                            <p style="margin:0 0 8px;font-size:10px;font-weight:600;color:#1a1a1a;letter-spacing:1.5px;text-transform:uppercase;">
                                                Admin Verification Pending
                                            </p>
                                            <p style="margin:0;font-size:13px;font-weight:300;color:#666666;line-height:1.7;">
                                                While your email is verified and you can log in, your store is pending admin review.
                                                Some features may be restricted until our team completes the process.
                                                You will receive another email once fully approved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Available features -->
                        <tr>
                            <td style="padding:0 48px 8px;">
                                <p style="margin:0 0 20px;font-size:10px;font-weight:600;color:#666666;letter-spacing:2px;text-transform:uppercase;">
                                    Available Features
                                </p>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="width:50%;padding-right:8px;padding-bottom:8px;">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;">
                                                <tr><td style="padding:14px 16px;">
                                                    <p style="margin:0 0 4px;font-size:12px;font-weight:500;color:#1a1a1a;">Inventory Management</p>
                                                    <p style="margin:0;font-size:11px;color:#999999;font-weight:300;">Real-time stock tracking</p>
                                                </td></tr>
                                            </table>
                                        </td>
                                        <td style="width:50%;padding-left:8px;padding-bottom:8px;">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;">
                                                <tr><td style="padding:14px 16px;">
                                                    <p style="margin:0 0 4px;font-size:12px;font-weight:500;color:#1a1a1a;">Purchase Orders</p>
                                                    <p style="margin:0;font-size:11px;color:#999999;font-weight:300;">Automated reorder system</p>
                                                </td></tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="width:50%;padding-right:8px;padding-bottom:8px;">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;">
                                                <tr><td style="padding:14px 16px;">
                                                    <p style="margin:0 0 4px;font-size:12px;font-weight:500;color:#1a1a1a;">Supplier Management</p>
                                                    <p style="margin:0;font-size:11px;color:#999999;font-weight:300;">Manage vendor relationships</p>
                                                </td></tr>
                                            </table>
                                        </td>
                                        <td style="width:50%;padding-left:8px;padding-bottom:8px;">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;">
                                                <tr><td style="padding:14px 16px;">
                                                    <p style="margin:0 0 4px;font-size:12px;font-weight:500;color:#1a1a1a;">Sales Analytics</p>
                                                    <p style="margin:0;font-size:11px;color:#999999;font-weight:300;">Performance metrics</p>
                                                </td></tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Support -->
                        <tr>
                            <td style="padding:24px 48px 48px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;border-left:3px solid #7ed957;">
                                    <tr>
                                        <td style="padding:20px 24px;">
                                            <p style="margin:0 0 8px;font-size:10px;font-weight:600;color:#666666;letter-spacing:1.5px;text-transform:uppercase;">
                                                Need Help?
                                            </p>
                                            <p style="margin:0 0 12px;font-size:13px;font-weight:300;color:#666666;line-height:1.7;">
                                                Our support team is here to assist you.
                                            </p>
                                            <p style="margin:0 0 6px;font-size:13px;color:#1a1a1a;">
                                                Email: <a href="mailto:support@stockflow.com" style="color:#1a1a1a;font-weight:500;">support@stockflow.com</a>
                                            </p>
                                            <p style="margin:0;font-size:13px;color:#1a1a1a;">
                                                Docs: <a href="#" style="color:#1a1a1a;font-weight:500;">help.stockflow.com</a>
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
                                    You are receiving this email because you verified your StockFlow account.
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

/**
 * Helper: render a numbered step row for the email
 */
function buildEmailStep($number, $title, $description) {
    return '
    <tr>
        <td style="padding:0 48px 12px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;">
                <tr>
                    <td style="width:48px;padding:16px 0 16px 20px;vertical-align:top;">
                        <span style="display:inline-block;font-family:\'Montserrat\',Arial,sans-serif;font-size:11px;font-weight:600;color:#7ed957;letter-spacing:1px;">' . $number . '</span>
                    </td>
                    <td style="padding:16px 20px 16px 0;vertical-align:top;">
                        <p style="margin:0 0 4px;font-size:13px;font-weight:500;color:#1a1a1a;">' . $title . '</p>
                        <p style="margin:0;font-size:12px;font-weight:300;color:#999999;line-height:1.6;">' . $description . '</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>';
}