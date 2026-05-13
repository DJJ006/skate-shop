<?php
session_start();
include '../db.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: ../client_page/login.php");
    exit();
}



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_reel'])) {
    $id = (int)$_POST['reel_id'];
    
    $info_stmt = $conn->prepare("SELECT title, user_id FROM reels WHERE id = ?");
    $info_stmt->bind_param("i", $id);
    $info_stmt->execute();
    $reel = $info_stmt->get_result()->fetch_assoc();

    if ($reel) {
        $sql = "UPDATE reels SET is_approved = 1, approved_at = CURRENT_TIMESTAMP WHERE id = $id";
        if ($conn->query($sql) === TRUE) {
            $msg = "Your reel '" . $reel['title'] . "' has been approved and published.";
            $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notif->bind_param("is", $reel['user_id'], $msg);
            $notif->execute();

            $_SESSION['msg'] = "REEL APPROVED! CLIENT NOTIFIED.";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: accept-reels.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_reel_final'])) {
    $id = (int)$_POST['reel_id'];
    $reason = $conn->real_escape_string($_POST['rejection_reason']);

    $info_stmt = $conn->prepare("SELECT title, user_id FROM reels WHERE id = ?");
    $info_stmt->bind_param("i", $id);
    $info_stmt->execute();
    $reel = $info_stmt->get_result()->fetch_assoc();

    if ($reel) {
        $u_id = (int)$reel['user_id'];
        $title = $reel['title'];

        // Send notification BEFORE deletion to ensure data availability if needed
        $msg = "Your reel '" . $title . "' was rejected by an admin. Reason: " . $reason;
        $notif = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
        $notif->bind_param("is", $u_id, $msg);
        $notif->execute();

        // Perform full cleanup
        $conn->query("DELETE FROM reel_likes WHERE reel_id = $id");
        $conn->query("DELETE FROM reel_comments WHERE reel_id = $id");
        $conn->query("DELETE FROM reel_edit_requests WHERE reel_id = $id");
        $conn->query("DELETE FROM reels WHERE id = $id");

        $_SESSION['msg'] = "REEL REJECTED & DELETED. CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: accept-reels.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_reel_edit'])) {
    $edit_id = (int)$_POST['edit_id'];

    $edit_stmt = $conn->prepare("SELECT rer.id, rer.reel_id, rer.user_id, rer.new_title, rer.new_description, r.title AS old_title FROM reel_edit_requests rer JOIN reels r ON r.id = rer.reel_id WHERE rer.id = ? AND rer.status = 'pending'");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit = $edit_stmt->get_result()->fetch_assoc();

    if ($edit) {
        $upd = $conn->prepare("UPDATE reels SET title = ?, description = ? WHERE id = ?");
        $upd->bind_param("ssi", $edit['new_title'], $edit['new_description'], $edit['reel_id']);
        $upd->execute();

        $close = $conn->prepare("UPDATE reel_edit_requests SET status = 'approved' WHERE id = ?");
        $close->bind_param("i", $edit_id);
        $close->execute();

        $msg_text = "Your reel edit for '" . $edit['old_title'] . "' has been approved! The changes are now live.";
        $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notif->bind_param("is", $edit['user_id'], $msg_text);
        $notif->execute();

        $_SESSION['msg'] = "REEL EDIT APPROVED & PUBLISHED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: accept-reels.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_reel_edit'])) {
    $edit_id = (int)$_POST['edit_id'];
    $reason = trim($_POST['rejection_reason']);

    $edit_stmt = $conn->prepare("SELECT rer.id, rer.reel_id, rer.user_id, r.title AS old_title FROM reel_edit_requests rer JOIN reels r ON r.id = rer.reel_id WHERE rer.id = ? AND rer.status = 'pending'");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit = $edit_stmt->get_result()->fetch_assoc();

    if ($edit) {
        $close = $conn->prepare("UPDATE reel_edit_requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $close->bind_param("si", $reason, $edit_id);
        $close->execute();

        $msg_text = "Your reel edit for '" . $edit['old_title'] . "' was rejected. Reason: " . $reason;
        $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notif->bind_param("is", $edit['user_id'], $msg_text);
        $notif->execute();

        $_SESSION['msg'] = "REEL EDIT REJECTED & CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: accept-reels.php");
    exit();
}

$pending_sql = "SELECT id, user_id, username, title, platform, embed_url, description, created_at 
                FROM reels 
                WHERE is_approved = 0 
                ORDER BY created_at ASC";
$pending_result = $conn->query($pending_sql);

// Fetch pending reel edit requests
$pending_edits_sql = "SELECT rer.id AS edit_id, rer.reel_id, rer.new_title, rer.new_description, rer.created_at AS submitted_at,
                             r.title AS orig_title, r.description AS orig_desc, r.embed_url, r.username
                      FROM reel_edit_requests rer
                      JOIN reels r ON r.id = rer.reel_id
                      WHERE rer.status = 'pending'
                      ORDER BY rer.created_at ASC";
$pending_edits_result = $conn->query($pending_edits_sql);

$modals_html = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | PENDING REELS</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <script src="../assets/admin-script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .video-preview {
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            margin-bottom: 1rem;
            border: 4px solid var(--charcoal);
        }
        .video-preview iframe {
            width: 100%;
            height: 100%;
            border: none;
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
    
<div style="margin-top: 47px;">
    <?php include 'admin_sidebar.php'; ?>
</div>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">PENDING <span class="text-primary">REELS</span></h2>
                <p class="admin-text-shop">REVIEW CLIENT SUBMITTED COMMUNITY VIDEOS</p>
            </div>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="admin-alert alert-<?php echo $_SESSION['msg_type']; ?>">
                <?php 
                    echo $_SESSION['msg']; 
                    unset($_SESSION['msg']); 
                    unset($_SESSION['msg_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="grainy-card card-padding" style="padding: 20px;">
        <h3 class="admin-table-h3">PENDING <span class="header-span">CLIPS</span></h3>
            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>UPLOADER</th>
                        <th>TITLE</th>
                        <th>PLATFORM</th>
                        <th>REVIEW</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pending_result->num_rows > 0): ?>
                        <?php while($item = $pending_result->fetch_assoc()): 
                            $submit_time = isset($item['created_at']) ? date("M j, Y @ H:i", strtotime($item['created_at'])) : 'N/A';
                        ?>
                            <tr>
                                <td class="td-id">#<?php echo $item['id']; ?></td>
                                <td>
                                    <strong>@<?php echo htmlspecialchars($item['username']); ?></strong><br>
                                </td>
                                <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                <td><span class="badge-status status-active"><?php echo htmlspecialchars($item['platform']); ?></span></td>
                                
                                <td>
                                    <button class="btn-mini btn-view" onclick="openModal('review-<?php echo $item['id']; ?>')">VIEW DETAILS</button>
                                </td>
                            </tr>

                            <?php ob_start(); ?>
                            <div id="review-<?php echo $item['id']; ?>" class="modal-overlay">
                                <div class="modal-content modal-content-lg">
                                    <span class="close-modal" onclick="closeModal('review-<?php echo $item['id']; ?>')">&times;</span>
                                    <h3 class="admin-table-h3 top-title">REVIEW <span class="header-span">REEL</span></h3>
                                    
                                    <div class="modal-grid">
                                        <div>
                                            <div class="video-preview">
                                                <iframe src="<?php echo htmlspecialchars($item['embed_url']); ?>" allowfullscreen></iframe>
                                            </div>
                                            <div class="modal-date-box">
                                                SUBMITTED:<br><?php echo $submit_time; ?>
                                            </div>
                                        </div>

                                        <div class="modal-details">
                                            <div class="detail-row">
                                                <span class="detail-label">UPLOADER:</span>
                                                <span>@<?php echo htmlspecialchars($item['username']); ?></span>
                                            </div>
                                            <div class="detail-row"><span class="detail-label">TITLE:</span> <span><?php echo htmlspecialchars($item['title']); ?></span></div>
                                            <div class="detail-row"><span class="detail-label">PLATFORM:</span> <span><?php echo htmlspecialchars($item['platform']); ?></span></div>
                                        </div>
                                    </div>
                                    
                                    <div class="modal-description-box">
                                        <strong>Description / Caption:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($item['description'] ?? 'No description provided.')); ?></p>
                                    </div>

                                    <div class="modal-actions">
                                        <form method="POST" action="accept-reels.php">
                                            <input type="hidden" name="reel_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="accept_reel" class="btn btn-accept btn-full btn-heavy-font">ACCEPT & PUBLISH</button>
                                        </form>

                                        <button type="button" class="btn btn-danger btn-full btn-heavy-font" onclick="openModal('reject-reason-<?php echo $item['id']; ?>')">
                                            REJECT
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php $modals_html[] = ob_get_clean(); ?>

                            <?php ob_start(); ?>
                                <div id="reject-reason-<?php echo $item['id']; ?>" class="modal-overlay modal-top-layer">
                                    <div class="modal-content">
                                        <span class="close-modal" onclick="closeModal('reject-reason-<?php echo $item['id']; ?>')">&times;</span>
                                        <h3 class="admin-table-h3">REJECTION <span class="header-span">REASON</span></h3>
                                        
                                        <form method="POST" action="accept-reels.php">
                                            <input type="hidden" name="reel_id" value="<?php echo $item['id']; ?>">
                                            
                                            <div class="admin-form-group">
                                                <label class="admin-form-label">WHY ARE YOU REJECTING THIS REEL?</label>
                                                <textarea 
                                                    name="rejection_reason" 
                                                    rows="4" 
                                                    class="admin-input-dark" 
                                                    placeholder="E.g. Not a skate video, broken link..." 
                                                    required></textarea>
                                            </div>
                                            
                                            <button type="submit" name="reject_reel_final" class="btn btn-danger btn-full btn-heavy-font">
                                                SEND REJECTION
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php $modals_html[] = ob_get_clean(); ?>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; font-weight:700; font-style: italic;">NO PENDING REELS TO REVIEW.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="grainy-card card-padding" style="padding: 20px; margin-top: 30px;">
            <h3 class="admin-table-h3">PENDING <span class="header-span">EDITS</span></h3>
            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>EDIT ID</th>
                        <th>REEL ID</th>
                        <th>UPLOADER</th>
                        <th>ORIGINAL TITLE</th>
                        <th>REVIEW</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pending_edits_result && $pending_edits_result->num_rows > 0): ?>
                        <?php while($e = $pending_edits_result->fetch_assoc()): 
                            $e_modal_id = 'edit-review-' . $e['edit_id'];
                            $e_reject_id = 'edit-reject-' . $e['edit_id'];
                            $submitted = date('M j, Y @ H:i', strtotime($e['submitted_at']));
                        ?>
                            <tr>
                                <td class="td-id">#<?php echo $e['edit_id']; ?></td>
                                <td>#<?php echo $e['reel_id']; ?></td>
                                <td><strong>@<?php echo htmlspecialchars($e['username']); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($e['orig_title']); ?></strong></td>
                                <td>
                                    <button class="btn-mini btn-view" onclick="openModal('<?php echo $e_modal_id; ?>')">COMPARE EDIT</button>
                                </td>
                            </tr>

                            <?php ob_start(); ?>
                            <div id="<?php echo $e_modal_id; ?>" class="modal-overlay">
                                <div class="modal-content modal-content-lg">
                                    <span class="close-modal" onclick="closeModal('<?php echo $e_modal_id; ?>')">&times;</span>
                                    <h3 class="admin-table-h3">REVIEW <span class="header-span">EDIT</span></h3>

                                    <div style="aspect-ratio:16/9; background:#000; margin-bottom:1rem; border:4px solid var(--charcoal);">
                                        <iframe src="<?php echo htmlspecialchars($e['embed_url']); ?>" allowfullscreen style="width:100%;height:100%;border:none;"></iframe>
                                    </div>

                                    <p style="font-family:'Staatliches',sans-serif; font-size:0.9rem; color:#777; margin-bottom:1rem;">SUBMITTED BY @<?php echo htmlspecialchars($e['username']); ?> &mdash; <?php echo $submitted; ?></p>

                                    <div class="edit-compare-grid">
                                        <div class="edit-compare-box">
                                            <h5>ORIGINAL</h5>
                                            <p><strong><?php echo htmlspecialchars($e['orig_title']); ?></strong></p>
                                            <p style="margin-top:6px; color:#555;"><?php echo htmlspecialchars($e['orig_desc'] ?? 'No description.'); ?></p>
                                        </div>
                                        <div class="edit-compare-box new-version">
                                            <h5>PROPOSED EDIT</h5>
                                            <p><strong><?php echo htmlspecialchars($e['new_title']); ?></strong></p>
                                            <p style="margin-top:6px; color:#333;"><?php echo htmlspecialchars($e['new_description'] ?? 'No description.'); ?></p>
                                        </div>
                                    </div>

                                    <div class="modal-actions">
                                        <form method="POST" action="accept-reels.php">
                                            <input type="hidden" name="edit_id" value="<?php echo $e['edit_id']; ?>">
                                            <button type="submit" name="approve_reel_edit" class="btn btn-accept btn-full btn-heavy-font">APPROVE EDIT</button>
                                        </form>
                                        <button type="button" class="btn btn-danger btn-full btn-heavy-font" onclick="openModal('<?php echo $e_reject_id; ?>')">REJECT</button>
                                    </div>
                                </div>
                            </div>

                            <div id="<?php echo $e_reject_id; ?>" class="modal-overlay modal-top-layer">
                                <div class="modal-content">
                                    <span class="close-modal" onclick="closeModal('<?php echo $e_reject_id; ?>');">&times;</span>
                                    <h3 class="admin-table-h3">REJECTION <span class="header-span">REASON</span></h3>
                                    <form method="POST" action="accept-reels.php">
                                        <input type="hidden" name="edit_id" value="<?php echo $e['edit_id']; ?>">
                                        <div class="admin-form-group">
                                            <label class="admin-form-label">WHY ARE YOU REJECTING THIS EDIT?</label>
                                            <textarea name="rejection_reason" rows="4" class="admin-input-dark" placeholder="E.g. Title contains inappropriate content..." required></textarea>
                                        </div>
                                        <button type="submit" name="reject_reel_edit" class="btn btn-danger btn-full btn-heavy-font">SEND REJECTION</button>
                                    </form>
                                </div>
                            </div>
                            <?php $modals_html[] = ob_get_clean(); ?>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; font-weight:700; font-style: italic;">NO PENDING EDITS TO REVIEW.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</section>

<style>
    .edit-compare-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .edit-compare-box { padding: 1rem; border: 2px solid #ddd; background: #f9f9f9; }
    .edit-compare-box h5 { font-family: 'Staatliches', sans-serif; font-size: 1rem; letter-spacing: 1px; margin: 0 0 6px; }
    .edit-compare-box p { font-family: 'Inter', sans-serif; font-size: 0.88rem; color: #333; margin: 0; word-break: break-word; }
    .edit-compare-box.new-version { border-color: #4ADE80; background: #f0fff4; }
    @media(max-width:640px) { .edit-compare-grid { grid-template-columns: 1fr; } }
</style>

<?php 
    // Render all collected modals (both for new clips and pending edits) at the end of the body
    foreach ($modals_html as $html) {
        echo $html;
    }
?>

</body>
</html>
