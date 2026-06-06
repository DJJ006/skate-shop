<?php
require_once __DIR__ . '/../db.php';

// Ensure this is called AFTER db.php where $conn is defined
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database schema migration for RBAC
$check_role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($check_role->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role ENUM('admin', 'client') DEFAULT 'client'");
    // Elevate the first user to admin to prevent lockout
    $conn->query("UPDATE users SET role = 'admin' ORDER BY id ASC LIMIT 1");
}

$check_login = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
if ($check_login->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL");
}

// Database schema migration for Ticket Deletion
$check_ticket_del = $conn->query("SHOW COLUMNS FROM support_tickets LIKE 'is_deleted_by_admin'");
if ($check_ticket_del->num_rows == 0) {
    $conn->query("ALTER TABLE support_tickets ADD COLUMN is_deleted_by_admin TINYINT(1) DEFAULT 0");
}

// Validate admin session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

// Ensure username is available in session for header
if (!isset($_SESSION['admin_username'])) {
    $auth_u_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $auth_u_stmt->bind_param("i", $_SESSION['admin_id']);
    $auth_u_stmt->execute();
    $auth_u = $auth_u_stmt->get_result()->fetch_assoc();
    if ($auth_u) {
        $_SESSION['admin_username'] = $auth_u['username'];
    }
}

// Verify the user actually exists and is still an admin
$auth_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND is_blocked = 0");
$auth_stmt->bind_param("i", $_SESSION['admin_id']);
$auth_stmt->execute();
if ($auth_stmt->get_result()->num_rows === 0) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_id']);
    header("Location: admin-login.php");
    exit();
}
?>

