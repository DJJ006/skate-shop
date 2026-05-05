<?php
require_once '../stripe-config.php';
include '../db.php';

$log_file = __DIR__ . '/stripe-webhook-log.txt';

function decryptShippingAddress($base64_payload) {
    if (empty($base64_payload)) return null;
    $decoded = base64_decode($base64_payload);
    if (!$decoded || strpos($decoded, '::') === false) return null;
    
    list($encrypted_data, $iv) = explode('::', $decoded, 2);
    $encryption_key = "YOUR_SUPER_SECRET_KEY_MAKE_IT_LONG";
    $cipher = 'aes-256-cbc';
    
    $decrypted = openssl_decrypt(
        $encrypted_data,
        $cipher,
        $encryption_key,
        0,
        $iv
    );
    
    if ($decrypted) {
        return json_decode($decrypted, true);
    }
    return null;
}

function webhook_log($message) {
    global $log_file;
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

webhook_log("Webhook hit");
webhook_log("Signature header: " . ($sig_header ?: 'NONE'));
webhook_log("Payload: " . $payload);

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        STRIPE_WEBHOOK_SECRET
    );
    webhook_log("Event verified: " . $event->type);
} catch (\UnexpectedValueException $e) {
    webhook_log("Invalid payload: " . $e->getMessage());
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    webhook_log("Invalid signature: " . $e->getMessage());
    http_response_code(400);
    exit('Invalid signature');
} catch (Exception $e) {
    webhook_log("Webhook error: " . $e->getMessage());
    http_response_code(400);
    exit('Webhook error');
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;

    $order_id = isset($session->metadata->order_id) ? (int)$session->metadata->order_id : 0;
    webhook_log("Order ID from metadata: " . $order_id);

    if ($order_id <= 0) {
        webhook_log("Missing or invalid order_id");
        http_response_code(400);
        exit('Missing order_id');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn->begin_transaction();
        webhook_log("Transaction started");

        $order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
        $order_stmt->bind_param("i", $order_id);
        $order_stmt->execute();
        $order = $order_stmt->get_result()->fetch_assoc();

        if (!$order) {
            throw new Exception('Order not found');
        }

        webhook_log("Order found with status: " . $order['status']);

        if ($order['status'] === 'PAID') {
            $conn->commit();
            webhook_log("Order already processed");
            http_response_code(200);
            exit('Already processed');
        }

        $prod_id = (int)$order['product_id'];
        $seller_id = (int)$order['seller_id'];
        $buyer_id = (int)$order['buyer_id'];
        $amount = (float)$order['amount'];

        $prod_stmt = $conn->prepare("SELECT id, is_marketplace, is_sold, quantity FROM products WHERE id = ? FOR UPDATE");
        $prod_stmt->bind_param("i", $prod_id);
        $prod_stmt->execute();
        $product = $prod_stmt->get_result()->fetch_assoc();

        if (!$product) {
            throw new Exception('Product not found');
        }

        webhook_log("Product found: ID " . $prod_id);

        if ($seller_id > 0) {
            if ((int)$product['is_sold'] === 1) {
                throw new Exception('Marketplace item already sold');
            }

            $update_product_stmt = $conn->prepare("UPDATE products SET is_sold = 1 WHERE id = ?");
            $update_product_stmt->bind_param("i", $prod_id);
            $update_product_stmt->execute();
            webhook_log("Marketplace product marked sold");
        } else {
            if ((int)$product['quantity'] <= 0) {
                throw new Exception('Product out of stock');
            }

            $update_product_stmt = $conn->prepare("UPDATE products SET quantity = GREATEST(0, quantity - 1) WHERE id = ?");
            $update_product_stmt->bind_param("i", $prod_id);
            $update_product_stmt->execute();
            webhook_log("Official product quantity reduced");
        }

        $update_order_stmt = $conn->prepare("UPDATE orders SET status = 'PAID' WHERE id = ? AND status = 'PENDING_PAYMENT'");
        $update_order_stmt->bind_param("i", $order_id);
        $update_order_stmt->execute();

        if ($update_order_stmt->affected_rows === 0) {
            throw new Exception('Order status update failed or already processed');
        }

        webhook_log("Order marked PAID");

        if ($seller_id > 0) {
            $seller_payout = round($amount * 0.95, 2);
            $purchase_code = "ORD-" . str_pad($order_id, 6, "0", STR_PAD_LEFT);
            $shipping_info = "N/A";
            $del_notes = "None";
            
            $shipping_payload = decryptShippingAddress($order['shipping_address']);
            if ($shipping_payload) {
                $ship_addr = "{$shipping_payload['full_name']}, {$shipping_payload['address_line_1']}";
                if (!empty($shipping_payload['address_line_2'])) $ship_addr .= ", {$shipping_payload['address_line_2']}";
                $ship_addr .= ", {$shipping_payload['city']}, {$shipping_payload['state_region']} {$shipping_payload['postal_code']}, {$shipping_payload['country']}";
                $shipping_info = $ship_addr;
                $del_notes = !empty($shipping_payload['delivery_notes']) ? $shipping_payload['delivery_notes'] : "None";
            }
            
            $seller_msg = "Cha-ching! Your gear '{$product['title']}' sold for $" . number_format($amount, 2) . "!\nOrder Code: {$purchase_code}\nShip to: {$shipping_info}\nDelivery Notes: {$del_notes}\nThe payout of $" . number_format($seller_payout, 2) . " is currently held in escrow and will be released to your wallet once the buyer confirms delivery, or automatically after 10 days.";
            
            $seller_notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $seller_notif_stmt->bind_param("is", $seller_id, $seller_msg);
            $seller_notif_stmt->execute();

            webhook_log("Seller notification updated for pending escrow payout with shipping address");
        }

        $purchase_code = "ORD-" . str_pad($order_id, 6, "0", STR_PAD_LEFT);
        $buyer_msg = "Order confirmed! Your payment was received and your gear is being prepared for shipping. Purchase Code: " . $purchase_code;
        $buyer_notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $buyer_notif_stmt->bind_param("is", $buyer_id, $buyer_msg);
        $buyer_notif_stmt->execute();

        webhook_log("Buyer notification inserted");

        $conn->commit();
        webhook_log("Transaction committed successfully");
    } catch (Exception $e) {
        $conn->rollback();
        webhook_log("Processing failed: " . $e->getMessage());
        http_response_code(500);
        exit('Webhook processing failed: ' . $e->getMessage());
    }
}

http_response_code(200);
echo 'OK';