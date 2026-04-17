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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | <?php echo htmlspecialchars($product['title']); ?></title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <script src="../assets/script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        /* Lightbox Styles */
        #main-product-img {
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        #main-product-img:hover {
            opacity: 0.9;
        }

        /* The Modal (background) */
        .image-lightbox-modal {
            display: none; 
            position: fixed; 
            z-index: 2000; 
            padding-top: 50px; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.9); 
            backdrop-filter: blur(5px);
        }

        /* Modal Content (Image) */
        .lightbox-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 800px;
            border: 3px solid var(--primary);
            box-shadow: 0 0 5px rgba(255, 75, 43, 0.5);
        }

        /* Caption Text */
        #lightbox-caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            text-align: center;
            color: #ccc;
            padding: 15px 0;
            font-family: 'Staatliches', sans-serif;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* The Close Button */
        .close-lightbox {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 30px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }

        .close-lightbox:hover {
            color: #ff4b2b;
        }

        /* Responsive */
        @media only screen and (max-width: 700px){
            .lightbox-content {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

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
            <div class="thumb active-thumb">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="Thumb 1">
            </div>
            <div class="thumb">
                <i class="fa-solid fa-camera" style="font-size: 2rem; color: #ccc;"></i>
            </div>
            <div class="thumb">
                <i class="fa-solid fa-camera" style="font-size: 2rem; color: #ccc;"></i>
            </div>
            <div class="thumb">
                <i class="fa-solid fa-camera" style="font-size: 2rem; color: #ccc;"></i>
            </div>
        </div>
    </div>

    <div class="product-info-panel">
        <div class="product-meta">
            <?php if (!$isMarketplace): ?>
                <span class="official-tag"><i class="fa-solid fa-circle-check"></i> VERIFIED AUTHENTIC</span>
            <?php endif; ?>
            
            <h3 class="brand-name"><?php echo strtoupper(htmlspecialchars($product['brand'])); ?></h3>
            <h1 class="product-title glitch-text"><?php echo htmlspecialchars($product['title']); ?></h1>
            
            <div class="price-wrap">
                <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                
                <?php if ($isMarketplace): ?>
                    <div class="marketplace-seller-info">
                        <i class="fa-solid fa-store" style="color: #ff4b2b;"></i>
                        <span>SELLER: </span>
                        <a href="user-profile.php?username=<?php echo urlencode($product['seller_name']); ?>">
                            @<?php echo htmlspecialchars($product['seller_name']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <button class="btn btn-primary add-to-cart-mega">
            <?php echo $isMarketplace ? "BUY FROM SELLER" : "ADD TO BAG"; ?>
            <span class="material-icons">shopping_cart</span>
        </button>

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

<div id="imageLightbox" class="image-lightbox-modal">
  <span class="close-lightbox">&times;</span>
  <img class="lightbox-content" id="imgExpanded">
  <div id="lightbox-caption"></div>
</div>

<?php include 'footer.php'; ?>

<script>
    // Get the modal
    var modal = document.getElementById("imageLightbox");

    // Get the image and insert it inside the modal - use its "alt" text as a caption
    var img = document.getElementById("main-product-img");
    var modalImg = document.getElementById("imgExpanded");
    var captionText = document.getElementById("lightbox-caption");

    img.onclick = function(){
      modal.style.display = "block";
      modalImg.src = this.src;
      captionText.innerHTML = this.alt;
      // Prevent scrolling when modal is open
      document.body.style.overflow = "hidden";
    }

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close-lightbox")[0];

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() { 
      modal.style.display = "none";
      document.body.style.overflow = "auto";
    }

    // Close modal when clicking outside the image
    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
        document.body.style.overflow = "auto";
      }
    }
</script>

</body>
</html>