<?php
session_start();
include '../db.php';
require_once '../notification-service.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$new_email = '';
$old_email = '';
$user_id = 0;

if (empty($token)) {
    $error = "INVALID OR MISSING TOKEN.";
} else {
    // Check if token exists and is not expired
    $stmt = $conn->prepare("
        SELECT e.user_id, e.new_email, e.expires_at, u.email as old_email 
        FROM email_change_requests e 
        JOIN users u ON e.user_id = u.id 
        WHERE e.token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (strtotime($row['expires_at']) < time()) {
            $error = "THIS VERIFICATION LINK HAS EXPIRED. PLEASE REQUEST A NEW ONE.";
            $del = $conn->prepare("DELETE FROM email_change_requests WHERE token = ?");
            $del->bind_param("s", $token);
            $del->execute();
        } else {
            $new_email = $row['new_email'];
            $old_email = $row['old_email'];
            $user_id = $row['user_id'];
        }
    } else {
        $error = "INVALID OR ALREADY USED TOKEN.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email'])) {
    $current_password = $_POST['current_password'] ?? '';
    
    if (!$error && $user_id > 0) {
        // Verify current password
        $pwd_stmt = $conn->prepare("SELECT email, password FROM users WHERE id = ?");
        $pwd_stmt->bind_param("i", $user_id);
        $pwd_stmt->execute();
        $user_data = $pwd_stmt->get_result()->fetch_assoc();
        
        if (password_verify($current_password, $user_data['password'])) {
            // Update email
            $update = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $update->bind_param("si", $new_email, $user_id);
            if ($update->execute()) {
                // Delete request
                $del = $conn->prepare("DELETE FROM email_change_requests WHERE token = ?");
                $del->bind_param("s", $token);
                $del->execute();
                
                // Send confirmation emails
                $subject = "SkateShop - Email Change Confirmation";
                
                $old_body = buildEmailTemplate("Email Address Changed", "<p>Your SkateShop account email has been successfully changed from this address to <strong>" . htmlspecialchars($new_email) . "</strong>.</p><p>If you did not authorize this, please contact support immediately.</p>");
                sendEmail($old_email, $subject, $old_body);
                
                $new_body = buildEmailTemplate("Email Address Confirmed", "<p>Your SkateShop account email has been successfully updated to this address.</p>");
                sendEmail($new_email, $subject, $new_body);
                
                // Remove pending notifications
                $msg_like = "Email change request to%";
                $del_notif = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND message LIKE ?");
                $del_notif->bind_param("is", $user_id, $msg_like);
                $del_notif->execute();
                
                // Push a notification to the new dashboard
                sendAppNotification($conn, $user_id, "Your email was successfully changed to " . htmlspecialchars($new_email) . ".", "general");
                
                $success = "EMAIL SUCCESSFULLY UPDATED.";
            } else {
                $error = "DATABASE ERROR OCCURRED.";
            }
        } else {
            $error = "INCORRECT CURRENT PASSWORD.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email Change - SkateShop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Staatliches&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>
<body style="background-color: var(--textwhite); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: 'Staatliches', sans-serif; font-weight: normal;">

<main class="container" style="display: flex; justify-content: center; width: 100%;">
    <div class="grainy-card" style="width: 100%; max-width: 500px; padding: 3rem; background: #fff; border: 5px solid #000; box-shadow: 12px 12px 0 #000; font-weight: normal;">
        
        <div style="text-align: center; margin-bottom: 2rem;">
            <i class="fa-solid fa-lock" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem; text-shadow: 3px 3px 0 #000;"></i>
            <h2 style="font-size: 3.5rem; text-align: center; margin: 0; color: #000; line-height: 1; letter-spacing: 2px;">SECURITY</h2>
            <h2 style="font-size: 3.5rem; text-align: center; margin: 0; color: var(--primary); line-height: 1; letter-spacing: 2px;">CHECK</h2>
        </div>

        <?php if ($error): ?>
            <div class="admin-alert alert-error" style="display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; font-weight: normal;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <a href="client-profile.php" class="btn btn-primary" style="display: block; text-align: center; width: 100%; font-size: 1.2rem; text-decoration: none; font-weight: normal;">RETURN TO DASHBOARD</a>
            
        <?php elseif ($success): ?>
            <div class="admin-alert alert-success" style="display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; font-weight: normal;">
                <i class="fa-solid fa-circle-check" style="font-size: 1.5rem;"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <a href="client-profile.php" class="btn btn-primary" style="display: block; text-align: center; width: 100%; font-size: 1.2rem; text-decoration: none; font-weight: normal;">RETURN TO DASHBOARD</a>

        <?php else: ?>
            <p style="font-size: 1.3rem; color: #222; margin-bottom: 1.5rem; text-align: center; letter-spacing: 1px; font-weight: normal;">YOU ARE AUTHORIZING AN EMAIL CHANGE FOR YOUR ACCOUNT.</p>
            
            <div style="background: #f4f4f4; border: 3px solid #000; padding: 1.5rem; margin-bottom: 2rem;">
                <div style="margin-bottom: 1rem;">
                    <span style="font-size: 1.1rem; color: #555; display: block; letter-spacing: 1px; font-weight: normal;">CURRENT EMAIL</span>
                    <span style="color: #000; word-break: break-all; font-size: 1.4rem; letter-spacing: 1px; font-weight: normal;"><?php echo htmlspecialchars($old_email); ?></span>
                </div>
                <div>
                    <span style="font-size: 1.1rem; color: var(--primary); display: block; letter-spacing: 1px; font-weight: normal;">NEW EMAIL</span>
                    <span style="color: #000; word-break: break-all; font-size: 1.4rem; letter-spacing: 1px; font-weight: normal;"><?php echo htmlspecialchars($new_email); ?></span>
                </div>
            </div>
            
            <p style="font-size: 1.2rem; color: #444; margin-bottom: 1.5rem; text-align: center; letter-spacing: 1px; font-weight: normal;">TO COMPLETE THIS ACTION, CONFIRM YOUR CURRENT PASSWORD BELOW.</p>
            
            <form method="POST" class="admin-form">
                <label style="font-family: 'Staatliches', sans-serif; font-size: 1.3rem; letter-spacing: 1px; font-weight: normal;">CURRENT PASSWORD <span style="color:var(--primary);">*</span></label>
                <input type="password" name="current_password" required style="font-family: 'Inter', sans-serif; font-size: 1.2rem; padding: 12px; border: 3px solid #000; box-shadow: 4px 4px 0 #000; margin-bottom: 1.5rem; outline: none; transition: all 0.2s; font-weight: normal;" onfocus="this.style.boxShadow='0 0 0 #000'; this.style.transform='translate(4px, 4px)';" onblur="this.style.boxShadow='4px 4px 0 #000'; this.style.transform='translate(0, 0)';">
                
                <button type="submit" name="verify_email" class="btn btn-primary" style="width: 100%; font-size: 1.8rem; padding: 1rem; display: flex; justify-content: center; align-items: center; gap: 10px; letter-spacing: 2px; text-transform: uppercase; font-weight: normal;">
                    <i class="fa-solid fa-check"></i> VERIFY & UPDATE
                </button>
            </form>
            
            <div style="margin-top: 2rem; text-align: center;">
                <a href="client-profile.php" style="color: #555; font-size: 1.2rem; text-decoration: underline; text-underline-offset: 4px; letter-spacing: 1px; transition: color 0.2s;" onmouseover="this.style.color='#000'" onmouseout="this.style.color='#555'">CANCEL & RETURN TO DASHBOARD</a>
            </div>
        <?php endif; ?>
        
    </div>
</main>

</body>
</html>
