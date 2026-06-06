<?php
// admin_header.php
// Expected to be included after admin_auth.php where $_SESSION['admin_username'] is guaranteed to be set
?>
<header class="main-header">
    <div class="container header-content" style="display:flex; justify-content: space-between; align-items: center;">
        <h1 class="logo">
            <a href="index.php">SKATE<span>SHOP</span> ADMIN</a>
        </h1>

        <div style="display: flex; align-items: center; gap: 20px;">


            <div class="admin-user-menu" style="position: relative;">
                <div class="admin-user-trigger" onclick="document.getElementById('adminDropdown').classList.toggle('active')" style="cursor:pointer; display:flex; align-items:center; gap:8px; font-family:'Staatliches', sans-serif; font-size:1.2rem; color:#fff; background: var(--primary); padding: 5px 15px; border: 3px solid #000; box-shadow: 3px 3px 0px #000; transition: transform 0.1s;">
                    <i class="fas fa-user-shield" style="color:#fff;"></i>
                    @<?php 
                        $raw_uname = $_SESSION['admin_username'] ?? 'ADMIN';
                        $display_uname = (mb_strlen($raw_uname) > 5) ? mb_substr($raw_uname, 0, 5) . '...' : $raw_uname;
                        echo htmlspecialchars($display_uname); 
                    ?>
                    <i class="fas fa-chevron-down" style="font-size:0.8rem;"></i>
                </div>
                
                <div id="adminDropdown" class="admin-dropdown">
                    <a href="edit-profile.php" style="display:block; padding:10px 15px; color:#000; text-decoration:none; font-family:'Inter', sans-serif; font-weight:600; border-bottom:2px dashed #ccc;"><i class="fas fa-user-edit" style="width:20px; color:var(--primary);"></i> EDIT PROFILE</a>
                    <a href="logout.php" style="display:block; padding:10px 15px; color:#000; text-decoration:none; font-family:'Inter', sans-serif; font-weight:600;"><i class="fas fa-sign-out-alt" style="width:20px; color:var(--primary);"></i> LOGOUT</a>
                </div>
            </div>

            <div class="mobile-menu-icon" id="menu-btn">
                <span class="material-icons">menu</span>
            </div>
        </div>
    </div>
</header>

<script>
// Click outside to close dropdown
document.addEventListener('click', function(e) {
    const trigger = document.querySelector('.admin-user-trigger');
    const dropdown = document.getElementById('adminDropdown');
    if (trigger && dropdown) {
        if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    }

    const menuBtn = document.getElementById('menu-btn');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (menuBtn && sidebar && overlay) {
        if (menuBtn.contains(e.target) || menuBtn === e.target) {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            const icon = menuBtn.querySelector('.material-icons');
            if (icon) {
                icon.textContent = sidebar.classList.contains('active') ? 'close' : 'menu';
            }
        } else if (overlay.contains(e.target) || overlay === e.target) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            const icon = menuBtn.querySelector('.material-icons');
            if (icon) {
                icon.textContent = 'menu';
            }
        }
    }
});
</script>
<style>
    .admin-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border: 3px solid #000;
        box-shadow: 4px 4px 0px #000;
        z-index: 1000;
        min-width: 150px;
        margin-top: 10px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease-in-out;
    }
    
    .admin-user-menu:hover .admin-dropdown,
    .admin-dropdown.active { 
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .admin-user-trigger:hover {
        transform: translate(-2px, -2px);
        box-shadow: 5px 5px 0px #000 !important;
    }

    .admin-dropdown a:hover { 
        background: #f0f0f0; 
        color: var(--primary); 
    }
</style>

