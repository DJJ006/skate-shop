<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Release checkout cart lock when user navigates away from checkout.php
if (basename($_SERVER['PHP_SELF']) !== 'checkout.php') {
    unset($_SESSION['cart_locked']);
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
                <li><a href="shop.php" class="nav-item">SHOP</a></li>
                <li><a href="marketplace.php" class="nav-item">MARKET</a></li>
                <li><a href="#" class="nav-item">COMMUNITY</a></li>
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
                        <a href="client-profile.php" class="nav-item" style="color: var(--primary); display: flex; align-items: center; gap: 5px; padding-bottom: .2rem;">
                            
                            @<?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                    </li>
                    <li><a href="logout.php" class="login-btn">LOGOUT</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="login-btn">LOGIN</a></li>
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