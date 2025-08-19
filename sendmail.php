<?php
// sendmail.php
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Simple health check (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "Render Mailer: OK";
    exit;
}

// POST only for sending mail
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

// API key check (protect endpoint)
$api_key_header = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expected = getenv('RENDER_API_KEY') ?: '';
if (!$expected || !hash_equals($expected, $api_key_header)) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// Read request body (support JSON or form data)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
} else {
    $data = $_POST;
}

$email = $data['email'] ?? '';
$token = $data['token'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($token)) {
    http_response_code(400);
    echo "Invalid payload";
    exit;
}

// Build verification link (your website)
$your_site = rtrim(getenv('APP_WEB_URL') ?: '', '/');
$verification_link = $your_site . '/verify.php?token=' . urlencode($token);

// PHPMailer + Gmail SMTP
$mail = new PHPMailer(true);
try {
    $gmail_user = getenv('GMAIL_USER');
    $gmail_pass = getenv('GMAIL_PASS'); // the 16-char app password
    $from_email = getenv('FROM_EMAIL') ?: $gmail_user;
    $from_name  = getenv('FROM_NAME') ?: 'No-Reply';

    if (!$gmail_user || !$gmail_pass) {
        throw new Exception('SMTP credentials not configured.');
    }

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmail_user;
    $mail->Password   = $gmail_pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($from_email, $from_name);
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Verify your email';
    $mail->Body    = "<p>Hello,</p><p>Your sign-up was successful. Please click the link below to verify your email address and continue your registration.
:</p><p><a href='{$verification_link}'>Verify Email</a></p>";
    $mail->AltBody = "Open the link to verify: {$verification_link}";

    $mail->send();
    echo "OK";
} catch (Exception $e) {
    error_log("Sendmail Error: " . $e->getMessage());
    http_response_code(500);
    echo "Mailer Error: " . $e->getMessage();
}
