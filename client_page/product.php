<?php 
include '../db.php'; 

// 1. Get the ID from the URL. If no ID is found, redirect back to shop.
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

$product_id = (int)$_GET['id'];

// 2. Fetch the specific product safely
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div class='container' style='padding:100px;'><h1>PRODUCT NOT FOUND.</h1><a href='shop.php'>Go Back</a></div>");
}

$product = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | VOID DECK v2</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <script src="../assets/script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container breadcrumbs">
    <a href="shop.php">SHOP</a> <span>/</span> 
    <a href="shop.php?category=<?php echo $product['category']; ?>"><?php echo strtoupper($product['category']); ?></a> <span>/</span> 
    <span class="current-page"><?php echo htmlspecialchars($product['title']); ?></span>
</div>

<section class="product-page-layout container">
    <div class="product-gallery">
        <div class="main-image-container grainy-card">
            <span class="condition-badge new-drop"><?php echo htmlspecialchars($product['condition_badge']); ?></span>
            <img id="main-product-img" src="<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
        </div>
        </div>

    <div class="product-info-panel">
        <div class="product-meta">
            <h3 class="brand-name"><?php echo htmlspecialchars($product['brand']); ?></h3>
            <h1 class="product-title glitch-text"><?php echo htmlspecialchars($product['title']); ?></h1>
            <div class="price-wrap">
                <span class="product-price">$<?php echo $product['price']; ?></span>
                
                <?php if ($product['is_marketplace']): ?>
                    <span class="seller-tag" style="margin-left: 20px;">SELLER: <a href="#"><b><?php echo htmlspecialchars($product['seller_name']); ?></b></a></span>
                <?php endif; ?>
            </div>
        </div>

        <button class="btn btn-primary add-to-cart-mega">
            ADD TO BAG <span class="material-icons">shopping_cart</span>
        </button>

        <div class="qna-accordion product-details-accordion">
            <div class="qna-item active">
                <div class="qna-question">
                    <h4>DESCRIPTION</h4>
                    <span class="material-icons toggle-icon">add</span>
                </div>
                <div class="qna-answer" style="max-height: 500px; border-top: 3px solid var(--charcoal);">
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
            </div>
            </div>
    </div>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
    