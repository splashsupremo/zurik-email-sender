<?php
// send_registration_mail.php
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Allow GET for quick health/debug (optional)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "Render Registration Mailer: OK";
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

// Protect with API key
$apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey  = getenv('RENDER_API_KEY') ?: '';
if (!$expectedKey || !hash_equals($expectedKey, $apiKeyHeader)) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// Read JSON or form
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
} else {
    $data = $_POST;
}

// Required fields
$toEmail   = $data['email'] ?? '';
$fullName  = $data['full_name'] ?? 'Applicant';
$subject   = $data['subject'] ?? 'Registration Received - Zurik Truck Academy';
// Optional: allow direct HTML override
$inlineHtml = $data['html'] ?? '';

// Validate
if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Invalid email";
    exit;
}

// Build HTML body
if ($inlineHtml) {
    $htmlBody = $inlineHtml;
} else {
    $templatePath = __DIR__ . '/email_templates/registration_success.html';
    if (!file_exists($templatePath)) {
        http_response_code(500);
        echo "Template not found";
        exit;
    }
    $htmlBody = file_get_contents($templatePath);
    // Replace placeholders
    $htmlBody = str_replace('{{NAME}}', htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'), $htmlBody);
}

// PHPMailer + Gmail SMTP
$mail = new PHPMailer(true);
try {
    $gmailUser = getenv('GMAIL_USER');
    $gmailPass = getenv('GMAIL_PASS'); // Google App Password (16 chars)
    $fromEmail = getenv('FROM_EMAIL') ?: $gmailUser;
    $fromName  = getenv('FROM_NAME') ?: 'Zurik Truck Academy';

    if (!$gmailUser || !$gmailPass) {
        throw new Exception('SMTP credentials not configured.');
    }

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmailUser;
    $mail->Password   = $gmailPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 587
    $mail->Port       = 587;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;
    $mail->AltBody = "Hello $fullName, your registration has been received.";

    $mail->send();
    echo "OK";
} catch (Exception $e) {
    error_log("Registration Mail Error: " . $e->getMessage());
    http_response_code(500);
    echo "Mailer Error: " . $e->getMessage();
}
