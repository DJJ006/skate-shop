<?php

// notification-service.php
require_once __DIR__ . '/email-config.php';

// Load PHPMailer via Composer autoload
define('PHPMAILER_AVAILABLE', file_exists(__DIR__ . '/vendor/autoload.php'));

if (PHPMAILER_AVAILABLE) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Sends a notification to the user's dashboard and optionally via email.
 */
function sendAppNotification($conn, $user_id, $message_key, $replacements = [], $type = 'general') {
    // Fetch user preferences and language
    $user_stmt = $conn->prepare("SELECT email, email_notifications FROM users WHERE id = ?");
    $user = null;
    if ($user_stmt) {
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();
    }

    // Translate the message (replacements only)
    $translated_msg = $message_key;
    foreach ($replacements as $k => $v) {
        $translated_msg = str_replace(":$k", $v, $translated_msg);
    }

    // 1. Insert into DB (dashboard notification)
    $is_read = 0;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isi", $user_id, $translated_msg, $is_read);
        $stmt->execute();
        $stmt->close();
    }

    // 2. Send Email if applicable
    if ($user && !empty($user['email']) && (($user['email_notifications'] ?? 1) == 1)) {
        $subject = "New Notification from SkateShop";
        $title = "You have a new notification";
        $html_body = buildEmailTemplate($title, $translated_msg);
        sendEmail($user['email'], $subject, $html_body);
    }

}

/**
 * Sends a purchase receipt to the buyer.
 * Transactional email (always sent regardless of email_notifications pref).
 */
function sendOrderReceiptEmail($conn, $buyer_id, $order_ids) {
    if (empty($order_ids)) return;

    $user_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
    if (!$user_stmt) return;
    
    $user_stmt->bind_param("i", $buyer_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$user || empty($user['email'])) return;


    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids));
    
    // Fetch order details
    $order_sql = "
        SELECT o.id, o.amount, o.amount_paid_wallet, o.amount_paid_stripe, o.shipping_name, p.title 
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        WHERE o.id IN ($placeholders)
    ";
    
    $stmt = $conn->prepare($order_sql);
    if (!$stmt) return;
    
    $stmt->bind_param($types, ...$order_ids);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($orders)) return;

    $total_amount = 0;
    $total_wallet = 0;
    $total_stripe = 0;
    $items_html = "<ul>";
    
    foreach ($orders as $o) {
        $total_amount += (float)$o['amount'];
        $total_wallet += (float)$o['amount_paid_wallet'];
        $total_stripe += (float)$o['amount_paid_stripe'];
        $items_html .= "<li><strong>" . htmlspecialchars($o['title']) . "</strong> - $" . number_format($o['amount'], 2) . "</li>";
    }
    $items_html .= "</ul>";

    $receipt_content = "
        <p>Thank you for your purchase, " . htmlspecialchars($user['first_name'] ?: 'Skater') . "!</p>
        <h3>Order Details:</h3>
        $items_html
        <p><strong>Total:</strong> $" . number_format($total_amount, 2) . "</p>
        <p>Paid via Wallet: $" . number_format($total_wallet, 2) . "<br>
        Paid via Card/Stripe: $" . number_format($total_stripe, 2) . "</p>
    ";

    $html_body = buildEmailTemplate("Your Purchase Receipt", $receipt_content);
    sendEmail($user['email'], "Your SkateShop Receipt", $html_body);

}

/**
 * Core function to send an email using PHPMailer.
 */
function sendEmail($to, $subject, $html_body) {
    if (!PHPMAILER_AVAILABLE) {
        error_log("PHPMailer not installed. Email to {$to} not sent.");
        return false;
    }
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
        }
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        if (defined('SMTP_SECURE') && SMTP_SECURE !== '') {
            $mail->SMTPSecure = SMTP_SECURE;
        }
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $html_body));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Builds an HTML email template with Brutalist aesthetics.
 */
function buildEmailTemplate($title, $content) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body {
                font-family: 'Courier New', Courier, monospace;
                background-color: #f6f6f6;
                color: #212121;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
                border: 4px solid #212121;
                box-shadow: 8px 8px 0px #212121;
                padding: 30px;
            }
            .header {
                border-bottom: 4px solid #ff4b4b;
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
            .header h1 {
                margin: 0;
                text-transform: uppercase;
                letter-spacing: 2px;
                color: #212121;
            }
            .content {
                font-size: 16px;
                line-height: 1.6;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 2px dashed #212121;
                font-size: 12px;
                text-align: center;
                color: #555;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$title</h1>
            </div>
            <div class='content'>
                $content
            </div>
            <div class='footer'>
                SkateShop automatically generated this email.
            </div>
        </div>
    </body>
    </html>
    ";
}

?>
