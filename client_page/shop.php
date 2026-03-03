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

<div class="shop-header-title container">
    <h2 class="glitch-text-shop">SHOP<br><span class="text-primary">DROPS</span></h2>
</div>

<section class="shop-layout container">
    <aside class="shop-sidebar">

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

    </aside>

    <div class="shop-main-grid">
        <div class="shop-controls">
                <div class="controls-upper">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <input type="text" placeholder="SEARCH GEAR..." id="shop-search">
                            <button class="search-btn"><span class="material-icons">search</span></button>
                        </div>
                        <p class="results-count">SHOWING 1-6 OF 24 ITEMS</p>
                    </div>

                    <div class="sort-section">
                        <select class="sort-dropdown">
                            <option>SORT: NEWEST</option>
                            <option>SORT: PRICE (LOW-HIGH)</option>
                            <option>SORT: PRICE (HIGH-LOW)</option>
                        </select>
                    </div>
                </div>
            </div>
    

      
        <div class="product-grid">
            
            <div class="product-card grainy-card compact-card">
                <div class="card-img">
                    <img src="https://dankiesskateboards.com/cdn/shop/files/rn-image_picker_lib_temp_7e38f2fb-809d-44f7-a5a0-56fada45739a.jpg?v=1739281202&width=1024" alt="Deck">
                </div>
                <div class="card-info">
                    <div>
                        <h4>VOID DECK v2</h4>
                        <p>LIMITED EDITION</p>
                    </div>
                    <span class="price">$85</span>
                </div>
            </div>

            <div class="product-card grainy-card compact-card">
                <div class="card-img">
                    <img src="https://idioma.world/cdn/shop/files/Kosmos-Hood-Full-Web-Graphic_1500x1500.jpg?v=1729783863" alt="Hoodie">
                </div>
                <div class="card-info">
                    <div>
                        <h4>COSMOS HOODIE</h4>
                        <p>HEAVYWEIGHT</p>
                    </div>
                    <span class="price">$120</span>
                </div>
            </div>

            <div class="product-card grainy-card compact-card">
                <div class="card-img">
                    <img src="https://images.unsplash.com/photo-1531565637446-32307b194362?auto=format&fit=crop&q=80&w=800" alt="Wheels">
                </div>
                <div class="card-info">
                    <div>
                        <h4>TRIPPY WHEELS</h4>
                        <p>54MM / 99A</p>
                    </div>
                    <span class="price">$45</span>
                </div>
            </div>

            

        </div>

        <div class="pagination">
            <button class="btn btn-outline">&lt; PREV</button>
            <button class="btn btn-primary">1</button>
            <button class="btn btn-outline">2</button>
            <button class="btn btn-outline">3</button>
            <button class="btn btn-outline">NEXT &gt;</button>
        </div>

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

<?php include 'footer.php'; ?>

<body>
</html>