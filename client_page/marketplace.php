<?php 
include '../db.php'; 

// 1. Collect and Sanitize Inputs
$search = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// FIX: Ensure Condition is always an array
$selected_conditions = isset($_GET['condition']) ? $_GET['condition'] : [];
if (!is_array($selected_conditions) && !empty($selected_conditions)) {
    $selected_conditions = [$selected_conditions];
}

// FIX: Ensure Category is always an array (Renamed from 'type' for consistency)
$selected_categories = isset($_GET['category']) ? $_GET['category'] : [];
if (!is_array($selected_categories) && !empty($selected_categories)) {
    $selected_categories = [$selected_categories];
}

$limit = 6; 
$offset = ($page - 1) * $limit;

// 2. Build the WHERE Clause
$whereClause = "WHERE is_marketplace = 1 AND is_approved = 1 AND id NOT IN (SELECT product_id FROM orders WHERE status IN ('PAID', 'RECEIVED')) AND seller_id IN (SELECT id FROM users WHERE is_blocked = 0)";

$types = "";
$params = [];

if ($search != '') {
    $whereClause .= " AND (title LIKE ? OR brand LIKE ? OR seller_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

// Filter by Condition
if (!empty($selected_conditions)) {
    $placeholders = implode(',', array_fill(0, count($selected_conditions), '?'));
    $whereClause .= " AND condition_badge IN ($placeholders)";
    foreach ($selected_conditions as $c) {
        $params[] = $c;
        $types .= "s";
    }
}

// Filter by Category
if (!empty($selected_categories)) {
    $placeholders = implode(',', array_fill(0, count($selected_categories), '?'));
    $whereClause .= " AND category IN ($placeholders)";
    foreach ($selected_categories as $c) {
        $params[] = $c;
        $types .= "s";
    }
}

// 3. Sorting Logic
$orderClause = "ORDER BY created_at DESC"; 
if ($sort === 'cheapest') {
    $orderClause = "ORDER BY price ASC";
} elseif ($sort === 'rarest') {
    $orderClause = "ORDER BY price DESC";
}

// 4. Execution
$count_query = "SELECT COUNT(*) as total FROM products $whereClause";
$stmt_count = $conn->prepare($count_query);
if ($types) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_items = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

$sql = "SELECT * FROM products $whereClause $orderClause LIMIT ? OFFSET ?";
$stmt_data = $conn->prepare($sql);
$types_data = $types . "ii";
$params_data = $params;
$params_data[] = $limit;
$params_data[] = $offset;
$stmt_data->bind_param($types_data, ...$params_data);
$stmt_data->execute();
$result = $stmt_data->get_result();

$start_item = ($total_items > 0) ? $offset + 1 : 0;
$end_item = min($offset + $limit, $total_items);

// URL Persistence Helper
function get_filter_url($params) {
    $current_params = $_GET;
    foreach($params as $key => $value) {
        $current_params[$key] = $value;
    }
    return "?" . http_build_query($current_params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | MARKETPLACE</title>
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
        <span>✦ COP OR DROP ✦</span>
        <span class="text-primary">★ STREET MARKET ★</span>
        <span>✦ USED BUT STILL SHREDS ✦</span>
        <span class="text-primary">★ STREET MARKET ★</span>
        <span>✦ COP OR DROP ✦</span>
        <span class="text-primary">★ STREET MARKET ★</span>
        <span>✦ USED BUT STILL SHREDS ✦</span>
        <span class="text-primary">★ STREET MARKET ★</span>
    </div>
</div>

<div class="gear-guide">
<section class="gear-guide-cta container">
    <div class="cta-box marketplace-cta">
        <div class="cta-text">
            <h2>GOT BOARDS COLLECTING DUST?</h2>
            <p>TURN YOUR OLD WOOD AND SCUFFED TRUCKS INTO COLD HARD CASH.</p>
        </div>
        <a href="client-profile.php?action=sellGear" class="btn btn-primary" style="font-size: 1.5rem; text-decoration: none;">START SELLING</a>
    </div>
</section>
</div>

<div class="shop-header-title container">
    <h2 class="glitch-text-shop">STREET <span class="text-primary">MARKET</span></h2>
    <p class="search-gear-text">BUY AND SELL FROM THE COMMUNITY</p>
</div>

<section class="shop-layout container">
<aside class="shop-sidebar">
        <form action="marketplace.php" method="GET" id="marketFilterForm">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

            <div class="mobile-filter-wrapper">
                <button type="button" class="filter-toggle-btn" id="filterToggle">
                    FILTER JUNK <span class="material-icons">expand_more</span>
                </button>
                
                <div class="filter-content-inner" id="filterContent">
                    <div class="filter-group">
                        <h4>CONDITION</h4>
                        <ul class="filter-list">
                            <li><label><input type="checkbox" name="condition[]" value="MINT / WALL HANGER" <?php echo in_array('MINT / WALL HANGER', $selected_conditions) ? 'checked' : ''; ?> onchange="this.form.submit()">MINT / WALLHANGER</label></li>
                            <li><label><input type="checkbox" name="condition[]" value="LIGHTLY SCUFFED" <?php echo in_array('LIGHTLY SCUFFED', $selected_conditions) ? 'checked' : ''; ?> onchange="this.form.submit()">LIGHTLY SCUFFED</label></li>
                            <li><label><input type="checkbox" name="condition[]" value="BEAT UP / SKATEABLE" <?php echo in_array('BEAT UP / SKATEABLE', $selected_conditions) ? 'checked' : ''; ?> onchange="this.form.submit()">BEAT UP / SKATEABLE</label></li>
                        </ul>
                    </div>

                    <div class="filter-group">
                        <h4>CATEGORY</h4>
                        <ul class="filter-list">
                            <li><label><input type="checkbox" name="category[]" value="Decks" <?php echo in_array('Decks', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">DECKS</label></li>
                            <li><label><input type="checkbox" name="category[]" value="Trucks" <?php echo in_array('Trucks', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">TRUCKS</label></li>
                            <li><label><input type="checkbox" name="category[]" value="Wheels" <?php echo in_array('Wheels', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">WHEELS</label></li>
                            <li><label><input type="checkbox" name="category[]" value="Bearings" <?php echo in_array('Bearings', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">BEARINGS</label></li>
                            <li><label><input type="checkbox" name="category[]" value="Apparel" <?php echo in_array('Apparel', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()"> APPAREL</label></li>
                            <li><label><input type="checkbox" name="category[]" value="Accesories" <?php echo in_array('Accesories', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">ACCESORIES</label></li>
                            <li><label><input type="checkbox" name="category[]" value="Other" <?php echo in_array('Other', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">OTHER</label></li>
                        </ul>
                    </div>

                    <a href="marketplace.php" class="btn reset-btn">RESET ALL</a>
                </div>
            </div>
        </form>
    </aside>

    <div class="shop-main-grid">
        <div class="shop-controls">
            <div class="controls-upper">
                <div class="search-section">

                    <form class="search-wrapper" action="marketplace.php" method="GET">
                        <input type="text" name="search" placeholder="SEARCH THE SCRAPHEAP..." id="shop-search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        
                        <?php foreach($selected_conditions as $c): ?><input type="hidden" name="condition[]" value="<?php echo htmlspecialchars($c); ?>"><?php endforeach; ?>
                        <?php foreach($selected_categories as $t): ?><input type="hidden" name="category[]" value="<?php echo htmlspecialchars($t); ?>"><?php endforeach; ?>
                        
                        <button type="submit" class="search-btn"><span class="material-icons">search</span></button>
                    </form>
                    <p class="results-count">SHOWING <?php echo $start_item; ?>-<?php echo $end_item; ?> OF <?php echo $total_items; ?> LISTINGS</p>
                </div>

                <div class="sort-section">
                    <select class="sort-dropdown" onchange="window.location.href = '<?php echo get_filter_url(['sort' => '']); ?>'.replace('sort=', 'sort=' + this.value)">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>SORT: NEWEST</option>
                        <option value="cheapest" <?php echo $sort == 'cheapest' ? 'selected' : ''; ?>>SORT: CHEAPEST</option>
                        <option value="rarest" <?php echo $sort == 'rarest' ? 'selected' : ''; ?>>SORT: RAREST</option>
                    </select>
                </div>
            </div>
        </div>


      
        <div class="product-grid">
        <?php 
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                ?>
                <a href="product.php?id=<?php echo $row['id']; ?>" style="text-decoration: none; color: inherit;">
                    <div class="product-card grainy-card compact-card marketplace-card">
                        <div class="card-img">
                            <span class="condition-badge <?php echo strtolower(explode(' ', $row['condition_badge'])[0]); ?>">
                                <?php echo htmlspecialchars($row['condition_badge']); ?>
                            </span>
                            <img src="<?php echo $row['image_url']; ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
                        </div>
                        <div class="card-info">
                            <div style="flex: 1; min-width: 0; padding-right: 10px;">
                                <h4 style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($row['title']); ?></h4>
                                <p style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">SELLER: <span class="seller-name"><?php echo htmlspecialchars($row['seller_name']); ?></span></p>
                            </div>
                            <div style="text-align: right; flex-shrink: 0;">
                                <span class="price" style="display: block;">$<?php echo number_format($row['price'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php
            }
        } else {
            echo "<div  class='not-found'>
            <h3>NO GEAR FOUND.</h3>
            <p>Try adjusting your filters or search terms.</p>
        </div>";
        }
        ?>
        </div>


        <?php render_intelligent_pagination($page, $total_pages, 'pagination'); ?>
    </div>
</section>

<div class="marketplace-info-banner" style="margin-top: 40px; margin-bottom: 80px;">
    <section class="container marketplace-grain-bg" style="padding: 60px 30px; text-align: center; background: #111; border: 4px solid var(--charcoal); box-shadow: 12px 12px 0px var(--charcoal); position: relative; overflow: hidden;">
        <div style="text-align: center; margin-bottom: 50px;">
            <h2 class="glitch-text" style="font-size: 3rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; color: var(--textwhite);">HOW THE <span class="text-primary">MARKETPLACE</span> WORKS</h2>
            <p style="font-family: 'Inter', sans-serif; font-size: 1.1rem; color: #ccc; max-width: 600px; margin: 0 auto; text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">Join the community. Buy, sell, and trade rare gear.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 40px;">
            <!-- Step 1 -->
            <div class="market-step-card" style="position: relative; background: #fff; border: 4px solid var(--charcoal); padding: 40px 30px; box-shadow: 8px 8px 0px var(--charcoal); transition: all 0.2s ease; cursor: default;">
                <div style="position: absolute; top: -20px; left: -20px; background: var(--primary); color: #fff; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; font-family: 'Staatliches', sans-serif; font-size: 2.2rem; border: 4px solid var(--charcoal); box-shadow: 4px 4px 0px var(--charcoal); transform: rotate(-5deg); z-index: 2;">1</div>
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fa-solid fa-tags" style="font-size: 3.5rem; color: var(--charcoal); transition: color 0.2s ease;"></i>
                </div>
                <h4 style="font-family: 'Staatliches', sans-serif; font-size: 1.8rem; margin-bottom: 15px; color: var(--charcoal); text-align: center; letter-spacing: 1px;">LIST YOUR GEAR</h4>
                <p style="color: #444; font-family: 'Inter', sans-serif; line-height: 1.6; font-size: 1.3rem; text-align: center; font-weight: 500; margin: 0;">Got extra parts or rare decks? Post them up! Set your price, upload clean photos, and let the community browse your stash.</p>
            </div>
            
            <!-- Step 2 -->
            <div class="market-step-card" style="position: relative; background: #fff; border: 4px solid var(--charcoal); padding: 40px 30px; box-shadow: 8px 8px 0px var(--charcoal); transition: all 0.2s ease; cursor: default;">
                <div style="position: absolute; top: -20px; left: -20px; background: var(--primary); color: #fff; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; font-family: 'Staatliches', sans-serif; font-size: 2.2rem; border: 4px solid var(--charcoal); box-shadow: 4px 4px 0px var(--charcoal); transform: rotate(3deg); z-index: 2;">2</div>
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fa-solid fa-lock" style="font-size: 3.5rem; color: var(--charcoal); transition: color 0.2s ease;"></i>
                </div>
                <h4 style="font-family: 'Staatliches', sans-serif; font-size: 1.8rem; margin-bottom: 15px; color: var(--charcoal); text-align: center; letter-spacing: 1px;">SECURE CHECKOUT</h4>
                <p style="color: #444; font-family: 'Inter', sans-serif; line-height: 1.6; font-size: 1.3rem; text-align: center; font-weight: 500; margin: 0;">Buyers pay safely via Stripe or internal Wallet. Funds are securely held until the order is successfully delivered and completed.</p>
            </div>
            
            <!-- Step 3 -->
            <div class="market-step-card" style="position: relative; background: #fff; border: 4px solid var(--charcoal); padding: 40px 30px; box-shadow: 8px 8px 0px var(--charcoal); transition: all 0.2s ease; cursor: default;">
                <div style="position: absolute; top: -20px; left: -20px; background: var(--primary); color: #fff; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; font-family: 'Staatliches', sans-serif; font-size: 2.2rem; border: 4px solid var(--charcoal); box-shadow: 4px 4px 0px var(--charcoal); transform: rotate(-3deg); z-index: 2;">3</div>
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fa-solid fa-handshake" style="font-size: 3.5rem; color: var(--charcoal); transition: color 0.2s ease;"></i>
                </div>
                <h4 style="font-family: 'Staatliches', sans-serif; font-size: 1.8rem; margin-bottom: 15px; color: var(--charcoal); text-align: center; letter-spacing: 1px;">BUILD YOUR REP</h4>
                <p style="color: #444; font-family: 'Inter', sans-serif; line-height: 1.6; font-size: 1.3rem; text-align: center; font-weight: 500; margin: 0;">Ship fast and accurately to earn 5-star reviews. Top rated sellers unlock the coveted Verified Trusted Badge!</p>
            </div>
        </div>
    </section>
</div>

<style>
.marketplace-grain-bg::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
    opacity: 0.15; 
    pointer-events: none; 
    z-index: 1;
}
.marketplace-grain-bg > * {
    position: relative;
    z-index: 2;
}

.market-step-card:hover {
    transform: translate(-4px, -4px) !important;
    box-shadow: 12px 12px 0px var(--primary) !important;
}
.market-step-card:hover i {
    color: var(--primary) !important;
}
</style>


<section id="qna" class="qna-section container">
    <h2 class="glitch-text">MARKET <span class="text-primary">Q&A</span></h2>
    
    <div class="qna-accordion">
        <div class="qna-item">
            <div class="qna-question">
                <h4><span>01.</span> HOW DO I NOT GET SCAMMED?</h4>
                <span class="material-icons toggle-icon">add</span>
            </div>
            <div class="qna-answer">
                <p>Always use our built-in secure checkout. We hold the funds until the gear arrives at your door and you verify the condition. If it's a brick in a box, you get your money back.</p>
            </div>
        </div>

        <div class="qna-item">
            <div class="qna-question">
                <h4><span>02.</span> WHO PAYS FOR SHIPPING?</h4>
                <span class="material-icons toggle-icon">add</span>
            </div>
            <div class="qna-answer">
                <p>The buyer pays for shipping at checkout. Sellers will get a printable label sent to their email. Slap it on the box and drop it at the post office.</p>
            </div>
        </div>

        <div class="qna-item">
            <div class="qna-question">
                <h4><span>03.</span> CAN I SELL SNAPPED DECKS?</h4>
                <span class="material-icons toggle-icon">add</span>
            </div>
            <div class="qna-answer">
                <p>Unless it was snapped by Tony Hawk and you have proof, throw it in the trash, man. We only allow skateable gear or legitimate wall-hanger collectibles.</p>
            </div>
        </div>
    </div>
</section>




<?php include 'footer.php'; ?>

</body>
</html>
