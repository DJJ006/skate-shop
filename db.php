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

try {
    $conn->query("CREATE TABLE IF NOT EXISTS login_rate_limits (
        ip_address VARCHAR(45) PRIMARY KEY,
        failed_attempts INT DEFAULT 0,
        lockout_until INT DEFAULT NULL
    )");
    $conn->query("CREATE TABLE IF NOT EXISTS login_lockout_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        email_attempted VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { /* ignore */ }

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
                echo "<!DOCTYPE html><html><head><title>Account Blocked</title><meta name='viewport' content='width=device-width, initial-scale=1.0'><style>body{background:#1a1a1a;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;} .box{border:4px solid #E11D48;padding:40px;background:#2d2d2d;box-shadow:8px 8px 0px #000;max-width:500px;} h1{color:#E11D48;margin-top:0;} a{color:#fff;text-decoration:underline;}</style></head><body><div class='box'><h1>ACCOUNT BLOCKED</h1><p>Your account has been suspended by an administrator.</p><p><a href='#' onclick='window.history.back(); return false;'>Go Back</a></p></div></body></html>";
                exit();
            }
        }
    }
}
?>