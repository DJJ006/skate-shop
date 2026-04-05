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


// --- HANDLE ADD FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])){
    $title = $conn->real_escape_string($_POST['title']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $is_marketplace = 1; // Set to 1 for Street Market

    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $target_dir = "../assets/uploads/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid('market_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($file_extension), $allowed_types)){
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_url = "../assets/uploads/" . $new_filename;
            }
        }
    }

    if ($image_url != '') {
        $sql = "INSERT INTO products (title, price, category, description, image_url, is_marketplace) 
                VALUES ('$title', '$price', '$category', '$description', '$image_url', '$is_marketplace')";
        if ($conn->query($sql) === TRUE) {
            $_SESSION['msg'] = "ITEM ADDED TO THE STREET MARKET!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "DATABASE ERROR: " . $conn->error;
            $_SESSION['msg_type'] = "error";
        }
    } else {
        $_SESSION['msg'] = "ERROR: INVALID IMAGE OR NO IMAGE UPLOADED.";
        $_SESSION['msg_type'] = "error";
    }
    
    // REDIRECT to clear POST data
    header("Location: marketplace-products.php");
    exit();
}

// --- HANDLE EDIT FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $id = (int)$_POST['product_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);

    $image_query_part = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../assets/uploads/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid('market_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_url = "../assets/uploads/" . $new_filename;
                $image_query_part = ", image_url='$image_url'";
            }
        }
    }

    $sql = "UPDATE products SET title='$title', price='$price', category='$category', description='$description' $image_query_part WHERE id=$id AND is_marketplace=1";
    if ($conn->query($sql) === TRUE) {
        $_SESSION['msg'] = "MARKET ITEM UPDATED SUCCESSFULLY!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "ERROR UPDATING MARKET ITEM.";
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: marketplace-products.php");
    exit();
}

// --- HANDLE DELETE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $id = (int)$_POST['product_id'];
    $sql = "DELETE FROM products WHERE id=$id AND is_marketplace=1";
    if ($conn->query($sql) === TRUE) {
        $_SESSION['msg'] = "MARKET ITEM TRASHED.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "ERROR DELETING ITEM.";
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: marketplace-products.php");
    exit();
}

// --- HANDLE SEARCH & FILTER ---
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$cat_filter = isset($_GET['filter_category']) ? $_GET['filter_category'] : []; 
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// ONLY PULL MARKETPLACE ITEMS
$where_clauses = ["is_marketplace = 1"];

if (!empty($search)) { 
    $where_clauses[] = "(title LIKE '%$search%' OR id LIKE '%$search%')"; 
}

if (!empty($cat_filter) && is_array($cat_filter)) {
    $sanitized_cats = array_map(function($c) use ($conn) {
        return "'" . $conn->real_escape_string($c) . "'";
    }, $cat_filter);
    
    $cat_list = implode(',', $sanitized_cats);
    $where_clauses[] = "category IN ($cat_list)";
}

$where_sql = implode(' AND ', $where_clauses);

$allowed_sorts = ['id', 'title', 'price', 'created_at'];
if (!in_array($sort_by, $allowed_sorts)) { $sort_by = 'created_at'; }

$products_sql = "SELECT id, title, price, category, description, created_at 
                 FROM products WHERE $where_sql ORDER BY $sort_by $order";
$products_result = $conn->query($products_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | STREET MARKET</title>
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
        <h1 class="logo"><a href="index.php">SKATE<span>SHOP</span> ADMIN</a></h1>
    </div>
</header>

<section class="admin-layout container">
    <aside class="admin-sidebar grainy-card">
        <h3 class="admin-sidebar-title">SYSTEM <span class="header-span">MENU<span></h3>
        <ul class="admin-nav-list">
            <li><a href="index.php"><span class="material-icons">dashboard</span> DASHBOARD</a></li>
            <li><a href="shop-products.php"><span class="material-icons">inventory_2</span> THE VAULT (SHOP)</a></li>
            <li><a href="marketplace-products.php" class="active"><span class="material-icons">storefront</span> STREET MARKET</a></li>
            <li><a href="registered-users.php"><span class="material-icons">manage_accounts</span> REGISTERED USERS</a></li>
            <li>
                <a href="accept-product.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'accept-product.php') ? '' : ''; ?>" style="position: relative;">
                    <span class="material-icons">gavel</span> PENDING GEAR
                    <?php if ($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="../index.php"><span class="material-icons">public</span> VIEW LIVE SITE</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">STREET <span class="text-primary">MARKET</span></h2>
                <p class="admin-text-shop">COMMUNITY GENERATED INVENTORY MANAGEMENT</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('addModal')">+ ADD MARKET ITEM</button>
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

        <div class="grainy-card" style="padding: 20px;">
            <h3 class="admin-table-h3">MARKET <span class="header-span">LISTINGS</span></h3>
            
            <div class="grainy-card filter-bar">
                <form method="GET" action="marketplace-products.php" class="search-filter-form">
                    <div class="filter-group search-box">
                        <label>SEARCH</label>
                        <input type="text" name="search" placeholder="ID or Product Name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label>CATEGORIES</label>
                        <div class="custom-multiselect" id="categoryDropdown">
                            <div class="select-box" onclick="toggleDropdown()">
                                <span id="selectText">
                                    <?php 
                                    if(!empty($cat_filter)) {
                                        echo count($cat_filter) . " SELECTED";
                                    } else {
                                        echo "ALL GEAR";
                                    }
                                    ?>
                                </span>
                                <span class="material-icons">expand_more</span>
                            </div>
                            <div class="dropdown-content" id="dropdownOptions">
                                <?php 
                                $options = ['Decks', 'Trucks', 'Wheels', 'Bearings', 'Apparel'];
                                foreach($options as $opt): 
                                    $checked = (is_array($cat_filter) && in_array($opt, $cat_filter)) ? 'checked' : '';
                                ?>
                                    <label class="dropdown-item">
                                        <input type="checkbox" name="filter_category[]" value="<?php echo $opt; ?>" <?php echo $checked; ?> onchange="this.form.submit()">
                                        <span><?php echo strtoupper($opt); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>SORT BY</label>
                        <select name="sort_by" onchange="this.form.submit()">
                            <option value="created_at" <?php if($sort_by == 'created_at') echo 'selected'; ?>>NEWEST</option>
                            <option value="id" <?php if($sort_by == 'id') echo 'selected'; ?>>ID NUMBER</option>
                            <option value="title" <?php if($sort_by == 'title') echo 'selected'; ?>>PRODUCT NAME</option>
                            <option value="price" <?php if($sort_by == 'price') echo 'selected'; ?>>PRICE</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>ORDER</label>
                        <select name="order" onchange="this.form.submit()">
                            <option value="DESC" <?php if($order == 'DESC') echo 'selected'; ?>>DESC</option>
                            <option value="ASC" <?php if($order == 'ASC') echo 'selected'; ?>>ASC</option>
                        </select>
                    </div>

                    <div class="filter-actions" style="margin-top:22px;">
                        <button type="submit" class="btn-filter">FILTER</button>
                        <a href="marketplace-products.php" class="btn-reset">RESET</a>
                    </div>
                </form>
            </div>
            
            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ITEM NAME</th>
                        <th>CATEGORY</th>
                        <th>PRICE</th>
                        <th>EDIT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products_result->num_rows > 0): ?>
                        <?php while($row = $products_result->fetch_assoc()): ?>
                            <tr class="clickable-row" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['title'])); ?>', <?php echo $row['price']; ?>, '<?php echo addslashes(htmlspecialchars($row['category'])); ?>', '<?php echo addslashes(htmlspecialchars($row['description'])); ?>')">
                                <td class="td-id">#<?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                <td><span class="badge-market"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                <td>$<?php echo number_format($row['price'], 2); ?></td>
                                <td class="edit-button"><span class="material-icons edit-icon">edit</span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; font-weight:700; font-style: italic;">THE STREET MARKET IS CURRENTLY EMPTY.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</section>

<div id="addModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
        <h3 class="admin-table-h3">ADD <span class="header-span">NEW</span> LISTING</h3>
        <form action="marketplace-products.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <label class="top-title">ITEM TITLE</label>
            <input type="text" name="title" required>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div><label>PRICE ($)</label><input type="number" name="price" step="0.01" required></div>
                <div>
                    <label>CATEGORY</label>
                    <select name="category" required>
                        <option value="Decks">DECKS</option>
                        <option value="Trucks">TRUCKS</option>
                        <option value="Wheels">WHEELS</option>
                        <option value="Bearings">BEARINGS</option>
                        <option value="Apparel">APPAREL</option>
                    </select>
                </div>
            </div>
            <label>DESCRIPTION</label>
            <textarea name="description" rows="3" required></textarea>
            <label>ITEM IMAGE</label>
            <input type="file" name="image" accept="image/*" required>
            <button type="submit" name="add_product" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;">PUBLISH TO MARKET</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">LISTING</span></h3>
        <form action="marketplace-products.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <input type="hidden" id="edit_id" name="product_id">
            <label class="top-title">ITEM TITLE</label>
            <input type="text" id="edit_title" name="title" required>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div><label>PRICE ($)</label><input type="number" id="edit_price" name="price" step="0.01" required></div>
                <div>
                    <label>CATEGORY</label>
                    <select id="edit_category" name="category" required>
                        <option value="Decks">DECKS</option>
                        <option value="Trucks">TRUCKS</option>
                        <option value="Wheels">WHEELS</option>
                        <option value="Bearings">BEARINGS</option>
                        <option value="Apparel">APPAREL</option>
                    </select>
                </div>
            </div>
            <label>DESCRIPTION</label>
            <textarea id="edit_description" name="description" rows="3" required></textarea>
            <label>CHANGE IMAGE (OPTIONAL)</label>
            <input type="file" name="image" accept="image/*">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-top: 20px;">
                <button type="submit" name="edit_product" class="btn btn-primary">SAVE CHANGES</button>
                <button type="submit" name="delete_product" class="btn btn-danger" onclick="return confirm('PERMANENTLY TRASH THIS ITEM?');">
                    <span class="material-icons" style="vertical-align: middle;">delete</span>
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>