<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticket_id === 0) exit('Invalid Ticket ID');

// Fetch ticket
$t_stmt = $conn->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
$t_stmt->bind_param("ii", $ticket_id, $user_id);
$t_stmt->execute();
$ticket = $t_stmt->get_result()->fetch_assoc();

if (!$ticket) exit('Ticket not found or unauthorized.');

// Fetch replies
$r_stmt = $conn->prepare("SELECT * FROM support_ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
$r_stmt->bind_param("i", $ticket_id);
$r_stmt->execute();
$replies = $r_stmt->get_result();

// (Column is_read_by_user doesn't exist, so no update needed here right now)
?>

<div id="fetched_ticket_status" style="display:none;"><?php echo htmlspecialchars($ticket['status']); ?></div>

<div style="margin-bottom: 25px; border-bottom: 4px solid #000; padding-bottom: 20px;">
    <p style="font-family:'Inter',sans-serif; font-size:1.15rem; margin-bottom:8px; color:#111;"><strong style="font-family:'Staatliches',sans-serif; font-size:1.3rem; letter-spacing:1px; color:#000;">CATEGORY:</strong> <?php echo htmlspecialchars($ticket['category']); ?></p>
    <p style="font-family:'Inter',sans-serif; font-size:1.15rem; margin-bottom:5px; color:#111;"><strong style="font-family:'Staatliches',sans-serif; font-size:1.3rem; letter-spacing:1px; color:#000;">SUBJECT:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>
</div>

<h4 style="font-family:'Staatliches',sans-serif; font-size:1.8rem; letter-spacing:1px; color:#000; margin-bottom:15px;">CONVERSATION HISTORY</h4>
<div class="thread-msg">
    <div class="thread-msg-header">
        <span class="sender-name"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($ticket['full_name']); ?></span>
        <span class="msg-timestamp"><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></span>
    </div>
    <div class="msg-body">
        <p><?php echo htmlspecialchars($ticket['message']); ?></p>
    </div>
</div>

<?php while($reply = $replies->fetch_assoc()): ?>
    <div class="thread-msg <?php echo $reply['sender_type'] === 'admin' ? 'admin-msg' : ''; ?>">
        <div class="thread-msg-header">
            <span class="sender-name">
                <?php if($reply['sender_type'] === 'admin'): ?>
                    <i class="fa-solid fa-user-shield"></i> ADMIN
                <?php else: ?>
                    <i class="fa-solid fa-user"></i> YOU
                <?php endif; ?>
            </span>
            <span class="msg-timestamp"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
        </div>
        <div class="msg-body">
            <p><?php echo htmlspecialchars($reply['message']); ?></p>
        </div>
    </div>
<?php endwhile; ?>
