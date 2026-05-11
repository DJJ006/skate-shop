<?php
// Default counters
$pending_gear_count = 0;
$pending_reels_count = 0;

if (isset($conn)) {
    // Count pending gear
    $count_gear_sql = "SELECT COUNT(*) as pending_count FROM products WHERE is_marketplace = 1 AND is_approved = 0";
    $count_gear_result = $conn->query($count_gear_sql);
    if ($count_gear_result) {
        $count_row = $count_gear_result->fetch_assoc();
        $pending_gear_count = (int)$count_row['pending_count'];
    }

    // Count pending reels
    $count_reels_sql = "SELECT COUNT(*) as pending_count FROM reels WHERE is_approved = 0";
    $count_reels_result = $conn->query($count_reels_sql);
    if ($count_reels_result) {
        $count_row = $count_reels_result->fetch_assoc();
        $pending_reels_count = (int)$count_row['pending_count'];
    }

    // Count pending reel edits
    $count_edits_sql = "SELECT COUNT(*) as pending_count FROM reel_edit_requests WHERE status = 'pending'";
    $count_edits_result = $conn->query($count_edits_sql);
    if ($count_edits_result) {
        $count_row = $count_edits_result->fetch_assoc();
        $pending_reels_count += (int)$count_row['pending_count'];
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="admin-sidebar grainy-card">
    <h3 class="admin-sidebar-title">SYSTEM <span class="header-span">MENU</span></h3>
    <ul class="admin-nav-list">
        <li><a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"><span class="material-icons">dashboard</span> DASHBOARD</a></li>
        <li><a href="shop-products.php" class="<?php echo ($current_page == 'shop-products.php') ? 'active' : ''; ?>"><span class="material-icons">inventory_2</span> THE VAULT (SHOP)</a></li>
        <li><a href="marketplace-products.php" class="<?php echo ($current_page == 'marketplace-products.php') ? 'active' : ''; ?>"><span class="material-icons">storefront</span> STREET MARKET</a></li>
        <li><a href="registered-users.php" class="<?php echo ($current_page == 'registered-users.php') ? 'active' : ''; ?>"><span class="material-icons">manage_accounts</span> REGISTERED USERS</a></li>
        <li><a href="client-orders.php" class="<?php echo ($current_page == 'client-orders.php') ? 'active' : ''; ?>"><span class="material-icons">receipt_long</span> CLIENT ORDERS</a></li>
        <li>
            <a href="accept-product.php" class="nav-relative <?php echo ($current_page == 'accept-product.php') ? 'active' : ''; ?>" style="position: relative;">
                <span class="material-icons">gavel</span> PENDING GEAR
                <?php if ($pending_gear_count > 0): ?>
                    <span class="notification-badge"><?php echo $pending_gear_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="accept-reels.php" class="nav-relative <?php echo ($current_page == 'accept-reels.php') ? 'active' : ''; ?>" style="position: relative;">
                <span class="material-icons">movie_filter</span> PENDING REELS
                <?php if ($pending_reels_count > 0): ?>
                    <span class="notification-badge"><?php echo $pending_reels_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="reels.php" class="nav-relative <?php echo ($current_page == 'reels.php') ? 'active' : ''; ?>">
                <span class="material-icons">video_library</span> REELS MGMT
            </a>
        </li>
        <li>
            <a href="verify-seller.php" class="nav-relative <?php echo ($current_page == 'verify-seller.php') ? 'active' : ''; ?>">
                <span class="material-icons">verified_user</span> TRUST & SAFETY
            </a>
        </li>
        <li><a href="../index.php"><span class="material-icons">public</span> VIEW LIVE SITE</a></li>
    </ul>
</aside>
