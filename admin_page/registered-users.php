<?php
require_once 'admin_auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php'; 


// --- 1. HANDLE POST REQUESTS (DELETE & TOGGLE BLOCK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target_user = (int)$_POST['user_id'];
    
    $conn->begin_transaction();
    try {
        // Buyer Protection Logic (Deleted Seller)
        $order_chk = $conn->prepare("SELECT o.buyer_id FROM orders o JOIN products p ON o.product_id = p.id WHERE p.seller_id = ? AND o.status = 'PAID'");
        $order_chk->bind_param("i", $target_user);
        $order_chk->execute();
        $buyer_res = $order_chk->get_result();
        
        $msg = "The seller from whom you purchased an item is no longer active on the platform. If your order has not been completed or delivered, please contact Support regarding a potential refund or further assistance.";
        while ($b = $buyer_res->fetch_assoc()) {
            if (!empty($b['buyer_id'])) {
                sendAppNotification($conn, $b['buyer_id'], $msg);
            }
        }
        $order_chk->close();

        // Safely attempt to alter orders table just in case they aren't nullable
        @$conn->query("ALTER TABLE orders MODIFY buyer_id INT DEFAULT NULL");
        @$conn->query("ALTER TABLE orders MODIFY seller_id INT DEFAULT NULL");
        @$conn->query("ALTER TABLE orders MODIFY product_id INT DEFAULT NULL");

        // Nullify order references to preserve order financial history
        $conn->query("UPDATE orders SET buyer_id = NULL WHERE buyer_id = $target_user");
        $conn->query("UPDATE orders SET seller_id = NULL WHERE seller_id = $target_user");
        $conn->query("UPDATE orders o JOIN products p ON o.product_id = p.id SET o.product_id = NULL WHERE p.seller_id = $target_user");

        // Cascading Deletes (hierarchy respected)
        $conn->query("DELETE FROM user_follows WHERE follower_id = $target_user OR followed_id = $target_user");
        $conn->query("DELETE FROM support_ticket_replies WHERE user_id = $target_user");
        $conn->query("DELETE FROM support_tickets WHERE user_id = $target_user");
        $conn->query("DELETE FROM notifications WHERE user_id = $target_user");
        $conn->query("DELETE FROM community_shoutouts WHERE user_id = $target_user");
        $conn->query("DELETE FROM community_qna WHERE user_id = $target_user");
        $conn->query("DELETE FROM seller_ratings WHERE buyer_id = $target_user OR seller_id = $target_user");
        $conn->query("DELETE FROM product_reviews WHERE user_id = $target_user");
        $conn->query("DELETE FROM reel_likes WHERE user_id = $target_user");
        $conn->query("DELETE FROM reel_comments WHERE user_id = $target_user");
        $conn->query("DELETE FROM reel_edit_requests WHERE user_id = $target_user");
        $conn->query("DELETE FROM reels WHERE user_id = $target_user");
        $conn->query("DELETE FROM products WHERE seller_id = $target_user");
        $conn->query("DELETE FROM users WHERE id = $target_user");

        $conn->commit();
        $_SESSION['admin_msg'] = "USER AND ALL ASSOCIATED DATA TERMINATED.";
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Failed to delete user ID {$target_user}: " . $e->getMessage());
        $_SESSION['admin_msg'] = "ERROR: COULD NOT DELETE USER. Check database logs.";
    }
    
    header("Location: registered-users.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_block'])) {
    $target_user = (int)$_POST['user_id'];
    
    $conn->begin_transaction();
    try {
        $chk = $conn->prepare("SELECT is_blocked FROM users WHERE id = ?");
        $chk->bind_param("i", $target_user);
        $chk->execute();
        $is_blocked = $chk->get_result()->fetch_assoc()['is_blocked'] ?? 0;
        $chk->close();
        
        $new_status = ($is_blocked == 1) ? 0 : 1;
        
        $block_stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
        $block_stmt->bind_param("ii", $new_status, $target_user);
        $block_stmt->execute();
        $block_stmt->close();
        
        // Buyer Protection Logic (Suspended Seller)
        if ($new_status == 1) {
            $order_chk = $conn->prepare("SELECT o.buyer_id FROM orders o JOIN products p ON o.product_id = p.id WHERE p.seller_id = ? AND o.status = 'PAID'");
            $order_chk->bind_param("i", $target_user);
            $order_chk->execute();
            $buyer_res = $order_chk->get_result();
            
            $msg = "The seller from whom you purchased an item has been suspended from the platform. If your order has not been completed or delivered, please contact Support for assistance.";
            while ($b = $buyer_res->fetch_assoc()) {
                if (!empty($b['buyer_id'])) {
                    sendAppNotification($conn, $b['buyer_id'], $msg);
                }
            }
            $order_chk->close();
        }
        
        $conn->commit();
        $_SESSION['admin_msg'] = "USER STATUS UPDATED.";
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Failed to toggle block user ID {$target_user}: " . $e->getMessage());
        $_SESSION['admin_msg'] = "ERROR: COULD NOT UPDATE STATUS.";
    }
    
    header("Location: registered-users.php");
    exit();
}

// --- 2. HANDLE SEARCH & FILTER LOGIC ---
$search = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Base query
$where_sql = "1=1";
$params = [];
$types = "";

// If user is searching something
if ($search !== '') {
    $where_sql .= " AND (username LIKE ? OR email LIKE ? OR id = ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search; // exact ID match
    $types .= "ssi";
}

// Dropdown status filters
if ($status_filter === 'blocked') {
    $where_sql .= " AND is_blocked = 1";
} elseif ($status_filter === 'unblocked') {
    $where_sql .= " AND (is_blocked = 0 OR is_blocked IS NULL)";
}

// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_sql = "SELECT COUNT(*) as total FROM users WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types !== "") {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_res = $count_stmt->get_result();
$total_records = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$users_sql = "SELECT id, username, email, is_blocked FROM users WHERE $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset";
// Execute prepared statement for fetching users
$stmt = $conn->prepare($users_sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | REGISTERED USERS</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <script src="../assets/admin-script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>

<?php require __DIR__ . '/admin_header.php'; ?>

<section class="admin-layout container">
    
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">REGISTERED <span class="text-primary">USERS</span></h2>
                <p class="admin-text-shop">MANAGE CLIENT ACCOUNTS</p>
            </div>
        </div>

        <?php if(isset($_SESSION['admin_msg'])): ?>
            <div class="admin-alert alert-success">
                <?php 
                    echo $_SESSION['admin_msg']; 
                    unset($_SESSION['admin_msg']);
                ?>
            </div>
        <?php endif; ?>

        

        <div class="grainy-card" style="padding: 20px;">
        <h3 class="admin-table-h3">USER <span class="header-span">LISTINGS</span></h3>

        <div class="grainy-card filter-bar">
            <form method="GET" action="registered-users.php" class="search-filter-form">
                
                <div class="filter-group search-box">
                    <label>SEARCH</label>
                    <input type="text" name="search" placeholder="ID, Username or Email..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="filter-group">
                    <label>STATUS</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">ALL STATUSES</option>
                        <option value="unblocked" <?php echo $status_filter === 'unblocked' ? 'selected' : ''; ?>>UNBLOCKED</option>
                        <option value="blocked" <?php echo $status_filter === 'blocked' ? 'selected' : ''; ?>>BLOCKED</option>
                    </select>
                </div>

                <div class="filter-actions" style="margin-top:22px;">
                    <button type="submit" class="btn-filter">FILTER</button>
                    <a href="registered-users.php" class="btn-reset">RESET</a>
                </div>
                
            </form>
        </div>

            <div class="table-responsive"><table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>USERNAME</th>
                        <th>EMAIL</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users_result->num_rows > 0): ?>
                        <?php while($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td class="td-id">#<?php echo $user['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if(isset($user['is_blocked']) && $user['is_blocked'] == 1): ?>
                                        <span class="listing-status-badge status-cancelled">BLOCKED</span>
                                    <?php else: ?>
                                        <span class="listing-status-badge status-active">ACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="toggle_block" class="btn-mini btn-block">
                                                <?php echo (isset($user['is_blocked']) && $user['is_blocked'] == 1) ? 'UNBLOCK' : 'BLOCK'; ?>
                                            </button>
                                        </form>

                                        <button type="button" class="btn-mini btn-danger" onclick="openConfirmDeleteUserModal(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>



                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; font-weight:700; font-style: italic;">NO USERS MATCHING YOUR SEARCH.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table></div>

            <?php if ($total_pages > 0): ?>
            <div class="admin-pagination">
                <?php
                $query_string = $_GET;
                if ($page > 1) {
                    $query_string['page'] = $page - 1;
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline">&laquo; PREV</a>';
                }
                for ($i = 1; $i <= $total_pages; $i++) {
                    $query_string['page'] = $i;
                    $active = ($i === $page) ? 'active' : '';
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline ' . $active . '">' . $i . '</a>';
                }
                if ($page < $total_pages) {
                    $query_string['page'] = $page + 1;
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline">NEXT &raquo;</a>';
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</section>

<div id="confirmDeleteUserModal" class="modal-overlay" style="z-index: 9999;">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteUserModal')">&times;</span>
        <h3 class="admin-table-h3" style="border:none; margin-bottom:10px;">TRASH <span class="text-primary">USER?</span></h3>
        <p style="font-family:'Inter',sans-serif; font-size:1rem; margin-bottom:20px;">Are you sure you want to completely delete this user and all their marketplace items?</p>
        <form method="POST" action="registered-users.php">
            <input type="hidden" name="user_id" id="deleteUserIdInput" value="">
            <input type="hidden" name="delete_user" value="1">
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-danger" style="flex: 1; font-size: 1rem;">YES</button>
                <button type="button" class="btn btn-outline" style="flex: 1; font-size: 1rem;" onclick="closeModal('confirmDeleteUserModal')">CANCEL</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openConfirmDeleteUserModal(userId) {
        document.getElementById('deleteUserIdInput').value = userId;
        document.getElementById('confirmDeleteUserModal').classList.add('active');
    }
</script>

</body>
</html>
