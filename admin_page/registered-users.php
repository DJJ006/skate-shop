<?php 
session_start();
include '../db.php'; 


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target_user = (int)$_POST['user_id'];
    
  
    $del_products = $conn->prepare("DELETE FROM products WHERE seller_id = ?");
    $del_products->bind_param("i", $target_user);
    $del_products->execute();

    $del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del_user->bind_param("i", $target_user);
    $del_user->execute();
    
    $_SESSION['admin_msg'] = "USER AND LISTINGS TERMINATED.";
    header("Location: registered-users.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_block'])) {
    $target_user = (int)$_POST['user_id'];
    
    $block_stmt = $conn->prepare("UPDATE users SET is_blocked = IF(is_blocked = 1, 0, 1) WHERE id = ?");
    $block_stmt->bind_param("i", $target_user);
    $block_stmt->execute();
    
    $_SESSION['admin_msg'] = "USER STATUS UPDATED.";
    header("Location: registered-users.php");
    exit();
}

$users_sql = "SELECT id, username, email, is_blocked FROM users ORDER BY id DESC";
$users_result = $conn->query($users_sql);

$modals_html = [];
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

<header class="main-header">
    <div class="container header-content">
        <h1 class="logo">
            <a href="../admin_page/index.php">SKATE<span>SHOP</span> ADMIN</a>
        </h1>

        <div class="mobile-menu-icon" id="menu-btn">
            <span class="material-icons">menu</span>
        </div>

    </div>
</header>

<section class="admin-layout container">
    
    <aside class="admin-sidebar grainy-card">
        <h3 class="admin-sidebar-title">SYSTEM <span class="header-span">MENU<span></h3>
        <ul class="admin-nav-list">
            <li><a href="index.php"><span class="material-icons">dashboard</span> DASHBOARD</a></li>
            <li><a href="shop-products.php"><span class="material-icons">inventory_2</span> THE VAULT (SHOP)</a></li>
            <li><a href="marketplace-products.php"><span class="material-icons">storefront</span> STREET MARKET</a></li>
            <li><a href="registered-users.php" class="active"><span class="material-icons">manage_accounts</span> REGISTERED USERS</a></li>
            <li><a href="../index.php"><span class="material-icons">public</span> VIEW LIVE SITE</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="shop-header-title">
            <h2 class="glitch-text-admin">REGISTERED <span class="text-primary">USERS</span></h2>
            <p class="admin-text">MANAGE CLIENT ACCOUNTS</p>
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
            <table class="recent-activity-table">
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
                                        <span class="badge-blocked">BLOCKED</span>
                                    <?php else: ?>
                                        <span class="badge-active">ACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="btn-mini btn-view" onclick="openModal('modal-<?php echo $user['id']; ?>')">VIEW LISTINGS</button>

                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="toggle_block" class="btn-mini btn-block">
                                                <?php echo (isset($user['is_blocked']) && $user['is_blocked'] == 1) ? 'UNBLOCK' : 'BLOCK'; ?>
                                            </button>
                                        </form>

                                        <form method="POST" style="margin:0;" onsubmit="return confirm('WARNING: THIS WILL DELETE THE USER AND ALL THEIR MARKETPLACE ITEMS. CONTINUE?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn-mini btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <?php
                            $market_sql = "SELECT id, title, price, image_url FROM products WHERE seller_id = " . $user['id'] . " AND is_marketplace = 1";
                            $market_res = $conn->query($market_sql);

                            ob_start();
                            ?>
                            <div id="modal-<?php echo $user['id']; ?>" class="modal-overlay">
                                <div class="modal-content">
                                    <span class="close-modal" onclick="closeModal('modal-<?php echo $user['id']; ?>')">&times;</span>
                                    <h3 class="admin-table-h3 top-title"><?php echo htmlspecialchars($user['username']); ?>'S <span class="header-span">GEAR</span></h3>
                                    
                                    <div class="listings-grid">
                                        <?php if($market_res && $market_res->num_rows > 0): ?>
                                            <?php while($item = $market_res->fetch_assoc()): ?>
                                                <div class="mini-listing">
                                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="Gear">
                                                    <div class="mini-listing-info">
                                                        <h4 class="mini-listing-title"><?php echo htmlspecialchars($item['title']); ?></h4>
                                                        <p class="mini-listing-price">$<?php echo number_format($item['price'], 2); ?></p>
                                                    </div>
                                                    <span class="mini-listing-id">#<?php echo $item['id']; ?></span>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <div class="empty-placeholder-box">
                                                <h4 class="empty-placeholder-title">NO GEAR LISTED</h4>
                                                <p class="empty-placeholder-text">This user hasn't added anything to the street market.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $modals_html[] = ob_get_clean();
                            ?>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">NO USERS FOUND.</td>
                        </tr>
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