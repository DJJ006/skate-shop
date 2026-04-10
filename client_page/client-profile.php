<?php
session_start();
include '../db.php'; 


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (isset($_GET['ajax_read_notif'])) {
    $notif_id = (int)$_GET['ajax_read_notif'];
    
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    
    if($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
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

$stmt = $conn->prepare("SELECT email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$notif_count_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_count_stmt->bind_param("i", $user_id);
$notif_count_stmt->execute();
$unread_count = $notif_count_stmt->get_result()->fetch_assoc()['unread'];

$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

$list_stmt = $conn->prepare("SELECT id, title, price, image_url, is_approved FROM products WHERE seller_id = ? AND is_marketplace = 1 ORDER BY id DESC");
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
                        <option>DECKS</option>
                        <option>TRUCKS</option>
                        <option>WHEELS</option>
                        <option>BEARINGS</option>
                        <option>APPAREL</option>
                        <option>ACCESORIES</option>
                        <option>OTHER</option>
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

            <button type="submit" name="add_market_item" class="btn-primary-brutal btn-full">SUBMIT FOR REVIEW</button>
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
                    <div class="mini-listing" style="position: relative;">
                        <?php if($row['is_approved'] == 0): ?>
                            <span class="listing-status-pending">PENDING</span>
                        <?php else: ?>
                            <span class="listing-status-active">ACTIVE</span>
                        <?php endif; ?>

                        <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="Listing Image">
                        
                        <div class="mini-listing-info">
                            <h4 class="mini-listing-title"><?php echo strtoupper(htmlspecialchars($row['title'])); ?></h4>
                            <p class="mini-listing-price">$<?php echo number_format($row['price'], 2); ?></p>
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
                        <a href="client-profile.php?delete_notif=<?php echo $n['id']; ?>" 
                        class="notif-delete-btn" 
                        onclick="return confirm('SHRED THIS MESSAGE PERMANENTLY?')">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-placeholder-box">
                    <p class="empty-placeholder-title">NO NOTIFICATIONS YET.</p>
                </div>
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

    // Opens the individual full-text modal
    function openFullMessage(el) {
    const notifId = el.getAttribute('data-notif-id');
    const fullText = el.getAttribute('data-full-msg');
    const fullDate = el.getAttribute('data-date');

    // 1. Show the message in the modal
    document.getElementById('fullMessageDisplay').innerText = fullText;
    document.getElementById('fullMessageDateDisplay').innerText = fullDate;
    
    closeModal('notificationsModal');
    openModal('fullMessageModal');

    // 2. Mark as read in the database (Background AJAX)
    // Only run if the parent item still has the 'unread' class
    const parentItem = el.closest('.notification-item');
    if (parentItem.classList.contains('unread')) {
        fetch(`client-profile.php?ajax_read_notif=${notifId}`)
            .then(() => {
                // 3. Update UI: Remove the red/bold unread styling
                parentItem.classList.remove('unread');
                
                
                const badge = document.querySelector('.notification-badge-client');
                if (badge) {
                    let count = parseInt(badge.innerText);
                    if (count > 1) {
                        badge.innerText = count - 1;
                    } else {
                        badge.remove(); // Remove badge if it hits 0
                    }
                }
            })
            .catch(err => console.error("Error marking read:", err));
    }
}

    // Allows user to easily step back to the list
    function backToNotifications() {
    closeModal('fullMessageModal');
    openModal('notificationsModal');
}

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


    let currentNotifPage = 1;
const itemsPerPage = 5;

function updatePagination() {
    const items = document.querySelectorAll('.notif-page-item');
    const totalPages = Math.ceil(items.length / itemsPerPage);
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const indicator = document.getElementById('pageIndicator');

    // If no notifications, hide controls
    if (items.length === 0) {
        document.getElementById('pagination-controls').style.display = 'none';
        return;
    }

    // Hide all items
    items.forEach(item => item.style.display = 'none');

    // Show only the items for current page
    let start = (currentNotifPage - 1) * itemsPerPage;
    let end = start + itemsPerPage;
    
    for (let i = start; i < end; i++) {
        if (items[i]) items[i].style.display = 'flex';
    }

    // Update UI
    indicator.innerText = `PAGE ${currentNotifPage} / ${totalPages}`;
    prevBtn.disabled = currentNotifPage === 1;
    nextBtn.disabled = currentNotifPage === totalPages;
}

function changePage(step) {
    currentNotifPage += step;
    updatePagination();
}

// Overwrite your existing openModal slightly to reset pagination when opened
function openModal(id) { 
    document.getElementById(id).style.display = 'flex'; 
    if (id === 'notificationsModal') {
        currentNotifPage = 1; // Reset to page 1 every time it opens
        updatePagination();
    }
}
</script>

<?php include 'footer.php'; ?>

</body>
</html>