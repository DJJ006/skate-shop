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
        SELECT 
            o.id, 
            o.amount, 
            o.amount_paid_wallet, 
            o.amount_paid_stripe, 
            o.shipping_fee, 
            o.created_at,
            o.stripe_session_id,
            p.title, 
            p.is_marketplace,
            seller.username as seller_username
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN users seller ON p.seller_id = seller.id
        WHERE o.id IN ($placeholders)
    ";
    
    $stmt = $conn->prepare($order_sql);
    if (!$stmt) return;
    
    $stmt->bind_param($types, ...$order_ids);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($orders)) return;

    $total_items_price = 0;
    $total_shipping = 0;
    $total_wallet = 0;
    $total_stripe = 0;
    $stripe_session = null;
    $order_date = null;
    
    $items_html = "<table width='100%' cellpadding='10' cellspacing='0' style='border-collapse: collapse; margin-bottom: 20px; text-align: left; font-family: \"Inter\", sans-serif, Arial;'>
        <thead>
            <tr style='background-color: #212121; color: #fff; font-family: \"Staatliches\", sans-serif, Arial; letter-spacing: 1px;'>
                <th style='padding: 10px; border: 2px solid #212121;'>PRODUCT</th>
                <th style='padding: 10px; border: 2px solid #212121;'>TYPE</th>
                <th style='padding: 10px; border: 2px solid #212121; text-align: right;'>PRICE</th>
            </tr>
        </thead>
        <tbody>";
    
    foreach ($orders as $o) {
        if (!$order_date) $order_date = date("M d, Y H:i", strtotime($o['created_at']));
        if (!$stripe_session && !empty($o['stripe_session_id'])) $stripe_session = $o['stripe_session_id'];
        
        $item_price = (float)$o['amount'];
        $item_shipping = (float)$o['shipping_fee'];
        
        $total_items_price += $item_price;
        $total_shipping += $item_shipping;
        $total_wallet += (float)$o['amount_paid_wallet'];
        $total_stripe += (float)$o['amount_paid_stripe'];
        
        $type_label = $o['is_marketplace'] ? "MARKETPLACE<br><small style='color:#666;'>Seller: @" . htmlspecialchars($o['seller_username']) . "</small>" : "SHOP";
        
        $items_html .= "
            <tr>
                <td style='border: 2px solid #212121; padding: 10px;'><strong>" . htmlspecialchars($o['title']) . "</strong></td>
                <td style='border: 2px solid #212121; padding: 10px; font-size: 0.9em;'>" . $type_label . "</td>
                <td style='border: 2px solid #212121; padding: 10px; text-align: right;'>$" . number_format($item_price, 2) . "</td>
            </tr>
        ";
    }
    $items_html .= "</tbody></table>";

    $final_total = $total_items_price + $total_shipping;

    // Payment Method Breakdown
    $payment_method_html = "<div style='background: #f1f1f1; padding: 15px; border: 3px solid #212121; margin-bottom: 20px;'>
        <h4 style='margin-top: 0; margin-bottom: 10px; font-family: \"Staatliches\", sans-serif, Arial; font-size: 1.2rem; letter-spacing: 1px;'>PAYMENT SUMMARY</h4>
        <ul style='list-style: none; padding: 0; margin: 0; font-family: \"Inter\", sans-serif, Arial; line-height: 1.6;'>";

    if ($total_wallet > 0 && $total_stripe <= 0) {
        $payment_method_html .= "<li><strong>Method:</strong> Wallet Only</li>";
        $payment_method_html .= "<li><strong>Deducted from Wallet:</strong> $" . number_format($total_wallet, 2) . "</li>";
    } elseif ($total_stripe > 0 && $total_wallet <= 0) {
        $payment_method_html .= "<li><strong>Method:</strong> Stripe (Credit/Debit)</li>";
        $payment_method_html .= "<li><strong>Charged via Stripe:</strong> $" . number_format($total_stripe, 2) . "</li>";
    } else {
        $payment_method_html .= "<li><strong>Method:</strong> Mixed (Wallet + Stripe)</li>";
        $payment_method_html .= "<li><strong>Deducted from Wallet:</strong> $" . number_format($total_wallet, 2) . "</li>";
        $payment_method_html .= "<li><strong>Charged via Stripe:</strong> $" . number_format($total_stripe, 2) . "</li>";
    }
    $payment_method_html .= "</ul></div>";

    // Transaction Details
    $tx_details = "";
    if ($stripe_session) {
        $tx_details = "<div style='font-size: 0.85em; color: #555; margin-top: 20px; font-family: \"Inter\", sans-serif, Arial;'>
            <strong>Stripe Session Reference:</strong> " . htmlspecialchars($stripe_session) . "
        </div>";
    }

    $receipt_content = "
        <p style='font-family: \"Inter\", sans-serif, Arial; font-size: 1.1rem; margin-bottom: 25px;'>Thank you for your purchase, <strong>" . htmlspecialchars($user['first_name'] ?: 'Skater') . "</strong>! Here is your official digital receipt.</p>
        
        <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom: 20px; font-family: \"Inter\", sans-serif, Arial; border-bottom: 2px dashed #212121; padding-bottom: 15px;'>
            <tr>
                <td align='left'><strong>ORDER DATE:</strong><br>$order_date</td>
                <td align='right'><strong>ORDER REFERENCE(S):</strong><br>#" . implode(", #", $order_ids) . "</td>
            </tr>
        </table>

        $items_html

        <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom: 25px; font-family: \"Inter\", sans-serif, Arial;'>
            <tr>
                <td align='right'>
                    <table width='250' cellpadding='5' cellspacing='0'>
                        <tr>
                            <td align='right'><strong>Items Subtotal:</strong></td>
                            <td align='right'>$" . number_format($total_items_price, 2) . "</td>
                        </tr>
                        <tr>
                            <td align='right'><strong>Shipping Fee:</strong></td>
                            <td align='right'>$" . number_format($total_shipping, 2) . "</td>
                        </tr>
                        <tr><td colspan='2'><hr style='border: 1px solid #212121;'></td></tr>
                        <tr>
                            <td align='right' style='font-size: 1.2rem; font-family: \"Staatliches\", sans-serif, Arial; letter-spacing: 1px; color: #ff4b4b;'><strong>TOTAL PAID:</strong></td>
                            <td align='right' style='font-size: 1.2rem; font-weight: bold; color: #ff4b4b;'>$" . number_format($final_total, 2) . "</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        $payment_method_html

        $tx_details
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

/**
 * Checks if a seller meets the criteria for automatic verification.
 * Criteria: >= 5 total sales, >= 3 total reviews, >= 4.0 average rating.
 */
function checkAutoVerification($conn, $seller_id) {
    if (!$conn || $seller_id <= 0) return;

    // First check if already verified
    $stmt = $conn->prepare("SELECT total_sales, average_seller_rating, total_seller_reviews, verified_status, is_verified FROM users WHERE id = ?");
    if (!$stmt) return;
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $seller = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($seller && $seller['verified_status'] === 'None' && $seller['is_verified'] == 0) {
        if ($seller['total_sales'] >= 5 && $seller['total_seller_reviews'] >= 3 && $seller['average_seller_rating'] >= 4.0) {
            $upd = $conn->prepare("UPDATE users SET is_verified = 1, verified_status = 'Verified', verification_type = 'Auto', verification_date = NOW() WHERE id = ?");
            $upd->bind_param("i", $seller_id);
            if ($upd->execute()) {
                sendAppNotification($conn, $seller_id, "Congratulations! You have reached the required milestones (5+ sales, 4.0+ rating) and your seller account has been automatically verified.");
            }
        }
    }
}

?>
