<?php
session_start();
include '../db.php';



// --- HANDLE ADD FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])){
    $title = $conn->real_escape_string($_POST['title']);
    $brand = $conn->real_escape_string($_POST['brand']); // BRAND ADDED
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $condition = $conn->real_escape_string($_POST['condition_badge']); 
    $description = $conn->real_escape_string($_POST['description']);
    $is_marketplace = 1; 

    if (mb_strlen($_POST['title']) > 50 || mb_strlen($_POST['brand']) > 50 || mb_strlen($_POST['description']) > 500) {
        $_SESSION['msg'] = "INPUT TOO LONG. MAX LIMITS: TITLE 50, BRAND 50, DESCRIPTION 500.";
        $_SESSION['msg_type'] = "error";
        header("Location: marketplace-products.php");
        exit();
    }

    if ($price > 5000) {
        $_SESSION['msg'] = "PRICE CANNOT EXCEED $5000.";
        $_SESSION['msg_type'] = "error";
        header("Location: marketplace-products.php");
        exit();
    }

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
        // Updated INSERT to include brand
        $sql = "INSERT INTO products (title, brand, price, category, condition_badge, description, image_url, is_marketplace) 
                VALUES ('$title', '$brand', '$price', '$category', '$condition', '$description', '$image_url', '$is_marketplace')";
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
    
    header("Location: marketplace-products.php");
    exit();
}

// --- HANDLE EDIT FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $id = (int)$_POST['product_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $brand = $conn->real_escape_string($_POST['brand']); // BRAND ADDED
    $price = (float)$_POST['price'];
    $category = $conn->real_escape_string($_POST['category']);
    $condition = $conn->real_escape_string($_POST['condition_badge']); 
    $description = $conn->real_escape_string($_POST['description']);

    if (mb_strlen($_POST['title']) > 50 || mb_strlen($_POST['brand']) > 50 || mb_strlen($_POST['description']) > 500) {
        $_SESSION['msg'] = "INPUT TOO LONG. MAX LIMITS: TITLE 50, BRAND 50, DESCRIPTION 500.";
        $_SESSION['msg_type'] = "error";
        header("Location: marketplace-products.php");
        exit();
    }

    if ($price > 5000) {
        $_SESSION['msg'] = "PRICE CANNOT EXCEED $5000.";
        $_SESSION['msg_type'] = "error";
        header("Location: marketplace-products.php");
        exit();
    }

    $image_query_part = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../assets/uploads/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid('market_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = "../assets/uploads/" . $new_filename;
            $image_query_part = ", image_url='$image_url'";
        }
    }

    // Updated UPDATE query to include brand
    $sql = "UPDATE products SET title='$title', brand='$brand', price='$price', category='$category', condition_badge='$condition', description='$description' $image_query_part WHERE id=$id AND is_marketplace=1";
    
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
    $reason = trim($_POST['deletion_reason'] ?? '');

    if ($id <= 0 || $reason === '') {
        $_SESSION['msg'] = "DELETION REASON IS REQUIRED.";
        $_SESSION['msg_type'] = "error";
    } elseif (mb_strlen($reason) > 500) {
        $_SESSION['msg'] = "DELETION REASON CANNOT EXCEED 500 CHARACTERS.";
        $_SESSION['msg_type'] = "error";
    } else {
        // Fetch info for notification before delete
        $info_stmt = $conn->prepare("SELECT seller_id, title FROM products WHERE id = ? AND is_marketplace = 1");
        $info_stmt->bind_param("i", $id);
        $info_stmt->execute();
        $prod = $info_stmt->get_result()->fetch_assoc();

        if ($prod) {
            $sql = "DELETE FROM products WHERE id=$id AND is_marketplace=1";
            if ($conn->query($sql) === TRUE) {
                if (!empty($prod['seller_id'])) {
                    require_once '../notification-service.php';
                    $msg = "Your marketplace listing '" . $prod['title'] . "' was removed by an administrator. Reason: " . $reason;
                    sendAppNotification($conn, $prod['seller_id'], $msg);
                    $_SESSION['msg'] = "MARKET ITEM TRASHED & SELLER NOTIFIED.";
                } else {
                    $_SESSION['msg'] = "MARKET ITEM TRASHED. (NO SELLER ATTACHED)";
                }
                $_SESSION['msg_type'] = "success";
            } else {
                $_SESSION['msg'] = "ERROR DELETING ITEM.";
                $_SESSION['msg_type'] = "error";
            }
        } else {
            $_SESSION['msg'] = "ITEM NOT FOUND.";
            $_SESSION['msg_type'] = "error";
        }
    }
    
    header("Location: marketplace-products.php");
    exit();
}

// --- HANDLE SEARCH & FILTER ---
$search = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : '';
$cat_filter = isset($_GET['filter_category']) ? $_GET['filter_category'] : []; 
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

$where_clauses = [
    "p.is_marketplace = 1",
    "p.id NOT IN (SELECT product_id FROM orders WHERE status IN ('PAID', 'RECEIVED'))"
];

$types = "";
$params = [];

if (!empty($search)) { 
    $where_clauses[] = "(p.title LIKE ? OR p.id LIKE ?)"; 
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if (!empty($cat_filter) && is_array($cat_filter)) {
    $placeholders = implode(',', array_fill(0, count($cat_filter), '?'));
    $where_clauses[] = "p.category IN ($placeholders)";
    foreach ($cat_filter as $c) {
        $params[] = $c;
        $types .= "s";
    }
}

$where_sql = implode(' AND ', $where_clauses);

$allowed_sorts = ['id', 'title', 'price', 'created_at'];
if (!in_array($sort_by, $allowed_sorts)) { $sort_by = 'created_at'; }

// FINAL SELECT QUERY: Added brand here so it shows up in your table/modals
// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_sql = "SELECT COUNT(*) as total FROM products p WHERE $where_sql";
$stmt_count = $conn->prepare($count_sql);
if ($types) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$products_sql = "SELECT p.id, p.title, p.brand, p.price, p.category, p.condition_badge, p.description, p.created_at, u.username AS seller_name 
                 FROM products p LEFT JOIN users u ON p.seller_id = u.id WHERE $where_sql ORDER BY p.$sort_by $order LIMIT ? OFFSET ?";
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

<?php require __DIR__ . '/admin_header.php'; ?>

<section class="admin-layout container">
    <?php include 'admin_sidebar.php'; ?>

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
                                $options = ['Decks', 'Trucks', 'Wheels', 'Bearings', 'Apparel', 'Accesories', 'Other'];
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
            
            <div class="table-responsive"><table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>SELLER</th>
                        <th>ITEM NAME</th>
                        <th>CATEGORY</th>
                        <th>PRICE</th>
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
                                '<?php echo addslashes(htmlspecialchars($row['condition_badge'])); ?>'
                            )">
                                <td class="td-id">#<?php echo $row['id']; ?></td>
                                <td><strong>@<?php echo htmlspecialchars($row['seller_name'] ?? 'unknown'); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                    <small style="opacity: 0.7;"><?php echo htmlspecialchars($row['brand']); ?></small> | 
                                    <small class="badge-condition"><?php echo htmlspecialchars($row['condition_badge']); ?></small>
                                </td>
                                <td><span class="badge-market"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                <td>$<?php echo number_format($row['price'], 2); ?></td>
                                <td class="edit-button"><span class="material-icons edit-icon">edit</span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; font-weight:700; font-style: italic;">THE STREET MARKET IS CURRENTLY EMPTY.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table></div>

            <?php if ($total_pages > 0): ?>
            <div class="admin-pagination">
                <?php
                $query_string = $_GET;
                if ($page > 1) {
                    $query_string['page'] = $page - 1;
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline">&laquo; PREV</a>';
                }
                for ($i = 1; $i <= $total_pages; $i++) {
                    $query_string['page'] = $i;
                    $active = ($i === $page) ? 'active' : '';
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline ' . $active . '">' . $i . '</a>';
                }
                if ($page < $total_pages) {
                    $query_string['page'] = $page + 1;
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline">NEXT &raquo;</a>';
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</section>

<div id="addModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
        <h3 class="admin-table-h3">ADD <span class="header-span">NEW</span> LISTING</h3>
        <form action="marketplace-products.php" method="POST" enctype="multipart/form-data" class="admin-form">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <div>
                    <label>ITEM TITLE</label>
                    <input type="text" name="title" placeholder="E.G. USED BAKER DECK" required maxlength="50">
                </div>
                <div>
                    <label>BRAND</label>
                    <input type="text" name="brand" placeholder="E.G. BAKER" required maxlength="50">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div><label>PRICE ($)</label><input type="number" name="price" step="0.01" required max="5000"></div>
                <div>
                    <label>CONDITION</label>
                    <select name="condition_badge" required>
                        <option value="MINT / WALL HANGER">MINT / WALL HANGER</option>
                        <option value="LIGHTLY SCUFFED">LIGHTLY SCUFFED</option>
                        <option value="BEAT UP / SKATEABLE">BEAT UP / SKATEABLE</option>
                    </select>
                </div>
            </div>
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
            <label>DESCRIPTION</label>
            <textarea name="description" rows="3" required maxlength="500"></textarea>
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
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <div>
                    <label>ITEM TITLE</label>
                    <input type="text" id="edit_title" name="title" required maxlength="50">
                </div>
                <div>
                    <label>BRAND</label>
                    <input type="text" id="edit_brand" name="brand" required maxlength="50">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div><label>PRICE ($)</label><input type="number" id="edit_price" name="price" step="0.01" required max="5000"></div>
                <div>
                    <label>CONDITION</label>
                    <select id="edit_condition" name="condition_badge" required>
                        <option value="MINT / WALL HANGER">MINT / WALL HANGER</option>
                        <option value="LIGHTLY SCUFFED">LIGHTLY SCUFFED</option>
                        <option value="BEAT UP / SKATEABLE">BEAT UP / SKATEABLE</option>
                    </select>
                </div>
            </div>
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
            <label>DESCRIPTION</label>
            <textarea id="edit_description" name="description" rows="3" required maxlength="500"></textarea>
            <label>CHANGE IMAGE (OPTIONAL)</label>
            <input type="file" name="image" accept="image/*">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-top: 20px;">
                <button type="submit" name="edit_product" class="btn btn-primary">SAVE CHANGES</button>
                <button type="button" class="btn btn-danger" onclick="openModal('deleteModal'); document.getElementById('delete_id').value = document.getElementById('edit_id').value; closeModal('editModal');">
                    <span class="material-icons" style="vertical-align: middle;">delete</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal-overlay modal-top-layer">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
        <h3 class="admin-table-h3">DELETE <span class="header-span">CONFIRMATION</span></h3>
        <form action="marketplace-products.php" method="POST" class="admin-form">
            <input type="hidden" id="delete_id" name="product_id">
            
            <label>WHY ARE YOU DELETING THIS PRODUCT?</label>
            <textarea name="deletion_reason" rows="4" placeholder="E.g. Inappropriate content, copyright violation, spam..." required maxlength="500"></textarea>
            
            <p style="margin-top: 1rem; color: var(--primary); font-family: 'Staatliches', sans-serif; font-size: 0.9rem;">* THIS WILL PERMANENTLY REMOVE THE LISTING AND NOTIFY THE SELLER.</p>
            
            <button type="submit" name="delete_product" class="btn btn-danger" style="width:100%; margin-top: 1rem; font-size: 1.2rem;">PERMANENTLY DELETE</button>
        </form>
    </div>
</div>

</body>
</html>
