<?php
include '../db.php';

$message = '';
$message_type = '';

// --- HANDLE ADD FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $is_marketplace = 0;

    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../assets/uploads/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid('gear_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_url = "../assets/uploads/" . $new_filename;
            }
        }
    }

    if ($image_url != '') {
        $sql = "INSERT INTO products (title, price, category, description, image_url, is_marketplace) 
                VALUES ('$title', '$price', '$category', '$description', '$image_url', '$is_marketplace')";
        if ($conn->query($sql) === TRUE) {
            $message = "GEAR ADDED TO THE VAULT!";
            $message_type = "success";
        } else {
            $message = "DATABASE ERROR: " . $conn->error;
            $message_type = "error";
        }
    } else {
        $message = "ERROR: INVALID IMAGE OR NO IMAGE UPLOADED.";
        $message_type = "error";
    }
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
        $new_filename = uniqid('gear_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_url = "../assets/uploads/" . $new_filename;
                $image_query_part = ", image_url='$image_url'";
            }
        }
    }

    $sql = "UPDATE products SET title='$title', price='$price', category='$category', description='$description' $image_query_part WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        $message = "GEAR UPDATED SUCCESSFULLY!";
        $message_type = "success";
    } else {
        $message = "ERROR UPDATING GEAR.";
        $message_type = "error";
    }
}

// --- HANDLE DELETE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $id = (int)$_POST['product_id'];
    $sql = "DELETE FROM products WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        $message = "GEAR TRASHED.";
        $message_type = "success";
    } else {
        $message = "ERROR DELETING GEAR.";
        $message_type = "error";
    }
}

// --- HANDLE SEARCH & FILTER ---
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$cat_filter = isset($_GET['filter_category']) ? $conn->real_escape_string($_GET['filter_category']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build the WHERE clause
$where_clauses = ["is_marketplace = 0"];

if (!empty($search)) {
    $where_clauses[] = "(title LIKE '%$search%' OR id LIKE '%$search%')";
}
if (!empty($cat_filter)) {
    $where_clauses[] = "category = '$cat_filter'";
}

$where_sql = implode(' AND ', $where_clauses);

// Validate sort columns to prevent SQL injection
$allowed_sorts = ['id', 'title', 'price', 'created_at'];
if (!in_array($sort_by, $allowed_sorts)) { $sort_by = 'created_at'; }

$products_sql = "SELECT id, title, price, category, description, created_at 
                 FROM products 
                 WHERE $where_sql 
                 ORDER BY $sort_by $order";

$products_result = $conn->query($products_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | THE VAULT</title>
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
            <a href="index.php">SKATE<span>SHOP</span> ADMIN</a>
        </h1>
    </div>
</header>

<section class="admin-layout container">
    <aside class="admin-sidebar grainy-card">
        <h3 class="admin-sidebar-title">SYSTEM <span class="header-span">MENU</span></h3>
        <ul class="admin-nav-list">
            <li><a href="index.php"><span class="material-icons">dashboard</span> DASHBOARD</a></li>
            <li><a href="shop-products.php" class="active"><span class="material-icons">inventory_2</span> THE VAULT (SHOP)</a></li>
            <li><a href="marketplace-products.php"><span class="material-icons">storefront</span> STREET MARKET</a></li>
            <li><a href="../index.php"><span class="material-icons">public</span> VIEW LIVE SITE</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">THE <span class="text-primary">VAULT</span></h2>
                <p class="admin-text-shop">OFFICIAL SHOP INVENTORY MANAGEMENT</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('addModal')">
                + ADD NEW GEAR
            </button>
        </div>

        <?php if ($message != ''): ?>
            <div class="admin-alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="grainy-card" style="padding: 20px;">
            <h3 class="admin-table-h3">CURRENT <span class="header-span">INVENTORY</span></h3>


            <div class="grainy-card filter-bar">
    <form method="GET" action="shop-products.php" class="search-filter-form">
        <div class="filter-group search-box">
            <label>SEARCH</label>
            <input type="text" name="search" placeholder="ID or Product Name..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div class="filter-group">
            <label>CATEGORY</label>
            <select name="filter_category" onchange="this.form.submit()">
                <option value="">ALL GEAR</option>
                <option value="Decks" <?php if($cat_filter == 'Decks') echo 'selected'; ?>>DECKS</option>
                <option value="Trucks" <?php if($cat_filter == 'Trucks') echo 'selected'; ?>>TRUCKS</option>
                <option value="Wheels" <?php if($cat_filter == 'Wheels') echo 'selected'; ?>>WHEELS</option>
                <option value="Bearings" <?php if($cat_filter == 'Bearings') echo 'selected'; ?>>BEARINGS</option>
                <option value="Apparel" <?php if($cat_filter == 'Apparel') echo 'selected'; ?>>APPAREL</option>
            </select>
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

        <div class="filter-group" style="flex: 0 0 auto;">
            <label style="visibility: hidden;">ACTIONS</label> 
            <div class="filter-actions">
                <button type="submit" class="btn-filter">FILTER</button>
                <a href="shop-products.php" class="btn-reset">RESET</a>
            </div>
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
                            <tr class="clickable-row" 
                                onclick="openEditModal(
                                    <?php echo $row['id']; ?>, 
                                    '<?php echo addslashes(htmlspecialchars($row['title'])); ?>', 
                                    <?php echo $row['price']; ?>, 
                                    '<?php echo addslashes(htmlspecialchars($row['category'])); ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['description'])); ?>'
                                )">
                                <td>#<?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                <td><span class="badge-shop"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                <td>$<?php echo number_format($row['price'], 2); ?></td>
                                <td class="edit-button">
                                <span class="material-icons edit-icon">edit</span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center;">THE VAULT IS CURRENTLY EMPTY.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</section>

<div id="addModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
        <h3 class="admin-table-h3">ADD <span class="header-span">NEW</span> GEAR</h3>
        <form action="shop-products.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <label class="top-title">GEAR TITLE</label>
            <input type="text" name="title" placeholder="e.g. DARKSTAR SEVEN-PLY" required>
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
            <textarea name="description" rows="3" placeholder="TECH SPECS..." required></textarea>
            <label>GEAR IMAGE</label>
            <input type="file" name="image" accept="image/*" required>
            <button type="submit" name="add_product" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;">
                ADD TO THE SHOP
            </button>
        </form>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">GEAR</span></h3>
        <form action="shop-products.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <input type="hidden" id="edit_id" name="product_id">
            <label class="top-title">GEAR TITLE</label>
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
                <button type="submit" name="edit_product" class="btn btn-primary" style="font-size: 1.2rem;">
                    SAVE CHANGES
                </button>
                <button type="submit" name="delete_product" class="btn btn-danger" onclick="return confirm('PERMANENTLY TRASH THIS GEAR?');">
                    <span class="material-icons" style="vertical-align: middle;">delete</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

    function openEditModal(id, title, price, category, description) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_category').value = category;
        document.getElementById('edit_description').value = description;
        openModal('editModal');
    }

    window.onclick = function(event) {
        if (event.target.className === 'modal-overlay') {
            event.target.style.display = "none";
        }
    }
</script>

</body>
</html>