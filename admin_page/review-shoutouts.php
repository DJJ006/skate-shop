<?php
require_once 'admin_auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php';



$shoutout_redirect_allowed = ['all', 'pending', 'published', 'rejected'];

function shoutout_admin_redirect_url(): string {
    global $shoutout_redirect_allowed;
    $st = $_POST['redirect_status'] ?? 'all';
    if (!in_array($st, $shoutout_redirect_allowed, true)) {
        $st = 'all';
    }
    $q = trim($_POST['redirect_q'] ?? '');
    $sort = $_POST['redirect_sort'] ?? 'newest';
    
    $params = ['status' => $st, 'sort' => $sort];
    if ($q !== '') {
        $params['q'] = $q;
    }
    return 'review-shoutouts.php?' . http_build_query($params);
}

function shoutout_notify_user(mysqli $conn, int $user_id, string $message): void {
    sendAppNotification($conn, $user_id, $message);
}

// --- POST: publish (pending -> published) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shoutout_publish'])) {
    $id = (int)$_POST['shoutout_id'];
    $body = trim($_POST['body'] ?? '');

    if ($id <= 0 || $body === '') {
        $_SESSION['msg'] = 'ADMIN ANSWER IS REQUIRED TO PUBLISH.';
        $_SESSION['msg_type'] = 'error';
    } else {
        $info = $conn->prepare('SELECT user_id, title, status FROM community_shoutouts WHERE id = ?');
        $info->bind_param('i', $id);
        $info->execute();
        $row = $info->get_result()->fetch_assoc();

        if (!$row || $row['status'] !== 'pending') {
            $_SESSION['msg'] = 'INVALID ITEM OR NOT PENDING.';
            $_SESSION['msg_type'] = 'error';
        } else {
            $upd = $conn->prepare("UPDATE community_shoutouts SET status = 'published', published_at = NOW() WHERE id = ? AND status = 'pending'");
            $upd->bind_param('si', $body, $id);
            if ($upd->execute() && $upd->affected_rows > 0) {
                $title = $row['title'];
                $uid = (int)$row['user_id'];
                $msg = 'Your SHOUTOUT "' . $title . '" has been published with an official answer.';
                shoutout_notify_user($conn, $uid, $msg);
                $_SESSION['msg'] = 'SHOUTOUT PUBLISHED & USER NOTIFIED.';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = 'COULD NOT PUBLISH (CHECK STATUS).';
                $_SESSION['msg_type'] = 'error';
            }
        }
    }
    header('Location: ' . shoutout_admin_redirect_url());
    exit();
}

// --- POST: reject ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shoutout_reject_final'])) {
    $id = (int)$_POST['shoutout_id'];
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($id <= 0 || $reason === '') {
        $_SESSION['msg'] = 'REJECTION REASON IS REQUIRED.';
        $_SESSION['msg_type'] = 'error';
    } else {
        $info = $conn->prepare('SELECT user_id, title, status FROM community_shoutouts WHERE id = ?');
        $info->bind_param('i', $id);
        $info->execute();
        $row = $info->get_result()->fetch_assoc();

        if (!$row || $row['status'] !== 'pending') {
            $_SESSION['msg'] = 'INVALID ITEM OR NOT PENDING.';
            $_SESSION['msg_type'] = 'error';
        } else {
            $del = $conn->prepare('DELETE FROM community_shoutouts WHERE id = ? AND status = ?');
            $st = 'pending';
            $del->bind_param('is', $id, $st);
            if ($del->execute() && $del->affected_rows > 0) {
                $msg = 'Your SHOUTOUT "' . $row['title'] . '" was rejected by an administrator. Reason: ' . $reason;
                shoutout_notify_user($conn, (int)$row['user_id'], $msg);
                $_SESSION['msg'] = 'SHOUTOUT REJECTED & DELETED. CLIENT NOTIFIED.';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = 'COULD NOT REJECT (DELETE FAILED).';
                $_SESSION['msg_type'] = 'error';
            }
        }
    }
    header('Location: ' . shoutout_admin_redirect_url());
    exit();
}

// --- POST: save admin answer on published ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shoutout_save_answer'])) {
    $id = (int)$_POST['shoutout_id'];
    $body = trim($_POST['body'] ?? '');

    if ($id <= 0 || $body === '') {
        $_SESSION['msg'] = 'ANSWER CANNOT BE EMPTY.';
        $_SESSION['msg_type'] = 'error';
    } else {
        $info = $conn->prepare('SELECT user_id, title, status FROM community_shoutouts WHERE id = ?');
        $info->bind_param('i', $id);
        $info->execute();
        $row = $info->get_result()->fetch_assoc();

        if (!$row || $row['status'] !== 'published') {
            $_SESSION['msg'] = 'INVALID ITEM OR NOT PUBLISHED.';
            $_SESSION['msg_type'] = 'error';
        } else {
            $upd = $conn->prepare('UPDATE community_shoutouts SET  WHERE id = ? AND status = ?');
            $st = 'published';
            $upd->bind_param('sis', $body, $id, $st);
            if ($upd->execute() && $upd->affected_rows > 0) {
                $msg = 'The official answer to your SHOUTOUT "' . $row['title'] . '" was updated by an administrator.';
                shoutout_notify_user($conn, (int)$row['user_id'], $msg);
                $_SESSION['msg'] = 'ANSWER UPDATED & USER NOTIFIED.';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = 'NO CHANGES SAVED.';
                $_SESSION['msg_type'] = 'error';
            }
        }
    }
    header('Location: ' . shoutout_admin_redirect_url());
    exit();
}

// --- POST: delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shoutout_delete'])) {
    $id = (int)$_POST['shoutout_id'];
    $reason = trim($_POST['deletion_reason'] ?? '');

    if ($id <= 0 || $reason === '') {
        $_SESSION['msg'] = 'DELETION REASON IS REQUIRED.';
        $_SESSION['msg_type'] = 'error';
    } else {
        // Fetch info for notification before delete
        $info = $conn->prepare('SELECT user_id, title FROM community_shoutouts WHERE id = ?');
        $info->bind_param('i', $id);
        $info->execute();
        $row = $info->get_result()->fetch_assoc();

        if ($row) {
            $del = $conn->prepare('DELETE FROM community_shoutouts WHERE id = ?');
            $del->bind_param('i', $id);
            if ($del->execute() && $del->affected_rows > 0) {
                // Send notification
                $msg = 'Your SHOUTOUT "' . $row['title'] . '" was removed by an administrator. Reason: ' . $reason;
                shoutout_notify_user($conn, (int)$row['user_id'], $msg);
                
                $_SESSION['msg'] = 'SHOUTOUT DELETED & USER NOTIFIED.';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = 'DELETE FAILED.';
                $_SESSION['msg_type'] = 'error';
            }
        } else {
            $_SESSION['msg'] = 'SHOUTOUT NOT FOUND.';
            $_SESSION['msg_type'] = 'error';
        }
    }
    header('Location: ' . shoutout_admin_redirect_url());
    exit();
}

// --- List query ---
$status_filter = isset($_GET['status']) && in_array($_GET['status'], $shoutout_redirect_allowed, true) ? $_GET['status'] : 'all';
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort_filter = $_GET['sort'] ?? 'newest';

$where = ['1=1'];
if ($status_filter !== 'all') {
    $sf = $conn->real_escape_string($status_filter);
    $where[] = "q.status = '$sf'";
}
if ($search_q !== '') {
    $s = $conn->real_escape_string($search_q);
    $where[] = "(q.id = '" . (int)$s . "' OR q.title LIKE '%$s%' OR q.body LIKE '%$s%' OR q.username LIKE '%$s%')";
}
$where_sql = implode(' AND ', $where);

// Sorting Logic
$order_sql = 'ORDER BY q.created_at DESC';
if ($sort_filter === 'oldest') {
    $order_sql = 'ORDER BY q.created_at ASC';
} elseif ($sort_filter === 'pending') {
    $order_sql = "ORDER BY (q.status = 'pending') DESC, q.created_at DESC";
} elseif ($sort_filter === 'published') {
    $order_sql = "ORDER BY (q.status = 'published') DESC, q.created_at DESC";
}

// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_res = @$conn->query("SELECT COUNT(*) as total FROM community_shoutouts q WHERE $where_sql");
if ($count_res) {
    $total_records = $count_res->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);
} else {
    $total_pages = 0;
}

$list_sql = "SELECT q.* FROM community_shoutouts q WHERE $where_sql $order_sql LIMIT $limit OFFSET $offset";
$list_result = @$conn->query($list_sql);
$rows = [];
$table_missing = false;
if ($list_result === false) {
    $table_missing = true;
} else {
    while ($r = $list_result->fetch_assoc()) {
        $rows[] = $r;
    }
}

$modals_html = [];

$shoutout_redirect_hidden = '<input type="hidden" name="redirect_status" value="' . htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="redirect_q" value="' . htmlspecialchars($search_q, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="redirect_sort" value="' . htmlspecialchars($sort_filter, ENT_QUOTES, 'UTF-8') . '">';

function shoutout_status_badge_class(string $status): string {
    if ($status === 'pending') {
        // Now orange (was yellow)
        return 'listing-status-badge status-received';
    }
    if ($status === 'published') {
        // Now green (was orange)
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
    <title>SkateShop | REVIEW SHOUTOUT</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <script src="../assets/admin-script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .qna-modal-body-preview { 
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
    </style>
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
                <h2 class="glitch-text-admin">REVIEW <span class="text-primary">SHOUTOUT</span></h2>
                <p class="admin-text-shop">COMMUNITY QUESTIONS & OFFICIAL ANSWERS</p>
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

        <?php if ($table_missing): ?>
            <div class="grainy-card card-padding" style="padding: 20px;">
                <p class="admin-text-shop" style="font-weight:700;">The <code>community_shoutouts</code> table is missing. Run the SQL in <code>sql/community_shoutouts.sql</code> on your database, then reload this page.</p>
            </div>
        <?php else: ?>

        

        <div class="grainy-card card-padding" style="padding: 20px;">
            <h3 class="admin-table-h3">SHOUTOUT <span class="header-span">SUBMISSIONS</span></h3>

            <div class="grainy-card filter-bar" style="margin-bottom: 25px;">
            <form method="get" action="review-shoutouts.php" class="search-filter-form" id="qna-filter-form">
                <div class="filter-group search-box">
                    <label>SEARCH SHOUTOUT</label>
                    <input type="text" name="q" id="qna-search-input" placeholder="ID, title, body, username..." value="<?php echo htmlspecialchars($search_q); ?>" autocomplete="off">
                </div>

                <div class="filter-group" style="flex: 1;">
                    <label>STATUS</label>
                    <select name="status" id="qna-status-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>ALL STATUSES</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>PENDING</option>
                        <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>PUBLISHED</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>REJECTED</option>
                    </select>
                </div>

                <div class="filter-group" style="flex: 1;">
                    <label>SORT BY</label>
                    <select name="sort" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sort_filter === 'newest' ? 'selected' : ''; ?>>NEWEST FIRST</option>
                        <option value="oldest" <?php echo $sort_filter === 'oldest' ? 'selected' : ''; ?>>OLDEST FIRST</option>
                        <option value="pending" <?php echo $sort_filter === 'pending' ? 'selected' : ''; ?>>PENDING FIRST</option>
                        <option value="published" <?php echo $sort_filter === 'published' ? 'selected' : ''; ?>>PUBLISHED FIRST</option>
                    </select>
                </div>

                <div class="filter-actions" style="margin-top:22px;">
                    <button type="submit" class="btn-filter">SEARCH</button>
                    <?php if ($search_q !== '' || $status_filter !== 'all' || $sort_filter !== 'newest'): ?>
                        <a href="review-shoutouts.php" class="btn-reset">RESET</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>USER</th>
                        <th>TITLE</th>
                        <th>STATUS</th>
                        <th>SUBMITTED</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="6" style="text-align:center;font-weight:700;font-style:italic;">NO SHOUTOUT ROWS MATCH THIS FILTER.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $item):
                            $mid = 'qna-review-' . (int)$item['id'];
                            $reject_mid = 'qna-reject-' . (int)$item['id'];
                            $delete_mid = 'qna-delete-' . (int)$item['id'];
                            $submitted = date('M j, Y @ H:i', strtotime($item['created_at']));
                            $title_short = mb_strlen($item['title']) > 48 ? mb_substr($item['title'], 0, 45) . '…' : $item['title'];
                            $status = $item['status'];
                            ?>
                             <tr class="<?php echo ($status === 'pending') ? 'pending-row' : ''; ?>">
                                <td class="td-id">#<?php echo (int)$item['id']; ?></td>
                                <td><strong>@<?php echo htmlspecialchars($item['username']); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($title_short); ?></strong></td>
                                <td><span class="<?php echo shoutout_status_badge_class($status); ?>"><?php echo strtoupper(htmlspecialchars($status)); ?></span></td>
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
                                    <h3 class="admin-table-h3 top-title">SHOUTOUT <span class="header-span">DETAIL</span></h3>
                                    <p style="font-family:'Staatliches',sans-serif;font-size:1.1rem;color:#666;margin-bottom:1rem;">
                                        SUBMITTED <?php echo htmlspecialchars($submitted); ?> &mdash;
                                        <span class="<?php echo shoutout_status_badge_class($status); ?>"><?php echo strtoupper(htmlspecialchars($status)); ?></span>
                                    </p>
                                    <div class="detail-row"><span class="detail-label">USER:</span> <span>@<?php echo htmlspecialchars($item['username']); ?> (ID <?php echo (int)$item['user_id']; ?>)</span></div>
                                    <div class="detail-row" style="margin-top:0.5rem;"><span class="detail-label">TITLE:</span> <span><?php echo htmlspecialchars($item['title']); ?></span></div>
                                    <div style="margin-top:1rem;"><strong>QUESTION</strong></div>
                                    <div class="qna-modal-body-preview"><?php echo htmlspecialchars($item['body']); ?></div>

                                    <?php if ($status === 'rejected' && !empty($item['rejection_reason'])): ?>
                                        <div class="modal-description-box" style="margin-top:1rem;border-color:#f87171;">
                                            <strong>Rejection reason:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($item['rejection_reason'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                     <?php if ($status === 'pending'): ?>
                                        <form method="post" action="review-shoutouts.php" class="admin-form" style="margin-top:1.5rem;">
                                            <?php echo $shoutout_redirect_hidden; ?>
                                            <input type="hidden" name="shoutout_id" value="<?php echo (int)$item['id']; ?>">
                                            
                                            <label>OFFICIAL ADMIN ANSWER (REQUIRED TO PUBLISH)</label>
                                            <textarea name="body" rows="5" required placeholder="Write the shop’s official response..."></textarea>
                                            
                                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-top: 1rem;">
                                                <button type="submit" name="shoutout_publish" class="btn btn-primary" style="font-size: 1.2rem;">ACCEPT &amp; PUBLISH</button>
                                                <button type="button" class="btn btn-danger" onclick="openModal('<?php echo htmlspecialchars($reject_mid); ?>')" style="font-size: 1.2rem;">
                                                    REJECT
                                                </button>
                                            </div>
                                        </form>
                                        
                                        
                                    <?php elseif ($status === 'published'): ?>
                                        <form method="post" action="review-shoutouts.php" class="admin-form" style="margin-top:1.5rem;">
                                            <?php echo $shoutout_redirect_hidden; ?>
                                            <input type="hidden" name="shoutout_id" value="<?php echo (int)$item['id']; ?>">
                                            
                                            <label>EDIT OFFICIAL ANSWER</label>
                                            <textarea name="body" rows="5" required><?php echo htmlspecialchars($item['body'] ?? ''); ?></textarea>
                                            
                                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-top: 1rem;">
                                                <button type="submit" name="shoutout_save_answer" class="btn btn-primary" style="font-size: 1.2rem;">SAVE CHANGES</button>
                                                <button type="button" class="btn btn-danger" onclick="openModal('<?php echo htmlspecialchars($delete_mid); ?>')" style="font-size: 1.2rem;">
                                                    <span class="material-icons" style="vertical-align: middle;">delete</span>
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
                            <?php if ($status === 'pending'): ?>
                                <div id="<?php echo htmlspecialchars($reject_mid); ?>" class="modal-overlay modal-top-layer">
                                    <div class="modal-content">
                                        <span class="close-modal" onclick="closeModal('<?php echo htmlspecialchars($reject_mid); ?>')">&times;</span>
                                        <h3 class="admin-table-h3">REJECTION <span class="header-span">REASON</span></h3>
                                        <form method="post" action="review-shoutouts.php" class="admin-form">
                                            <?php echo $shoutout_redirect_hidden; ?>
                                            <input type="hidden" name="shoutout_id" value="<?php echo (int)$item['id']; ?>">
                                            
                                            <label>WHY ARE YOU REJECTING THIS SHOUTOUT?</label>
                                            <textarea name="rejection_reason" rows="4" placeholder="E.g. Off-topic, spam, unclear..." required></textarea>
                                            
                                            <button type="submit" name="shoutout_reject_final" class="btn btn-danger" style="width:100%; margin-top: 1rem; font-size: 1.2rem;">SEND REJECTION</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div id="<?php echo htmlspecialchars($delete_mid); ?>" class="modal-overlay modal-top-layer">
                                <div class="modal-content">
                                    <span class="close-modal" onclick="closeModal('<?php echo htmlspecialchars($delete_mid); ?>')">&times;</span>
                                    <h3 class="admin-table-h3">DELETE <span class="header-span">CONFIRMATION</span></h3>
                                    <form method="post" action="review-shoutouts.php" class="admin-form">
                                        <?php echo $shoutout_redirect_hidden; ?>
                                        <input type="hidden" name="shoutout_id" value="<?php echo (int)$item['id']; ?>">
                                        
                                        <label>WHY ARE YOU DELETING THIS SHOUTOUT?</label>
                                        <textarea name="deletion_reason" rows="4" placeholder="E.g. Inappropriate content, duplicate, spam..." required></textarea>
                                        
                                        <p style="margin-top: 1rem; color: var(--primary); font-family: 'Staatliches', sans-serif; font-size: 0.9rem;">* THIS WILL PERMANENTLY REMOVE THE SHOUTOUT FROM THE DATABASE.</p>
                                        
                                        <button type="submit" name="shoutout_delete" class="btn btn-danger" style="width:100%; margin-top: 1rem; font-size: 1.2rem;">PERMANENTLY DELETE</button>
                                    </form>
                                </div>
                            </div>
                            <?php
                            $modals_html[] = ob_get_clean();
                            ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!$table_missing && $total_pages > 0): ?>
            <div class="admin-pagination">
                <?php
                $query_string = $_GET;
                if ($page > 1) {
                    $query_string['page'] = $page - 1;
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline">&laquo; PREV</a>';
                }
                for ($i = 1; $i <= $total_pages; $i++) {
                    $query_string['page'] = $i;
                    $active = ($i === $page) ? 'active' : '';
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline ' . $active . '">' . $i . '</a>';
                }
                if ($page < $total_pages) {
                    $query_string['page'] = $page + 1;
                    echo '<a href="?' . http_build_query($query_string) . '" class="btn btn-outline">NEXT &raquo;</a>';
                }
                ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </main>
</section>

<?php foreach ($modals_html as $html) {
    echo $html;
} ?>

<script>
// Live Filtering Logic
document.addEventListener('DOMContentLoaded', () => {
    const qnaForm = document.getElementById('qna-filter-form');
    const qnaSearch = document.getElementById('qna-search-input');
    const qnaStatus = document.getElementById('qna-status-select');

    if (qnaSearch) {
        let debounceTimer;
        qnaSearch.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                qnaForm.submit();
            }, 600);
        });

        // Keep cursor at end of text after refresh
        if (new URLSearchParams(window.location.search).has('q')) {
            qnaSearch.focus();
            const val = qnaSearch.value;
            qnaSearch.value = '';
            qnaSearch.value = val;
        }
    }
});
</script>
</body>
</html>


