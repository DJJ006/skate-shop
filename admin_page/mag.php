<?php
require_once 'admin_auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php';



// ── HELPERS ──────────────────────────────────────────────────────────────────
function make_slug($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

function unique_slug($conn, $base, $exclude_id = 0) {
    $slug = $base; $i = 1;
    while (true) {
        $s = $conn->real_escape_string($slug);
        $res = $conn->query("SELECT id FROM magazine_posts WHERE slug='$s' AND id!=$exclude_id LIMIT 1");
        if ($res->num_rows === 0) return $slug;
        $slug = $base . '-' . $i++;
    }
}

// ── HANDLE IMAGE UPLOAD ───────────────────────────────────────────────────────
function handle_cover_upload() {
    if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === 0) {
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if (!in_array($_FILES['cover_file']['type'], $allowed)) return ['error' => 'Invalid file type.'];
        $ext = pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION);
        $fname = 'mag_' . uniqid() . '.' . $ext;
        $dest = '../assets/uploads/' . $fname;
        if (move_uploaded_file($_FILES['cover_file']['tmp_name'], $dest)) {
            return ['url' => '../assets/uploads/' . $fname];
        }
        return ['error' => 'Upload failed.'];
    }
    return null;
}

// ── POST HANDLERS ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CREATE
    if (isset($_POST['create_post'])) {
        $title  = trim($_POST['title']);
        $short  = trim($_POST['short_description']);
        $content = trim($_POST['content']);
        $author = trim($_POST['author']) ?: 'Admin';
        $status = $_POST['status'] === 'published' ? 'published' : 'draft';
        $pub_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;

        // Cover image
        $cover = trim($_POST['cover_image_url']);
        $upload = handle_cover_upload();
        if ($upload && isset($upload['url'])) $cover = $upload['url'];
        if ($upload && isset($upload['error'])) {
            $_SESSION['msg'] = $upload['error']; $_SESSION['msg_type'] = 'error';
            header("Location: mag.php"); exit();
        }

        if (empty($title)) {
            $_SESSION['msg'] = 'TITLE IS REQUIRED.'; $_SESSION['msg_type'] = 'error';
            header("Location: mag.php"); exit();
        }

        if (mb_strlen($title) > 255 || mb_strlen($short) > 500 || mb_strlen($content) > 10000 || mb_strlen($author) > 50 || mb_strlen($cover) > 500) {
            $_SESSION['msg'] = 'INPUT TOO LONG. PLEASE CHECK CHARACTER LIMITS.'; $_SESSION['msg_type'] = 'error';
            header("Location: mag.php"); exit();
        }

        $slug = unique_slug($conn, make_slug($title));
        $stmt = $conn->prepare("INSERT INTO magazine_posts (title, slug, short_description, content, cover_image, author, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $title, $slug, $short, $content, $cover, $author, $status, $pub_at);
        $stmt->execute();
        $_SESSION['msg'] = 'ARTICLE CREATED SUCCESSFULLY.'; $_SESSION['msg_type'] = 'success';
        header("Location: mag.php"); exit();
    }

    // UPDATE
    if (isset($_POST['update_post'])) {
        $id     = (int)$_POST['post_id'];
        $title  = trim($_POST['title']);
        $short  = trim($_POST['short_description']);
        $content = trim($_POST['content']);
        $author = trim($_POST['author']) ?: 'Admin';
        $status = $_POST['status'] === 'published' ? 'published' : 'draft';

        $cover = trim($_POST['cover_image_url']);
        $upload = handle_cover_upload();
        if ($upload && isset($upload['url'])) $cover = $upload['url'];
        if ($upload && isset($upload['error'])) {
            $_SESSION['msg'] = $upload['error']; $_SESSION['msg_type'] = 'error';
            header("Location: mag.php"); exit();
        }

        if (mb_strlen($title) > 255 || mb_strlen($short) > 500 || mb_strlen($content) > 10000 || mb_strlen($author) > 50 || mb_strlen($cover) > 500) {
            $_SESSION['msg'] = 'INPUT TOO LONG. PLEASE CHECK CHARACTER LIMITS.'; $_SESSION['msg_type'] = 'error';
            header("Location: mag.php"); exit();
        }

        // Check if publishing for first time
        $existing = $conn->query("SELECT status, published_at FROM magazine_posts WHERE id=$id LIMIT 1")->fetch_assoc();
        $pub_at = $existing['published_at'];
        if ($status === 'published' && empty($pub_at)) $pub_at = date('Y-m-d H:i:s');

        $slug = unique_slug($conn, make_slug($title), $id);
        $stmt = $conn->prepare("UPDATE magazine_posts SET title=?, slug=?, short_description=?, content=?, cover_image=?, author=?, status=?, published_at=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $title, $slug, $short, $content, $cover, $author, $status, $pub_at, $id);
        $stmt->execute();
        $_SESSION['msg'] = 'ARTICLE UPDATED.'; $_SESSION['msg_type'] = 'success';
        header("Location: mag.php"); exit();
    }

    // DELETE
    if (isset($_POST['delete_post'])) {
        $id = (int)$_POST['post_id'];
        $conn->query("DELETE FROM magazine_posts WHERE id=$id");
        $_SESSION['msg'] = 'ARTICLE DELETED.'; $_SESSION['msg_type'] = 'success';
        header("Location: mag.php"); exit();
    }
}

// ── FETCH POSTS WITH FILTERS ──────────────────────────────────────────────────
// ── FETCH POSTS WITH FILTERS ──────────────────────────────────────────────────
$search_query = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : "";
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : "ALL";
$sort = isset($_GET['sort']) ? $_GET['sort'] : "newest";

$where = ["1=1"];
$types = "";
$params = [];

if ($status_filter === 'published') {
    $where[] = "status = 'published'";
} elseif ($status_filter === 'draft') {
    $where[] = "status = 'draft'";
}

if ($search_query !== "") {
    $where[] = "(id = ? OR title LIKE ? OR author LIKE ?)";
    $s_id = (int)$search_query;
    $like = "%$search_query%";
    $params[] = $s_id;
    $params[] = $like;
    $params[] = $like;
    $types .= "iss";
}

$where_sql = implode(" AND ", $where);

// Sorting logic
$order_sql = "ORDER BY created_at DESC";
if ($sort === 'oldest') {
    $order_sql = "ORDER BY created_at ASC";
}

// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_res = null;
$count_sql = "SELECT COUNT(*) as total FROM magazine_posts WHERE $where_sql";
$stmt_count = $conn->prepare($count_sql);
if ($stmt_count) {
    if ($types) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $count_res = $stmt_count->get_result();
}

$total_records = 0;
$total_pages = 0;
if ($count_res) {
    $total_records = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);
}

$list_sql = "SELECT * FROM magazine_posts WHERE $where_sql $order_sql LIMIT ? OFFSET ?";
$stmt_data = $conn->prepare($list_sql);
$types_data = $types . "ii";
$params_data = $params;
$params_data[] = $limit;
$params_data[] = $offset;
$stmt_data->bind_param($types_data, ...$params_data);
$stmt_data->execute();
$posts_res = $stmt_data->get_result();

$posts = [];
if ($posts_res) {
    while ($r = $posts_res->fetch_assoc()) $posts[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SkateShop | MAG MANAGEMENT</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/shop.css">
<link rel="stylesheet" href="../assets/admin.css">
<script src="../assets/admin-script.js" defer></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
/* ── STATUS BADGE ── */
.status-badge {
    display: inline-block; padding: 3px 10px;
    font-family: 'Staatliches', sans-serif; font-size: 0.9rem; letter-spacing: 1px;
    border: 2px solid;
}
.status-badge.published { background: #4ADE80; color: #000; border-color: #000; }
.status-badge.draft { background: #f0f0f0; color: #555; border-color: #999; }

/* ── COVER THUMB IN TABLE ── */
.cover-thumb { width: 70px; height: 50px; object-fit: cover; border: 2px solid var(--charcoal); }
.cover-thumb-placeholder {
    width: 70px; height: 50px; background: #eee;
    display: flex; align-items: center; justify-content: center; border: 2px solid #ccc;
}

/* ── COVER PREVIEW IN MODAL ── */
.cover-preview-wrap { margin-top: 0.5rem; }
.cover-preview-wrap img {
    max-width: 100%; max-height: 160px; object-fit: cover;
    border: 3px solid var(--charcoal); display: block; margin-top: 0.5rem;
}

/* ── CONTENT TEXTAREA — inherits .admin-form textarea but needs min-height ── */
.admin-form textarea.content-area { min-height: 220px; resize: vertical; }

/* ── VIEW BUTTON HOVER ── */
.btn-mini.btn-view {
    background: var(--charcoal); color: #fff;
    border: 2px solid var(--charcoal);
    font-family: 'Staatliches', sans-serif; font-size: 0.85rem;
    padding: 6px 12px; cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    letter-spacing: 0.5px;
}
.btn-mini.btn-view:hover {
    background: var(--primary); color: #fff;
    border-color: var(--primary);
}
.btn-mini.btn-view.btn-preview {
    background: #555; border-color: #555;
}
.btn-mini.btn-view.btn-preview:hover {
    background: #333; border-color: #333;
}

/* ── ACTIONS COLUMN ── */
.recent-activity-table td:last-child {
    text-align: right; white-space: nowrap; vertical-align: middle;
}
.recent-activity-table td:last-child .btn-mini,
.recent-activity-table td:last-child form { display: inline-block; vertical-align: middle; margin-left: 4px; }
@media (max-width: 1200px) {
    .recent-activity-table td:last-child { white-space: normal; }
    .recent-activity-table td:last-child .btn-mini,
    .recent-activity-table td:last-child form { display: block !important; width: 100%; margin: 3px 0; }
}
</style>
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>

<?php require __DIR__ . '/admin_header.php'; ?>

<section class="admin-layout container">
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">THE MAG <span class="text-primary">MGMT</span></h2>
                <p class="admin-text-shop">CREATE, EDIT & PUBLISH MAGAZINE ARTICLES</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('createModal')">+ NEW ARTICLE</button>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="admin-alert alert-<?php echo $_SESSION['msg_type']; ?>">
                <?php echo $_SESSION['msg']; unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
            </div>
        <?php endif; ?>

        <!-- ARTICLES TABLE -->
        <div class="grainy-card" style="padding:20px;">
            <h3 class="admin-table-h3">ALL <span class="header-span">ARTICLES</span></h3>

            <!-- FILTER BAR -->
            <div class="grainy-card filter-bar" style="margin-bottom: 25px; padding: 20px; border: 3px solid var(--charcoal);">
                <form method="GET" action="mag.php" class="search-filter-form">
                    <div class="filter-group search-box" style="flex: 2;">
                        <label>SEARCH ARTICLES</label>
                        <input type="text" name="search" placeholder="ID, Title, or Author..." value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="filter-group" style="flex: 1;">
                        <label>STATUS</label>
                        <select name="status_filter" onchange="this.form.submit()">
                            <option value="ALL" <?php echo ($status_filter === 'ALL') ? 'selected' : ''; ?>>ALL STATUSES</option>
                            <option value="published" <?php echo ($status_filter === 'published') ? 'selected' : ''; ?>>PUBLISHED</option>
                            <option value="draft" <?php echo ($status_filter === 'draft') ? 'selected' : ''; ?>>DRAFTED</option>
                        </select>
                    </div>

                    <div class="filter-group" style="flex: 1;">
                        <label>SORT BY</label>
                        <select name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>NEWEST FIRST</option>
                            <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>OLDEST FIRST</option>
                        </select>
                    </div>

                    <div class="filter-actions" style="margin-top:22px;">
                        <button type="submit" class="btn-filter">SEARCH</button>
                        <?php if (!empty($search_query) || $status_filter !== 'ALL' || $sort !== 'newest'): ?>
                            <a href="mag.php" class="btn-reset">RESET</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="table-responsive"><table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>COVER</th>
                        <th>TITLE</th>
                        <th>AUTHOR</th>
                        <th>STATUS</th>
                        <th>DATE</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($posts) > 0): ?>
                    <?php foreach ($posts as $p):
                        $date_display = date('M j, Y', strtotime($p['published_at'] ?? $p['created_at']));
                    ?>
                    <tr>
                        <td class="td-id">#<?php echo $p['id']; ?></td>
                        <td>
                            <?php if (!empty($p['cover_image'])): ?>
                                <img src="<?php echo htmlspecialchars($p['cover_image']); ?>" class="cover-thumb" alt="">
                            <?php else: ?>
                                <div class="cover-thumb-placeholder"><span class="material-icons" style="color:#ccc;">image</span></div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['author']); ?></td>
                        <td><span class="status-badge <?php echo $p['status']; ?>"><?php echo strtoupper($p['status']); ?></span></td>
                        <td><?php echo $date_display; ?></td>
                        <td>
                            <button class="btn-mini btn-view" onclick="openMagEditModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)">EDIT</button>
                            <button class="btn-mini btn-view btn-preview" onclick="openMagPreviewModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)">VIEW</button>
                            <button type="button" class="btn-mini btn-danger" onclick="event.stopPropagation(); openConfirmDeleteMagModal(<?php echo $p['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;font-weight:700;font-style:italic;">NO ARTICLES YET. CREATE THE FIRST ONE!</td></tr>
                <?php endif; ?>
                </tbody>
            </table></div>

            <?php render_intelligent_pagination($page, $total_pages, 'admin-pagination'); ?>

        </div>
    </main>
</section>

<!-- ══ CREATE MODAL ══════════════════════════════════════════════════════════ -->
<div id="createModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('createModal')">&times;</span>
        <h3 class="admin-table-h3">ADD <span class="header-span">NEW</span> ARTICLE</h3>
        <form method="POST" enctype="multipart/form-data" class="admin-form">

            <label>ARTICLE TITLE</label>
            <input type="text" name="title" placeholder="E.G. TOP 5 SPOTS IN THE CITY" required maxlength="255">

            <label>SHORT DESCRIPTION</label>
            <textarea name="short_description" rows="3" placeholder="Brief preview shown on the magazine feed..." maxlength="500"></textarea>

            <label>COVER IMAGE URL</label>
            <input type="url" name="cover_image_url" id="create-cover-url" placeholder="https://... (paste image URL)" maxlength="500">
            <div class="cover-preview-wrap"><img id="create-cover-preview" src="" alt="" style="display:none;"></div>

            <label>— OR UPLOAD IMAGE FILE —</label>
            <input type="file" name="cover_file" accept="image/*">

            <label>FULL ARTICLE CONTENT</label>
            <p style="font-family:'Inter',sans-serif;font-size:0.8rem;color:#888;margin-bottom:0.5rem;margin-top:-0.6rem;">Supports HTML: &lt;p&gt; &lt;h3&gt; &lt;strong&gt; &lt;em&gt; &lt;ul&gt; &lt;li&gt;</p>
            <textarea name="content" class="content-area" rows="10" placeholder="Write the full article here..." required maxlength="10000"></textarea>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div>
                    <label>AUTHOR</label>
                    <input type="text" name="author" placeholder="Admin" value="Admin" maxlength="50">
                </div>
                <div>
                    <label>PUBLISH STATUS</label>
                    <select name="status">
                        <option value="draft">DRAFT</option>
                        <option value="published">PUBLISHED</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="create_post" class="btn btn-primary" style="width:100%;margin-top:1rem;font-size:1.4rem;">CREATE ARTICLE</button>
        </form>
    </div>
</div>

<!-- ══ EDIT MODAL ════════════════════════════════════════════════════════════ -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
        <h3 class="admin-table-h3">EDIT <span class="header-span">ARTICLE</span></h3>
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <input type="hidden" name="post_id" id="edit-id">

            <label>ARTICLE TITLE</label>
            <input type="text" name="title" id="edit-title" required maxlength="255">

            <label>SHORT DESCRIPTION</label>
            <textarea name="short_description" id="edit-short" rows="3" maxlength="500"></textarea>

            <label>COVER IMAGE URL</label>
            <input type="url" name="cover_image_url" id="edit-cover-url" placeholder="https://..." maxlength="500">
            <div class="cover-preview-wrap"><img id="edit-cover-preview" src="" alt="" style="display:none;"></div>

            <label>— OR UPLOAD NEW IMAGE —</label>
            <input type="file" name="cover_file" accept="image/*">

            <label>FULL ARTICLE CONTENT</label>
            <p style="font-family:'Inter',sans-serif;font-size:0.8rem;color:#888;margin-bottom:0.5rem;margin-top:-0.6rem;">Supports HTML: &lt;p&gt; &lt;h3&gt; &lt;strong&gt; &lt;em&gt; &lt;ul&gt; &lt;li&gt;</p>
            <textarea name="content" id="edit-content" class="content-area" rows="10" required maxlength="10000"></textarea>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div>
                    <label>AUTHOR</label>
                    <input type="text" name="author" id="edit-author" maxlength="50">
                </div>
                <div>
                    <label>PUBLISH STATUS</label>
                    <select name="status" id="edit-status">
                        <option value="draft">DRAFT</option>
                        <option value="published">PUBLISHED</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-top:20px;">
                <button type="submit" name="update_post" class="btn btn-primary">SAVE CHANGES</button>
                <button type="button" class="btn btn-danger" onclick="openConfirmDeleteMagModal(document.getElementById('edit-id').value)">
                    <span class="material-icons" style="vertical-align:middle;">delete</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ PREVIEW MODAL ═════════════════════════════════════════════════════════ -->
<div id="previewModal" class="modal-overlay">
    <div class="modal-content modal-content-lg" style="background:#0d0d0d;border:4px solid var(--primary);">
        <span class="close-modal" onclick="closeModal('previewModal')" style="color:#fff;">&times;</span>
        <div id="previewContent" style="color:#fff;"></div>
    </div>
</div>

<script>
// Wait for admin-script.js to be ready, then bind cover previews
document.addEventListener('DOMContentLoaded', function() {
    bindCoverPreview('create-cover-url', 'create-cover-preview');
    bindCoverPreview('edit-cover-url', 'edit-cover-preview');
});

function bindCoverPreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const img   = document.getElementById(previewId);
    if (!input || !img) return;
    input.addEventListener('input', function() {
        if (this.value) { img.src = this.value; img.style.display = 'block'; }
        else { img.src = ''; img.style.display = 'none'; }
    });
}

// Renamed to avoid collision with admin-script.js openEditModal
function openMagEditModal(post) {
    document.getElementById('edit-id').value       = post.id;
    document.getElementById('edit-title').value    = post.title;
    document.getElementById('edit-short').value    = post.short_description || '';
    document.getElementById('edit-content').value  = post.content || '';
    document.getElementById('edit-author').value   = post.author || 'Admin';
    document.getElementById('edit-status').value   = post.status;
    const urlInput = document.getElementById('edit-cover-url');
    const prev     = document.getElementById('edit-cover-preview');
    urlInput.value = post.cover_image || '';
    if (post.cover_image) { prev.src = post.cover_image; prev.style.display = 'block'; }
    else { prev.src = ''; prev.style.display = 'none'; }
    openModal('editModal');
}

// Renamed to avoid collision
function openMagPreviewModal(post) {
    const rawDate = post.published_at || post.created_at;
    const date    = rawDate ? new Date(rawDate.replace(' ', 'T')) : new Date();
    const dateStr = date.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'}).toUpperCase();
    const coverHtml = post.cover_image
        ? `<img src="${escMagHtml(post.cover_image)}" style="width:100%;max-height:300px;object-fit:cover;display:block;border-bottom:3px solid var(--primary);">`
        : '<div style="height:60px;"></div>';
    document.getElementById('previewContent').innerHTML = `
        ${coverHtml}
        <div style="padding:2rem;">
            <div style="font-family:'Staatliches',sans-serif;font-size:0.9rem;letter-spacing:3px;color:var(--primary);margin-bottom:0.8rem;">${dateStr} &mdash; BY ${escMagHtml(post.author.toUpperCase())}</div>
            <h2 style="font-family:'Staatliches',sans-serif;font-size:2.5rem;color:#fff;margin-bottom:1rem;text-transform:uppercase;line-height:1.1;">${escMagHtml(post.title)}</h2>
            <hr style="border:none;border-top:3px solid var(--primary);margin-bottom:1.5rem;">
            <div style="font-family:'Inter',sans-serif;font-size:1rem;line-height:1.8;color:#ccc;">${post.content || '<em style="color:#666;">No content yet.</em>'}</div>
        </div>`;
    openModal('previewModal');
}

function escMagHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}
</script>

<!-- ===== CONFIRM DELETE MODAL ===== -->
<div id="confirmDeleteMagModal" class="modal-overlay" style="z-index: 9999;">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeModal('confirmDeleteMagModal')">&times;</span>
        <h3 class="admin-table-h3" style="border:none; margin-bottom:10px;">TRASH <span class="text-primary">ARTICLE?</span></h3>
        <p style="font-family:'Inter',sans-serif; font-size:1rem; margin-bottom:20px;">Are you sure you want to permanently delete this article?</p>
        <form method="POST" action="mag.php">
            <input type="hidden" name="post_id" id="deleteMagIdInput" value="">
            <input type="hidden" name="delete_post" value="1">
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-danger" style="flex: 1; font-size: 1rem;">YES</button>
                <button type="button" class="btn btn-outline" style="flex: 1; font-size: 1rem;" onclick="closeModal('confirmDeleteMagModal')">CANCEL</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openConfirmDeleteMagModal(postId) {
        document.getElementById('deleteMagIdInput').value = postId;
        document.getElementById('confirmDeleteMagModal').classList.add('active');
    }
</script>

</body>
</html>


