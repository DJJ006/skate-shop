<?php
session_start();
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
    
    $sql = "UPDATE users SET is_verified = 1 WHERE id = $seller_id";
    if ($conn->query($sql) === TRUE) {
        // Send Notification to the User
        $msg = $is_manual 
            ? "Congratulations! An administrator has manually awarded you the VERIFIED SELLER badge!"
            : "Congratulations! You have been awarded the VERIFIED SELLER badge for maintaining a 5-star rating!";
        
        $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notif->bind_param("is", $seller_id, $msg);
        $notif->execute();

        $_SESSION['msg'] = $is_manual ? "SELLER MANUALLY VERIFIED!" : "SELLER VERIFIED (AUTO CRITERIA)!";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: verify-seller.php");
    exit();
}

// --- POST: REVOKE VERIFICATION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['revoke_seller'])) {
    $seller_id = (int)$_POST['seller_id'];
    
    $sql = "UPDATE users SET is_verified = 0 WHERE id = $seller_id";
    if ($conn->query($sql) === TRUE) {
        $msg = "Your VERIFIED SELLER badge has been revoked by an administrator.";
        $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notif->bind_param("is", $seller_id, $msg);
        $notif->execute();

        $_SESSION['msg'] = "VERIFICATION REVOKED & CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: verify-seller.php");
    exit();
}

// Fetch all users to display in the table
$sellers_stmt = $conn->query("SELECT id, username, is_verified FROM users ORDER BY created_at DESC");
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
</head>
<body>

<header class="main-header">
    <div class="container header-content">
        <h1 class="logo"><a href="index.php">SKATE<span>SHOP</span> ADMIN</a></h1>
    </div>
</header>

<section class="admin-layout container">
    <aside class="admin-sidebar grainy-card sidebar-accept" style="margin-top: 2.9rem;">
        <h3 class="admin-sidebar-title">SYSTEM <span class="header-span">MENU</span></h3>
        <ul class="admin-nav-list">
            <li><a href="index.php"><span class="material-icons">dashboard</span> DASHBOARD</a></li>
            <li><a href="shop-products.php"><span class="material-icons">inventory_2</span> THE VAULT (SHOP)</a></li>
            <li><a href="marketplace-products.php"><span class="material-icons">storefront</span> STREET MARKET</a></li>
            <li><a href="registered-users.php"><span class="material-icons">manage_accounts</span> REGISTERED USERS</a></li>
            <li>
                <a href="accept-product.php" class="nav-relative">
                    <span class="material-icons">gavel</span> PENDING GEAR
                    <?php if ($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="verify-seller.php" class="nav-relative active">
                    <span class="material-icons">verified_user</span> TRUST & SAFETY
                </a>
            </li>
            <li><a href="../index.php"><span class="material-icons">public</span> VIEW LIVE SITE</a></li>
        </ul>
    </aside>

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
            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>USERNAME</th>
                        <th>LAST 5 RATINGS</th>
                        <th>STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sellers_stmt->num_rows > 0): ?>
                        <?php while($user = $sellers_stmt->fetch_assoc()): ?>
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
                                <td><strong>@<?php echo htmlspecialchars($user['username']); ?></strong></td>
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
                                                <button type="submit" name="verify_seller" class="btn-mini btn-view" style="font-weight: 900; background-color: #3498db;">MANUAL VERIFY</button>
                                            </form>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <form method="POST" action="verify-seller.php">
                                            <input type="hidden" name="seller_id" value="<?php echo $uid; ?>">
                                            <button type="submit" name="revoke_seller" class="btn-mini btn-danger" onclick="return confirm('Revoke verification for @<?php echo htmlspecialchars($user['username']); ?>?')" style="font-weight: 900;">REVOKE</button>
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
            </table>
        </div>
    </main>
</section>

</body>
</html>