<?php
require_once 'admin_auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php';



// Handle delete reel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_reel'])) {
    $id = (int)$_POST['reel_id'];
    $reason = trim($_POST['deletion_reason'] ?? '');

    if ($id <= 0 || $reason === '') {
        $_SESSION['msg'] = "DELETION REASON IS REQUIRED.";
        $_SESSION['msg_type'] = "error";
    } elseif (mb_strlen($reason) > 500) {
        $_SESSION['msg'] = "DELETION REASON CANNOT EXCEED 500 CHARACTERS.";
        $_SESSION['msg_type'] = "error";
    } else {
        // Fetch info for notification before delete
        $info_stmt = $conn->prepare("SELECT user_id, title FROM reels WHERE id = ?");
        $info_stmt->bind_param("i", $id);
        $info_stmt->execute();
        $reel = $info_stmt->get_result()->fetch_assoc();

        if ($reel) {
            $u_id = $reel['user_id'];
            $title = $reel['title'];
            
            $conn->query("DELETE FROM reel_likes WHERE reel_id = $id");
            $conn->query("DELETE FROM reel_comments WHERE reel_id = $id");
            $conn->query("DELETE FROM reel_edit_requests WHERE reel_id = $id");
            $conn->query("DELETE FROM reels WHERE id = $id");
            
            // Send notification
            $msg = "Your reel '" . $title . "' was removed by an admin. Reason: " . $reason;
            sendAppNotification($conn, $u_id, $msg);

            $_SESSION['msg'] = "REEL DELETED PERMANENTLY AND USER NOTIFIED.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "REEL NOT FOUND.";
            $_SESSION['msg_type'] = "error";
        }
    }
    header("Location: reels.php");
    exit();
}

// Handle delete comment (admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_delete_comment'])) {
    $comment_id = (int)$_POST['comment_id'];
    $reel_id = (int)$_POST['return_reel_id'];
    $conn->query("DELETE FROM reel_comments WHERE id = $comment_id");
    $_SESSION['msg'] = "COMMENT DELETED.";
    $_SESSION['msg_type'] = "success";
    header("Location: reels.php");
    exit();
}

// Handle search
$search_query = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : "";
$sort_filter = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$where = ["r.is_approved = 1"];
$types = "";
$params = [];

if ($search_query !== "") {
    $where[] = "(r.id = ? OR r.title LIKE ? OR u.username LIKE ?)";
    $s_id = (int)$search_query;
    $like = "%$search_query%";
    $params[] = $s_id;
    $params[] = $like;
    $params[] = $like;
    $types .= "iss";
}

$where_sql = implode(" AND ", $where);

// Sorting logic
$order_sql = "r.created_at DESC";
if ($sort_filter === 'oldest') {
    $order_sql = "r.created_at ASC";
} elseif ($sort_filter === 'likes') {
    $order_sql = "like_count DESC, r.created_at DESC";
} elseif ($sort_filter === 'comments') {
    $order_sql = "comment_count DESC, r.created_at DESC";
}

// Fetch reels with counts
// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_res = null;
$count_sql = "SELECT COUNT(*) as total FROM reels r JOIN users u ON r.user_id = u.id WHERE $where_sql";
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

$reels_sql = "SELECT r.*, u.username,
    (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.id) as like_count,
    (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.id) as comment_count
    FROM reels r 
    JOIN users u ON r.user_id = u.id
    WHERE $where_sql
    ORDER BY $order_sql LIMIT ? OFFSET ?";

$stmt_data = $conn->prepare($reels_sql);
$types_data = $types . "ii";
$params_data = $params;
$params_data[] = $limit;
$params_data[] = $offset;
$stmt_data->bind_param($types_data, ...$params_data);
$stmt_data->execute();
$reels_result = $stmt_data->get_result();

function reel_status_badge(int $is_approved): string {
    if ($is_approved == 0) return '<span class="listing-status-badge status-received">PENDING</span>';
    if ($is_approved == 1) return '<span class="listing-status-badge status-active">PUBLISHED</span>';
    return '<span class="listing-status-badge status-cancelled">REJECTED</span>';
}

$modals_html = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | REELS MANAGEMENT</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <script src="../assets/admin-script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
    .video-preview { width: 100%; aspect-ratio: 16/9; background: #000; margin-bottom: 1rem; border: 4px solid var(--charcoal); }
    .video-preview iframe { width: 100%; height: 100%; border: none; }
    .admin-comment-item { padding: 0.8rem; border: 2px solid #ddd; margin-bottom: 0.5rem; background: #fafafa; display: flex; justify-content: space-between; align-items: flex-start; }
    .admin-comment-item .comment-info { flex: 1; }
    .admin-comment-item .comment-info strong { font-family: 'Staatliches', sans-serif; color: var(--primary); }
    .admin-comment-item .comment-info p { margin: 0.3rem 0 0; font-family: 'Inter', sans-serif; font-size: 0.9rem; word-break: break-word; overflow-wrap: break-word; }
    .admin-comment-item .comment-info small { color: #999; font-size: 0.75rem; }
    .btn-delete-comment { background: var(--primary); color: white; border: 2px solid var(--charcoal); padding: 4px 10px; font-family: 'Staatliches', sans-serif; font-size: 0.85rem; cursor: pointer; transition: 0.2s; }
    .btn-delete-comment:hover { background: var(--charcoal); }
    .likes-comments-badge { display: inline-flex; align-items: center; gap: 4px; font-family: 'Staatliches', sans-serif; font-size: 1rem; }

    /* --- SCROLLABLE COMMENTS CONTAINER --- */
    .admin-modal-comments-container {
        max-height: 250px;
        overflow-y: auto;
        padding-right: 10px;
        margin-bottom: 1rem;
    }
    .admin-modal-comments-container::-webkit-scrollbar { width: 10px; }
    .admin-modal-comments-container::-webkit-scrollbar-track { background: #e0e0e0; border: 2px solid var(--charcoal); }
    .admin-modal-comments-container::-webkit-scrollbar-thumb { background: var(--charcoal); border: 1px solid #000; }
    .admin-modal-comments-container::-webkit-scrollbar-thumb:hover { background: var(--primary); }

    /* --- FIXED ACTIONS COLUMN --- */
    .recent-activity-table td:last-child { 
        text-align: right; 
        white-space: nowrap; 
        vertical-align: middle;
        background-color: #fff; /* Forces the cell to be solid white */
    }

    /* Keep buttons aligned without breaking the table cell */
    .recent-activity-table td:last-child .btn-mini,
    .recent-activity-table td:last-child form {
        display: inline-block;
        vertical-align: middle;
        margin-left: 5px;
    }

    @media (max-width: 1200px) {
        .recent-activity-table td:last-child { 
            white-space: normal; 
        }
        .recent-activity-table td:last-child .btn-mini, 
        .recent-activity-table td:last-child form { 
            display: block !important; 
            width: 100%; 
            margin: 4px 0; 
        }
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
                <h2 class="glitch-text-admin">REELS <span class="text-primary">MGMT</span></h2>
                <p class="admin-text-shop">MANAGE ALL PUBLISHED COMMUNITY REELS</p>
            </div>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="admin-alert alert-<?php echo $_SESSION['msg_type']; ?>">
                <?php echo $_SESSION['msg']; unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
            </div>
        <?php endif; ?>

        <div class="grainy-card" style="padding: 20px;">
            <h3 class="admin-table-h3">PUBLISHED <span class="header-span">REELS</span></h3>

            <!-- FILTER BAR -->
            <div class="grainy-card filter-bar" style="margin-bottom: 25px; padding: 20px; border: 3px solid var(--charcoal);">
                <form method="GET" action="reels.php" class="search-filter-form">
                    <div class="filter-group search-box" style="flex: 2;">
                        <label>SEARCH REELS</label>
                        <input type="text" name="search" placeholder="ID, Title, or Username..." value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="filter-group" style="flex: 1;">
                        <label>SORT BY</label>
                        <select name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo $sort_filter === 'newest' ? 'selected' : ''; ?>>NEWEST FIRST</option>
                            <option value="oldest" <?php echo $sort_filter === 'oldest' ? 'selected' : ''; ?>>OLDEST FIRST</option>
                            <option value="likes" <?php echo $sort_filter === 'likes' ? 'selected' : ''; ?>>MOST LIKED</option>
                            <option value="comments" <?php echo $sort_filter === 'comments' ? 'selected' : ''; ?>>MOST COMMENTS</option>
                        </select>
                    </div>

                    <div class="filter-actions" style="margin-top:22px;">
                        <button type="submit" class="btn-filter">SEARCH</button>
                        <?php if ($search_query !== '' || $sort_filter !== 'newest'): ?>
                            <a href="reels.php" class="btn-reset">RESET</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="table-responsive"><table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>TITLE</th>
                        <th>UPLOADER</th>
                        <th>DATE</th>
                        <th><i class="fas fa-heart"></i></th>
                        <th><i class="fas fa-comment"></i></th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reels_result && $reels_result->num_rows > 0): ?>
                        <?php while($item = $reels_result->fetch_assoc()):
                            $post_date = date("M j, Y", strtotime($item['created_at']));
                            // Fetch comments for this reel's modal
                            $comments_q = $conn->query("SELECT id, username, comment, created_at FROM reel_comments WHERE reel_id = " . $item['id'] . " ORDER BY created_at ASC");
                        ?>
                            <tr>
                                <td class="td-id">#<?php echo $item['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                <td>@<?php echo htmlspecialchars($item['username']); ?></td>
                                <td><?php echo $post_date; ?></td>
                                <td><span class="likes-comments-badge"><i class="fas fa-heart" style="color:var(--primary)"></i> <?php echo $item['like_count']; ?></span></td>
                                <td><span class="likes-comments-badge"><i class="fas fa-comment" style="color:var(--charcoal)"></i> <?php echo $item['comment_count']; ?></span></td>
                                <td>
                                    <button class="btn-mini btn-view" onclick="openModal('reel-<?php echo $item['id']; ?>')">VIEW</button>
                                    <button type="button" class="btn-mini btn-danger" style="padding:6px 10px; font-size:0.85rem;" onclick="openModal('reel-delete-<?php echo $item['id']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>

                            <?php ob_start(); ?>
                            <div id="reel-<?php echo $item['id']; ?>" class="modal-overlay">
                                <div class="modal-content modal-content-lg">
                                    <span class="close-modal" onclick="closeModal('reel-<?php echo $item['id']; ?>')">&times;</span>
                                    <h3 class="admin-table-h3 top-title">REEL <span class="header-span">DETAILS</span></h3>
                                    
                                    <div class="video-preview">
                                        <iframe src="<?php echo htmlspecialchars($item['embed_url']); ?>" allowfullscreen></iframe>
                                    </div>

                                    <div class="modal-details" style="margin-bottom:1rem;">
                                        <div class="detail-row"><span class="detail-label">TITLE:</span> <span><?php echo htmlspecialchars($item['title']); ?></span></div>
                                        <div class="detail-row"><span class="detail-label">UPLOADER:</span> <span>@<?php echo htmlspecialchars($item['username']); ?></span></div>
                                        <div class="detail-row"><span class="detail-label">PLATFORM:</span> <span><?php echo htmlspecialchars($item['platform']); ?></span></div>
                                        <div class="detail-row"><span class="detail-label">DATE:</span> <span><?php echo $post_date; ?></span></div>
                                        <div class="detail-row"><span class="detail-label">LIKES:</span> <span><?php echo $item['like_count']; ?></span></div>
                                    </div>

                                    <?php if (!empty($item['description'])): ?>
                                        <div class="modal-description-box">
                                            <strong>Description:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <h4 style="font-family:'Staatliches',sans-serif; font-size:1.5rem; margin: 1.5rem 0 0.8rem; border-bottom:2px solid #ddd; padding-bottom:0.5rem;">COMMENTS (<?php echo $item['comment_count']; ?>)</h4>
                                    <div class="admin-modal-comments-container">
                                        <?php if ($comments_q && $comments_q->num_rows > 0): ?>
                                            <?php while($c = $comments_q->fetch_assoc()): ?>
                                                <div class="admin-comment-item">
                                                    <div class="comment-info">
                                                        <strong>@<?php echo htmlspecialchars($c['username']); ?></strong>
                                                        <small><?php echo date('M j, Y H:i', strtotime($c['created_at'])); ?></small>
                                                        <p><?php echo htmlspecialchars($c['comment']); ?></p>
                                                    </div>
                                                    <form method="POST" onsubmit="return confirm('Delete this comment?');">
                                                        <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                                        <input type="hidden" name="return_reel_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" name="admin_delete_comment" class="btn-delete-comment"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <p style="color:#999; font-style:italic; font-family:'Staatliches',sans-serif;">NO COMMENTS YET.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 1.5rem; border-top: 2px solid #ddd; padding-top: 1.5rem;">
                                        <button type="button" class="btn btn-danger" style="width:100%; font-size: 1.2rem;" onclick="openModal('reel-delete-<?php echo $item['id']; ?>')">DELETE REEL PERMANENTLY</button>
                                    </div>
                                </div>
                            </div>

                            <!-- DELETE REEL MODAL -->
                            <div id="reel-delete-<?php echo $item['id']; ?>" class="modal-overlay modal-top-layer">
                                <div class="modal-content">
                                    <span class="close-modal" onclick="closeModal('reel-delete-<?php echo $item['id']; ?>')">&times;</span>
                                    <h3 class="admin-table-h3">DELETE <span class="header-span">CONFIRMATION</span></h3>
                                    <form method="post" action="reels.php" class="admin-form">
                                        <input type="hidden" name="reel_id" value="<?php echo $item['id']; ?>">
                                        
                                        <label>WHY ARE YOU DELETING THIS REEL?</label>
                                        <textarea name="deletion_reason" rows="4" placeholder="E.g. Inappropriate content, copyright violation, spam..." required maxlength="500"></textarea>
                                        
                                        <p style="margin-top: 1rem; color: var(--primary); font-family: 'Staatliches', sans-serif; font-size: 0.9rem;">* THIS WILL PERMANENTLY REMOVE THE REEL, LIKES, AND COMMENTS.</p>
                                        
                                        <button type="submit" name="delete_reel" class="btn btn-danger" style="width:100%; margin-top: 1rem; font-size: 1.2rem;">PERMANENTLY DELETE</button>
                                    </form>
                                </div>
                            </div>
                            <?php $modals_html[] = ob_get_clean(); ?>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center; font-weight:700; font-style: italic;">NO PUBLISHED REELS FOUND.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table></div>

            <?php render_intelligent_pagination($page, $total_pages, 'admin-pagination'); ?>
        </div>
    </main>
</section>

<?php foreach ($modals_html as $html) echo $html; ?>

</body>
</html>


