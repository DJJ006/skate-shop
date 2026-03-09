document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. MOBILE HAMBURGER NAVIGATION ---
    const menuBtn = document.getElementById('menu-btn');
    const navMenu = document.getElementById('nav-menu');

    if (menuBtn && navMenu) {
        menuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            
            // Toggle between menu and close icons
            const icon = menuBtn.querySelector('.material-icons');
            if (navMenu.classList.contains('active')) {
                icon.textContent = 'close';
            } else {
                icon.textContent = 'menu';
            }
        });

        // Close menu when clicking a link (ideal for single-page sites)
        document.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                menuBtn.querySelector('.material-icons').textContent = 'menu';
            });
        });
    }


    // 1. SELECT ELEMENTS
    const video = document.getElementById('video-display');
    const playIcon = document.getElementById('play-icon');
    const timer = document.getElementById('timer');
    const monitor = document.getElementById('monitor-container');
    const volumeSlider = document.getElementById('volume-slider');
    const tapes = document.querySelectorAll('.tape-item');
    const progressBar = document.getElementById('progress-bar');
    const currTimeText = document.getElementById('current-time');
    const durationText = document.getElementById('total-duration');
    

    const slides = document.querySelectorAll('.commercial-slide');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.getElementById('prev-ad');
    const nextBtn = document.getElementById('next-ad');
    let currentSlide = 0;
    

    // ------------VIDEO PLAYERIS---------------
    
    // Format for progress bar (M:SS)
    function formatTime(seconds) {
        let m = Math.floor(seconds / 60);
        let s = Math.floor(seconds % 60);
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

    // Format for SMPTE Timecode (HH:MM:SS:FF)
    function updateTimecode() {
        let fps = 30;
        let totalSeconds = video.currentTime;
        
        let hours = Math.floor(totalSeconds / 3600);
        let minutes = Math.floor((totalSeconds % 3600) / 60);
        let seconds = Math.floor(totalSeconds % 60);
        let frames = Math.floor((totalSeconds % 1) * fps);

        const pad = (n) => n.toString().padStart(2, '0');
        timer.innerText = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}:${pad(frames)}`;
        requestAnimationFrame(updateTimecode);
    }

    // Tape Switcher Logic
    function playTape(index) {
        const tape = tapes[index];
        
        // Trigger Glitch Effect
        monitor.classList.add('glitch-active');
        
        setTimeout(() => {
            // UI Updates
            tapes.forEach(t => t.classList.remove('active'));
            tape.classList.add('active');

            // Source Updates
            video.querySelector('source').src = tape.getAttribute('data-video');
            document.getElementById('reel-title').innerText = tape.getAttribute('data-title');
            document.getElementById('reel-meta').innerText = tape.getAttribute('data-meta');

            video.load();
            video.play();
            playIcon.style.opacity = '0';
            
            // Remove glitch after load
            setTimeout(() => monitor.classList.remove('glitch-active'), 300);
        }, 200);
    }

    // 3. PROGRESS BAR & SCRUBBING
    
    // Update Slider as Video Plays
    video.addEventListener('timeupdate', () => {
        if (!video.duration) return;
        
        const percentage = (video.currentTime / video.duration) * 100;
        progressBar.value = percentage;
        
        // CSS variable for the red fill effect
        progressBar.style.setProperty('--buffered', `${percentage}%`);
        currTimeText.innerText = formatTime(video.currentTime);
    });

    // Set duration once file loads
    video.addEventListener('loadedmetadata', () => {
        durationText.innerText = formatTime(video.duration);
    });

    // Dragging the bar (Scrubbing)
    progressBar.addEventListener('input', () => {
        const seekTime = (progressBar.value / 100) * video.duration;
        video.currentTime = seekTime;
        progressBar.style.setProperty('--buffered', `${progressBar.value}%`);
    });

    // 4. EVENT LISTENERS

    // Autoplay Next Tape
    video.addEventListener('ended', () => {
        let currentIndex = Array.from(tapes).findIndex(t => t.classList.contains('active'));
        let nextIndex = (currentIndex + 1) % tapes.length;
        playTape(nextIndex);
    });

    // Volume Control
    volumeSlider.addEventListener('input', (e) => {
        video.volume = e.target.value;
    });

    // Manual Tape Clicks
    tapes.forEach((tape, index) => {
        tape.addEventListener('click', () => playTape(index));
    });

    // Master Play/Pause Trigger
    document.getElementById('play-trigger').addEventListener('click', () => {
        if (video.paused) { 
            video.play(); 
            playIcon.style.opacity = '0'; 
        } else { 
            video.pause(); 
            playIcon.style.opacity = '1'; 
        }
    });

    // 5. INITIALIZE
    updateTimecode();
});

// - - - - - - - - - - - - - - - - - - - - - - - 




function showSlide(index) {
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    
    slides[index].classList.add('active');
    dots[index].classList.add('active');
}

nextBtn.addEventListener('click', () => {
    currentSlide = (currentSlide + 1) % slides.length;
    showSlide(currentSlide);
});

prevBtn.addEventListener('click', () => {
    currentSlide = (currentSlide - 1 + slides.length) % slides.length;
    showSlide(currentSlide);
});

// Auto-slide every 5 seconds
setInterval(() => {
    currentSlide = (currentSlide + 1) % slides.length;
    showSlide(currentSlide);
}, 5000);












<?php 
include 'db.php'; 

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

        <a href="product.php" style="text-decoration: none; color: inherit;">
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
        </a>


        <a href="product.php" style="text-decoration: none; color: inherit;">
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
        </a>

        <a href="product.php" style="text-decoration: none; color: inherit;">
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
        </a>
            

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