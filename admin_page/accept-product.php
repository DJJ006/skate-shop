<?php
require_once 'admin_auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_product'])) {
    $id = (int)$_POST['product_id'];
    
    $info_stmt = $conn->prepare("SELECT title, seller_id FROM products WHERE id = ?");
    $info_stmt->bind_param("i", $id);
    $info_stmt->execute();
    $prod = $info_stmt->get_result()->fetch_assoc();

    $sql = "UPDATE products SET is_approved = 1 WHERE id = $id AND is_marketplace = 1";
    if ($conn->query($sql) === TRUE) {
        
        $msg = "Your product '" . $prod['title'] . "' has been approved and is now live!";
        sendAppNotification($conn, $prod['seller_id'], $msg);

        $_SESSION['msg'] = "LISTING APPROVED! CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: accept-product.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_product_final'])) {
    $id = (int)$_POST['product_id'];
    $reason = trim($_POST['rejection_reason']);

    if (mb_strlen($reason) > 500) {
        $_SESSION['msg'] = "REJECTION REASON CANNOT EXCEED 500 CHARACTERS.";
        $_SESSION['msg_type'] = "error";
        header("Location: accept-product.php");
        exit();
    }
    
    $reason = $conn->real_escape_string($reason);

    $info_stmt = $conn->prepare("SELECT title, seller_id FROM products WHERE id = ?");
    $info_stmt->bind_param("i", $id);
    $info_stmt->execute();
    $prod = $info_stmt->get_result()->fetch_assoc();

    $sql = "DELETE FROM products WHERE id = $id AND is_marketplace = 1";
    if ($conn->query($sql) === TRUE) {
        
        $msg = "Your product '" . $prod['title'] . "' was rejected. Reason: " . $reason;
        sendAppNotification($conn, $prod['seller_id'], $msg);

        $_SESSION['msg'] = "LISTING REJECTED & CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: accept-product.php");
    exit();
}

// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_sql = "SELECT COUNT(*) as total FROM products WHERE is_marketplace=1 AND is_approved=0";
$count_res = $conn->query($count_sql);
$total_records = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$pending_sql = "SELECT id, title, price, category, condition_badge, brand, description, image_url, seller_name, created_at 
                FROM products 
                WHERE is_marketplace = 1 AND is_approved = 0 
                ORDER BY created_at ASC LIMIT $limit OFFSET $offset";
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
            <div class="table-responsive"><table class="recent-activity-table">
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
                                                    required maxlength="500"></textarea>
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
            </table></div>

            <?php render_intelligent_pagination($page, $total_pages, 'admin-pagination'); ?>
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

