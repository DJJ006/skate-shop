<?php
session_start();
include '../db.php';

define('QNA_TITLE_MAX', 50);
define('QNA_BODY_MAX', 350);

$msg = '';
$msg_type = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}



// --- Submit question ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_qna'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $user_id = (int)$_SESSION['user_id'];
    $username = trim($_SESSION['username'] ?? 'user');

    if ($title === '' || $body === '') {
        $_SESSION['msg'] = 'TITLE AND QUESTION ARE REQUIRED.';
        $_SESSION['msg_type'] = 'error';
    } elseif (mb_strlen($title) > QNA_TITLE_MAX) {
        $_SESSION['msg'] = 'TITLE IS TOO LONG (MAX ' . QNA_TITLE_MAX . ').';
        $_SESSION['msg_type'] = 'error';
    } elseif (mb_strlen($body) > QNA_BODY_MAX) {
        $_SESSION['msg'] = 'QUESTION IS TOO LONG (MAX ' . QNA_BODY_MAX . ').';
        $_SESSION['msg_type'] = 'error';
    } else {
        $stmt = $conn->prepare('INSERT INTO community_qna (user_id, username, title, body, status) VALUES (?, ?, ?, ?, ?)');
        $status = 'pending';
        $stmt->bind_param('issss', $user_id, $username, $title, $body, $status);
        if ($stmt->execute()) {
            $_SESSION['msg'] = 'QUESTION SUBMITTED! IT WILL APPEAR AFTER ADMIN REVIEW.';
            $_SESSION['msg_type'] = 'success';
            $notif = 'Your QnA has been submitted and is awaiting admin review.';
            sendAppNotification($conn, $user_id, $notif);
        } else {
            $_SESSION['msg'] = 'COULD NOT SUBMIT. TRY AGAIN LATER.';
            $_SESSION['msg_type'] = 'error';
        }
    }
    header('Location: qna.php');
    exit();
}

// --- List published ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 6;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mb_substr(trim($_GET['search']), 0, 100) : '';
$sort = isset($_GET['sort']) && $_GET['sort'] === 'oldest' ? 'oldest' : 'newest';

$published = [];
$total_published = 0;
$my_pending = [];
$total_pages = 1;

if (true) {
    $where = "status = 'published'";
    $types = "";
    $params = [];

    if ($search !== '') {
        $where .= " AND (title LIKE ? OR body LIKE ? OR username LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= "sss";
    }
    
    $order = $sort === 'oldest'
        ? 'ORDER BY COALESCE(published_at, created_at) ASC'
        : 'ORDER BY COALESCE(published_at, created_at) DESC';

    $stmt_count = $conn->prepare("SELECT COUNT(*) AS c FROM community_qna WHERE $where");
    if ($types) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $cr = $stmt_count->get_result();
    
    if ($cr) {
        $total_published = (int)$cr->fetch_assoc()['c'];
    }
    $total_pages = max(1, (int)ceil($total_published / $limit));
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    $stmt_data = $conn->prepare("SELECT id, user_id, title, body, admin_answer, username, published_at, created_at FROM community_qna WHERE $where $order LIMIT ? OFFSET ?");
    $types_data = $types . "ii";
    $params_data = $params;
    $params_data[] = $limit;
    $params_data[] = $offset;
    $stmt_data->bind_param($types_data, ...$params_data);
    $stmt_data->execute();
    $pq = $stmt_data->get_result();
    
    if ($pq) {
        while ($r = $pq->fetch_assoc()) {
            $published[] = $r;
        }
    }

}

// 5. Helper to keep params (Matching the-mag.php style)
function get_qna_url($params) {
    $current = $_GET;
    foreach($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return "?" . http_build_query($current);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | Community Q&amp;A</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/shop.css">
    <script src="../assets/script.js" defer></script>
    <script src="../assets/qna.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/user-profile.css">
    <style>
        /* ===== THE MAG — PAGE STYLES (ADAPTED FOR QNA) ===== */
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
        .mag-hero h1 span {
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

        /* ===== INTRO BLOCK ===== */
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

        /* ===== FILTER / SEARCH BAR ===== */
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
        .not-found {
            grid-column: 1 / -1;
            /* Added Flexbox for centering */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            
            text-align: center;
            padding: 5rem 2rem;
            margin: 2rem 0;
        }

        .not-found h3 {
            font-family: 'Staatliches', sans-serif;
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            letter-spacing: 1px;
        }

        .not-found p {
            font-family: 'Inter', sans-serif;
            font-size: 1.5rem;
            color: var(--charcoal);
            text-transform: none;
            letter-spacing: normal;
        }
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

        /* ===== QNA LAYOUT & CARDS ===== */
        .qna-page-layout { 
            display: flex; 
            flex-direction: row; 
            gap: 2.5rem; 
            padding: 0 0 4rem; 
            max-width: 1400px; 
            margin: 0 auto; 
            align-items: flex-start; 
        }
        .qna-main { flex: 1; min-width: 0; }
        .qna-sidebar { flex: 0 0 340px; position: sticky; top: 2rem; }

        .qna-card {
            background: var(--textwhite);
            border: 4px solid var(--charcoal);
            box-shadow: 6px 6px 0px var(--charcoal);
            padding: 1.5rem;
            margin-bottom: 0;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            height: 350px; /* Increased from 340px for better readability */
            overflow: hidden;
            position: relative;
        }
        .qna-card:hover {
            transform: translate(-4px, -4px);
            box-shadow: 10px 10px 0px var(--primary);
            border-color: var(--primary);
        }
        .qna-card h3 { 
            font-family: 'Staatliches', sans-serif; 
            font-size: 1.6rem; 
            letter-spacing: 0.5px; 
            color: var(--charcoal); 
            text-transform: uppercase;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.3rem;
        }
        .qna-card-meta { 
            font-family: 'Staatliches', sans-serif; 
            font-size: 0.95rem; 
            color: var(--primary); 
            margin-bottom: 1rem; 
            letter-spacing: 1.5px;
            border-bottom: 1px solid var(--primary);
            padding-bottom: 0.6rem;
        }
        .qna-card-q { 
            font-family: 'Staatliches', sans-serif; 
            color: var(--charcoal); 
            line-height: 1.5; 
            margin-bottom: 1rem; 
            font-size: 1.1rem; 
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 0 0 auto;
            max-height: 4.8rem;
        }
        .qna-card-a {
            border-left: 4px solid var(--primary);
            padding: 1rem 1.2rem;
            background: #f4f4f4;
            font-family: 'Inter', sans-serif;
            color: #1a1a1a;
            line-height: 1.1;
            font-size: 1rem;
            margin-top: auto;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 6rem;
        }
        .qna-card-a strong { 
            font-family: 'Staatliches', sans-serif; 
            color: var(--charcoal); 
            letter-spacing: 1.5px;
            font-size: 1rem;
            display: block;
            margin-bottom: 0;
        }
        .qna-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 1200px) {
            .qna-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .qna-grid { grid-template-columns: 1fr; }
        }

        /* ===== SIDEBAR / MINE SECTION ===== */
        .qna-mine-wrap { margin-bottom: 3rem; }
        .qna-mine-wrap h4 { 
            font-family: 'Staatliches', sans-serif; 
            font-size: 1.8rem; 
            margin-bottom: 1.2rem; 
            letter-spacing: 2px; 
            color: var(--charcoal);
            border-bottom: 3px solid var(--primary);
            display: inline-block;
        }
        .qna-mine-item {
            border: 3px solid var(--charcoal);
            padding: 1.2rem;
            margin-bottom: 1rem;
            background: #fff;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.1);
        }
        .badge-p { 
            display: inline-block; 
            padding: 4px 12px; 
            font-family: 'Staatliches', sans-serif; 
            font-size: 0.85rem; 
            margin-bottom: 0.8rem; 
            letter-spacing: 1px;
        }
        .badge-pending { background: #ffcc00; color: #000; }
        .badge-rejected { background: var(--primary); color: #fff; }

        .share-clip-btn { 
            width: 100%; 
            font-size: 1.5rem; 
            background: var(--primary); 
            color: white; 
            border: 4px solid var(--charcoal); 
            padding: 1.2rem; 
            font-family: 'Staatliches', sans-serif; 
            cursor: pointer; 
            box-shadow: 6px 6px 0px var(--charcoal); 
            letter-spacing: 1px; 
            transition: all 0.2s;
        }
        .share-clip-btn:hover { 
            background: white; 
            color: var(--charcoal); 
            transform: translate(-3px,-3px); 
            box-shadow: 9px 9px 0px var(--charcoal);
        }

        .login-prompt { 
            text-align: center; 
            background: var(--textwhite); 
            border: 4px solid var(--charcoal); 
            padding: 2.5rem 1.5rem; 
            color: var(--charcoal); 
            box-shadow: 8px 8px 0px var(--primary); 
        }
        .login-prompt h3 { font-family: 'Staatliches', sans-serif; font-size: 2.2rem; margin-bottom: 1rem; }

        /* ===== SIDEBAR INFO BLOCKS ===== */
        .qna-info-block {
            margin-top: 2rem;
            background: var(--textwhite);
            border: 4px solid var(--charcoal);
            padding: 1.8rem;
            box-shadow: 8px 8px 0px var(--charcoal);
        }
        .qna-info-block h4 {
            font-family: 'Staatliches', sans-serif;
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
            letter-spacing: 1px;
            line-height: 1;
            font-weight: 900;
        }
        .qna-info-block p {
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            line-height: 1.5;
            color: #444;
            margin-bottom: 1.2rem;
        }
        .qna-info-block ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .qna-info-block li {
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            line-height: 1.4;
        }
        .qna-info-block li i {
            color: var(--primary);
            font-size: 0.85rem;
            margin-top: 0.2rem;
        }
        .qna-info-link {
            font-family: 'Staatliches', sans-serif;
            color: var(--primary);
            text-decoration: underline;
            font-size: 1.15rem;
            letter-spacing: 1px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .qna-info-link:hover {
            color: var(--charcoal);
            transform: translateX(8px);
            text-decoration: none;
        }

        /* ===== SYSTEM ALERTS ===== */
        .system-alert { 
            padding: 1.2rem; 
            margin-bottom: 2rem; 
            font-weight: bold; 
            text-align: center; 
            border: 3px solid var(--charcoal); 
            font-family: 'Staatliches', sans-serif; 
            font-size: 1.3rem; 
            letter-spacing: 1px; 
            box-shadow: 6px 6px 0px var(--charcoal);
        }
        .system-alert.success { background: #4ADE80; color: #000; }
        .system-alert.error { background: var(--primary); color: white; }

        /* ===== MODAL ===== */
        .reel-modal-overlay {
            display: flex; 
            position: fixed; 
            z-index: 9000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.85); 
            backdrop-filter: blur(6px); 
            align-items: center; 
            justify-content: center; 
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .reel-modal-overlay.active { 
            opacity: 1;
            visibility: visible;
            pointer-events: all;
         }

        .reel-modal-content {
            background: var(--bg-dark); 
            border: 4px solid var(--primary); 
            padding: 2.5rem; 
            width: 95%; 
            max-width: 550px; 
            box-shadow: 8px 8px 0px var(--charcoal); 
            position: relative; 
            color: var(--textwhite); 
            transform: scale(0.8);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .reel-modal-overlay.active .reel-modal-content { transform: scale(1); }
        .reel-modal-content h3 { font-size: 2.5rem; font-family: 'Staatliches', sans-serif; color: var(--primary); margin-bottom: 1.5rem; }
        .reel-modal-content input, .reel-modal-content textarea {
            width: 100%; padding: 1.2rem; margin-bottom: 1rem; border: 3px solid var(--charcoal);
            background: var(--textwhite); color: var(--charcoal); font-family: 'Inter', sans-serif; font-size: 1rem; outline: none;
        }
        .reel-modal-content input:focus, .reel-modal-content textarea:focus { border-color: var(--primary); box-shadow: 4px 4px 0px var(--primary); }
        .reel-modal-close { position: absolute; top: 10px; right: 20px; font-size: 3rem; color: var(--textwhite); cursor: pointer; font-family: 'Staatliches', sans-serif; line-height: 1; transition: var(--transition); }
        .reel-modal-close:hover { color: var(--primary); transform: scale(1.20); }

        /* Modal character counter */
        .modal-char-counter {
            font-family: 'Staatliches', sans-serif;
            font-size: 0.85rem;
            color: #777;
            letter-spacing: 1px;
            text-align: right;
            margin-top: -0.6rem;
            margin-bottom: 0.8rem;
        }
        .modal-char-counter.warning { color: #ffcc00; }
        .modal-char-counter.danger { color: var(--primary); }

        .close-modal {
            position: absolute; 
            top: 10px; 
            right: 20px; 
            font-size: 3rem; 
            color: var(--charcoal); 
            cursor: pointer; 
            font-family: 'Staatliches', sans-serif;
            line-height: 1;
            z-index: 100;
            transition: var(--transition);
        }
        .close-modal:hover {
            color: var(--primary); 
            transform: scale(1.20);
        }

        @media (max-width: 992px) {
            .qna-page-layout { flex-direction: column-reverse; }
            .qna-sidebar { flex: none; width: 100%; position: static; margin-bottom: 3rem; }
            .mag-hero h1 { font-size: 3.5rem; }
            .mag-intro-title { font-size: 3.5rem; }
            .qna-card h3 { font-size: 1.8rem; }
        }

        /* ===== VIEW MODAL OVERLAY (Matching the-mag.php) ===== */
        .view-overlay {
            display: flex; position: fixed; z-index: 6000;
            left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.92); backdrop-filter: blur(8px);
            align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; pointer-events: none;
            transition: all 0.3s ease; padding: 2rem 1rem;
        }
        .view-overlay.active { opacity: 1; visibility: visible; pointer-events: all; }
        .view-shell {
            background: #0d0d0d; border: 4px solid var(--primary);
            width: 100%; max-width: 820px; position: relative;
            box-shadow: 12px 12px 0px rgba(255,45,90,0.3);
            transform: translateY(30px); transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex; flex-direction: column;
            max-height: 90vh;
        }
        .view-overlay.active .view-shell { transform: translateY(0); }
        .view-close {
            position: absolute; top: 12px; right: 20px;
            font-size: 3rem; color: white; cursor: pointer;
            font-family: 'Staatliches', sans-serif; line-height: 1; z-index: 100;
            transition: color 0.2s, transform 0.2s;
            text-shadow: 2px 2px 0px var(--charcoal);
        }
        .view-close:hover { color: var(--primary); transform: scale(1.2); }

        .view-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 3rem;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) #1a1a1a;
        }
        .view-content::-webkit-scrollbar { width: 10px; }
        .view-content::-webkit-scrollbar-track { background: #1a1a1a; }
        .view-content::-webkit-scrollbar-thumb { 
            background: var(--primary); 
            border: 2px solid #1a1a1a;
        }
        .view-content::-webkit-scrollbar-thumb:hover { background: white; }

        .view-body h2 {
            font-family: 'Staatliches', sans-serif; font-size: 3.5rem;
            line-height: 1; letter-spacing: -1px; color: var(--textwhite);
            margin-bottom: 1.5rem; text-transform: uppercase;
            padding-right: 3rem;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        .view-meta {
            font-family: 'Staatliches', sans-serif; font-size: 1rem;
            letter-spacing: 3px; color: var(--primary); margin-bottom: 1.5rem;
            display: flex; gap: 1.5rem; flex-wrap: wrap;
        }
        .view-q-block {
            font-family: 'Inter', sans-serif; font-size: 1.1rem;
            line-height: 1.8; color: #ccc; margin-bottom: 2.5rem;
            background: rgba(255,255,255,0.03); padding: 1.5rem;
            border-left: 4px solid #444;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        .view-a-block {
            border-left: 4px solid var(--primary);
            padding: 2rem;
            background: rgba(225, 29, 72, 0.05);
            font-family: 'Inter', sans-serif;
            color: #fff;
            line-height: 1.7;
            font-size: 1.1rem;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        .view-a-block strong {
            font-family: 'Staatliches', sans-serif;
            color: var(--primary);
            display: block;
            font-size: 1.4rem;
            margin-bottom: 1rem;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="mag-hero noise-bg">
    <div class="container">
        <h1 class="glitch-text">COMMUNITY <span class="text-primary">Q&amp;A</span></h1>
        <p class="mag-hero-sub">ASK / ANSWER / SHOP KNOWLEDGE</p>
    </div>
</div>

<section style="padding-top: 3rem;">
    <div class="container">
        <!-- INTRO BLOCK -->
        <div class="mag-intro-block">
            <div class="mag-intro-tag">FAQ_LIVE</div>
            <h2 class="mag-intro-title">QUESTIONS <span>ANSWERED</span></h2>
            <div class="mag-intro-desc">
                <p><strong>Official answers from the crew.</strong> Browse what others asked about products, shipping, and the site. Logged-in skaters can submit new questions — everything is reviewed before it goes live.</p>
            </div>
        </div>

        <div class="section-header">
            <h3>ALL <span class="header-span">QUESTIONS</span></h3>
            <span class="mag-count-badge"><?php echo $total_published; ?> Q&amp;A PUBLISHED</span>
        </div>

        <?php if ($msg): ?>
            <div class="system-alert <?php echo htmlspecialchars($msg_type); ?>" id="form-alert"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>



        <div class="qna-page-layout">
            <div class="qna-main">

                <!-- FILTER / SEARCH ROW -->
                <div class="mag-filter-row">
                    <!-- SEARCH BY TITLE, CONTENT OR USERNAME -->
                    <form action="qna.php" method="GET" class="mag-filter-controls">
                        <div class="mag-search-section">
                            <div class="mag-search-field">
                                <input type="text" name="search" id="mag-search" placeholder="SEARCH BY TITLE, CONTENT OR USERNAME..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                                <button type="submit" style="display:none;"></button> <!-- Hidden submit for enter key -->
                            </div>
                        </div>
                        <div class="mag-sort-section">
                            <select name="sort" id="mag-sort" onchange="this.form.submit()">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>SORT: NEWEST FIRST</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>SORT: OLDEST FIRST</option>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if (count($published) === 0): ?>
                    <div class="not-found">
                        <?php if ($search !== ''): ?>
                            <h3>NO PUBLISHED Q&amp;A MATCHES YOUR SEARCH.</h3>
                            <p>Try adjusting your search terms or filters.</p>
                        <?php else: ?>
                            <h3>NO PUBLISHED Q&amp;A YET.</h3>
                            <p>Check back soon — or be the first to ask (Login Required).</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="qna-grid">
                        <?php foreach ($published as $p): 
                            // Truncate in PHP for safety, but CSS will handle exact fitting
                            $q_preview = mb_strlen($p['body']) > 200 ? mb_substr($p['body'], 0, 200) : $p['body'];
                            $a_content = $p['admin_answer'] !== null && $p['admin_answer'] !== '' ? $p['admin_answer'] : '—';
                            $a_preview = mb_strlen($a_content) > 300 ? mb_substr($a_content, 0, 300) : $a_content;
                        ?>
                            <article class="qna-card" onclick="openQnaView(<?php echo $p['id']; ?>)">
                                <h3><?php echo htmlspecialchars($p['title']); ?></h3>
                                <div class="qna-card-meta">
                                    ASKED BY @<?php echo htmlspecialchars(strtoupper($p['username'])); ?> &middot; <?php echo strtoupper(date('M d, Y', strtotime($p['published_at'] ?? $p['created_at']))); ?>
                                </div>
                                <div class="qna-card-q"><?php echo nl2br(htmlspecialchars($q_preview)); ?></div>
                                <div class="qna-card-a">
                                    <strong>OFFICIAL ANSWER</strong><br><br>
                                    <?php echo nl2br(htmlspecialchars($a_preview)); ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- PAGINATION -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                            <a href="<?php echo get_qna_url(['page' => $page - 1]); ?>" class="btn btn-outline">&lt; PREV</a>
                        <?php endif; ?>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?php echo get_qna_url(['page' => $i]); ?>" class="btn <?php echo ($page == $i) ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if($page < $total_pages): ?>
                            <a href="<?php echo get_qna_url(['page' => $page + 1]); ?>" class="btn btn-outline">NEXT &gt;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <aside class="qna-sidebar">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button type="button" class="share-clip-btn" id="open-qna-modal"><i class="fas fa-question-circle"></i> ASK A QUESTION</button>
                <?php else: ?>
                    <div class="login-prompt">
                        <h3>WANT TO ASK?</h3>
                        <p style="margin-bottom: 1.5rem;">Log in to submit questions to the shop. Anyone can read published Q&amp;A.</p>
                        <a href="login.php" class="btn btn-primary" style="display:inline-block;padding:1rem 2rem;border:3px solid var(--charcoal);text-decoration:none;background:var(--primary);color:#fff;font-family:'Staatliches',sans-serif;font-size:1.4rem;">LOGIN TO POST</a>
                    </div>
                <?php endif; ?>

                <div class="qna-info-block">
                    <h4>WHAT IS Q&A?</h4>
                    <p>Connect directly with the shop and community. Ask about products, skating, or upcoming drops.</p>
                    <ul>
                        <li><i class="fas fa-check-square"></i> Be respectful and clear.</li>
                        <li><i class="fas fa-check-square"></i> Check if it was asked before.</li>
                        <li><i class="fas fa-check-square"></i> Admins review all posts.</li>
                        <li><i class="fas fa-check-square"></i> Responses may take 24-48h.</li>
                    </ul>
                </div>

                <div class="qna-info-block" style="background: var(--bg-light); border-style: dashed;">
                    <h4>COMMUNITY GEAR</h4>
                    <p>Have a setup you want to show off? Head over to the Marketplace or share a Reel!</p>
                    <a href="reels.php" class="qna-info-link">GO TO REELS &rarr;</a>
                </div>
            </aside>
        </div>


    </div>
</section>

<!-- QNA VIEW MODAL -->
<div class="view-overlay" id="qnaViewOverlay">
    <div class="view-shell">
        <span class="view-close" onclick="closeQnaView()">&times;</span>
        <div class="view-content" id="qnaViewContent">
            <!-- Populated via JS -->
        </div>
    </div>
</div>

<?php if (isset($_SESSION['user_id'])): ?>
<div class="reel-modal-overlay" id="qnaUploadModal">
    <div class="reel-modal-content">
        <span class="reel-modal-close" id="close-qna-modal">&times;</span>
        <h3 style="font-family: 'Staatliches', sans-serif; letter-spacing: -4px; font-size: 3rem; font-style: italic; margin: 0; padding-bottom: 20px; padding-top: 10px; color: var(--textwhite); ">
            ASK THE SHOP
        </h3>
            <form action="qna.php" method="post">
        <input type="text" name="title" id="qna-modal-title" placeholder="QUESTION TITLE" maxlength="<?php echo QNA_TITLE_MAX; ?>" required>
        <div class="modal-char-counter" id="qna-title-counter"><?php echo QNA_TITLE_MAX; ?> characters remaining</div>

        <textarea name="body" id="qna-modal-body" placeholder="YOUR QUESTION (BE SPECIFIC)" rows="6" maxlength="<?php echo QNA_BODY_MAX; ?>" required style="resize:vertical;"></textarea>
        <div class="modal-char-counter" id="qna-body-counter"><?php echo QNA_BODY_MAX; ?> characters remaining</div>

        <button type="submit" name="submit_qna" class="btn btn-primary" style="width:100%; font-size:1.2rem; padding:1rem; font-family:'Staatliches',sans-serif; cursor:pointer; border:3px solid var(--charcoal); transition: var(--transition);" >SUBMIT FOR REVIEW</button>
    </form>
        <p style="margin-top: 1rem; font-size: 0.8rem; opacity: 0.75; text-align: center;">No hate, spam, or unrelated content. The team reads every submission.</p>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>

<script>
// QNA Data for Modal
const QNA_POSTS = <?php echo json_encode($published, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function openQnaView(id) {
    const post = QNA_POSTS.find(p => p.id == id);
    if (!post) return;

    const overlay = document.getElementById('qnaViewOverlay');
    const content = document.getElementById('qnaViewContent');
    
    const date = new Date(post.published_at || post.created_at);
    const dateStr = date.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'}).toUpperCase();

    content.innerHTML = `
        <div class="view-body">
            <div class="view-meta">
                <span>ASKED BY <a href="javascript:void(0)" onclick="openUserProfile('${encodeURIComponent(post.username)}')" style="color:var(--primary); text-decoration:underline; font-weight:bold;">@${escHtml(post.username.toUpperCase())}</a></span>
                <span>${dateStr}</span>
            </div>
            <h2>${escHtml(post.title)}</h2>
            <div class="view-q-block">
                ${escHtml(post.body).replace(/\n/g, '<br>')}
            </div>
            <div class="view-a-block">
                <strong>OFFICIAL ANSWER</strong>
                ${(post.admin_answer ? escHtml(post.admin_answer).replace(/\n/g, '<br>') : '—')}
            </div>
        </div>
    `;

    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeQnaView() {
    document.getElementById('qnaViewOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

// Close on escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeQnaView();
    }
});

// Close on outside click
document.getElementById('qnaViewOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeQnaView();
});

// Live Search Debounce (Matching the-mag.php)
const qnaSearch = document.getElementById('mag-search');
if (qnaSearch) {
    let debounceTimer;
    qnaSearch.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            qnaSearch.form.submit();
        }, 600);
    });

    // Restore focus and cursor position after reload if searching
    if (new URLSearchParams(window.location.search).has('search')) {
        qnaSearch.focus();
        const val = qnaSearch.value;
        qnaSearch.value = '';
        qnaSearch.value = val;
    }
}
</script>

<!-- User Profile Modal -->
<div id="userProfileModal" class="reel-modal-overlay">
    <div class="modal-content seller-profile-modal-shell" id="userProfileContent" style="padding: 30px; width: 90%; max-width: 1100px; max-height: 90vh; overflow-y: auto;">
    </div>
</div>

<script>
// --- User Profile AJAX Modal Logic (Matching reels.php) ---
function openUserProfile(username) {
    const modal = document.getElementById('userProfileModal');
    const contentDiv = document.getElementById('userProfileContent');
    
    // Show loading state
    contentDiv.innerHTML = '<span class="close-modal" onclick="closeSellerModal()">&times;</span><div class="user-profile-loading"><h2 class="glitch-text" style="text-align:center; padding: 50px;">LOADING RECORD...</h2></div>';
    modal.classList.add('active');

    // Fetch the HTML fragment from user-profile.php
    fetch(`user-profile.php?username=${username}`)
        .then(response => response.text())
        .then(html => {
            contentDiv.innerHTML = html;
        })
        .catch(err => {
            contentDiv.innerHTML = '<span class="close-modal" onclick="closeSellerModal()">&times;</span><h3 style="color:#ff4b2b; text-align:center; padding: 50px;">FAILED TO LOAD USER DATA.</h3>';
        });
}

function closeSellerModal() {
    document.getElementById('userProfileModal').classList.remove('active');
}

// Close user profile modal if clicked outside
document.getElementById('userProfileModal').addEventListener('click', (e) => { 
    if (e.target === document.getElementById('userProfileModal')) {
        closeSellerModal();
    }
});
</script>

</body>
</html>
