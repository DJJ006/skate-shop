<?php
session_start();
include '../db.php';
require_once '../stripe-config.php';

// --- Security: Only allow POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// --- Security: Admin session check ---
// Adjust this check to match your actual admin session variable
// if (!isset($_SESSION['admin_id']) && !isset($_SESSION['is_admin'])) {
//     $_SESSION['admin_msg'] = 'UNAUTHORIZED: Admin access required.';
//     $_SESSION['admin_msg_type'] = 'error';
//     header('Location: client-orders.php');
//     exit();
// }

// --- Sanitize & Validate Inputs ---
$order_id          = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$cancel_reason     = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';

if ($order_id <= 0) {
    $_SESSION['admin_msg'] = 'INVALID ORDER ID.';
    $_SESSION['admin_msg_type'] = 'error';
    header('Location: client-orders.php');
    exit();
}

if (empty($cancel_reason)) {
    $_SESSION['admin_msg'] = 'CANCELLATION REASON IS REQUIRED.';
    $_SESSION['admin_msg_type'] = 'error';
    header('Location: client-orders.php');
    exit();
}

if (strlen($cancel_reason) > 1000) {
    $_SESSION['admin_msg'] = 'CANCELLATION REASON IS TOO LONG (MAX 1000 CHARACTERS).';
    $_SESSION['admin_msg_type'] = 'error';
    header('Location: client-orders.php');
    exit();
}

// --- Fetch the order and lock it ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn->begin_transaction();

    $order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order = $order_stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('ORDER NOT FOUND.');
    }

    // --- Prevent duplicate cancellations ---
    if ($order['status'] === 'CANCELLED') {
        throw new Exception('THIS ORDER HAS ALREADY BEEN CANCELLED.');
    }

    // --- Only PAID orders can be cancelled ---
    if ($order['status'] !== 'PAID') {
        throw new Exception('ONLY PAID ORDERS CAN BE CANCELLED. CURRENT STATUS: ' . $order['status']);
    }

    $buyer_id         = (int)$order['buyer_id'];
    $stripe_session_id = $order['stripe_session_id'] ?? '';
    $purchase_code    = 'ORD-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);

    $amount_paid_stripe = isset($order['amount_paid_stripe']) ? (float)$order['amount_paid_stripe'] : $order['amount']; // Fallback for old orders
    $amount_paid_wallet = isset($order['amount_paid_wallet']) ? (float)$order['amount_paid_wallet'] : 0;

    // --- Stripe Refund ---
    if ($amount_paid_stripe > 0) {
        if (empty($stripe_session_id)) {
            throw new Exception('NO STRIPE SESSION ID FOUND FOR THIS ORDER. CANNOT PROCESS REFUND.');
        }

        // Retrieve the Stripe Checkout Session to get the Payment Intent
        $stripe_session = \Stripe\Checkout\Session::retrieve($stripe_session_id);
        $payment_intent_id = $stripe_session->payment_intent ?? null;

        if (empty($payment_intent_id)) {
            throw new Exception('COULD NOT RETRIEVE PAYMENT INTENT FROM STRIPE SESSION.');
        }

        // Issue a full refund
        \Stripe\Refund::create([
            'payment_intent' => $payment_intent_id,
        ]);
    }

    // --- Wallet Refund ---
    if ($amount_paid_wallet > 0) {
        $refund_wallet_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $refund_wallet_stmt->bind_param("di", $amount_paid_wallet, $buyer_id);
        $refund_wallet_stmt->execute();
    }

    // --- Update Order Status ---
    $update_stmt = $conn->prepare("UPDATE orders SET status = 'CANCELLED', payout_status = 'REFUNDED' WHERE id = ? AND status = 'PAID'");
    $update_stmt->bind_param("i", $order_id);
    $update_stmt->execute();

    if ($update_stmt->affected_rows === 0) {
        throw new Exception('ORDER STATUS UPDATE FAILED OR ORDER WAS ALREADY PROCESSED.');
    }

    // --- Update Product Inventory ---
    $product_id = (int)$order['product_id'];
    $seller_id = (int)$order['seller_id'];

    if ($seller_id > 0) {
        // Marketplace product - revert is_sold to 0
        $restore_prod = $conn->prepare("UPDATE products SET is_sold = 0 WHERE id = ?");
        $restore_prod->bind_param("i", $product_id);
        $restore_prod->execute();
    } else {
        // Official product - increment quantity by 1 (assuming 1 item per order)
        $restore_prod = $conn->prepare("UPDATE products SET quantity = quantity + 1 WHERE id = ?");
        $restore_prod->bind_param("i", $product_id);
        $restore_prod->execute();
    }

    // --- Create Client Notification ---
    $notif_message = "ORDER CANCELLED — Your order {$purchase_code} has been cancelled by the admin.\n\n"
                   . "REASON: {$cancel_reason}\n\n"
                   . "A full refund has been issued to your original payment method. "
                   . "Please allow 5–10 business days for the refund to appear on your statement.";

    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notif_stmt->bind_param("is", $buyer_id, $notif_message);
    $notif_stmt->execute();

    if ($seller_id > 0) {
        $seller_notif_message = "ORDER CANCELLED — The order {$purchase_code} for your marketplace item has been cancelled by the admin.\n\n"
                              . "REASON: {$cancel_reason}\n\n"
                              . "Your item has been placed back into the marketplace and is available for purchase again.";
        $seller_notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $seller_notif_stmt->bind_param("is", $seller_id, $seller_notif_message);
        $seller_notif_stmt->execute();
    }

    $conn->commit();

    $_SESSION['admin_msg'] = "ORDER {$purchase_code} HAS BEEN CANCELLED AND THE CLIENT HAS BEEN NOTIFIED. REFUND ISSUED VIA STRIPE.";
    $_SESSION['admin_msg_type'] = 'success';
    header('Location: client-orders.php');
    exit();

} catch (\Stripe\Exception\InvalidRequestException $e) {
    $conn->rollback();
    // Handle already-refunded charge gracefully
    $err = $e->getMessage();
    if (strpos($err, 'already been refunded') !== false || strpos($err, 'charge_already_refunded') !== false) {
        // Stripe already refunded — still update the DB and notify
        try {
            $update_stmt2 = $conn->prepare("UPDATE orders SET status = 'CANCELLED', payout_status = 'REFUNDED' WHERE id = ? AND status = 'PAID'");
            $update_stmt2->bind_param("i", $order_id);
            $update_stmt2->execute();

            if ($update_stmt2->affected_rows > 0) {
                $product_id = (int)$order['product_id'];
                $seller_id = (int)$order['seller_id'];
                
                if ($seller_id > 0) {
                    $restore_prod = $conn->prepare("UPDATE products SET is_sold = 0 WHERE id = ?");
                    $restore_prod->bind_param("i", $product_id);
                    $restore_prod->execute();
                } else {
                    $restore_prod = $conn->prepare("UPDATE products SET quantity = quantity + 1 WHERE id = ?");
                    $restore_prod->bind_param("i", $product_id);
                    $restore_prod->execute();
                }
            }

            if (isset($buyer_id) && isset($purchase_code)) {
                $notif_msg2 = "Order cancelled. Your order {$purchase_code} has been cancelled by the admin.\n\n"
                            . "REASON: {$cancel_reason}\n\n"
                            . "This order had already been refunded previously.";
                $notif_stmt2 = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif_stmt2->bind_param("is", $buyer_id, $notif_msg2);
                $notif_stmt2->execute();

                if (isset($seller_id) && $seller_id > 0) {
                    $seller_notif_msg2 = "Order cancelled. The order {$purchase_code} for your marketplace item has been cancelled by the admin.\n\n"
                                       . "REASON: {$cancel_reason}\n\n"
                                       . "Your item has been placed back into the marketplace.";
                    $seller_notif_stmt2 = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $seller_notif_stmt2->bind_param("is", $seller_id, $seller_notif_msg2);
                    $seller_notif_stmt2->execute();
                }
            }
        } catch (Exception $inner) {
            // Best effort
        }
        $_SESSION['admin_msg'] = "ORDER CANCELLED. NOTE: STRIPE REPORTED THIS CHARGE WAS ALREADY REFUNDED.";
        $_SESSION['admin_msg_type'] = 'success';
    } else {
        $_SESSION['admin_msg'] = 'STRIPE ERROR: ' . htmlspecialchars($err);
        $_SESSION['admin_msg_type'] = 'error';
    }
    header('Location: client-orders.php');
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['admin_msg'] = 'ERROR: ' . htmlspecialchars($e->getMessage());
    $_SESSION['admin_msg_type'] = 'error';
    header('Location: client-orders.php');
    exit();
}
