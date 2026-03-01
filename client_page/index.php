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
        <h3>NEW <span class="header-span">SHOP</span> DROPS</h3>
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
    <div class="header-title-group">
        <h3>THE REELS</h3>
        <p class="rec-text">REC<span class="rec-indicator">●</span></p>
    </div>

    <button class="btn-submit" id="open-upload">
        SUBMIT YOUR FOOTAGE
    </button>
</div>

        <div class="reels-grid">
            <div class="main-monitor" id="monitor-container">
                <div class="monitor-screen" id="play-trigger">
                    <div class="scanlines"></div>
                    <div class="glitch-overlay" id="glitch-layer"></div>
                    <video id="video-display" playsinline>
                        <source src="../assets/Skate_Vids/skulls-dylmatic.mp4" type="video/mp4">
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
                <div class="tape-item active" data-video="../assets/Skate_Vids/skulls-dylmatic.mp4" data-title="SKULLS" data-meta="FILMED BY: DYLMATIC // 01:08">
                    <span class="tape-label">TAPE_01: SKULLS</span>
                </div>
                <div class="tape-item" data-video="../assets/Skate_Vids/hoseapeeters-ballroom.mp4" data-title="BALLROOM" data-meta="FILMED BY: Hosea Peeters // 01:54">
                    <span class="tape-label">TAPE_02: BALLROOM</span>
                </div>
                <div class="tape-item" data-video="../assets/Skate_Vids/tearz-askateedit-danielmoore.mp4" data-title="A SKATE EDIT" data-meta="FILMED BY: Tearz and Daniel Moore // 01:42">
                    <span class="tape-label">TAPE_03: A_SKATE_EDIT</span>
                </div>
            </div>
        </div>
    </div>
</section>


<section class="products container">

    <div class="section-header">
        <h3>NEW <span class="header-span">MARKETPLACE</span> DROPS</h3>
        <a href="#" class="view-all">VIEW ALL +</a>
    </div>

    <div class="product-grid">

        <div class="product-card grainy-card">
            <div class="card-img">
                <img src="https://u-mercari-images.mercdn.net/photos/m11897013105_1.jpg" alt="Decks">
            </div>

            <div class="card-info">
                <div>
                    <h4>3 SKATEBOARD DECKS</h4>
                    <p>USED</p>
                </div>
                <span class="price">$50</span>
            </div>
        </div>
        
        <div class="product-card grainy-card">
            <div class="card-img">
                <img src="https://boardrecycler.com/oc-content/uploads/1/355.jpg" alt="Wheels">
            </div>
            <div class="card-info">
                <div>
                    <h4>COSTA MESA WHEELS</h4>
                    <p>USED /4X</p>
                </div>
                <span class="price">$15</span>
            </div>
        </div>

        <div class="product-card grainy-card">
            <div class="card-img">
                <img src="https://edge.images.sidelineswap.com/production/090/500/431/87e20cc713cf32b6_original.jpeg?height=500&optimize=high" alt="Trucks">
            </div>
            <div class="card-info">
                <div>
                    <h4>TRUCKS</h4>
                    <p>USED / 2X</p>
                </div>
                <span class="price">$20</span>
            </div>
        </div>
        
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
            <button class="btn-submit">VIEW ARCHIVE</button>
        </div>

        <div class="mag-grid">
            <article class="mag-card featured">
                <div class="mag-img-wrap">
                    <img src="https://concretewaves.com/wp-content/uploads/2023/10/powerslide.jpg" alt="Featured Article">
                    <span class="mag-tag">FEATURED</span>
                </div>
                <div class="mag-content">
                    <span class="mag-date">FEB 20, 2026</span>
                    <h2>DOWNHILL DRIFTING: THE ART OF THE POWERSLIDE</h2>
                    <p>We sit down with the crew from the East Side to discuss why the streets are getting rougher and the wheels are getting harder...</p>
                    <a href="#" class="read-more">READ STORY <span class="material-icons">arrow_forward</span></a>
                </div>
            </article>

            <div class="mag-sidebar">
                <article class="mag-card small">
                    <div class="mag-img-wrap">
                        <img src="https://blog.gotrip.lv/wp-content/uploads/2020/10/104586586_2971723212955985_954076223985500886_o.jpg" alt="Article 2">
                    </div>
                    <div class="mag-content">
                        <h3>TOP 5 SPOTS IN THE CITY</h3>
                        <a href="#" class="read-more">READ STORY</a>
                    </div>
                </article>

                <article class="mag-card small">
                    <div class="mag-img-wrap">
                        <img src="https://www.longboarderlabs.com/wp-content/uploads/2022/05/paris-v3-trucks-purple-tide-flip-2.jpg" alt="Article 3">
                    </div>
                    <div class="mag-content">
                        <h3>GEAR REVIEW: V3 TRUCKS</h3>
                        <a href="#" class="read-more">READ STORY</a>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>


<section class="guestbook container">
    <div class="section-header">
        <h3>GUESTBOOK <span class="header-span">SHOUTOUTS</span></h3>
        <a href="#" class="view-all">VIEW ALL +</a>
    </div>

    <div class="guestbook-grid">
        <div class="product-card grainy-card guest-form-card">
            <h4>DROP A LINE</h4>
            <form class="simple-form">
                <div class="input-group">
                    <input type="text" placeholder="SKATER HANDLE" required>
                    <input type="number" placeholder="STOKE LEVEL (1-5)" min="1" max="5" required>
                </div>
                <textarea placeholder="WHAT'S THE WORD?" rows="4"></textarea>
                <button class="btn btn-primary">POST SHOUTOUT</button>
            </form>
        </div>

        <div class="reviews-container custom-scrollbar">
            <div class="product-card grainy-card review-item">
                <div class="review-header">
                    <h4>@TRICK_WIZARD</h4>
                    <span class="stars">★★★★★</span>
                </div>
                <p>"The V3 trucks are absolute tanks. Fast shipping!"</p>
                <span class="timestamp">2 HOURS AGO</span>
            </div>

            <div class="product-card grainy-card review-item">
                <div class="review-header">
                    <h4>RAD_DAD_88</h4>
                    <span class="stars">★★★★☆</span>
                </div>
                <p>"Found a vintage board in 20 minutes. Legit."</p>
                <span class="timestamp">YESTERDAY</span>
            </div>
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


</body>
</html>