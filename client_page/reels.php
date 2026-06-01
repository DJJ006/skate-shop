<?php
session_start();
include '../db.php';

$msg = '';
$msg_type = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

function parse_video_url($url) {
    $embed_url = '';
    $platform = '';
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        $platform = 'YouTube';
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $match);
        if (isset($match[1])) $embed_url = 'https://www.youtube.com/embed/' . $match[1];
    } elseif (strpos($url, 'vimeo.com') !== false) {
        $platform = 'Vimeo';
        preg_match('/vimeo\.com\/([0-9]+)/i', $url, $match);
        if (isset($match[1])) $embed_url = 'https://player.vimeo.com/video/' . $match[1];
    }
    return ['platform' => $platform, 'embed_url' => $embed_url];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reel'])) {
    if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
    $title = $conn->real_escape_string(trim($_POST['title']));
    $video_url = $conn->real_escape_string(trim($_POST['video_url']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $user_id = (int)$_SESSION['user_id'];
    $username = $conn->real_escape_string($_SESSION['username']);
    $parsed = parse_video_url($video_url);

    if (empty($title) || empty($video_url)) {
        $_SESSION['msg'] = "TITLE AND URL ARE REQUIRED.";
        $_SESSION['msg_type'] = "error";
    } elseif (mb_strlen($title) > 35) {
        $_SESSION['msg'] = "TITLE IS TOO LONG (MAX 35).";
        $_SESSION['msg_type'] = "error";
    } elseif (mb_strlen($description) > 100) {
        $_SESSION['msg'] = "CAPTION IS TOO LONG (MAX 100).";
        $_SESSION['msg_type'] = "error";
    } elseif (empty($parsed['embed_url'])) {
        $_SESSION['msg'] = "INVALID OR UNSUPPORTED VIDEO URL.";
        $_SESSION['msg_type'] = "error";
    } else {
        $embed_url = $conn->real_escape_string($parsed['embed_url']);
        $platform = $conn->real_escape_string($parsed['platform']);
        $stmt = $conn->prepare("INSERT INTO reels (user_id, username, video_url, embed_url, platform, title, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $user_id, $username, $video_url, $embed_url, $platform, $title, $description);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "CLIP SUBMITTED! PENDING ADMIN APPROVAL.";
            $_SESSION['msg_type'] = "success";
            $notif_msg = "Your reel \"" . $title . "\" will be checked by admin soon.";
            sendAppNotification($conn, $user_id, $notif_msg);
        } else {
            $_SESSION['msg'] = "ERROR ADDING CLIP: " . $conn->error;
            $_SESSION['msg_type'] = "error";
        }
    }
    header("Location: reels.php");
    exit();
}

// Fetch approved reels with like counts
$current_user_id = $_SESSION['user_id'] ?? 0;
$reels_query = "SELECT r.*, 
    (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.id) as like_count,
    (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.id AND user_id = $current_user_id) as user_liked,
    (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.id) as comment_count
    FROM reels r WHERE r.is_approved = 1 ORDER BY r.created_at DESC";
$reels_result = $conn->query($reels_query);
$reels = [];
while ($row = $reels_result->fetch_assoc()) $reels[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | The Reels</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <script src="../assets/script.js" defer></script>
    <script src="../assets/reels.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/user-profile.css">
    <style>
        .reels-page-layout { display: flex; flex-direction: row; gap: 2rem; padding: 4rem 0; max-width: 1400px; margin: 0 auto; align-items: flex-start; }
        .reels-sidebar { flex: 0 0 350px; position: sticky; top: 2rem; }
        .reels-main-content { flex: 1; min-width: 0; }
        .monitor-screen iframe { position: relative; z-index: 10; width: 100%; height: 100%; border: 0; }
        .platform-badge { position: absolute; top: 1rem; right: 1rem; background: var(--primary); color: white; padding: 4px 8px; font-family: 'Staatliches', sans-serif; letter-spacing: 1px; border: 2px solid #000; transform: skew(-5deg); }
        .footer-desc { margin-top: 1rem; font-family: 'Inter', sans-serif; font-size: 1rem; color: #ccc; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 1rem; }
        .system-alert { padding: 1rem; margin-bottom: 1.5rem; font-weight: bold; text-align: center; border: 2px solid; font-family: 'Staatliches', sans-serif; font-size: 1.2rem; letter-spacing: 1px; transition: opacity 0.5s ease; }
        .system-alert.success { background: #4ADE80; color: #000; border-color: #000; }
        .system-alert.error { background: var(--primary); color: white; border-color: #000; }
        .fade-out { opacity: 0; }


        .mag-hero {
            text-align: center;
            padding: 4rem 1rem 2.5rem;
            background-color: var(--charcoal);
            background-image: radial-gradient(var(--textwhite) 1px, transparent 1px);
            background-size: 20px 20px;
            position: relative;
            overflow: hidden;
        }

        .mag-hero h1 {
            font-size: 5rem;
            color: var(--textwhite);
            text-shadow: 4px 4px 0px var(--primary);
            margin-bottom: 0.5rem;
        }

        .mag-hero h1 span{
            font-size: 5rem;
            color: var(--textwhite);
            text-shadow: 4px 4px 0px var(--primary);
            margin-bottom: 0.5rem;
        }

        .mag-hero-sub {
            font-family: 'Staatliches', sans-serif;
            font-size: 1.4rem;
            letter-spacing: 4px;
            color: var(--textwhite);
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

        /* Upload modal */
        .reel-modal-overlay { 
            display: flex; 
            position: fixed; 
            z-index: 3000; 
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
        .reel-modal-overlay.active .reel-modal-content {
            transform: scale(1);
        }
        .reel-modal-content h3 { font-size: 2.5rem; font-family: 'Staatliches', sans-serif; color: var(--primary); margin-bottom: 1.5rem; }
        .reel-modal-content input, .reel-modal-content textarea { width: 100%; padding: 1rem; margin-bottom: 1rem; border: 2px solid var(--charcoal); background: var(--textwhite); color: var(--charcoal); font-family: 'Inter', sans-serif; font-size: 1rem; outline: none; }
        .reel-modal-content input:focus, .reel-modal-content textarea:focus { border-color: var(--primary); box-shadow: 4px 4px 0px var(--primary); }
        .reel-modal-close { position: absolute; top: 10px; right: 20px; font-size: 3rem; color: var(--textwhite); cursor: pointer; font-family: 'Staatliches', sans-serif; line-height: 1; transition: var(--transition); }
        .reel-modal-close:hover { color: var(--primary); transform: scale(1.20); }

        /* Sidebar button */
        .share-clip-btn { width: 100%; font-size: 1.8rem; background: var(--primary); color: white; border: 4px solid var(--charcoal); padding: 1.2rem; font-family: 'Staatliches', sans-serif; cursor: pointer; transition: all 0.2s; box-shadow: 6px 6px 0px var(--charcoal); letter-spacing: 1px; }
        .share-clip-btn:hover { background: white; color: var(--charcoal); transform: translate(-3px, -3px); box-shadow: 9px 9px 0px var(--charcoal); }
        .login-prompt { text-align: center; background: var(--textwhite); border: 4px solid var(--charcoal); padding: 2rem; color: var(--charcoal); box-shadow: 6px 6px 0px var(--primary); }
        .login-prompt h3 { font-family: 'Staatliches', sans-serif; font-size: 2rem; margin-bottom: 1rem; }

        /* Like & comment bar */
        .reel-interact-bar { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; background: #222; border-top: 2px solid #444; }
        .like-btn { display: flex; align-items: center; gap: 0.5rem; background: none; border: 2px solid #555; color: #ccc; padding: 0.5rem 1rem; font-family: 'Staatliches', sans-serif; font-size: 1.1rem; cursor: pointer; transition: var(--transition); letter-spacing: 1px; }
        .like-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
        .like-btn.liked { background: var(--primary); color: white; border-color: var(--primary); }
        .like-btn.liked:hover { background: #c81640; transform: translateY(-2px); }
        .toggle-comments-btn { background: none; border: 2px solid #555; color: #ccc; padding: 0.5rem 1rem; font-family: 'Staatliches', sans-serif; font-size: 1.1rem; cursor: pointer; transition: var(--transition); letter-spacing: 1px; }
        .toggle-comments-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }

        /* Comments section */
        .comments-section { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; background: #1a1a1a; border-top: 2px solid #333; }
        .comments-section.open { max-height: 400px; overflow-y: auto; padding: 1rem 1.5rem; }
        .comment-item { border-bottom: 1px solid #333; padding: 0.8rem 0; }
        .comment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; }
        .comment-header strong { color: var(--primary); font-family: 'Staatliches', sans-serif; font-size: 1.1rem; }
        .comment-date { color: #666; font-size: 0.8rem; }
        .comment-text { color: #ccc; font-family: 'Inter', sans-serif; font-size: 0.95rem; margin: 0; }
        .comment-text.editing { display: flex; gap: 0.5rem; align-items: center; }
        .comment-actions { display: flex; gap: 0.5rem; margin-top: 0.4rem; }
        .comment-edit-btn, .comment-delete-btn { background: none; border: 1px solid #555; color: #999; padding: 2px 8px; font-family: 'Staatliches', sans-serif; font-size: 0.8rem; cursor: pointer; transition: var(--transition); }
        .comment-edit-btn:hover { color: var(--primary); border-color: var(--primary); transform: translateY(-1px); }
        .comment-delete-btn:hover { color: #ff4444; border-color: #ff4444; transform: translateY(-1px); }
        .comment-form { display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; }
        .comment-form-row { display: flex; gap: 0.5rem; width: 100%; }
        .comment-form input { flex: 1; padding: 0.7rem; border: 2px solid #444; background: #2a2a2a; color: white; font-family: 'Inter', sans-serif; font-size: 0.9rem; outline: none; transition: var(--transition); }
        .comment-form input:focus { border-color: var(--primary); box-shadow: 0 0 0 1px var(--primary); }
        .comment-form button { background: var(--primary); color: white; border: 2px solid var(--charcoal); padding: 0.7rem 1rem; font-family: 'Staatliches', sans-serif; cursor: pointer; font-size: 1rem; letter-spacing: 1px; transition: var(--transition); }
        .comment-form button:hover { background: white; color: var(--charcoal); transform: translateY(-2px); box-shadow: var(--boxshadow); }
        .comment-edit-input { flex: 1; padding: 0.5rem; border: 2px solid var(--primary); background: #2a2a2a; color: white; font-family: 'Inter', sans-serif; font-size: 0.9rem; outline: none; margin-right: 0.5rem; }
        .comment-save-btn { background: var(--primary); color: white; border: 2px solid var(--charcoal); padding: 0.5rem 1rem; font-family: 'Staatliches', sans-serif; cursor: pointer; transition: var(--transition); letter-spacing: 1px; }
        .comment-save-btn:hover { background: white; color: var(--charcoal); transform: translateY(-2px); box-shadow: var(--boxshadow); }
        .no-comments { color: #666; font-family: 'Staatliches', sans-serif; text-align: center; padding: 1rem; }
        .char-counter { font-family: 'Staatliches', sans-serif; font-size: 0.85rem; color: #777; letter-spacing: 1px; width: 100%; text-align: right; margin-top: -0.5rem; margin-bottom: 0.5rem; }
        .char-counter.warning { color: #ffcc00; }
        .char-counter.danger { color: var(--primary); }

        /* Tape library scrollable */
        .tape-library { max-height: 500px; overflow-y: auto; }

        /* Custom scrollbar */
        .tape-library::-webkit-scrollbar,
        .comments-section.open::-webkit-scrollbar { width: 8px; }
        .tape-library::-webkit-scrollbar-track,
        .comments-section.open::-webkit-scrollbar-track { background: #222; }
        .tape-library::-webkit-scrollbar-thumb,
        .comments-section.open::-webkit-scrollbar-thumb { background: var(--primary); border: 1px solid #000; }
        .tape-library::-webkit-scrollbar-thumb:hover,
        .comments-section.open::-webkit-scrollbar-thumb:hover { background: #ff2d5a; }

        /* Modal char counter */
        .modal-char-counter { font-family: 'Staatliches', sans-serif; font-size: 0.85rem; color: #777; letter-spacing: 1px; text-align: right; margin-top: -0.6rem; margin-bottom: 0.8rem; }
        .modal-char-counter.warning { color: #ffcc00; }
        .modal-char-counter.danger { color: var(--primary); }

        .comment-sort-bar { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #333; margin-bottom: 0.5rem; }
        .comment-sort-bar label { font-family: 'Staatliches', sans-serif; font-size: 0.9rem; color: #777; letter-spacing: 1px; }
        .comment-sort-bar select { background: #2a2a2a; color: white; border: 1px solid #444; font-family: 'Staatliches', sans-serif; font-size: 0.9rem; padding: 2px 5px; outline: none; cursor: pointer; transition: var(--transition); }
        .comment-sort-bar select:hover { border-color: var(--primary); }

        @media (max-width: 992px) {
            .reels-page-layout { flex-direction: column-reverse; }
            .reels-sidebar { flex: none; width: 100%; position: static; }
            .reels-grid { grid-template-columns: 1fr; }
            .tape-library { max-height: 300px; }
        }



        /* Search and Sort Filter Bar */
        .reels-filter-bar {
            margin-bottom: 2rem;
            background: #111;
            border: 4px solid var(--charcoal);
            padding: 1.5rem;
            box-shadow: 12px 12px 0px var(--primary);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .reels-filter-bar::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            opacity: 0.15; pointer-events: none; z-index: 1;
        }

        .reels-filter-controls {
            position: relative;
            z-index: 2;
            display: flex;
            width: 100%;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .reels-search-section {
            flex: 1;
            min-width: 300px;
        }

        .reels-search-wrapper {
            display: flex;
            width: 100%;
            border: 3px solid #444;
            background: #1a1a1a;
            transition: var(--transition);
        }
        
        .reels-search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 4px 4px 0px var(--primary);
        }

        .reels-search-wrapper input {
            flex: 1;
            padding: 1rem;
            background: transparent;
            border: none;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            color: #fff;
            outline: none;
            letter-spacing: 0.5px;
        }

        .reels-search-wrapper input::placeholder {
            color: #777;
        }

        .reels-search-wrapper .search-btn {
            background: var(--primary);
            border: none;
            border-left: 3px solid #444;
            padding: 0 1.5rem;
            cursor: pointer;
            color: white;
            transition: var(--transition);
        }

        .reels-search-wrapper .search-btn:hover {
            background: white;
            color: var(--charcoal);
        }

        .reels-sort-section select {
            padding: 1rem 2.5rem 1rem 1.5rem;
            background: #1a1a1a;
            border: 3px solid #444;
            font-family: 'Staatliches', sans-serif;
            font-size: 1.1rem;
            color: #fff;
            outline: none;
            cursor: pointer;
            transition: var(--transition);
            letter-spacing: 1px;
            appearance: none;
            background-image: linear-gradient(45deg, transparent 50%, #fff 50%), linear-gradient(135deg, #fff 50%, transparent 50%);
            background-position: calc(100% - 20px) calc(50% - 2px), calc(100% - 14px) calc(50% - 2px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            min-width: 220px;
        }

        .reels-sort-section select:hover,
        .reels-sort-section select:focus {
            border-color: var(--primary);
            box-shadow: 4px 4px 0px var(--primary);
        }

        @media (max-width: 768px) {
            .reels-filter-controls { flex-direction: column; align-items: stretch; }
            .reels-sort-section select { width: 100%; }
        }

        /* --- NEW ABOUT/INFO SECTION STYLES --- */
        .close-modal {
            position: absolute; 
            top: 10px; 
            right: 20px; 
            font-size: 3rem; 
            color: var(--charcoal); 
            cursor: pointer; 
            font-family: 'Staatliches', sans-serif;
            line-height: 1;
        }

        .close-modal:hover{
            color: var(--primary); 
            transform: scale(1.20);
            transition: var(--transition);
        }

.reels-intro-block {
    background: #111;
    border: 4px solid var(--charcoal);
    padding: 3rem;
    margin-bottom: 4rem;
    position: relative; /* Required for the grain overlay */
    overflow: hidden;
    box-shadow: 12px 12px 0px var(--primary);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* THE GRAIN OVERLAY */
.reels-intro-block::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    /* Generates a gritty, high-contrast noise texture */
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
    opacity: 0.15; 
    pointer-events: none; 
    z-index: 1;
}

/* Ensure content stays above the grain */
.intro-title, .intro-description, .intro-tag {
    position: relative;
    z-index: 2;
}

/* Decorative "Tape" or "Tag" element */
.intro-tag {
    position: absolute;
    top: 50px;
    right: -45px;
    background: var(--primary);
    color: white;
    padding: 5px 40px;
    font-family: 'Staatliches', sans-serif;
    transform: rotate(45deg);
    font-size: 1rem;
    letter-spacing: 2px;
    box-shadow: 0 4px 0 rgba(0,0,0,0.3);
}

.intro-title {
    font-family: 'Staatliches', sans-serif;
    font-size: 5rem;
    line-height: 0.85;
    letter-spacing: -3px;
    color: var(--textwhite);
    margin: 0;
    text-transform: uppercase;
}

.intro-title span {
    color: var(--primary);
    font-style: italic;
    display: block;
}

.intro-description {
    font-family: 'Inter', sans-serif;
    font-size: 1.15rem;
    line-height: 1.6;
    color: #aaa;
    max-width: 750px;
    border-left: 3px solid var(--primary);
    padding-left: 1.5rem;
    margin-top: 1rem;
}

.intro-description strong {
    color: var(--textwhite);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Responsive adjustments for the info block */
@media (max-width: 768px) {
    .reels-intro-block { padding: 2rem 1.5rem; }
    .intro-title { font-size: 3.5rem; letter-spacing: -1px; }
    .intro-tag { display: none; }
}
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="mag-hero noise-bg">
    <div class="container">
        <h1 class="glitch-text">THE <span class="text-primary">REELS</span></h1>
        <p class="mag-hero-sub">MOTION / STREETS / SPOTS / STYLE</p>
    </div>
</div>

<section class="reels-section" style="min-height: 80vh; padding-top: 4rem;">
    <div class="container">



    <div class="reels-intro-block">
    <div class="intro-tag">RAW_FEED_V1</div>
    
    <h2 class="intro-title">
        STREET ENERGY 
        <span>COLLECTIVE</span>
    </h2>

    <div class="intro-description">
        <p>
            <strong>This is the shop’s pulse.</strong> A dedicated community space for the clips that define us. 
            Share your raw street energy, the music fueling your sessions, and the edits that keep the culture moving. 
            From heavy bails to clean bolts - if you're skating it, we want to see it.
        </p>
    </div>
</div>

        <div class="section-header-reels" style="margin-bottom: 2rem;">
            <div class="header-title-group">
                <h3>THE REELS</h3>
                <p class="rec-text">REC<span class="rec-indicator">●</span></p>
            </div>
            <p style="font-family: 'Staatliches', sans-serif; font-size: 1.2rem; color: var(--textwhite); letter-spacing: 2px; margin: 0; padding-bottom: 0px; transform: skewX(-6deg); font-weight: 700; display: inline-block;">
    WATCH THE LATEST COMMUNITY CLIPS
</p>
        </div>

        <?php if ($msg): ?>
            <div class="system-alert <?php echo $msg_type; ?>" id="form-alert"><?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="reels-page-layout" style="padding-top: 0;">
            <section class="reels-main-content">
                <?php if (count($reels) > 0): ?>
                    <input type="hidden" id="current-reel-id" value="<?php echo $reels[0]['id']; ?>">
                    
                    <!-- Search & Sort Controls -->
                    <div class="reels-filter-bar">
                        <div class="reels-filter-controls">
                            <div class="reels-search-section">
                                <div class="reels-search-wrapper">
                                    <input type="text" id="reel-search" placeholder="SEARCH REELS BY TITLE OR USERNAME...">
                                    <button type="button" class="search-btn"><span class="material-icons">search</span></button>
                                </div>
                            </div>
                            <div class="reels-sort-section">
                                <select id="reel-sort" class="sort-dropdown">
                                    <option value="newest">SORT: NEWEST</option>
                                    <option value="oldest">SORT: OLDEST</option>
                                    <option value="most_liked">SORT: MOST LIKED</option>
                                    <option value="most_commented">SORT: MOST COMMENTED</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="reels-grid">
                        <div class="main-monitor" id="monitor-container">
                            <div class="monitor-screen" id="play-trigger">
                                <div class="scanlines"></div>
                                <div class="glitch-overlay" id="glitch-layer"></div>
                                <iframe id="reel-video-display" src="<?php echo htmlspecialchars($reels[0]['embed_url']); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            </div>
                            <div class="monitor-footer">
                                <div class="footer-top-row" style="position: relative;">
                                    <div>
                                        <h4 id="reel-title"><?php echo htmlspecialchars($reels[0]['title']); ?></h4>
                                        <p id="reel-meta">FILMED BY: <a href="javascript:void(0)" onclick="openUserProfile('<?php echo urlencode($reels[0]['username']); ?>')" style="color:var(--primary); text-decoration:underline; font-weight:bold;">@<?php echo htmlspecialchars($reels[0]['username']); ?></a> // <?php echo date('M d, Y', strtotime($reels[0]['created_at'])); ?></p>
                                    </div>
                                    <div class="platform-badge" id="reel-platform"><?php echo htmlspecialchars($reels[0]['platform']); ?></div>
                                </div>
                                <?php if(!empty($reels[0]['description'])): ?>
                                    <div class="footer-desc" id="reel-desc"><?php echo nl2br(htmlspecialchars($reels[0]['description'])); ?></div>
                                <?php else: ?>
                                    <div class="footer-desc" id="reel-desc" style="display: none;"></div>
                                <?php endif; ?>
                            </div>
                            <!-- Like & Comment Bar -->
                            <div class="reel-interact-bar">
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <button class="like-btn <?php echo $reels[0]['user_liked'] ? 'liked' : ''; ?>" id="like-btn">
                                        <i class="fas fa-heart"></i> <span id="like-count"><?php echo $reels[0]['like_count']; ?></span>
                                    </button>
                                <?php else: ?>
                                    <span style="color:#666; font-family:'Staatliches',sans-serif; font-size:1.1rem; display: flex; align-items: center; gap: 8px;"><i class="fas fa-heart"></i> <span id="like-count"><?php echo $reels[0]['like_count']; ?></span></span>
                                <?php endif; ?>
                                <button class="toggle-comments-btn" id="toggle-comments">COMMENTS</button>
                            </div>
                            <!-- Comments Section -->
                            <div class="comments-section" id="comments-section">
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <form class="comment-form" id="comment-form" style="margin-top: 0; margin-bottom: 1.5rem;">
                                        <div class="comment-form-row">
                                            <input type="text" id="comment-input" placeholder="Drop a comment..." maxlength="75" required>
                                            <button type="submit">POST</button>
                                        </div>
                                        <div class="char-counter" style="margin-top: 7px;" id="comment-counter">75 characters remaining</div>
                                    </form>
                                <?php else: ?>
                                    <div style="text-align: center; margin-bottom: 1.5rem; padding: 1.5rem; background: var(--bg-light); border: 2px dashed var(--charcoal);">
                                        <p style="font-family: 'Staatliches', sans-serif; font-size: 1.2rem; color: var(--charcoal); margin: 0; letter-spacing: 1px;">YOU MUST <a href="login.php" style="color: var(--primary); text-decoration: underline;">LOGIN</a> TO LEAVE A COMMENT.</p>
                                    </div>
                                <?php endif; ?>

                                <div class="comment-sort-bar">
                                    <label>SORT BY:</label>
                                    <select id="comment-sort">
                                        <option value="oldest">OLDEST FIRST</option>
                                        <option value="newest">NEWEST FIRST</option>
                                    </select>
                                </div>

                                <div id="comments-list"></div>
                            </div>
                        </div>
                        <div class="tape-library" id="tape-list">
                            <?php 
                            $total_reels = count($reels);
                            foreach($reels as $index => $reel): 
                                $tape_number = $total_reels - $index;
                            ?>
                                <div class="tape-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     data-video="<?php echo htmlspecialchars($reel['embed_url']); ?>" 
                                     data-title="<?php echo htmlspecialchars($reel['title']); ?>" 
                                     data-username="<?php echo htmlspecialchars(strtolower($reel['username'])); ?>"
                                     data-meta="FILMED BY: <a href='javascript:void(0)' onclick='openUserProfile(&quot;<?php echo urlencode($reel['username']); ?>&quot;)' style='color:var(--primary); text-decoration:underline; font-weight:bold;'>@<?php echo htmlspecialchars($reel['username']); ?></a> // <?php echo date('M d, Y', strtotime($reel['created_at'])); ?>"
                                     data-platform="<?php echo htmlspecialchars($reel['platform']); ?>"
                                     data-desc="<?php echo htmlspecialchars($reel['description']); ?>"
                                     data-reel-id="<?php echo $reel['id']; ?>"
                                     data-likes="<?php echo $reel['like_count']; ?>"
                                     data-comments="<?php echo $reel['comment_count']; ?>"
                                     data-date="<?php echo strtotime($reel['created_at']); ?>"
                                     data-user-liked="<?php echo $reel['user_liked']; ?>">
                                    <span class="tape-label">TAPE_<?php echo str_pad($tape_number, 2, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars(substr($reel['title'], 0, 15)); ?><?php echo strlen($reel['title']) > 15 ? '...' : ''; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 4rem; border: 4px dashed var(--charcoal); font-family: 'Staatliches', sans-serif; font-size: 2rem;">
                        NO REELS FOUND. BE THE FIRST TO POST!
                    </div>
                <?php endif; ?>
            </section>

            <aside class="reels-sidebar">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="share-clip-btn" id="open-upload-modal"><i class="fas fa-video"></i> SHARE YOUR CLIP</button>
                <?php else: ?>
                    <div class="login-prompt">
                        <h3>WANT TO SHARE?</h3>
                        <p style="margin-bottom: 1.5rem;">Join the crew to share your own skate edits and clips.</p>
                        <a href="login.php" class="btn btn-primary" style="display:inline-block; padding: 1rem 2rem; border:3px solid var(--charcoal); text-decoration:none; background: var(--primary); color: white; font-family:'Staatliches', sans-serif; font-size:1.5rem;">LOGIN TO POST</a>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<!-- Upload Modal -->
<?php if (isset($_SESSION['user_id'])): ?>
<div class="reel-modal-overlay" id="uploadModal">
    <div class="reel-modal-content">
        <span class="reel-modal-close" id="close-upload-modal">&times;</span>
        <h3 style="font-family: 'Staatliches', sans-serif; letter-spacing: -4px; font-size: 3rem; font-style: italic; margin: 0; padding-bottom: 20px; padding-top: 10px; color: var(--textwhite); ">
            SHARE YOUR CLIP
        </h3>
        <form action="reels.php" method="POST">
            <input type="text" name="title" id="modal-title" placeholder="VIDEO TITLE" maxlength="35" required>
            <div class="modal-char-counter" id="title-counter">35 characters remaining</div>
            <input type="url" name="video_url" placeholder="YOUTUBE OR VIMEO URL" required>
            <textarea name="description" id="modal-desc" placeholder="DROP A CAPTION (OPTIONAL)" rows="3" maxlength="100" style="resize: vertical;"></textarea>
            <div class="modal-char-counter" id="desc-counter">100 characters remaining</div>
            <button type="submit" name="submit_reel" class="btn btn-primary" style="width:100%; font-size:1.2rem; padding:1rem; font-family:'Staatliches',sans-serif; cursor:pointer; border:3px solid var(--charcoal); transition: var(--transition);" >POST REEL</button>
        </form>
        <p style="margin-top: 1rem; font-size: 0.8rem; opacity: 0.7; text-align: center;">We support YouTube and Vimeo links. Make sure your video is public!</p>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>

<!-- User Profile Modal -->
<div id="userProfileModal" class="reel-modal-overlay">
    <div class="modal-content seller-profile-modal-shell" id="userProfileContent" style="padding: 30px; width: 90%; max-width: 1100px; max-height: 90vh; overflow-y: auto;">
    </div>
</div>

<script>
    // --- User Profile AJAX Modal Logic ---
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
    
    // Close modal if clicked outside
    document.getElementById('userProfileModal').addEventListener('click', (e) => { 
        if (e.target === document.getElementById('userProfileModal')) {
            closeSellerModal();
        }
    });
</script>

</body>
</html>
