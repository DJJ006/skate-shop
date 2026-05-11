<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../client_page/login.php");
    exit();
}

// Handle delete reel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_reel'])) {
    $id = (int)$_POST['reel_id'];
    $conn->query("DELETE FROM reel_likes WHERE reel_id = $id");
    $conn->query("DELETE FROM reel_comments WHERE reel_id = $id");
    $conn->query("DELETE FROM reels WHERE id = $id");
    $_SESSION['msg'] = "REEL DELETED PERMANENTLY.";
    $_SESSION['msg_type'] = "success";
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

// Fetch all approved reels with counts
$reels_sql = "SELECT r.*, 
    (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.id) as like_count,
    (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.id) as comment_count
    FROM reels r 
    WHERE r.is_approved = 1 
    ORDER BY r.created_at DESC";
$reels_result = $conn->query($reels_sql);

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
</head>
<body>

<header class="main-header">
    <div class="container header-content">
        <h1 class="logo"><a href="index.php">SKATE<span>SHOP</span> ADMIN</a></h1>
    </div>
</header>

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
            <table class="recent-activity-table">
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
                    <?php if ($reels_result->num_rows > 0): ?>
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
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('DELETE THIS REEL AND ALL ITS LIKES/COMMENTS?');">
                                        <input type="hidden" name="reel_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete_reel" class="btn-mini btn-danger" style="padding:6px 10px; font-size:0.85rem;"><i class="fas fa-trash"></i></button>
                                    </form>
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
                            </div>
                            <?php $modals_html[] = ob_get_clean(); ?>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center; font-weight:700; font-style: italic;">NO PUBLISHED REELS FOUND.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</section>

<?php foreach ($modals_html as $html) echo $html; ?>

</body>
</html>
