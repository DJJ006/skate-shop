<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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