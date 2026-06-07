<?php
session_start();
include '../db.php';
include '../rate_limit.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['login_error'] = "INVALID SECURITY TOKEN. PLEASE TRY AGAIN.";
        header("Location: login.php");
        exit();
    }

    $username_email = trim($_POST['username_email']);
    $password = $_POST['password'];
    $ip = $_SERVER['REMOTE_ADDR'];

    $lockout_time = checkRateLimit($conn, $ip);
    if ($lockout_time > 0) {
        $mins = floor($lockout_time / 60);
        $secs = $lockout_time % 60;
        $_SESSION['login_error'] = "Too many failed login attempts. For security reasons, your account has been temporarily locked. Please try again in {$mins} minutes and {$secs} seconds.";
        header("Location: login.php");
        exit();
    } else {
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
                    $_SESSION['login_error'] = "ACCESS DENIED. YOUR ACCOUNT HAS BEEN SUSPENDED.";
                    header("Location: login.php");
                    exit();
                } else {
                    resetRateLimit($conn, $ip);
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
                recordFailedLogin($conn, $ip, $username_email);
                $_SESSION['login_error'] = "INVALID EMAIL OR PASSWORD.";
                header("Location: login.php");
                exit();
            }
        } else {
            recordFailedLogin($conn, $ip, $username_email);
            $_SESSION['login_error'] = "INVALID EMAIL OR PASSWORD.";
            header("Location: login.php");
            exit();
        }
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
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body style="background-color: #f7f7f7; background-image: linear-gradient(45deg, transparent 50%, rgba(0,0,0,0.03) 50%), linear-gradient(135deg, rgba(0,0,0,0.03) 50%, transparent 50%); background-size: 10px 10px; background-position: 0 0, 5px 0;">
    <?php include 'header.php'; ?>

    <main class="container" style="display: flex; justify-content: center; margin-top: 5rem; min-height: 60vh;">
        <div class="grainy-card auth-card" style="width: 100%; max-width: 500px;">
            <h2 class="glitch-text-admin" style="font-size: 3rem; text-align: center;">LOG<span class="text-primary">IN</span></h2>
            
            <?php if ($error): ?>
                <div class="admin-alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label>USERNAME OR EMAIL</label>
                <input type="text" name="username_email" required>
                
                <label>PASSWORD</label>
                <input type="password" name="password" required>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;">ENTER THE VAULT</button>
                
                <p style="text-align: center; margin-top: 1.5rem; font-family: 'Staatliches', sans-serif; font-size: 1.1rem;">
                    <a href="forgot-password.php" style="color: var(--primary); text-decoration: none;">FORGOT PASSWORD?</a>
                </p>

                <p style="text-align: center; margin-top: 0.5rem; font-family: 'Staatliches', sans-serif; font-size: 1.1rem;">
                    NEW HERE? <a href="register.php" style="color: var(--primary);">CREATE ACCOUNT</a>
                </p>
            </form>
        </div>
    </main>
</body>
</html>
