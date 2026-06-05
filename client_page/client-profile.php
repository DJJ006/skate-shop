<?php
session_start();
include '../db.php'; 

// --- MIGRATION: Ensure the table exists
$conn->query("
CREATE TABLE IF NOT EXISTS email_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    new_email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id),
    UNIQUE KEY (token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// --- MIGRATION: Ensure hidden columns exist for cleanup feature ---
$check_p = $conn->query("SHOW COLUMNS FROM products LIKE 'hidden_from_seller'");
if ($check_p->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN hidden_from_seller TINYINT(1) DEFAULT 0");
}
$check_o = $conn->query("SHOW COLUMNS FROM orders LIKE 'hidden_from_buyer'");
if ($check_o->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN hidden_from_buyer TINYINT(1) DEFAULT 0");
}
$check_o2 = $conn->query("SHOW COLUMNS FROM orders LIKE 'hidden_from_seller'");
if ($check_o2->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN hidden_from_seller TINYINT(1) DEFAULT 0");
}

// --- MIGRATION: Seller Verification and Marketplace Reputation ---
$check_users = $conn->query("SHOW COLUMNS FROM users LIKE 'verified_status'");
if ($check_users->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN verification_type VARCHAR(20) DEFAULT NULL");
    $conn->query("ALTER TABLE users ADD COLUMN verification_date DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE users ADD COLUMN verification_admin_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE users ADD COLUMN total_sales INT DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN total_seller_reviews INT DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN average_seller_rating DECIMAL(3,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE users ADD COLUMN verified_status VARCHAR(20) DEFAULT 'None'");
    
    // Migrate is_verified to verified_status if it exists
    $check_is_verified = $conn->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
    if ($check_is_verified->num_rows > 0) {
        $conn->query("UPDATE users SET verified_status = 'Verified' WHERE is_verified = 1");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS seller_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    buyer_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    transaction_id INT DEFAULT NULL,
    rating INT NOT NULL,
    comment VARCHAR(100) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'Pending Approval',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$check_seller_ratings = $conn->query("SHOW COLUMNS FROM seller_ratings LIKE 'status'");
if ($check_seller_ratings->num_rows == 0) {
    $conn->query("ALTER TABLE seller_ratings ADD COLUMN comment VARCHAR(100) DEFAULT NULL");
    $conn->query("ALTER TABLE seller_ratings ADD COLUMN status VARCHAR(20) DEFAULT 'Pending Approval'");
    $conn->query("ALTER TABLE seller_ratings ADD COLUMN transaction_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE seller_ratings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- AJAX: FETCH SINGLE PRODUCT FOR EDIT ---
if (isset($_GET['get_listing_details'])) {
    $p_id = (int)$_GET['get_listing_details'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ? AND is_approved = 1");
    $stmt->bind_param("ii", $p_id, $user_id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc());
    exit();
}

// --- AJAX: FETCH SINGLE QNA DETAILS ---
if (isset($_GET['get_qna_details'])) {
    $q_id = (int)$_GET['get_qna_details'];
    $stmt = $conn->prepare("SELECT * FROM community_qna WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $q_id, $user_id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc());
    exit();
}

// --- AJAX: FETCH SINGLE SHOUTOUT DETAILS ---
if (isset($_GET['get_shoutout_details'])) {
    $s_id = (int)$_GET['get_shoutout_details'];
    $stmt = $conn->prepare("SELECT * FROM community_shoutouts WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $s_id, $user_id);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_assoc());
    } else {
        echo json_encode(null);
    }
    exit();
}

// --- AJAX: MARK NOTIF AS READ ---
if (isset($_GET['ajax_read_notif'])) {
    $notif_id = (int)$_GET['ajax_read_notif'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    echo $stmt->execute() ? "success" : "error";
    exit();
}

// Clear all notifications
if (isset($_GET['clear_notifs'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    header("Location: client-profile.php");
    exit();
}

// Delete single notification
if (isset($_GET['delete_notif'])) {
    $notif_id = (int)$_GET['delete_notif'];
    $del_notif_stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $del_notif_stmt->bind_param("ii", $notif_id, $user_id);
    $del_notif_stmt->execute();
    header("Location: client-profile.php");
    exit();
}

$msg = '';
$msg_type = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['msg']);
    unset($_SESSION['msg_type']);
}

// Fetch basic user data including wallet_balance
$stmt = $conn->prepare("SELECT username, email, profile_pic, wallet_balance, email_notifications FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Unread count
$notif_count_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_count_stmt->bind_param("i", $user_id);
$notif_count_stmt->execute();
$unread_count = $notif_count_stmt->get_result()->fetch_assoc()['unread'];

// Fetch Notifications
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

// Fetch Listings
$list_stmt = $conn->prepare("
    SELECT p.id, p.title, p.price, p.image_url, p.is_approved,
           (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id AND o.status IN ('PAID', 'RECEIVED')) as sold_count
    FROM products p 
    WHERE p.seller_id = ? AND p.is_marketplace = 1 AND p.hidden_from_seller = 0
    ORDER BY p.id DESC
");
$list_stmt->bind_param("i", $user_id);
$list_stmt->execute();
$user_listings = $list_stmt->get_result();

// Fetch Buy History
$buy_history_stmt = $conn->prepare("SELECT o.id as order_id, o.amount, o.amount_paid_wallet, o.amount_paid_stripe, o.shipping_fee, o.order_group_id, o.created_at, o.status, p.title, p.image_url, p.is_marketplace, p.seller_id, p.seller_name, (SELECT id FROM seller_ratings sr WHERE sr.transaction_id = o.id AND sr.status != 'Rejected' LIMIT 1) as has_seller_review FROM orders o JOIN products p ON o.product_id = p.id WHERE o.buyer_id = ? AND o.status IN ('PAID', 'CANCELLED', 'RECEIVED') AND o.hidden_from_buyer = 0 ORDER BY o.created_at DESC");
$buy_history_stmt->bind_param("i", $user_id);
$buy_history_stmt->execute();
$buy_history_result = $buy_history_stmt->get_result();

$grouped_buy_history = [];
if ($buy_history_result) {
    while ($row = $buy_history_result->fetch_assoc()) {
        $group_id = !empty($row['order_group_id']) ? $row['order_group_id'] : 'INDIVIDUAL-' . $row['order_id'];
        if (!isset($grouped_buy_history[$group_id])) {
            $grouped_buy_history[$group_id] = [
                'group_id' => $group_id,
                'created_at' => $row['created_at'],
                'items' => [],
                'total_amount' => 0,
                'total_shipping' => 0,
                'total_wallet' => 0,
                'total_stripe' => 0
            ];
        }
        $grouped_buy_history[$group_id]['items'][] = $row;
        $grouped_buy_history[$group_id]['total_amount'] += (float)$row['amount'];
        $grouped_buy_history[$group_id]['total_shipping'] += (float)$row['shipping_fee'];
        $grouped_buy_history[$group_id]['total_wallet'] += (float)$row['amount_paid_wallet'];
        $grouped_buy_history[$group_id]['total_stripe'] += (float)$row['amount_paid_stripe'];
    }
}

// Fetch Sales History
$sales_history_stmt = $conn->prepare("SELECT o.id as order_id, o.amount, o.amount_paid_wallet, o.amount_paid_stripe, o.created_at, o.status, p.title, p.image_url, u.username as buyer_name, u.email as buyer_email FROM orders o JOIN products p ON o.product_id = p.id LEFT JOIN users u ON o.buyer_id = u.id WHERE o.seller_id = ? AND o.status IN ('PAID', 'RECEIVED') AND o.hidden_from_seller = 0 ORDER BY o.created_at DESC");
$sales_history_stmt->bind_param("i", $user_id);
$sales_history_stmt->execute();
$sales_history_result = $sales_history_stmt->get_result();

// Fetch My Reels
$my_reels_stmt = $conn->prepare("
    SELECT r.id, r.title, r.description, r.embed_url, r.platform, r.is_approved, r.created_at,
           (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.id) AS like_count,
           (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.id) AS comment_count,
           (SELECT COUNT(*) FROM reel_edit_requests WHERE reel_id = r.id AND status = 'pending') AS has_pending_edit
    FROM reels r
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$my_reels_stmt->bind_param("i", $user_id);
$my_reels_stmt->execute();
$my_reels_result = $my_reels_stmt->get_result();

// Fetch My QNAs
$my_qna_stmt = $conn->prepare("SELECT * FROM community_qna WHERE user_id = ? AND status != 'rejected' ORDER BY created_at DESC");
$my_qna_stmt->bind_param("i", $user_id);
$my_qna_stmt->execute();
$my_qna_result = $my_qna_stmt->get_result();

// Fetch Support Tickets
$my_tickets = null;
$t_check = @$conn->query("SHOW TABLES LIKE 'support_tickets'");
if ($t_check && $t_check->num_rows > 0) {
    $tickets_stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY updated_at DESC");
    $tickets_stmt->bind_param("i", $user_id);
    $tickets_stmt->execute();
    $my_tickets = $tickets_stmt->get_result();
}

// Fetch Followers
$followers_stmt = $conn->prepare("
    SELECT u.id, u.username, u.profile_pic 
    FROM user_follows f 
    JOIN users u ON f.follower_id = u.id 
    WHERE f.followed_id = ?
    ORDER BY f.created_at DESC
");
$followers_stmt->bind_param("i", $user_id);
$followers_stmt->execute();
$followers_list = $followers_stmt->get_result();

// Fetch My Shoutouts (empty if table missing)
$my_shoutouts_rows = [];
if (@$conn->query("SELECT 1 FROM community_shoutouts LIMIT 1") !== false) {
    $my_shoutouts_stmt = $conn->prepare("SELECT * FROM community_shoutouts WHERE user_id = ? ORDER BY created_at DESC");
    if ($my_shoutouts_stmt) {
        $my_shoutouts_stmt->bind_param("i", $user_id);
        $my_shoutouts_stmt->execute();
        $my_shoutouts_res = $my_shoutouts_stmt->get_result();
        if ($my_shoutouts_res) {
            while ($sr = $my_shoutouts_res->fetch_assoc()) {
                $my_shoutouts_rows[] = $sr;
            }
        }
    }
}

// Fetch My Reviews
$my_reviews_stmt = $conn->prepare("
    SELECT pr.*, p.title as product_title 
    FROM product_reviews pr 
    JOIN products p ON pr.product_id = p.id 
    WHERE pr.user_id = ? 
    ORDER BY pr.created_at DESC
");
$my_reviews_stmt->bind_param("i", $user_id);
$my_reviews_stmt->execute();
$my_reviews_result = $my_reviews_stmt->get_result();

// Fetch My Seller Reviews
$my_seller_reviews_stmt = $conn->prepare("
    SELECT sr.*, u.username as seller_username
    FROM seller_ratings sr 
    JOIN users u ON sr.seller_id = u.id 
    WHERE sr.buyer_id = ? 
    ORDER BY sr.created_at DESC
");
$my_seller_reviews_stmt->bind_param("i", $user_id);
$my_seller_reviews_stmt->execute();
$my_seller_reviews_result = $my_seller_reviews_stmt->get_result();

// --- POST: EDIT MY REVIEW ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_my_review'])) {
    $r_id = (int)$_POST['review_id'];
    $rating = (int)$_POST['rating'];
    $comment = substr(trim($_POST['comment']), 0, 100);
    
    if ($rating >= 1 && $rating <= 5) {
        $check_pr = $conn->query("SHOW COLUMNS FROM product_reviews LIKE 'previous_rating'");
        if ($check_pr->num_rows == 0) {
            $conn->query("ALTER TABLE product_reviews ADD COLUMN previous_rating INT DEFAULT NULL");
            $conn->query("ALTER TABLE product_reviews ADD COLUMN previous_comment VARCHAR(100) DEFAULT NULL");
        }

        $curr = $conn->prepare("SELECT rating, comment, status FROM product_reviews WHERE id = ? AND user_id = ?");
        $curr->bind_param("ii", $r_id, $user_id);
        $curr->execute();
        $curr_res = $curr->get_result()->fetch_assoc();
        
        if ($curr_res) {
            if ($curr_res['status'] == 'Approved') {
                $stmt = $conn->prepare("UPDATE product_reviews SET previous_rating = rating, previous_comment = comment, rating = ?, comment = ?, status = 'Pending Approval' WHERE id = ? AND user_id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE product_reviews SET rating = ?, comment = ?, status = 'Pending Approval' WHERE id = ? AND user_id = ?");
            }
            $stmt->bind_param("isii", $rating, $comment, $r_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['msg'] = "REVIEW UPDATED AND PENDING APPROVAL.";
                $_SESSION['msg_type'] = "success";
            }
        }
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: DELETE MY REVIEW ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_my_review'])) {
    $r_id = (int)$_POST['review_id'];
    
    $p_stmt = $conn->prepare("SELECT product_id FROM product_reviews WHERE id = ? AND user_id = ?");
    $p_stmt->bind_param("ii", $r_id, $user_id);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result()->fetch_assoc();
    
    if ($p_res) {
        $p_id = $p_res['product_id'];
        $del_stmt = $conn->prepare("DELETE FROM product_reviews WHERE id = ? AND user_id = ?");
        $del_stmt->bind_param("ii", $r_id, $user_id);
        if ($del_stmt->execute()) {
            $conn->query("UPDATE products p SET p.average_rating = (SELECT IFNULL(AVG(rating), 0) FROM product_reviews WHERE product_id = $p_id AND status = 'Approved'), p.review_count = (SELECT COUNT(*) FROM product_reviews WHERE product_id = $p_id AND status = 'Approved') WHERE p.id = $p_id");
            $_SESSION['msg'] = "REVIEW DELETED.";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: EDIT MY SELLER REVIEW ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_my_seller_review'])) {
    $r_id = (int)$_POST['review_id'];
    $rating = (int)$_POST['rating'];
    $comment = substr(trim($_POST['comment']), 0, 100);
    
    if ($rating >= 1 && $rating <= 5) {
        $check_sr = $conn->query("SHOW COLUMNS FROM seller_ratings LIKE 'previous_rating'");
        if ($check_sr->num_rows == 0) {
            $conn->query("ALTER TABLE seller_ratings ADD COLUMN previous_rating INT DEFAULT NULL");
            $conn->query("ALTER TABLE seller_ratings ADD COLUMN previous_comment VARCHAR(100) DEFAULT NULL");
        }

        $curr = $conn->prepare("SELECT rating, comment, status FROM seller_ratings WHERE id = ? AND buyer_id = ?");
        $curr->bind_param("ii", $r_id, $user_id);
        $curr->execute();
        $curr_res = $curr->get_result()->fetch_assoc();
        
        if ($curr_res) {
            if ($curr_res['status'] == 'Approved') {
                $stmt = $conn->prepare("UPDATE seller_ratings SET previous_rating = rating, previous_comment = comment, rating = ?, comment = ?, status = 'Pending Approval' WHERE id = ? AND buyer_id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE seller_ratings SET rating = ?, comment = ?, status = 'Pending Approval' WHERE id = ? AND buyer_id = ?");
            }
            $stmt->bind_param("isii", $rating, $comment, $r_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['msg'] = "SELLER REVIEW UPDATED AND PENDING APPROVAL.";
                $_SESSION['msg_type'] = "success";
            }
        }
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: DELETE MY SELLER REVIEW ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_my_seller_review'])) {
    $r_id = (int)$_POST['review_id'];
    
    $p_stmt = $conn->prepare("SELECT seller_id FROM seller_ratings WHERE id = ? AND buyer_id = ?");
    $p_stmt->bind_param("ii", $r_id, $user_id);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result()->fetch_assoc();
    
    if ($p_res) {
        $s_id = $p_res['seller_id'];
        $del_stmt = $conn->prepare("DELETE FROM seller_ratings WHERE id = ? AND buyer_id = ?");
        $del_stmt->bind_param("ii", $r_id, $user_id);
        if ($del_stmt->execute()) {
            $conn->query("UPDATE users u SET u.average_seller_rating = (SELECT IFNULL(AVG(rating), 0) FROM seller_ratings WHERE seller_id = $s_id AND status = 'Approved'), u.total_seller_reviews = (SELECT COUNT(*) FROM seller_ratings WHERE seller_id = $s_id AND status = 'Approved') WHERE u.id = $s_id");
            $_SESSION['msg'] = "SELLER REVIEW DELETED.";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: SUBMIT SELLER REVIEW ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_seller_review'])) {
    $seller_id = (int)$_POST['seller_id'];
    $transaction_id = (int)$_POST['transaction_id'];
    $rating = max(1, min(5, (int)$_POST['rating']));
    $comment = substr(trim($_POST['comment']), 0, 100);
    
    // Validate that the transaction belongs to the buyer and seller, and is RECEIVED
    $valid_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND buyer_id = ? AND seller_id = ? AND status = 'RECEIVED'");
    $valid_stmt->bind_param("iii", $transaction_id, $user_id, $seller_id);
    $valid_stmt->execute();
    if ($valid_stmt->get_result()->num_rows > 0) {
        
        // Ensure only one active review per transaction (update if rejected)
        $check_stmt = $conn->prepare("SELECT id FROM seller_ratings WHERE transaction_id = ?");
        $check_stmt->bind_param("i", $transaction_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows == 0) {
            $insert_stmt = $conn->prepare("INSERT INTO seller_ratings (seller_id, buyer_id, transaction_id, rating, comment, status) VALUES (?, ?, ?, ?, ?, 'Pending Approval')");
            $insert_stmt->bind_param("iiiis", $seller_id, $user_id, $transaction_id, $rating, $comment);
            $success = $insert_stmt->execute();
        } else {
            $update_stmt = $conn->prepare("UPDATE seller_ratings SET rating = ?, comment = ?, status = 'Pending Approval' WHERE transaction_id = ?");
            $update_stmt->bind_param("isi", $rating, $comment, $transaction_id);
            $success = $update_stmt->execute();
        }

        if ($success) {
            $_SESSION['msg'] = "SELLER REVIEW SUBMITTED AND PENDING APPROVAL.";
            $_SESSION['msg_type'] = "success";
            
            // Notify seller
            $seller_msg = "A buyer has submitted a review for a recent transaction. It is pending admin approval.";
            sendAppNotification($conn, $seller_id, $seller_msg);
        } else {
            $_SESSION['msg'] = "FAILED TO SUBMIT SELLER REVIEW.";
            $_SESSION['msg_type'] = "error";
        }
    } else {
        $_SESSION['msg'] = "INVALID TRANSACTION FOR REVIEW.";
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: client-profile.php");
    exit();
}

// --- POST: CONFIRM RECEIPT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_receipt'])) {
    $order_id = (int)$_POST['order_id'];
    
    $conn->begin_transaction();
    try {
        $order_stmt = $conn->prepare("SELECT seller_id, amount, payout_status, status FROM orders WHERE id = ? AND buyer_id = ? FOR UPDATE");
        $order_stmt->bind_param("ii", $order_id, $user_id);
        $order_stmt->execute();
        $order = $order_stmt->get_result()->fetch_assoc();

        if (!$order) {
            throw new Exception("Order not found.");
        }

        if ($order['status'] !== 'PAID') {
            throw new Exception("Order is not in a valid state to be received.");
        }

        $update_stmt = $conn->prepare("UPDATE orders SET status = 'RECEIVED' WHERE id = ?");
        $update_stmt->bind_param("i", $order_id);
        $update_stmt->execute();

        $seller_id = (int)$order['seller_id'];
        $payout_status = strtoupper(trim((string)$order['payout_status']));

        if ($seller_id > 0) {
            // Marketplace Product
            if ($payout_status === 'PENDING') {
                $amount = (float)$order['amount'];
                $seller_payout = round($amount * 0.95, 2);

                $wallet_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, total_sales = total_sales + 1 WHERE id = ?");
                $wallet_stmt->bind_param("di", $seller_payout, $seller_id);
                $wallet_stmt->execute();

                $payout_stmt = $conn->prepare("UPDATE orders SET payout_status = 'RELEASED', payout_date = NOW() WHERE id = ?");
                $payout_stmt->bind_param("i", $order_id);
                $payout_stmt->execute();

                $seller_msg = "Your funds have been released! $" . number_format($seller_payout, 2) . " has been added to your Wallet for a confirmed delivery.";
                sendAppNotification($conn, $seller_id, $seller_msg);
                
                checkAutoVerification($conn, $seller_id);
            }
        } else {
            // Official Shop Product
            $payout_stmt = $conn->prepare("UPDATE orders SET payout_status = 'NOT_APPLICABLE', payout_date = NOW() WHERE id = ?");
            $payout_stmt->bind_param("i", $order_id);
            $payout_stmt->execute();
        }

        $conn->commit();
        $_SESSION['msg'] = "ORDER MARKED AS RECEIVED.";
        $_SESSION['msg_type'] = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "COULD NOT UPDATE ORDER: " . strtoupper($e->getMessage());
        $_SESSION['msg_type'] = "error";
    }

    header("Location: client-profile.php");
    exit();
}

// --- POST: HIDE INVENTORY ITEM (SOLD) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hide_inventory_item'])) {
    $product_id = (int)$_POST['product_id'];
    $check = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
    $check->bind_param("ii", $product_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $upd = $conn->prepare("UPDATE products SET hidden_from_seller = 1 WHERE id = ?");
        $upd->bind_param("i", $product_id);
        $upd->execute();
        $_SESSION['msg'] = "ITEM REMOVED FROM VIEW.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: HIDE BUY HISTORY ITEM (CANCELLED/RECEIVED) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hide_buy_history_item'])) {
    $order_id = (int)$_POST['order_id'];
    $check = $conn->prepare("SELECT id FROM orders WHERE id = ? AND buyer_id = ? AND status IN ('CANCELLED', 'RECEIVED')");
    $check->bind_param("ii", $order_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $upd = $conn->prepare("UPDATE orders SET hidden_from_buyer = 1 WHERE id = ?");
        $upd->bind_param("i", $order_id);
        $upd->execute();
        $_SESSION['msg'] = "ORDER REMOVED FROM VIEW.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: HIDE SALE RECORD ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hide_sale_record'])) {
    $order_id = (int)$_POST['order_id'];
    $check = $conn->prepare("SELECT id FROM orders WHERE id = ? AND seller_id = ? AND status IN ('PAID', 'RECEIVED')");
    $check->bind_param("ii", $order_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $upd = $conn->prepare("UPDATE orders SET hidden_from_seller = 1 WHERE id = ?");
        $upd->bind_param("i", $order_id);
        $upd->execute();
        $_SESSION['msg'] = "SALE RECORD REMOVED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: UPDATE PROFILE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_email = trim($_POST['new_email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $update_query_parts = [];
    $types = "";
    $params = [];
    $username_changed = false;

    if (!empty($new_username) && $new_username !== $user_data['username']) {
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $new_username)) {
            $_SESSION['msg'] = "USERNAME MUST BE 3-20 CHARACTERS (ALPHANUMERIC & UNDERSCORES ONLY).";
            $_SESSION['msg_type'] = "error";
            header("Location: client-profile.php");
            exit();
        }
        $check_user = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_user->bind_param("si", $new_username, $user_id);
        $check_user->execute();
        if ($check_user->get_result()->num_rows > 0) {
            $_SESSION['msg'] = "USERNAME ALREADY TAKEN.";
            $_SESSION['msg_type'] = "error";
            header("Location: client-profile.php");
            exit();
        }
        $update_query_parts[] = "username = ?";
        $types .= "s";
        $params[] = $new_username;
        $username_changed = true;
    }

    if (!empty($new_email) && $new_email !== $user_data['email']) {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['msg'] = "INVALID EMAIL FORMAT.";
            $_SESSION['msg_type'] = "error";
            header("Location: client-profile.php");
            exit();
        }
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $new_email, $user_id);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $_SESSION['msg'] = "EMAIL ALREADY TAKEN.";
            $_SESSION['msg_type'] = "error";
            header("Location: client-profile.php");
            exit();
        }
        
        // Handle Secure Email Change Flow
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $req_stmt = $conn->prepare("INSERT INTO email_change_requests (user_id, new_email, token, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE new_email = VALUES(new_email), token = VALUES(token), expires_at = VALUES(expires_at)");
        $req_stmt->bind_param("isss", $user_id, $new_email, $token, $expires_at);
        
        if ($req_stmt->execute()) {
            $verify_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify-email.php?token=" . $token;
            $email_body = "
                <p>You requested to change your email address to <strong>" . htmlspecialchars($new_email) . "</strong>.</p>
                <p>Please click the link below to verify this change. You will be required to enter your current password.</p>
                <p><a href='" . $verify_link . "' style='display:inline-block; padding:10px 20px; background:#ff4b4b; color:#fff; text-decoration:none; font-weight:bold;'>VERIFY EMAIL CHANGE</a></p>
                <p>If you did not request this, please ignore this email or secure your account.</p>
                <p><small>This link will expire in 24 hours.</small></p>
            ";
            require_once __DIR__ . '/../notification-service.php';
            $html_body = buildEmailTemplate("Verify Email Change", $email_body);
            sendEmail($user_data['email'], "SkateShop - Verify Your Email Change", $html_body);
            
            $_SESSION['msg'] = "Email change request submitted. Please check your current email address to verify and complete the change.";
            $_SESSION['msg_type'] = "success";
            
            // Delete any existing pending email notifications to avoid duplicates
            $msg_like = "Email change request to%";
            $del_old = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND message LIKE ?");
            $del_old->bind_param("is", $user_id, $msg_like);
            $del_old->execute();
            
            // Add a standard notification
            $email_notif = "Email change request to " . $new_email . " is pending. Please check your current email address to verify.";
            $ins = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
            $ins->bind_param("is", $user_id, $email_notif);
            $ins->execute();
        } else {
            $_SESSION['msg'] = "ERROR INITIATING EMAIL CHANGE.";
            $_SESSION['msg_type'] = "error";
            header("Location: client-profile.php");
            exit();
        }
    }

    if (!empty($new_password)) {
        $pwd_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $pwd_stmt->bind_param("i", $user_id);
        $pwd_stmt->execute();
        $user_pwd_hash = $pwd_stmt->get_result()->fetch_assoc()['password'];

        if (!password_verify($current_password, $user_pwd_hash)) {
            $_SESSION['msg'] = "INCORRECT CURRENT PASSWORD.";
            $_SESSION['msg_type'] = "error";
            header("Location: client-profile.php");
            exit();
        }

        if ($new_password === $confirm_password) {
            $update_query_parts[] = "password = ?";
            $types .= "s";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        } else {
            $_SESSION['msg'] = "PASSWORDS DO NOT MATCH.";
            $_SESSION['msg_type'] = "error";
            header("Location: client-profile.php");
            exit();
        }
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "../assets/uploads/avatars/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $new_filename = 'user_' . $user_id . '_' . time() . '.' . pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . $new_filename;
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $update_query_parts[] = "profile_pic = ?";
            $types .= "s";
            $params[] = $target_file;
        }
    }

    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $update_query_parts[] = "email_notifications = ?";
    $types .= "i";
    $params[] = $email_notifications;


    if (!empty($update_query_parts)) {
        $sql = "UPDATE users SET " . implode(", ", $update_query_parts) . " WHERE id = ?";
        $types .= "i"; $params[] = $user_id;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        if ($username_changed) {
            $_SESSION['username'] = $new_username;
            
            $c1 = $conn->prepare("UPDATE products SET seller_name = ? WHERE seller_id = ?");
            $c1->bind_param("si", $new_username, $user_id); $c1->execute();
            
            $c2 = $conn->prepare("UPDATE reels SET username = ? WHERE user_id = ?");
            $c2->bind_param("si", $new_username, $user_id); $c2->execute();
            
            $c3 = $conn->prepare("UPDATE reel_comments SET username = ? WHERE user_id = ?");
            $c3->bind_param("si", $new_username, $user_id); $c3->execute();
            
            $c4 = $conn->prepare("UPDATE community_qna SET username = ? WHERE user_id = ?");
            $c4->bind_param("si", $new_username, $user_id); $c4->execute();
            
            $c5 = $conn->prepare("UPDATE community_shoutouts SET username = ? WHERE user_id = ?");
            $c5->bind_param("si", $new_username, $user_id); $c5->execute();
        }

        if (empty($_SESSION['msg']) || $_SESSION['msg'] === "PROFILE UPDATED.") {
            $_SESSION['msg'] = "PROFILE UPDATED.";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: SUBMIT NEW GEAR ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_market_item'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $brand = $conn->real_escape_string($_POST['brand']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $condition = $conn->real_escape_string($_POST['condition_badge']);
    $description = $conn->real_escape_string($_POST['description']);
    
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0){
        $target_file = "../assets/uploads/" . uniqid('market_') . '.' . pathinfo($_FILES["item_image"]["name"], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO products (title, brand, price, category, condition_badge, description, image_url, is_marketplace, is_approved, seller_id, seller_name) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)");
            $stmt->bind_param("ssdssssis", $title, $brand, $price, $category, $condition, $description, $target_file, $user_id, $username);
            $stmt->execute();
            $_SESSION['msg'] = "GEAR SUBMITTED FOR REVIEW!";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: UPDATE EXISTING LISTING ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_listing'])) {
    $p_id = (int)$_POST['product_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $brand = $conn->real_escape_string($_POST['brand']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $condition = $conn->real_escape_string($_POST['condition_badge']);
    $description = $conn->real_escape_string($_POST['description']);

    $sql = "UPDATE products SET title=?, brand=?, price=?, category=?, condition_badge=?, description=?, is_approved=0";
    $types = "ssdsss"; 
    $params = [$title, $brand, $price, $category, $condition, $description];

    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $target_file = "../assets/uploads/" . uniqid('market_') . '.' . pathinfo($_FILES["item_image"]["name"], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file)) {
            $sql .= ", image_url=?";
            $types .= "s";
            $params[] = $target_file;
        }
    }

    $sql .= " WHERE id=? AND seller_id=? AND is_approved = 1";
    $types .= "ii";
    $params[] = $p_id;
    $params[] = $user_id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "LISTING UPDATED AND SENT FOR REVIEW.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "DATABASE ERROR: COULD NOT UPDATE.";
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: client-profile.php");
    exit();
}

// --- POST: DELETE MY REEL ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_my_reel'])) {
    $r_id = (int)$_POST['reel_id'];
    $conn->query("DELETE FROM reel_likes WHERE reel_id = $r_id");
    $conn->query("DELETE FROM reel_comments WHERE reel_id = $r_id");
    $conn->query("DELETE FROM reel_edit_requests WHERE reel_id = $r_id");
    $stmt = $conn->prepare("DELETE FROM reels WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $r_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "REEL SHREDDED!";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: DELETE MY QNA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_my_qna'])) {
    $q_id = (int)$_POST['qna_id'];
    $stmt = $conn->prepare("DELETE FROM community_qna WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $q_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Q&A DELETED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: DELETE MY SHOUTOUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_my_shoutout'])) {
    $s_id = (int)$_POST['shoutout_id'];
    $stmt = $conn->prepare("DELETE FROM community_shoutouts WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $s_id, $user_id);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "SHOUTOUT DELETED.";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: SUBMIT REEL EDIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reel_edit'])) {
    $reel_id = (int)$_POST['reel_id'];
    $new_title = trim($_POST['new_title']);
    $new_desc = trim($_POST['new_description']);

    $own_check = $conn->prepare("SELECT id, is_approved FROM reels WHERE id = ? AND user_id = ?");
    $own_check->bind_param("ii", $reel_id, $user_id);
    $own_check->execute();
    $reel_res = $own_check->get_result()->fetch_assoc();
    
    if (!$reel_res) {
        $_SESSION['msg'] = "REEL NOT FOUND.";
        $_SESSION['msg_type'] = "error";
        header("Location: client-profile.php");
        exit();
    }
    if ($reel_res['is_approved'] != 1) {
        $_SESSION['msg'] = "REEL MUST BE APPROVED BEFORE EDITING.";
        $_SESSION['msg_type'] = "error";
        header("Location: client-profile.php");
        exit();
    }

    if (empty($new_title)) {
        $_SESSION['msg'] = "TITLE IS REQUIRED.";
        $_SESSION['msg_type'] = "error";
    } elseif (mb_strlen($new_title) > 35) {
        $_SESSION['msg'] = "TITLE IS TOO LONG (MAX 35).";
        $_SESSION['msg_type'] = "error";
    } elseif (mb_strlen($new_desc) > 100) {
        $_SESSION['msg'] = "DESCRIPTION IS TOO LONG (MAX 100).";
        $_SESSION['msg_type'] = "error";
    } else {
        $cancel_edit = $conn->prepare("DELETE FROM reel_edit_requests WHERE reel_id = ? AND user_id = ? AND status = 'pending'");
        $cancel_edit->bind_param("ii", $reel_id, $user_id);
        $cancel_edit->execute();

        $ins = $conn->prepare("INSERT INTO reel_edit_requests (reel_id, user_id, new_title, new_description) VALUES (?, ?, ?, ?)");
        $ins->bind_param("iiss", $reel_id, $user_id, $new_title, $new_desc);
        $ins->execute();

        $notif_msg = "Your edit for the reel '" . $new_title . "' has been submitted and will be reviewed by an administrator.";
        sendAppNotification($conn, $user_id, $notif_msg);

        $_SESSION['msg'] = "EDIT SUBMITTED FOR ADMIN REVIEW.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: DELETE ACCOUNT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    $delete_password = $_POST['delete_password'] ?? '';
    
    // Fetch the user's current hashed password
    $pwd_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $pwd_stmt->bind_param("i", $user_id);
    $pwd_stmt->execute();
    $pwd_res = $pwd_stmt->get_result()->fetch_assoc();
    
    if (!$pwd_res || !password_verify($delete_password, $pwd_res['password'])) {
        $_SESSION['msg'] = "INCORRECT PASSWORD. PROFILE NOT DELETED.";
        $_SESSION['msg_type'] = "error";
        header("Location: client-profile.php");
        exit();
    }

    // --- 1. Fetch File Paths for Deletion ---
    $files_to_delete = [];

    // Profile Pic
    $pp_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $pp_stmt->bind_param("i", $user_id);
    $pp_stmt->execute();
    $pp_res = $pp_stmt->get_result()->fetch_assoc();
    if ($pp_res && !empty($pp_res['profile_pic']) && strpos($pp_res['profile_pic'], 'assets/uploads/') !== false) {
        $files_to_delete[] = $pp_res['profile_pic'];
    }

    // Product Images
    $pi_stmt = $conn->prepare("SELECT image_url FROM products WHERE seller_id = ?");
    $pi_stmt->bind_param("i", $user_id);
    $pi_stmt->execute();
    $pi_res = $pi_stmt->get_result();
    while ($row = $pi_res->fetch_assoc()) {
        if (!empty($row['image_url']) && strpos($row['image_url'], 'assets/uploads/') !== false) {
            $files_to_delete[] = $row['image_url'];
        }
    }

    // Reel Videos
    $rv_stmt = $conn->prepare("SELECT video_url FROM reels WHERE user_id = ?");
    $rv_stmt->bind_param("i", $user_id);
    $rv_stmt->execute();
    $rv_res = $rv_stmt->get_result();
    while ($row = $rv_res->fetch_assoc()) {
        if (!empty($row['video_url']) && strpos($row['video_url'], 'assets/uploads/') !== false) {
            $files_to_delete[] = $row['video_url'];
        }
    }

    // --- 2. Database Transaction for Complete Deletion ---
    $conn->begin_transaction();
    try {
        // Anonymize Orders to preserve history for the other party
        try {
            $conn->query("UPDATE orders SET buyer_id = NULL WHERE buyer_id = " . (int)$user_id);
            $conn->query("UPDATE orders SET seller_id = NULL WHERE seller_id = " . (int)$user_id);
        } catch (Exception $e) {
            // Fallback if NULL is not allowed
            $conn->query("UPDATE orders SET buyer_id = 0 WHERE buyer_id = " . (int)$user_id);
            $conn->query("UPDATE orders SET seller_id = 0 WHERE seller_id = " . (int)$user_id);
        }

        // Delete dependencies
        $conn->query("DELETE FROM user_follows WHERE follower_id = " . (int)$user_id . " OR followed_id = " . (int)$user_id);
        
        $sr_check = $conn->query("SHOW TABLES LIKE 'seller_ratings'");
        if ($sr_check && $sr_check->num_rows > 0) {
            $conn->query("DELETE FROM seller_ratings WHERE buyer_id = " . (int)$user_id . " OR seller_id = " . (int)$user_id);
        }
        
        $conn->query("DELETE FROM notifications WHERE user_id = " . (int)$user_id);
        
        $ec_check = $conn->query("SHOW TABLES LIKE 'email_change_requests'");
        if ($ec_check && $ec_check->num_rows > 0) {
            $conn->query("DELETE FROM email_change_requests WHERE user_id = " . (int)$user_id);
        }
        
        $conn->query("DELETE FROM reel_likes WHERE user_id = " . (int)$user_id);
        $conn->query("DELETE FROM reel_comments WHERE user_id = " . (int)$user_id);
        $conn->query("DELETE FROM reel_edit_requests WHERE user_id = " . (int)$user_id);
        $conn->query("DELETE FROM reels WHERE user_id = " . (int)$user_id);
        $conn->query("DELETE FROM community_qna WHERE user_id = " . (int)$user_id);
        
        $cs_check = $conn->query("SHOW TABLES LIKE 'community_shoutouts'");
        if ($cs_check && $cs_check->num_rows > 0) {
            $conn->query("DELETE FROM community_shoutouts WHERE user_id = " . (int)$user_id);
        }
        
        $conn->query("DELETE FROM products WHERE seller_id = " . (int)$user_id);
        $conn->query("DELETE FROM users WHERE id = " . (int)$user_id);

        $conn->commit();
        
        // --- 3. Unlink Files after successful commit ---
        foreach ($files_to_delete as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        session_destroy();
        header("Location: ../client_page/index.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "ERROR DELETING ACCOUNT: " . strtoupper($e->getMessage());
        $_SESSION['msg_type'] = "error";
        header("Location: client-profile.php");
        exit();
    }
}

// --- POST: USER TICKET REPLY ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_ticket_reply'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $reply_message = trim($_POST['reply_message']);
    
    // Verify ticket belongs to user
    $chk = $conn->query("SELECT id, status FROM support_tickets WHERE id = $ticket_id AND user_id = $user_id");
    if ($chk && $chk->num_rows > 0) {
        $t_data = $chk->fetch_assoc();
        
        if ($t_data['status'] === 'New') {
            $_SESSION['msg'] = "PLEASE WAIT FOR AN ADMINISTRATOR TO RESPOND BEFORE SENDING ANOTHER MESSAGE.";
            $_SESSION['msg_type'] = "error";
        } elseif (in_array($t_data['status'], ['Resolved', 'Closed'])) {
            $_SESSION['msg'] = "THIS TICKET IS CLOSED AND CANNOT ACCEPT NEW REPLIES.";
            $_SESSION['msg_type'] = "error";
        } elseif (!empty($reply_message)) {
            $stmt = $conn->prepare("INSERT INTO support_ticket_replies (ticket_id, sender_type, user_id, message) VALUES (?, 'user', ?, ?)");
            $stmt->bind_param("iis", $ticket_id, $user_id, $reply_message);
            $stmt->execute();
            
            $conn->query("UPDATE support_tickets SET status = 'Open' WHERE id = $ticket_id AND status != 'Open'");
            
            $_SESSION['msg'] = "REPLY SENT.";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: client-profile.php");
    exit();
}

// User Delete Ticket Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_ticket_btn'])) {
    $del_ticket_id = (int)$_POST['delete_ticket_id'];
    
    // Hard delete to clean up records
    $del_stmt = $conn->prepare("DELETE FROM support_tickets WHERE id = ? AND user_id = ?");
    $del_stmt->bind_param("ii", $del_ticket_id, $user_id);
    
    if ($del_stmt->execute()) {
        $_SESSION['msg'] = "TICKET AND ALL MESSAGES PERMANENTLY DELETED.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "ERROR DELETING TICKET.";
        $_SESSION['msg_type'] = "error";
    }
    header("Location: client-profile.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | DASHBOARD</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/user-profile.css">
    <script src="../assets/script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- Optimized Listings Design --- */
        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            padding: 10px 0;
        }

        /* Support Ticket Message Styling (Copied from reports.php) */
        .thread-msg {
            background: #fff;
            border: 3px solid #000;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 4px 4px 0px #000;
        }
        .thread-msg.admin-msg {
            background: #f1f8fa;
            border-color: var(--primary);
            box-shadow: 4px 4px 0px var(--primary);
        }
        .thread-msg-header {
            font-family: 'Staatliches', sans-serif;
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            color: #222;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 10px;
        }
        .thread-msg-header .sender-name {
            letter-spacing: 1px;
            color: #000;
        }
        .thread-msg-header .msg-timestamp {
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
        }
        .thread-msg .msg-body p {
            font-family: 'Inter', sans-serif;
            font-size: 1.05rem;
            line-height: 1.6;
            white-space: pre-wrap;
            color: #111;
        }

        .mini-listing {
            background: #ffffff; 
            border: 3px solid #000000; 
            position: relative;
            padding: 15px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            cursor: pointer;
            overflow: hidden;
            box-shadow: 4px 4px 0px #000000;
        }

        .status-sold { background: #000; color: #fff; }

        /* Status Badges - Global Dashboard Style */
        .listing-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.75rem;
            font-weight: 900;
            padding: 4px 10px;
            border: 2px solid #000;
            z-index: 2;
            font-family: 'Staatliches', sans-serif;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .status-active, .status-received { background: #2ecc71; color: white; }
        .status-pending { background: #f1c40f; color: #1a1a1a; }
        .status-cancelled, .status-rejected { background: var(--primary); color: white; }
        .status-edit-locked { background: #666; color: #fff; font-size: 0.65rem !important; }

        /* MY QNAs REDESIGN - MATCHING DASHBOARD ECOSYSTEM */
        .my-qna-card { border: 4px solid #000; padding: 1.5rem; margin-bottom: 1.5rem; background: #fff; display: flex; gap: 1.5rem; align-items: flex-start; box-shadow: 6px 6px 0 #000; transition: transform 0.2s; cursor: pointer; position: relative; }
        .my-qna-card:hover { transform: translateY(-2px); box-shadow: 8px 8px 0 #000; border-color: var(--primary); }
        .my-qna-info { flex: 1; min-width: 0; }
        .my-qna-title { font-family: 'Staatliches', sans-serif; font-size: 1.6rem; letter-spacing: 1px; margin: 0 0 8px; color: #000; text-transform: uppercase; word-break: break-word; line-height: 1.1; padding-right: 100px; }
        .my-qna-body-preview { font-family: 'Inter', sans-serif; font-size: 0.95rem; color: #444; margin: 0 0 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
        .my-qna-meta { display: flex; gap: 15px; font-family: 'Staatliches', sans-serif; font-size: 1rem; color: #555; margin-bottom: 12px; flex-wrap: wrap; align-items: center; }
        .my-qna-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        @media (max-width: 768px) { .my-qna-card { flex-direction: column; } .my-qna-title { padding-right: 0; margin-top: 30px; } }

        /* QNA DETAIL VIEW OVERLAY */
        .view-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.92); backdrop-filter: blur(8px);
            z-index: 99999; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .view-overlay.active { opacity: 1; visibility: visible; }
        .view-shell {
            width: 95%; max-width: 800px; background: #111;
            border: 4px solid var(--charcoal); box-shadow: 15px 15px 0px var(--primary);
            position: relative; transform: translateY(30px); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex; flex-direction: column; max-height: 90vh;
        }
        .view-overlay.active .view-shell { transform: translateY(0); }
        .view-close {
            position: absolute; top: 12px; right: 20px;
            font-size: 3rem; color: white; cursor: pointer;
            font-family: 'Staatliches', sans-serif; line-height: 1; z-index: 100;
            transition: color 0.2s, transform 0.2s;
            text-shadow: 2px 2px 0px var(--charcoal);
        }
        .view-close:hover { color: var(--primary); transform: scale(1.2); }
        .view-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 3rem;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) #1a1a1a;
        }
        .view-content::-webkit-scrollbar { width: 10px; }
        .view-content::-webkit-scrollbar-track { background: #1a1a1a; }
        .view-content::-webkit-scrollbar-thumb { 
            background: var(--primary); 
            border: 2px solid #1a1a1a;
        }
        .view-content::-webkit-scrollbar-thumb:hover { background: white; }

        .mini-listing img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            margin-bottom: 12px;
            filter: grayscale(0%);
            border: 2px solid #000000;
            background: #f9f9f9;
        }

        .mini-listing-info {
            flex-grow: 1;
        }

        .mini-listing-title {
            font-size: 1.1rem;
            color: #000000; 
            margin: 0 0 5px 0;
            font-family: 'Arial Black', sans-serif;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .mini-listing-price {
            color: var(--primary);
            font-weight: bold;
            font-size: 1.2rem;
            margin: 0;
        }

        .mini-listing-id {
            font-size: 0.7rem;
            color: #444; 
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Pagination Styling */
        .listing-pagination {
            margin-top: 30px;
            border-top: 3px solid #000000; 
            padding-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
        }

        .page-number {
            font-weight: bold;
            color: #000;
        }

        .btn-pagination {
            background: #fff;
            color: #000;
            border: 3px solid #000;
            padding: 10px 14px;
            cursor: pointer;
            box-shadow: 4px 4px 0 #000;
        }

        .btn-pagination i {
            color: #000;
        }

        .btn-pagination:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .empty-placeholder-box {
            display: flex;
            flex-direction: column;
            align-items: center;        
            justify-content: center;
            text-align: center;          
        }

        .empty-placeholder-box .btn-primary-brutal {
            margin-top: 15px;
            padding: 12px 20px;         
        }

        /* Additional Confirm Modal Styles */
        #confirmReceiptModal .btn-receipt-no {
            flex: 1;
            background: var(--primary);
            color: #fff !important;
        }
        #confirmReceiptModal .btn-receipt-no:hover {
            background: var(--textwhite);
            color: #1A1A1A !important;
        }
        #confirmReceiptModal .btn-receipt-yes {
            background: #2ecc71;
            color: #fff !important;
        }
        #confirmReceiptModal .btn-receipt-yes:hover {
            background: #27ae60;
            color: #fff !important;
        }
        #notReceivedMsg .contact-label {
            color: var(--primary);
            font-size: 1.1rem;
            font-family: 'Staatliches', sans-serif;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 6px;
        }
        .btn-hide-cancel {
            background: #666 !important;
            color: #fff !important;
        }
        .btn-hide-cancel:hover {
            background: #444 !important;
        }
        .custom-checkbox { display: block; position: relative; padding-left: 38px; margin-bottom: 25px; cursor: pointer; font-family: 'Staatliches', sans-serif; font-size: 1.2rem; color: var(--charcoal); letter-spacing: 1px; user-select: none; }
        .custom-checkbox input[type="checkbox"] { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
        .custom-checkbox .checkmark { position: absolute; top: 0; left: 0; height: 24px; width: 24px; background-color: var(--textwhite); border: 3px solid var(--charcoal); transition: background-color 0.15s, border-color 0.15s; }
        .custom-checkbox:hover .checkmark { border-color: var(--primary); }
        .custom-checkbox input[type="checkbox"]:checked ~ .checkmark { background-color: var(--primary) !important; border-color: var(--primary) !important; }
        .custom-checkbox .checkmark:after { content: ""; position: absolute; display: none; left: 6px; top: 2px; width: 6px; height: 11px; border: solid #fff; border-width: 0 3px 3px 0; transform: rotate(45deg); }
        .custom-checkbox input[type="checkbox"]:checked ~ .checkmark:after { display: block; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div class="dashboard-header">
        <h2 class="glitch-text-admin">USER <span class="text-primary">DASHBOARD</span></h2>
        <div style="display: flex; justify-content: space-between; align-items: flex-end; width: 100%; flex-wrap: wrap; gap: 1rem;">
            <p class="admin-text-shop">WELCOME BACK, @<?php echo htmlspecialchars($username); ?></p>
            <button class="btn-primary-brutal" onclick="openMyProfile('<?php echo urlencode($username); ?>')" style="padding: 0.5rem 1.5rem; font-size: 1.2rem; transform: skewX(-6deg); margin-bottom: 5px; cursor: pointer;">VIEW PROFILE</button>
        </div>
    </div>

    <?php if ($msg): ?>
        <div id="alert-box" class="admin-alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="option-grid">
        <div class="option-card" onclick="openModal('editProfile')">
            <i class="fa-solid fa-user-gear"></i>
            <h3>EDIT PROFILE</h3>
        </div>
        <div class="option-card" onclick="openModal('sellGear')">
            <i class="fas fa-money-bill-alt"></i>
            <h3>SELL GEAR</h3>
        </div>
        <div class="option-card" onclick="openModal('myReels')">
            <i class="fa-solid fa-film"></i>
            <h3>MY REELS</h3>
        </div>
        <div class="option-card" onclick="openModal('myListings')">
            <i class="fa-solid fa-list-ul"></i>
            <h3>MY LISTINGS</h3>
        </div>
        <div class="option-card" onclick="openModal('buyHistory')">
            <i class="fa-solid fa-history"></i>
            <h3>BUY HISTORY</h3>
        </div>
        <div class="option-card" onclick="openModal('salesHistory')">
            <i class="fa-solid fa-hand-holding-dollar"></i>
            <h3>SALES HISTORY</h3>
        </div>
        <div class="option-card-long" onclick="openModal('notificationsModal')">
            <i class="fa-solid fa-bell"></i>
            <h3>NOTIFICATIONS</h3>
            <?php if ($unread_count > 0): ?>
                <span class="notification-badge-client"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </div>
        <div class="option-card" onclick="openModal('myWallet')">
            <i class="fa-solid fa-wallet"></i>
            <h3>MY WALLET</h3>
            <span style="color: #2ecc71; font-weight: 900;">$<?php echo number_format($user_data['wallet_balance'] ?? 0, 2); ?></span>
        </div>
        <div class="option-card" onclick="openModal('myQnaModal')">
            <i class="fa-solid fa-circle-question"></i>
            <h3>MY QNAs</h3>
        </div>
        <div class="option-card" onclick="openModal('myReviewsModal')">
            <i class="fa-solid fa-star"></i>
            <h3>MY REVIEWS</h3>
        </div>
        <div class="option-card" onclick="openModal('myShoutoutModal')">
            <i class="fa-solid fa-bullhorn"></i>
            <h3>MY SHOUTOUTS</h3>
        </div>
        <div class="option-card" onclick="openModal('ticketsModal')">
            <i class="fa-solid fa-headset"></i>
            <h3>SUPPORT HISTORY</h3>
        </div>
        <div class="option-card card-danger" onclick="openModal('deleteProfile')">
            <i class="fa-solid fa-trash-can"></i>
            <h3>DELETE ACCOUNT</h3>
        </div>
    </div>
</main>

<!-- === USER PROFILE MODAL (OWN PROFILE) === -->
<div id="userProfileModal" class="modal-overlay">
    <div class="modal-content seller-profile-modal-shell" id="userProfileContent" style="padding: 30px; width: 90%; max-width: 1100px; max-height: 90vh; overflow-y: auto;">
        <!-- Content loaded via AJAX -->
    </div>
</div>

<!-- === FOLLOWERS MODAL === -->
<div id="followersModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal" onclick="closeModal('followersModal')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">FOLLOWERS</span></h3>
        <div class="followers-list" style="margin-top: 1rem; max-height: 400px; overflow-y: auto;">
            <?php if ($followers_list->num_rows > 0): ?>
                <?php while ($f = $followers_list->fetch_assoc()): ?>
                    <div class="follower-item" style="display: flex; align-items: center; gap: 1rem; padding: 10px; border-bottom: 2px solid #eee;">
                        <img src="<?php echo htmlspecialchars($f['profile_pic'] ? $f['profile_pic'] : '../assets/images/default-avatar.png'); ?>" 
                             style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid var(--primary); object-fit: cover;">
                        <span style="font-family: 'Staatliches', sans-serif; font-size: 1.2rem; color: var(--charcoal);">
                            @<?php echo htmlspecialchars($f['username']); ?>
                        </span>
                        <button class="btn-mini btn-view" style="margin-left: auto;" onclick="closeModal('followersModal'); openMyProfile('<?php echo urlencode($f['username']); ?>')">VIEW</button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-placeholder-box" style="padding: 20px;">
                    <p class="empty-placeholder-title" style="font-size: 1.2rem;">NO FOLLOWERS YET.</p>
                    <p style="font-family:'Staatliches',sans-serif; color:#666;">Keep shredding to get noticed!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="editProfile" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editProfile')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">PROFILE</span></h3>
        <form action="client-profile.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="avatar-edit-preview">
                <img src="<?php echo htmlspecialchars($user_data['profile_pic'] ? $user_data['profile_pic'] : '../assets/images/default-avatar.png'); ?>" class="avatar-edit-img">
            </div>
            <label>CHANGE AVATAR</label>
            <input type="file" name="profile_pic" accept="image/*">
            <label>USERNAME</label>
            <input type="text" name="new_username" placeholder="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>">
            <label>EMAIL</label>
            <input type="email" name="new_email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
            <label>CURRENT PASSWORD</label>
            <input type="password" name="current_password" placeholder="REQUIRED IF CHANGING PASSWORD">
            <label>NEW PASSWORD</label>
            <input type="password" name="new_password" placeholder="LEAVE BLANK TO KEEP CURRENT">
            <label>CONFIRM PASSWORD</label>
            <input type="password" name="confirm_password" placeholder="REPEAT NEW PASSWORD" style="margin-bottom: 20px;">

            <label class="custom-checkbox">
                <input type="checkbox" name="email_notifications" value="1" <?php echo (($user_data['email_notifications'] ?? 1) == 1) ? 'checked' : ''; ?>>
                <span class="checkmark"></span>
                RECEIVE EMAIL NOTIFICATIONS
            </label>
            <button type="submit" name="update_profile" class="btn-primary-brutal btn-full">SAVE CHANGES</button>
        </form>
    </div>
</div>

<div id="sellGear" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('sellGear')">&times;</span>
        <h3 class="admin-table-h3">SELL <span class="header-span">GEAR</span></h3>
        <form action="client-profile.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="form-grid-2-1">
                <div>
                    <label>TITLE</label>
                    <input type="text" name="title" placeholder="E.G. INDEPENDENT 149" required>
                </div>
                <div>
                    <label>BRAND</label>
                    <input type="text" name="brand" placeholder="E.G. INDY" required>
                </div>
            </div>
            <div class="form-grid-3">
                <div>
                    <label>PRICE ($)</label>
                    <input type="number" name="price" step="0.01" placeholder="0.00" required>
                </div>
                <div>
                    <label>CATEGORY</label>
                    <select name="category">
                        <option>DECKS</option><option>TRUCKS</option><option>WHEELS</option>
                        <option>BEARINGS</option><option>APPAREL</option><option>ACCESORIES</option><option>OTHER</option>
                    </select>
                </div>
                <div>
                    <label>CONDITION</label>
                    <select name="condition_badge">
                        <option>MINT / WALL HANGER</option><option>LIGHTLY SCUFFED</option><option>BEAT UP / SKATEABLE</option>
                    </select>
                </div>
            </div>
            <label>DESCRIPTION</label>
            <textarea name="description" rows="3" placeholder="DESCRIBE YOUR GEAR..." required></textarea>
            <label>IMAGE</label>
            <input type="file" name="item_image" required>
            <button type="submit" name="add_market_item" class="btn-primary-brutal btn-full">SUBMIT FOR REVIEW</button>
        </form>
    </div>
</div>

<div id="myListings" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px;"> <span class="close-modal" onclick="closeModal('myListings')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">INVENTORY</span></h3>

        <?php if($user_listings->num_rows > 0): ?>
            <div class="listings-grid" id="listings-wrapper">
                <?php while($row = $user_listings->fetch_assoc()): ?>
                    <?php 
                        $is_pending = ($row['is_approved'] == 0 && $row['sold_count'] == 0);
                        $is_sold = ($row['sold_count'] > 0);
                        $can_edit = (!$is_pending && !$is_sold);
                    ?>
                    <?php if(!$can_edit): ?>
                        <div class="mini-listing listing-page-item" style="cursor: not-allowed; opacity: 0.8;" title="<?php echo $is_pending ? 'CANNOT EDIT WHILE PENDING' : 'SOLD ITEMS CANNOT BE EDITED'; ?>">
                    <?php else: ?>
                        <div class="mini-listing listing-page-item" onclick="openEditListing(<?php echo $row['id']; ?>)">
                    <?php endif; ?>
                        
                        <?php if($row['sold_count'] > 0): ?>
                            <span class="listing-status-badge status-sold">SOLD</span>
                            <button type="button" class="btn-mini btn-danger" style="position:absolute; bottom:12px; right:12px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size:1rem; z-index: 10;" onclick="event.stopPropagation(); openHideInventoryModal(<?php echo $row['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php elseif($row['is_approved'] == 0): ?>
                            <span class="listing-status-badge status-edit-locked" style="top: 10px;">CANNOT EDIT WHILE PENDING</span>
                            <span class="listing-status-badge status-pending" style="top: 40px;">PENDING</span>
                        <?php else: ?>
                            <span class="listing-status-badge status-active">ACTIVE</span>
                        <?php endif; ?>

                        <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="Gear Image">
                        
                        <div class="mini-listing-info">
                            <h4 class="mini-listing-title"><?php echo strtoupper(htmlspecialchars($row['title'])); ?></h4>
                            <p class="mini-listing-price">$<?php echo number_format($row['price'], 2); ?></p>
                        </div>
                        
                        <span class="mini-listing-id">REF_#<?php echo $row['id']; ?></span>
                    </div>
                <?php endwhile; ?>
            </div>

            <div id="listing-pagination-controls" class="listing-pagination">
                <button class="btn-pagination" onclick="changeListingPage(-1)" id="prevListBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span id="listingPageIndicator" class="page-number">PAGE 1</span>
                <button class="btn-pagination" onclick="changeListingPage(1)" id="nextListBtn">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">NO GEAR FOUND IN YOUR LOCKER.</p>
                <button class="btn-primary-brutal" onclick="closeModal('myListings'); openModal('sellGear');">START SELLING</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- === MY REELS MODAL === -->
<div id="myReels" class="modal-overlay">
    <div class="modal-content" style="max-width: 860px;">
        <span class="close-modal" onclick="closeModal('myReels')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">REELS</span></h3>

        <style>
            .my-reel-card { border: 4px solid #000; padding: 1.5rem; margin-bottom: 1.5rem; background: #fff; display: flex; gap: 1.5rem; align-items: flex-start; box-shadow: 6px 6px 0 #000; transition: transform 0.2s; position: relative; }
            .my-reel-card:hover { transform: translateY(-2px); box-shadow: 8px 8px 0 #000; }
            .my-reel-thumb { width: 320px; max-width: 100%; flex-shrink: 0; background: #000; aspect-ratio: 16/9; border: 3px solid #000; }
            .my-reel-thumb iframe { width: 100%; height: 100%; border: none; display: block; }
            .my-reel-info { flex: 1; min-width: 0; }
            .my-reel-title { font-family: 'Staatliches', sans-serif; font-size: 1.6rem; letter-spacing: 1px; margin: 0 0 8px; color: #000; text-transform: uppercase; word-break: break-word; line-height: 1.1; padding-right: 120px; }
            .my-reel-desc { font-family: 'Inter', sans-serif; font-size: 0.95rem; color: #444; margin: 0 0 12px; word-break: break-word; line-height: 1.4; }
            .my-reel-meta { display: flex; gap: 15px; font-family: 'Staatliches', sans-serif; font-size: 1rem; color: #555; margin-bottom: 12px; flex-wrap: wrap; align-items: center; }
            .my-reel-actions { display: flex; gap: 10px; flex-wrap: wrap; }
            @media (max-width: 768px) { .my-reel-card { flex-direction: column; } .my-reel-thumb { width: 100%; } }
            .status-edit { background: #a78bfa; color: #fff; }
            .my-reel-comments-container { margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 10px; display: none; max-height: 150px; overflow-y: auto; }
            .my-reel-comments-container::-webkit-scrollbar { width: 6px; }
            .my-reel-comments-container::-webkit-scrollbar-track { background: #f1f1f1; border: 1px solid #ccc; }
            .my-reel-comments-container::-webkit-scrollbar-thumb { background: #000; }
            .my-reel-comments-container::-webkit-scrollbar-thumb:hover { background: var(--primary); }
            .my-reel-comment { font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #333; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px; }
            .my-reel-comment strong { font-family: 'Staatliches', sans-serif; font-size: 0.95rem; letter-spacing: 0.5px; }
            .my-reel-comment-date { font-size: 0.7rem; color: #888; margin-left: 6px; }
            .btn-toggle-comments { background: none; border: none; font-family: 'Staatliches', sans-serif; color: #555; cursor: pointer; padding: 0; text-decoration: underline; font-size: 0.85rem; margin-left: 8px; }
            .btn-toggle-comments:hover { color: var(--primary); }
        </style>

        <?php if ($my_reels_result && $my_reels_result->num_rows > 0): ?>
            <?php while ($reel = $my_reels_result->fetch_assoc()): ?>
                <div class="my-reel-card my-reel-page-item">
                    <div class="my-reel-thumb">
                        <iframe src="<?php echo htmlspecialchars($reel['embed_url']); ?>" allowfullscreen loading="lazy"></iframe>
                    </div>
                    <div class="my-reel-info">
                        <?php if ($reel['is_approved'] == 1): ?>
                            <span class="listing-status-badge status-active">LIVE</span>
                        <?php elseif ($reel['is_approved'] == 0): ?>
                            <span class="listing-status-badge status-pending">PENDING</span>
                        <?php endif; ?>
                        <?php if ($reel['has_pending_edit'] > 0): ?>
                            <span class="listing-status-badge status-edit" style="top: 45px;">EDIT PENDING</span>
                        <?php endif; ?>

                        <p class="my-reel-title"><?php echo htmlspecialchars($reel['title']); ?></p>
                        <?php if (!empty($reel['description'])): ?>
                            <p class="my-reel-desc"><?php echo htmlspecialchars($reel['description']); ?></p>
                        <?php endif; ?>
                        <div class="my-reel-meta">
                            <span><i class="fas fa-heart" style="color:var(--primary)"></i> <?php echo (int)$reel['like_count']; ?></span>
                            <span>
                                <i class="fas fa-comment" style="color:#555"></i> <?php echo (int)$reel['comment_count']; ?>
                                <?php if ($reel['comment_count'] > 0): ?>
                                    <button type="button" class="btn-toggle-comments" onclick="toggleReelComments(<?php echo $reel['id']; ?>)">VIEW</button>
                                <?php endif; ?>
                            </span>
                            <span><?php echo date('M j, Y', strtotime($reel['created_at'])); ?></span>
                        </div>
                        <div class="my-reel-actions">
                            <?php if ($reel['is_approved'] == 1 && $reel['has_pending_edit'] == 0): ?>
                                <button class="btn-mini btn-view" onclick="openEditReel(<?php echo $reel['id']; ?>, <?php echo htmlspecialchars(json_encode($reel['title'])); ?>, <?php echo htmlspecialchars(json_encode($reel['description'] ?? '')); ?>)">EDIT</button>
                            <?php elseif ($reel['is_approved'] == 0): ?>
                                <button class="btn-mini" style="background:#ccc; cursor:not-allowed;" disabled title="Wait for approval before editing">LOCKED</button>
                            <?php elseif ($reel['has_pending_edit'] > 0): ?>
                                <button class="btn-mini" style="background:#ccc; cursor:not-allowed;" disabled title="Edit already pending review">EDIT PENDING</button>
                            <?php else: ?>
                                <button class="btn-mini" style="background:#ccc; cursor:not-allowed;" disabled title="Cannot edit this reel">LOCKED</button>
                            <?php endif; ?>
                            <button type="button" name="delete_my_reel" class="btn-mini btn-danger" onclick="openDeleteReelModal(<?php echo $reel['id']; ?>)">
                                <i class="fas fa-trash"></i> DELETE
                            </button>
                        </div>
                        <div id="reel-comments-<?php echo $reel['id']; ?>" class="my-reel-comments-container"></div>
                    </div>
                </div>
            <?php endwhile; ?>

            <div id="my-reels-pagination-controls" class="listing-pagination">
                <button class="btn-pagination" onclick="changeMyReelsPage(-1)" id="prevReelBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span id="myReelsPageIndicator" class="page-number">PAGE 1</span>
                <button class="btn-pagination" onclick="changeMyReelsPage(1)" id="nextReelBtn">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
            
        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">YOU HAVEN'T POSTED ANY REELS YET.</p>
                <p style="font-family:'Staatliches',sans-serif; color:#666; margin-bottom:20px;">Share your clips with the community.</p>
                <button class="btn-primary-brutal" onclick="closeModal('myReels'); window.location.href='reels.php';">GO TO REELS</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- === EDIT REEL SUB-MODAL === -->
<div id="editReelModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 520px;">
        <span class="close-modal" onclick="closeModal('editReelModal'); openModal('myReels');">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">REEL</span></h3>
        <p style="font-family:'Staatliches',sans-serif; color:#777; letter-spacing:1px; margin-bottom:1.2rem; font-size:0.95rem;">
            YOUR EDIT WILL BE SENT FOR ADMIN REVIEW BEFORE GOING LIVE.
        </p>
        <form action="client-profile.php" method="POST" class="admin-form" id="edit-reel-form">
            <input type="hidden" name="reel_id" id="edit-reel-id">
            <label>TITLE <span style="font-family:'Inter',sans-serif; font-size:0.75rem; color:#999;">(MAX 35 CHARS)</span></label>
            <input type="text" name="new_title" id="edit-reel-title" maxlength="35" required>
            <div id="edit-reel-title-counter" style="font-family:'Staatliches',sans-serif; font-size:0.85rem; color:#777; text-align:right; margin-top:-0.8rem; margin-bottom:0.8rem; letter-spacing:1px;">35 characters remaining</div>
            <label>DESCRIPTION <span style="font-family:'Inter',sans-serif; font-size:0.75rem; color:#999;">(MAX 100 CHARS, OPTIONAL)</span></label>
            <textarea name="new_description" id="edit-reel-desc" rows="3" maxlength="100" style="resize:vertical;"></textarea>
            <div id="edit-reel-desc-counter" style="font-family:'Staatliches',sans-serif; font-size:0.85rem; color:#777; text-align:right; margin-top:-0.8rem; margin-bottom:0.8rem; letter-spacing:1px;">100 characters remaining</div>
            <button type="submit" name="submit_reel_edit" class="btn-primary-brutal btn-full">SUBMIT EDIT FOR REVIEW</button>
        </form>
    </div>
</div>

<div id="editListingModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="backToListings()">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">LISTING</span></h3>
        <form action="client-profile.php" method="POST" enctype="multipart/form-data" class="admin-form" id="edit-listing-form">
            <input type="hidden" name="product_id" id="edit-p-id">
            
            <div class="form-grid-2-1">
                <div>
                    <label>TITLE</label>
                    <input type="text" name="title" id="edit-p-title" required>
                </div>
                <div>
                    <label>BRAND</label>
                    <input type="text" name="brand" id="edit-p-brand" required>
                </div>
            </div>

            <div class="form-grid-3">
                <div>
                    <label>PRICE ($)</label>
                    <input type="number" name="price" id="edit-p-price" step="0.01" required>
                </div>
                <div>
                    <label>CATEGORY</label>
                    <select name="category" id="edit-p-category">
                        <option>DECKS</option><option>TRUCKS</option><option>WHEELS</option>
                        <option>BEARINGS</option><option>APPAREL</option><option>ACCESORIES</option><option>OTHER</option>
                    </select>
                </div>
                <div>
                    <label>CONDITION</label>
                    <select name="condition_badge" id="edit-p-condition">
                        <option>MINT / WALL HANGER</option><option>LIGHTLY SCUFFED</option><option>BEAT UP / SKATEABLE</option>
                    </select>
                </div>
            </div>

            <label>DESCRIPTION</label>
            <textarea name="description" id="edit-p-desc" rows="3" required></textarea>

            <label>REPLACE IMAGE (LEAVE BLANK TO KEEP CURRENT)</label>
            <input type="file" name="item_image">

            <button type="submit" name="update_listing" class="btn-primary-brutal btn-full">UPDATE & RE-SUBMIT</button>
        </form>
    </div>
</div>

<div id="myQnaModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 860px;"> 
        <span class="close-modal" onclick="closeModal('myQnaModal')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">QUESTIONS</span></h3>

        <?php if($my_qna_result->num_rows > 0): ?>
            <div id="my-qna-list">
                <?php while($row = $my_qna_result->fetch_assoc()): ?>
                    <div class="my-qna-card my-qna-page-item" onclick="openQnaDetails(<?php echo $row['id']; ?>)">
                        <div class="my-qna-info">
                            <?php if($row['status'] === 'published'): ?>
                                <span class="listing-status-badge status-active">PUBLISHED</span>
                            <?php elseif($row['status'] === 'pending'): ?>
                                <span class="listing-status-badge status-pending">PENDING</span>
                            <?php endif; ?>

                            <p class="my-qna-title"><?php echo strtoupper(htmlspecialchars($row['title'])); ?></p>
                            <p class="my-qna-body-preview"><?php echo htmlspecialchars($row['body']); ?></p>
                            
                            <div class="my-qna-meta">
                                <span><i class="fa-solid fa-calendar-day" style="color:var(--primary)"></i> <?php echo date('M j, Y', strtotime($row['created_at'])); ?></span>
                                <?php if($row['admin_answer']): ?>
                                    <span><i class="fa-solid fa-check-double" style="color:#2ecc71"></i> ANSWERED</span>
                                <?php endif; ?>
                            </div>

                            <div class="my-qna-actions" onclick="event.stopPropagation();">
                                <button type="button" class="btn-mini btn-danger" onclick="openDeleteQnaModal(<?php echo $row['id']; ?>)">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div id="my-qna-pagination-controls" class="listing-pagination">
                <button class="btn-pagination" onclick="changeMyQnaPage(-1)" id="prevQnaBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span id="myQnaPageIndicator" class="page-number">PAGE 1</span>
                <button class="btn-pagination" onclick="changeMyQnaPage(1)" id="nextQnaBtn">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">YOU HAVEN'T ASKED ANY QUESTIONS YET.</p>
                <p style="font-family:'Staatliches',sans-serif; color:#666; margin-bottom:20px;">Curiosity is the fuel for progress.</p>
                <button class="btn-primary-brutal" onclick="closeModal('myQnaModal'); window.location.href='qna.php';">GO TO Q&A</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="myShoutoutModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 860px;">
        <span class="close-modal" onclick="closeModal('myShoutoutModal')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">SHOUTOUTS</span></h3>

        <?php if (count($my_shoutouts_rows) > 0): ?>
            <div id="my-shoutout-list">
                <?php foreach ($my_shoutouts_rows as $row): ?>
                    <div class="my-qna-card my-qna-page-item" onclick="openShoutoutDetails(<?php echo (int)$row['id']; ?>)">
                        <div class="my-qna-info">
                            <?php if ($row['status'] === 'published'): ?>
                                <span class="listing-status-badge status-active">PUBLISHED</span>
                            <?php elseif ($row['status'] === 'pending'): ?>
                                <span class="listing-status-badge status-pending">PENDING</span>
                            <?php elseif ($row['status'] === 'rejected'): ?>
                                <span class="listing-status-badge status-cancelled">REJECTED</span>
                            <?php endif; ?>

                            <p class="my-qna-title"><?php echo strtoupper(htmlspecialchars($row['title'])); ?></p>
                            <p class="my-qna-body-preview"><?php echo htmlspecialchars($row['body']); ?></p>

                            <div class="my-qna-meta">
                                <span><i class="fa-solid fa-calendar-day" style="color:var(--primary)"></i> <?php echo date('M j, Y', strtotime($row['created_at'])); ?></span>
                            </div>

                            <div class="my-qna-actions" onclick="event.stopPropagation();">
                                <button type="button" class="btn-mini btn-danger" onclick="openDeleteShoutoutModal(<?php echo (int)$row['id']; ?>)">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="my-shoutout-pagination-controls" class="listing-pagination">
                <button class="btn-pagination" onclick="changeMyShoutoutPage(-1)" id="prevShoutoutBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span id="myShoutoutPageIndicator" class="page-number">PAGE 1</span>
                <button class="btn-pagination" onclick="changeMyShoutoutPage(1)" id="nextShoutoutBtn">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">YOU HAVEN'T POSTED ANY SHOUTOUTS YET.</p>
                <p style="font-family:'Staatliches',sans-serif; color:#666; margin-bottom:20px;">Share some love on the community wall.</p>
                <button class="btn-primary-brutal" onclick="closeModal('myShoutoutModal'); window.location.href='shoutouts.php';">GO TO SHOUTOUTS</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- QNA VIEW MODAL -->
<div class="view-overlay" id="qnaViewOverlay">
    <div class="view-shell">
        <span class="view-close" onclick="closeQnaView()">&times;</span>
        <div class="view-content" id="qnaViewContent">
            <!-- Populated via JS -->
        </div>
    </div>
</div>

<div class="view-overlay" id="shoutoutViewOverlay">
    <div class="view-shell">
        <span class="view-close" onclick="closeShoutoutView()">&times;</span>
        <div class="view-content" id="shoutoutViewContent">
            <!-- Populated via JS -->
        </div>
    </div>
</div>

<div id="notificationsModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('notificationsModal')">&times;</span>
        <h3 class="admin-table-h3">SYSTEM <span class="header-span">MESSAGES</span></h3>
        <div id="notif-wrapper" class="notifications-list">
            <?php if ($notifications->num_rows > 0): ?>
                <?php while($n = $notifications->fetch_assoc()): 
                    $raw_msg = $n['message'];
                    $short_msg = strlen($raw_msg) > 35 ? substr($raw_msg, 0, 35) . "..." : $raw_msg;
                    $formatted_date = date("M j, H:i", strtotime($n['created_at']));
                ?>
                    <div class="notification-item notif-page-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                        <div class="notif-content" 
                             data-notif-id="<?php echo $n['id']; ?>" 
                             data-full-msg="<?php echo htmlspecialchars($raw_msg, ENT_QUOTES); ?>" 
                             data-date="<?php echo $formatted_date; ?>"
                             onclick="openFullMessage(this)">
                            <p class="notif-msg"><?php echo htmlspecialchars($short_msg); ?></p>
                            <span class="notif-date"><?php echo $formatted_date; ?></span>
                        </div>
                        <button type="button" class="btn-mini btn-danger" style="position:absolute; top: 50%; right: 15px; transform: translateY(-50%); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size:1rem; z-index: 10;" onclick="event.stopPropagation(); openDeleteNotifModal(<?php echo $n['id']; ?>)">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-placeholder-box"><p class="empty-placeholder-title">NO NOTIFICATIONS YET.</p></div>
            <?php endif; ?>
        </div>
        <?php if ($unread_count > 0): ?>
            <a href="client-profile.php?clear_notifs=1" class="btn-mark-read">MARK ALL READ</a>
        <?php endif; ?>
        <div id="pagination-controls" class="notif-pagination">
            <button class="btn-pagination" onclick="changePage(-1)" id="prevBtn"><i class="fa-solid fa-chevron-left"></i></button>
            <span id="pageIndicator" class="page-number">PAGE 1</span>
            <button class="btn-pagination" onclick="changePage(1)" id="nextBtn"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
</div>

<div id="fullMessageModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('fullMessageModal')">&times;</span>
        <h3 class="admin-table-h3">MESSAGE <span class="header-span">DETAILS</span></h3>
        <div class="full-message-box">
            <p id="fullMessageDisplay" class="full-message-text"></p>
            <span id="fullMessageDateDisplay" class="notif-date"></span>
        </div>
        <button type="button" class="btn-primary-brutal btn-full" onclick="backToNotifications()">BACK TO INBOX</button>
    </div>
</div>

<div id="buyHistory" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px;">
        <span class="close-modal" onclick="closeModal('buyHistory')">&times;</span>
        <h3 class="admin-table-h3">BUY <span class="header-span">HISTORY</span></h3>
        
        <?php if (!empty($grouped_buy_history)): ?>
            <div class="listings-grid" style="grid-template-columns: 1fr;">
                <?php foreach ($grouped_buy_history as $group): ?>
                    <div class="buy-history-page-item" style="border: 4px solid var(--charcoal); margin-bottom: 20px; padding: 20px; background: var(--textwhite); box-shadow: 4px 4px 0px var(--charcoal);">
                        <div style="border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 20px;">
                            <h4 style="margin: 0; font-family: 'Staatliches', sans-serif; font-size: 1.5rem;">RECEIPT: <?php echo htmlspecialchars($group['group_id']); ?></h4>
                            <span style="font-size: 0.9rem; color: #555;">DATE: <?php echo date("M d, Y", strtotime($group['created_at'])); ?></span>
                            <div style="margin-top: 10px; font-size: 0.9rem; color: #000;">
                                <strong>Items Total:</strong> $<?php echo number_format($group['total_amount'], 2); ?> | 
                                <strong>Shipping Fee:</strong> $<?php echo number_format($group['total_shipping'], 2); ?> <br>
                                <?php
                                $w = $group['total_wallet'];
                                $s = $group['total_stripe'];
                                if ($w > 0 && $s <= 0) echo "<strong>Paid via:</strong> Wallet ($" . number_format($w, 2) . ")";
                                elseif ($w <= 0 && $s > 0) echo "<strong>Paid via:</strong> Stripe ($" . number_format($s, 2) . ")";
                                else echo "<strong>Paid via:</strong> Wallet ($" . number_format($w, 2) . ") + Stripe ($" . number_format($s, 2) . ")";
                                ?>
                            </div>
                        </div>

                        <div class="listings-grid" style="gap: 15px;">
                            <?php foreach ($group['items'] as $purchase): ?>
                                <?php $purchase_code = "ORD-" . str_pad($purchase['order_id'], 6, "0", STR_PAD_LEFT); ?>
                                
                                <div class="mini-listing" style="cursor: default; box-shadow: 2px 2px 0px var(--charcoal); border-width: 2px; position: relative;">
                                    <?php if ($purchase['status'] === 'CANCELLED'): ?>
                                        <span class="listing-status-badge status-cancelled">CANCELLED</span>
                                        <button type="button" class="btn-mini btn-danger" style="position:absolute; bottom:12px; right:12px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size:1rem; z-index: 10;" onclick="openHideBuyHistoryModal(<?php echo $purchase['order_id']; ?>)"><i class="fas fa-trash"></i></button>
                                    <?php elseif ($purchase['status'] === 'RECEIVED'): ?>
                                        <span class="listing-status-badge status-received">RECEIVED</span>
                                        <?php if (!($purchase['is_marketplace'] == 1 && !$purchase['has_seller_review'])): ?>
                                            <button type="button" class="btn-mini btn-danger" style="position:absolute; bottom:12px; right:12px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size:1rem; z-index: 10;" onclick="openHideBuyHistoryModal(<?php echo $purchase['order_id']; ?>)"><i class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="listing-status-badge status-active">PAID</span>
                                    <?php endif; ?>

                                    <img src="<?php echo htmlspecialchars($purchase['image_url']); ?>" alt="Product">
                                    <div class="mini-listing-info">
                                        <h4 class="mini-listing-title" style="font-size:1.1rem;"><?php echo strtoupper(htmlspecialchars($purchase['title'])); ?></h4>
                                        <p class="mini-listing-price">$<?php echo number_format($purchase['amount'], 2); ?></p>
                                    </div>

                                    <div style="margin-top: 5px; border-top: 1px dashed #ccc; padding-top: 5px;">
                                        <span class="mini-listing-id" style="display: block; font-weight: bold; color: #000; font-size: 0.8rem;">CODE: <?php echo $purchase_code; ?></span>
                                    </div>
                                    
                                    <?php if ($purchase['status'] === 'PAID'): ?>
                                        <button type="button" class="btn-primary-brutal btn-full" style="margin-top: 10px; font-size:0.9rem; padding:6px;" onclick="openConfirmReceiptModal(<?php echo $purchase['order_id']; ?>)">CONFIRM DELIVERY</button>
                                    <?php elseif ($purchase['status'] === 'RECEIVED' && $purchase['is_marketplace'] == 1 && !$purchase['has_seller_review']): ?>
                                        <button type="button" class="btn-primary-brutal btn-full" style="margin-top: 10px; font-size:0.9rem; padding:6px; background:var(--primary); color:#fff;" onclick="openReviewSellerModal(<?php echo (int)$purchase['seller_id']; ?>, '<?php echo htmlspecialchars($purchase['seller_name']); ?>', <?php echo $purchase['order_id']; ?>)">REVIEW SELLER</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="buy-history-pagination-controls" class="listing-pagination">
                <button class="btn-pagination" onclick="changeBuyPage(-1)" id="prevBuyBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span id="buyPageIndicator" class="page-number">PAGE 1</span>
                <button class="btn-pagination" onclick="changeBuyPage(1)" id="nextBuyBtn">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">NO PURCHASES YET.</p>
                <p style="font-family: 'Staatliches', sans-serif; color: #666; margin-bottom: 20px;">Hit the shop or street market to score some gear.</p>
                <button class="btn-primary-brutal" onclick="window.location.href='shop.php'">GO TO SHOP</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="salesHistory" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px;">
        <span class="close-modal" onclick="closeModal('salesHistory')">&times;</span>
        <h3 class="admin-table-h3">SALES <span class="header-span">HISTORY</span></h3>
        
        <?php if ($sales_history_result && $sales_history_result->num_rows > 0): ?>
            <div class="listings-grid">
                <?php while ($sale = $sales_history_result->fetch_assoc()): ?>
                    <?php $sale_code = "ORD-" . str_pad($sale['order_id'], 6, "0", STR_PAD_LEFT); ?>
                    
                    <div class="mini-listing buy-history-page-item" style="cursor: default; position: relative;">
                        
                        <?php if ($sale['status'] === 'RECEIVED'): ?>
                            <span class="listing-status-badge status-received">DELIVERED</span>
                        <?php else: ?>
                            <span class="listing-status-badge status-pending" style="background-color: #f39c12; color: #fff;">IN DELIVERY</span>
                        <?php endif; ?>
                        
                        <button type="button" class="btn-mini btn-danger" style="position:absolute; bottom:12px; right:12px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size:1rem; z-index: 10;" onclick="event.stopPropagation(); openDeleteSaleModal(<?php echo $sale['order_id']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>

                        <img src="<?php echo htmlspecialchars($sale['image_url']); ?>" alt="Product">
                        
                        <div class="mini-listing-info">
                            <h4 class="mini-listing-title"><?php echo strtoupper(htmlspecialchars($sale['title'])); ?></h4>
                            <p class="mini-listing-price">$<?php echo number_format($sale['amount'], 2); ?></p>
                        </div>
                        
                        <div style="margin-top: 10px; border-top: 2px dashed #000; padding-top: 10px;">
                            <span class="mini-listing-id" style="display: block; font-weight: bold; color: #000;">
                                BUYER: @<?php echo htmlspecialchars($sale['buyer_name']); ?>
                            </span>
                            <span class="mini-listing-id" style="display: block; margin-top: 5px;">
                                CODE: <?php echo $sale_code; ?>
                            </span>
                            <span class="mini-listing-id" style="display: block; margin-top: 5px;">
                                DATE: <?php echo date("M d, Y", strtotime($sale['created_at'])); ?>
                            </span>
                            
                            <div style="margin-top: 10px; font-size: 0.8rem; color: #555;">
                                <?php
                                $w = (float)$sale['amount_paid_wallet'];
                                $s = (float)$sale['amount_paid_stripe'];
                                if ($w > 0 && $s <= 0) {
                                    echo "<strong>Payment Method:</strong> Wallet<br>";
                                    echo "Paid from Wallet: $" . number_format($w, 2) . "<br>";
                                    echo "Paid from Stripe: $0.00";
                                } elseif ($w <= 0 && $s > 0) {
                                    echo "<strong>Payment Method:</strong> Stripe<br>";
                                    echo "Paid from Wallet: $0.00<br>";
                                    echo "Paid from Stripe: $" . number_format($s, 2);
                                } elseif ($w > 0 && $s > 0) {
                                    echo "<strong>Payment Method:</strong> Wallet + Stripe<br>";
                                    echo "Paid from Wallet: $" . number_format($w, 2) . "<br>";
                                    echo "Paid from Stripe: $" . number_format($s, 2);
                                } else {
                                    echo "<strong>Payment Method:</strong> N/A";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <div id="sales-history-pagination-controls" class="listing-pagination" style="margin-top: 20px;">
                <p style="text-align:center; font-family:'Staatliches', sans-serif; color:#666;">ALL RECENT SALES</p>
            </div>
        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">NO SALES YET.</p>
                <p style="font-family: 'Staatliches', sans-serif; color: #666; margin-bottom: 20px;">List some gear and spread the word!</p>
                <button class="btn-primary-brutal" onclick="closeModal('salesHistory'); openModal('myListings')">MY INVENTORY</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="myWallet" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('myWallet')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">WALLET</span></h3>
        
        <div class="grainy-card" style="text-align: center; padding: 40px 20px; background: #f7f7f7; border: 2px solid #333; margin-bottom: 20px;">
            <p style="color: #1A1A1A; font-family: 'Arial Black', sans-serif; margin: 0; letter-spacing: 2px;">AVAILABLE BALANCE</p>
            <h1 style="color: #2ecc71; font-size: 4rem; margin: 10px 0;">$<?php echo number_format($user_data['wallet_balance'] ?? 0, 2); ?></h1>
        </div>

        <button class="btn-primary-brutal btn-full" onclick="alert('Withdrawal system coming soon! Connect your bank in settings.')">WITHDRAW TO BANK</button>
        
        <p style="font-size: 0.8rem; color: #666; text-align: center; margin-top: 15px;">
            Funds from your marketplace sales appear here instantly.
        </p>
    </div>
</div>

<div id="deleteProfile" class="modal-overlay">
    <div class="modal-content modal-danger-dialog">
        <span class="close-modal" onclick="closeModal('deleteProfile')">&times;</span>
        <h3 class="admin-table-h3">ARE <span class="header-span">YOU</span> SURE?</h3>
        <p class="modal-danger-text">This action is permanent. All your releated things will be dragged to the graveyard.</p>
        <form action="client-profile.php" method="POST" class="admin-form">
            <label style="color:var(--primary); font-family:'Staatliches',sans-serif; font-size:1.2rem; letter-spacing:1px; margin-bottom:5px; display:block;">ENTER PASSWORD TO CONFIRM</label>
            <input type="password" name="delete_password" required style="width:100%; padding:10px; margin-bottom:20px; font-family:'Inter',sans-serif; border: 3px solid #000; outline:none;">
            <button type="submit" name="delete_account" class="btn-danger btn-full btn-space-bottom">YES, DELETE EVERYTHING</button>
        </form>
    </div>
    </div>
</div>

<!-- Support Tickets Modal -->
<div id="ticketsModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px;">
        <span class="close-modal" onclick="closeModal('ticketsModal')">&times;</span>
        <h3 class="admin-table-h3">SUPPORT <span class="header-span">HISTORY</span></h3>
        
        <table class="recent-activity-table" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>TICKET ID</th>
                    <th>SUBJECT</th>
                    <th>STATUS</th>
                    <th>LAST UPDATED</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($my_tickets && $my_tickets->num_rows > 0): ?>
                    <?php while ($t = $my_tickets->fetch_assoc()): ?>
                        <tr>
                            <td class="td-id">#<?php echo $t['id']; ?></td>
                            <td><?php echo htmlspecialchars($t['subject']); ?></td>
                            <td>
                                <?php if($t['status'] === 'New'): ?>
                                    <span class="badge-market" style="border: 2px solid #000; box-shadow: 2px 2px 0px #000;">WAITING</span>
                                <?php else: ?>
                                    <span class="badge-shop" style="background:#000; color:#fff; border:none;"><?php echo htmlspecialchars(strtoupper($t['status'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($t['updated_at'])); ?></td>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <button class="btn btn-primary" style="padding: 5px 10px; font-size: 0.9rem; border: 2px solid #000;" onclick="openTicketViewModal(<?php echo $t['id']; ?>)">VIEW</button>
                                    <button class="btn-mini btn-danger" onclick="openDeleteTicketModal(<?php echo $t['id']; ?>)"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 20px;">YOU HAVE NO SUPPORT TICKETS YET.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="text-align:center; margin-top: 20px;">
            <a href="contact-us.php" class="btn-primary-brutal" style="display:inline-block; padding: 10px 20px; text-decoration: none;">OPEN NEW TICKET</a>
        </div>
    </div>
</div>

<!-- View Specific Ticket Modal -->
<div id="ticketViewModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; display:flex; flex-direction:column;">
        <span class="close-modal" onclick="closeModal('ticketViewModal')">&times;</span>
        <h3 class="admin-table-h3">TICKET <span class="header-span">#<span id="u_t_id"></span></span></h3>
        <div id="u_ticket_content" style="flex:1; overflow-y:auto; padding: 20px; border: 3px solid #000; margin-bottom:20px; background:#f9f9f9;">
            Loading...
        </div>
        
        <form action="client-profile.php" method="POST" class="admin-form" style="margin-top: 0;">
            <input type="hidden" name="ticket_id" id="reply_t_id">
            <textarea name="reply_message" rows="3" required placeholder="Type your reply..." style="width:100%; border:3px solid #000; padding:10px; font-family:'Inter',sans-serif; margin-bottom:10px; outline:none;"></textarea>
            <button type="submit" name="user_ticket_reply" class="btn-primary-brutal" style="width:100%;">SEND REPLY</button>
        </form>
    </div>
</div>

<script>
// Prevent modal closing when interacting inside
document.querySelectorAll('.modal-content').forEach(mc => {
    mc.addEventListener('click', e => e.stopPropagation());
});
</script>

<div id="confirmDeleteTicketModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteTicketModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2.5rem;">DELETE <span class="header-span">TICKET?</span></h3>
        <p style="font-family:'Inter',sans-serif; margin-bottom:20px; color:#666;">Are you sure you want to permanently delete this ticket and all its messages? This cannot be undone.</p>
        <div style="display:flex; gap:10px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="delete_ticket_id" id="deleteTicketIdInput">
                <button type="submit" name="delete_user_ticket_btn" class="btn-primary-brutal btn-full btn-receipt-yes" style="margin-top:0; padding:20px;">YES, DELETE</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-hide-cancel" style="margin-top:0;" onclick="closeModal('confirmDeleteTicketModal')">CANCEL</button>
        </div>
    </div>
</div>

<div id="confirmReceiptModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmReceiptModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2.5rem;">DID YOU <span class="header-span">RECEIVE</span> THE PRODUCT?</h3>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="order_id" id="confirmReceiptOrderId">
                <button type="submit" name="confirm_receipt" class="btn-primary-brutal btn-full btn-receipt-yes">YES</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-receipt-no" onclick="showNotReceivedMsg()">NO</button>
        </div>
        <div id="notReceivedMsg" style="display:none; margin-top:15px; border: 3px dashed var(--charcoal); padding: 15px; background: rgba(0,0,0,0.03);">
            <span class="contact-label">Contact us through our email:</span>
            <a href="mailto:skateshopp2026@gmail.com" style="color:#3498db; text-decoration:underline; font-weight:bold;">skateshopp2026@gmail.com</a>
        </div>
    </div>
</div>

<div id="confirmDeleteSaleModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteSaleModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2rem; padding-top:10px;">REMOVE <span class="header-span">SALE?</span></h3>
        <p style="font-family:'Inter',sans-serif; margin-bottom:20px; color:#666;">Are you sure you want to remove this sale from your history list?</p>
        <div style="display:flex; gap:10px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="order_id" id="hideSaleHistoryOrderId">
                <button type="submit" name="hide_sale_record" class="btn-primary-brutal btn-full btn-receipt-yes" style="margin-top:0; padding:20px;">YES, REMOVE</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-hide-cancel" style="margin-top:0;" onclick="closeModal('confirmDeleteSaleModal')">CANCEL</button>
        </div>
    </div>
</div>

<div id="confirmHideInventoryModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmHideInventoryModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2rem; padding-top:10px;">REMOVE ITEM FROM <span class="header-span">INVENTORY?</span></h3>
        <p style="font-family:'Inter',sans-serif; margin-bottom:20px; color:#666;">Are you sure you want to remove this item from your inventory list?</p>
        <div style="display:flex; gap:10px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="product_id" id="hideInventoryProductId">
                <button type="submit" name="hide_inventory_item" class="btn-primary-brutal btn-full btn-receipt-yes" style="margin-top:0; padding:20px;">YES</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-hide-cancel" style="margin-top:0;" onclick="closeModal('confirmHideInventoryModal')">CANCEL</button>
        </div>
    </div>
</div>

<div id="confirmHideBuyHistoryModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmHideBuyHistoryModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2.5rem;">REMOVE FROM <span class="header-span">HISTORY?</span></h3>
        <p style="font-family:'Inter',sans-serif; margin-bottom:20px; color:#666;">Are you sure you want to remove this order from your buy history?</p>
        <div style="display:flex; gap:10px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="order_id" id="hideBuyHistoryOrderId">
                <button type="submit" name="hide_buy_history_item" class="btn-primary-brutal btn-full btn-receipt-yes" style="margin-top:0; padding:20px;">YES</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-hide-cancel" style="margin-top:0;" onclick="closeModal('confirmHideBuyHistoryModal')">CANCEL</button>
        </div>
    </div>
</div>

<div id="confirmDeleteReelModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteReelModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2.5rem;">DELETE THIS <span class="header-span">REEL?</span></h3>
        <p style="font-family:'Inter',sans-serif; margin-bottom:20px; color:#666;">This cannot be undone. All likes and comments will be lost in the void.</p>
        <div style="display:flex; gap:10px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="reel_id" id="deleteReelId">
                <button type="submit" name="delete_my_reel" class="btn-primary-brutal btn-full btn-receipt-yes" style="margin-top:0; padding:20px;">YES</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-hide-cancel" style="margin-top:0;" onclick="closeModal('confirmDeleteReelModal')">CANCEL</button>
        </div>
    </div>
</div>

<div id="confirmDeleteQnaModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteQnaModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2.5rem;">DELETE THIS <span class="header-span">QUESTION?</span></h3>
        <p style="font-family:'Inter',sans-serif; margin-bottom:20px; color:#666;">Are you sure? This will permanently remove your contribution.</p>
        <div style="display:flex; gap:10px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="qna_id" id="deleteQnaId">
                <button type="submit" name="delete_my_qna" class="btn-primary-brutal btn-full btn-receipt-yes" style="margin-top:0; padding:20px;">YES</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-hide-cancel" style="margin-top:0;" onclick="closeModal('confirmDeleteQnaModal')">CANCEL</button>
        </div>
    </div>
</div>

<div id="confirmDeleteShoutoutModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteShoutoutModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2.5rem;">DELETE THIS <span class="header-span">SHOUTOUT?</span></h3>
        <p style="font-family:'Inter',sans-serif; margin-bottom:20px; color:#666;">Are you sure? This will permanently remove your shoutout.</p>
        <div style="display:flex; gap:10px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="shoutout_id" id="deleteShoutoutId">
                <button type="submit" name="delete_my_shoutout" class="btn-primary-brutal btn-full btn-receipt-yes" style="margin-top:0; padding:20px;">YES</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-hide-cancel" style="margin-top:0;" onclick="closeModal('confirmDeleteShoutoutModal')">CANCEL</button>
        </div>
    </div>
</div>

<div id="confirmDeleteNotifModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteNotifModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2.5rem;">SHRED THIS <span class="header-span">MESSAGE?</span></h3>
        <p style="font-family:'Inter',sans-serif; margin-bottom:20px; color:#666;">Once deleted, this system message will be gone forever.</p>
        <div style="display:flex; gap:10px;">
            <form action="client-profile.php" method="GET" style="flex:1;">
                <input type="hidden" name="delete_notif" id="deleteNotifId">
                <button type="submit" class="btn-primary-brutal btn-full btn-receipt-yes" style="margin-top:0; padding:20px;">YES</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-hide-cancel" style="margin-top:0;" onclick="closeModal('confirmDeleteNotifModal')">CANCEL</button>
        </div>
    </div>
</div>

<!-- === MY REVIEWS MODAL === -->
<div id="myReviewsModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 860px;">
        <span class="close-modal" onclick="closeModal('myReviewsModal')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">REVIEWS</span></h3>

        <div style="max-height: 60vh; overflow-y: auto; padding-right: 10px;">
            <h4 class="admin-table-h3" style="font-size: 1.2rem; margin-bottom: 15px; color: #555;">GEAR REVIEWS</h4>
            <?php if ($my_reviews_result && $my_reviews_result->num_rows > 0): ?>
                <?php while ($rev = $my_reviews_result->fetch_assoc()): ?>
                    <div class="my-qna-card" style="margin-bottom: 20px;">
                        <div class="my-qna-info">
                            <?php if ($rev['status'] == 'Approved'): ?>
                                <span class="listing-status-badge status-active">APPROVED</span>
                            <?php elseif ($rev['status'] == 'Rejected'): ?>
                                <span class="listing-status-badge status-rejected">REJECTED</span>
                            <?php else: ?>
                                <span class="listing-status-badge status-pending">PENDING</span>
                            <?php endif; ?>

                            <h4 style="font-family: 'Staatliches', sans-serif; font-size: 1.4rem; margin: 25px 0 10px 0;"><?php echo htmlspecialchars($rev['product_title']); ?></h4>
                            <div style="color: var(--primary); margin-bottom: 10px;">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    echo ($i <= $rev['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                }
                                ?>
                            </div>
                            <?php if (!empty($rev['comment'])): ?>
                                <p style="font-family: 'Inter', sans-serif; font-size: 0.95rem; color: #444; margin-bottom: 15px;"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                            <?php endif; ?>
                            <div class="my-qna-actions">
                                <button type="button" class="btn-mini btn-view" onclick="openEditReviewModal(<?php echo $rev['id']; ?>, <?php echo $rev['rating']; ?>, '<?php echo htmlspecialchars(addslashes($rev['comment']), ENT_QUOTES); ?>')">EDIT</button>
                                <form method="POST" action="client-profile.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this review?');">
                                    <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                    <button type="submit" name="delete_my_review" class="btn-mini btn-danger"><i class="fas fa-trash"></i> DELETE</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="user-profile-empty-text" style="margin-bottom: 30px;">NO GEAR REVIEWS WRITTEN YET.</p>
            <?php endif; ?>

            <div style="width: 100%; height: 2px; background: #eee; margin: 30px 0;"></div>

            <h4 class="admin-table-h3" style="font-size: 1.2rem; margin-bottom: 15px; color: #555;">SELLER REVIEWS</h4>
            <?php if ($my_seller_reviews_result && $my_seller_reviews_result->num_rows > 0): ?>
                <?php while ($s_rev = $my_seller_reviews_result->fetch_assoc()): ?>
                    <div class="my-qna-card" style="margin-bottom: 20px;">
                        <div class="my-qna-info">
                            <?php if ($s_rev['status'] == 'Approved'): ?>
                                <span class="listing-status-badge status-active">APPROVED</span>
                            <?php elseif ($s_rev['status'] == 'Rejected'): ?>
                                <span class="listing-status-badge status-rejected">REJECTED</span>
                            <?php else: ?>
                                <span class="listing-status-badge status-pending">PENDING</span>
                            <?php endif; ?>

                            <h4 style="font-family: 'Staatliches', sans-serif; font-size: 1.4rem; margin: 25px 0 10px 0;">SELLER: @<?php echo htmlspecialchars($s_rev['seller_username']); ?></h4>
                            <div style="color: var(--primary); margin-bottom: 10px;">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    echo ($i <= $s_rev['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                }
                                ?>
                            </div>
                            <?php if (!empty($s_rev['comment'])): ?>
                                <p style="font-family: 'Inter', sans-serif; font-size: 0.95rem; color: #444; margin-bottom: 15px;"><?php echo nl2br(htmlspecialchars($s_rev['comment'])); ?></p>
                            <?php endif; ?>
                            <div class="my-qna-actions">
                                <button type="button" class="btn-mini btn-view" onclick="openEditSellerReviewModal(<?php echo $s_rev['id']; ?>, <?php echo $s_rev['rating']; ?>, '<?php echo htmlspecialchars(addslashes($s_rev['comment']), ENT_QUOTES); ?>')">EDIT</button>
                                <form method="POST" action="client-profile.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this seller review? It will recalculate their reputation score.');">
                                    <input type="hidden" name="review_id" value="<?php echo $s_rev['id']; ?>">
                                    <button type="submit" name="delete_my_seller_review" class="btn-mini btn-danger"><i class="fas fa-trash"></i> DELETE</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="user-profile-empty-text">NO SELLER REVIEWS WRITTEN YET.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- === EDIT REVIEW MODAL === -->
<div id="editReviewModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close-modal" onclick="closeModal('editReviewModal')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">REVIEW</span></h3>
        <form method="POST" action="client-profile.php" class="admin-form">
            <input type="hidden" name="review_id" id="edit_review_id">
            
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">RATING (1-5)</label>
            <div class="star-rating-input" id="edit-stars-container" style="font-size: 2rem; color: #555; cursor: pointer; margin-bottom: 20px;">
                <i class="fa-solid fa-star" data-rating="1"></i>
                <i class="fa-solid fa-star" data-rating="2"></i>
                <i class="fa-solid fa-star" data-rating="3"></i>
                <i class="fa-solid fa-star" data-rating="4"></i>
                <i class="fa-solid fa-star" data-rating="5"></i>
            </div>
            <input type="hidden" name="rating" id="edit_review_rating" value="5" required>
            
            <label>COMMENT (OPTIONAL, MAX 100 CHARS)</label>
            <textarea name="comment" id="edit_review_comment" class="review-textarea" rows="3" maxlength="100" placeholder="Tell us what you think..."></textarea>
            <div id="edit-review-counter" style="font-family:'Staatliches',sans-serif; font-size:0.85rem; color:#777; text-align:right; margin-top:0.5rem; margin-bottom:0.8rem; letter-spacing:1px;">100 characters remaining</div>
            
            <button type="submit" name="edit_my_review" class="btn-primary-brutal btn-full">SUBMIT FOR REVIEW</button>
        </form>
    </div>
</div>

<!-- === EDIT SELLER REVIEW MODAL === -->
<div id="editSellerReviewModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close-modal" onclick="closeModal('editSellerReviewModal')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">SELLER REVIEW</span></h3>
        <form method="POST" action="client-profile.php" class="admin-form">
            <input type="hidden" name="review_id" id="edit_seller_review_id">
            
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">RATING (1-5)</label>
            <div class="star-rating-input" id="edit-seller-stars-container" style="font-size: 2rem; color: #555; cursor: pointer; margin-bottom: 20px;">
                <i class="fa-solid fa-star" data-rating="1"></i>
                <i class="fa-solid fa-star" data-rating="2"></i>
                <i class="fa-solid fa-star" data-rating="3"></i>
                <i class="fa-solid fa-star" data-rating="4"></i>
                <i class="fa-solid fa-star" data-rating="5"></i>
            </div>
            <input type="hidden" name="rating" id="edit_seller_review_rating" value="5" required>
            
            <label>COMMENT (OPTIONAL, MAX 100 CHARS)</label>
            <textarea name="comment" id="edit_seller_review_comment" class="review-textarea" rows="3" maxlength="100" placeholder="Tell us what you think..."></textarea>
            <div id="edit-seller-review-counter" style="font-family:'Staatliches',sans-serif; font-size:0.85rem; color:#777; text-align:right; margin-top:0.5rem; margin-bottom:0.8rem; letter-spacing:1px;">100 characters remaining</div>
            
            <button type="submit" name="edit_my_seller_review" class="btn-primary-brutal btn-full">SUBMIT FOR REVIEW</button>
        </form>
    </div>
</div>

<script>
    // --- MODAL STACK MANAGER ---
    const ModalStack = {
        stack: [],
        baseZIndex: 90000,

        open: function(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

            // Remove from current position if it exists so we can push it to the top
            this.stack = this.stack.filter(mId => mId !== id);
            this.stack.push(id);

            // Apply progressive z-index so child modals always appear on top
            modal.style.zIndex = this.baseZIndex + (this.stack.length * 10);
            modal.classList.add('active');

            // Modal specific initialization/pagination logic
            if (id === 'notificationsModal') { currentNotifPage = 1; updatePagination(); }
            if (id === 'myListings') { currentListPage = 1; updateListingPagination(); }
            if (id === 'buyHistory') { currentBuyPage = 1; updateBuyPagination(); }
            if (id === 'myReels') { currentMyReelsPage = 1; updateMyReelsPagination(); }
            if (id === 'myQnaModal') { currentMyQnaPage = 1; updateMyQnaPagination(); }
            if (id === 'myShoutoutModal') { currentMyShoutoutPage = 1; updateMyShoutoutPagination(); }
        },

        close: function(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

            modal.classList.remove('active');
            
            // Clean up z-index after the fade-out transition
            setTimeout(() => {
                if (!modal.classList.contains('active')) modal.style.zIndex = '';
            }, 300);

            // Remove from the stack tracker
            this.stack = this.stack.filter(mId => mId !== id);
        }
    };

    function openModal(id) {
        ModalStack.open(id);
    }

    function closeModal(id) {
        // INTERCEPT PARENT CLOSURE
        if (id === 'userProfileModal') return; 
        ModalStack.close(id);
    }

    function closeMyProfileModal() {
        const profileModal = document.getElementById('userProfileModal');
        profileModal.classList.remove('active');
        profileModal.style.zIndex = '';
        ModalStack.stack = ModalStack.stack.filter(mId => mId !== 'userProfileModal');
    }

    function closeSellerModal() {
        closeMyProfileModal();
    }

    // --- AJAX PROFILE LOADER ---
    function openMyProfile(username) {
        const modal = document.getElementById('userProfileModal');
        const contentDiv = document.getElementById('userProfileContent');
        
        contentDiv.innerHTML = '<div class="user-profile-loading"><h2 class="glitch-text" style="text-align:center; padding: 50px;">LOADING RECORD...</h2></div>';
        
        ModalStack.open('userProfileModal');

        fetch(`user-profile.php?username=${username}`)
            .then(response => response.text())
            .then(html => {
                contentDiv.innerHTML = html;
            })
            .catch(err => {
                contentDiv.innerHTML = '<span class="close-modal" onclick="closeSellerModal()">&times;</span><h3 style="color:#ff4b2b; text-align:center; padding: 50px;">FAILED TO LOAD PROFILE.</h3>';
            });
    }

    // --- GLOBAL CLICK HANDLER ---
    window.onclick = function(event) { 
        if (event.target.classList.contains('modal-overlay')) { 
            if (event.target.id === 'userProfileModal') {
                closeMyProfileModal();
            } else {
                ModalStack.close(event.target.id);
            }
        } 
        
        if (event.target.classList.contains('view-overlay') || event.target.id === 'qnaViewOverlay' || event.target.id === 'shoutoutViewOverlay') {
            event.target.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // --- HELPER FUNCS ---
    function escHtml(str) {
        const d = document.createElement('div');
        if (str) d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    // --- CONFIRMATION MODALS ---
    function openConfirmReceiptModal(orderId) {
        document.getElementById('confirmReceiptOrderId').value = orderId;
        document.getElementById('notReceivedMsg').style.display = 'none';
        openModal('confirmReceiptModal');
    }
    function showNotReceivedMsg() {
        document.getElementById('notReceivedMsg').style.display = 'block';
    }
    function openHideInventoryModal(productId) {
        document.getElementById('hideInventoryProductId').value = productId;
        openModal('confirmHideInventoryModal');
    }
    function openDeleteSaleModal(orderId) {
        document.getElementById('hideSaleHistoryOrderId').value = orderId;
        openModal('confirmDeleteSaleModal');
    }

    function openHideBuyHistoryModal(orderId) {
        document.getElementById('hideBuyHistoryOrderId').value = orderId;
        openModal('confirmHideBuyHistoryModal');
    }
    function openDeleteReelModal(reelId) {
        document.getElementById('deleteReelId').value = reelId;
        openModal('confirmDeleteReelModal');
    }
    function openDeleteQnaModal(qnaId) {
        document.getElementById('deleteQnaId').value = qnaId;
        openModal('confirmDeleteQnaModal');
    }
    function openDeleteShoutoutModal(shoutoutId) {
        document.getElementById('deleteShoutoutId').value = shoutoutId;
        openModal('confirmDeleteShoutoutModal');
    }
    function openDeleteNotifModal(notifId) {
        document.getElementById('deleteNotifId').value = notifId;
        openModal('confirmDeleteNotifModal');
    }

    // --- Q&A DETAIL VIEW ---
    function openQnaDetails(id) {
        fetch(`client-profile.php?get_qna_details=${id}`)
        .then(res => res.json())
        .then(data => {
            if (!data) return;
            const content = document.getElementById('qnaViewContent');
            const date = new Date(data.created_at);
            const dateStr = date.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'}).toUpperCase();
            
            content.innerHTML = `
                <div class="view-body">
                    <div class="view-meta" style="font-family:'Staatliches', sans-serif; letter-spacing:2px; color:var(--primary); margin-bottom:1.5rem; display:flex; gap:1.5rem; align-items:center;">
                        <span>MY SUBMISSION</span>
                        <span>${dateStr}</span>
                    </div>
                    <h2 style="color: #fff; font-family: 'Staatliches', sans-serif; font-size: 3rem; margin-bottom: 1.5rem; line-height:1; text-transform:uppercase;">${escHtml(data.title)}</h2>
                    <div class="view-q-block" style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-left: 4px solid #444; color: #ccc; font-family: 'Inter', sans-serif; line-height: 1.8; margin-bottom: 2rem; font-size:1.1rem; word-break:break-word;">
                        ${escHtml(data.body).replace(/\n/g, '<br>')}
                    </div>
                    <div class="view-a-block" style="border-left: 4px solid var(--primary); padding: 2rem; background: rgba(225, 29, 72, 0.08); color: #fff; font-family: 'Inter', sans-serif; line-height:1.7; font-size:1.1rem; word-break:break-word;">
                        <strong style="color: var(--primary); display: block; font-family: 'Staatliches', sans-serif; font-size: 1.5rem; margin-bottom: 0.8rem; letter-spacing:1px;">OFFICIAL ANSWER</strong>
                        ${(data.admin_answer ? escHtml(data.admin_answer).replace(/\n/g, '<br>') : '—')}
                    </div>
                </div>
            `;
            const overlay = document.getElementById('qnaViewOverlay');
            overlay.style.zIndex = '999999';
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }

    function openShoutoutDetails(id) {
        fetch(`client-profile.php?get_shoutout_details=${id}`)
        .then(res => res.json())
        .then(data => {
            if (!data) return;
            const content = document.getElementById('shoutoutViewContent');
            const date = new Date(data.created_at);
            const dateStr = date.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'}).toUpperCase();
            const statusLabel = (data.status || '').toUpperCase();

            content.innerHTML = `
                <div class="view-body">
                    <div class="view-meta" style="font-family:'Staatliches', sans-serif; letter-spacing:2px; color:var(--primary); margin-bottom:1.5rem; display:flex; gap:1.5rem; align-items:center; flex-wrap:wrap;">
                        <span>MY SHOUTOUT</span>
                        <span>${statusLabel}</span>
                        <span>${dateStr}</span>
                    </div>
                    <h2 style="color: #fff; font-family: 'Staatliches', sans-serif; font-size: 3rem; margin-bottom: 1.5rem; line-height:1; text-transform:uppercase;">${escHtml(data.title)}</h2>
                    <div class="view-q-block" style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-left: 4px solid #444; color: #ccc; font-family: 'Inter', sans-serif; line-height: 1.8; margin-bottom: 2rem; font-size:1.1rem; word-break:break-word;">
                        ${escHtml(data.body).replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
            const overlay = document.getElementById('shoutoutViewOverlay');
            overlay.style.zIndex = '999999';
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }

    function closeQnaView() {
        document.getElementById('qnaViewOverlay').classList.remove('active');
        document.body.style.overflow = '';
    }

    function closeShoutoutView() {
        const el = document.getElementById('shoutoutViewOverlay');
        if (el) {
            el.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // --- LISTING EDIT LOGIC ---
    function openEditListing(id) {
        fetch(`client-profile.php?get_listing_details=${id}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('edit-p-id').value = data.id;
            document.getElementById('edit-p-title').value = data.title;
            document.getElementById('edit-p-brand').value = data.brand;
            document.getElementById('edit-p-price').value = data.price;
            document.getElementById('edit-p-category').value = data.category;
            document.getElementById('edit-p-condition').value = data.condition_badge;
            document.getElementById('edit-p-desc').value = data.description;
            
            closeModal('myListings');
            openModal('editListingModal');
        });
    }

    function backToListings() {
        closeModal('editListingModal');
        openModal('myListings');
    }

    // --- MY REELS EDIT LOGIC ---
    function toggleReelComments(reelId) {
        const container = document.getElementById('reel-comments-' + reelId);
        if (container.style.display === 'block') {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        container.innerHTML = '<div style="font-size:0.8rem; color:#666; font-family:\'Inter\',sans-serif;">LOADING COMMENTS...</div>';
        
        fetch(`reels-api.php?action=get_comments&reel_id=${reelId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.comments.length === 0) {
                        container.innerHTML = '<div style="font-size:0.85rem; color:#666; font-family:\'Inter\',sans-serif;">No comments yet.</div>';
                        return;
                    }
                    let html = '';
                    data.comments.forEach(c => {
                        const avatar = c.profile_pic ? c.profile_pic : '../assets/images/default-avatar.png';
                        html += `
                            <div class="my-reel-comment" style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 15px;">
                                <img src="${avatar}" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border: 2px solid #000;" alt="Avatar">
                                <div>
                                    <strong>@${c.username}</strong>
                                    <span class="my-reel-comment-date">${c.created_at}</span>
                                    <div style="margin-top:2px;">${c.comment}</div>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div style="font-size:0.85rem; color:red;">Error loading comments.</div>';
                }
            })
            .catch(err => {
                container.innerHTML = '<div style="font-size:0.85rem; color:red;">Network error.</div>';
            });
    }

    function openEditReel(id, title, desc) {
        document.getElementById('edit-reel-id').value = id;
        document.getElementById('edit-reel-title').value = title;
        document.getElementById('edit-reel-desc').value = desc;
        updateReelEditCounter('edit-reel-title', 'edit-reel-title-counter', 35);
        updateReelEditCounter('edit-reel-desc', 'edit-reel-desc-counter', 100);
        closeModal('myReels');
        openModal('editReelModal');
    }

    function updateReelEditCounter(inputId, counterId, max) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);
        if (!input || !counter) return;
        function refresh() {
            const remaining = max - input.value.length;
            counter.textContent = remaining + ' characters remaining';
            counter.style.color = remaining <= 5 ? 'var(--primary)' : remaining <= 15 ? '#ffcc00' : '#777';
        }
        input.removeEventListener('input', input._reelCounterFn);
        input._reelCounterFn = refresh;
        input.addEventListener('input', refresh);
        refresh();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const titleInput = document.getElementById('edit-reel-title');
        const descInput  = document.getElementById('edit-reel-desc');
        if (titleInput) titleInput.addEventListener('input', () => updateReelEditCounter('edit-reel-title', 'edit-reel-title-counter', 35));
        if (descInput)  descInput.addEventListener('input',  () => updateReelEditCounter('edit-reel-desc',  'edit-reel-desc-counter',  100));
    });

    // --- BUY HISTORY PAGINATION ---
    let currentBuyPage = 1;
    const buyItemsPerPage = 6;

    function updateBuyPagination() {
        const items = document.querySelectorAll('.buy-history-page-item');
        const totalPages = Math.ceil(items.length / buyItemsPerPage);
        const prevBtn = document.getElementById('prevBuyBtn');
        const nextBtn = document.getElementById('nextBuyBtn');
        const indicator = document.getElementById('buyPageIndicator');

        if (items.length === 0) {
            const controls = document.getElementById('buy-history-pagination-controls');
            if (controls) controls.style.display = 'none';
            return;
        }

        items.forEach(item => item.style.display = 'none');
        let start = (currentBuyPage - 1) * buyItemsPerPage;
        let end = start + buyItemsPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'block'; }

        indicator.innerText = `PAGE ${currentBuyPage} / ${totalPages}`;
        if(prevBtn) prevBtn.disabled = currentBuyPage === 1;
        if(nextBtn) nextBtn.disabled = currentBuyPage === totalPages || totalPages === 0;
    }

    function changeBuyPage(step) {
        currentBuyPage += step;
        updateBuyPagination();
    }

    // --- LISTING PAGINATION ---
    let currentListPage = 1;
    const listItemsPerPage = 6;

    function updateListingPagination() {
        const items = document.querySelectorAll('.listing-page-item');
        const totalPages = Math.ceil(items.length / listItemsPerPage);
        const prevBtn = document.getElementById('prevListBtn');
        const nextBtn = document.getElementById('nextListBtn');
        const indicator = document.getElementById('listingPageIndicator');

        if (items.length === 0) {
            if(document.getElementById('listing-pagination-controls')) document.getElementById('listing-pagination-controls').style.display = 'none';
            return;
        }

        items.forEach(item => item.style.display = 'none');
        let start = (currentListPage - 1) * listItemsPerPage;
        let end = start + listItemsPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'block'; }

        indicator.innerText = `PAGE ${currentListPage} / ${totalPages}`;
        if(prevBtn) prevBtn.disabled = currentListPage === 1;
        if(nextBtn) nextBtn.disabled = currentListPage === totalPages || totalPages === 0;
    }

    function changeListingPage(step) {
        currentListPage += step;
        updateListingPagination();
    }

    // --- MY REELS PAGINATION ---
    let currentMyReelsPage = 1;
    const myReelsPerPage = 4;

    function updateMyReelsPagination() {
        const items = document.querySelectorAll('.my-reel-page-item');
        const totalPages = Math.ceil(items.length / myReelsPerPage);
        const prevBtn = document.getElementById('prevReelBtn');
        const nextBtn = document.getElementById('nextReelBtn');
        const indicator = document.getElementById('myReelsPageIndicator');

        if (items.length === 0) {
            const controls = document.getElementById('my-reels-pagination-controls');
            if (controls) controls.style.display = 'none';
            return;
        }

        items.forEach(item => item.style.display = 'none');
        let start = (currentMyReelsPage - 1) * myReelsPerPage;
        let end = start + myReelsPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'flex'; }

        if (indicator) indicator.innerText = `PAGE ${currentMyReelsPage} / ${totalPages}`;
        if (prevBtn) prevBtn.disabled = currentMyReelsPage === 1;
        if (nextBtn) nextBtn.disabled = currentMyReelsPage === totalPages || totalPages === 0;
    }

    function changeMyReelsPage(step) {
        currentMyReelsPage += step;
        updateMyReelsPagination();
    }

    // --- MY QNA PAGINATION ---
    let currentMyQnaPage = 1;
    const myQnaPerPage = 4;

    function updateMyQnaPagination() {
        const items = document.querySelectorAll('#my-qna-list .my-qna-page-item');
        const totalPages = Math.ceil(items.length / myQnaPerPage);
        const prevBtn = document.getElementById('prevQnaBtn');
        const nextBtn = document.getElementById('nextQnaBtn');
        const indicator = document.getElementById('myQnaPageIndicator');

        if (items.length === 0) {
            const controls = document.getElementById('my-qna-pagination-controls');
            if (controls) controls.style.display = 'none';
            return;
        }

        items.forEach(item => item.style.display = 'none');
        let start = (currentMyQnaPage - 1) * myQnaPerPage;
        let end = start + myQnaPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'flex'; }

        if (indicator) indicator.innerText = `PAGE ${currentMyQnaPage} / ${totalPages}`;
        if (prevBtn) prevBtn.disabled = currentMyQnaPage === 1;
        if (nextBtn) nextBtn.disabled = currentMyQnaPage === totalPages || totalPages === 0;
    }

    function changeMyQnaPage(step) {
        currentMyQnaPage += step;
        updateMyQnaPagination();
    }

    // --- MY SHOUTOUT PAGINATION ---
    let currentMyShoutoutPage = 1;
    const myShoutoutPerPage = 4;

    function updateMyShoutoutPagination() {
        const items = document.querySelectorAll('#my-shoutout-list .my-qna-page-item');
        const totalPages = Math.ceil(items.length / myShoutoutPerPage);
        const prevBtn = document.getElementById('prevShoutoutBtn');
        const nextBtn = document.getElementById('nextShoutoutBtn');
        const indicator = document.getElementById('myShoutoutPageIndicator');

        if (items.length === 0) {
            const controls = document.getElementById('my-shoutout-pagination-controls');
            if (controls) controls.style.display = 'none';
            return;
        }

        items.forEach(item => item.style.display = 'none');
        let start = (currentMyShoutoutPage - 1) * myShoutoutPerPage;
        let end = start + myShoutoutPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'flex'; }

        if (indicator) indicator.innerText = `PAGE ${currentMyShoutoutPage} / ${totalPages}`;
        if (prevBtn) prevBtn.disabled = currentMyShoutoutPage === 1;
        if (nextBtn) nextBtn.disabled = currentMyShoutoutPage === totalPages || totalPages === 0;
    }

    function changeMyShoutoutPage(step) {
        currentMyShoutoutPage += step;
        updateMyShoutoutPagination();
    }

    // --- SUPPORT TICKET VIEW LOGIC ---
    function openTicketViewModal(ticketId) {
        document.getElementById('reply_t_id').value = ticketId;
        document.getElementById('u_t_id').innerText = ticketId;
        openModal('ticketViewModal');
        
        // Fetch ticket details via AJAX
        fetch('../admin_page/get-ticket.php?id=' + ticketId)
            .then(response => response.text())
            .then(html => {
                document.getElementById('u_ticket_content').innerHTML = html;
                // Remove admin action form from user view if it exists in the fetched HTML
                const adminForm = document.getElementById('u_ticket_content').querySelector('form');
                if(adminForm) adminForm.remove();
                const adminActionHeader = document.getElementById('u_ticket_content').querySelector('h4:last-of-type');
                if(adminActionHeader && adminActionHeader.innerText === 'ADMIN ACTION') adminActionHeader.remove();

                // Check ticket status for locking
                const statusDiv = document.getElementById('fetched_ticket_status');
                const userReplyForm = document.querySelector('#ticketViewModal form.admin-form');
                
                // Clear any old warning message if it exists
                const oldWarning = document.getElementById('ticketLockWarning');
                if (oldWarning) oldWarning.remove();

                if (statusDiv && userReplyForm) {
                    const status = statusDiv.innerText.trim();
                    if (status === 'New') {
                        userReplyForm.style.display = 'none';
                        
                        const warning = document.createElement('div');
                        warning.id = 'ticketLockWarning';
                        warning.className = 'admin-alert alert-error';
                        warning.style.marginTop = '0';
                        warning.style.backgroundColor = '#ffcc00'; // Yellow for waiting
                        warning.style.color = '#000';
                        warning.innerText = 'WAITING FOR ADMINISTRATOR RESPONSE. YOU CAN REPLY ONCE AN ADMINISTRATOR HAS RESPONDED TO THIS TICKET.';
                        
                        userReplyForm.parentNode.insertBefore(warning, userReplyForm);
                    } else if (status === 'Resolved' || status === 'Closed') {
                        userReplyForm.style.display = 'none';
                        
                        const warning = document.createElement('div');
                        warning.id = 'ticketLockWarning';
                        warning.className = 'admin-alert alert-error';
                        warning.style.marginTop = '0';
                        warning.innerText = status === 'Resolved' 
                            ? 'THIS TICKET HAS BEEN RESOLVED AND IS NO LONGER ACCEPTING NEW REPLIES.' 
                            : 'THIS TICKET HAS BEEN CLOSED BY THE ADMINISTRATION TEAM.';
                        
                        userReplyForm.parentNode.insertBefore(warning, userReplyForm);
                    } else {
                        userReplyForm.style.display = 'block';
                    }
                }
            });
    }

    function openDeleteTicketModal(ticketId) {
        document.getElementById('deleteTicketIdInput').value = ticketId;
        openModal('confirmDeleteTicketModal');
    }

    // --- NOTIFICATION LOGIC ---
    let currentNotifPage = 1;
    const itemsPerPage = 5;

    function updatePagination() {
        const items = document.querySelectorAll('.notif-page-item');
        const totalPages = Math.ceil(items.length / itemsPerPage);
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const indicator = document.getElementById('pageIndicator');
        if (items.length === 0) {
            if(document.getElementById('pagination-controls')) document.getElementById('pagination-controls').style.display = 'none';
            return;
        }
        items.forEach(item => item.style.display = 'none');
        let start = (currentNotifPage - 1) * itemsPerPage;
        let end = start + itemsPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'flex'; }
        indicator.innerText = `PAGE ${currentNotifPage} / ${totalPages}`;
        if(prevBtn) prevBtn.disabled = currentNotifPage === 1;
        if(nextBtn) nextBtn.disabled = currentNotifPage === totalPages;
    }

    function changePage(step) {
        currentNotifPage += step;
        updatePagination();
    }

    function openFullMessage(el) {
        const notifId = el.getAttribute('data-notif-id');
        document.getElementById('fullMessageDisplay').innerText = el.getAttribute('data-full-msg');
        document.getElementById('fullMessageDateDisplay').innerText = el.getAttribute('data-date');
        closeModal('notificationsModal');
        openModal('fullMessageModal');
        const parentItem = el.closest('.notification-item');
        if (parentItem.classList.contains('unread')) {
            fetch(`client-profile.php?ajax_read_notif=${notifId}`).then(() => {
                parentItem.classList.remove('unread');
                const badge = document.querySelector('.notification-badge-client');
                if (badge) {
                    let count = parseInt(badge.innerText);
                    if (count > 1) badge.innerText = count - 1; else badge.remove();
                }
            });
        }
    }

    function backToNotifications() { closeModal('fullMessageModal'); openModal('notificationsModal'); }

    function openEditReviewModal(id, rating, comment) {
        document.getElementById('edit_review_id').value = id;
        document.getElementById('edit_review_rating').value = rating;
        document.getElementById('edit_review_comment').value = comment;
        
        const stars = document.querySelectorAll('#edit-stars-container i');
        stars.forEach(star => {
            if (parseInt(star.dataset.rating) <= rating) {
                star.style.color = '#ff4b2b';
            } else {
                star.style.color = '#555';
            }
        });
        const commentCounter = document.getElementById('edit-review-counter');
        if (commentCounter) {
            const remaining = 100 - (comment ? comment.length : 0);
            commentCounter.textContent = remaining + ' characters remaining';
        }
        
        closeModal('myReviewsModal');
        openModal('editReviewModal');
    }

    function openEditSellerReviewModal(id, rating, comment) {
        document.getElementById('edit_seller_review_id').value = id;
        document.getElementById('edit_seller_review_rating').value = rating;
        document.getElementById('edit_seller_review_comment').value = comment;
        
        const stars = document.querySelectorAll('#edit-seller-stars-container i');
        stars.forEach(star => {
            if (parseInt(star.dataset.rating) <= rating) {
                star.style.color = 'var(--primary)';
            } else {
                star.style.color = '#555';
            }
        });
        const commentCounter = document.getElementById('edit-seller-review-counter');
        if (commentCounter) {
            const remaining = 100 - (comment ? comment.length : 0);
            commentCounter.textContent = remaining + ' characters remaining';
        }
        
        closeModal('myReviewsModal');
        openModal('editSellerReviewModal');
    }

    document.addEventListener("DOMContentLoaded", () => {
        const editStars = document.querySelectorAll('#edit-stars-container i');
        const editRatingInput = document.getElementById('edit_review_rating');
        if(editStars) {
            editStars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    editRatingInput.value = rating;
                    editStars.forEach(s => {
                        if (parseInt(s.dataset.rating) <= rating) {
                            s.style.color = '#ff4b2b';
                        } else {
                            s.style.color = '#555';
                        }
                    });
                });
            });
        }
        
        const editSellerStars = document.querySelectorAll('#edit-seller-stars-container i');
        const editSellerRatingInput = document.getElementById('edit_seller_review_rating');
        if(editSellerStars) {
            editSellerStars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    editSellerRatingInput.value = rating;
                    editSellerStars.forEach(s => {
                        if (parseInt(s.dataset.rating) <= rating) {
                            s.style.color = 'var(--primary)';
                        } else {
                            s.style.color = '#555';
                        }
                    });
                });
            });
        }

        const editCommentInput = document.getElementById('edit_review_comment');
        const editCommentCounter = document.getElementById('edit-review-counter');
        if (editCommentInput && editCommentCounter) {
            editCommentInput.addEventListener('input', function() {
                const remaining = 100 - this.value.length;
                editCommentCounter.textContent = remaining + ' characters remaining';
            });
        }
        
        const editSellerCommentInput = document.getElementById('edit_seller_review_comment');
        const editSellerCommentCounter = document.getElementById('edit-seller-review-counter');
        if (editSellerCommentInput && editSellerCommentCounter) {
            editSellerCommentInput.addEventListener('input', function() {
                const remaining = 100 - this.value.length;
                editSellerCommentCounter.textContent = remaining + ' characters remaining';
            });
        }

        const alertBox = document.getElementById('alert-box');
        if (alertBox) { setTimeout(() => { alertBox.style.opacity = "0"; setTimeout(() => alertBox.remove(), 500); }, 4000); }
        
        // Initialize all pagination systems
        if (typeof updatePagination === 'function') updatePagination();
        if (typeof updateMyReelsPagination === 'function') updateMyReelsPagination();
        if (typeof updateMyQnaPagination === 'function') updateMyQnaPagination();
        if (typeof updateMyShoutoutPagination === 'function') updateMyShoutoutPagination();
        if (typeof updateListingPagination === 'function') updateListingPagination();
        if (typeof updateBuyPagination === 'function') updateBuyPagination();
    });

    function openReviewSellerModal(sellerId, sellerName, transactionId) {
        document.getElementById('seller_review_id_input').value = sellerId;
        document.getElementById('seller_review_transaction_id').value = transactionId;
        document.getElementById('seller_review_name_display').innerText = '@' + sellerName;
        
        // Reset stars and input
        document.getElementById('seller_review_rating').value = 5;
        const stars = document.querySelectorAll('#seller-stars-container i');
        stars.forEach(s => s.style.color = 'var(--primary)');
        
        document.getElementById('seller_review_comment').value = '';
        const counter = document.getElementById('seller-review-counter');
        if (counter) counter.innerText = '100 characters remaining';
        
        openModal('reviewSellerModal');
    }
    
    // Star interaction for Seller Review Modal
    document.addEventListener("DOMContentLoaded", () => {
        const sellerStars = document.querySelectorAll('#seller-stars-container i');
        const sellerRatingInput = document.getElementById('seller_review_rating');
        if(sellerStars) {
            sellerStars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    sellerRatingInput.value = rating;
                    sellerStars.forEach(s => {
                        if (parseInt(s.dataset.rating) <= rating) {
                            s.style.color = 'var(--primary)';
                        } else {
                            s.style.color = '#555';
                        }
                    });
                });
            });
        }
        
        const sellerCommentInput = document.getElementById('seller_review_comment');
        const sellerCommentCounter = document.getElementById('seller-review-counter');
        if (sellerCommentInput && sellerCommentCounter) {
            sellerCommentInput.addEventListener('input', function() {
                const remaining = 100 - this.value.length;
                sellerCommentCounter.textContent = remaining + ' characters remaining';
            });
        }
    });
</script>

<!-- === SELLER REVIEW MODAL === -->
<div id="reviewSellerModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close-modal" onclick="closeModal('reviewSellerModal')">&times;</span>
        <h3 class="admin-table-h3">REVIEW <span class="header-span" id="seller_review_name_display">SELLER</span></h3>
        <form action="client-profile.php" method="POST" class="admin-form">
            <input type="hidden" name="seller_id" id="seller_review_id_input" value="">
            <input type="hidden" name="transaction_id" id="seller_review_transaction_id" value="">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">RATING (1-5)</label>
                <div id="seller-stars-container" style="font-size: 2rem; color: #555; cursor: pointer; text-align: left; margin: 10px 0;">
                    <i class="fa-solid fa-star" data-rating="1" style="color: var(--primary);"></i>
                    <i class="fa-solid fa-star" data-rating="2" style="color: var(--primary);"></i>
                    <i class="fa-solid fa-star" data-rating="3" style="color: var(--primary);"></i>
                    <i class="fa-solid fa-star" data-rating="4" style="color: var(--primary);"></i>
                    <i class="fa-solid fa-star" data-rating="5" style="color: var(--primary);"></i>
                </div>
                <input type="hidden" name="rating" id="seller_review_rating" value="5" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="seller_review_comment" style="display: block; font-weight: bold; margin-bottom: 5px;">COMMENT (OPTIONAL, MAX 100 CHARS)</label>
                <textarea name="comment" id="seller_review_comment" class="review-textarea" rows="3" maxlength="100" placeholder="Tell us about your experience..."></textarea>
                <div id="seller-review-counter" style="font-family:'Staatliches',sans-serif; font-size:0.85rem; color:#777; text-align:right; margin-top:0.5rem; letter-spacing:1px;">100 characters remaining</div>
            </div>
            
            <button type="submit" name="submit_seller_review" class="btn-primary-brutal btn-full" style="margin-top: 15px;">SUBMIT REVIEW</button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>