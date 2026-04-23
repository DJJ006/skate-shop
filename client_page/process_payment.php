<?php
session_start();
include '../db.php';

if (!isset($_GET['order_id'])) {
    die("Invalid Request.");
}

$order_id = (int)$_GET['order_id'];

// 1. Fetch the Pending Order
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND status = 'PENDING_PAYMENT'");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order already processed or not found.");
}

// 2. Manage Inventory (UPDATED LOGIC)
$prod_id = $order['product_id'];

if ($order['seller_id'] > 0) {
    // Marketplace Item: Mark as completely sold out (1-of-1)
    $conn->query("UPDATE products SET is_sold = 1 WHERE id = $prod_id");
} else {
    // Official Shop Item: Subtract 1 from quantity
    // GREATEST(0, ...) ensures quantity never goes below zero
    $conn->query("UPDATE products SET quantity = GREATEST(0, quantity - 1) WHERE id = $prod_id");
}

// 3. Mark Order as PAID
$conn->query("UPDATE orders SET status = 'PAID' WHERE id = $order_id");

// 4. DISTRIBUTE FUNDS
if ($order['seller_id'] > 0) {
    // It's a marketplace item. Credit the Seller's Wallet!
    $seller_id = $order['seller_id'];
    $amount = $order['amount'];
    
    // Platform fee (5% cut)
    $platform_fee = $amount * 0.05;
    $seller_payout = $amount - $platform_fee;

    $wallet_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $wallet_stmt->bind_param("di", $seller_payout, $seller_id);
    $wallet_stmt->execute();

    // Notify the Seller
    $msg = "Cha-ching! Your gear sold for $" . number_format($amount, 2) . ". $" . number_format($seller_payout, 2) . " has been added to your Wallet.";
    $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notif->bind_param("is", $seller_id, $msg);
    $notif->execute();
}

// 5. Notify the Buyer
$msg_buyer = "Order Confirmed! Your gear is getting boxed up and shipped out.";
$notif_buyer = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
$notif_buyer->bind_param("is", $order['buyer_id'], $msg_buyer);
$notif_buyer->execute();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SkateShop | ORDER CONFIRMED</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="container" style="padding-top: 150px; text-align: center; min-height: 70vh;">
    <i class="fa-solid fa-circle-check" style="font-size: 5rem; color: #2ecc71; margin-bottom: 20px;"></i>
    <h1 class="glitch-text" style="font-size: 3rem;">PAYMENT <span class="text-primary">SUCCESSFUL</span></h1>
    <p style="color: #ccc; font-size: 1.2rem; margin-bottom: 40px;">Your order has been secured. Shred on.</p>
    
    <div style="display: flex; justify-content: center; gap: 20px;">
        <a href="shop.php" class="btn btn-primary" style="text-decoration: none;">BACK TO SHOP</a>
        <a href="client-profile.php" class="btn btn-outline" style="text-decoration: none;">VIEW DASHBOARD</a>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>