<?php
// mag-api.php — AJAX endpoint for magazine articles
session_start();
include '../db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_article') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM magazine_posts WHERE id=? AND status='published' LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res && $res->num_rows > 0) {
        $a = $res->fetch_assoc();
        echo json_encode(['success' => true, 'article' => $a]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);

