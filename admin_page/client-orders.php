<?php
session_start();
include '../db.php';

// Fetch pending count for the sidebar notification badge
$count_sql = "SELECT COUNT(*) as pending_count FROM products WHERE is_marketplace = 1 AND is_approved = 0";
$count_result = $conn->query($count_sql);
$pending_count = 0;

if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $pending_count = (int)$count_row['pending_count'];
}

// Handle search
$search_query = "";
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Handle status filter
$status_filter = "ALL";
if (isset($_GET['status_filter'])) {
    $status_filter = $_GET['status_filter'];
}

// Build the order query
$sql = "
    SELECT 
        o.id as order_id, 
        o.buyer_id,
        o.amount, 
        o.created_at, 
        o.status,
        o.stripe_session_id,
        p.title, 
        p.image_url, 
        u.username, 
        u.email 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    JOIN users u ON o.buyer_id = u.id 
    WHERE 1=1
";

if ($status_filter === 'PAID') {
    $sql .= " AND o.status = 'PAID' ";
} elseif ($status_filter === 'CANCELLED') {
    $sql .= " AND o.status = 'CANCELLED' ";
} elseif ($status_filter === 'RECEIVED') {
    $sql .= " AND o.status = 'RECEIVED' ";
} else {
    $sql .= " AND o.status IN ('PAID', 'CANCELLED', 'RECEIVED') ";
}

if ($search_query !== "") {
    $clean_search = $conn->real_escape_string($search_query);
    $order_id_search = null;
    if (preg_match('/^ORD-(\d+)$/i', $clean_search, $matches)) {
        $order_id_search = (int)$matches[1];
    }
    
    $sql .= " AND (";
    $sql .= " p.title LIKE '%$clean_search%' ";
    $sql .= " OR u.username LIKE '%$clean_search%' ";
    if ($order_id_search !== null) {
        $sql .= " OR o.id = $order_id_search ";
    }
    $sql .= ") ";
}

$sql .= " ORDER BY o.created_at DESC";
$orders_stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | CLIENT ORDERS</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <script src="../assets/admin-script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
</head>
<body>

<header class="main-header">
    <div class="container header-content">
        <h1 class="logo">
            <a href="index.php">SKATE<span>SHOP</span> ADMIN</a>
        </h1>
        <div class="mobile-menu-icon" id="menu-btn">
            <span class="material-icons">menu</span>
        </div>
    </div>
</header>

<section class="admin-layout container">
    <aside class="admin-sidebar grainy-card">
        <h3 class="admin-sidebar-title">SYSTEM <span class="header-span">MENU</span></h3>
        <ul class="admin-nav-list">
            <li><a href="index.php"><span class="material-icons">dashboard</span> DASHBOARD</a></li>
            <li><a href="shop-products.php"><span class="material-icons">inventory_2</span> THE VAULT (SHOP)</a></li>
            <li><a href="marketplace-products.php"><span class="material-icons">storefront</span> STREET MARKET</a></li>
            <li><a href="registered-users.php"><span class="material-icons">manage_accounts</span> REGISTERED USERS</a></li>
            <li><a href="client-orders.php" class="nav-relative active"><span class="material-icons">receipt_long</span> CLIENT ORDERS</a></li>
            <li>
                <a href="accept-product.php" class="nav-relative">
                    <span class="material-icons">gavel</span> PENDING GEAR
                    <?php if ($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="verify-seller.php" class="nav-relative">
                    <span class="material-icons">verified_user</span> TRUST & SAFETY
                </a>
            </li>
            <li><a href="../index.php"><span class="material-icons">public</span> VIEW LIVE SITE</a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <div class="shop-header-title">
            <h2 class="glitch-text-admin">CLIENT <span class="text-primary">ORDERS</span></h2>
            <p class="admin-text">VIEW AND SEARCH ALL CLIENT PURCHASES</p>
        </div>

        <?php if(isset($_SESSION['admin_msg'])): ?>
            <?php
                $msg_type_class = (isset($_SESSION['admin_msg_type']) && $_SESSION['admin_msg_type'] === 'error') ? 'alert-danger' : 'alert-success';
            ?>
            <div class="admin-alert <?php echo $msg_type_class; ?>">
                <?php 
                    echo $_SESSION['admin_msg']; 
                    unset($_SESSION['admin_msg']);
                    unset($_SESSION['admin_msg_type']);
                ?>
            </div>
        <?php endif; ?>


        <div class="grainy-card" style="padding: 20px;">
            <h3 class="admin-table-h3">ORDER <span class="header-span">HISTORY</span></h3>

            <div class="grainy-card filter-bar" style="margin-bottom: 25px;">
            <form method="GET" action="client-orders.php" class="search-filter-form">
                <div class="filter-group search-box" style="flex: 2;">
                    <label>SEARCH ORDERS</label>
                    <input type="text" name="search" placeholder="Product, ORD-XXXXXX, or Username..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>

                <div class="filter-group" style="flex: 1;">
                    <label>STATUS</label>
                    <select name="status_filter" onchange="this.form.submit()">
                        <option value="ALL" <?php echo ($status_filter === 'ALL') ? 'selected' : ''; ?>>ALL STATUSES</option>
                        <option value="PAID" <?php echo ($status_filter === 'PAID') ? 'selected' : ''; ?>>PAID (PENDING)</option>
                        <option value="RECEIVED" <?php echo ($status_filter === 'RECEIVED') ? 'selected' : ''; ?>>RECEIVED</option>
                        <option value="CANCELLED" <?php echo ($status_filter === 'CANCELLED') ? 'selected' : ''; ?>>CANCELLED</option>
                    </select>
                </div>

                <div class="filter-actions" style="margin-top:22px;">
                    <button type="submit" class="btn-filter">SEARCH</button>
                    <?php if (!empty($search_query) || $status_filter !== 'ALL'): ?>
                        <a href="client-orders.php" class="btn-reset">RESET</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
            <div class="table-responsive">
                <table class="recent-activity-table">
                    <thead>
                        <tr>
                            <th>ORDER INFO</th>
                            <th>CLIENT</th>
                            <th>PRODUCT</th>
                            <th>AMOUNT</th>
                            <th>DATE</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_stmt && $orders_stmt->num_rows > 0): ?>
                            <?php while($order = $orders_stmt->fetch_assoc()): ?>
                                <?php $purchase_code = "ORD-" . str_pad($order['order_id'], 6, "0", STR_PAD_LEFT); ?>
                                <tr>
                                    <td class="td-id">
                                        <span style="font-family: monospace; font-weight: bold;"><?php echo $purchase_code; ?></span>
                                        <?php if ($order['status'] === 'CANCELLED'): ?>
                                            <span style="display:block; margin-top:4px; font-size:0.7rem; font-weight:900; color:#e74c3c; letter-spacing:1px;">CANCELLED</span>
                                        <?php elseif ($order['status'] === 'RECEIVED'): ?>
                                            <span style="display:block; margin-top:4px; font-size:0.7rem; font-weight:900; color:#3498db; letter-spacing:1px;">RECEIVED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['username']); ?></strong><br>
                                        <small style="opacity: 0.7;"><?php echo htmlspecialchars($order['email']); ?></small>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="<?php echo htmlspecialchars($order['image_url']); ?>" alt="Product" style="width: 35px; height: 35px; object-fit: cover; border: 1px solid var(--charcoal);">
                                            <span><?php echo htmlspecialchars($order['title']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-primary">$<?php echo number_format($order['amount'], 2); ?></strong>
                                    </td>
                                    <td style="font-size: 0.85rem; font-weight: 600;">
                                        <?php echo date("M d, Y", strtotime($order['created_at'])); ?><br>
                                        <small style="opacity: 0.6;"><?php echo date("H:i", strtotime($order['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($order['status'] === 'PAID'): ?>
                                            <button 
                                                class="btn-cancel-order" 
                                                onclick="openCancelModal(<?php echo $order['order_id']; ?>, '<?php echo htmlspecialchars(addslashes($purchase_code)); ?>')"
                                            >
                                                <i class="fa-solid fa-ban"></i> CANCEL
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size:0.75rem; color:#888; font-weight:700;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; font-weight: 700; font-style: italic;">NO ORDERS FOUND.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</section>

<!-- ===== CANCEL ORDER MODAL ===== -->
<div id="cancelOrderModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 520px;">
        <span class="close-modal" onclick="closeCancelModal()">&times;</span>
        <h3 class="admin-table-h3" style="margin-top:0;">CANCEL <span class="header-span">ORDER</span></h3>
        <p id="cancelOrderCode" style="font-family: 'Staatliches', sans-serif; font-size:1.2rem; margin:0 0 20px 0; opacity:0.7; letter-spacing: 1px;"></p>

        <form id="cancelOrderForm" action="cancel-order.php" method="POST" class="admin-form">
            <input type="hidden" name="order_id" id="cancelOrderId">

            <label class="admin-form-label">CANCELLATION REASON <span style="color:var(--primary);">*</span></label>
            <textarea 
                name="cancel_reason" 
                id="cancelReasonInput"
                class="admin-input-dark"
                rows="4" 
                placeholder="Provide a clear reason for the cancellation that will be sent to the client..." 
                required
                maxlength="1000"
                style="resize:vertical; margin-bottom:6px;"
            ></textarea>
            <small id="cancelCharCount" style="display:block; text-align:right; color:#888; font-size:0.75rem; margin-bottom:20px;">0 / 1000</small>

            <div style="display:flex; gap:12px; margin-top: 1.5rem;">
                <button type="submit" id="cancelSubmitBtn" class="btn-primary-brutal" style="flex:1; margin-top:0; padding: 0 15px;">
                    CONFIRM CANCELLATION
                </button>
                <button type="button" onclick="closeCancelModal()" class="btn-filter" style="flex:1; margin-top:0; padding: 0 15px;">
                    BACK
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.btn-cancel-order {
    background: #fff;
    color: #e74c3c;
    border: 2px solid #e74c3c;
    padding: 7px 12px;
    font-family: 'Arial Black', sans-serif;
    font-size: 0.72rem;
    letter-spacing: 1px;
    cursor: pointer;
    box-shadow: 3px 3px 0 #c0392b;
    transition: all 0.15s;
    white-space: nowrap;
}
.btn-cancel-order:hover {
    background: #e74c3c;
    color: #fff;
    transform: translate(-1px, -1px);
    box-shadow: 4px 4px 0 #c0392b;
}
</style>

<script>
function openCancelModal(orderId, purchaseCode) {
    document.getElementById('cancelOrderId').value = orderId;
    document.getElementById('cancelOrderCode').textContent = purchaseCode;
    document.getElementById('cancelReasonInput').value = '';
    document.getElementById('cancelCharCount').textContent = '0 / 1000';
    document.getElementById('cancelOrderModal').style.display = 'flex';
}

function closeCancelModal() {
    document.getElementById('cancelOrderModal').style.display = 'none';
}

// Character counter
document.getElementById('cancelReasonInput').addEventListener('input', function() {
    document.getElementById('cancelCharCount').textContent = this.value.length + ' / 1000';
});

// Confirm before submit
document.getElementById('cancelOrderForm').addEventListener('submit', function(e) {
    const reason = document.getElementById('cancelReasonInput').value.trim();
    if (!reason) {
        e.preventDefault();
        alert('PLEASE ENTER A CANCELLATION REASON BEFORE CONFIRMING.');
        return;
    }
    const code = document.getElementById('cancelOrderCode').textContent;
    const confirmed = confirm('Are you sure you want to cancel order ' + code + '?\n\nThis will issue a full Stripe refund and notify the client. This action cannot be undone.');
    if (!confirmed) {
        e.preventDefault();
    }
});

// Close modal when clicking backdrop
document.getElementById('cancelOrderModal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});
</script>

</body>
</html>