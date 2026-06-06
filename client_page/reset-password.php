<?php
session_start();
include '../db.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$user_id = null;

if (empty($token)) {
    $error = "INVALID OR MISSING RESET TOKEN.";
} else {
    $token_hash = hash('sha256', $token);
    
    // Check if token is valid and not expired
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    if ($stmt) {
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            $user_id = $user['id'];
        } else {
            $error = "INVALID OR EXPIRED RESET TOKEN. PLEASE REQUEST A NEW ONE.";
        }
    } else {
        $error = "SYSTEM ERROR. PLEASE TRY AGAIN LATER.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $user_id) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Server-side validation just in case JS is bypassed
    if (strlen($new_password) < 8) {
        $error = "PASSWORD MUST BE AT LEAST 8 CHARACTERS LONG.";
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
        $error = "PASSWORD MUST CONTAIN AT LEAST ONE SPECIAL CHARACTER.";
    } elseif ($new_password !== $confirm_password) {
        $error = "PASSWORDS DO NOT MATCH.";
    } else {
        // Hash and save
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        if ($upd) {
            $upd->bind_param("si", $hashed_password, $user_id);
            if ($upd->execute()) {
                $success = "PASSWORD SUCCESSFULLY UPDATED.";
                // Redirect to login after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $error = "FAILED TO UPDATE PASSWORD.";
            }
            $upd->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SkateShop | RESET PASSWORD</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .validation-panel {
            background: var(--textwhite);
            border: 2px solid var(--charcoal);
            padding: 15px;
            margin-bottom: 20px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }
        .validation-item {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #d32f2f; /* Red by default */
        }
        .validation-item i {
            width: 16px;
        }
        .validation-item.valid {
            color: #2e7d32; /* Green when valid */
        }
        .char-counter {
            font-size: 0.8rem;
            color: #666;
            text-align: right;
            margin-top: -10px;
            margin-bottom: 10px;
            font-family: 'Inter', sans-serif;
        }
        
        button:disabled {
            background-color: #ccc !important;
            border-color: #999 !important;
            color: #666 !important;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
    </style>
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container" style="display: flex; justify-content: center; margin-top: 5rem; min-height: 60vh; margin-bottom: 5rem">
        <div class="grainy-card" style="width: 100%; max-width: 500px; padding: 2.5rem;">
            <h2 class="glitch-text-admin" style="font-size: 2.5rem; text-align: center;">RESET <span class="text-primary">PASSWORD</span></h2>
            
            <?php if ($error): ?>
                <div class="admin-alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php if (!$user_id): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="forgot-password.php" class="btn btn-primary" style="padding: 10px 20px;">REQUEST NEW LINK</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="admin-alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <p style="text-align:center; font-family:'Inter', sans-serif;">Redirecting to login...</p>
            <?php elseif ($user_id): ?>
                <p style="text-align:center; font-family:'Inter', sans-serif; margin-bottom: 20px; color:#555;">CREATE A NEW, STRONG PASSWORD FOR YOUR ACCOUNT.</p>

                <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" class="admin-form" id="resetForm">
                    <label>NEW PASSWORD</label>
                    <input type="password" name="password" id="new_password" required maxlength="50">
                    <div class="char-counter"><span id="charCount">0</span> / 50 characters</div>
                    
                    <label>CONFIRM NEW PASSWORD</label>
                    <input type="password" name="confirm_password" id="confirm_password" required maxlength="50">
                    
                    <div class="validation-panel">
                        <div class="validation-item" id="req-length">
                            <i class="fa-solid fa-xmark"></i> Minimum 8 characters
                        </div>
                        <div class="validation-item" id="req-special">
                            <i class="fa-solid fa-xmark"></i> Contains a special character
                        </div>
                        <div class="validation-item" id="req-match">
                            <i class="fa-solid fa-xmark"></i> Passwords match
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;" disabled>UPDATE PASSWORD</button>
                </form>
                
                <script>
                    const pwd = document.getElementById('new_password');
                    const confirmPwd = document.getElementById('confirm_password');
                    const charCount = document.getElementById('charCount');
                    const submitBtn = document.getElementById('submitBtn');

                    const reqLength = document.getElementById('req-length');
                    const reqSpecial = document.getElementById('req-special');
                    const reqMatch = document.getElementById('req-match');

                    function updateValidation() {
                        const val = pwd.value;
                        const confVal = confirmPwd.value;

                        // Char counter
                        charCount.textContent = val.length;

                        // Length check
                        let isLength = val.length >= 8;
                        toggleValid(reqLength, isLength);

                        // Special char check
                        let isSpecial = /[^a-zA-Z0-9]/.test(val);
                        toggleValid(reqSpecial, isSpecial);

                        // Match check
                        let isMatch = val.length > 0 && val === confVal;
                        toggleValid(reqMatch, isMatch);

                        // Submit toggle
                        submitBtn.disabled = !(isLength && isSpecial && isMatch);
                    }

                    function toggleValid(element, isValid) {
                        const icon = element.querySelector('i');
                        if (isValid) {
                            element.classList.add('valid');
                            icon.classList.remove('fa-xmark');
                            icon.classList.add('fa-check');
                        } else {
                            element.classList.remove('valid');
                            icon.classList.remove('fa-check');
                            icon.classList.add('fa-xmark');
                        }
                    }

                    pwd.addEventListener('input', updateValidation);
                    confirmPwd.addEventListener('input', updateValidation);
                </script>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>

