<?php
require_once 'admin_auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php';
require_once '../notification-service.php';

$review_redirect_allowed = ['all', 'Pending Approval', 'Approved', 'Rejected'];

function review_admin_redirect_url(): string {
    global $review_redirect_allowed;
    $st = $_POST['redirect_status'] ?? 'all';
    if (!in_array($st, $review_redirect_allowed, true)) {
        $st = 'all';
    }
    $q = trim($_POST['redirect_q'] ?? '');
    $sort = $_POST['redirect_sort'] ?? 'newest';
    
    $params = ['status' => $st, 'sort' => $sort];
    if ($q !== '') {
        $params['q'] = $q;
    }
    return 'review-sellers.php?' . http_build_query($params);
}

function review_notify_user(mysqli $conn, int $user_id, string $message): void {
    sendAppNotification($conn, $user_id, $message);
}

function recalculateSellerStats(mysqli $conn, int $seller_id) {
    $conn->query("UPDATE users u SET u.average_seller_rating = (SELECT IFNULL(AVG(rating), 0) FROM seller_ratings WHERE seller_id = $seller_id AND status = 'Approved'), u.total_seller_reviews = (SELECT COUNT(*) FROM seller_ratings WHERE seller_id = $seller_id AND status = 'Approved') WHERE u.id = $seller_id");
}

// --- POST: approve ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_approve'])) {
    $id = (int)$_POST['review_id'];

    if ($id <= 0) {
        $_SESSION['msg'] = 'INVALID REVIEW ID.';
        $_SESSION['msg_type'] = 'error';
    } else {
        $info = $conn->prepare('SELECT r.buyer_id, r.seller_id, u_seller.username as seller_username, r.status FROM seller_ratings r JOIN users u_seller ON r.seller_id = u_seller.id WHERE r.id = ?');
        $info->bind_param('i', $id);
        $info->execute();
        $row = $info->get_result()->fetch_assoc();

        if (!$row || $row['status'] !== 'Pending Approval') {
            $_SESSION['msg'] = 'INVALID ITEM OR NOT PENDING.';
            $_SESSION['msg_type'] = 'error';
        } else {
            $upd = $conn->prepare("UPDATE seller_ratings SET status = 'Approved' WHERE id = ? AND status = 'Pending Approval'");
            $upd->bind_param('i', $id);
            if ($upd->execute() && $upd->affected_rows > 0) {
                recalculateSellerStats($conn, $row['seller_id']);
                
                $title = $row['seller_username'];
                $uid = (int)$row['buyer_id'];
                $seller_id = (int)$row['seller_id'];
                
                $msg = 'Your review for "' . $title . '" has been approved and is now live!';
                review_notify_user($conn, $uid, $msg);
                
                $seller_msg = 'Great news! A new buyer review has been approved and published to your profile.';
                review_notify_user($conn, $seller_id, $seller_msg);
                $_SESSION['msg'] = 'REVIEW APPROVED & USER NOTIFIED.';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = 'COULD NOT APPROVE (CHECK STATUS).';
                $_SESSION['msg_type'] = 'error';
            }
        }
    }
    header('Location: ' . review_admin_redirect_url());
    exit();
}

// --- POST: reject ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_reject_final'])) {
    $id = (int)$_POST['review_id'];
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($id <= 0 || $reason === '') {
        $_SESSION['msg'] = 'REJECTION REASON IS REQUIRED.';
        $_SESSION['msg_type'] = 'error';
    } elseif (mb_strlen($reason) > 500) {
        $_SESSION['msg'] = 'REJECTION REASON CANNOT EXCEED 500 CHARACTERS.';
        $_SESSION['msg_type'] = 'error';
    } else {
        $info = $conn->prepare('SELECT r.buyer_id, r.seller_id, u_seller.username as seller_username, r.status FROM seller_ratings r JOIN users u_seller ON r.seller_id = u_seller.id WHERE r.id = ?');
        $info->bind_param('i', $id);
        $info->execute();
        $row = $info->get_result()->fetch_assoc();

        if (!$row || $row['status'] !== 'Pending Approval') {
            $_SESSION['msg'] = 'INVALID ITEM OR NOT PENDING.';
            $_SESSION['msg_type'] = 'error';
        } else {
            $check_prev = $conn->prepare("SELECT previous_rating, previous_comment FROM seller_ratings WHERE id = ?");
            $check_prev->bind_param('i', $id);
            $check_prev->execute();
            $prev_row = $check_prev->get_result()->fetch_assoc();
            
            if ($prev_row && $prev_row['previous_rating'] !== null) {
                // Restore previous state instead of rejecting completely
                $upd = $conn->prepare("UPDATE seller_ratings SET status = 'Approved', rating = previous_rating, comment = previous_comment, previous_rating = NULL, previous_comment = NULL WHERE id = ? AND status = 'Pending Approval'");
                $is_restore = true;
            } else {
                $upd = $conn->prepare("UPDATE seller_ratings SET status = 'Rejected' WHERE id = ? AND status = 'Pending Approval'");
                $is_restore = false;
            }
            $upd->bind_param('i', $id);
            if ($upd->execute() && $upd->affected_rows > 0) {
                if ($is_restore) {
                    recalculateSellerStats($conn, (int)$row['seller_id']);
                    $msg = 'Your review edit for "' . $row['seller_username'] . '" was rejected. Reason: ' . $reason . '. The review has been restored to its previous approved state.';
                    $seller_msg = 'An edit to a buyer review on your profile was rejected by the admin. Reason: ' . $reason;
                } else {
                    $msg = 'Your review for "' . $row['seller_username'] . '" was rejected. Reason: ' . $reason;
                    $seller_msg = 'A pending buyer review for your profile was rejected by the admin. Reason: ' . $reason;
                }
                review_notify_user($conn, (int)$row['buyer_id'], $msg);
                review_notify_user($conn, (int)$row['seller_id'], $seller_msg);
                $_SESSION['msg'] = 'REVIEW REJECTED. CLIENT NOTIFIED.';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = 'COULD NOT REJECT.';
                $_SESSION['msg_type'] = 'error';
            }
        }
    }
    header('Location: ' . review_admin_redirect_url());
    exit();
}

// --- POST: delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_delete'])) {
    $id = (int)$_POST['review_id'];
    $reason = trim($_POST['deletion_reason'] ?? '');

    if ($id <= 0 || $reason === '') {
        $_SESSION['msg'] = 'DELETION REASON IS REQUIRED.';
        $_SESSION['msg_type'] = 'error';
    } elseif (mb_strlen($reason) > 500) {
        $_SESSION['msg'] = 'DELETION REASON CANNOT EXCEED 500 CHARACTERS.';
        $_SESSION['msg_type'] = 'error';
    } else {
        $info = $conn->prepare('SELECT r.buyer_id, r.seller_id, u_seller.username as seller_username, r.status FROM seller_ratings r JOIN users u_seller ON r.seller_id = u_seller.id WHERE r.id = ?');
        $info->bind_param('i', $id);
        $info->execute();
        $row = $info->get_result()->fetch_assoc();

        if ($row) {
            $del = $conn->prepare('DELETE FROM seller_ratings WHERE id = ?');
            $del->bind_param('i', $id);
            if ($del->execute() && $del->affected_rows > 0) {
                if ($row['status'] === 'Approved') {
                    recalculateSellerStats($conn, $row['seller_id']);
                }
                
                $msg = 'Your review for "' . $row['seller_username'] . '" was removed by an administrator. Reason: ' . $reason;
                review_notify_user($conn, (int)$row['buyer_id'], $msg);
                
                $seller_msg = 'A review on your profile was removed by an administrator. Reason: ' . $reason;
                review_notify_user($conn, (int)$row['seller_id'], $seller_msg);
                
                $_SESSION['msg'] = 'REVIEW DELETED & USER NOTIFIED.';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = 'DELETE FAILED.';
                $_SESSION['msg_type'] = 'error';
            }
        } else {
            $_SESSION['msg'] = 'REVIEW NOT FOUND.';
            $_SESSION['msg_type'] = 'error';
        }
    }
    header('Location: ' . review_admin_redirect_url());
    exit();
}

// --- List query ---
$status_filter = isset($_GET['status']) && in_array($_GET['status'], $review_redirect_allowed, true) ? $_GET['status'] : 'all';
$search_q = isset($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$sort_filter = $_GET['sort'] ?? 'newest';

$where = ['1=1'];
$types = "";
$params = [];

if ($status_filter !== 'all') {
    $where[] = "pr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($search_q !== '') {
    $where[] = "(pr.id = ? OR u_seller.username LIKE ? OR pr.comment LIKE ? OR u.username LIKE ?)";
    $s_id = (int)$search_q;
    $like = "%$search_q%";
    $params[] = $s_id;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "isss";
}
$where_sql = implode(' AND ', $where);

// Sorting Logic
$order_sql = 'ORDER BY pr.created_at DESC';
if ($sort_filter === 'oldest') {
    $order_sql = 'ORDER BY pr.created_at ASC';
} elseif ($sort_filter === 'pending') {
    $order_sql = "ORDER BY (pr.status = 'Pending Approval') DESC, pr.created_at DESC";
} elseif ($sort_filter === 'published') {
    $order_sql = "ORDER BY (pr.status = 'Approved') DESC, pr.created_at DESC";
}

// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_res = null;
$count_sql = "SELECT COUNT(*) as total FROM seller_ratings pr JOIN users u_seller ON pr.seller_id = u_seller.id JOIN users u ON pr.buyer_id = u.id WHERE $where_sql";
$stmt_count = $conn->prepare($count_sql);
if ($stmt_count) {
    if ($types) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $count_res = $stmt_count->get_result();
}

if ($count_res) {
    $total_records = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);
} else {
    $total_pages = 0;
}

$list_sql = "SELECT pr.*, u_seller.username as seller_username, u_seller.profile_pic as profile_pic, u.username as buyer_username FROM seller_ratings pr JOIN users u_seller ON pr.seller_id = u_seller.id JOIN users u ON pr.buyer_id = u.id WHERE $where_sql $order_sql LIMIT ? OFFSET ?";
$stmt_data = $conn->prepare($list_sql);
$types_data = $types . "ii";
$params_data = $params;
$params_data[] = $limit;
$params_data[] = $offset;
$stmt_data->bind_param($types_data, ...$params_data);
$stmt_data->execute();
$list_result = $stmt_data->get_result();

$rows = [];
if ($list_result) {
    while ($r = $list_result->fetch_assoc()) {
        $rows[] = $r;
    }
}

$modals_html = [];

$review_redirect_hidden = '<input type="hidden" name="redirect_status" value="' . htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="redirect_q" value="' . htmlspecialchars($search_q, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="redirect_sort" value="' . htmlspecialchars($sort_filter, ENT_QUOTES, 'UTF-8') . '">';

function review_status_badge_class(string $status): string {
    if ($status === 'Pending Approval') {
        return 'listing-status-badge status-received';
    }
    if ($status === 'Approved') {
        return 'listing-status-badge status-active';
    }
    return 'listing-status-badge status-cancelled';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | MODERATE SELLER REVIEWS</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <script src="../assets/admin-script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .review-modal-body-preview { 
            max-height: 160px; 
            overflow-y: auto; 
            padding: 1.2rem; 
            background: #f8f8f8; 
            border: 3px solid var(--charcoal); 
            font-family: 'Inter', sans-serif; 
            font-size: 1rem; 
            white-space: pre-wrap; 
            color: #333;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        /* Highlight Pending Rows */
        .recent-activity-table tr.pending-row td {
            background-color: color-mix(in srgb, var(--primary), transparent 94%);
        }
        .recent-activity-table tr.pending-row:hover td {
            background-color: color-mix(in srgb, var(--primary), transparent 88%);
        }
        .detail-row {
            margin-bottom: 0.8rem;
            font-family: 'Inter', sans-serif;
            font-size: 1.05rem;
        }
        .detail-label {
            font-family: 'Staatliches', sans-serif;
            letter-spacing: 1px;
            color: var(--charcoal);
            margin-right: 8px;
            font-weight: 700;
        }
        .recent-activity-table td:last-child {
            white-space: nowrap;
        }
        .recent-activity-table td:last-child .btn-mini,
        .recent-activity-table td:last-child form {
            display: inline-block;
            vertical-align: middle;
        }
        .recent-activity-table td:last-child form {
            margin-left: 8px;
        }
        .product-thumb-modal {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 2px solid var(--charcoal);
            border-radius: 4px;
            margin-right: 15px;
            float: left;
        }
    </style>
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>

<?php require __DIR__ . '/admin_header.php'; ?>

<section class="admin-layout container">
    <div style="margin-top: 47px;">
        <?php include 'admin_sidebar.php'; ?>
    </div>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">REVIEW <span class="text-primary">SELLERS</span></h2>
                <p class="admin-text-shop">MODERATE SELLER REVIEWS</p>
            </div>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="admin-alert alert-<?php echo htmlspecialchars($_SESSION['msg_type'] ?? 'success'); ?>">
                <?php
                echo htmlspecialchars($_SESSION['msg']);
                unset($_SESSION['msg'], $_SESSION['msg_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="grainy-card card-padding" style="padding: 20px;">
            <h3 class="admin-table-h3">GEAR <span class="header-span">REVIEWS</span></h3>

            <div class="grainy-card filter-bar" style="margin-bottom: 25px;">
            <form method="get" action="review-sellers.php" class="search-filter-form" id="review-filter-form">
                <div class="filter-group search-box">
                    <label>SEARCH REVIEWS</label>
                    <input type="text" name="q" id="review-search-input" placeholder="ID, product, comment, username..." value="<?php echo htmlspecialchars($search_q, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>

                <div class="filter-group" style="flex: 1;">
                    <label>STATUS</label>
                    <select name="status" id="review-status-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>ALL STATUSES</option>
                        <option value="Pending Approval" <?php echo $status_filter === 'Pending Approval' ? 'selected' : ''; ?>>PENDING</option>
                        <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>APPROVED</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>REJECTED</option>
                    </select>
                </div>

                <div class="filter-group" style="flex: 1;">
                    <label>SORT BY</label>
                    <select name="sort" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sort_filter === 'newest' ? 'selected' : ''; ?>>NEWEST FIRST</option>
                        <option value="oldest" <?php echo $sort_filter === 'oldest' ? 'selected' : ''; ?>>OLDEST FIRST</option>
                        <option value="pending" <?php echo $sort_filter === 'pending' ? 'selected' : ''; ?>>PENDING FIRST</option>
                        <option value="published" <?php echo $sort_filter === 'published' ? 'selected' : ''; ?>>APPROVED FIRST</option>
                    </select>
                </div>

                <div class="filter-actions" style="margin-top:22px;">
                    <button type="submit" class="btn-filter">SEARCH</button>
                    <?php if ($search_q !== '' || $status_filter !== 'all' || $sort_filter !== 'newest'): ?>
                        <a href="review-sellers.php" class="btn-reset">RESET</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
            <div class="table-responsive"><table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>USER</th>
                        <th>SELLER</th>
                        <th>RATING</th>
                        <th>STATUS</th>
                        <th>SUBMITTED</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="7" style="text-align:center;font-weight:700;font-style:italic;">NO REVIEWS MATCH THIS FILTER.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $item):
                            $mid = 'review-modal-' . (int)$item['id'];
                            $reject_mid = 'review-reject-' . (int)$item['id'];
                            $delete_mid = 'review-delete-' . (int)$item['id'];
                            $submitted = date('M j, Y @ H:i', strtotime($item['created_at']));
                            $title_short = mb_strlen($item['seller_username']) > 32 ? mb_substr($item['seller_username'], 0, 29) . '…' : $item['seller_username'];
                            $status = $item['status'];
                            ?>
                             <tr class="<?php echo ($status === 'Pending Approval') ? 'pending-row' : ''; ?>">
                                <td class="td-id">#<?php echo (int)$item['id']; ?></td>
                                <td><strong>@<?php echo htmlspecialchars($item['buyer_username']); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($title_short); ?></strong></td>
                                <td>
                                    <div style="color: var(--primary);">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo ($i <= $item['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                    }
                                    ?>
                                    </div>
                                </td>
                                <td><span class="<?php echo review_status_badge_class($status); ?>"><?php echo strtoupper(htmlspecialchars($status)); ?></span></td>
                                <td><?php echo htmlspecialchars($submitted); ?></td>
                                <td>
                                    <button type="button" class="btn-mini btn-view" onclick="openModal('<?php echo htmlspecialchars($mid); ?>')">VIEW / EDIT</button>
                                    <button type="button" class="btn-mini btn-danger" style="padding:6px 10px; font-size:0.85rem;" onclick="openModal('<?php echo htmlspecialchars($delete_mid); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php ob_start(); ?>
                            <div id="<?php echo htmlspecialchars($mid); ?>" class="modal-overlay">
                                <div class="modal-content modal-content-lg">
                                    <span class="close-modal" onclick="closeModal('<?php echo htmlspecialchars($mid); ?>')">&times;</span>
                                    <h3 class="admin-table-h3 top-title">REVIEW <span class="header-span">DETAIL</span></h3>
                                    <p style="font-family:'Staatliches',sans-serif;font-size:1.1rem;color:#666;margin-bottom:1rem;">
                                        SUBMITTED <?php echo htmlspecialchars($submitted); ?> &mdash;
                                        <span class="<?php echo review_status_badge_class($status); ?>"><?php echo strtoupper(htmlspecialchars($status)); ?></span>
                                    </p>
                                    
                                    <div style="margin-bottom: 20px; overflow: hidden;">
                                        <img src="<?php echo htmlspecialchars($item['profile_pic']); ?>" class="product-thumb-modal" alt="Seller Image">
                                        <div class="detail-row"><span class="detail-label">BUYER:</span> <span>@<?php echo htmlspecialchars($item['buyer_username']); ?> (ID <?php echo (int)$item['buyer_id']; ?>)</span></div>
                                        <div class="detail-row" style="margin-top:0.5rem;"><span class="detail-label">SELLER:</span> <span><?php echo htmlspecialchars($item['seller_username']); ?></span></div>
                                        <div class="detail-row" style="margin-top:0.5rem;"><span class="detail-label">RATING:</span> <span style="color: var(--primary);">
                                            <?php 
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo ($i <= $item['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                            }
                                            ?>
                                        </span></div>
                                    </div>
                                    <div style="clear: both;"></div>

                                    <div style="margin-top:1rem; margin-bottom: 0.5rem;"><strong>COMMENT :</strong></div>
                                    <div class="review-modal-body-preview"><?php if (!empty($item['comment'])): ?><?php echo htmlspecialchars($item['comment']); ?><?php else: ?><em style="color: #888;">No comment provided.</em><?php endif; ?></div>

                                     <?php if ($status === 'Pending Approval'): ?>
                                        <form method="post" action="review-sellers.php" class="admin-form" style="margin-top:1.5rem;">
                                            <?php echo $review_redirect_hidden; ?>
                                            <input type="hidden" name="review_id" value="<?php echo (int)$item['id']; ?>">
                                            
                                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-top: 1rem;">
                                                <button type="submit" name="review_approve" class="btn btn-primary" style="font-size: 1.2rem;"><i class="fas fa-check"></i> ACCEPT &amp; PUBLISH</button>
                                                <button type="button" class="btn btn-danger" onclick="openModal('<?php echo htmlspecialchars($reject_mid); ?>')" style="font-size: 1.2rem;">
                                                    <i class="fas fa-times"></i> REJECT
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php else: ?>
                                        <div style="margin-top:1.5rem;">
                                            <button type="button" class="btn btn-danger" style="width:100%; font-size: 1.2rem;" onclick="openModal('<?php echo htmlspecialchars($delete_mid); ?>')">DELETE PERMANENTLY</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($status === 'Pending Approval'): ?>
                                <div id="<?php echo htmlspecialchars($reject_mid); ?>" class="modal-overlay modal-top-layer">
                                    <div class="modal-content">
                                        <span class="close-modal" onclick="closeModal('<?php echo htmlspecialchars($reject_mid); ?>')">&times;</span>
                                        <h3 class="admin-table-h3">REJECTION <span class="header-span">REASON</span></h3>
                                        <form method="post" action="review-sellers.php" class="admin-form">
                                            <?php echo $review_redirect_hidden; ?>
                                            <input type="hidden" name="review_id" value="<?php echo (int)$item['id']; ?>">
                                            
                                            <label>WHY ARE YOU REJECTING THIS REVIEW?</label>
                                            <textarea name="rejection_reason" rows="4" placeholder="E.g. Inappropriate language, spam..." required maxlength="500"></textarea>
                                            
                                            <button type="submit" name="review_reject_final" class="btn btn-danger" style="width:100%; margin-top: 1rem; font-size: 1.2rem;">SEND REJECTION</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div id="<?php echo htmlspecialchars($delete_mid); ?>" class="modal-overlay modal-top-layer">
                                <div class="modal-content">
                                    <span class="close-modal" onclick="closeModal('<?php echo htmlspecialchars($delete_mid); ?>')">&times;</span>
                                    <h3 class="admin-table-h3">DELETE <span class="header-span">CONFIRMATION</span></h3>
                                    <form method="post" action="review-sellers.php" class="admin-form">
                                        <?php echo $review_redirect_hidden; ?>
                                        <input type="hidden" name="review_id" value="<?php echo (int)$item['id']; ?>">
                                        
                                        <label>WHY ARE YOU DELETING THIS REVIEW?</label>
                                        <textarea name="deletion_reason" rows="4" placeholder="E.g. Inappropriate content, duplicate, spam..." required maxlength="500"></textarea>
                                        
                                        <p style="margin-top: 1rem; color: var(--primary); font-family: 'Staatliches', sans-serif; font-size: 0.9rem;">* THIS WILL PERMANENTLY REMOVE THE REVIEW FROM THE DATABASE.</p>
                                        
                                        <button type="submit" name="review_delete" class="btn btn-danger" style="width:100%; margin-top: 1rem; font-size: 1.2rem;">PERMANENTLY DELETE</button>
                                    </form>
                                </div>
                            </div>
                            <?php
                            $modals_html[] = ob_get_clean();
                            ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table></div>

            <?php render_intelligent_pagination($page, $total_pages, 'admin-pagination'); ?>

        </div>
    </main>
</section>

<?php foreach ($modals_html as $html) {
    echo $html;
} ?>

<script>
// Live Filtering Logic
document.addEventListener('DOMContentLoaded', () => {
    const reviewForm = document.getElementById('review-filter-form');
    const reviewSearch = document.getElementById('review-search-input');

    if (reviewSearch) {
        let debounceTimer;
        reviewSearch.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                reviewForm.submit();
            }, 600);
        });

        // Keep cursor at end of text after refresh
        if (new URLSearchParams(window.location.search).has('q')) {
            reviewSearch.focus();
            const val = reviewSearch.value;
            reviewSearch.value = '';
            reviewSearch.value = val;
        }
    }
});
</script>
</body>
</html>

