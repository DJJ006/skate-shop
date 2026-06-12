<?php
session_start();
include '../db.php';

// 1. Hero Section & Shop Drops (is_marketplace = 0)
$shop_sql = "SELECT id, title, price, discount_price, image_url, brand, quantity FROM products WHERE is_marketplace = 0 AND is_approved = 1 ORDER BY created_at DESC LIMIT 4";
$shop_result = $conn->query($shop_sql);
$shop_products = [];
if ($shop_result) {
    while ($row = $shop_result->fetch_assoc()) {
        $shop_products[] = $row;
    }
}
$hero_product = !empty($shop_products) ? $shop_products[0] : null;
$shop_drops = array_slice($shop_products, 1, 3); // Take next 3
if (empty($shop_drops) && !empty($shop_products)) {
    $shop_drops = $shop_products; // Fallback if less than 4 total
}

// 2. The Reels
$reels_sql = "SELECT r.id, r.title, r.embed_url, r.platform, r.description, r.created_at, u.username,
             (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.id) as like_count,
             (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.id) as comment_count
              FROM reels r 
              JOIN users u ON r.user_id = u.id 
              WHERE r.is_approved = 1 
              ORDER BY r.created_at DESC LIMIT 3";
$reels_result = $conn->query($reels_sql);
$reels = [];
if ($reels_result) {
    while ($row = $reels_result->fetch_assoc()) $reels[] = $row;
}

// 3. Marketplace Drops
$market_sql = "SELECT id, title, price, image_url, brand, quantity FROM products 
               WHERE is_marketplace = 1 AND is_approved = 1 
               AND id NOT IN (SELECT product_id FROM orders WHERE status IN ('PAID', 'RECEIVED')) 
               AND seller_id IN (SELECT id FROM users WHERE is_blocked = 0)
               ORDER BY created_at DESC LIMIT 3";
$market_result = $conn->query($market_sql);
$market_products = [];
if ($market_result) {
    while ($row = $market_result->fetch_assoc()) $market_products[] = $row;
}

// 4. The Mag
$mag_sql = "SELECT id, title, slug, short_description, cover_image, published_at FROM magazine_posts WHERE status='published' ORDER BY published_at DESC LIMIT 3";
$mag_result = $conn->query($mag_sql);
$mag_posts = [];
if ($mag_result) {
    while ($row = $mag_result->fetch_assoc()) $mag_posts[] = $row;
}

// 5. Guestbook Shoutouts
$shout_sql = "SELECT id, username, body, created_at FROM community_shoutouts WHERE status='published' ORDER BY created_at DESC LIMIT 2";
$shout_result = $conn->query($shout_sql);
$shoutouts = [];
if ($shout_result) {
    while ($row = $shout_result->fetch_assoc()) $shoutouts[] = $row;
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = ['y' => 'year','m' => 'month','w' => 'week','d' => 'day','h' => 'hour','i' => 'minute','s' => 'second'];
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">

</head>
<body>

<?php include 'header.php'; ?>

<div class="marquee-banner">
    <div class="marquee-content">
        <span>✦ NEW DROPS ✦</span>
        <span class="text-primary">★ SKATE UNIVERSE ★</span>
        <span>✦ WORLDWIDE SHIPPING ✦</span>
        <span class="text-primary">★ JOIN THE MARKET ★</span>
        
        <span>✦ NEW DROPS ✦</span>
        <span class="text-primary">★ SKATE UNIVERSE ★</span>
        <span>✦ WORLDWIDE SHIPPING ✦</span>
        <span class="text-primary">★ JOIN THE MARKET ★</span>
    </div>
</div>


<section class="hero noise-bg">
    <div class="container hero-grid">
        <?php if ($hero_product): ?>
            <div class="hero-text">
                <h2 class="glitch-text" style="font-size: clamp(3rem, 8vw, 7rem);"><?php echo htmlspecialchars($hero_product['title']); ?></h2>
                <p class="trippy-sub"><?php echo htmlspecialchars($hero_product['brand'] ?? 'LATEST DROP'); ?></p>
                <div class="btn-group">
                    <a href="product.php?id=<?php echo $hero_product['id']; ?>" class="btn btn-primary" style="text-decoration:none;">VIEW PRODUCT</a>
                    <a href="shop.php" class="btn btn-outline" style="text-decoration:none;">SHOP ALL</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="image-wrapper">
                    <img src="<?php echo htmlspecialchars($hero_product['image_url']); ?>" alt="<?php echo htmlspecialchars($hero_product['title']); ?>" style="<?php echo ((int)$hero_product['quantity'] <= 0) ? 'filter: grayscale(100%) contrast(1.2); opacity: 0.8;' : 'filter: contrast(1.1);'; ?>">
                    <?php if((int)$hero_product['quantity'] <= 0): ?>
                        <div class="badge">SOLD OUT!</div>
                    <?php else: ?>
                        <div class="badge" style="background:var(--charcoal);">NEW DROP!</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="hero-text">
                <h2 class="glitch-text">ENTER THE <br><span class="text-primary">SKATE</span><br> UNIVERSE</h2>
                <p class="trippy-sub">the third annual collection</p>
                <div class="btn-group">
                    <a href="shop.php" class="btn btn-primary" style="text-decoration:none;">SHOP NOW</a>
                    <a href="marketplace.php" class="btn btn-outline" style="text-decoration:none;">SELL GEAR</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="image-wrapper">
                    <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuDjzeekzJbpA7zuORIlXb-v0di5yyyCSdMKXQOpi_M2Oexuom29aeexZtBUZZv-5gl52K1rgC7RlsHFwVZz79NsHLNNf_2XeTi_a1cnGwqUsJVs7PO4djHcTK3QQm2zDsjGawS0j3eUY5optPWgJv6yQqHBmwSOJTtq9poWnNuv08EIn4zAb-WTPRyUmzGi3GdSQXs94FnkXj2VOxYcmzZ5OWMyBF4gR-bRnRP5ZgaIZQLPTwOBO_fCHQOb7arCRwUAWjPvwgNjkSo" alt="Skater">
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>


<section class="products container">

    <div class="section-header">
        <h3>NEW <span class="header-span">SHOP</span> DROPS</h3>
        <a href="shop.php" class="view-all">VIEW ALL +</a>
    </div>

    <div class="product-grid">
        <?php if (!empty($shop_drops)): ?>
            <?php foreach ($shop_drops as $drop): ?>
                <a href="product.php?id=<?php echo $drop['id']; ?>" style="text-decoration: none; color: inherit;">
                    <div class="product-card grainy-card">
                        <div class="card-img" style="position: relative; overflow: hidden;">
                            <?php if((int)$drop['quantity'] <= 0): ?>
                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; z-index: 10; pointer-events: none;">
                                    <span style="background: #E11D48; color: #fff; padding: 8px 20px; font-family: 'Arial Black', sans-serif; font-size: 1.1rem; transform: rotate(-12deg); border: 3px solid #000; box-shadow: 5px 5px 0 #000; text-transform: uppercase; letter-spacing: 1px;">
                                        OUT OF STOCK
                                    </span>
                                </div>
                            <?php endif; ?>
                            <img src="<?php echo htmlspecialchars($drop['image_url']); ?>" alt="<?php echo htmlspecialchars($drop['title']); ?>" style="<?php echo ((int)$drop['quantity'] <= 0) ? 'filter: grayscale(100%) blur(1px); opacity: 0.7;' : ''; ?>">
                        </div>

                        <div class="card-info">
                            <div style="flex: 1; min-width: 0; padding-right: 10px;">
                                <h4 style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($drop['title']); ?></h4>
                                <p style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($drop['brand']); ?></p>
                            </div>
                            <div style="text-align: right;">
                                <?php if (!empty($drop['discount_price']) && (int)$drop['quantity'] > 0): ?>
                                    <span class="price" style="text-decoration: line-through; color: #888; font-size: 1.2rem; display: block; margin-bottom: -5px;">$<?php echo number_format($drop['price'], 2); ?></span>
                                    <span class="price" style="color: var(--primary);">$<?php echo number_format($drop['discount_price'], 2); ?></span>
                                <?php else: ?>
                                    <span class="price" style="<?php echo ((int)$drop['quantity'] <= 0) ? 'text-decoration: line-through; color: #888;' : ''; ?>">$<?php echo number_format($drop['price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; border: 4px dashed var(--charcoal); font-family: 'Staatliches', sans-serif; font-size: 2rem;">
                NO DROPS AVAILABLE RIGHT NOW.
            </div>
        <?php endif; ?>
    </div>
</section>


<section class="reels-section">
    <div class="container">
    <div class="section-header-reels">
        <div class="header-title-group">
            <h3>THE REELS</h3>
            <p class="rec-text">REC<span class="rec-indicator">●</span></p>
        </div>
        <a href="reels.php" class="btn-submit" style="text-decoration:none;">SUBMIT YOUR FOOTAGE</a>
    </div>

        <div class="reels-grid">
            <?php if (!empty($reels)): ?>
                <div class="main-monitor" id="monitor-container">
                    <div class="monitor-screen" id="play-trigger">
                        <div class="scanlines"></div>
                        <div class="glitch-overlay" id="glitch-layer"></div>
                        <iframe id="reel-video-display" style="width:100%; height:100%; border:0; position:relative; z-index:10;" src="<?php echo htmlspecialchars($reels[0]['embed_url']); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    
                    <div class="monitor-footer">
                        <div class="footer-top-row">
                            <div>
                                <h4 id="reel-title"><?php echo htmlspecialchars($reels[0]['title']); ?></h4>
                                <p id="reel-meta">FILMED BY: @<?php echo htmlspecialchars($reels[0]['username']); ?> // <?php echo date('M d, Y', strtotime($reels[0]['created_at'])); ?></p>
                            </div>
                            <div class="platform-badge" id="reel-platform" style="background: var(--primary); color: white; padding: 4px 8px; font-family: 'Staatliches', sans-serif; letter-spacing: 1px; border: 2px solid #000; transform: skew(-5deg);"><?php echo htmlspecialchars($reels[0]['platform']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="tape-library" id="tape-list">
                    <?php 
                    $total_reels = count($reels);
                    foreach($reels as $index => $reel): 
                        $tape_number = $total_reels - $index;
                    ?>
                        <div class="tape-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                             data-video="<?php echo htmlspecialchars($reel['embed_url']); ?>" 
                             data-title="<?php echo htmlspecialchars($reel['title']); ?>" 
                             data-meta="FILMED BY: @<?php echo htmlspecialchars($reel['username']); ?> // <?php echo date('M d, Y', strtotime($reel['created_at'])); ?>"
                             data-platform="<?php echo htmlspecialchars($reel['platform']); ?>">
                            <span class="tape-label">TAPE_<?php echo str_pad($tape_number, 2, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars(substr($reel['title'], 0, 15)); ?><?php echo strlen($reel['title']) > 15 ? '...' : ''; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="grid-column: 1 / -1; width: 100%; text-align: center; padding: 4rem; border: 4px dashed var(--charcoal); font-family: 'Staatliches', sans-serif; font-size: 2rem;">
                    NO TAPES FOUND IN THE ARCHIVE.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>


<section class="products container">

    <div class="section-header">
        <h3>NEW <span class="header-span">MARKETPLACE</span> DROPS</h3>
        <a href="marketplace.php" class="view-all">VIEW ALL +</a>
    </div>

    <div class="product-grid">
        <?php if (!empty($market_products)): ?>
            <?php foreach ($market_products as $market): ?>
                <a href="product.php?id=<?php echo $market['id']; ?>" style="text-decoration: none; color: inherit;">
                    <div class="product-card grainy-card">
                        <div class="card-img" style="position: relative; overflow: hidden;">
                            <?php if((int)$market['quantity'] <= 0): ?>
                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; z-index: 10; pointer-events: none;">
                                    <span style="background: #E11D48; color: #fff; padding: 8px 20px; font-family: 'Arial Black', sans-serif; font-size: 1.1rem; transform: rotate(-12deg); border: 3px solid #000; box-shadow: 5px 5px 0 #000; text-transform: uppercase; letter-spacing: 1px;">
                                        SOLD OUT
                                    </span>
                                </div>
                            <?php endif; ?>
                            <img src="<?php echo htmlspecialchars($market['image_url']); ?>" alt="<?php echo htmlspecialchars($market['title']); ?>" style="<?php echo ((int)$market['quantity'] <= 0) ? 'filter: grayscale(100%) blur(1px); opacity: 0.7;' : ''; ?>">
                        </div>

                        <div class="card-info">
                            <div style="flex: 1; min-width: 0; padding-right: 10px;">
                                <h4 style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($market['title']); ?></h4>
                                <p style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($market['brand']); ?></p>
                            </div>
                            <span class="price" style="<?php echo ((int)$market['quantity'] <= 0) ? 'text-decoration: line-through; color: #888;' : ''; ?>">$<?php echo number_format($market['price'], 2); ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; border: 4px dashed var(--charcoal); font-family: 'Staatliches', sans-serif; font-size: 2rem;">
                NO MARKETPLACE ITEMS LISTED YET.
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="marquee-banner marketplace-section">
    <div class="marquee-content marketplace-marquee">
        <img src="../assets/svg/vans_logo_svg.svg" alt="Vans" class="brand-logo">
        <img src="../assets/svg/nike_sb_logo_svg.svg" alt="Nike SB" class="brand-logo">
        <img src="../assets/svg/spitfire_logo_svg.svg" alt="Spitfire" class="brand-logo">
        <img src="../assets/svg/santa_cruz_logo_svg.svg" alt="Santa Cruz" class="brand-logo">
        <img src="../assets/svg/element_logo_svg.svg" alt="Element" class="brand-logo">
        <img src="../assets/svg/trasher_logo_svg.svg" alt="Thrasher" class="brand-logo">

        <img src="../assets/svg/vans_logo_svg.svg" alt="Vans" class="brand-logo">
        <img src="../assets/svg/nike_sb_logo_svg.svg" alt="Nike SB" class="brand-logo">
        <img src="../assets/svg/spitfire_logo_svg.svg" alt="Spitfire" class="brand-logo">
        <img src="../assets/svg/santa_cruz_logo_svg.svg" alt="Santa Cruz" class="brand-logo">
        <img src="../assets/svg/element_logo_svg.svg" alt="Element" class="brand-logo">
        <img src="../assets/svg/trasher_logo_svg.svg" alt="Thrasher" class="brand-logo">
    </div>
</div>

<section class="the-mag section">
    <div class="container">
        <div class="section-header-reels">
            <div class="header-title-group">
                <h3>THE MAG</h3>
                <p class="issue-no">VOL. 04 // ISSUE 12</p>
            </div>
            <a href="the-mag.php" class="btn-submit" style="text-decoration:none;">VIEW ARCHIVE</a>
        </div>

        <div class="mag-grid">
            <?php if (!empty($mag_posts)): ?>
                <?php $featured = $mag_posts[0]; ?>
                <article class="mag-card featured">
                    <div class="mag-img-wrap">
                        <img src="<?php echo htmlspecialchars($featured['cover_image']); ?>" alt="<?php echo htmlspecialchars($featured['title']); ?>">
                        <span class="mag-tag">FEATURED</span>
                    </div>
                    <div class="mag-content">
                        <span class="mag-date"><?php echo date('M d, Y', strtotime($featured['published_at'])); ?></span>
                        <h2><?php echo htmlspecialchars($featured['title']); ?></h2>
                        <p><?php echo htmlspecialchars(substr($featured['short_description'], 0, 150)); ?>...</p>
                        <a href="the-mag.php?slug=<?php echo urlencode($featured['slug']); ?>" class="read-more">READ STORY <span class="material-icons">arrow_forward</span></a>
                    </div>
                </article>

                <div class="mag-sidebar">
                    <?php for ($i = 1; $i < count($mag_posts); $i++): ?>
                        <article class="mag-card small">
                            <div class="mag-img-wrap">
                                <img src="<?php echo htmlspecialchars($mag_posts[$i]['cover_image']); ?>" alt="<?php echo htmlspecialchars($mag_posts[$i]['title']); ?>">
                            </div>
                            <div class="mag-content">
                                <h3><?php echo htmlspecialchars($mag_posts[$i]['title']); ?></h3>
                                <a href="the-mag.php?slug=<?php echo urlencode($mag_posts[$i]['slug']); ?>" class="read-more">READ STORY</a>
                            </div>
                        </article>
                    <?php endfor; ?>
                </div>
            <?php else: ?>
                <div style="grid-column: 1 / -1; width: 100%; text-align: center; padding: 4rem; border: 4px dashed var(--charcoal); font-family: 'Staatliches', sans-serif; font-size: 2rem;">
                    NO MAGAZINE POSTS YET.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>


<section class="guestbook container">
    <div class="section-header">
        <h3>GUESTBOOK <span class="header-span">SHOUTOUTS</span></h3>
        <a href="shoutouts.php" class="view-all">VIEW ALL +</a>
    </div>

    <div class="guestbook-grid" style="grid-template-columns: 1fr;">
        <div class="reviews-container custom-scrollbar" style="max-height: none;">
            <?php if (!empty($shoutouts)): ?>
                <?php foreach($shoutouts as $shout): ?>
                    <div class="product-card grainy-card review-item">
                        <div class="review-header">
                            <h4 style="text-transform: uppercase;">@<?php echo htmlspecialchars($shout['username']); ?></h4>
                            <span class="stars">★★★★★</span>
                        </div>
                        <p>"<?php echo htmlspecialchars(substr($shout['body'], 0, 150)); ?><?php echo strlen($shout['body']) > 150 ? '...' : ''; ?>"</p>
                        <span class="timestamp"><?php echo strtoupper(time_elapsed_string($shout['created_at'])); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; border: 4px dashed var(--charcoal); font-family: 'Staatliches', sans-serif; font-size: 1.5rem;">
                    NO SHOUTOUTS POSTED YET.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="service-info container">
    <div class="section-header">
        <h3>SERVICES</h3>
    </div>

    <div class="info-grid">
        <div class="info-block grainy-card">
            <div class="icon-badge">
                <span class="material-icons">public</span>
            </div>
            <h4>WORLDWIDE</h4>
            <p>Fast shipping to every corner of the universe.</p>
        </div>

        <div class="info-block grainy-card">
            <div class="icon-badge">
                <span class="material-icons">verified_user</span>
            </div>
            <h4>SECURE MARKET</h4>
            <p>Every board is checked by our crew before payout.</p>
        </div>

        <div class="info-block grainy-card">
            <div class="icon-badge">
                <span class="material-icons">favorite</span>
            </div>
            <h4>GIVING BACK</h4>
            <p>Supporting local skatepark builds since '26.</p>
        </div>
    </div>
</section>


<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const indexTapes = document.querySelectorAll('.reels-section .tape-item');
    const indexIframe = document.getElementById('reel-video-display');
    const indexMonitor = document.getElementById('monitor-container');
    const indexTitle = document.getElementById('reel-title');
    const indexMeta = document.getElementById('reel-meta');
    const indexPlatform = document.getElementById('reel-platform');

    if(indexTapes.length > 0 && indexIframe) {
        indexTapes.forEach(tape => {
            tape.addEventListener('click', function() {
                if(indexMonitor) indexMonitor.classList.add('glitch-active');
                setTimeout(() => {
                    indexTapes.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    indexIframe.src = this.getAttribute('data-video');
                    if(indexTitle) indexTitle.innerText = this.getAttribute('data-title');
                    if(indexMeta) indexMeta.innerHTML = this.getAttribute('data-meta');
                    if(indexPlatform) indexPlatform.innerText = this.getAttribute('data-platform');
                    setTimeout(() => {
                        if(indexMonitor) indexMonitor.classList.remove('glitch-active');
                    }, 300);
                }, 200);
            });
        });
    }
});
</script>

</body>
</html>