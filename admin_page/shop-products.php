<?php
session_start();
include '../db.php';

// --- HANDLE ADD FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])){
    $title = $conn->real_escape_string($_POST['title']);
    $brand = $conn->real_escape_string($_POST['brand']); // <--- BRAND ADDED HERE
    $price = (float)$_POST['price'];
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : 'NULL';
    $quantity = (int)$_POST['quantity'];
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $is_marketplace = 0;

    if (mb_strlen($_POST['title']) > 50 || mb_strlen($_POST['brand']) > 50 || mb_strlen($_POST['description']) > 500) {
        $_SESSION['msg'] = "INPUT TOO LONG. MAX LIMITS: TITLE 50, BRAND 50, DESCRIPTION 500.";
        $_SESSION['msg_type'] = "error";
        header("Location: shop-products.php");
        exit();
    }

    if ($price > 5000) {
        $_SESSION['msg'] = "PRICE CANNOT EXCEED $5000.";
        $_SESSION['msg_type'] = "error";
        header("Location: shop-products.php");
        exit();
    }

    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $target_dir = "../assets/uploads/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid('gear_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($file_extension), $allowed_types)){
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_url = "../assets/uploads/" . $new_filename;
            }
        }
    }

    if ($image_url != '') {
        // UPDATED INSERT QUERY TO INCLUDE BRAND, QUANTITY AND DISCOUNT
        $sql = "INSERT INTO products (title, brand, price, discount_price, quantity, category, description, image_url, is_marketplace) 
                VALUES ('$title', '$brand', '$price', $discount_price, '$quantity', '$category', '$description', '$image_url', '$is_marketplace')";
        if ($conn->query($sql) === TRUE) {
            $_SESSION['msg'] = "GEAR ADDED TO THE VAULT!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "DATABASE ERROR: " . $conn->error;
            $_SESSION['msg_type'] = "error";
        }
    } else {
        $_SESSION['msg'] = "ERROR: INVALID IMAGE OR NO IMAGE UPLOADED.";
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: shop-products.php");
    exit();
}

// --- HANDLE EDIT FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $id = (int)$_POST['product_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $brand = $conn->real_escape_string($_POST['brand']); // <--- BRAND ADDED HERE
    $price = (float)$_POST['price'];
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : 'NULL';
    $quantity = (int)$_POST['quantity'];
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);

    if (mb_strlen($_POST['title']) > 50 || mb_strlen($_POST['brand']) > 50 || mb_strlen($_POST['description']) > 500) {
        $_SESSION['msg'] = "INPUT TOO LONG. MAX LIMITS: TITLE 50, BRAND 50, DESCRIPTION 500.";
        $_SESSION['msg_type'] = "error";
        header("Location: shop-products.php");
        exit();
    }

    if ($price > 5000) {
        $_SESSION['msg'] = "PRICE CANNOT EXCEED $5000.";
        $_SESSION['msg_type'] = "error";
        header("Location: shop-products.php");
        exit();
    }

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

    // UPDATED UPDATE QUERY TO INCLUDE BRAND, QUANTITY AND DISCOUNT
    $sql = "UPDATE products SET title='$title', brand='$brand', price='$price', discount_price=$discount_price, quantity='$quantity', category='$category', description='$description' $image_query_part WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        $_SESSION['msg'] = "GEAR UPDATED SUCCESSFULLY!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "ERROR UPDATING GEAR.";
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: shop-products.php");
    exit();
}

// --- HANDLE DELETE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $id = (int)$_POST['product_id'];
    $sql = "DELETE FROM products WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        $_SESSION['msg'] = "GEAR TRASHED.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "ERROR DELETING GEAR.";
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: shop-products.php");
    exit();
}

// --- HANDLE SEARCH & FILTER ---
$search = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : '';
$cat_filter = isset($_GET['filter_category']) ? $_GET['filter_category'] : []; 
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

$where_clauses = ["is_marketplace = 0"];
$types = "";
$params = [];

if (!empty($search)) { 
    $where_clauses[] = "(title LIKE ? OR id LIKE ?)"; 
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if (!empty($cat_filter) && is_array($cat_filter)) {
    $placeholders = implode(',', array_fill(0, count($cat_filter), '?'));
    $where_clauses[] = "category IN ($placeholders)";
    foreach ($cat_filter as $c) {
        $params[] = $c;
        $types .= "s";
    }
}

$where_sql = implode(' AND ', $where_clauses);

$allowed_sorts = ['id', 'title', 'price', 'created_at'];
if ($sort_by === 'discounted') {
    $where_sql .= " AND discount_price IS NOT NULL AND discount_price > 0";
    $sort_by_column = 'created_at';
} else {
    $sort_by_column = in_array($sort_by, $allowed_sorts) ? $sort_by : 'created_at';
}

// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_sql = "SELECT COUNT(*) as total FROM products WHERE $where_sql";
$stmt_count = $conn->prepare($count_sql);
if ($types) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// UPDATED SELECT QUERY TO INCLUDE BRAND AND QUANTITY AND DISCOUNT
$products_sql = "SELECT id, title, brand, price, discount_price, quantity, category, description, created_at 
                 FROM products WHERE $where_sql ORDER BY $sort_by_column $order LIMIT ? OFFSET ?";
$stmt_data = $conn->prepare($products_sql);
$types_data = $types . "ii";
$params_data = $params;
$params_data[] = $limit;
$params_data[] = $offset;
$stmt_data->bind_param($types_data, ...$params_data);
$stmt_data->execute();
$products_result = $stmt_data->get_result();
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
    <script src="../assets/admin-script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
    
</head>
<body>

<?php require __DIR__ . '/admin_header.php'; ?>

<section class="admin-layout container">
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">THE <span class="text-primary">VAULT</span></h2>
                <p class="admin-text-shop">OFFICIAL SHOP INVENTORY MANAGEMENT</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('addModal')">+ ADD NEW GEAR</button>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="admin-alert alert-<?php echo $_SESSION['msg_type']; ?>">
                <?php 
                    echo $_SESSION['msg']; 
                    unset($_SESSION['msg']); // Clear so it doesn't repeat on refresh
                    unset($_SESSION['msg_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="grainy-card" style="padding: 20px;">
            <h3 class="admin-table-h3">CURRENT <span class="header-span">INVENTORY</span></h3>
            <div class="grainy-card filter-bar">
    <form method="GET" action="shop-products.php" class="search-filter-form">
        <div class="filter-group search-box">
            <label>SEARCH</label>
            <input type="text" name="search" placeholder="ID or Product Name..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
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
                $options = ['Decks', 'Trucks', 'Wheels', 'Bearings', 'Apparel',  'Accesories', 'Other'];
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
                <option value="discounted" <?php if($sort_by == 'discounted') echo 'selected'; ?>>DISCOUNTED</option>
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
            <a href="shop-products.php" class="btn-reset">RESET</a>
        </div>
    </form>
</div>
            
            <div class="table-responsive"><table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ITEM NAME</th>
                        <th>CATEGORY</th>
                        <th>PRICE / QTY</th>
                        <th>EDIT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products_result->num_rows > 0): ?>
                        <?php while($row = $products_result->fetch_assoc()): ?>
                            <tr class="clickable-row" onclick="openEditModal(
                                <?php echo $row['id']; ?>, 
                                '<?php echo addslashes(htmlspecialchars($row['title'])); ?>', 
                                '<?php echo addslashes(htmlspecialchars($row['brand'])); ?>', 
                                <?php echo $row['price']; ?>, 
                                '<?php echo addslashes(htmlspecialchars($row['category'])); ?>', 
                                '<?php echo addslashes(htmlspecialchars($row['description'])); ?>',
                                <?php echo (int)$row['quantity']; ?>,
                                <?php echo !empty($row['discount_price']) ? $row['discount_price'] : 'null'; ?>
                            )">
                                <td class="td-id">#<?php echo $row['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                    <small style="opacity: 0.7;"><?php echo htmlspecialchars($row['brand']); ?></small>
                                </td>
                                <td><span class="badge-shop"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                <td>
                                    <?php if (!empty($row['discount_price'])): ?>
                                        <span style="text-decoration: line-through; color: #888; font-size: 0.9em;">$<?php echo number_format($row['price'], 2); ?></span>
                                        <br><span style="color: var(--primary); font-weight: bold;">$<?php echo number_format($row['discount_price'], 2); ?></span>
                                    <?php else: ?>
                                        $<?php echo number_format($row['price'], 2); ?>
                                    <?php endif; ?>
                                    <br><small style="opacity:0.7;">QTY: <?php echo $row['quantity']; ?></small>
                                </td>
                                <td class="edit-button"><span class="material-icons edit-icon">edit</span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; font-weight:700; font-style: italic;">THE VAULT IS CURRENTLY EMPTY.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table></div>

            <?php render_intelligent_pagination($page, $total_pages, 'admin-pagination'); ?>



        </div>
    </main>
</section>

<div id="addModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
        <h3 class="admin-table-h3">ADD <span class="header-span">NEW</span> GEAR</h3>
        <form action="shop-products.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <div>
                    <label>GEAR TITLE</label>
                    <input type="text" name="title" placeholder="E.G. STAGE 11 HOLLOW" required maxlength="50">
                </div>
                <div>
                    <label>BRAND</label>
                    <input type="text" name="brand" placeholder="E.G. INDY" required maxlength="50">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div><label>PRICE ($)</label><input type="number" name="price" step="0.01" required max="5000"></div>
                <div><label>DISCOUNT ($)</label><input type="number" name="discount_price" step="0.01" placeholder="Optional"></div>
                <div><label>QUANTITY</label><input type="number" name="quantity" min="0" required></div>
            </div>
            <div style="margin-top: 20px;">
                <label>CATEGORY</label>
                <select name="category" required>
                    <option value="Decks">DECKS</option>
                    <option value="Trucks">TRUCKS</option>
                    <option value="Wheels">WHEELS</option>
                    <option value="Bearings">BEARINGS</option>
                    <option value="Apparel">APPAREL</option>
                    <option value="Accesories">ACCESORIES</option>
                    <option value="Other">OTHER</option>
                </select>
            </div>
            <label>DESCRIPTION</label>
            <textarea name="description" rows="3" required maxlength="500"></textarea>
            <label>GEAR IMAGE</label>
            <input type="file" name="image" accept="image/*" required>
            <button type="submit" name="add_product" class="btn btn-primary" style="width: 100%; margin-top: 1rem; font-size: 1.4rem;">ADD TO THE SHOP</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">GEAR</span></h3>
        <form action="shop-products.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <input type="hidden" id="edit_id" name="product_id">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <div>
                    <label>GEAR TITLE</label>
                    <input type="text" id="edit_title" name="title" required maxlength="50">
                </div>
                <div>
                    <label>BRAND</label>
                    <input type="text" id="edit_brand" name="brand" required maxlength="50">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div><label>PRICE ($)</label><input type="number" id="edit_price" name="price" step="0.01" required max="5000"></div>
                <div><label>DISCOUNT ($)</label><input type="number" id="edit_discount_price" name="discount_price" step="0.01" placeholder="Optional"></div>
                <div><label>QUANTITY</label><input type="number" id="edit_quantity" name="quantity" min="0" required></div>
            </div>
            <div style="margin-top: 20px;">
                <label>CATEGORY</label>
                <select id="edit_category" name="category" required>
                    <option value="Decks">DECKS</option>
                    <option value="Trucks">TRUCKS</option>
                    <option value="Wheels">WHEELS</option>
                    <option value="Bearings">BEARINGS</option>
                    <option value="Apparel">APPAREL</option>
                    <option value="Accesories">ACCESORIES</option>
                    <option value="Other">OTHER</option>
                </select>
            </div>
            <label>DESCRIPTION</label>
            <textarea id="edit_description" name="description" rows="3" required maxlength="500"></textarea>
            <label>CHANGE IMAGE (OPTIONAL)</label>
            <input type="file" name="image" accept="image/*">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-top: 20px;">
                <button type="submit" name="edit_product" class="btn btn-primary">SAVE CHANGES</button>
                <button type="button" class="btn btn-danger" onclick="openConfirmDeleteModal()">
                    <span class="material-icons" style="vertical-align: middle;">delete</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="confirmDeleteModal" class="modal-overlay" style="z-index: 9999;">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteModal')">&times;</span>
        <h3 class="admin-table-h3" style="border:none; margin-bottom:10px;">TRASH <span class="text-primary">GEAR?</span></h3>
        <p style="font-family:'Inter',sans-serif; font-size:1rem; margin-bottom:20px;">Are you sure you want to completely delete this product from the vault?</p>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-danger" style="flex: 1; font-size: 1rem;" onclick="submitDelete()">YES</button>
            <button type="button" class="btn btn-outline" style="flex: 1; font-size: 1rem;" onclick="closeModal('confirmDeleteModal')">CANCEL</button>
        </div>
    </div>
</div>

<script>
    function openConfirmDeleteModal() {
        document.getElementById('confirmDeleteModal').classList.add('active');
    }
    
    function submitDelete() {
        const form = document.querySelector('#editModal form');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_product';
        input.value = '1';
        form.appendChild(input);
        form.submit();
    }
</script>

</body>
</html>
