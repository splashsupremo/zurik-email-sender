<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $token = $_POST['token'] ?? '';

    if (!$email || !$token) {
        http_response_code(400);
        echo "Missing email or token";
        exit;
    }

    $verification_link = getenv('APP_WEB_URL') . "/verify.php?token=" . urlencode($token);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('GMAIL_USER');
        $mail->Password   = getenv('GMAIL_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(getenv('FROM_EMAIL') ?: getenv('GMAIL_USER'), getenv('FROM_NAME') ?: 'No-Reply');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Verify Your Email";
        $mail->Body    = "Click <a href='{$verification_link}'>here</a> to verify your email.";

        $mail->send();
        echo "OK";
    } catch (Exception $e) {
        http_response_code(500);
        echo "Mailer Error: " . $mail->ErrorInfo;
    }
} else {
    http_response_code(405);
    echo "Method Not Allowed";
}
