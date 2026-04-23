<?php
session_start();
include '../db.php';


$count_sql = "SELECT COUNT(*) as pending_count FROM products WHERE is_marketplace = 1 AND is_approved = 0";
$count_result = $conn->query($count_sql);
$pending_count = 0;

if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $pending_count = (int)$count_row['pending_count'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_product'])) {
    $id = (int)$_POST['product_id'];
    
    $info_stmt = $conn->prepare("SELECT title, seller_id FROM products WHERE id = ?");
    $info_stmt->bind_param("i", $id);
    $info_stmt->execute();
    $prod = $info_stmt->get_result()->fetch_assoc();

    $sql = "UPDATE products SET is_approved = 1 WHERE id = $id AND is_marketplace = 1";
    if ($conn->query($sql) === TRUE) {
        
        $msg = "Your product '" . $prod['title'] . "' has been approved and is now live!";
        $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notif->bind_param("is", $prod['seller_id'], $msg);
        $notif->execute();

        $_SESSION['msg'] = "LISTING APPROVED! CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: accept-product.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_product_final'])) {
    $id = (int)$_POST['product_id'];
    $reason = $conn->real_escape_string($_POST['rejection_reason']);

    $info_stmt = $conn->prepare("SELECT title, seller_id FROM products WHERE id = ?");
    $info_stmt->bind_param("i", $id);
    $info_stmt->execute();
    $prod = $info_stmt->get_result()->fetch_assoc();

    $sql = "DELETE FROM products WHERE id = $id AND is_marketplace = 1";
    if ($conn->query($sql) === TRUE) {
        
        $msg = "Your product '" . $prod['title'] . "' was rejected. Reason: " . $reason;
        $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notif->bind_param("is", $prod['seller_id'], $msg);
        $notif->execute();

        $_SESSION['msg'] = "LISTING REJECTED & CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: accept-product.php");
    exit();
}

$pending_sql = "SELECT id, title, price, category, condition_badge, brand, description, image_url, seller_name, created_at 
                FROM products 
                WHERE is_marketplace = 1 AND is_approved = 0 
                ORDER BY created_at ASC";
$pending_result = $conn->query($pending_sql);

$modals_html = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | PENDING GEAR</title>
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
        <h3 class="admin-sidebar-title">SYSTEM <span class="header-span">MENU<span></h3>
        <ul class="admin-nav-list">
            <li><a href="index.php"><span class="material-icons">dashboard</span> DASHBOARD</a></li>
            <li><a href="shop-products.php"><span class="material-icons">inventory_2</span> THE VAULT (SHOP)</a></li>
            <li><a href="marketplace-products.php"><span class="material-icons">storefront</span> STREET MARKET</a></li>
            <li><a href="registered-users.php"><span class="material-icons">manage_accounts</span> REGISTERED USERS</a></li>
            <li>
                <a href="accept-product.php" class="nav-relative <?php echo (basename($_SERVER['PHP_SELF']) == 'accept-product.php') ? 'active' : ''; ?>">
                    <span class="material-icons">gavel</span> PENDING GEAR
                    <?php if ($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="verify-seller.php" class="nav-relative">
                    <span class="material-icons">verified_user</span> TRUST & SAFETY
                </a>
            </li>
            <li><a href="../index.php"><span class="material-icons">public</span> VIEW LIVE SITE</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">PENDING <span class="text-primary">APPROVALS</span></h2>
                <p class="admin-text-shop">REVIEW CLIENT SUBMITTED MARKETPLACE GEAR</p>
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
        <h3 class="admin-table-h3">PENDING <span class="header-span">ITEMS</span></h3>
            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>SELLER</th>
                        <th>ITEM NAME</th>
                        <th>PRICE</th>
                        <th>REVIEW</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pending_result->num_rows > 0): ?>
                        <?php while($item = $pending_result->fetch_assoc()): 
                            $submit_time = isset($item['created_at']) ? date("M j, Y @ H:i", strtotime($item['created_at'])) : 'N/A';
                            $is_blocked = (isset($item['user_status']) && strtolower($item['user_status']) === 'blocked') || (isset($item['is_blocked']) && $item['is_blocked'] == 1);
                            $status_text = $is_blocked ? 'BLOCKED' : 'ACTIVE';
                            $status_class = $is_blocked ? 'status-blocked' : 'status-active';
                        ?>
                            <tr>
                                <td class="td-id">#<?php echo $item['id']; ?></td>
                                <td>
                                    <strong>@<?php echo htmlspecialchars($item['seller_name']); ?></strong><br>
                                </td>
                                <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                <td>$<?php echo number_format($item['price'], 2); ?></td>
                                
                                <td>
                                    <button class="btn-mini btn-view" onclick="openModal('review-<?php echo $item['id']; ?>')">VIEW DETAILS</button>
                                </td>
                            </tr>

                            <?php ob_start(); ?>
                            <div id="review-<?php echo $item['id']; ?>" class="modal-overlay">
                                <div class="modal-content modal-content-lg">
                                    <span class="close-modal" onclick="closeModal('review-<?php echo $item['id']; ?>')">&times;</span>
                                    <h3 class="admin-table-h3 top-title">REVIEW <span class="header-span">LISTING</span></h3>
                                    
                                    <div class="modal-grid">
                                        <div>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="Gear" class="modal-img">
                                            <div class="modal-date-box">
                                                SUBMITTED:<br><?php echo $submit_time; ?>
                                            </div>
                                        </div>

                                        <div class="modal-details">
                                            <div class="detail-row">
                                                <span class="detail-label">SELLER:</span>
                                                <span>
                                                    @<?php echo htmlspecialchars($item['seller_name']); ?>
                                                    
                                                </span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">ACCOUNT STATUS:</span>
                                                <span class="badge-status <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            <div class="detail-row"><span class="detail-label">TITLE:</span> <span><?php echo htmlspecialchars($item['title']); ?></span></div>
                                            <div class="detail-row"><span class="detail-label">BRAND:</span> <span><?php echo htmlspecialchars($item['brand']); ?></span></div>
                                            <div class="detail-row"><span class="detail-label">CATEGORY:</span> <span><?php echo htmlspecialchars($item['category']); ?></span></div>
                                            <div class="detail-row"><span class="detail-label">CONDITION:</span> <span><?php echo htmlspecialchars($item['condition_badge']); ?></span></div>
                                            <div class="detail-row"><span class="detail-label">PRICE:</span> <span class="price-bold">$<?php echo number_format($item['price'], 2); ?></span></div>
                                        </div>
                                    </div>
                                    
                                    <div class="modal-description-box">
                                        <strong>Description:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                    </div>

                                    <div class="modal-actions">
                                        <form method="POST" action="accept-product.php">
                                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="accept_product" class="btn btn-accept btn-full btn-heavy-font">ACCEPT & PUBLISH</button>
                                        </form>

                                        <button type="button" class="btn btn-danger btn-full btn-heavy-font" onclick="openModal('reject-reason-<?php echo $item['id']; ?>')">
                                            REJECT / DELETE
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php $modals_html[] = ob_get_clean(); ?>

                            <?php ob_start(); ?>
                                <div id="reject-reason-<?php echo $item['id']; ?>" class="modal-overlay modal-top-layer">
                                    <div class="modal-content">
                                        <span class="close-modal" onclick="closeModal('reject-reason-<?php echo $item['id']; ?>')">&times;</span>
                                        <h3 class="admin-table-h3">REJECTION <span class="header-span">REASON</span></h3>
                                        
                                        <form method="POST" action="accept-product.php">
                                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                            
                                            <div class="admin-form-group">
                                                <label class="admin-form-label">WHY ARE YOU REJECTING THIS GEAR?</label>
                                                <textarea 
                                                    name="rejection_reason" 
                                                    rows="4" 
                                                    class="admin-input-dark" 
                                                    placeholder="E.g. Photos are too blurry..." 
                                                    required></textarea>
                                            </div>
                                            
                                            <button type="submit" name="reject_product_final" class="btn btn-danger btn-full btn-heavy-font">
                                                SEND REJECTION & DELETE
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php $modals_html[] = ob_get_clean(); ?>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; font-weight:700; font-style: italic;">NO PENDING LISTINGS TO REVIEW.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</section>

<?php 
    foreach ($modals_html as $html) {
        echo $html;
    }
?>



</body>
</html>