<?php
require_once 'admin_auth.php';
require_once '../notification-service.php';

// Ensure tables exist
$conn->query("
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    reported_username VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    attachment_url VARCHAR(255) DEFAULT NULL,
    status ENUM('New', 'Open', 'In Progress', 'Resolved', 'Closed') DEFAULT 'New',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("
CREATE TABLE IF NOT EXISTS support_ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender_type ENUM('admin', 'user') NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
)");

// Handle Admin Reply and Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_reply'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    $reply_message = trim($_POST['reply_message']);
    
    // Update status
    $conn->query("UPDATE support_tickets SET status = '$new_status' WHERE id = $ticket_id");
    
    // Insert reply if not empty
    if (!empty($reply_message)) {
        $stmt = $conn->prepare("INSERT INTO support_ticket_replies (ticket_id, sender_type, user_id, message) VALUES (?, 'admin', ?, ?)");
        $admin_id = $_SESSION['admin_id'] ?? 0;
        $stmt->bind_param("iis", $ticket_id, $admin_id, $reply_message);
        $stmt->execute();
        
        // Notify User
        $t_stmt = $conn->query("SELECT user_id, subject FROM support_tickets WHERE id = $ticket_id");
        if ($t_row = $t_stmt->fetch_assoc()) {
            $user_id = $t_row['user_id'];
            $subject = $t_row['subject'];
            
            $notif = "The administration team has responded to your report regarding: '{$subject}'. Status is now: {$new_status}.";
            sendAppNotification($conn, $user_id, $notif);
        }
    }

    $_SESSION['msg'] = "TICKET UPDATED SUCCESSFULLY.";
    $_SESSION['msg_type'] = "success";
    header("Location: reports.php");
    exit();
}

// Handle Ticket Deletion (Soft Delete for Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticket_id = (int)$_POST['delete_ticket_id'];
    $reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    $info_stmt = $conn->prepare("SELECT user_id, subject FROM support_tickets WHERE id = ?");
    $info_stmt->bind_param("i", $ticket_id);
    $info_stmt->execute();
    $ticket_info = $info_stmt->get_result()->fetch_assoc();

    if ($ticket_info) {
        $msg = "Your ticket '#$ticket_id - " . $ticket_info['subject'] . "' was closed by an admin. Reason: " . $reason;
        sendAppNotification($conn, $ticket_info['user_id'], $msg);
        
        $reply_stmt = $conn->prepare("INSERT INTO support_ticket_replies (ticket_id, sender_type, user_id, message) VALUES (?, 'admin', ?, ?)");
        $admin_id = $_SESSION['user_id'];
        $reply_message = "[ADMIN SYSTEM MESSAGE]: This ticket has been removed from the active queue. Reason: " . $reason;
        $reply_stmt->bind_param("iis", $ticket_id, $admin_id, $reply_message);
        $reply_stmt->execute();
    }
    
    // Admin deletion only hides it from the admin workflow, keeping it visible for the user
    $del_stmt = $conn->prepare("UPDATE support_tickets SET is_deleted_by_admin = 1, status = 'Closed' WHERE id = ?");
    $del_stmt->bind_param("i", $ticket_id);
    
    if ($del_stmt->execute()) {
        $_SESSION['msg'] = "TICKET REMOVED FROM ADMIN WORKFLOW. CLIENT NOTIFIED.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "ERROR REMOVING TICKET.";
        $_SESSION['msg_type'] = "error";
    }
    header("Location: reports.php");
    exit();
}

// Fetch Tickets
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$sort_filter = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'newest';

$where = "is_deleted_by_admin = 0";
if (!empty($status_filter)) {
    $where .= " AND status = '$status_filter'";
}

$order_by = ($sort_filter === 'oldest') ? "ASC" : "DESC";

// PAGINATION SETUP
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// COUNT TOTAL RECORDS
$count_res = $conn->query("SELECT COUNT(*) as total FROM support_tickets t WHERE $where");
$total_records = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$sql = "SELECT t.*, u.username FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id WHERE $where ORDER BY t.created_at $order_by LIMIT $limit OFFSET $offset";
$tickets = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | REPORTS & SUPPORT</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/admin.css">
    <script src="../assets/admin-script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .ticket-modal-content {
            background: #fff;
            padding: 20px;
            border: 4px solid var(--primary);
            box-shadow: 8px 8px 0px #000;
            max-width: 800px;
            margin: 5% auto;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .badge-status {
            padding: 4px 8px;
            font-family: 'Staatliches', sans-serif;
            font-size: 0.9rem;
            letter-spacing: 1px;
            background: #000;
            color: #fff;
            text-transform: uppercase;
        }
        .status-new { background: var(--primary); }
        .status-open { background: #f1c40f; color: #000; }
        .status-resolved { background: #2ecc71; }
        .status-closed { background: #7f8c8d; }

        .thread-msg {
            background: #fff;
            border: 3px solid #000;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 4px 4px 0px #000;
        }
        .thread-msg.admin-msg {
            background: #f1f8fa;
            border-color: var(--primary);
            box-shadow: 4px 4px 0px var(--primary);
        }
        .thread-msg-header {
            font-family: 'Staatliches', sans-serif;
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            color: #222;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 10px;
        }
        .thread-msg-header .sender-name {
            letter-spacing: 1px;
            color: #000;
        }
        .thread-msg-header .msg-timestamp {
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
        }
        .thread-msg .msg-body p {
            font-family: 'Inter', sans-serif;
            font-size: 1.05rem;
            line-height: 1.6;
            white-space: pre-wrap;
            color: #111;
        }
        .attachment-link {
            display: inline-block;
            margin-top: 10px;
            padding: 5px 10px;
            background: #000;
            color: #fff;
            text-decoration: none;
            font-family: 'Staatliches', sans-serif;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/admin_header.php'; ?>

<section class="admin-layout container">
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="top-action-bar">
            <div>
                <h2 class="glitch-text-admin">SUPPORT <span class="text-primary">TICKETS</span></h2>
                <p class="admin-text-shop">MANAGE USER REPORTS & INQUIRIES</p>
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

        <div class="grainy-card" style="padding: 20px;">
            <h3 class="admin-table-h3">ALL <span class="header-span">TICKETS</span></h3>
            
        <div class="grainy-card filter-bar" style="margin-bottom: 20px;">
            <form method="GET" action="reports.php" class="search-filter-form">
                <div class="filter-group">
                    <label>FILTER BY STATUS</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">ALL TICKETS</option>
                        <option value="New" <?php if($status_filter=='New') echo 'selected'; ?>>NEW</option>
                        <option value="Open" <?php if($status_filter=='Open') echo 'selected'; ?>>OPEN</option>
                        <option value="In Progress" <?php if($status_filter=='In Progress') echo 'selected'; ?>>IN PROGRESS</option>
                        <option value="Resolved" <?php if($status_filter=='Resolved') echo 'selected'; ?>>RESOLVED</option>
                        <option value="Closed" <?php if($status_filter=='Closed') echo 'selected'; ?>>CLOSED</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>SORT BY</label>
                    <select name="sort" onchange="this.form.submit()">
                        <option value="newest" <?php if($sort_filter=='newest') echo 'selected'; ?>>NEWEST FIRST</option>
                        <option value="oldest" <?php if($sort_filter=='oldest') echo 'selected'; ?>>OLDEST FIRST</option>
                    </select>
                </div>
                <div class="filter-actions" style="margin-bottom: 0; margin-top: 0;">
                    <a href="reports.php" class="btn-reset">RESET</a>
                </div>
            </form>
        </div>

            <table class="recent-activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>USER</th>
                        <th>SUBJECT & CATEGORY</th>
                        <th>STATUS</th>
                        <th>DATE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $modals_html = [];
                    if ($tickets && $tickets->num_rows > 0): ?>
                        <?php while($row = $tickets->fetch_assoc()): 
                            $status_class = strtolower(str_replace(' ', '-', $row['status']));
                        ?>
                            <tr>
                                <td class="td-id">#<?php echo $row['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($row['email']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['subject']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($row['category']); ?></small>
                                </td>
                                <td><span class="badge-status status-<?php echo $status_class; ?>"><?php echo $row['status']; ?></span></td>
                                <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div style="display:flex; gap:10px;">
                                        <button class="btn btn-primary" style="padding:5px 10px; font-size:0.9rem; border: 2px solid #000;" onclick="openTicketModal(<?php echo $row['id']; ?>)">VIEW</button>
                                        <button class="btn-mini btn-danger" onclick="openModal('reject-ticket-<?php echo $row['id']; ?>')"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php ob_start(); ?>
                            <div id="reject-ticket-<?php echo $row['id']; ?>" class="modal-overlay modal-top-layer">
                                <div class="modal-content">
                                    <span class="close-modal" onclick="closeModal('reject-ticket-<?php echo $row['id']; ?>')">&times;</span>
                                    <h3 class="admin-table-h3">REMOVE <span class="header-span">TICKET</span></h3>
                                    <form method="POST" action="reports.php">
                                        <input type="hidden" name="delete_ticket_id" value="<?php echo $row['id']; ?>">
                                        <div class="admin-form-group">
                                            <label class="admin-form-label">WHY ARE YOU REMOVING THIS TICKET?</label>
                                            <textarea name="rejection_reason" rows="4" class="admin-input-dark" placeholder="E.g. Duplicate ticket, spam, resolved elsewhere..." required style="width: 100%; border: 3px solid #000; padding: 10px; font-family: 'Inter', sans-serif; resize: vertical; margin-bottom: 20px;"></textarea>
                                        </div>
                                        <button type="submit" name="delete_ticket" class="btn btn-danger btn-full btn-heavy-font">REMOVE TICKET</button>
                                    </form>
                                </div>
                            </div>
                            <?php $modals_html[] = ob_get_clean(); ?>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; font-weight:700;">NO TICKETS FOUND.</td></tr>
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

<?php 
foreach ($modals_html as $html) {
    echo $html;
}
?>

<!-- View Ticket Modal Structure (Populated via JS/AJAX or inline) -->
<div id="ticketModal" class="modal-overlay">
    <div class="ticket-modal-content">
        <span class="close-modal" onclick="closeModal('ticketModal')">&times;</span>
        <h3 class="admin-table-h3">TICKET <span class="header-span">#<span id="t_id_display"></span></span></h3>
        <div id="ticket_details_container">
            <!-- Content loaded via AJAX -->
            Loading...
        </div>
    </div>
</div>

<script>
function openTicketModal(ticketId) {
    document.getElementById('t_id_display').innerText = ticketId;
    document.getElementById('ticketModal').classList.add('active');
    
    // Fetch ticket details via AJAX
    fetch('get-ticket.php?id=' + ticketId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('ticket_details_container').innerHTML = html;
        });
}
</script>

</body>
</html>

