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
        <div class="mobile-filter-wrapper">
            <button type="button" class="filter-toggle-btn" id="filterToggle">
                FILTER JUNK <span class="material-icons">expand_more</span>
            </button>
            
            <div class="filter-content-inner" id="filterContent">
                <div class="filter-group">
                    <h4>CONDITION</h4>
                    <ul class="filter-list">
                        <li><label><input type="checkbox" checked> ALL</label></li>
                        <li><label><input type="checkbox"> DEADSTOCK (MINT)</label></li>
                        <li><label><input type="checkbox"> LIGHTLY SCUFFED</label></li>
                        <li><label><input type="checkbox"> TRASHED (CHEAP)</label></li>
                        <li><label><input type="checkbox"> WALL HANGERS</label></li>
                    </ul>
                </div>

                <div class="filter-group">
                    <h4>GEAR TYPE</h4>
                    <ul class="filter-list">
                        <li><label><input type="checkbox"> VINTAGE DECKS</label></li>
                        <li><label><input type="checkbox"> USED TRUCKS</label></li>
                        <li><label><input type="checkbox"> STREETWEAR</label></li>
                        <li><label><input type="checkbox"> COLLECTIBLES</label></li>
                    </ul>
                </div>
            </div>
        </div>
    </aside>

    <div class="shop-main-grid">
        <div class="shop-controls">
            <div class="controls-upper">
                <div class="search-section">
                    <div class="search-wrapper">
                        <input type="text" placeholder="SEARCH THE SCRAPHEAP..." id="shop-search">
                        <button class="search-btn"><span class="material-icons">search</span></button>
                    </div>
                    <p class="results-count">SHOWING 1-6 OF 142 LISTINGS</p>
                </div>

                <div class="sort-section">
                    <select class="sort-dropdown">
                        <option>SORT: NEWEST LISTINGS</option>
                        <option>SORT: CHEAPEST</option>
                        <option>SORT: RAREST</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="product-grid">
            <div class="product-card grainy-card compact-card marketplace-card">
                <div class="card-img">
                    <span class="condition-badge mint">MINT / WALL HANGER</span>
                    <img src="https://images.unsplash.com/photo-1520045892732-304bc3ac5d8e?auto=format&fit=crop&q=80&w=800" alt="Vintage Deck">
                </div>
                <div class="card-info">
                    <div>
                        <h4>OG 1999 BLIND DECK</h4>
                        <p>SELLER: <span class="seller-name">@SkateRat99</span></p>
                    </div>
                    <span class="price">$250</span>
                </div>
            </div>

            <div class="product-card grainy-card compact-card marketplace-card">
                <div class="card-img">
                    <span class="condition-badge beat">BEAT UP / SKATEABLE</span>
                    <img src="https://images.unsplash.com/photo-1547447134-cd3f5c716030?auto=format&fit=crop&q=80&w=800" alt="Used Trucks">
                </div>
                <div class="card-info">
                    <div>
                        <h4>INDEPENDENT 149 TRUCKS</h4>
                        <p>SELLER: <span class="seller-name">@KickflipKenny</span></p>
                    </div>
                    <span class="price">$20</span>
                </div>
            </div>

            <div class="product-card grainy-card compact-card marketplace-card">
                <div class="card-img">
                    <span class="condition-badge good">WORN ONCE</span>
                    <img src="https://idioma.world/cdn/shop/files/Kosmos-Hood-Full-Web-Graphic_1500x1500.jpg?v=1729783863" alt="Hoodie">
                </div>
                <div class="card-info">
                    <div>
                        <h4>SUPREME 2018 HOODIE</h4>
                        <p>SELLER: <span class="seller-name">@HypeBeast_22</span></p>
                    </div>
                    <span class="price">$140</span>
                </div>
            </div>
        </div>

        <div class="pagination">
            <button class="btn btn-outline">< PREV</button>
            <button class="btn btn-primary">1</button>
            <button class="btn btn-outline">2</button>
            <button class="btn btn-outline">3</button>
            <button class="btn btn-outline">NEXT ></button>
        </div>
    </div>
</section>

<section class="bounty-board-section container">
    <div class="bounty-wrapper grainy-card">
        <div class="bounty-header">
            <h2><span class="material-icons">local_police</span> THE BOUNTY BOARD</h2>
            <p>GRAILS PEOPLE ARE ACTIVELY LOOKING TO BUY. GOT ONE? MESSAGE 'EM.</p>
        </div>
        <div class="bounty-grid">
            <div class="bounty-item">
                <h5>WANTED: 2004 WORLD INDUSTRIES FLAME BOY</h5>
                <p>REWARD: $300</p>
                <span>BUYER: @OldSchoolDave</span>
            </div>
            <div class="bounty-item">
                <h5>WANTED: SPITFIRE FORMULA FOUR 54MM (NEW)</h5>
                <p>REWARD: $40</p>
                <span>BUYER: @GromSkater</span>
            </div>
            <div class="bounty-item">
                <h5>WANTED: ZERO SKULL DECK (SIGNED BY JAMIE THOMAS)</h5>
                <p>REWARD: NAME YOUR PRICE</p>
                <span>BUYER: @ZeroOrDie</span>
            </div>
        </div>
    </div>
</section>

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
