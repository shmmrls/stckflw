<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/grocery/store_email_config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.php');
    exit();
}

// ── Sanitize & validate inputs ────────────────────────────────────────
$name    = trim(strip_tags($_POST['name']    ?? ''));
$email   = trim(strip_tags($_POST['email']   ?? ''));
$subject = trim(strip_tags($_POST['subject'] ?? ''));
$message = trim(strip_tags($_POST['message'] ?? ''));

$errors = [];

// Handle file upload
$attachment_info = null;
$attachment_path = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed. Please try again.';
    } else {
        // Check file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            $errors[] = 'File size must be less than 5MB.';
        }
        
        // Check file type
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = 'Invalid file type. Allowed: Images, PDF, Word documents, Text files.';
        }
        
        if (empty($errors)) {
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid('contact_', true) . '.' . $file_extension;
            $upload_dir = __DIR__ . '/contact_attachments/';
            $attachment_path = $upload_dir . $unique_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $attachment_path)) {
                // Get base URL for absolute path
                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $base_path = str_replace('/contact_submit.php', '', $_SERVER['PHP_SELF']);
                
                $attachment_info = [
                    'original_name' => $file['name'],
                    'file_path' => $attachment_path,
                    'file_size' => $file['size'],
                    'mime_type' => $mime_type,
                    'url_path' => $base_url . $base_path . '/contact_attachments/' . $unique_filename
                ];
            } else {
                $errors[] = 'Failed to save uploaded file.';
            }
        }
    }
}

if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    $errors[] = 'Name must be between 2 and 100 characters.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $errors[] = 'Invalid email address.';
}

$allowed_subjects = [
    'General Inquiry',
    'Technical Support',
    'Account Issues',
    'Feature Request',
    'Partnership / Business',
    'Other',
];

if (empty($subject) || !in_array($subject, $allowed_subjects)) {
    $errors[] = 'Invalid subject.';
}

if (empty($message) || strlen($message) < 5 || strlen($message) > 2000) {
    $errors[] = 'Message must be between 5 and 2000 characters.';
}

if (!empty($errors)) {
    // Store errors in session to display on the form
    session_start();
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = [
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message
    ];
    header('Location: contact.php?error=1');
    exit();
}

// ── Build HTML email body ─────────────────────────────────────────────
$html_body = '
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Form Submission</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:\'Helvetica Neue\',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 20px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid rgba(0,0,0,0.08);">

          <!-- Header -->
          <tr>
            <td style="background:#0a0a0a;padding:30px 40px;">
              <p style="margin:0;font-family:Georgia,serif;font-size:22px;color:#ffffff;letter-spacing:-0.3px;">StockFlow</p>
              <p style="margin:6px 0 0;font-size:11px;color:rgba(255,255,255,0.45);letter-spacing:1.5px;text-transform:uppercase;">Contact Form Submission</p>
            </td>
          </tr>

          <!-- Accent Line -->
          <tr>
            <td style="height:3px;background:#7ed957;"></td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px 40px 30px;">
              <p style="margin:0 0 24px;font-size:14px;color:rgba(0,0,0,0.55);line-height:1.6;">
                You have received a new message through the StockFlow contact form.
              </p>

              <!-- Sender Details -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;border:1px solid rgba(0,0,0,0.08);background:#fafafa;">
                <tr>
                  <td style="padding:14px 20px;border-bottom:1px solid rgba(0,0,0,0.06);">
                    <span style="font-size:10px;letter-spacing:1.2px;text-transform:uppercase;color:rgba(0,0,0,0.4);display:block;margin-bottom:4px;">From</span>
                    <span style="font-size:14px;color:#0a0a0a;font-weight:500;">' . htmlspecialchars($name) . '</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:14px 20px;border-bottom:1px solid rgba(0,0,0,0.06);">
                    <span style="font-size:10px;letter-spacing:1.2px;text-transform:uppercase;color:rgba(0,0,0,0.4);display:block;margin-bottom:4px;">Reply To</span>
                    <a href="mailto:' . htmlspecialchars($email) . '" style="font-size:14px;color:#0a0a0a;text-decoration:none;border-bottom:1px solid rgba(0,0,0,0.2);">' . htmlspecialchars($email) . '</a>
                  </td>
                </tr>
                <tr>
                  <td style="padding:14px 20px;">
                    <span style="font-size:10px;letter-spacing:1.2px;text-transform:uppercase;color:rgba(0,0,0,0.4);display:block;margin-bottom:4px;">Subject</span>
                    <span style="font-size:14px;color:#0a0a0a;">' . htmlspecialchars($subject) . '</span>
                  </td>
                </tr>
              </table>

              <!-- Message -->
              <p style="margin:0 0 10px;font-size:10px;letter-spacing:1.2px;text-transform:uppercase;color:rgba(0,0,0,0.4);">Message</p>
              <div style="border:1px solid rgba(0,0,0,0.08);padding:20px 22px;background:#fafafa;font-size:14px;color:rgba(0,0,0,0.75);line-height:1.8;white-space:pre-wrap;">' . htmlspecialchars($message) . '</div>';

              // Add attachment info if present
              if ($attachment_info) {
                  $html_body .= '
              <!-- Attachment -->
              <div style="margin-top:24px;padding:16px 20px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;">
                <p style="margin:0 0 8px;font-size:10px;letter-spacing:1.2px;text-transform:uppercase;color:rgba(0,0,0,0.4);">Attachment</p>
                <div style="font-size:13px;color:rgba(0,0,0,0.7);">
                  <strong>File:</strong> ' . htmlspecialchars($attachment_info['original_name']) . '<br>
                  <strong>Size:</strong> ' . number_format($attachment_info['file_size'] / 1024, 2) . ' KB<br>
                  <strong>Type:</strong> ' . htmlspecialchars($attachment_info['mime_type']) . '<br>
                  <strong>Access:</strong> <a href="' . htmlspecialchars($attachment_info['url_path']) . '" style="color:#0a0a0a;text-decoration:underline;">Download File</a>
                </div>
              </div>';
              }

$html_body .= '
            </td>
          </tr>

          <!-- Reply CTA -->
          <tr>
            <td style="padding:0 40px 36px;">
              <a href="mailto:' . htmlspecialchars($email) . '" style="display:inline-block;background:#0a0a0a;color:#ffffff;text-decoration:none;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;padding:14px 28px;">
                Reply to ' . htmlspecialchars($name) . '
              </a>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="border-top:1px solid rgba(0,0,0,0.08);padding:22px 40px;background:#fafafa;">
              <p style="margin:0;font-size:11px;color:rgba(0,0,0,0.35);line-height:1.6;">
                This message was submitted via the StockFlow contact form on ' . date('F j, Y \a\t g:i A') . '.<br>
                Do not reply to this notification email directly — use the button above.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

// ── Send via Mailtrap SMTP (same as rest of project) ─────────────────
$email_subject = '[StockFlow Contact] ' . $subject . ' — from ' . $name;

$sent = sendSMTPEmail(
    MAILTRAP_HOST,
    MAILTRAP_PORT,
    MAILTRAP_USERNAME,
    MAILTRAP_PASSWORD,
    EMAIL_FROM_ADDRESS,
    EMAIL_FROM_NAME,
    'stockflowg6@gmail.com',   // to_email
    'StockFlow Team',           // to_name
    $email_subject,
    $html_body
);

// ── Redirect with result ──────────────────────────────────────────────
if ($sent) {
    header('Location: contact.php?sent=1');
} else {
    header('Location: contact.php?error=1');
}
exit();
?>