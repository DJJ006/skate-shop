<?php
session_start();
include '../db.php';
require_once '../notification-service.php';

// Guest support
$is_guest = !isset($_SESSION['user_id']);
$user_id = $is_guest ? null : $_SESSION['user_id'];

// Ensure tables exist
$conn->query("
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    reported_username VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    attachment_url VARCHAR(255) DEFAULT NULL,
    status ENUM('New', 'Open', 'In Progress', 'Resolved', 'Closed') DEFAULT 'New',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("
CREATE TABLE IF NOT EXISTS support_ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender_type ENUM('admin', 'user') NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
)");

$msg = '';
$msg_type = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['msg']);
    unset($_SESSION['msg_type']);
}

// Pre-fill user data
$u_data = null;
if (!$is_guest) {
    $u_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_data = $u_stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    if ($is_guest) {
        $_SESSION['msg'] = "YOU MUST BE LOGGED IN TO SUBMIT A SUPPORT TICKET.";
        $_SESSION['msg_type'] = "error";
        header("Location: contact-us.php");
        exit();
    }

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $subject = trim($_POST['subject']);
    $category = trim($_POST['category']);
    $message = trim($_POST['message']);
    
    
    // Validate required fields
    if (empty($full_name) || empty($email) || empty($phone) || empty($subject) || empty($category) || empty($message)) {
        $_SESSION['msg'] = "PLEASE FILL ALL REQUIRED FIELDS.";
        $_SESSION['msg_type'] = "error";
        header("Location: contact-us.php");
        exit();
    }
    
    // Length validations
    if (mb_strlen($full_name) > 50 || mb_strlen($subject) > 100 || mb_strlen($message) > 250) {
        $_SESSION['msg'] = "INPUT EXCEEDS MAXIMUM ALLOWED LENGTH.";
        $_SESSION['msg_type'] = "error";
        header("Location: contact-us.php");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $_SESSION['msg'] = "INVALID EMAIL FORMAT.";
        $_SESSION['msg_type'] = "error";
        header("Location: contact-us.php");
        exit();
    }
    
    if (!preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $phone) || preg_match('/[a-zA-Z]/', $phone)) {
        $_SESSION['msg'] = "INVALID PHONE NUMBER FORMAT.";
        $_SESSION['msg_type'] = "error";
        header("Location: contact-us.php");
        exit();
    }
    $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, full_name, email, phone, subject, category, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $user_id, $full_name, $email, $phone, $subject, $category, $message);
    
    if ($stmt->execute()) {
        $ticket_id = $conn->insert_id;
        
        // Notify User
        $notif_msg = "Your support ticket #{$ticket_id} ('{$subject}') has been received. Our team will review it shortly.";
        sendAppNotification($conn, $user_id, $notif_msg);
        
        $_SESSION['msg'] = "TICKET SUBMITTED SUCCESSFULLY! WE WILL BE IN TOUCH.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "FAILED TO SUBMIT TICKET. PLEASE TRY AGAIN.";
        $_SESSION['msg_type'] = "error";
    }
    header("Location: contact-us.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | CONTACT US</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .contact-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 2.5fr;
            gap: 30px;
        }
        .contact-info {
            background: #fff;
            border: 4px solid var(--charcoal);
            box-shadow: 8px 8px 0px var(--primary);
            padding: 30px;
            height: fit-content;
        }
        .contact-form-card {
            background: #fff;
            border: 4px solid var(--charcoal);
            box-shadow: 8px 8px 0px var(--charcoal);
            padding: 40px;
        }
        .info-item {
            margin-bottom: 25px;
        }
        .info-item h4 {
            font-family: 'Staatliches', sans-serif;
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 5px;
        }
        .info-item p {
            font-family: 'Inter', sans-serif;
            color: #333;
            font-weight: 600;
            font-size: 1.2rem;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-row > div {
            flex: 1;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            font-family: 'Staatliches', sans-serif;
            letter-spacing: 1px;
            border: 3px solid #000;
            text-align: center;
        }
        .alert-success { background: #2ecc71; color: #fff; }
        .alert-error { background: var(--primary); color: #fff; }
        
        @media(max-width: 768px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
    <script src="../assets/script.js" defer></script>
</head>
<body>

<?php include 'header.php'; ?>

<main class="contact-container">
    <div class="section-header">
        <h3>CONTACT <span class="header-span">US</span></h3>
        <p style="color: var(--charcoal); font-size: 1.5rem; font-weight: 600; margin: 0; padding-bottom: 5px;">REPORT AN ISSUE, ASK A QUESTION, OR GET SUPPORT.</p>
    </div>
    
    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="contact-grid">
        <div class="contact-info">
            <div class="info-item">
                <h4><i class="fa-solid fa-headset"></i> SKATESHOP SUPPORT</h4>
                <p>Available 24/7 for critical issues.</p>
            </div>
            <div class="info-item">
                <h4><i class="fa-solid fa-envelope"></i> EMAIL US</h4>
                <p></p>
                <p><a href="mailto:skateshopp2026@gmail.com" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 1.2rem;">skateshopp2026@gmail.com</a></p>
            </div>
            <div class="info-item">
                <h4><i class="fa-solid fa-phone"></i> CALL US</h4>
                <p>+371 20 000 000</p>
            </div>
            <div class="info-item">
                <h4><i class="fa-solid fa-location-dot"></i> HEADQUARTERS</h4>
                <p>Liepāja, Latvia</p>
            </div>
        </div>

        <div class="contact-form-card">
            <form action="contact-us.php" method="POST" enctype="multipart/form-data" class="admin-form">
                <div class="form-row">
                    <div>
                        <label>FULL NAME *</label>
                        <input type="text" name="full_name" id="contact-name" maxlength="50" required <?php echo $is_guest ? 'disabled' : ''; ?>>
                        <p id="contact-name-counter" style="text-align: right; font-size: 0.8rem; margin-top: 5px; font-family: 'Inter', sans-serif; color: #777;">50 characters remaining</p>
                        <p id="name-error" style="color: var(--primary); font-size: 0.85rem; font-family: 'Inter', sans-serif; display: none; margin-top: 5px; font-weight: 600;"></p>
                    </div>
                    <div>
                        <label>EMAIL ADDRESS *</label>
                        <input type="email" name="email" id="contact-email" value="<?php echo htmlspecialchars($u_data['email'] ?? ''); ?>" required <?php echo $is_guest ? 'disabled' : ''; ?>>
                        <p id="email-error" style="color: var(--primary); font-size: 0.85rem; font-family: 'Inter', sans-serif; display: none; margin-top: 5px; font-weight: 600;"></p>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label>PHONE NUMBER *</label>
                        <input type="tel" name="phone" id="contact-phone" required placeholder="+371..." <?php echo $is_guest ? 'disabled' : ''; ?>>
                        <p id="phone-error" style="color: var(--primary); font-size: 0.85rem; font-family: 'Inter', sans-serif; display: none; margin-top: 5px; font-weight: 600;"></p>
                    </div>
                    <div>
                        <label>CATEGORY *</label>
                        <select name="category" required <?php echo $is_guest ? 'disabled' : ''; ?>>
                            <option value="" disabled selected>SELECT A CATEGORY...</option>
                            <option value="Report a User">Report a User</option>
                            <option value="Report Inappropriate Content">Report Inappropriate Content</option>
                            <option value="Website Bug / Technical Problem">Website Bug / Technical Problem</option>
                            <option value="Payment Issue">Payment Issue</option>
                            <option value="Account Issue">Account Issue</option>
                            <option value="Feature Request">Feature Request</option>
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label>SUBJECT *</label>
                    <input type="text" name="subject" id="contact-subject" maxlength="100" required <?php echo $is_guest ? 'disabled' : ''; ?>>
                    <p id="contact-subject-counter" style="text-align: right; font-size: 0.8rem; margin-top: 5px; font-family: 'Inter', sans-serif; color: #777;">100 characters remaining</p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label>DETAILED MESSAGE *</label>
                    <textarea name="message" id="contact-message" rows="5" required maxlength="250" placeholder="Describe your issue or request in detail... (max 250 characters)" <?php echo $is_guest ? 'disabled' : ''; ?>></textarea>
                    <p id="contact-message-counter" style="text-align: right; font-size: 0.8rem; margin-top: 5px; font-family: 'Inter', sans-serif; color: #777;">250 characters remaining</p>
                </div>

                <?php if ($is_guest): ?>
                    <div style="text-align: center; margin-top: 20px; padding: 15px; border: 2px solid var(--primary); background: #fff0f0;">
                        <p style="font-family: 'Staatliches', sans-serif; font-size: 1.5rem; color: var(--primary); margin-bottom: 10px;">YOU MUST BE LOGGED IN TO SUBMIT A SUPPORT REQUEST.</p>
                        <a href="login.php" class="btn btn-primary" style="text-decoration: none; display: inline-block;">LOG IN OR SIGN UP</a>
                    </div>
                <?php else: ?>
                    <button type="submit" name="submit_ticket" class="btn-primary-brutal" style="width: 100%; font-size: 1.5rem; padding: 15px;">SUBMIT TICKET</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    function setupCounter(inputId, counterId, max) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);
        if (!input || !counter) return;

        const refresh = () => {
            const currentLength = input.value.length;
            const remaining = max - currentLength;
            counter.textContent = remaining + ' characters remaining';
            counter.style.color = remaining <= (max * 0.2) ? 'var(--primary)' : remaining <= (max * 0.4) ? '#ffcc00' : '#777';
        };

        refresh();
        input.addEventListener('input', refresh);
    }

    setupCounter("contact-message", "contact-message-counter", 250);
    setupCounter("contact-name", "contact-name-counter", 50);
    setupCounter("contact-subject", "contact-subject-counter", 100);

    const form = document.querySelector(".admin-form");
    const emailInput = document.getElementById("contact-email");
    const phoneInput = document.getElementById("contact-phone");
    const nameInput = document.getElementById("contact-name");

    const emailError = document.getElementById("email-error");
    const phoneError = document.getElementById("phone-error");
    const nameError = document.getElementById("name-error");

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const phoneRegex = /^\+?[0-9\s\-\(\)]{7,20}$/;

    function validateEmail() {
        if (!emailInput) return true;
        if (!emailRegex.test(emailInput.value)) {
            emailError.textContent = "Please enter a valid email address.";
            emailError.style.display = "block";
            return false;
        }
        emailError.style.display = "none";
        return true;
    }

    function validatePhone() {
        if (!phoneInput) return true;
        if (!phoneRegex.test(phoneInput.value) || /[a-zA-Z]/.test(phoneInput.value)) {
            phoneError.textContent = "Please enter a valid phone number (e.g. +371...).";
            phoneError.style.display = "block";
            return false;
        }
        phoneError.style.display = "none";
        return true;
    }

    function validateName() {
        if (!nameInput) return true;
        if (nameInput.value.trim().length === 0) {
            nameError.textContent = "Name cannot be empty.";
            nameError.style.display = "block";
            return false;
        }
        nameError.style.display = "none";
        return true;
    }

    if (emailInput) emailInput.addEventListener("input", validateEmail);
    if (phoneInput) phoneInput.addEventListener("input", validatePhone);
    if (nameInput) nameInput.addEventListener("input", validateName);

    if (form) {
        form.addEventListener("submit", function(e) {
            const isEmailValid = validateEmail();
            const isPhoneValid = validatePhone();
            const isNameValid = validateName();

            if (!isEmailValid || !isPhoneValid || !isNameValid) {
                e.preventDefault();
            }
        });
    }
});
</script>

</body>
</html>

