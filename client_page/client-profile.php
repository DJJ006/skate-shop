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
    
    $del_listings = $conn->prepare("DELETE FROM products WHERE seller_id = ?");
    $del_listings->bind_param("i", $user_id);
    $del_listings->execute();

    $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del_stmt->bind_param("i", $user_id);
    if ($del_stmt->execute()) {
        session_destroy();
        header("Location: ../client_page/index.php");
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
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div class="dashboard-header">
        <h2 class="glitch-text-admin">USER <span class="text-primary">DASHBOARD</span></h2>
        <p class="admin-text-shop">WELCOME BACK, @<?php echo $username; ?></p>
    </div>

    <?php if ($msg): ?>
        <div id="alert-box" class="admin-alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="option-grid">
        <div class="option-card" onclick="openModal('editProfile')">
            <i class="fa-solid fa-user-gear"></i>
            <h3>EDIT PROFILE</h3>
        </div>
        <div class="option-card" onclick="openModal('sellGear')">
            <i class="fas fa-money-bill-alt"></i>
            <h3>SELL GEAR</h3>
        </div>
        <div class="option-card" onclick="openModal('myListings')">
            <i class="fa-solid fa-list-ul"></i>
            <h3>MY LISTINGS</h3>
        </div>
        <div class="option-card" onclick="openModal('buyHistory')">
            <i class="fa-solid fa-history"></i>
            <h3>BUY HISTORY</h3>
        </div>
        <div class="option-card card-danger" onclick="openModal('deleteProfile')">
            <i class="fa-solid fa-trash-can"></i>
            <h3>DELETE ACCOUNT</h3>
        </div>
    </div>
</main>

<div id="editProfile" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editProfile')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">PROFILE</span></h3>
        
        <form action="client-profile.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="avatar-edit-preview">
                <img src="<?php echo htmlspecialchars($user_data['profile_pic'] ? $user_data['profile_pic'] : '../assets/images/default-avatar.png'); ?>" class="avatar-edit-img">
            </div>
            
            <label>CHANGE AVATAR</label>
            <input type="file" name="profile_pic" accept="image/*">
            
            <label>NEW PASSWORD</label>
            <input type="password" name="new_password" placeholder="LEAVE BLANK TO KEEP CURRENT">
            
            <label>CONFIRM PASSWORD</label>
            <input type="password" name="confirm_password" placeholder="REPEAT NEW PASSWORD">
            
            <button type="submit" name="update_profile" class="btn-primary-brutal btn-full">SAVE CHANGES</button>
        </form>
    </div>
</div>

<div id="sellGear" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('sellGear')">&times;</span>
        <h3 class="admin-table-h3">SELL <span class="header-span">GEAR</span></h3>
        
        <form action="client-profile.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="form-grid-2-1">
                <div><label>TITLE</label><input type="text" name="title" placeholder="E.G. INDEPENDENT 149" required></div>
                <div><label>BRAND</label><input type="text" name="brand" placeholder="E.G. INDY" required></div>
            </div>
            
            <div class="form-grid-3">
                <div><label>PRICE ($)</label><input type="number" name="price" step="0.01" placeholder="0.00" required></div>
                <div>
                    <label>CATEGORY</label>
                    <select name="category">
                        <option>Decks</option>
                        <option>Trucks</option>
                        <option>Wheels</option>
                    </select>
                </div>
                <div>
                    <label>CONDITION</label>
                    <select name="condition_badge">
                        <option>MINT / WALL HANGER</option>
                        <option>LIGHTLY SCUFFED</option>
                        <option>BEAT UP / SKATEABLE</option>
                    </select>
                </div>
            </div>
            
            <label>DESCRIPTION</label>
            <textarea name="description" rows="3" placeholder="DESCRIBE YOUR GEAR..." required></textarea>
            
            <label>IMAGE</label>
            <input type="file" name="item_image" required>
            
            <button type="submit" name="add_market_item" class="btn-primary-brutal btn-full">PUBLISH LISTING</button>
        </form>
    </div>
</div>

<div id="myListings" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('myListings')">&times;</span>
        <h3 class="admin-table-h3">ACTIVE <span class="header-span">LISTINGS</span></h3>
        
        <?php if($user_listings->num_rows > 0): ?>
            <div class="listings-grid">
                <?php while($row = $user_listings->fetch_assoc()): ?>
                    <div class="mini-listing">
                        <img src="<?php echo $row['image_url']; ?>" alt="Listing Image">
                        <div class="mini-listing-info">
                            <h4 class="mini-listing-title"><?php echo strtoupper($row['title']); ?></h4>
                            <p class="mini-listing-price">$<?php echo $row['price']; ?></p>
                        </div>
                        <span class="mini-listing-id">ID: #<?php echo $row['id']; ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">YOU HAVEN'T LISTED ANYTHING YET.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="buyHistory" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('buyHistory')">&times;</span>
        <h3 class="admin-table-h3">BUY <span class="header-span">HISTORY</span></h3>
        <div class="empty-placeholder-box">
            <p class="empty-placeholder-title">STREET MARKET UNDER CONSTRUCTION</p>
            <p class="empty-placeholder-text">Purchasing functionality coming in the next update. Shred on.</p>
        </div>
    </div>
</div>

<div id="deleteProfile" class="modal-overlay">
    <div class="modal-content modal-danger-dialog">
        <span class="close-modal" onclick="closeModal('deleteProfile')">&times;</span>
        <h3 class="admin-table-h3">ARE <span class="header-span">YOU</span> SURE?</h3>
        <p class="modal-danger-text">This action is permanent. All your listings will be dragged to the graveyard.</p>
        
        <form action="client-profile.php" method="POST">
            <button type="submit" name="delete_account" class="btn-danger btn-full btn-space-bottom">YES, DELETE EVERYTHING</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    window.onclick = function(event) {
        if (event.target.className === 'modal-overlay') {
            event.target.style.display = 'none';
        }
    }

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

<?php include 'footer.php'; ?>

</body>
</html>