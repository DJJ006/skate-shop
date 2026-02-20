<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>

<header class="main-header">

    <div class="container header-content">

        <h1 class="logo">SKATE<span>SHOP</span></h1>

        <nav class="desktop-nav">
        <ul class="nav-links">
            <li><a href="#" class="nav-item">SHOP</a></li>
            <li><a href="#" class="nav-item">MARKET</a></li>
            <li><a href="#" class="nav-item">COMMUNITY</a></li>
            <li><a href="#" class="login-btn">LOGIN</a></li>
        </ul>
        </nav>

        <!-- <div class="mobile-menu-icon" id="menu-btn">
            <span class="material-icons">menu</span>
        </div> -->

    </div>
</header>

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
        <div class="hero-text">
            <h2 class="glitch-text">ENTER THE <br><span class="text-primary">SKATE</span><br> UNIVERSE</h2>
            <p class="trippy-sub">the third annual collection</p>
            <div class="btn-group">
                <button class="btn btn-primary">SHOP NOW</button>
                <button class="btn btn-outline">SELL GEAR</button>
            </div>
        </div>
        <div class="hero-image">
            <div class="image-wrapper">
                <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuDjzeekzJbpA7zuORIlXb-v0di5yyyCSdMKXQOpi_M2Oexuom29aeexZtBUZZv-5gl52K1rgC7RlsHFwVZz79NsHLNNf_2XeTi_a1cnGwqUsJVs7PO4djHcTK3QQm2zDsjGawS0j3eUY5optPWgJv6yQqHBmwSOJTtq9poWnNuv08EIn4zAb-WTPRyUmzGi3GdSQXs94FnkXj2VOxYcmzZ5OWMyBF4gR-bRnRP5ZgaIZQLPTwOBO_fCHQOb7arCRwUAWjPvwgNjkSo" alt="Skater">
                <div class="badge">SOLD OUT!</div>
            </div>
        </div>
    </div>
</section>


<section class="products container">

    <div class="section-header">
        <h3>NEW DROPS</h3>
        <a href="#" class="view-all">VIEW ALL +</a>
    </div>

    <div class="product-grid">

        <div class="product-card grainy-card">
            <div class="card-img">
                <img src="https://dankiesskateboards.com/cdn/shop/files/rn-image_picker_lib_temp_7e38f2fb-809d-44f7-a5a0-56fada45739a.jpg?v=1739281202&width=1024" alt="Deck">
            </div>

            <div class="card-info">
                <div>
                    <h4>VOID DECK v2</h4>
                    <p>LIMITED EDITION / 50 PCS</p>
                </div>
                <span class="price">$85</span>
            </div>
        </div>
        
        <div class="product-card grainy-card">
            <div class="card-img">
                <img src="https://idioma.world/cdn/shop/files/Kosmos-Hood-Full-Web-Graphic_1500x1500.jpg?v=1729783863" alt="Hoodie">
            </div>
            <div class="card-info">
                <div>
                    <h4>COSMOS HOODIE</h4>
                    <p>HEAVYWEIGHT FLEECE</p>
                </div>
                <span class="price">$120</span>
            </div>
        </div>

        <div class="product-card grainy-card">
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
</section>


<section class="reels-section">
    <div class="container">
        <div class="section-header-reels">
            <h3>THE REELS</h3>
            <p class="rec-text">REC<span class="rec-indicator">●</span></p>
        </div>

        <div class="reels-grid">
            <div class="main-monitor" id="monitor-container">
                <div class="monitor-screen" id="play-trigger">
                    <div class="scanlines"></div>
                    <div class="glitch-overlay" id="glitch-layer"></div>
                    <video id="video-display" playsinline>
                        <source src="./Skate_Vids/skulls-dylmatic.mp4" type="video/mp4">
                    </video>
                    <div class="play-btn-overlay" id="play-icon">
                        <span class="material-icons">play_circle</span>
                    </div>
                </div>
                
                <div class="monitor-footer">
                    <div class="footer-top-row">
                        <div>
                            <h4 id="reel-title">skulls</h4>
                            <p id="reel-meta">FILMED BY: DYLMATIC</p>
                        </div>

                        <div class="timer-controls-group">
                            <div class="time-code" id="timer">00:00:00:00</div>
                            <div class="volume-control">
                                <span class="material-icons">volume_up</span>
                                <input type="range" id="volume-slider" min="0" max="1" step="0.1" value="0.5">
                            </div>
                        </div>
                    </div>

                    <div class="scrub-container">
                        <span class="timestamp" id="current-time">0:00</span>
                        <input type="range" id="progress-bar" min="0" max="100" value="0">
                        <span class="timestamp" id="total-duration">0:00</span>
                    </div>
                </div>
            </div>

            <div class="tape-library" id="tape-list">
                <div class="tape-item active" data-video="./Skate_Vids/skulls-dylmatic.mp4" data-title="SKULLS" data-meta="FILMED BY: DYLMATIC // 01:08">
                    <span class="tape-label">TAPE_01: SKULLS</span>
                </div>
                <div class="tape-item" data-video="./Skate_Vids/hoseapeeters-ballroom.mp4" data-title="BALLROOM" data-meta="FILMED BY: Hosea Peeters // 01:54">
                    <span class="tape-label">TAPE_02: BALLROOM</span>
                </div>
                <div class="tape-item" data-video="./Skate_Vids/tearz-askateedit-danielmoore.mp4" data-title="A SKATE EDIT" data-meta="FILMED BY: Tearz and Daniel Moore // 01:42">
                    <span class="tape-label">TAPE_03: A_SKATE_EDIT</span>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="main-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <h2 class="footer-logo">SKATE<span>SHOP</span></h2>
                <p>The premier destination for the skate community. Built for the streets, inspired by the universe. Raw, DIY, and unapologetically bold.</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-links">
                <h4>EXPLORE</h4>
                <ul>
                    <li><a href="#">NEW ARRIVALS</a></li>
                    <li><a href="#">SKATEBOARDS</a></li>
                    <li><a href="#">STREETWEAR</a></li>
                    <li><a href="#">ACCESSORIES</a></li>
                    <li><a href="#">MARKETPLACE</a></li>
                </ul>
            </div>

            <div class="footer-links">
                <h4>SUPPORT</h4>
                <ul>
                    <li><a href="#">SHIPPING INFO</a></li>
                    <li><a href="#">RETURNS</a></li>
                    <li><a href="#">CONTACT US</a></li>
                    <li><a href="#">PRIVACY POLICY</a></li>
                    <li><a href="#">TERMS OF SERVICE</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© 2026 SKATESHOP UNIVERSE. ALL RIGHTS RESERVED. NO REFUNDS IN SPACE.</p>
        </div>
    </div>
</footer>
    
</body>
</html>