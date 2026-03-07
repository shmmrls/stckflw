<?php
/**
 * Email Configuration for Store Registration
 * Uses Mailtrap SMTP for email notifications
 */

// Mailtrap SMTP Configuration
define('MAILTRAP_HOST', 'smtp.mailtrap.io');
define('MAILTRAP_PORT', 2525);
define('MAILTRAP_USERNAME', '90dc2b77c5ab41'); // Replace with your Mailtrap username
define('MAILTRAP_PASSWORD', '0c9b095e915d27'); // Replace with your Mailtrap password

// Email Settings
define('EMAIL_FROM_ADDRESS', 'noreply@stockflow.com');
define('EMAIL_FROM_NAME', 'StockFlow - Grocery Management');

// Production SMTP (for real emails when ready)
// Note: Google SMTP removed - configure your preferred SMTP provider here
// define('PROD_SMTP_HOST', 'smtp.gmail.com'); // Example: Gmail
// define('PROD_SMTP_PORT', 587);
// define('PROD_SMTP_USERNAME', 'Stock Flow');
// define('PROD_SMTP_PASSWORD', 'nosn hgcs bajk nmwx');
// define('PROD_SMTP_ENCRYPTION', 'tls');

// Email Sending Modes
define('SEND_TO_MAILTRAP', true);  // Always send to Mailtrap for testing
define('SEND_TO_REAL_EMAIL', true); // Set to true when ready for production

/**
 * Check if email is a real email address (not a test email)
 */
function isRealEmail($email) {
    $test_domains = ['test.com', 'example.com', 'mailtrap.io', 'mailinator.com'];
    $domain = substr(strrchr($email, "@"), 1);
    return !in_array(strtolower($domain), $test_domains);
}

/**
 * Send SMTP Email
 */
function sendSMTPEmail($host, $port, $username, $password, $from_email, $from_name, $to_email, $to_name, $subject, $html_body, $encryption = 'none') {
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Connect to SMTP server
        if ($encryption === 'ssl') {
            $socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        } else {
            $socket = @stream_socket_client("{$host}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        }
        
        if (!$socket) {
            error_log("SMTP Connection Error: $errstr ($errno)");
            return false;
        }
        
        // Read server greeting
        fgets($socket, 515);
        
        // Send EHLO
        fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
        
        // Read EHLO response
        do {
            $response = fgets($socket, 515);
        } while (substr(trim($response), 0, 3) === '250' && substr(trim($response), 3, 1) === '-');
        
        // Handle STARTTLS if needed
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            
            if (strpos($response, '220') === false) {
                error_log("STARTTLS failed: $response");
                fclose($socket);
                return false;
            }
            
            // Enable TLS encryption
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("TLS encryption failed");
                fclose($socket);
                return false;
            }
            
            // Send EHLO again after STARTTLS
            fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
            do {
                $response = fgets($socket, 515);
            } while (substr(trim($response), 0, 3) === '250' && substr(trim($response), 3, 1) === '-');
        }
        
        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '334') === false) {
            error_log("AUTH LOGIN failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send username
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '334') === false) {
            error_log("Username auth failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send password
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '235') === false) {
            error_log("Password auth failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send MAIL FROM
        fputs($socket, "MAIL FROM: <{$from_email}>\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '250') === false) {
            error_log("MAIL FROM failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send RCPT TO
        fputs($socket, "RCPT TO: <{$to_email}>\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '250') === false) {
            error_log("RCPT TO failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send DATA command
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '354') === false) {
            error_log("DATA command failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send email headers and body
        $message = "From: {$from_name} <{$from_email}>\r\n";
        $message .= "To: {$to_name} <{$to_email}>\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $html_body;
        $message .= "\r\n.\r\n";
        
        // Send message
        fputs($socket, $message);
        $response = fgets($socket, 515);
        
        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fgets($socket, 515);
        
        fclose($socket);
        
        // Check if email was accepted
        if (strpos($response, '250') !== false) {
            return true;
        } else {
            error_log("Email send failed: $response");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("SMTP Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Log email activity to database
 */
function logStoreEmail($conn, $store_id, $admin_email, $subject, $status) {
    try {
        $log_stmt = $conn->prepare("INSERT INTO store_email_logs (store_id, recipient_email, subject, sent_at, status) VALUES (?, ?, ?, NOW(), ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("isss", $store_id, $admin_email, $subject, $status);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Store email log warning: " . $e->getMessage());
    }
}

/**
 * Generate a unique verification token
 */
function generateVerificationToken() {
    return bin2hex(random_bytes(32)); // 64-character hex string
}

/**
 * Save verification token to database
 */
function saveVerificationToken($conn, $store_id, $token) {
    try {
        // Token expires in 24 hours
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $conn->prepare("UPDATE grocery_stores SET verification_token = ?, verification_token_expires = ? WHERE store_id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $token, $expires, $store_id);
            $stmt->execute();
            $stmt->close();
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Save verification token error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get base URL for verification links
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get the base path from the current request
    $basePath = '';
    if (isset($_SERVER['PHP_SELF'])) {
        $pathParts = pathinfo($_SERVER['PHP_SELF']);
        $dirName = $pathParts['dirname'] ?? '';
        // Remove /grocery from the path to get the application root
        $basePath = str_replace('/grocery', '', $dirName);
    }
    
    return $protocol . '://' . $host . $basePath;
}