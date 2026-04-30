<?php

require_once '../db.php'; // adjust path if moved

try {
    echo "Starting automated escrow release script...\n";
    
    // Find all orders that meet the criteria
    $stmt = $conn->prepare("
        SELECT id, seller_id, buyer_id, amount 
        FROM orders 
        WHERE status = 'PAID' 
          AND payout_status = 'PENDING' 
          AND seller_id > 0 
          AND created_at <= DATE_SUB(NOW(), INTERVAL 10 DAY)
    ");
    
    if (!$stmt->execute()) {
        throw new Exception("Query failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $processed_count = 0;
    
    while ($order = $result->fetch_assoc()) {
        $order_id = (int)$order['id'];
        $seller_id = (int)$order['seller_id'];
        $buyer_id = (int)$order['buyer_id'];
        $amount = (float)$order['amount'];
        $seller_payout = round($amount * 0.95, 2);
        
        $conn->begin_transaction();
        
        try {
            // 1. Update order status
            $update_order_stmt = $conn->prepare("
                UPDATE orders 
                SET status = 'RECEIVED', 
                    payout_status = 'RELEASED', 
                    payout_date = NOW() 
                WHERE id = ? AND payout_status = 'PENDING'
            ");
            $update_order_stmt->bind_param("i", $order_id);
            $update_order_stmt->execute();
            
            if ($update_order_stmt->affected_rows === 0) {
                // Someone else processed it or it changed status
                $conn->rollback();
                continue;
            }
            
            // 2. Add funds to seller wallet
            $wallet_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $wallet_stmt->bind_param("di", $seller_payout, $seller_id);
            $wallet_stmt->execute();
            
            // 3. Notify seller
            $seller_msg = "Your funds have been automatically released! $" . number_format($seller_payout, 2) . " has been added to your Wallet because 10 days have passed since the order.";
            $seller_notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $seller_notif_stmt->bind_param("is", $seller_id, $seller_msg);
            $seller_notif_stmt->execute();
            
            // 4. Notify buyer
            $buyer_msg = "Your order (ID: ORD-" . str_pad($order_id, 6, "0", STR_PAD_LEFT) . ") has been automatically marked as received because 10 days have passed.";
            $buyer_notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $buyer_notif_stmt->bind_param("is", $buyer_id, $buyer_msg);
            $buyer_notif_stmt->execute();
            
            $conn->commit();
            $processed_count++;
            echo "Successfully released payout for Order #$order_id.\n";
            
        } catch (Exception $inner) {
            $conn->rollback();
            echo "Failed to release payout for Order #$order_id: " . $inner->getMessage() . "\n";
        }
    }
    
    echo "Finished. Total orders processed: $processed_count\n";
    
} catch (Exception $e) {
    echo "Script encountered a fatal error: " . $e->getMessage() . "\n";
}

?>
