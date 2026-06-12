<?php
require_once 'admin_auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php';

// Fetch pending count for the sidebar notification badge
$count_sql = "SELECT COUNT(*) as pending_count FROM products WHERE is_marketplace = 1 AND is_approved = 0";
$count_result = $conn->query($count_sql);
$pending_count = 0;

if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $pending_count = (int)$count_row['pending_count'];
}



// --- POST: VERIFY SELLER (Manual or Auto) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_seller'])) {
    $seller_id = (int)$_POST['seller_id'];
    $is_manual = isset($_POST['manual_override']) ? true : false;
    $admin_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
    
    $v_type = $is_manual ? 'Manual' : 'Automatic';
    
    $sql = "UPDATE users SET is_verified = 1, verified_status = 'Verified', verification_type = '$v_type', verification_admin_id = $admin_id, verification_date = NOW() WHERE id = $seller_id";
    if ($conn->query($sql) === TRUE) {
        // Send Notification to the User
        $msg = $is_manual 
            ? "Congratulations! An administrator has manually awarded you the VERIFIED SELLER badge!"
            : "Congratulations! You have been awarded the VERIFIED SELLER badge for maintaining a 5-star rating!";
        sendAppNotification($conn, $seller_id, $msg);

        $_SESSION['msg'] = $is_manual ? "SELLER MANUALLY VERIFIED!" : "SELLER VERIFIED (AUTO CRITERIA)!";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: verify-seller.php");
    exit();
}

// --- POST: REVOKE VERIFICATION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['revoke_seller'])) {
    $seller_id = (int)$_POST['seller_id'];
    
    $sql = "UPDATE users SET is_verified = 0, verified_status = 'None', verification_type = NULL, verification_admin_id = NULL, verification_date = NULL WHERE id = $seller_id";
    if ($conn->query($sql) === TRUE) {
        $msg = "Your VERIFIED SELLER badge has been revoked by an administrator.";
        sendAppNotification($conn, $seller_id, $msg);

        $_SESSION['msg'] = "VERIFICATION REVOKED & CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: verify-seller.php");
    exit();
}

// SEARCH LOGIC
$search = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$where_sql = "1=1";
$params = [];
$types = "";

if ($search !== '') {
    $where_sql .= " AND (username LIKE ? OR id = ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search; // exact ID match
    $types .= "ss";
}

if ($status_filter === 'verified') {
    $where_sql .= " AND is_verified = 1";
} elseif ($status_filter === 'unverified') {
    $where_sql .= " AND is_verified = 0";
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
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Fetch all users to display in the table
$sellers_sql = "SELECT id, username, is_verified, verification_type, verification_date FROM users WHERE $where_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$sellers_stmt_query = $conn->prepare($sellers_sql);
if ($types !== "") {
    $sellers_stmt_query->bind_param($types, ...$params);
}
$sellers_stmt_query->execute();
$sellers_result = $sellers_stmt_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | SELLER VERIFICATION</title>
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
    
<div style="margin-top: 47px;">
    <?php include 'admin_sidebar.php'; ?>
</div>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">TRUST & <span class="text-primary">SAFETY</span></h2>
                <p class="admin-text-shop">MANAGE SELLER VERIFICATIONS AND RATINGS</p>
            </div>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="admin-alert alert-<?php echo $_SESSION['msg_type']; ?>">
                <?php 
                    echo $_SESSION['msg']; 
                    unset($_SESSION['msg']); 
                    unset($_SESSION['msg_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="grainy-card card-padding" style="padding: 20px;">
            <h3 class="admin-table-h3">SELLER <span class="header-span">STATUS</span></h3>

            <div class="grainy-card filter-bar">
                <form method="GET" action="verify-seller.php" class="search-filter-form">
                    
                    <div class="filter-group search-box">
                        <label>SEARCH</label>
                        <input type="text" name="search" placeholder="ID or Username..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="filter-group">
                        <label>STATUS</label>
                        <select name="status_filter" onchange="this.form.submit()">
                            <option value="">ALL STATUSES</option>
                            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>VERIFIED</option>
                            <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>NOT VERIFIED</option>
                        </select>
                    </div>

                    <div class="filter-actions" style="margin-top:22px;">
                        <button type="submit" class="btn-filter">FILTER</button>
                        <a href="verify-seller.php" class="btn-reset">RESET</a>
                    </div>
                    
                </form>
            </div>

            <div class="table-responsive"><table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>USERNAME</th>
                        <th>LAST 5 RATINGS</th>
                        <th>STATUS & SOURCE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sellers_result->num_rows > 0): ?>
                        <?php while($user = $sellers_result->fetch_assoc()): ?>
                            <?php
                                $uid = $user['id'];
                                $r_stmt = $conn->query("SELECT rating FROM seller_ratings WHERE seller_id = $uid ORDER BY created_at DESC LIMIT 5");
                                
                                $ratings = [];
                                while($r = $r_stmt->fetch_assoc()) {
                                    $ratings[] = (int)$r['rating'];
                                }

                                // Auto criteria: 5 ratings, all 5-stars
                                $qualifies = (count($ratings) === 5 && min($ratings) === 5);
                            ?>
                            <tr>
                                <td class="td-id">#<?php echo $user['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td>
                                    <?php 
                                        if (empty($ratings)) {
                                            echo "<span style='color: #888; font-size: 0.8rem; font-style: italic;'>NO RATINGS YET</span>";
                                        } else {
                                            foreach($ratings as $star) {
                                                echo "<i class='fa-solid fa-star' style='color: #ffcc00; font-size: 0.8rem;'></i> ";
                                            }
                                            echo "<br><span style='font-size: 0.8rem; color: #666;'>(" . implode(", ", $ratings) . ")</span>";
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($user['is_verified']): ?>
                                        <span class="badge-status status-active" style="background: #2ecc71; color: #fff;">
                                            <i class="fa-solid fa-check-circle"></i> VERIFIED
                                        </span>
                                        <?php if (!empty($user['verification_type'])): ?>
                                            <div style="font-size: 0.75rem; margin-top: 5px; color: #555;">
                                                Via: <strong><?php echo htmlspecialchars($user['verification_type']); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($qualifies): ?>
                                        <span class="badge-status status-pending" style="background: #ffcc00; color: #000;">QUALIFIED</span>
                                    <?php else: ?>
                                        <span class="badge-status status-blocked" style="background: #444; color: #fff;">UNQUALIFIED</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                    <?php if (!$user['is_verified']): ?>
                                        <?php if ($qualifies): ?>
                                            <form method="POST" action="verify-seller.php">
                                                <input type="hidden" name="seller_id" value="<?php echo $uid; ?>">
                                                <button type="submit" name="verify_seller" class="btn-mini btn-accept" style="font-weight: 900;">GIVE BADGE</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="verify-seller.php">
                                                <input type="hidden" name="seller_id" value="<?php echo $uid; ?>">
                                                <input type="hidden" name="manual_override" value="1">
                                                <button type="submit" name="verify_seller" class="btn-mini btn-view">MANUAL VERIFY</button>
                                            </form>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <form method="POST" action="verify-seller.php">
                                            <input type="hidden" name="seller_id" value="<?php echo $uid; ?>">
                                            <button type="submit" name="revoke_seller" class="btn-mini btn-danger" onclick="return confirm('Revoke verification for @<?php echo htmlspecialchars($user['username']); ?>?')">REVOKE</button>
                                        </form>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; font-weight:700; font-style: italic;">NO USERS FOUND.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table></div>

            <?php render_intelligent_pagination($page, $total_pages, 'admin-pagination'); ?>

        </div>
    </main>
</section>

</body>
</html>

