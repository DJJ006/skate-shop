<?php
session_start();
include '../db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "PASSWORDS DO NOT MATCH.";
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "USERNAME OR EMAIL ALREADY TAKEN.";
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($insert_stmt->execute()) {
                $success = "ACCOUNT CREATED. YOU CAN NOW LOGIN.";
            } else {
                $error = "ERROR CREATING ACCOUNT.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SkateShop | REGISTER</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/admin.css"> <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container" style="display: flex; justify-content: center; margin-top: 5rem; min-height: 60vh;">
        <div class="grainy-card" style="width: 100%; max-width: 500px; padding: 2.5rem;">
            <h2 class="glitch-text-admin" style="font-size: 3rem; text-align: center;">JOIN <span class="text-primary">CREW</span></h2>
            
            <?php if ($error): ?>
                <div class="admin-alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="admin-alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="admin-form">
                <label>USERNAME</label>
                <input type="text" name="username" required>
                
                <label>EMAIL</label>
                <input type="email" name="email" required>
                
                <label>PASSWORD</label>
                <input type="password" name="password" required>
                
                <label>CONFIRM PASSWORD</label>
                <input type="password" name="confirm_password" required>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;">REGISTER</button>
                
                <p style="text-align: center; margin-top: 1.5rem; font-family: 'Staatliches', sans-serif; font-size: 1.1rem;">
                    ALREADY HAVE AN ACCOUNT? <a href="login.php" style="color: var(--primary);">LOGIN HERE</a>
                </p>
            </form>
        </div>
    </main>
</body>
</html>