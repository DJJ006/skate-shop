<?php 
// Adjust the path to your db.php since this is inside an 'admin' folder
include '../db.php';

$count_sql = "SELECT COUNT(*) as pending_count FROM products WHERE is_marketplace = 1 AND is_approved = 0";
$count_result = $conn->query($count_sql);
$pending_count = 0;

if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $pending_count = (int)$count_row['pending_count'];
}


// Fetch basic statistics from the database
$shop_count_res = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_marketplace = 0");
$shop_count = $shop_count_res->fetch_assoc()['count'];

$market_count_res = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_marketplace = 1");
$market_count = $market_count_res->fetch_assoc()['count'];

$total_items = $shop_count + $market_count;

// Fetch the 5 most recently added items for the activity feed
$recent_sql = "SELECT title, price, is_marketplace, created_at FROM products ORDER BY created_at DESC LIMIT 5";
$recent_result = $conn->query($recent_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | ADMIN COMMAND CENTER</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
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
            <li><a href="index.php" class="active"><span class="material-icons">dashboard</span> DASHBOARD</a></li>
            <li><a href="shop-products.php"><span class="material-icons">inventory_2</span> THE VAULT (SHOP)</a></li>
            <li><a href="marketplace-products.php"><span class="material-icons">storefront</span> STREET MARKET</a></li>
            <li><a href="registered-users.php"><span class="material-icons">manage_accounts</span> REGISTERED USERS</a></li>
            <li><a href="client-orders.php"><span class="material-icons">receipt_long</span> CLIENT ORDERS</a></li>
            <li>
                <a href="accept-product.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'accept-product.php') ? '' : ''; ?>" style="position: relative;">
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
        <div class="shop-header-title">
            <h2 class="glitch-text-admin">COMMAND <span class="text-primary">CENTER</span></h2>
            <p class="admin-text">SYSTEM OVERVIEW & STATISTICS</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card grainy-card">
                <h3><?php echo $total_items; ?></h3>
                <p>TOTAL GEAR IN SYSTEM</p>
            </div>
            <div class="stat-card grainy-card">
                <h3><?php echo $shop_count; ?></h3>
                <p>OFFICIAL VAULT ITEMS</p>
            </div>
            <div class="stat-card grainy-card">
                <h3><?php echo $market_count; ?></h3>
                <p>MARKETPLACE LISTINGS</p>
            </div>
        </div>

        <div class="grainy-card" style="padding: 20px;">
            <h3 class="admin-table-h3">RECENTLY <span class="header-span">ADDED</span> GEAR</h3>
            
            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ITEM</th>
                        <th>PRICE</th>
                        <th>TYPE</th>
                        <th>ADDED ON</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_result->num_rows > 0): ?>
                        <?php while($row = $recent_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                <td>$<?php echo number_format($row['price'], 2); ?></td>
                                <td>
                                    <?php if($row['is_marketplace'] == 1): ?>
                                        <span class="badge-market">MARKET</span>
                                    <?php else: ?>
                                        <span class="badge-shop">SHOP</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">NO GEAR ADDED YET.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</section>

</body>
</html>