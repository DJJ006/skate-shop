<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php';
include '../rate_limit.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
if (isset($_SESSION['admin_login_error'])) {
    $error = $_SESSION['admin_login_error'];
    unset($_SESSION['admin_login_error']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['admin_login_error'] = "INVALID SECURITY TOKEN. PLEASE TRY AGAIN.";
        header("Location: admin-login.php");
        exit();
    }

    $username_email = trim($_POST['username_email']);
    $password = $_POST['password'];
    $ip = $_SERVER['REMOTE_ADDR'];

    $lockout_time = checkRateLimit($conn, $ip);
    if ($lockout_time > 0) {
        $mins = floor($lockout_time / 60);
        $secs = $lockout_time % 60;
        $_SESSION['admin_login_error'] = "Too many failed login attempts. For security reasons, your account has been temporarily locked. Please try again in {$mins} minutes and {$secs} seconds.";
        header("Location: admin-login.php");
        exit();
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, is_blocked, role FROM users WHERE (username = ? OR email = ?) AND role = 'admin'");
        $stmt->bind_param("ss", $username_email, $username_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                if ($row['is_blocked'] == 1) {
                    $_SESSION['admin_login_error'] = "ACCESS DENIED. YOUR ADMIN ACCOUNT IS SUSPENDED.";
                    header("Location: admin-login.php");
                    exit();
                } else {
                    resetRateLimit($conn, $ip);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $row['id'];
                    
                    // Update last_login
                    $update = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                    $update->bind_param("i", $row['id']);
                    $update->execute();

                    header("Location: index.php");
                    exit();
                }
            } else {
                recordFailedLogin($conn, $ip, $username_email);
                $_SESSION['admin_login_error'] = "INVALID EMAIL OR PASSWORD.";
                header("Location: admin-login.php");
                exit();
            }
        } else {
            recordFailedLogin($conn, $ip, $username_email);
            $_SESSION['admin_login_error'] = "INVALID EMAIL OR PASSWORD.";
            header("Location: admin-login.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | ADMIN LOGIN</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body style="background: var(--bg-dark); display: flex; align-items: center; justify-content: center; min-height: 100vh;">

<div class="grainy-card" style="width: 100%; max-width: 500px; padding: 40px; border: 4px solid var(--primary); background: #000; color: #fff;">
    <h2 class="glitch-text" style="font-size: 3rem; text-align: center; margin-bottom: 10px; color: #fff;">ADMIN <span class="text-primary">PORTAL</span></h2>
    <p style="text-align: center; font-family: 'Inter', sans-serif; color: #aaa; margin-bottom: 30px;">AUTHORIZED PERSONNEL ONLY</p>

    <?php if ($error): ?>
        <div class="alert alert-error" style="border: 2px solid var(--primary); background: rgba(225, 29, 72, 0.1); color: var(--primary); padding: 20px; margin-bottom: 25px; text-align: center; font-weight: bold;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="admin-login.php" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <label style="color: #fff;">USERNAME OR EMAIL</label>
        <input type="text" name="username_email" required style="background: #222; color: #fff; border: 2px solid #555;">

        <label style="color: #fff;">PASSWORD</label>
        <input type="password" name="password" required style="background: #222; color: #fff; border: 2px solid #555;">

        <button type="submit" class="btn-primary-brutal" style="width: 100%; padding: 15px; font-size: 1.5rem; margin-top: 20px;">AUTHENTICATE</button>
    </form>

    <div style="text-align: center; margin-top: 20px;">
        <a href="../client_page/login.php" style="color: #666; text-decoration: none; font-family: 'Staatliches', sans-serif; font-size: 1.2rem;">&larr; BACK TO PUBLIC LOGIN</a>
    </div>
</div>

</body>
</html>

