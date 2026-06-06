<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php'; 
require_once '../notification-service.php';

// Ensure table exists
try {
    $conn->query("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// Handle Newsletter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    $email = filter_var($_POST['newsletter_email'], FILTER_SANITIZE_EMAIL);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        
        $check = $conn->prepare("SELECT id FROM newsletter_subscribers WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            $_SESSION['newsletter_msg'] = "YOU ARE ALREADY SIGNED UP!";
            $_SESSION['newsletter_type'] = "error";
        } else {
            $ins = $conn->prepare("INSERT INTO newsletter_subscribers (email) VALUES (?)");
            $ins->bind_param("s", $email);
            if ($ins->execute()) {
                $subject = "Welcome to the SkateShop Crew!";
                $html_body = buildEmailTemplate("STAY IN TOUCH", "<p>Thank you for applying to our newsletter. You're now officially part of the SkateShop crew. Stay tuned for exclusive drops, rare marketplace finds, and fresh clips.</p>");
                if (sendEmail($email, $subject, $html_body)) {
                    $_SESSION['newsletter_msg'] = "THANKS FOR JOINING THE CREW!";
                    $_SESSION['newsletter_type'] = "success";
                } else {
                    $_SESSION['newsletter_msg'] = "ERROR SENDING EMAIL. PLEASE TRY AGAIN.";
                    $_SESSION['newsletter_type'] = "error";
                }
            } else {
                $_SESSION['newsletter_msg'] = "DATABASE ERROR. PLEASE TRY AGAIN.";
                $_SESSION['newsletter_type'] = "error";
            }
        }
    } else {
        $_SESSION['newsletter_msg'] = "PLEASE ENTER A VALID EMAIL.";
        $_SESSION['newsletter_type'] = "error";
    }
    
    // Prevent resubmission on refresh
    $redirect_url = "shop.php";
    if (!empty($_GET)) {
        $redirect_url .= "?" . http_build_query($_GET);
    }
    $redirect_url .= "#newsletter-anchor";
    header("Location: " . $redirect_url);
    exit();
}

$newsletter_msg = $_SESSION['newsletter_msg'] ?? '';
$newsletter_type = $_SESSION['newsletter_type'] ?? '';
unset($_SESSION['newsletter_msg'], $_SESSION['newsletter_type']);

// 1. Collect and Sanitize Inputs
$search = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$selected_categories = isset($_GET['category']) ? $_GET['category'] : [];
if (!is_array($selected_categories) && !empty($selected_categories)) {
    $selected_categories = [$selected_categories];
}
$price_range = isset($_GET['price']) ? $_GET['price'] : '';

$limit = 6;
$offset = ($page - 1) * $limit;

// 2. Build the WHERE Clause
$whereClause = "WHERE is_marketplace = 0";

$types = "";
$params = [];

if ($search != '') {
    $whereClause .= " AND (title LIKE ? OR brand LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if (!empty($selected_categories)) {
    // Escaping array values for the IN() clause
    $placeholders = implode(',', array_fill(0, count($selected_categories), '?'));
    $whereClause .= " AND category IN ($placeholders)";
    foreach ($selected_categories as $c) {
        $params[] = $c;
        $types .= "s";
    }
}

if ($price_range === 'under_50') {
    $whereClause .= " AND (IF(discount_price IS NOT NULL AND discount_price > 0, discount_price, price) < 50)";
} elseif ($price_range === '50_100') {
    $whereClause .= " AND (IF(discount_price IS NOT NULL AND discount_price > 0, discount_price, price) BETWEEN 50 AND 100)";
} elseif ($price_range === 'over_100') {
    $whereClause .= " AND (IF(discount_price IS NOT NULL AND discount_price > 0, discount_price, price) > 100)";
} elseif ($price_range === 'discounted') {
    $whereClause .= " AND discount_price IS NOT NULL AND discount_price > 0";
}

// 3. Build the ORDER BY Clause
$orderClause = "ORDER BY created_at DESC"; // Default
if ($sort === 'price_asc') {
    $orderClause = "ORDER BY IF(discount_price IS NOT NULL AND discount_price > 0, discount_price, price) ASC";
} elseif ($sort === 'price_desc') {
    $orderClause = "ORDER BY IF(discount_price IS NOT NULL AND discount_price > 0, discount_price, price) DESC";
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

// Helper function to keep URL parameters during pagination
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
    <form action="shop.php" method="GET" id="filterForm">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

        <div class="mobile-filter-wrapper">
            <button type="button" class="filter-toggle-btn" id="filterToggle">
                FILTER GEAR <span class="material-icons">expand_more</span>
            </button>
            
            <div class="filter-content-inner" id="filterContent">
                <div class="filter-group">
                    <h4>CATEGORY</h4>
                    <ul class="filter-list">
                        <li><label><input type="checkbox" name="category[]" value="Decks" <?php echo in_array('Decks', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()"> DECKS</label></li>
                        <li><label><input type="checkbox" name="category[]" value="Trucks" <?php echo in_array('Trucks', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()"> TRUCKS</label></li>
                        <li><label><input type="checkbox" name="category[]" value="Wheels" <?php echo in_array('Wheels', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">WHEELS</label></li>
                        <li><label><input type="checkbox" name="category[]" value="Bearings" <?php echo in_array('Bearings', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">BEARINGS</label></li>
                        <li><label><input type="checkbox" name="category[]" value="Apparel" <?php echo in_array('Apparel', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()"> APPAREL</label></li>
                        <li><label><input type="checkbox" name="category[]" value="Accesories" <?php echo in_array('Accesories', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">ACCESORIES</label></li>
                        <li><label><input type="checkbox" name="category[]" value="Other" <?php echo in_array('Other', $selected_categories) ? 'checked' : ''; ?> onchange="this.form.submit()">OTHER</label></li>
                    </ul>
                </div>

                <div class="filter-group">
                    <h4>PRICE</h4>
                    <ul class="filter-list">
                        <li><label><input type="radio" name="price" value="" <?php echo $price_range == '' ? 'checked' : ''; ?> onchange="this.form.submit()"> ALL PRICES</label></li>
                        <li><label><input type="radio" name="price" value="under_50" <?php echo $price_range == 'under_50' ? 'checked' : ''; ?> onchange="this.form.submit()"> UNDER $50</label></li>
                        <li><label><input type="radio" name="price" value="50_100" <?php echo $price_range == '50_100' ? 'checked' : ''; ?> onchange="this.form.submit()"> $50 - $100</label></li>
                        <li><label><input type="radio" name="price" value="over_100" <?php echo $price_range == 'over_100' ? 'checked' : ''; ?> onchange="this.form.submit()"> OVER $100</label></li>
                        <li><label><input type="radio" name="price" value="discounted" <?php echo $price_range == 'discounted' ? 'checked' : ''; ?> onchange="this.form.submit()"> DISCOUNTED</label></li>
                    </ul>
                </div>
                
                <a href="shop.php" class="btn reset-btn">RESET ALL</a>
            </div>
        </div>
    </form>
</aside>

    <div class="shop-main-grid">
        <div class="shop-controls">
            <div class="controls-upper">
                <div class="search-section">

                <form class="search-wrapper" action="shop.php" method="GET">
                    <input type="text" name="search" placeholder="SEARCH GEAR..." id="shop-search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php foreach($selected_categories as $cat): ?>
                        <input type="hidden" name="category[]" value="<?php echo htmlspecialchars($cat); ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="price" value="<?php echo htmlspecialchars($price_range); ?>">
                    
                    <button type="submit" class="search-btn"><span class="material-icons">search</span></button>
                </form>

                    <p class="results-count">SHOWING <?php echo $start_item; ?>-<?php echo $end_item; ?> OF <?php echo $total_items; ?> ITEMS</p>
                </div>

                <div class="sort-section">
                <select class="sort-dropdown" onchange="window.location.href = '<?php echo get_filter_url(['sort' => '']); ?>'.replace('sort=', 'sort=' + this.value)">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>SORT: NEWEST</option>
                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>SORT: PRICE (LOW-HIGH)</option>
                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>SORT: PRICE (HIGH-LOW)</option>
                </select>
            </div>
          </div>
        </div>
        
    

      
        <div class="product-grid">
<?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
        <a href="product.php?id=<?php echo $row['id']; ?>" style="text-decoration: none; color: inherit;">
            <div class="product-card grainy-card compact-card">
                <div class="card-img" style="position: relative; overflow: hidden;">
                    
                    <?php if((int)$row['quantity'] <= 0): ?>
                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; z-index: 10; pointer-events: none;">
                            <span style="background: #E11D48; color: #fff; padding: 8px 20px; font-family: 'Arial Black', sans-serif; font-size: 1.1rem; transform: rotate(-12deg); border: 3px solid #000; box-shadow: 5px 5px 0 #000; text-transform: uppercase; letter-spacing: 1px;">
                                OUT OF STOCK
                            </span>
                        </div>
                    <?php endif; ?>

                    <img src="<?php echo $row['image_url']; ?>" 
                         alt="<?php echo htmlspecialchars($row['title']); ?>" 
                         style="<?php echo ((int)$row['quantity'] <= 0) ? 'filter: grayscale(100%) blur(1px); opacity: 0.7;' : ''; ?>">
                </div>
                
                <div class="card-info">
                    <div>
                        <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                        <p><?php echo htmlspecialchars($row['brand']); ?></p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                        <?php if (!empty($row['discount_price']) && (float)$row['discount_price'] > 0): ?>
                            <span class="price" style="text-decoration: line-through; color: #999; font-size: 1.1em;">
                                $<?php echo number_format($row['price'], 2); ?>
                            </span>
                            <span class="price" style="color: var(--primary); font-weight: bold;">
                                $<?php echo number_format($row['discount_price'], 2); ?>
                            </span>
                        <?php else: ?>
                            <span class="price" style="<?php echo ((int)$row['quantity'] <= 0) ? 'text-decoration: line-through; color: #888;' : ''; ?>">
                                $<?php echo number_format($row['price'], 2); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    <?php endwhile; ?>

        <?php else: ?>
            <div  class="not-found">
                <h3>NO GEAR FOUND.</h3>
                <p>Try adjusting your filters or search terms.</p>
            </div>
        <?php endif; ?>
    </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="<?php echo get_filter_url(['page' => $page - 1]); ?>" class="btn btn-outline">&lt; PREV</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo get_filter_url(['page' => $i]); ?>" class="btn <?php echo ($page == $i) ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="<?php echo get_filter_url(['page' => $page + 1]); ?>" class="btn btn-outline">NEXT &gt;</a>
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

<section id="newsletter-anchor" class="newsletter-section">
    <div class="newsletter-box grainy-card">
        <div class="newsletter-content container">
            <h2 class="glitch-text">STAY IN <span class="text-primary">TOUCH</span></h2>
            <p>JOIN THE CREW FOR EXCLUSIVE DROPS, CLIPS, AND SALES.</p>
            
            <?php if (!empty($newsletter_msg)): ?>
                <div id="newsletter-msg-box" style="margin-bottom: 15px; font-size: 1.2rem; font-weight: bold; font-family: 'Staatliches', sans-serif; letter-spacing: 1px; color: <?php echo $newsletter_type === 'success' ? '#4ADE80' : 'var(--primary)'; ?>; transition: opacity 0.5s ease;">
                    <?php echo htmlspecialchars($newsletter_msg); ?>
                </div>
                <script>
                    setTimeout(() => {
                        const msgBox = document.getElementById('newsletter-msg-box');
                        if (msgBox) {
                            msgBox.style.opacity = '0';
                            setTimeout(() => msgBox.remove(), 500);
                        }
                    }, 5000);
                </script>
            <?php endif; ?>
            
            <form class="newsletter-form" method="POST" action="shop.php#newsletter-anchor">
                <input type="email" name="newsletter_email" placeholder="ENTER YOUR EMAIL..." required>
                <button type="submit" class="btn btn-primary">SIGN ME UP +</button>
            </form>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>

</body>
</html>