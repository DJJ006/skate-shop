<?php
session_start();
include '../db.php';

// Save cart to DB before destroying session so it persists after next login
if (!empty($_SESSION['user_id']) && isset($_SESSION['cart'])) {
    $user_id = (int)$_SESSION['user_id'];
    $cart_json = json_encode($_SESSION['cart']);
    $save_stmt = $conn->prepare("UPDATE users SET cart_data = ? WHERE id = ?");
    if ($save_stmt) {
        $save_stmt->bind_param("si", $cart_json, $user_id);
        $save_stmt->execute();
    }
}

session_unset();
session_destroy();
header("Location: index.php");
exit();
?>