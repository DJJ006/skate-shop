<?php 
include '../db.php'; 

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;


$whereClause = "WHERE is_marketplace = 0";
if ($search != '') {
    $whereClause .= " AND (title LIKE '%$search%' OR brand LIKE '%$search%')";
}

$count_query = "SELECT COUNT(*) as total FROM products $whereClause";
$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

$sql = "SELECT * FROM products $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$start_item = $offset + 1;
$end_item = min($offset + $limit, $total_items);
if ($total_items == 0) { $start_item = 0; $end_item = 0; }
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | SHOP</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <script src="../assets/script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">

</head>
<body>

<?php include 'header.php'; ?>

<div class="marquee-banner">
    <div class="marquee-content">
        <span>✦ THE VAULT ✦</span>
        <span class="text-primary">★ OFFICIAL GEAR ★</span>
        <span>✦ THE VAULT ✦</span>
        <span class="text-primary">★ OFFICIAL GEAR ★</span>
        <span>✦ THE VAULT ✦</span>
        <span class="text-primary">★ OFFICIAL GEAR ★</span>
        <span>✦ THE VAULT ✦</span>
        <span class="text-primary">★ OFFICIAL GEAR ★</span>
    </div>
</div>

<div class="gear-guide">
<section class="gear-guide-cta container">
    <div class="cta-box">
        <div class="cta-text">
            <h2>NOT SURE WHAT TO RIDE?</h2>
            <p>CONSTRUCTION, CONCAVE, AND COMPONENTS EXPLAINED.</p>
        </div>
        <a href="#qna" class="btn btn-primary" style="font-size: 1.5rem; text-decoration: none;">CHECK OUT GUIDE</a>
    </div>
</section>
</div>

<div class="shop-header-title container">
    <h2 class="glitch-text-shop">SHOP <span class="text-primary">DROPS</span></h2>
    <p class="search-gear-text">BEST PLACE FOR SKATING</p>
</div>

<section class="shop-layout container">
<aside class="shop-sidebar">
    <div class="mobile-filter-wrapper">
        <button type="button" class="filter-toggle-btn" id="filterToggle">
            FILTER GEAR <span class="material-icons">expand_more</span>
        </button>
        
        <div class="filter-content-inner" id="filterContent">
            <div class="filter-group">
                <h4>CATEGORY</h4>
                <ul class="filter-list">
                    <li><label><input type="checkbox" checked> ALL GEAR</label></li>
                    <li><label><input type="checkbox"> DECKS</label></li>
                    <li><label><input type="checkbox"> TRUCKS & WHEELS</label></li>
                    <li><label><input type="checkbox"> APPAREL</label></li>
                    <li><label><input type="checkbox"> ACCESSORIES</label></li>
                </ul>
            </div>

            <div class="filter-group">
                <h4>PRICE</h4>
                <ul class="filter-list">
                    <li><label><input type="radio" name="price"> UNDER $50</label></li>
                    <li><label><input type="radio" name="price"> $50 - $100</label></li>
                    <li><label><input type="radio" name="price"> OVER $100</label></li>
                </ul>
            </div>
        </div>
    </div>
</aside>

    <div class="shop-main-grid">
        <div class="shop-controls">
                <div class="controls-upper">
                <div class="search-section">

                    <form class="search-wrapper" action="shop.php" method="GET">
                        <input type="text" name="search" placeholder="SEARCH GEAR..." id="shop-search" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="search-btn"><span class="material-icons">search</span></button>
                    </form>

                    <p class="results-count">SHOWING <?php echo $start_item; ?>-<?php echo $end_item; ?> OF <?php echo $total_items; ?> ITEMS</p>
                </div>
                </div>

                    <div class="sort-section">
                        <select class="sort-dropdown">
                            <option>SORT: NEWEST</option>
                            <option>SORT: PRICE (LOW-HIGH)</option>
                            <option>SORT: PRICE (HIGH-LOW)</option>
                        </select>
                    </div>
                </div>
        
    

      
        <div class="product-grid">
        <?php 
            
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    
                    ?>
                    <a href="product.php?id=<?php echo $row['id']; ?>" style="text-decoration: none; color: inherit;">
                        <div class="product-card grainy-card compact-card">
                            <div class="card-img">
                                <img src="<?php echo $row['image_url']; ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
                            </div>
                            <div class="card-info">
                                <div>
                                    <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($row['condition_badge']); ?></p>
                                </div>
                                <span class="price">$<?php echo $row['price']; ?></span>
                            </div>
                        </div>
                    </a>
                    <?php
                }
            } else {
                echo "<h3>NO GEAR FOUND. TRY ANOTHER SEARCH.</h3>";
            }
            ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>" class="btn btn-outline">&lt; PREV</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>" class="btn <?php echo ($page == $i) ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>" class="btn btn-outline">NEXT &gt;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</section>


<div class="commercial">
<section class="commercial-slider container">
    <div class="slider-wrapper">
        <div class="commercial-slide active">
            <a href="https://www.nike.com/skateboarding" target="_blank">
                <div class="ad-content">
                    <img src="../assets/images/nike_sb_ad.jpeg" alt="Nike SB Ad">
                    <div class="ad-overlay">
                        <h2 class="ad-title">NIKE SB</h2>
                        <p class="ad-sub">THE NEW DUNK LOW IS HERE.</p>
                        <span class="ad-cta">EXPLORE COLLECTION +</span>
                    </div>
                </div>
            </a>
        </div>

        <div class="commercial-slide">
            <a href="#" target="_blank">
                <div class="ad-content">
                    <img src="../assets/images/vans_ad.jpg" alt="Vans Ad">
                    <div class="ad-overlay">
                        <h2 class="ad-title">VANS</h2>
                        <p class="ad-sub">OFF THE WALL SINCE '66.</p>
                        <span class="ad-cta">SHOP CLASSICS +</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- <div class="slider-nav">
            <button id="prev-ad" class="nav-btn"><span class="material-icons">arrow_back</span></button>
            <div class="slider-dots">
                <span class="dot active"></span>
                <span class="dot"></span>
            </div>
            <button id="next-ad" class="nav-btn"><span class="material-icons">arrow_forward</span></button>
        </div> -->
    </div>
</section>
</div>




<section id="qna" class="qna-section container">
    <h2 class="glitch-text">GEAR <span class="text-primary">Q&A</span></h2>
    
    <div class="qna-accordion">
        <div class="qna-item">
            <div class="qna-question">
                <h4><span>01.</span> CHOOSING DECK WIDTH?</h4>
                <span class="material-icons toggle-icon">add</span>
            </div>
            <div class="qna-answer">
                <p>It's about your shoe size and style. Tech/street skaters usually dig 8.0" to 8.25". If you're hitting bowls or have giant feet, go 8.5" and up for that extra stability.</p>
            </div>
        </div>

        <div class="qna-item">
            <div class="qna-question">
                <h4><span>02.</span> WHAT WHEEL HARDNESS IS BEST?</h4>
                <span class="material-icons toggle-icon">add</span>
            </div>
            <div class="qna-answer">
                <p>99A-101A is the standard for parks and smooth street. If your local spot is crusty asphalt, grab some 78A-86A "cloud" wheels so you don't eat pebbles for breakfast.</p>
            </div>
        </div>

        <div class="qna-item">
            <div class="qna-question">
                <h4><span>03.</span> HI, LOW, OR MID TRUCKS?</h4>
                <span class="material-icons toggle-icon">add</span>
            </div>
            <div class="qna-answer">
                <p>Lows are great for flip tricks. Highs allow for bigger wheels and better turns. Mids are the "safe bet" if you want to do a bit of everything without overthinking it.</p>
            </div>
        </div>
    </div>
</section>

<section class="newsletter-section">
    <div class="newsletter-box grainy-card">
        <div class="newsletter-content container">
            <h2 class="glitch-text">STAY IN <span class="text-primary">TOUCH</span></h2>
            <p>JOIN THE CREW FOR EXCLUSIVE DROPS, CLIPS, AND SALES.</p>
            <form class="newsletter-form">
                <input type="email" placeholder="ENTER YOUR EMAIL..." required>
                <button type="submit" class="btn btn-primary">SIGN ME UP +</button>
            </form>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>

</body>
</html>