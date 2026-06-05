<?php
#Savienojuma izveide ar datubāzi:
$host = "localhost";
$username = "grobina1_jaunarajs";
$password = 'Nej$v3Hw0J7t';
$database = "grobina1_jaunarajs";

$conn = mysqli_connect($host, $username, $password, $database);

if(!$conn){
    die("Nav izveidots savienojums: " . mysqli_connect_error());
}else{
    #echo "Ir izveidots savienojums ar datubāzi";
}

require_once __DIR__ . '/notification-service.php';

if (!function_exists('sendSellerPayoutNotification')) {
    function sendSellerPayoutNotification($conn, $seller_id, $product_title, $amount, $order_id, $seller_payout, $shipping_info, $del_notes) {
        $purchase_code = "ORD-" . str_pad($order_id, 6, "0", STR_PAD_LEFT);
        $seller_msg = "Cha-ching! Your gear '{$product_title}' sold for $" . number_format($amount, 2) . "!\nOrder Code: {$purchase_code}\nShip to: {$shipping_info}\nDelivery Notes: {$del_notes}\nThe payout of $" . number_format($seller_payout, 2) . " is currently held in escrow and will be released to your wallet once the buyer confirms delivery, or automatically after 10 days.";
        sendAppNotification($conn, $seller_id, $seller_msg);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $check_user_id = $_SESSION['user_id'];
    
    $current_page = basename($_SERVER['PHP_SELF']);
    $exempt_pages = ['login.php', 'register.php'];
    
    if (!in_array($current_page, $exempt_pages)) {
        
        
        $stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ?");
        $stmt->bind_param("i", $check_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (isset($row['is_blocked']) && $row['is_blocked'] == 1) {
                
                
                session_unset();
                session_destroy();
                
                
                
                exit();
            }
        }
    }
}
?>