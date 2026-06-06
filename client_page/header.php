<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Release checkout cart lock when user navigates away from checkout.php
if (basename($_SERVER['PHP_SELF']) !== 'checkout.php') {
    unset($_SESSION['cart_locked']);
}

// --- Active Nav State Logic ---
$current_page = basename($_SERVER['PHP_SELF']);
$active_nav = '';

if (in_array($current_page, ['shop.php', 'checkout.php', 'success.php'])) {
    $active_nav = 'shop';
} elseif (in_array($current_page, ['marketplace.php'])) {
    $active_nav = 'market';
} elseif (in_array($current_page, ['community.php', 'reels.php', 'shoutouts.php', 'qna.php', 'the-mag.php', 'mag-article.php', 'mag-api.php', 'user-profile.php'])) {
    $active_nav = 'community';
} elseif ($current_page === 'client-profile.php') {
    $active_nav = 'profile';
} elseif ($current_page === 'product.php') {
    // Rely on $isMarketplace flag set in product.php before including header
    if (isset($isMarketplace) && $isMarketplace) {
        $active_nav = 'market';
    } else {
        $active_nav = 'shop';
    }
}
?>
<header class="main-header">
    <div class="container header-content">
        <h1 class="logo">
            <a href="index.php">SKATE<span>SHOP</span></a>
        </h1>

        <div class="mobile-menu-icon" id="menu-btn">
            <span class="material-icons">menu</span>
        </div>

        <nav class="desktop-nav" id="nav-menu">
            <ul class="nav-links">
                <li><a href="shop.php" class="nav-item <?php echo ($active_nav === 'shop') ? 'active-nav' : ''; ?>">SHOP</a></li>
                <li><a href="marketplace.php" class="nav-item <?php echo ($active_nav === 'market') ? 'active-nav' : ''; ?>">MARKET</a></li>
                <li><a href="community.php" class="nav-item <?php echo ($active_nav === 'community') ? 'active-nav' : ''; ?>">COMMUNITY</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li>
                    <a href="#" class="nav-item" id="cartIcon" style="position: relative;">
                        <i class="fa-solid fa-shopping-cart"></i> CART
                        <span class="cart-badge" id="cartCountBadge" style="display: none;">0</span>
                    </a>
                </li>
                <?php endif; ?>
                


                <?php if(isset($_SESSION['user_id'])): ?>
                    <li>
                        <a href="client-profile.php" class="nav-item <?php echo ($active_nav === 'profile') ? 'active-nav' : ''; ?>" style="color: var(--primary); display: flex; align-items: center; gap: 5px; padding-bottom: .2rem;">
                            @<?php 
                                $display_name = $_SESSION['username'];
                                if (mb_strlen($display_name) > 5) {
                                    $display_name = mb_substr($display_name, 0, 5) . '...';
                                }
                                echo htmlspecialchars($display_name); 
                            ?>
                        </a>
                    </li>
                    <li><a href="logout.php" class="login-btn">LOGOUT</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="login-btn">LOG IN</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<?php if (isset($_SESSION['user_id'])): ?>
<!-- Shopping Cart Drawer -->
<div class="cart-drawer-overlay" id="cartOverlay"></div>
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-drawer-header">
        <h2 class="cart-drawer-title">YOUR <span class="text-primary">CART</span></h2>
        <button class="cart-close-btn" id="closeCartBtn">&times;</button>
    </div>
    
    <div class="cart-items-container" id="cartItemsContainer">
        
    </div>
    
    <div class="cart-drawer-footer">
        <div class="cart-subtotal-row">
            <span>SUBTOTAL:</span>
            <span class="cart-subtotal-value" id="cartSubtotalValue">$0.00</span>
        </div>
        <div class="cart-actions">
            <button class="btn btn-outline cart-btn" id="clearCartBtn">CLEAR</button>
            <a href="checkout.php" class="btn btn-primary cart-btn" id="checkoutBtn" style="text-decoration: none;">CHECKOUT</a>
        </div>
    </div>
</div>
<?php endif; ?>

<link rel="stylesheet" href="../assets/cart.css">
<script src="../assets/cart.js" defer></script>
