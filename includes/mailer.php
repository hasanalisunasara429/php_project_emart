<?php
// includes/mailer.php
// Requires: composer require phpmailer/phpmailer
// Or download PHPMailer and place in /includes/PHPMailer/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust path if you installed via composer or manual download
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

/**
 * sendMail() — send an HTML email via Gmail SMTP
 *
 * @param string $toEmail   recipient email
 * @param string $toName    recipient name
 * @param string $subject   email subject
 * @param string $body      HTML body
 * @return bool
 */
function sendMail($toEmail, $toName, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Sender / recipient
        $mail->setFrom(SMTP_USER, SITE_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(ADMIN_EMAIL, SITE_NAME . ' Support');

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * orderConfirmationEmail() — send order placed email
 */
function orderConfirmationEmail($toEmail, $toName, $orderId, $total) {
    $subject = "Order #$orderId Confirmed — " . SITE_NAME;
    $body = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #ddd;border-radius:8px;overflow:hidden'>
      <div style='background:#f90;padding:20px;text-align:center'>
        <h1 style='color:#fff;margin:0'>🛒 " . SITE_NAME . "</h1>
      </div>
      <div style='padding:30px'>
        <h2>Hi $toName, your order is confirmed! 🎉</h2>
        <p>Order <strong>#$orderId</strong> has been placed successfully.</p>
        <p>Total Amount: <strong>₹" . number_format($total, 2) . "</strong></p>
        <p>We'll notify you once your order is shipped.</p>
        <a href='" . BASE_URL . "user/orders.php'
           style='display:inline-block;background:#f90;color:#fff;padding:12px 24px;
                  text-decoration:none;border-radius:5px;margin-top:15px'>
           View Order
        </a>
      </div>
      <div style='background:#f5f5f5;padding:15px;text-align:center;color:#888;font-size:12px'>
        © " . date('Y') . " " . SITE_NAME . " | <a href='" . BASE_URL . "'>Visit Store</a>
      </div>
    </div>";
    return sendMail($toEmail, $toName, $subject, $body);
}
