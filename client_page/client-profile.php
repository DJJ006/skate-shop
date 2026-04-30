<?php
session_start();
include '../db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- AJAX: FETCH SINGLE PRODUCT FOR EDIT ---
if (isset($_GET['get_listing_details'])) {
    $p_id = (int)$_GET['get_listing_details'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $p_id, $user_id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc());
    exit();
}

// --- AJAX: MARK NOTIF AS READ ---
if (isset($_GET['ajax_read_notif'])) {
    $notif_id = (int)$_GET['ajax_read_notif'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    echo $stmt->execute() ? "success" : "error";
    exit();
}

// Clear all notifications
if (isset($_GET['clear_notifs'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    header("Location: client-profile.php");
    exit();
}

// Delete single notification
if (isset($_GET['delete_notif'])) {
    $notif_id = (int)$_GET['delete_notif'];
    $del_notif_stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $del_notif_stmt->bind_param("ii", $notif_id, $user_id);
    $del_notif_stmt->execute();
    header("Location: client-profile.php");
    exit();
}

$msg = '';
$msg_type = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['msg']);
    unset($_SESSION['msg_type']);
}

// Fetch basic user data
$stmt = $conn->prepare("SELECT email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Fetch basic user data including wallet_balance
$stmt = $conn->prepare("SELECT email, profile_pic, wallet_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Unread count
$notif_count_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_count_stmt->bind_param("i", $user_id);
$notif_count_stmt->execute();
$unread_count = $notif_count_stmt->get_result()->fetch_assoc()['unread'];

// Fetch Notifications
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

// Fetch Listings
$list_stmt = $conn->prepare("
    SELECT p.id, p.title, p.price, p.image_url, p.is_approved,
           (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id AND o.status IN ('PAID', 'RECEIVED')) as sold_count
    FROM products p 
    WHERE p.seller_id = ? AND p.is_marketplace = 1 
    ORDER BY p.id DESC
");
$list_stmt->bind_param("i", $user_id);
$list_stmt->execute();
$user_listings = $list_stmt->get_result();

// Fetch Buy History
$buy_history_stmt = $conn->prepare("SELECT o.id as order_id, o.amount, o.created_at, o.status, p.title, p.image_url FROM orders o JOIN products p ON o.product_id = p.id WHERE o.buyer_id = ? AND o.status IN ('PAID', 'CANCELLED', 'RECEIVED') ORDER BY o.created_at DESC");
$buy_history_stmt->bind_param("i", $user_id);
$buy_history_stmt->execute();
$buy_history_result = $buy_history_stmt->get_result();

// --- POST: CONFIRM RECEIPT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_receipt'])) {
    $order_id = (int)$_POST['order_id'];
    
    $conn->begin_transaction();
    try {
        $order_stmt = $conn->prepare("SELECT seller_id, amount, payout_status, status FROM orders WHERE id = ? AND buyer_id = ? FOR UPDATE");
        $order_stmt->bind_param("ii", $order_id, $user_id);
        $order_stmt->execute();
        $order = $order_stmt->get_result()->fetch_assoc();

        if (!$order) {
            throw new Exception("Order not found.");
        }

        if ($order['status'] !== 'PAID') {
            throw new Exception("Order is not in a valid state to be received.");
        }

        $update_stmt = $conn->prepare("UPDATE orders SET status = 'RECEIVED' WHERE id = ?");
        $update_stmt->bind_param("i", $order_id);
        $update_stmt->execute();

        $seller_id = (int)$order['seller_id'];
        $payout_status = strtoupper(trim((string)$order['payout_status']));

        if ($seller_id > 0) {
            // Marketplace Product
            if ($payout_status === 'PENDING') {
                $amount = (float)$order['amount'];
                $seller_payout = round($amount * 0.95, 2);

                $wallet_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $wallet_stmt->bind_param("di", $seller_payout, $seller_id);
                $wallet_stmt->execute();

                $payout_stmt = $conn->prepare("UPDATE orders SET payout_status = 'RELEASED', payout_date = NOW() WHERE id = ?");
                $payout_stmt->bind_param("i", $order_id);
                $payout_stmt->execute();

                $seller_msg = "Your funds have been released! $" . number_format($seller_payout, 2) . " has been added to your Wallet for a confirmed delivery.";
                $seller_notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $seller_notif_stmt->bind_param("is", $seller_id, $seller_msg);
                $seller_notif_stmt->execute();
            }
        } else {
            // Official Shop Product - Update payout status so it doesn't stay PENDING
            $payout_stmt = $conn->prepare("UPDATE orders SET payout_status = 'NOT_APPLICABLE', payout_date = NOW() WHERE id = ?");
            $payout_stmt->bind_param("i", $order_id);
            $payout_stmt->execute();
        }

        $conn->commit();
        $_SESSION['msg'] = "ORDER MARKED AS RECEIVED.";
        $_SESSION['msg_type'] = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "COULD NOT UPDATE ORDER: " . strtoupper($e->getMessage());
        $_SESSION['msg_type'] = "error";
    }

    header("Location: client-profile.php");
    exit();
}


// --- POST: UPDATE PROFILE ---
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

// --- POST: SUBMIT NEW GEAR ---
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
            $stmt = $conn->prepare("INSERT INTO products (title, brand, price, category, condition_badge, description, image_url, is_marketplace, is_approved, seller_id, seller_name) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)");
            $stmt->bind_param("ssdssssis", $title, $brand, $price, $category, $condition, $description, $target_file, $user_id, $username);
            $stmt->execute();
            $_SESSION['msg'] = "GEAR SUBMITTED FOR REVIEW!";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: client-profile.php");
    exit();
}

// --- POST: UPDATE EXISTING LISTING ---
// --- POST: UPDATE EXISTING LISTING ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_listing'])) {
    $p_id = (int)$_POST['product_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $brand = $conn->real_escape_string($_POST['brand']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $condition = $conn->real_escape_string($_POST['condition_badge']);
    $description = $conn->real_escape_string($_POST['description']);

    // 1. Initial query parts
    // We reset is_approved to 0 so admin reviews changes.
    $sql = "UPDATE products SET title=?, brand=?, price=?, category=?, condition_badge=?, description=?, is_approved=0";
    $types = "ssdsss"; // Match the 6 fields above: title(s), brand(s), price(d), category(s), condition(s), description(s)
    $params = [$title, $brand, $price, $category, $condition, $description];

    // 2. Check if a new image was uploaded
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $target_file = "../assets/uploads/" . uniqid('market_') . '.' . pathinfo($_FILES["item_image"]["name"], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file)) {
            $sql .= ", image_url=?";
            $types .= "s";
            $params[] = $target_file;
        }
    }

    // 3. Add WHERE clause
    $sql .= " WHERE id=? AND seller_id=?";
    $types .= "ii";
    $params[] = $p_id;
    $params[] = $user_id;

    // 4. Prepare and Bind
    $stmt = $conn->prepare($sql);
    
    // THE FIX: We pass the types string first, then the unpacked params array
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "LISTING UPDATED AND SENT FOR REVIEW.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "DATABASE ERROR: COULD NOT UPDATE.";
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: client-profile.php");
    exit();
}

// --- POST: DELETE ACCOUNT ---
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
        /* --- Optimized Listings Design --- */
        .listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
    padding: 10px 0;
}

.mini-listing {
    /* Changed from #1a1a1a to #ffffff */
    background: #ffffff; 
    /* Changed from #333 to a solid black for contrast */
    border: 3px solid #000000; 
    position: relative;
    padding: 15px;
    display: flex;
    flex-direction: column;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    cursor: pointer;
    overflow: hidden;
    /* Added a subtle light shadow for depth */
    box-shadow: 4px 4px 0px #000000;
}

.mini-listing:hover {
    border-color: var(--primary);
    transform: translate(-2px, -2px);
    /* Stronger shadow on hover to match the single product page style */
    box-shadow: 8px 8px 0px #000000;
}

.mini-listing img {
    width: 100%;
    height: 160px;
    object-fit: cover;
    margin-bottom: 12px;
    filter: grayscale(0%); /* Removed grayscale for a cleaner light look */
    border: 2px solid #000000;
    background: #f9f9f9;
}

.mini-listing-info {
    flex-grow: 1;
}

.mini-listing-title {
    font-size: 1.1rem;
    /* Changed from #fff to #000 */
    color: #000000; 
    margin: 0 0 5px 0;
    font-family: 'Arial Black', sans-serif;
    letter-spacing: -0.5px;
    text-transform: uppercase;
}

.mini-listing-price {
    color: var(--primary);
    font-weight: bold;
    font-size: 1.2rem;
    margin: 0;
}

.mini-listing-id {
    font-size: 0.7rem;
    /* Changed to a darker grey for readability on light bg */
    color: #444; 
    margin-top: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Status Badges */
.listing-status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 8px;
    font-size: 0.65rem;
    font-weight: 900;
    z-index: 5;
    letter-spacing: 1px;
    border: 1px solid #000; /* Added border to badges */
    box-shadow: 2px 2px 0px #000;
}

.status-cancelled {
    background: #e74c3c;
    color: #fff;
}

.status-active {
    background: #00ff88;
    color: #000;
}

.status-pending {
    background: #ffcc00;
    color: #000;
}

.status-sold {
    background: #e74c3c;
    color: #fff;
}

/* Pagination Styling */
.listing-pagination {
    margin-top: 30px;
    /* Changed from #333 to #000 */
    border-top: 3px solid #000000; 
    padding-top: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
}

.page-number {
    font-weight: bold;
    color: #000;
}

.btn-pagination {
    background: #fff;
    color: #000;
    border: 3px solid #000;
    padding: 10px 14px;
    cursor: pointer;
    box-shadow: 4px 4px 0 #000;
}

.btn-pagination i {
    color: #000;
}

.btn-pagination:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.empty-placeholder-box {
    display: flex;
    flex-direction: column;
    align-items: center;       
    justify-content: center;
    text-align: center;         
}

.empty-placeholder-box .btn-primary-brutal {
    margin-top: 15px;
    padding: 12px 20px;         /* ✅ better padding */
}
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <div class="dashboard-header">
        <h2 class="glitch-text-admin">USER <span class="text-primary">DASHBOARD</span></h2>
        <p class="admin-text-shop">WELCOME BACK, @<?php echo htmlspecialchars($username); ?></p>
    </div>

    <?php if ($msg): ?>
        <div id="alert-box" class="admin-alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
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
        <div class="option-card-long" onclick="openModal('notificationsModal')">
            <i class="fa-solid fa-bell"></i>
            <h3>NOTIFICATIONS</h3>
            <?php if ($unread_count > 0): ?>
                <span class="notification-badge-client"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </div>
        <div class="option-card" onclick="openModal('myWallet')">
            <i class="fa-solid fa-wallet"></i>
            <h3>MY WALLET</h3>
            <span style="color: #2ecc71; font-weight: 900;">$<?php echo number_format($user_data['wallet_balance'] ?? 0, 2); ?></span>
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
                <div>
                    <label>TITLE</label>
                    <input type="text" name="title" placeholder="E.G. INDEPENDENT 149" required>
                </div>
                <div>
                    <label>BRAND</label>
                    <input type="text" name="brand" placeholder="E.G. INDY" required>
                </div>
            </div>
            <div class="form-grid-3">
                <div>
                    <label>PRICE ($)</label>
                    <input type="number" name="price" step="0.01" placeholder="0.00" required>
                </div>
                <div>
                    <label>CATEGORY</label>
                    <select name="category">
                        <option>DECKS</option><option>TRUCKS</option><option>WHEELS</option>
                        <option>BEARINGS</option><option>APPAREL</option><option>ACCESORIES</option><option>OTHER</option>
                    </select>
                </div>
                <div>
                    <label>CONDITION</label>
                    <select name="condition_badge">
                        <option>MINT / WALL HANGER</option><option>LIGHTLY SCUFFED</option><option>BEAT UP / SKATEABLE</option>
                    </select>
                </div>
            </div>
            <label>DESCRIPTION</label>
            <textarea name="description" rows="3" placeholder="DESCRIBE YOUR GEAR..." required></textarea>
            <label>IMAGE</label>
            <input type="file" name="item_image" required>
            <button type="submit" name="add_market_item" class="btn-primary-brutal btn-full">SUBMIT FOR REVIEW</button>
        </form>
    </div>
</div>

<div id="myListings" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px;"> <span class="close-modal" onclick="closeModal('myListings')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">INVENTORY</span></h3>

        <?php if($user_listings->num_rows > 0): ?>
            <div class="listings-grid" id="listings-wrapper">
                <?php while($row = $user_listings->fetch_assoc()): ?>
                    <?php if($row['sold_count'] > 0): ?>
                        <div class="mini-listing listing-page-item" style="cursor: default; opacity: 0.8;">
                    <?php else: ?>
                        <div class="mini-listing listing-page-item" onclick="openEditListing(<?php echo $row['id']; ?>)">
                    <?php endif; ?>
                        
                        <?php if($row['sold_count'] > 0): ?>
                            <span class="listing-status-badge status-sold">SOLD</span>
                        <?php elseif($row['is_approved'] == 0): ?>
                            <span class="listing-status-badge status-pending">PENDING</span>
                        <?php else: ?>
                            <span class="listing-status-badge status-active">ACTIVE</span>
                        <?php endif; ?>

                        <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="Gear Image">
                        
                        <div class="mini-listing-info">
                            <h4 class="mini-listing-title"><?php echo strtoupper(htmlspecialchars($row['title'])); ?></h4>
                            <p class="mini-listing-price">$<?php echo number_format($row['price'], 2); ?></p>
                        </div>
                        
                        <span class="mini-listing-id">REF_#<?php echo $row['id']; ?></span>
                    </div>
                <?php endwhile; ?>
            </div>

            <div id="listing-pagination-controls" class="listing-pagination">
                <button class="btn-pagination" onclick="changeListingPage(-1)" id="prevListBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span id="listingPageIndicator" class="page-number">PAGE 1</span>
                <button class="btn-pagination" onclick="changeListingPage(1)" id="nextListBtn">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">NO GEAR FOUND IN YOUR LOCKER.</p>
                <button class="btn-primary-brutal" onclick="closeModal('myListings'); openModal('sellGear');">START SELLING</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="editListingModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="backToListings()">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">LISTING</span></h3>
        <form action="client-profile.php" method="POST" enctype="multipart/form-data" class="admin-form" id="edit-listing-form">
            <input type="hidden" name="product_id" id="edit-p-id">
            
            <div class="form-grid-2-1">
                <div>
                    <label>TITLE</label>
                    <input type="text" name="title" id="edit-p-title" required>
                </div>
                <div>
                    <label>BRAND</label>
                    <input type="text" name="brand" id="edit-p-brand" required>
                </div>
            </div>

            <div class="form-grid-3">
                <div>
                    <label>PRICE ($)</label>
                    <input type="number" name="price" id="edit-p-price" step="0.01" required>
                </div>
                <div>
                    <label>CATEGORY</label>
                    <select name="category" id="edit-p-category">
                        <option>DECKS</option><option>TRUCKS</option><option>WHEELS</option>
                        <option>BEARINGS</option><option>APPAREL</option><option>ACCESORIES</option><option>OTHER</option>
                    </select>
                </div>
                <div>
                    <label>CONDITION</label>
                    <select name="condition_badge" id="edit-p-condition">
                        <option>MINT / WALL HANGER</option><option>LIGHTLY SCUFFED</option><option>BEAT UP / SKATEABLE</option>
                    </select>
                </div>
            </div>

            <label>DESCRIPTION</label>
            <textarea name="description" id="edit-p-desc" rows="3" required></textarea>

            <label>REPLACE IMAGE (LEAVE BLANK TO KEEP CURRENT)</label>
            <input type="file" name="item_image">

            <button type="submit" name="update_listing" class="btn-primary-brutal btn-full">UPDATE & RE-SUBMIT</button>
        </form>
    </div>
</div>

<div id="notificationsModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('notificationsModal')">&times;</span>
        <h3 class="admin-table-h3">SYSTEM <span class="header-span">MESSAGES</span></h3>
        <div id="notif-wrapper" class="notifications-list">
            <?php if ($notifications->num_rows > 0): ?>
                <?php while($n = $notifications->fetch_assoc()): 
                    $raw_msg = $n['message'];
                    $short_msg = strlen($raw_msg) > 35 ? substr($raw_msg, 0, 35) . "..." : $raw_msg;
                    $formatted_date = date("M j, H:i", strtotime($n['created_at']));
                ?>
                    <div class="notification-item notif-page-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                        <div class="notif-content" 
                             data-notif-id="<?php echo $n['id']; ?>" 
                             data-full-msg="<?php echo htmlspecialchars($raw_msg, ENT_QUOTES); ?>" 
                             data-date="<?php echo $formatted_date; ?>"
                             onclick="openFullMessage(this)">
                            <p class="notif-msg"><?php echo htmlspecialchars($short_msg); ?></p>
                            <span class="notif-date"><?php echo $formatted_date; ?></span>
                        </div>
                        <a href="client-profile.php?delete_notif=<?php echo $n['id']; ?>" class="notif-delete-btn" onclick="return confirm('SHRED THIS MESSAGE PERMANENTLY?')">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-placeholder-box"><p class="empty-placeholder-title">NO NOTIFICATIONS YET.</p></div>
            <?php endif; ?>
        </div>
        <?php if ($unread_count > 0): ?>
            <a href="client-profile.php?clear_notifs=1" class="btn-mark-read">MARK ALL READ</a>
        <?php endif; ?>
        <div id="pagination-controls" class="notif-pagination">
            <button class="btn-pagination" onclick="changePage(-1)" id="prevBtn"><i class="fa-solid fa-chevron-left"></i></button>
            <span id="pageIndicator" class="page-number">PAGE 1</span>
            <button class="btn-pagination" onclick="changePage(1)" id="nextBtn"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
</div>

<div id="fullMessageModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('fullMessageModal')">&times;</span>
        <h3 class="admin-table-h3">MESSAGE <span class="header-span">DETAILS</span></h3>
        <div class="full-message-box">
            <p id="fullMessageDisplay" class="full-message-text"></p>
            <span id="fullMessageDateDisplay" class="notif-date"></span>
        </div>
        <button type="button" class="btn-primary-brutal btn-full" onclick="backToNotifications()">BACK TO INBOX</button>
    </div>
</div>

<div id="buyHistory" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px;">
        <span class="close-modal" onclick="closeModal('buyHistory')">&times;</span>
        <h3 class="admin-table-h3">BUY <span class="header-span">HISTORY</span></h3>
        
        <?php if ($buy_history_result && $buy_history_result->num_rows > 0): ?>
            <div class="listings-grid">
                <?php while ($purchase = $buy_history_result->fetch_assoc()): ?>
                    <?php $purchase_code = "ORD-" . str_pad($purchase['order_id'], 6, "0", STR_PAD_LEFT); ?>
                    
                    <div class="mini-listing buy-history-page-item" style="cursor: default;">
                        
                        <?php if ($purchase['status'] === 'CANCELLED'): ?>
                            <span class="listing-status-badge status-cancelled">CANCELLED</span>
                        <?php elseif ($purchase['status'] === 'RECEIVED'): ?>
                            <span class="listing-status-badge" style="background:#3498db; color:#fff;">RECEIVED</span>
                        <?php else: ?>
                            <span class="listing-status-badge status-active">PAID</span>
                        <?php endif; ?>

                        <img src="<?php echo htmlspecialchars($purchase['image_url']); ?>" alt="Product">
                        
                        <div class="mini-listing-info">
                            <h4 class="mini-listing-title"><?php echo strtoupper(htmlspecialchars($purchase['title'])); ?></h4>
                            <p class="mini-listing-price">$<?php echo number_format($purchase['amount'], 2); ?></p>
                        </div>
                        
                        <div style="margin-top: 10px; border-top: 2px dashed #000; padding-top: 10px;">
                            <span class="mini-listing-id" style="display: block; font-weight: bold; color: #000;">
                                CODE: <?php echo $purchase_code; ?>
                            </span>
                            <span class="mini-listing-id" style="display: block; margin-top: 5px;">
                                DATE: <?php echo date("M d, Y", strtotime($purchase['created_at'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($purchase['status'] === 'PAID'): ?>
                            <button type="button" class="btn-primary-brutal btn-full" style="margin-top: 10px; font-size:1rem; padding:8px;" onclick="openConfirmReceiptModal(<?php echo $purchase['order_id']; ?>)">CONFIRM RECEIPT</button>
                        <?php endif; ?>

                    </div>
                <?php endwhile; ?>
            </div>

            <div id="buy-history-pagination-controls" class="listing-pagination">
                <button class="btn-pagination" onclick="changeBuyPage(-1)" id="prevBuyBtn">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span id="buyPageIndicator" class="page-number">PAGE 1</span>
                <button class="btn-pagination" onclick="changeBuyPage(1)" id="nextBuyBtn">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="empty-placeholder-box">
                <p class="empty-placeholder-title">NO PURCHASES YET.</p>
                <p style="font-family: 'Staatliches', sans-serif; color: #666; margin-bottom: 20px;">Hit the shop or street market to score some gear.</p>
                <button class="btn-primary-brutal" onclick="window.location.href='shop.php'">GO TO SHOP</button>
            </div>
        <?php endif; ?>
    </div>
</div>


<div id="myWallet" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('myWallet')">&times;</span>
        <h3 class="admin-table-h3">MY <span class="header-span">WALLET</span></h3>
        
        <div class="grainy-card" style="text-align: center; padding: 40px 20px; background: #f7f7f7; border: 2px solid #333; margin-bottom: 20px;">
            <p style="color: #1A1A1A; font-family: 'Arial Black', sans-serif; margin: 0; letter-spacing: 2px;">AVAILABLE BALANCE</p>
            <h1 style="color: #2ecc71; font-size: 4rem; margin: 10px 0;">$<?php echo number_format($user_data['wallet_balance'] ?? 0, 2); ?></h1>
        </div>

        <button class="btn-primary-brutal btn-full" onclick="alert('Withdrawal system coming soon! Connect your bank in settings.')">WITHDRAW TO BANK</button>
        
        <p style="font-size: 0.8rem; color: #666; text-align: center; margin-top: 15px;">
            Funds from your marketplace sales appear here instantly.
        </p>
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
    function openModal(id) { 
        document.getElementById(id).style.display = 'flex'; 
        if (id === 'notificationsModal') {
            currentNotifPage = 1;
            updatePagination();
        }
        if (id === 'myListings') {
            currentListPage = 1;
            updateListingPagination();
        }
        if (id === 'buyHistory') {
            currentBuyPage = 1;
            updateBuyPagination();
        }
    }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    // --- LISTING EDIT LOGIC ---
    function openEditListing(id) {
        fetch(`client-profile.php?get_listing_details=${id}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('edit-p-id').value = data.id;
            document.getElementById('edit-p-title').value = data.title;
            document.getElementById('edit-p-brand').value = data.brand;
            document.getElementById('edit-p-price').value = data.price;
            document.getElementById('edit-p-category').value = data.category;
            document.getElementById('edit-p-condition').value = data.condition_badge;
            document.getElementById('edit-p-desc').value = data.description;
            
            closeModal('myListings');
            openModal('editListingModal');
        });
    }

    function backToListings() {
        closeModal('editListingModal');
        openModal('myListings');
    }

    // --- BUY HISTORY PAGINATION ---
    let currentBuyPage = 1;
    const buyItemsPerPage = 6;

    function updateBuyPagination() {
        const items = document.querySelectorAll('.buy-history-page-item');
        const totalPages = Math.ceil(items.length / buyItemsPerPage);
        const prevBtn = document.getElementById('prevBuyBtn');
        const nextBtn = document.getElementById('nextBuyBtn');
        const indicator = document.getElementById('buyPageIndicator');

        if (items.length === 0) {
            const controls = document.getElementById('buy-history-pagination-controls');
            if (controls) controls.style.display = 'none';
            return;
        }

        items.forEach(item => item.style.display = 'none');
        let start = (currentBuyPage - 1) * buyItemsPerPage;
        let end = start + buyItemsPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'block'; }

        indicator.innerText = `PAGE ${currentBuyPage} / ${totalPages}`;
        prevBtn.disabled = currentBuyPage === 1;
        nextBtn.disabled = currentBuyPage === totalPages || totalPages === 0;
    }

    function changeBuyPage(step) {
        currentBuyPage += step;
        updateBuyPagination();
    }

    // --- LISTING PAGINATION ---
    let currentListPage = 1;
    const listItemsPerPage = 6; // Grid looks better with 4 items per page (2x2)

    function updateListingPagination() {
        const items = document.querySelectorAll('.listing-page-item');
        const totalPages = Math.ceil(items.length / listItemsPerPage);
        const prevBtn = document.getElementById('prevListBtn');
        const nextBtn = document.getElementById('nextListBtn');
        const indicator = document.getElementById('listingPageIndicator');

        if (items.length === 0) {
            document.getElementById('listing-pagination-controls').style.display = 'none';
            return;
        }

        items.forEach(item => item.style.display = 'none');
        let start = (currentListPage - 1) * listItemsPerPage;
        let end = start + listItemsPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'block'; }

        indicator.innerText = `PAGE ${currentListPage} / ${totalPages}`;
        prevBtn.disabled = currentListPage === 1;
        nextBtn.disabled = currentListPage === totalPages || totalPages === 0;
    }

    function changeListingPage(step) {
        currentListPage += step;
        updateListingPagination();
    }

    // --- NOTIFICATION LOGIC (Existing) ---
    let currentNotifPage = 1;
    const itemsPerPage = 5;

    function updatePagination() {
        const items = document.querySelectorAll('.notif-page-item');
        const totalPages = Math.ceil(items.length / itemsPerPage);
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const indicator = document.getElementById('pageIndicator');
        if (items.length === 0) {
            if(document.getElementById('pagination-controls')) document.getElementById('pagination-controls').style.display = 'none';
            return;
        }
        items.forEach(item => item.style.display = 'none');
        let start = (currentNotifPage - 1) * itemsPerPage;
        let end = start + itemsPerPage;
        for (let i = start; i < end; i++) { if (items[i]) items[i].style.display = 'flex'; }
        indicator.innerText = `PAGE ${currentNotifPage} / ${totalPages}`;
        prevBtn.disabled = currentNotifPage === 1;
        nextBtn.disabled = currentNotifPage === totalPages;
    }

    function changePage(step) {
        currentNotifPage += step;
        updatePagination();
    }

    function openFullMessage(el) {
        const notifId = el.getAttribute('data-notif-id');
        document.getElementById('fullMessageDisplay').innerText = el.getAttribute('data-full-msg');
        document.getElementById('fullMessageDateDisplay').innerText = el.getAttribute('data-date');
        closeModal('notificationsModal');
        openModal('fullMessageModal');
        const parentItem = el.closest('.notification-item');
        if (parentItem.classList.contains('unread')) {
            fetch(`client-profile.php?ajax_read_notif=${notifId}`).then(() => {
                parentItem.classList.remove('unread');
                const badge = document.querySelector('.notification-badge-client');
                if (badge) {
                    let count = parseInt(badge.innerText);
                    if (count > 1) badge.innerText = count - 1; else badge.remove();
                }
            });
        }
    }

    function backToNotifications() { closeModal('fullMessageModal'); openModal('notificationsModal'); }

    window.onclick = function(event) { if (event.target.className === 'modal-overlay') { event.target.style.display = 'none'; } }

    document.addEventListener("DOMContentLoaded", () => {
        const alertBox = document.getElementById('alert-box');
        if (alertBox) { setTimeout(() => { alertBox.style.opacity = "0"; setTimeout(() => alertBox.remove(), 500); }, 4000); }
    });
</script>

<style>
#confirmReceiptModal .btn-receipt-no {
    flex: 1;
    background: var(--primary);
    color: #fff !important;
}
#confirmReceiptModal .btn-receipt-no:hover {
    background: var(--textwhite);
    color: #1A1A1A !important;
}
#confirmReceiptModal .btn-receipt-yes {
    background: #2ecc71;
    color: #fff !important;
}
#confirmReceiptModal .btn-receipt-yes:hover {
    background: #27ae60;
    color: #fff !important;
}
#notReceivedMsg .contact-label {
    color: var(--primary);
    font-size: 1.1rem;
    font-family: 'Staatliches', sans-serif;
    letter-spacing: 1px;
    display: block;
    margin-bottom: 6px;
}
</style>

<div id="confirmReceiptModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmReceiptModal')">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0; font-size:2.5rem;">DID YOU <span class="header-span">RECEIVE</span> THE PRODUCT?</h3>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <form action="client-profile.php" method="POST" style="flex:1;">
                <input type="hidden" name="order_id" id="confirmReceiptOrderId">
                <button type="submit" name="confirm_receipt" class="btn-primary-brutal btn-full btn-receipt-yes">YES</button>
            </form>
            <button type="button" class="btn-primary-brutal btn-full btn-receipt-no" onclick="showNotReceivedMsg()">NO</button>
        </div>
        <div id="notReceivedMsg" style="display:none; margin-top:15px; border: 3px dashed var(--charcoal); padding: 15px; background: rgba(0,0,0,0.03);">
            <span class="contact-label">Contact us through our email:</span>
            <a href="mailto:skateshopp2026@gmail.com" style="color:#3498db; text-decoration:underline; font-weight:bold;">skateshopp2026@gmail.com</a>
        </div>
    </div>
</div>

<script>
function openConfirmReceiptModal(orderId) {
    document.getElementById('confirmReceiptOrderId').value = orderId;
    document.getElementById('notReceivedMsg').style.display = 'none';
    openModal('confirmReceiptModal');
}
function showNotReceivedMsg() {
    document.getElementById('notReceivedMsg').style.display = 'block';
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>