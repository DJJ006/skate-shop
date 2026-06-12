<?php
session_start();
include '../db.php';

// 1. Pagination & Filter Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 8;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// 2. Build Query
$whereClause = "WHERE status='published'";
$params = [];
$types = "";

if ($search !== '') {
    $whereClause .= " AND (title LIKE ? OR short_description LIKE ? OR content LIKE ? OR author LIKE ?)";
    $like_search = "%$search%";
    $params = [$like_search, $like_search, $like_search, $like_search];
    $types = "ssss";
}

$orderClause = "ORDER BY COALESCE(published_at, created_at) DESC";
if ($sort === 'oldest') {
    $orderClause = "ORDER BY COALESCE(published_at, created_at) ASC";
} elseif ($sort === 'alpha') {
    $orderClause = "ORDER BY title ASC";
}

// 3. Count Total for Pagination
$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM magazine_posts $whereClause");
if ($types) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_res = $stmt_count->get_result();
$total_items = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

// 4. Fetch Paginated Posts
$stmt_posts = $conn->prepare("SELECT id, title, slug, short_description, cover_image, author, published_at, created_at FROM magazine_posts $whereClause $orderClause LIMIT ? OFFSET ?");
$posts_types = $types . "ii";
$posts_params = $params;
$posts_params[] = $limit;
$posts_params[] = $offset;

$stmt_posts->bind_param($posts_types, ...$posts_params);
$stmt_posts->execute();
$posts_result = $stmt_posts->get_result();
$posts = [];
while ($row = $posts_result->fetch_assoc()) $posts[] = $row;

// 5. Helper to keep params
function get_mag_url($params) {
    $current = $_GET;
    foreach($params as $k => $v) $current[$k] = $v;
    return "?" . http_build_query($current);
}

// Single article view
$article = null;
if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    $stmt = $conn->prepare("SELECT * FROM magazine_posts WHERE slug=? AND status='published' LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) $article = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SkateShop | The Mag<?php if ($article) echo ' — ' . htmlspecialchars($article['title']); ?></title>
<meta name="description" content="The Mag — skate culture, stories, gear reviews and editorial content from the SkateShop crew.">
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/shop.css">
<script src="../assets/script.js" defer></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
/* ===== THE MAG — PAGE STYLES ===== */
.mag-hero {
    text-align: center;
    padding: 4rem 1rem 2.5rem;
    border-bottom: 4px solid var(--charcoal);
    background-color: var(--textwhite);
    background-image: radial-gradient(var(--charcoal) 1px, transparent 1px);
    background-size: 20px 20px;
    position: relative;
    overflow: hidden;
}
.mag-hero h1 {
    font-size: 5rem;
    color: var(--charcoal);
    text-shadow: 4px 4px 0px var(--primary);
    margin-bottom: 0.5rem;
}

.mag-hero h1 span{
            font-size: 5rem;
            color: var(--charcoal);
            text-shadow: 4px 4px 0px var(--primary);
            margin-bottom: 0.5rem;
        }

.mag-hero-sub {
    font-family: 'Staatliches', sans-serif;
    font-size: 1.4rem;
    letter-spacing: 4px;
    color: var(--charcoal);
    opacity: 0.9;
}
.mag-issue-ribbon {
    display: inline-block;
    background: var(--primary);
    color: #fff;
    font-family: 'Staatliches', sans-serif;
    font-size: 1rem;
    letter-spacing: 3px;
    padding: 4px 20px;
    margin-top: 1rem;
    transform: skewX(-6deg);
}

/* ===== INTRO BLOCK (same as reels) ===== */
.mag-intro-block {
    background: #111;
    border: 4px solid var(--charcoal);
    padding: 3rem;
    margin-bottom: 3rem;
    position: relative;
    overflow: hidden;
    box-shadow: 12px 12px 0px var(--primary);
}
.mag-intro-block::before {
    content: "";
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    opacity: 0.15; pointer-events: none; z-index: 1;
}
.mag-intro-tag {
    position: absolute; top: 35px; right: -35px;
    background: var(--primary); color: white;
    padding: 5px 40px; font-family: 'Staatliches', sans-serif;
    transform: rotate(45deg); font-size: 1rem; letter-spacing: 2px;
    box-shadow: 0 4px 0 rgba(0,0,0,0.3); z-index: 2;
}
.mag-intro-title {
    font-family: 'Staatliches', sans-serif; font-size: 5rem;
    line-height: 0.85; letter-spacing: -3px; color: var(--textwhite);
    margin: 0; text-transform: uppercase; position: relative; z-index: 2;
}
.mag-intro-title span { color: var(--primary); font-style: italic; display: block; }
.mag-intro-desc {
    font-family: 'Inter', sans-serif; font-size: 1.1rem; line-height: 1.6;
    color: #aaa; max-width: 700px; border-left: 3px solid var(--primary);
    padding-left: 1.5rem; margin-top: 1rem; position: relative; z-index: 2;
}
.mag-intro-desc strong { color: var(--textwhite); text-transform: uppercase; letter-spacing: 1px; }

/* ===== FILTER / SEARCH BAR — light editorial style matching reels.php layout ===== */
.mag-filter-row {
    margin-bottom: 2.5rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
}
.mag-filter-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1.5rem;
    width: 100%;
}
.mag-search-section {
    flex: 1;
    min-width: 300px;
}
.mag-search-field {
    display: flex;
    align-items: center;
    border: 3px solid var(--charcoal);
    background: var(--textwhite);
    transition: border-color 0.2s, box-shadow 0.2s;
    box-shadow: 6px 6px 0px var(--charcoal);
}
.mag-search-field:focus-within {
    border-color: var(--primary);
    box-shadow: 6px 6px 0px var(--primary);
}
.mag-search-field input {
    flex: 1;
    padding: 1.1rem 1.4rem;
    background: transparent;
    border: none;
    color: var(--charcoal);
    font-family: 'Staatliches', sans-serif;
    font-size: 1.2rem;
    letter-spacing: 1px;
    outline: none;
}
.mag-search-field input::placeholder {
    color: #999;
    font-size: 1.1rem;
}
.mag-search-field .search-icon {
    padding: 0 1.2rem;
    color: var(--charcoal);
    display: flex;
    align-items: center;
    opacity: 0.5;
    pointer-events: none;
    font-size: 1.6rem;
}
.mag-search-field:focus-within .search-icon {
    color: var(--primary);
    opacity: 1;
}

/* Sorting section */
.mag-sort-section select {
    padding: 1.1rem 3rem 1.1rem 1.5rem;
    background: var(--textwhite);
    border: 3px solid var(--charcoal);
    font-family: 'Staatliches', sans-serif;
    font-size: 1.2rem;
    color: var(--charcoal);
    outline: none;
    cursor: pointer;
    transition: all 0.2s;
    letter-spacing: 1px;
    appearance: none;
    background-image: linear-gradient(45deg, transparent 50%, var(--charcoal) 50%), linear-gradient(135deg, var(--charcoal) 50%, transparent 50%);
    background-position: calc(100% - 20px) calc(50% - 2px), calc(100% - 14px) calc(50% - 2px);
    background-size: 6px 6px, 6px 6px;
    background-repeat: no-repeat;
    min-width: 220px;
    box-shadow: 6px 6px 0px var(--charcoal);
}
.mag-sort-section select:hover,
.mag-sort-section select:focus {
    border-color: var(--primary);
    box-shadow: 6px 6px 0px var(--primary);
    background-image: linear-gradient(45deg, transparent 50%, var(--primary) 50%), linear-gradient(135deg, var(--primary) 50%, transparent 50%);
}

.mag-result-count {
    font-family: 'Staatliches', sans-serif;
    font-size: 1.1rem;
    letter-spacing: 2px;
    color: #999;
    white-space: nowrap;
    text-transform: uppercase;
}

/* ===== MAGAZINE GRID ===== */
.mag-feed-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
    margin-bottom: 4rem;
}
.mag-card-item {
    background: var(--textwhite);
    border: 4px solid var(--charcoal);
    box-shadow: 6px 6px 0px var(--charcoal);
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    display: flex; flex-direction: column;
    text-decoration: none; color: inherit;
}
.mag-card-item:hover {
    transform: translate(-4px, -4px);
    box-shadow: 10px 10px 0px var(--primary);
    border-color: var(--primary);
}
.mag-card-img {
    width: 100%; height: 220px; overflow: hidden; position: relative; background: #111;
}
.mag-card-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
.mag-card-item:hover .mag-card-img img { transform: scale(1.05); }
.mag-card-img .mag-issue-tag {
    position: absolute; top: 0; left: 0;
    background: var(--primary); color: white;
    font-family: 'Staatliches', sans-serif; font-size: 0.9rem;
    letter-spacing: 2px; padding: 4px 12px;
}
.mag-no-img {
    width: 100%; height: 220px; background: #1a1a1a;
    display: flex; align-items: center; justify-content: center;
}
.mag-no-img span { font-size: 4rem; color: var(--primary); }
.mag-card-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
.mag-card-meta {
    font-family: 'Staatliches', sans-serif; font-size: 0.9rem;
    letter-spacing: 2px; color: var(--primary); margin-bottom: 0.5rem;
}
.mag-card-title {
    font-family: 'Staatliches', sans-serif; font-size: 1.8rem;
    line-height: 1.1; letter-spacing: 0.5px; color: var(--charcoal);
    margin-bottom: 0.8rem; text-transform: uppercase;
}
.mag-card-desc {
    font-family: 'Inter', sans-serif; font-size: 0.92rem;
    color: #555; line-height: 1.5; flex: 1; margin-bottom: 1.2rem;
}
.mag-read-more {
    font-family: 'Staatliches', sans-serif; font-size: 1.1rem;
    color: var(--charcoal); letter-spacing: 2px;
    display: inline-flex; align-items: center; gap: 0.3rem;
    text-decoration: none; transition: color 0.2s;
}
.mag-card-item:hover .mag-read-more { color: var(--primary); }
.mag-card-author {
    font-family: 'Staatliches', sans-serif; font-size: 0.85rem;
    color: #888; letter-spacing: 1px; margin-top: 0.4rem;
}

/* ===== EMPTY STATE ===== */
.mag-empty {
    grid-column: 1/-1; text-align: center;
    padding: 5rem 2rem; border: 4px dashed var(--charcoal);
    font-family: 'Staatliches', sans-serif; font-size: 2rem; color: #888;
}

/* ===== ARTICLE MODAL OVERLAY ===== */
.article-overlay {
    display: flex; position: fixed; z-index: 4000;
    left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.92); backdrop-filter: blur(8px);
    align-items: center; justify-content: center;
    opacity: 0; visibility: hidden; pointer-events: none;
    transition: all 0.3s ease; padding: 2rem 1rem;
}
.article-overlay.active { opacity: 1; visibility: visible; pointer-events: all; }
.article-shell {
    background: #0d0d0d; border: 4px solid var(--primary);
    width: 100%; max-width: 820px; position: relative;
    box-shadow: 12px 12px 0px rgba(255,45,90,0.3);
    transform: translateY(30px); transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex; flex-direction: column;
    max-height: 90vh;
}
.article-overlay.active .article-shell { transform: translateY(0); }
.article-close {
    position: absolute; top: 12px; right: 20px;
    font-size: 3rem; color: white; cursor: pointer;
    font-family: 'Staatliches', sans-serif; line-height: 1; z-index: 100;
    transition: color 0.2s, transform 0.2s;
    text-shadow: 2px 2px 0px var(--charcoal);
}
.article-close:hover { color: var(--primary); transform: scale(1.2); }

#articleContent {
    flex: 1;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--primary) #1a1a1a;
}
#articleContent::-webkit-scrollbar { width: 10px; }
#articleContent::-webkit-scrollbar-track { background: #1a1a1a; }
#articleContent::-webkit-scrollbar-thumb { 
    background: var(--primary); 
    border: 2px solid #1a1a1a;
}
#articleContent::-webkit-scrollbar-thumb:hover { background: white; }
.article-cover { width: 100%; max-height: 420px; object-fit: cover; display: block; }
.article-cover-placeholder {
    width: 100%; height: 300px; background: #1a1a1a;
    display: flex; align-items: center; justify-content: center;
}
.article-cover-placeholder span { font-size: 5rem; color: var(--primary); }
.article-body { padding: 2.5rem; }
.article-eyebrow {
    font-family: 'Staatliches', sans-serif; font-size: 0.9rem;
    letter-spacing: 3px; color: var(--primary); margin-bottom: 1rem;
    display: flex; gap: 1rem; flex-wrap: wrap;
}
.article-title {
    font-family: 'Staatliches', sans-serif; font-size: 3.5rem;
    line-height: 1; letter-spacing: -1px; color: var(--textwhite);
    margin-bottom: 1.5rem; text-transform: uppercase;
    padding-right: 3rem; /* Space for close button */
}
.article-divider {
    border: none; border-top: 3px solid var(--primary);
    margin-bottom: 2rem;
}
.article-content {
    font-family: 'Inter', sans-serif; font-size: 1.2rem;
    line-height: 1.8; color: #ccc;
}
.article-content p { margin-bottom: 1.2rem; }
.article-content h3 {
    font-family: 'Staatliches', sans-serif; font-size: 2rem;
    color: var(--textwhite); margin: 2rem 0 1rem;
    letter-spacing: 1px; border-left: 4px solid var(--primary);
    padding-left: 1rem;
}
.article-content strong { color: var(--textwhite); }
.article-back-btn {
    margin-top: 2rem; display: inline-flex; align-items: center; gap: 0.5rem;
    background: var(--primary); color: white; border: 3px solid var(--charcoal);
    padding: 0.8rem 2rem; font-family: 'Staatliches', sans-serif;
    font-size: 1.3rem; cursor: pointer; letter-spacing: 1px;
    transition: all 0.2s; box-shadow: 4px 4px 0px var(--charcoal);
}
.article-back-btn:hover { background: white; color: var(--charcoal); transform: translate(-2px,-2px); box-shadow: 6px 6px 0px var(--charcoal); }

/* ===== SECTION HEADER ===== */
.mag-section-hd {
    margin-bottom: 2rem;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
}
.mag-section-hd h2 {
    font-family: 'Staatliches', sans-serif; font-size: 3rem;
    color: var(--charcoal); letter-spacing: 1px;
}
.mag-section-hd h2 span { color: var(--primary); }
.mag-count-badge {
    font-family: 'Staatliches', sans-serif;
    font-size: 1.2rem;
    color: var(--charcoal);
    letter-spacing: 2px;
    margin: 0;
    padding-bottom: 10px;
    transform: skewX(-6deg);
    font-weight: 700;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .mag-hero h1 { font-size: 3.5rem; }
    .mag-intro-title { font-size: 3.5rem; letter-spacing: -1px; }
    .mag-intro-tag { display: none; }
    .article-title { font-size: 2.5rem; }
    .article-body { padding: 1.5rem; }
    .mag-feed-grid { grid-template-columns: 1fr; }
}
</style>
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>

<?php include 'header.php'; ?>

<!-- PAGE HERO -->
<div class="mag-hero noise-bg">
    <div class="container">
        <h1 class="glitch-text">THE <span class="text-primary">MAG</span></h1>
        <p class="mag-hero-sub">STORIES / CULTURE / GEAR / EDITORIAL</p>
    </div>
</div>

<main class="container" style="padding-top: 3rem; padding-bottom: 4rem;">

    <!-- INTRO BLOCK -->
    <div class="mag-intro-block">
        <div class="mag-intro-tag">ISSUE_12</div>
        <h2 class="mag-intro-title">THE DIGITAL<span>SKATE PRESS</span></h2>
        <div class="mag-intro-desc">
            <p><strong>Stories from the concrete.</strong> Deep dives into skate culture, gear reviews, spot guides, and raw editorial from the crew. This is what the community reads.</p>
        </div>
    </div>

     <!-- SECTION HEADER -->

    <div class="section-header">
        <h3>ALL <span class="header-span">ISSUES</span></h3>
        <span class="mag-count-badge"><?php echo $total_items; ?> ARTICLE<?php echo $total_items !== 1 ? 'S' : ''; ?> PUBLISHED</span>
    </div>

    <!-- FILTER / SEARCH ROW -->
    <div class="mag-filter-row">
        <form action="the-mag.php" method="GET" class="mag-filter-controls">
            <div class="mag-search-section">
                <div class="mag-search-field">
                    <input type="text" name="search" id="mag-search" placeholder="SEARCH ARTICLES..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>
            </div>
            <div class="mag-sort-section">
                <select name="sort" id="mag-sort" onchange="this.form.submit()">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>NEWEST FIRST</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>OLDEST FIRST</option>
                    <option value="alpha" <?php echo $sort === 'alpha' ? 'selected' : ''; ?>>A – Z (TITLE)</option>
                </select>
            </div>
        </form>
    </div>

   

    <!-- FEED GRID -->
    <div class="mag-feed-grid" id="mag-feed">
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $p):
                $display_date = date('M d, Y', strtotime($p['published_at'] ?? $p['created_at']));
            ?>
            <div class="mag-card-item"
                 data-id="<?php echo $p['id']; ?>"
                 onclick="openMagArticle(<?php echo $p['id']; ?>)">
                <div class="mag-card-img">
                    <?php if (!empty($p['cover_image'])): ?>
                        <img src="<?php echo htmlspecialchars($p['cover_image']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" loading="lazy">
                    <?php else: ?>
                        <div class="mag-no-img"><span class="material-icons">auto_stories</span></div>
                    <?php endif; ?>
                    <span class="mag-issue-tag">ISSUE_<?php echo str_pad($p['id'], 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="mag-card-body">
                    <div class="mag-card-meta"><?php echo $display_date; ?></div>
                    <h3 class="mag-card-title"><?php echo htmlspecialchars($p['title']); ?></h3>
                    <?php if (!empty($p['short_description'])): ?>
                        <p class="mag-card-desc"><?php echo htmlspecialchars(substr($p['short_description'], 0, 120)) . (strlen($p['short_description']) > 120 ? '...' : ''); ?></p>
                    <?php endif; ?>
                    <span class="mag-read-more">READ STORY <span class="material-icons" style="font-size:1.1rem;">arrow_forward</span></span>
                    <div class="mag-card-author">BY <?php echo htmlspecialchars(strtoupper($p['author'])); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php if ($search !== ''): ?>
                <div class="not-found">
                    <h3>NO ARTICLES FOUND.</h3>
                    <p>Try adjusting your search terms or filters.</p>
                </div>
            <?php else: ?>
                <div class="not-found">
                    <span class="material-icons" style="font-size:4rem; display:block; margin-bottom:1rem; color:var(--primary);">auto_stories</span>
                    <h3>THE ARCHIVE IS EMPTY.</h3>
                    <p>No articles have been published yet. Check back soon.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- PAGINATION -->
    <?php render_intelligent_pagination($page, $total_pages, 'pagination'); ?>

</main>

<?php include 'footer.php'; ?>

<!-- ARTICLE MODAL OVERLAY -->
<div class="article-overlay" id="articleOverlay">
    <div class="article-shell" id="articleShell">
        <span class="article-close" id="articleClose">&times;</span>
        <div id="articleContent"><!-- Loaded via AJAX --></div>
    </div>
</div>

<!-- ARTICLE DATA (PHP → JS) -->
<script>
const MAG_POSTS = <?php echo json_encode($posts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

// Pre-fetch all full articles via AJAX
const articleCache = {};

<?php if ($article): ?>
document.addEventListener('DOMContentLoaded', () => {
    openMagArticle(<?php echo $article['id']; ?>);
});
<?php endif; ?>

function openMagArticle(id) {
    const overlay = document.getElementById('articleOverlay');
    const content = document.getElementById('articleContent');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';

    content.innerHTML = '<div style="padding:4rem; text-align:center;"><span class="material-icons" style="font-size:4rem;color:var(--primary);animation:spin 1s linear infinite">refresh</span><p style="font-family:\'Staatliches\',sans-serif;font-size:1.5rem;color:#fff;margin-top:1rem;">LOADING ARTICLE...</p></div>';

    if (articleCache[id]) {
        renderArticle(articleCache[id]);
        return;
    }

    fetch('mag-api.php?action=get_article&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                articleCache[id] = data.article;
                renderArticle(data.article);
            } else {
                content.innerHTML = '<div style="padding:3rem;text-align:center;color:#ff4b2b;font-family:\'Staatliches\',sans-serif;font-size:2rem;">ARTICLE NOT FOUND</div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div style="padding:3rem;text-align:center;color:#ff4b2b;font-family:\'Staatliches\',sans-serif;font-size:2rem;">FAILED TO LOAD ARTICLE</div>';
        });
}

function renderArticle(a) {
    const content = document.getElementById('articleContent');
    const date = new Date(a.published_at || a.created_at);
    const dateStr = date.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'}).toUpperCase();

    const coverHtml = a.cover_image
        ? `<img class="article-cover" src="${escHtml(a.cover_image)}" alt="${escHtml(a.title)}">`
        : `<div class="article-cover-placeholder"><span class="material-icons">auto_stories</span></div>`;

    content.innerHTML = `
        ${coverHtml}
        <div class="article-body">
            <div class="article-eyebrow">
                <span>${dateStr}</span>
                <span>BY ${escHtml(a.author.toUpperCase())}</span>
            </div>
            <h1 class="article-title">${escHtml(a.title)}</h1>
            <hr class="article-divider">
            <div class="article-content">${a.content}</div>
            <button class="article-back-btn" onclick="closeMagArticle()">
                <span class="material-icons" style="font-size:1.2rem;">arrow_back</span> BACK TO MAG
            </button>
        </div>`;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

function closeMagArticle() {
    document.getElementById('articleOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('articleClose').addEventListener('click', closeMagArticle);
document.getElementById('articleOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeMagArticle();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMagArticle(); });

// Live Search Debounce
const magSearch = document.getElementById('mag-search');
if (magSearch) {
    let debounceTimer;
    magSearch.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            magSearch.form.submit();
        }, 600);
    });

    // Restore focus and cursor position after reload if searching
    if (new URLSearchParams(window.location.search).has('search')) {
        magSearch.focus();
        const val = magSearch.value;
        magSearch.value = '';
        magSearch.value = val;
    }
}

// Spin animation for loader
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);
</script>

</body>
</html>

