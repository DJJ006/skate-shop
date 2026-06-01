<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// === LIKE / UNLIKE ===
if ($action === 'toggle_like' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Login required']);
        exit;
    }
    $reel_id = (int)($_POST['reel_id'] ?? 0);
    $user_id = (int)$_SESSION['user_id'];
    if ($reel_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid reel']);
        exit;
    }

    // Check if already liked
    $check = $conn->prepare("SELECT id FROM reel_likes WHERE reel_id = ? AND user_id = ?");
    $check->bind_param("ii", $reel_id, $user_id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();

    if ($exists) {
        $del = $conn->prepare("DELETE FROM reel_likes WHERE reel_id = ? AND user_id = ?");
        $del->bind_param("ii", $reel_id, $user_id);
        $del->execute();
        $liked = false;
    } else {
        $ins = $conn->prepare("INSERT INTO reel_likes (reel_id, user_id) VALUES (?, ?)");
        $ins->bind_param("ii", $reel_id, $user_id);
        $ins->execute();
        $liked = true;

        // Notification to reel owner
        $reel_q = $conn->prepare("SELECT title, user_id FROM reels WHERE id = ?");
        $reel_q->bind_param("i", $reel_id);
        $reel_q->execute();
        $reel_data = $reel_q->get_result()->fetch_assoc();
        if ($reel_data && $reel_data['user_id'] != $user_id) {
            $username = $_SESSION['username'] ?? 'Someone';
            $msg = $username . ' liked your reel "' . $reel_data['title'] . '"';
            sendAppNotification($conn, $reel_data['user_id'], $msg);
        }
    }

    // Get new count
    $cnt = $conn->prepare("SELECT COUNT(*) as c FROM reel_likes WHERE reel_id = ?");
    $cnt->bind_param("i", $reel_id);
    $cnt->execute();
    $count = $cnt->get_result()->fetch_assoc()['c'];

    echo json_encode(['success' => true, 'liked' => $liked, 'count' => (int)$count]);
    exit;
}

// === ADD COMMENT ===
if ($action === 'add_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Login required']);
        exit;
    }
    $reel_id = (int)($_POST['reel_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $user_id = (int)$_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Anon';

    if ($reel_id <= 0 || empty($comment)) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    if (mb_strlen($comment) > 75) {
        echo json_encode(['success' => false, 'error' => 'Comment too long (max 75)']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO reel_comments (reel_id, user_id, username, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $reel_id, $user_id, $username, $comment);
    $stmt->execute();
    $new_id = $conn->insert_id;

    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $new_id,
            'username' => $username,
            'comment' => htmlspecialchars($comment),
            'created_at' => date('M j, Y H:i'),
            'is_owner' => true
        ]
    ]);
    exit;
}

// === EDIT COMMENT ===
if ($action === 'edit_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Login required']);
        exit;
    }
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    $new_text = trim($_POST['comment'] ?? '');
    $user_id = (int)$_SESSION['user_id'];

    if ($comment_id <= 0 || empty($new_text)) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    if (mb_strlen($new_text) > 75) {
        echo json_encode(['success' => false, 'error' => 'Comment too long (max 75)']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE reel_comments SET comment = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_text, $comment_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'comment' => htmlspecialchars($new_text)]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not found or not owner']);
    }
    exit;
}

// === DELETE COMMENT ===
if ($action === 'delete_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Login required']);
        exit;
    }
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    $user_id = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("DELETE FROM reel_comments WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $comment_id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => $stmt->affected_rows > 0]);
    exit;
}

// === GET COMMENTS FOR A REEL ===
if ($action === 'get_comments' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $reel_id = (int)($_GET['reel_id'] ?? 0);
    $current_user = $_SESSION['user_id'] ?? 0;

    $sort = $_GET['sort'] ?? 'oldest';
    $order = ($sort === 'newest') ? 'DESC' : 'ASC';

    $stmt = $conn->prepare("SELECT rc.id, rc.user_id, rc.username, rc.comment, rc.created_at, u.profile_pic FROM reel_comments rc JOIN users u ON rc.user_id = u.id WHERE rc.reel_id = ? ORDER BY rc.created_at $order");
    $stmt->bind_param("i", $reel_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'comment' => htmlspecialchars($row['comment']),
            'created_at' => date('M j, Y H:i', strtotime($row['created_at'])),
            'is_owner' => ((int)$row['user_id'] === (int)$current_user),
            'profile_pic' => $row['profile_pic']
        ];
    }

    echo json_encode(['success' => true, 'comments' => $comments]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
