<?php
require_once 'admin_auth.php';
$current_page = 'edit-profile.php';

$msg = '';
$msg_type = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

// Fetch current user data
$u_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$u_stmt->bind_param("i", $_SESSION['admin_id']);
$u_stmt->execute();
$admin_data = $u_stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];

    // Verify current password first
    $pass_check = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $pass_check->bind_param("i", $_SESSION['admin_id']);
    $pass_check->execute();
    $hash = $pass_check->get_result()->fetch_assoc()['password'];
    
    if (!password_verify($current_password, $hash)) {
        $_SESSION['msg'] = "INCORRECT CURRENT PASSWORD. CHANGES NOT SAVED.";
        $_SESSION['msg_type'] = "error";
    } else {
        // Check uniqueness if username or email changed
        $unique_check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $unique_check->bind_param("ssi", $new_username, $new_email, $_SESSION['admin_id']);
        $unique_check->execute();
        if ($unique_check->get_result()->num_rows > 0) {
            $_SESSION['msg'] = "USERNAME OR EMAIL IS ALREADY TAKEN BY ANOTHER USER.";
            $_SESSION['msg_type'] = "error";
        } else {
            if (mb_strlen($new_username) > 25) {
                $_SESSION['msg'] = "USERNAME CANNOT EXCEED 25 CHARACTERS.";
                $_SESSION['msg_type'] = "error";
                header("Location: edit-profile.php");
                exit();
            }
            if (mb_strlen($new_email) > 100) {
                $_SESSION['msg'] = "EMAIL CANNOT EXCEED 100 CHARACTERS.";
                $_SESSION['msg_type'] = "error";
                header("Location: edit-profile.php");
                exit();
            }
            // Update profile
            $upd_sql = "UPDATE users SET username = ?, email = ?";
            $params = [$new_username, $new_email];
            $types = "ss";

            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $_SESSION['msg'] = "NEW PASSWORD MUST BE AT LEAST 6 CHARACTERS.";
                    $_SESSION['msg_type'] = "error";
                    header("Location: edit-profile.php");
                    exit();
                }
                $upd_sql .= ", password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                $types .= "s";
            }

            $params[] = $_SESSION['admin_id'];
            $types .= "i";
            
            $upd_sql .= " WHERE id = ?";
            $upd_stmt = $conn->prepare($upd_sql);
            $upd_stmt->bind_param($types, ...$params);
            
            if ($upd_stmt->execute()) {
                $_SESSION['admin_username'] = $new_username; // Update admin session for the header
                $_SESSION['msg'] = "PROFILE UPDATED SUCCESSFULLY.";
                $_SESSION['msg_type'] = "success";
            }
        }
    }
    header("Location: edit-profile.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | MY PROFILE</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="../assets/admin-script.js" defer></script>
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>

<?php require __DIR__ . '/admin_header.php'; ?>

<section class="admin-layout container">
    <div style="margin-top: 47px;">
        <?php include 'admin_sidebar.php'; ?>
    </div>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">MY <span class="text-primary">PROFILE</span></h2>
                <p class="admin-text-shop">UPDATE YOUR ADMINISTRATOR CREDENTIALS</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="admin-alert alert-<?php echo $msg_type; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="grainy-card" style="padding: 30px; max-width: 600px; margin: 0 auto; border-color: var(--primary); border-width: 4px; box-shadow: 8px 8px 0px var(--primary);">
            <form method="POST" action="edit-profile.php" class="admin-form">
                <label>USERNAME</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($admin_data['username']); ?>" required minlength="3" maxlength="25">
                
                <label>EMAIL ADDRESS</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required maxlength="100">
                
                <label>NEW PASSWORD <span style="color:#777; font-size: 0.8rem;">(Leave blank to keep current password)</span></label>
                <input type="password" name="new_password" minlength="6" maxlength="100">
                
                <hr style="border: 2px solid #000; margin: 30px 0;">
                
                <p style="font-family: 'Staatliches', sans-serif; color: var(--primary); font-size: 1.2rem; margin-bottom: 10px;">AUTHORIZATION REQUIRED</p>
                <label>CURRENT PASSWORD *</label>
                <input type="password" name="current_password" required placeholder="Enter your current password to save changes">
                
                <button type="submit" name="update_profile" class="btn-primary-brutal" style="width: 100%; margin-top: 20px; font-size: 1.5rem; padding: 15px;">SAVE CHANGES</button>
            </form>
        </div>
    </main>
</section>

</body>
</html>

