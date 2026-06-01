<?php
require_once 'admin_auth.php';
$current_page = 'admin-users.php'; 

$msg = '';
$msg_type = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

// Handle Admin Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $temp_pass = $_POST['temp_password'];

    if (strlen($username) < 3 || strlen($temp_pass) < 6) {
        $msg = "INVALID INPUTS. USERNAME OR PASSWORD TOO SHORT.";
        $msg_type = "error";
    } else {
        // Check uniqueness
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $msg = "USERNAME OR EMAIL ALREADY IN USE.";
            $msg_type = "error";
        } else {
            $hashed = password_hash($temp_pass, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            $ins->bind_param("sss", $username, $email, $hashed);
            if ($ins->execute()) {
                $msg = "NEW ADMINISTRATOR ACCOUNT CREATED SUCCESSFULLY.";
                $msg_type = "success";
            }
        }
    }
    $_SESSION['msg'] = $msg;
    $_SESSION['msg_type'] = $msg_type;
    header("Location: admin-users.php");
    exit();
}

// Handle Admin Status Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_admin'])) {
    $target_id = (int)$_POST['admin_id'];
    $new_status = (int)$_POST['new_status'];
    
    if ($target_id === $_SESSION['admin_id']) {
        $_SESSION['msg'] = "YOU CANNOT DEACTIVATE YOUR OWN ACCOUNT.";
        $_SESSION['msg_type'] = "error";
    } else {
        $upd = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ? AND role = 'admin'");
        $upd->bind_param("ii", $new_status, $target_id);
        $upd->execute();
        $_SESSION['msg'] = "ACCOUNT STATUS UPDATED.";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: admin-users.php");
    exit();
}

// Handle Admin Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $target_id = (int)$_POST['admin_id'];
    
    if ($target_id === $_SESSION['admin_id']) {
        $_SESSION['msg'] = "YOU CANNOT DELETE YOUR OWN ACCOUNT.";
        $_SESSION['msg_type'] = "error";
    } else {
        // Ensure at least one active admin remains
        $count_res = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'admin' AND is_blocked = 0 AND id != $target_id");
        $c = $count_res->fetch_assoc()['c'];
        if ($c == 0) {
            $_SESSION['msg'] = "CANNOT DELETE: MUST LEAVE AT LEAST ONE ACTIVE ADMINISTRATOR.";
            $_SESSION['msg_type'] = "error";
        } else {
            $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
            $del->bind_param("i", $target_id);
            $del->execute();
            $_SESSION['msg'] = "ADMINISTRATOR DELETED PERMANENTLY.";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: admin-users.php");
    exit();
}

// Fetch Admins
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "role = 'admin'";
if ($search) {
    $clean_search = $conn->real_escape_string($search);
    $where .= " AND (username LIKE '%$clean_search%' OR email LIKE '%$clean_search%')";
}
// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_res = $conn->query("SELECT COUNT(*) as total FROM users WHERE $where");
$total_records = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$admins_q = $conn->query("SELECT id, username, email, created_at, last_login, is_blocked FROM users WHERE $where ORDER BY id ASC LIMIT $limit OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | ADMIN USERS</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="../assets/admin-script.js" defer></script>
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
                <h2 class="glitch-text-admin">ADMIN <span class="text-primary">USERS</span></h2>
                <p class="admin-text-shop">MANAGE PLATFORM ADMINISTRATORS</p>
            </div>
            <button class="btn-primary-brutal" style="padding: 10px 20px;" onclick="openModal('add-admin-modal')">+ NEW ADMIN</button>
        </div>

        <?php if ($msg): ?>
            <div class="admin-alert alert-<?php echo $msg_type; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="grainy-card" style="padding: 20px; margin-bottom: 30px;">
            <h3 class="admin-table-h3">STAFF <span class="header-span">DIRECTORY</span></h3>
            
        <div class="grainy-card filter-bar" style="margin-bottom: 20px;">
            <form method="GET" action="admin-users.php" class="search-filter-form">
                
                <div class="filter-group search-box">
                    <label>SEARCH</label>
                    <input type="text" name="search" placeholder="Search username or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filter-actions" style="margin-top:22px;">
                    <button type="submit" class="btn-filter">SEARCH</button>
                    <a href="admin-users.php" class="btn-reset">RESET</a>
                </div>
                
            </form>
        </div>

            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>USERNAME</th>
                        <th>EMAIL</th>
                        <th>STATUS</th>
                        <th>LAST LOGIN</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($admins_q->num_rows > 0): ?>
                        <?php while($row = $admins_q->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['username']); ?></strong>
                                <?php if($row['id'] == $_SESSION['admin_id']) echo '<span style="color:var(--primary); font-size:0.8rem; font-family:\'Staatliches\',sans-serif; margin-left:5px;">(YOU)</span>'; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td>
                                <?php if($row['is_blocked'] == 1): ?>
                                    <span style="color:red; font-weight:bold;">INACTIVE</span>
                                <?php else: ?>
                                    <span style="color:green; font-weight:bold;">ACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $row['last_login'] ? date('M j, Y H:i', strtotime($row['last_login'])) : 'NEVER'; ?></td>
                            <td>
                                <?php if($row['id'] != $_SESSION['admin_id']): ?>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Toggle status for this admin?');">
                                        <input type="hidden" name="admin_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $row['is_blocked'] == 1 ? 0 : 1; ?>">
                                        <button type="submit" name="toggle_admin" class="btn-mini" style="background: var(--charcoal); color: white;">TOGGLE</button>
                                    </form>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('WARNING: Permanently delete this admin account?');">
                                        <input type="hidden" name="admin_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_admin" class="btn-mini btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; font-weight:700; font-style: italic;">NO USERS MATCHING YOUR SEARCH.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 0): ?>
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
    </main>
</section>

<!-- Add Admin Modal -->
<div id="add-admin-modal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('add-admin-modal')">&times;</span>
        <h3 class="admin-table-h3">CREATE <span class="header-span">NEW ADMIN</span></h3>
        <form method="POST" action="admin-users.php" class="admin-form">
            <label>USERNAME *</label>
            <input type="text" name="username" required minlength="3">
            
            <label>EMAIL ADDRESS *</label>
            <input type="email" name="email" required>
            
            <label>TEMPORARY PASSWORD *</label>
            <input type="text" name="temp_password" required minlength="6" placeholder="Will be hashed securely...">
            
            <button type="submit" name="create_admin" class="btn-primary-brutal" style="width: 100%; margin-top: 20px; font-size: 1.5rem; padding: 15px;">CREATE ACCOUNT</button>
        </form>
    </div>
</div>

</body>
</html>

