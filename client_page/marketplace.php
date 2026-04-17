<?php 
include '../db.php'; 

// 1. Collect and Sanitize Inputs
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
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
$whereClause = "WHERE is_marketplace = 1 AND is_approved = 1";

if ($search != '') {
    $whereClause .= " AND (title LIKE '%$search%' OR brand LIKE '%$search%' OR seller_name LIKE '%$search%')";
}

// Filter by Condition
if (!empty($selected_conditions)) {
    $escaped_conds = array_map(function($c) use ($conn) { 
        return "'" . $conn->real_escape_string($c) . "'"; 
    }, $selected_conditions);
    $whereClause .= " AND condition_badge IN (" . implode(',', $escaped_conds) . ")";
}

// Filter by Category
if (!empty($selected_categories)) {
    $escaped_cats = array_map(function($t) use ($conn) { 
        return "'" . $conn->real_escape_string($t) . "'"; 
    }, $selected_categories);
    $whereClause .= " AND category IN (" . implode(',', $escaped_cats) . ")";
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
$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

$sql = "SELECT * FROM products $whereClause $orderClause LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

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
        <a href="#sell" class="btn btn-primary" style="font-size: 1.5rem; text-decoration: none;">START SELLING</a>
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
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
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
                        <input type="text" name="search" placeholder="SEARCH THE SCRAPHEAP..." id="shop-search" value="<?php echo htmlspecialchars($search); ?>">
                        
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
                            <div>
                                <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                                <p>SELLER: <span class="seller-name"><?php echo htmlspecialchars($row['seller_name']); ?></span></p>
                            </div>
                            <span class="price">$<?php echo number_format($row['price'], 2); ?></span>
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


        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="<?php echo get_filter_url(['page' => $page - 1]); ?>" class="btn btn-outline">&lt; PREV</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($page == $i): ?>
                    <span class="btn btn-primary active-page"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo get_filter_url(['page' => $i]); ?>" class="btn btn-outline"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="<?php echo get_filter_url(['page' => $page + 1]); ?>" class="btn btn-outline">NEXT &gt;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="bounty">
    <section class="bounty-board-section container">
        <div class="bounty-wrapper">
            <div class="bounty-header">
                <h2 class="glitch-text-shop">THE BOUNTY BOARD</h2>
                <p>CASH REWARDS FOR RARE GEAR</p>
            </div>
            
            <div class="bounty-grid">
                <div class="bounty-item">
                    <span class="tape-effect"></span>
                    <div class="bounty-stamp">WANTED</div>
                    <h5>2004 WORLD INDUSTRIES FLAME BOY</h5>
                    <div class="reward-tag">
                        <span>REWARD</span>
                        <strong class="price">$300</strong>
                    </div>
                    <div class="buyer-info">
                        <span class="material-icons">person</span> @OldSchoolDave
                    </div>
                </div>

                <div class="bounty-item">
                    <span class="tape-effect"></span>
                    <div class="bounty-stamp red-stamp">HIGH PRIORITY</div>
                    <h5>ZERO SKULL DECK (SIGNED BY JAMIE THOMAS)</h5>
                    <div class="reward-tag">
                        <span>REWARD</span>
                        <strong class="price">NAME PRICE</strong>
                    </div>
                    <div class="buyer-info">
                        <span class="material-icons">person</span> @ZeroOrDie
                    </div>
                </div>

                <div class="bounty-item">
                    <span class="tape-effect"></span>
                    <div class="bounty-stamp">WANTED</div>
                    <h5>SPITFIRE FORMULA FOUR 54MM (NEW)</h5>
                    <div class="reward-tag">
                        <span>REWARD</span>
                        <strong class="price">$40</strong>
                    </div>
                    <div class="buyer-info">
                        <span class="material-icons">person</span> @GromSkater
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>


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
