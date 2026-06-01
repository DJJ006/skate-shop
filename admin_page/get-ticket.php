<?php
require_once 'admin_auth.php';

if (!isset($_SESSION['admin_logged_in'])) {
    exit('Unauthorized');
}

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticket_id === 0) exit('Invalid Ticket ID');

// Fetch ticket
$t_stmt = $conn->prepare("SELECT * FROM support_tickets WHERE id = ?");
$t_stmt->bind_param("i", $ticket_id);
$t_stmt->execute();
$ticket = $t_stmt->get_result()->fetch_assoc();

if (!$ticket) exit('Ticket not found.');

// Fetch replies
$r_stmt = $conn->prepare("SELECT * FROM support_ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
$r_stmt->bind_param("i", $ticket_id);
$r_stmt->execute();
$replies = $r_stmt->get_result();
?>

<div id="fetched_ticket_status" style="display:none;"><?php echo htmlspecialchars($ticket['status']); ?></div>

<div style="margin-bottom: 25px; border-bottom: 4px solid #000; padding-bottom: 20px;">
    <p style="font-family:'Inter',sans-serif; font-size:1.15rem; margin-bottom:8px; color:#111;"><strong style="font-family:'Staatliches',sans-serif; font-size:1.3rem; letter-spacing:1px; color:#000;">USER:</strong> <?php echo htmlspecialchars($ticket['full_name']); ?> (<?php echo htmlspecialchars($ticket['email']); ?>) | <?php echo htmlspecialchars($ticket['phone']); ?></p>
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
                    <i class="fa-solid fa-user"></i> USER
                <?php endif; ?>
            </span>
            <span class="msg-timestamp"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
        </div>
        <div class="msg-body">
            <p><?php echo htmlspecialchars($reply['message']); ?></p>
        </div>
    </div>
<?php endwhile; ?>

<h4 style="margin-top: 30px; margin-bottom: 15px; font-family:'Staatliches',sans-serif; font-size:1.8rem; letter-spacing:1px; color:var(--primary);">ADMIN ACTION</h4>
<form action="reports.php" method="POST" class="admin-form" style="background:#f1f1f1; padding:20px; border:3px solid #000;">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
    
    <label>UPDATE STATUS</label>
    <select name="status" style="width:100%; padding:10px; margin-bottom:15px; border:2px solid #000; font-family:'Inter',sans-serif;">
        <option value="New" <?php if($ticket['status']=='New') echo 'selected'; ?>>NEW</option>
        <option value="Open" <?php if($ticket['status']=='Open') echo 'selected'; ?>>OPEN</option>
        <option value="In Progress" <?php if($ticket['status']=='In Progress') echo 'selected'; ?>>IN PROGRESS</option>
        <option value="Resolved" <?php if($ticket['status']=='Resolved') echo 'selected'; ?>>RESOLVED</option>
        <option value="Closed" <?php if($ticket['status']=='Closed') echo 'selected'; ?>>CLOSED</option>
    </select>
    
    <label>WRITE A REPLY <span style="font-size:0.8rem; color:#666;">(Will be sent to user via Email & In-App Notification)</span></label>
    <textarea name="reply_message" rows="4" style="width:100%; padding:10px; margin-bottom:15px; border:2px solid #000; font-family:'Inter',sans-serif;" placeholder="Type your response here... (Leave blank to only update status)"></textarea>
    
    <button type="submit" name="admin_reply" class="btn btn-primary" style="width:100%;">SUBMIT ACTION</button>
</form>
