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
if (isset($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}

$success = '';
if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['register_error'] = "INVALID SECURITY TOKEN. PLEASE TRY AGAIN.";
        header("Location: register.php");
        exit();
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $ip = $_SERVER['REMOTE_ADDR'];

    $lockout_time = checkRateLimit($conn, $ip);
    if ($lockout_time > 0) {
        $mins = floor($lockout_time / 60);
        $secs = $lockout_time % 60;
        $_SESSION['register_error'] = "Too many failed attempts. For security reasons, your IP has been temporarily locked. Please try again in {$mins} minutes and {$secs} seconds.";
        header("Location: register.php");
        exit();
    } else {
        if (!preg_match('/^[a-zA-Z0-9_]{3,25}$/', $username)) {
            recordFailedLogin($conn, $ip, $email);
            $_SESSION['register_error'] = "USERNAME MUST BE 3-25 CHARACTERS (ALPHANUMERIC & UNDERSCORES ONLY).";
            header("Location: register.php");
            exit();
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            recordFailedLogin($conn, $ip, $email);
            $_SESSION['register_error'] = "INVALID EMAIL FORMAT.";
            header("Location: register.php");
            exit();
        } elseif (strlen($password) < 8) {
            recordFailedLogin($conn, $ip, $email);
            $_SESSION['register_error'] = "PASSWORD MUST BE AT LEAST 8 CHARACTERS LONG.";
            header("Location: register.php");
            exit();
        } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            recordFailedLogin($conn, $ip, $email);
            $_SESSION['register_error'] = "PASSWORD MUST CONTAIN AT LEAST ONE SPECIAL CHARACTER.";
            header("Location: register.php");
            exit();
        } elseif ($password !== $confirm_password) {
            recordFailedLogin($conn, $ip, $email);
            $_SESSION['register_error'] = "PASSWORDS DO NOT MATCH.";
            header("Location: register.php");
            exit();
        } else {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                recordFailedLogin($conn, $ip, $email);
                $_SESSION['register_error'] = "USERNAME OR EMAIL ALREADY TAKEN.";
                header("Location: register.php");
                exit();
            } else {
                // Hash password and insert
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $insert_stmt->bind_param("sss", $username, $email, $hashed_password);
                
                if ($insert_stmt->execute()) {
                    resetRateLimit($conn, $ip);
                    $_SESSION['register_success'] = "ACCOUNT CREATED. YOU CAN NOW LOGIN.";
                    header("Location: register.php");
                    exit();
                } else {
                    recordFailedLogin($conn, $ip, $email);
                    $_SESSION['register_error'] = "ERROR CREATING ACCOUNT.";
                    header("Location: register.php");
                    exit();
                }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .validation-panel { background: var(--textwhite); border: 2px solid var(--charcoal); padding: 15px; margin-bottom: 20px; font-family: 'Inter', sans-serif; font-size: 0.9rem; }
        .validation-item { margin-bottom: 8px; display: flex; align-items: center; gap: 10px; color: #d32f2f; }
        .validation-item i { width: 16px; }
        .validation-item.valid { color: #2e7d32; }
        .char-counter { font-size: 0.8rem; color: #666; text-align: right; margin-top: -10px; margin-bottom: 10px; font-family: 'Inter', sans-serif; }
        button:disabled { background-color: #ccc !important; border-color: #999 !important; color: #666 !important; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
    </style>
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body style="background-color: #f7f7f7; background-image: linear-gradient(45deg, transparent 50%, rgba(0,0,0,0.03) 50%), linear-gradient(135deg, rgba(0,0,0,0.03) 50%, transparent 50%); background-size: 10px 10px; background-position: 0 0, 5px 0;">
    <?php include 'header.php'; ?>

    <main class="container" style="display: flex; justify-content: center; margin-top: 5rem; min-height: 60vh;">
        <div class="grainy-card auth-card" style="width: 100%; max-width: 500px;">
            <h2 class="glitch-text-admin" style="font-size: 3rem; text-align: center;">JOIN <span class="text-primary">CREW</span></h2>
            
            <?php if ($error): ?>
                <div class="admin-alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="admin-alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label>USERNAME</label>
                <input type="text" name="username" id="register-username" maxlength="25" required>
                <p id="register-username-counter" style="text-align: right; font-size: 0.8rem; margin-top: 5px; font-family: 'Inter', sans-serif; color: #777;">25 characters remaining</p>
                
                <label>EMAIL</label>
                <input type="email" name="email" pattern="[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$" title="Please enter a valid email address (e.g., user@example.com)" required>
                
                <label>PASSWORD</label>
                <input type="password" name="password" id="reg_password" maxlength="50" required>
                <div class="char-counter"><span id="charCount">0</span> / 50 characters</div>
                
                <label>CONFIRM PASSWORD</label>
                <input type="password" name="confirm_password" id="reg_confirm_password" maxlength="50" required>
                
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
                
                <button type="submit" id="reg_submitBtn" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;" disabled>REGISTER</button>
                
                <p style="text-align: center; margin-top: 1.5rem; font-family: 'Staatliches', sans-serif; font-size: 1.1rem;">
                    ALREADY HAVE AN ACCOUNT? <a href="login.php" style="color: var(--primary);">LOGIN HERE</a>
                </p>
            </form>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('register-username');
        const counter = document.getElementById('register-username-counter');
        if (input && counter) {
            const update = () => {
                const remaining = 25 - input.value.length;
                counter.textContent = remaining + ' characters remaining';
                if (remaining <= 5) counter.style.color = 'var(--primary)';
                else if (remaining <= 10) counter.style.color = '#e6b800';
                else counter.style.color = '#777';
            };
            update();
            input.addEventListener('input', update);
        }

        const pwd = document.getElementById('reg_password');
        const confirmPwd = document.getElementById('reg_confirm_password');
        const charCount = document.getElementById('charCount');
        const submitBtn = document.getElementById('reg_submitBtn');

        const reqLength = document.getElementById('req-length');
        const reqSpecial = document.getElementById('req-special');
        const reqMatch = document.getElementById('req-match');

        function updateValidation() {
            const val = pwd.value;
            const confVal = confirmPwd.value;

            charCount.textContent = val.length;

            let isLength = val.length >= 8;
            toggleValid(reqLength, isLength);

            let isSpecial = /[^a-zA-Z0-9]/.test(val);
            toggleValid(reqSpecial, isSpecial);

            let isMatch = val.length > 0 && val === confVal;
            toggleValid(reqMatch, isMatch);

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

        if (pwd && confirmPwd) {
            pwd.addEventListener('input', updateValidation);
            confirmPwd.addEventListener('input', updateValidation);
            updateValidation();
        }
    });
    </script>
</body>
</html>
