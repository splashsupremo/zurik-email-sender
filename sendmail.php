<?php
// sendmail.php
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Simple health check for GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "Render Mailer: OK";
    exit;
}

// Only POST allowed for sending
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

// Verify API key header
$api_key_header = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expected = getenv('RENDER_API_KEY') ?: '';
if (!$expected || !hash_equals($expected, $api_key_header)) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo "Invalid JSON";
    exit;
}

$email = $data['email'] ?? '';
$token = $data['token'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($token)) {
    http_response_code(400);
    echo "Invalid payload";
    exit;
}

// Build verification link (point to your site)
$your_site = getenv('APP_WEB_URL') ?: 'https://app.zuriktrucka.com';
$verification_link = rtrim($your_site, '/') . '/verify.php?token=' . urlencode($token);

// PHPMailer send via Gmail SMTP
$mail = new PHPMailer(true);
try {
    $gmail_user = getenv('GMAIL_USER');
    $gmail_pass = getenv('GMAIL_PASS'); // App password
    $from_email = getenv('FROM_EMAIL') ?: $gmail_user;
    $from_name  = getenv('FROM_NAME') ?: 'Zurik Truck Academy';

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
    $mail->Body    = "<p>Hello,</p><p>Click the link to verify your email:</p><p><a href='{$verification_link}'>Verify Email</a></p>";
    $mail->AltBody = "Open the link to verify: {$verification_link}";

    $mail->send();
    echo "OK";
} catch (Exception $e) {
    error_log("Sendmail Error: " . $e->getMessage());
    http_response_code(500);
    echo "Mailer Error: " . $e->getMessage();
}
