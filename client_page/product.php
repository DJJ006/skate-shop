<?php 
include '../db.php'; 

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

$product_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div class='container' style='padding:100px;'><h1>PRODUCT NOT FOUND.</h1><a href='shop.php'>Go Back</a></div>");
}

$product = $result->fetch_assoc();

$isMarketplace = (isset($product['is_marketplace']) && $product['is_marketplace'] == 1);
$basePage = $isMarketplace ? "marketplace.php" : "shop.php";
$baseLabel = $isMarketplace ? "MARKETPLACE" : "SHOP";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    if (isset($_SESSION['user_id'])) {
        $u_id = $_SESSION['user_id'];
        $rating = (int)$_POST['rating'];
        $comment = substr(trim($_POST['comment']), 0, 100);
        
        if ($rating >= 1 && $rating <= 5) {
            $check = $conn->prepare("SELECT status FROM product_reviews WHERE product_id = ? AND user_id = ? AND status != 'Rejected' ORDER BY created_at DESC LIMIT 1");
            $check->bind_param("ii", $product_id, $u_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['msg'] = "YOU HAVE ALREADY REVIEWED THIS PRODUCT.";
                $_SESSION['msg_type'] = "error";
            } else {
                $exist_check = $conn->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
                $exist_check->bind_param("ii", $product_id, $u_id);
                $exist_check->execute();
                if ($exist_check->get_result()->num_rows > 0) {
                    $ins = $conn->prepare("UPDATE product_reviews SET rating = ?, comment = ?, status = 'Pending Approval' WHERE product_id = ? AND user_id = ?");
                    $ins->bind_param("isii", $rating, $comment, $product_id, $u_id);
                } else {
                    $ins = $conn->prepare("INSERT INTO product_reviews (product_id, user_id, rating, comment, status) VALUES (?, ?, ?, ?, 'Pending Approval')");
                    $ins->bind_param("iiis", $product_id, $u_id, $rating, $comment);
                }
                if ($ins->execute()) {
                    $_SESSION['msg'] = "REVIEW SUBMITTED AND PENDING APPROVAL.";
                    $_SESSION['msg_type'] = "success";
                }
            }
        }
    } else {
        $_SESSION['msg'] = "YOU MUST BE LOGGED IN TO REVIEW.";
        $_SESSION['msg_type'] = "error";
    }
    header("Location: product.php?id=" . $product_id);
    exit();
}

$reviews_stmt = $conn->prepare("
    SELECT pr.*, u.username, u.profile_pic 
    FROM product_reviews pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE pr.product_id = ? AND pr.status = 'Approved'
    ORDER BY pr.created_at DESC
");
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$approved_reviews = $reviews_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | <?php echo htmlspecialchars($product['title']); ?></title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/user-profile.css"> 
    <script src="../assets/script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        /* Lightbox Styles */
        #main-product-img { cursor: pointer; transition: opacity 0.3s ease; }
        #main-product-img:hover { opacity: 0.9; }

        .image-lightbox-modal {
            display: none; position: fixed; z-index: 2000; padding-top: 50px; 
            left: 0; top: 0; width: 100%; height: 100%; overflow: auto; 
            background-color: rgba(0,0,0,0.9); backdrop-filter: blur(5px);
        }
        .lightbox-content {
            margin: auto; display: block; width: 80%; max-width: 800px;
            border: 3px solid #ff4b2b; box-shadow: 0 0 5px rgba(255, 75, 43, 0.5);
        }
        #lightbox-caption {
            margin: auto; display: block; width: 80%; max-width: 700px;
            text-align: center; color: #ccc; padding: 15px 0;
            font-family: 'Staatliches', sans-serif; text-transform: uppercase; letter-spacing: 2px;
        }
        .close-lightbox {
            position: absolute; top: 20px; right: 35px; color: #f1f1f1;
            font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer;
        }
        .close-lightbox:hover { color: #ff4b2b; }

        /* SELLER MODAL STYLES */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--bg-light);
            border: 4px solid var(--primary);
            box-shadow: 10px 10px 0px var(--charcoal);
            padding: 30px;
            width: 90%;
            max-width: 1100px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .close-modal {
            position: absolute; 
            top: 10px; 
            right: 20px; 
            font-size: 3rem; 
            color: var(--charcoal); 
            cursor: pointer; 
            font-family: 'Staatliches', sans-serif;
            line-height: 1;
        }

        .close-modal:hover{
            color: var(--primary); 
            transform: scale(1.20);
            transition: var(--transition);
        }

        @media only screen and (max-width: 700px){
            .lightbox-content { width: 100%; }
            .modal-content { width: 95%; padding: 20px; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    <?php if (isset($_SESSION['msg'])): ?>
        <div id="alert-box" class="admin-alert alert-<?php echo $_SESSION['msg_type'] === 'success' ? 'success' : 'error'; ?>" style="margin-top: 20px;">
            <?php 
                echo htmlspecialchars($_SESSION['msg']); 
                unset($_SESSION['msg']);
                unset($_SESSION['msg_type']);
            ?>
        </div>
    <?php endif; ?>
</div>

<div class="container breadcrumbs">
    <a href="<?php echo $basePage; ?>"><?php echo $baseLabel; ?></a> 
    <span>/</span> 
    <a href="<?php echo $basePage; ?>?category[]=<?php echo urlencode($product['category']); ?>">
        <?php echo strtoupper(htmlspecialchars($product['category'])); ?>
    </a> 
    <span>/</span> 
    <span class="current-page"><?php echo htmlspecialchars($product['title']); ?></span>
</div>

<section class="product-page-layout container">
    <div class="product-gallery">
        <div class="main-image-container grainy-card">
            <?php if ($isMarketplace): ?>
                <span class="condition-badge marketplace-badge">
                    <i class="fa-solid fa-handshake"></i> <?php echo strtoupper(htmlspecialchars($product['condition_badge'])); ?>
                </span>
            <?php else: ?>
                <span class="condition-badge new-drop">OFFICIAL STOCK</span>
            <?php endif; ?>
            
            <img id="main-product-img" src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
        </div>

        <div class="thumbnail-row">
            <div class="thumb active-thumb"><img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="Thumb 1"></div>
            <div class="thumb"><i class="fa-solid fa-camera" style="font-size: 2rem; color: #ccc;"></i></div>
            <div class="thumb"><i class="fa-solid fa-camera" style="font-size: 2rem; color: #ccc;"></i></div>
            <div class="thumb"><i class="fa-solid fa-camera" style="font-size: 2rem; color: #ccc;"></i></div>
        </div>
    </div>

    <div class="product-info-panel">
        <div class="product-meta">
            <?php if (!$isMarketplace): ?>
                <span class="official-tag"><i class="fa-solid fa-circle-check"></i> VERIFIED AUTHENTIC</span>
            <?php endif; ?>
            
            <h3 class="brand-name"><?php echo strtoupper(htmlspecialchars($product['brand'])); ?></h3>
            <h1 class="product-title glitch-text"><?php echo htmlspecialchars($product['title']); ?></h1>
            <?php if (!$isMarketplace): ?>
            <div class="product-rating-overview" style="margin-bottom: 15px; color: var(--primary); font-size: 1.2rem;">
                <?php
                $avg = (float)($product['average_rating'] ?? 0);
                $cnt = (int)($product['review_count'] ?? 0);
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= round($avg)) {
                        echo '<i class="fa-solid fa-star"></i>';
                    } else {
                        echo '<i class="fa-regular fa-star"></i>';
                    }
                }
                ?>
                <span style="color: #5f5f5fff; font-size: 0.9rem; margin-left: 10px;">(<?php echo $cnt; ?> REVIEWS)</span>
            </div>
            <?php endif; ?>
            
            <div class="price-wrap">
                <?php if (!empty($product['discount_price']) && (float)$product['discount_price'] > 0): ?>
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <span class="product-price" style="text-decoration: line-through; color: #aaa; font-size: 1.5rem;">$<?php echo number_format($product['price'], 2); ?></span>
                        <span class="product-price" style="color: var(--primary);">$<?php echo number_format($product['discount_price'], 2); ?></span>
                        <span class="condition-badge new-drop" style="position: relative; top: 0; left: 0; margin-left: 10px;">DISCOUNTED PRODUCT</span>
                    </div>
                <?php else: ?>
                    <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                <?php endif; ?>
            
                <?php if ($isMarketplace): ?>
                    <div class="marketplace-seller-info">
                        <i class="fa-solid fa-store" style="color: #ff4b2b;"></i>
                        <span>SELLER: </span>
                        <a href="javascript:void(0)" onclick="openSellerModal('<?php echo urlencode($product['seller_name']); ?>', <?php echo $product_id; ?>)">
                            @<?php echo htmlspecialchars($product['seller_name']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php
        $canBuy = false;
        $stockDisplay = "";
        $isOwnItem = false;

        if ($isMarketplace && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $product['seller_id']) {
            $isOwnItem = true;
        }
        
        if ($isMarketplace) {
            // Marketplace items are 1-of-1
            $canBuy = ($product['is_sold'] == 0 && !$isOwnItem);
            $stockDisplay = "1 UNIQUE ITEM";
            if ($isOwnItem) $stockDisplay = "YOUR LISTING";
        } else {
            // Official Shop uses Quantity
            $qty = (int)$product['quantity'];
            $canBuy = ($qty > 0);
            $stockDisplay = $canBuy ? "IN STOCK: " . $qty : "OUT OF STOCK";
        }
        ?>

        <div style="margin: 15px 0; color: <?php echo $canBuy ? '#2ecc71' : '#ff4b2b'; ?>; font-family: 'Arial Black', sans-serif; font-size: 0.9rem; letter-spacing: 1px;">
            <i class="fa-solid <?php echo $canBuy ? 'fa-box-open' : 'fa-ban'; ?>"></i> <?php echo $stockDisplay; ?>
        </div>

        <?php if ($canBuy): ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <button onclick="addToCart(<?php echo $product_id; ?>, 1, <?php echo $isMarketplace ? 'true' : 'false'; ?>, this)" class="btn btn-primary add-to-cart-mega" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;">
                    <?php echo $isMarketplace ? "BUY FROM SELLER" : "ADD TO CART"; ?>
                    <span class="material-icons"><?php echo $isMarketplace ? "payments" : "shopping_cart"; ?></span>
                </button>
            <?php else: ?>
                <a href="login.php?redirect=<?php echo urlencode('product.php?id=' . $product_id); ?>" class="btn btn-primary add-to-cart-mega" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;">
                    LOGIN TO PURCHASE
                    <span class="material-icons">lock</span>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <button class="btn btn-primary add-to-cart-mega" disabled style="opacity: 0.3; cursor: not-allowed; background: #333; border-color: #222; color: #888; width: 100%;">
                OUT OF STOCK
                <span class="material-icons">block</span>
            </button>
        <?php endif; ?>

        </div>

        <div class="product-info-block">
            <h4 class="block-title"><i class="fa-solid fa-file-lines"></i> GEAR SPECS</h4>
            <div class="block-content">
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
        </div>
        
        <?php if ($isMarketplace): ?>
        <div class="product-info-block safety-block">
            <h4 class="block-title warning-title"><i class="fa-solid fa-triangle-exclamation"></i> STREET MARKET GUARANTEE</h4>
            <div class="block-content">
                <p>All marketplace transactions are protected by our community guidelines. Ensure you inspect high-res photos before purchasing gear from other skaters. <strong style="color: #ff4b2b;">NO SCAMS, JUST SKATE.</strong></p>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<section class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <?php if (!$isMarketplace): ?>
    <div class="reviews-section-layout" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 40px; align-items: start; max-width: 1400px; margin: 0 auto;">
        
        <!-- LEFT COLUMN: REVIEW FORM -->
        <div class="review-form-wrapper">
            <h2 class="glitch-text" style="border-bottom: 2px solid var(--charcoal); padding-bottom: 10px; margin-bottom: 20px; font-size: 3rem; letter-spacing: 1px;">LEAVE A REVIEW</h2>
            <div class="review-form-container grainy-card" style="padding: 30px; border: 4px solid var(--charcoal); background: #ffffff; box-shadow: 12px 12px 0px var(--primary);">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" action="product.php?id=<?php echo $product_id; ?>" class="simple-form">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 10px; color: #000000; font-size: 1.2rem; font-family: 'Staatliches', sans-serif; letter-spacing: 1px;">RATING (1-5)</label>
                            <div class="star-rating-input" style="font-size: 2rem; color: #555; cursor: pointer; text-align: left; margin-bottom: 10px;">
                                <i class="fa-solid fa-star" data-rating="1"></i>
                                <i class="fa-solid fa-star" data-rating="2"></i>
                                <i class="fa-solid fa-star" data-rating="3"></i>
                                <i class="fa-solid fa-star" data-rating="4"></i>
                                <i class="fa-solid fa-star" data-rating="5"></i>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" value="5" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="comment" style="display: block; font-weight: bold; margin-bottom: 10px; color: #000000; font-size: 1.2rem; font-family: 'Staatliches', sans-serif; letter-spacing: 1px;">COMMENT (OPTIONAL, MAX 100 CHARS)</label>
                            <textarea name="comment" id="comment" rows="4" maxlength="100" placeholder="Tell us what you think..." style="font-family: 'Inter', sans-serif; font-size: 1.25rem; line-height: 1.5; letter-spacing: 0px;"></textarea>
                            <div id="product-review-counter" style="font-family:'Staatliches',sans-serif; font-size:0.95rem; color:#555; text-align:right; margin-top:0.5rem; letter-spacing:1px;">100 characters remaining</div>
                        </div>
                        <button type="submit" name="submit_review" class="btn btn-primary" style="width: 100%; font-size: 1.2rem; padding: 12px 30px; text-transform: uppercase; font-family: 'Staatliches', sans-serif; letter-spacing: 1px;">SUBMIT REVIEW</button>
                    </form>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const stars = document.querySelectorAll('.star-rating-input i');
                            const ratingInput = document.getElementById('ratingInput');
                            function updateStars(rating) {
                                stars.forEach(star => {
                                    if (parseInt(star.dataset.rating) <= rating) {
                                        star.style.color = 'var(--primary)';
                                    } else {
                                        star.style.color = '#555';
                                    }
                                });
                            }
                            updateStars(5); // Default
                            stars.forEach(star => {
                                star.addEventListener('click', function() {
                                    ratingInput.value = parseInt(this.dataset.rating);
                                    updateStars(ratingInput.value);
                                });
                            });
                            const commentInput = document.getElementById('comment');
                            const commentCounter = document.getElementById('product-review-counter');
                            if (commentInput && commentCounter) {
                                commentInput.addEventListener('input', function() {
                                    commentCounter.textContent = (100 - this.value.length) + ' characters remaining';
                                });
                            }
                            const alertBox = document.getElementById('alert-box');
                            if (alertBox) { setTimeout(() => { alertBox.style.opacity = "0"; setTimeout(() => alertBox.remove(), 500); }, 4000); }
                        });
                    </script>
                <?php else: ?>
                    <p style="text-align: center;"><a href="login.php?redirect=<?php echo urlencode('product.php?id=' . $product_id); ?>" style="color: var(--primary); text-decoration: underline;">LOGIN</a> TO LEAVE A REVIEW.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT COLUMN: REVIEWS LIST -->
        <div class="user-reviews-wrapper">
            <h2 class="glitch-text" style="border-bottom: 2px solid var(--charcoal); padding-bottom: 10px; margin-bottom: 20px; font-size: 3rem; letter-spacing: 1px;">USER REVIEWS</h2>
            <div class="reviews-container" style="display: grid; gap: 20px; grid-template-columns: 1fr; max-height: 700px; overflow-y: auto; padding-right: 15px;">
                <?php if ($approved_reviews->num_rows > 0): ?>
                    <?php while ($rev = $approved_reviews->fetch_assoc()): ?>
                        <div class="review-card grainy-card" style="padding: 25px; border: 4px solid var(--charcoal); background: #ffffff; box-shadow: 8px 8px 0px var(--primary);">
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px dashed #ccc; padding-bottom: 15px; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo !empty($rev['profile_pic']) ? htmlspecialchars($rev['profile_pic']) : '../assets/images/default_avatar.png'; ?>" alt="Avatar" style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid var(--primary);">
                                    <strong style="font-family: 'Staatliches', sans-serif; font-size: 1.4rem; letter-spacing: 1px; color: #000;">@<?php echo htmlspecialchars($rev['username']); ?></strong>
                                </div>
                                <div style="color: var(--primary); font-size: 1.2rem;">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo ($i <= $rev['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php if (!empty($rev['comment'])): ?>
                                <p style="font-size: 1.25rem; color: #222; font-weight: 500; line-height: 1.6; margin-bottom: 15px; font-family: 'Inter', sans-serif;"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                            <?php endif; ?>
                            <small style="color: #666; font-family: 'Staatliches', sans-serif; font-size: 1.1rem; letter-spacing: 1px;"><i class="fa-regular fa-clock"></i> <?php echo date('M d, Y', strtotime($rev['created_at'])); ?></small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #696969ff; font-style: italic; font-size: 1.3rem; text-align: center; margin-top: 20px;">No reviews yet. Be the first to review this gear!</p>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    <?php else: ?>
    <h2 class="glitch-text" style="border-bottom: 2px solid var(--charcoal); padding-bottom: 10px; margin-bottom: 20px; font-size: 4rem; letter-spacing: 1px;">SELLER REPUTATION</h2>
    <div class="grainy-card" style="padding: 30px; border: 4px solid var(--charcoal); max-width: 800px; margin: 0 auto; text-align: center; background: #ffffff; box-shadow: 12px 12px 0px var(--primary);">
        <i class="fa-solid fa-store" style="font-size: 4rem; color: var(--primary); margin-bottom: 15px; display: block;"></i>
        <h3 style="font-family: 'Staatliches', sans-serif; font-size: 2.2rem; margin-bottom: 10px; color: #000000; letter-spacing: 1px;">MARKETPLACE ITEMS DON'T HAVE PRODUCT REVIEWS</h3>
        <p style="color: #333333; font-family: 'Inter', sans-serif; font-size: 1.1rem; margin-bottom: 25px; font-weight: 500;">Because this is a unique marketplace listing, you should check the seller's reputation instead of the product.</p>
        <button onclick="openSellerModal('<?php echo htmlspecialchars($product['seller_name']); ?>', <?php echo $product_id; ?>)" class="btn btn-primary" style="display: inline-block; font-size: 1.2rem; padding: 12px 30px; text-transform: uppercase; font-family: 'Staatliches', sans-serif; letter-spacing: 1px;">VIEW SELLER REVIEWS</button>
    </div>
    <?php endif; ?>
</section>

<div id="imageLightbox" class="image-lightbox-modal">
  <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
  <img class="lightbox-content" id="imgExpanded">
  <div id="lightbox-caption"></div>
</div>

<div id="sellerProfileModal" class="modal-overlay">
    <div class="modal-content seller-profile-modal-shell" id="sellerProfileContent">
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    // --- Lightbox Logic ---
    var lightbox = document.getElementById("imageLightbox");
    var img = document.getElementById("main-product-img");
    var modalImg = document.getElementById("imgExpanded");
    var captionText = document.getElementById("lightbox-caption");

    img.onclick = function(){
      lightbox.style.display = "block";
      modalImg.src = this.src;
      captionText.innerHTML = this.alt;
      document.body.style.overflow = "hidden";
    }

    function closeLightbox() { 
      lightbox.style.display = "none";
      document.body.style.overflow = "auto";
    }

    // Close lightbox on outside click
    window.onclick = function(event) {
      if (event.target == lightbox) { closeLightbox(); }
      if (event.target == document.getElementById('sellerProfileModal')) { closeSellerModal(); }
    }

    // --- Seller Profile AJAX Modal Logic ---
    function openSellerModal(username, productId) {
        const modal = document.getElementById('sellerProfileModal');
        const contentDiv = document.getElementById('sellerProfileContent');
        
        // Show loading state
        contentDiv.innerHTML = '<div class="user-profile-loading"><h2 class="glitch-text">LOADING RECORD...</h2></div>';
        modal.style.display = 'flex';
        document.body.style.overflow = "hidden";

        // Fetch the HTML fragment from user-profile.php
        fetch(`user-profile.php?username=${username}&product_id=${productId}`)
            .then(response => response.text())
            .then(html => {
                contentDiv.innerHTML = html;
            })
            .catch(err => {
                contentDiv.innerHTML = '<h3 style="color:#ff4b2b;">FAILED TO LOAD SELLER DATA.</h3>';
            });
    }

    function closeSellerModal() {
        document.getElementById('sellerProfileModal').style.display = 'none';
        document.body.style.overflow = "auto";
    }
</script>

</body>
</html>