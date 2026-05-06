<?php
session_start();
include '../db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_email = trim($_POST['username_email']);
    $password = $_POST['password'];

    // 1. Added is_blocked to the SELECT statement
    $stmt = $conn->prepare("SELECT id, username, password, is_blocked FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username_email, $username_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Verify password hash
        if (password_verify($password, $row['password'])) {
            
            // 2. Check if the account is banned before letting them in
            if (isset($row['is_blocked']) && $row['is_blocked'] == 1) {
                $error = "ACCESS DENIED. YOUR ACCOUNT HAS BEEN SUSPENDED.";
            } else {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];

                // Restore cart from database
                $cart_stmt = $conn->prepare("SELECT cart_data FROM users WHERE id = ?");
                $cart_stmt->bind_param("i", $row['id']);
                $cart_stmt->execute();
                $cart_result = $cart_stmt->get_result()->fetch_assoc();
                if (!empty($cart_result['cart_data'])) {
                    $restored_cart = json_decode($cart_result['cart_data'], true);
                    if (is_array($restored_cart)) {
                        $_SESSION['cart'] = $restored_cart;
                    }
                } else {
                    $_SESSION['cart'] = [];
                }

                header("Location: index.php");
                exit();
            }
            
        } else {
            $error = "INCORRECT PASSWORD/USER NAME.";
        }
    } else {
        $error = "INCORRECT PASSWORD/USER NAME.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SkateShop | LOGIN</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container" style="display: flex; justify-content: center; margin-top: 5rem; min-height: 60vh;">
        <div class="grainy-card" style="width: 100%; max-width: 500px; padding: 2.5rem;">
            <h2 class="glitch-text-admin" style="font-size: 3rem; text-align: center;">LOG<span class="text-primary">IN</span></h2>
            
            <?php if ($error): ?>
                <div class="admin-alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="admin-form">
                <label>USERNAME OR EMAIL</label>
                <input type="text" name="username_email" required>
                
                <label>PASSWORD</label>
                <input type="password" name="password" required>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;">ENTER THE VAULT</button>
                
                <p style="text-align: center; margin-top: 1.5rem; font-family: 'Staatliches', sans-serif; font-size: 1.1rem;">
                    NEW HERE? <a href="register.php" style="color: var(--primary);">CREATE ACCOUNT</a>
                </p>
            </form>
        </div>
    </main>
</body>
</html>