<?php
session_start();
include '../db.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';
$message_type = ''; 

// Schema Migration (if columns don't exist)
try {
    $conn->query("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL");
    $conn->query("ALTER TABLE users ADD COLUMN reset_token_expires DATETIME DEFAULT NULL");
} catch (Exception $e) { /* Ignore if already exists */ }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Always show the same generic message for security
    $message = "IF AN ACCOUNT WITH THIS EMAIL EXISTS, A PASSWORD RESET LINK HAS BEEN SENT.";
    $message_type = "success";

    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save hash to DB
            $upd = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            if ($upd) {
                $upd->bind_param("ssi", $token_hash, $expires, $user['id']);
                $upd->execute();
                $upd->close();

                // Send Email via notification-service.php
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $reset_link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . urlencode($token);
                
                $first_name = !empty($user['first_name']) ? htmlspecialchars($user['first_name']) : "Skater";

                $email_content = "
                    <p>What's up, {$first_name}?</p>
                    <p>You recently requested to reset your password for your SkateShop account.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$reset_link}' style='display: inline-block; background-color: #ff4b4b; color: #ffffff; padding: 15px 30px; text-decoration: none; font-weight: bold; border: 4px solid #212121; text-transform: uppercase;'>RESET PASSWORD</a>
                    </div>
                    <p>Or copy and paste this link into your browser:<br>
                    <a href='{$reset_link}'>{$reset_link}</a></p>
                    <hr style='border: 1px dashed #212121; margin: 30px 0;'>
                    <p style='font-size: 12px; color: #555;'>If you did not request this password reset, you can safely ignore this email. No changes will be made to your account unless you complete the password reset process.</p>
                ";

                $html_body = buildEmailTemplate("PASSWORD RESET", $email_content);
                sendEmail($email, "SkateShop Password Reset", $html_body);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SkateShop | FORGOT PASSWORD</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container" style="display: flex; justify-content: center; margin-top: 5rem; min-height: 60vh; margin-bottom: 5rem">
        <div class="grainy-card" style="width: 100%; max-width: 500px; padding: 2.5rem;">
            <h2 class="glitch-text-admin" style="font-size: 2.5rem; text-align: center;">FORGOT <span class="text-primary">PASSWORD?</span></h2>
            <p style="text-align:center; font-family:'Inter', sans-serif; margin-bottom: 20px; color:#555;">ENTER YOUR REGISTERED EMAIL ADDRESS TO RECEIVE A RESET LINK.</p>
            
            <?php if ($message): ?>
                <div class="admin-alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="forgot-password.php" method="POST" class="admin-form">
                <label>EMAIL ADDRESS</label>
                <input type="email" name="email" required placeholder="skater@example.com">
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;">SEND RESET LINK</button>
                
                <p style="text-align: center; margin-top: 1.5rem; font-family: 'Staatliches', sans-serif; font-size: 1.1rem;">
                    REMEMBERED IT? <a href="login.php" style="color: var(--primary);">BACK TO LOGIN</a>
                </p>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
