<?php
session_start();
include '../db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$msg = '';
$msg_type = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['msg']);
    unset($_SESSION['msg_type']);
}


$stmt = $conn->prepare("SELECT email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$list_stmt = $conn->prepare("SELECT id, title, price, image_url FROM products WHERE seller_id = ? AND is_marketplace = 1");
$list_stmt->bind_param("i", $user_id);
$list_stmt->execute();
$user_listings = $list_stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del_stmt->bind_param("i", $user_id);
    if ($del_stmt->execute()) {
        session_destroy();
        header("Location: ../index.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $update_query_parts = [];
    $types = "";
    $params = [];

    if (!empty($new_password)) {
        if ($new_password === $confirm_password) {
            $update_query_parts[] = "password = ?";
            $types .= "s";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        } else {
            $_SESSION['msg'] = "PASSWORDS DO NOT MATCH.";
            $_SESSION['msg_type'] = "error";
            header("Location: client-profile.php");
            exit();
        }
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "../assets/uploads/avatars/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $new_filename = 'user_' . $user_id . '_' . time() . '.' . pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . $new_filename;
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $update_query_parts[] = "profile_pic = ?";
            $types .= "s";
            $params[] = $target_file;
        }
    }

    if (!empty($update_query_parts)) {
        $sql = "UPDATE users SET " . implode(", ", $update_query_parts) . " WHERE id = ?";
        $types .= "i"; $params[] = $user_id;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $_SESSION['msg'] = "PROFILE UPDATED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: client-profile.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_market_item'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $brand = $conn->real_escape_string($_POST['brand']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $condition = $conn->real_escape_string($_POST['condition_badge']);
    $description = $conn->real_escape_string($_POST['description']);
    
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0){
        $target_file = "../assets/uploads/" . uniqid('market_') . '.' . pathinfo($_FILES["item_image"]["name"], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO products (title, brand, price, category, condition_badge, description, image_url, is_marketplace, seller_id, seller_name) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
            $stmt->bind_param("ssdssssis", $title, $brand, $price, $category, $condition, $description, $target_file, $user_id, $username);
            $stmt->execute();
            $_SESSION['msg'] = "GEAR PUBLISHED!";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: client-profile.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | DASHBOARD</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/admin.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        
        .option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 3rem 0;
        }
        .option-card {
            background: var(--charcoal);
            border: 2px solid #333;
            padding: 2.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .option-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            background: #1a1a1a;
        }
        .option-card i { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; }
        .option-card h3 { font-family: 'Staatliches', sans-serif; font-size: 1.8rem; letter-spacing: 1px; }

        
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 20px;
        }
        .modal-content {
            background: #111;
            border: 2px solid var(--primary);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            padding: 2.5rem;
            box-shadow: 0 0 30px rgba(255,102,0,0.2);
        }
        .close-modal {
            position: absolute;
            top: 15px; right: 20px;
            font-size: 2rem;
            color: var(--textwhite);
            cursor: pointer;
        }
        .close-modal:hover { color: var(--primary); }

        .mini-listing {
            display: flex;
            align-items: center;
            gap: 15px;
            background: #1a1a1a;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
        }
        .mini-listing img { width: 60px; height: 60px; object-fit: cover; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div style="margin-top: 3rem; text-align: center;">
        <h2 class="glitch-text-admin">USER <span class="text-primary">DASHBOARD</span></h2>
        <p class="admin-text-shop">WELCOME BACK, @<?php echo $username; ?></p>
    </div>

    <?php if ($msg): ?>
        <div id="alert-box" class="admin-alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="option-grid">
        <div class="option-card grainy-card" onclick="openModal('editProfile')">
            <i class="fa-solid fa-user-gear"></i>
            <h3>EDIT PROFILE</h3>
        </div>
        <div class="option-card grainy-card" onclick="openModal('sellGear')">
            <i class="fa-solid fa- skateboard"></i>
            <h3>SELL GEAR</h3>
        </div>
        <div class="option-card grainy-card" onclick="openModal('myListings')">
            <i class="fa-solid fa-list-ul"></i>
            <h3>MY LISTINGS</h3>
        </div>
        <div class="option-card grainy-card" onclick="openModal('buyHistory')">
            <i class="fa-solid fa-history"></i>
            <h3>BUY HISTORY</h3>
        </div>
        <div class="option-card grainy-card" style="border-color: #500;" onclick="openModal('deleteProfile')">
            <i class="fa-solid fa-trash-can" style="color: #ff4444;"></i>
            <h3 style="color: #ff4444;">DELETE ACCOUNT</h3>
        </div>
    </div>
</main>

<div id="editProfile" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editProfile')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">PROFILE</span></h3>
        <form action="client-profile.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <img src="<?php echo htmlspecialchars($user_data['profile_pic'] ? $user_data['profile_pic'] : '../assets/images/default-avatar.png'); ?>" style="width:120px; height:120px; border-radius:50%; border:3px solid var(--primary); object-fit:cover;">
            </div>
            <label>CHANGE AVATAR</label>
            <input type="file" name="profile_pic" accept="image/*">
            <label>NEW PASSWORD</label>
            <input type="password" name="new_password">
            <label>CONFIRM PASSWORD</label>
            <input type="password" name="confirm_password">
            <button type="submit" name="update_profile" class="btn btn-primary" style="width:100%;">SAVE CHANGES</button>
        </form>
    </div>
</div>

<div id="sellGear" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('sellGear')">&times;</span>
        <h3 class="admin-table-h3">SELL <span class="header-span">GEAR</span></h3>
        <form action="client-profile.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div><label>TITLE</label><input type="text" name="title" required></div>
                <div><label>BRAND</label><input type="text" name="brand" required></div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div><label>PRICE ($)</label><input type="number" name="price" step="0.01" required></div>
                <div><label>CATEGORY</label><select name="category"><?php /* Options here */ ?><option>Decks</option><option>Trucks</option><option>Wheels</option></select></div>
                <div><label>CONDITION</label><select name="condition_badge"><option>MINT / WALL HANGER</option><option>LIGHTLY SCUFFED</option><option>BEAT UP / SKATEABLE</option></select></div>
            </div>
            <label>DESCRIPTION</label><textarea name="description" rows="3" required></textarea>
            <label>IMAGE</label><input type="file" name="item_image" required>
            <button type="submit" name="add_market_item" class="btn btn-primary" style="width:100%;">PUBLISH LISTING</button>
        </form>
    </div>
</div>

<div id="myListings" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('myListings')">&times;</span>
        <h3 class="admin-table-h3">ACTIVE <span class="header-span">LISTINGS</span></h3>
        <?php if($user_listings->num_rows > 0): ?>
            <?php while($row = $user_listings->fetch_assoc()): ?>
                <div class="mini-listing">
                    <img src="<?php echo $row['image_url']; ?>">
                    <div style="flex-grow:1;">
                        <h4 style="margin:0;"><?php echo $row['title']; ?></h4>
                        <p style="margin:0; color:var(--primary);">$<?php echo $row['price']; ?></p>
                    </div>
                    <span style="font-family:Staatliches; color:#666;">ID: #<?php echo $row['id']; ?></span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>You haven't listed anything yet.</p>
        <?php endif; ?>
    </div>
</div>

<div id="buyHistory" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('buyHistory')">&times;</span>
        <h3 class="admin-table-h3">BUY <span class="header-span">HISTORY</span></h3>
        <p style="text-align:center; color:#666; padding: 2rem;">Purchasing functionality coming in the next update. Shred on.</p>
    </div>
</div>

<div id="deleteProfile" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('deleteProfile')">&times;</span>
        <h3 style="color: #ff4444; font-family: 'Staatliches'; font-size: 2rem;">ARE YOU SURE?</h3>
        <p>This action is permanent. All your listings will be deleted from the Street Market.</p>
        <form action="client-profile.php" method="POST">
            <button type="submit" name="delete_account" class="btn" style="background:#ff4444; color:white; width:100%; margin-top:1rem;">YES, DELETE EVERYTHING</button>
            <button type="button" onclick="closeModal('deleteProfile')" class="btn" style="background:#333; width:100%; margin-top:0.5rem;">CANCEL</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    // Close on outside click
    window.onclick = function(event) {
        if (event.target.className === 'modal-overlay') {
            event.target.style.display = 'none';
        }
    }

    // Alert Auto-hide
    document.addEventListener("DOMContentLoaded", () => {
        const alertBox = document.getElementById('alert-box');
        if (alertBox) {
            setTimeout(() => {
                alertBox.style.opacity = "0";
                setTimeout(() => alertBox.remove(), 500);
            }, 4000);
        }
    });
</script>

</body>
</html>