<?php
require_once 'admin_auth.php';
include '../db.php';

// 1. GEAR & LISTINGS COUNTS
$shop_count_res = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_marketplace = 0 AND is_approved = 1 AND quantity > 0");
$shop_count = $shop_count_res->fetch_assoc()['count'];

$market_count_res = $conn->query("
    SELECT COUNT(*) as count 
    FROM products 
    WHERE is_marketplace = 1 AND is_approved = 1 
    AND id NOT IN (SELECT product_id FROM orders WHERE status IN ('PAID', 'RECEIVED')) 
    AND seller_id IN (SELECT id FROM users WHERE is_blocked = 0)
");
$market_count = $market_count_res->fetch_assoc()['count'];

$total_items = $shop_count + $market_count;

// 2. REVENUE & EARNINGS
$shop_rev_res = $conn->query("SELECT SUM(amount) as total FROM orders WHERE seller_id = 0 AND status IN ('PAID', 'RECEIVED')");
$shop_revenue = (float)($shop_rev_res->fetch_assoc()['total'] ?? 0);

$market_rev_res = $conn->query("SELECT SUM(amount) as total FROM orders WHERE seller_id > 0 AND status IN ('PAID', 'RECEIVED')");
$market_revenue = (float)($market_rev_res->fetch_assoc()['total'] ?? 0);

$platform_earnings = $market_revenue * 0.05;

// 3. MARKETPLACE ACTIVITY (For Doughnut Chart)
$market_active = $market_count; // From above
$market_sold_res = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_marketplace = 1 AND is_sold = 1");
$market_sold = $market_sold_res->fetch_assoc()['count'];
$market_pending_res = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_marketplace = 1 AND is_approved = 0 AND is_sold = 0");
$market_pending = $market_pending_res->fetch_assoc()['count'];

// 4. SALES ANALYTICS (Last 7 Days)
$sales_labels = [];
$sales_shop_data = [];
$sales_market_data = [];

for($i=6; $i>=0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sales_labels[] = date('M d', strtotime($date));
    
    // Shop Sales
    $s_q = $conn->query("SELECT SUM(amount) as total FROM orders WHERE DATE(created_at) = '$date' AND seller_id = 0 AND status IN ('PAID', 'RECEIVED')");
    $sales_shop_data[] = (float)($s_q->fetch_assoc()['total'] ?? 0);
    
    // Market Sales
    $m_q = $conn->query("SELECT SUM(amount) as total FROM orders WHERE DATE(created_at) = '$date' AND seller_id > 0 AND status IN ('PAID', 'RECEIVED')");
    $sales_market_data[] = (float)($m_q->fetch_assoc()['total'] ?? 0);
}

// 5. USER GROWTH (Last 7 Days)
$user_labels = [];
$user_data = [];

for($i=6; $i>=0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $user_labels[] = date('M d', strtotime($date));
    
    $u_q = $conn->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = '$date'");
    $user_data[] = (int)($u_q->fetch_assoc()['count'] ?? 0);
}

// 6. CONTENT STATS
$reels_count = $conn->query("SELECT COUNT(*) as count FROM reels")->fetch_assoc()['count'];
$qna_count = $conn->query("SELECT COUNT(*) as count FROM community_qna")->fetch_assoc()['count'];
$shoutouts_count = $conn->query("SELECT COUNT(*) as count FROM community_shoutouts")->fetch_assoc()['count'];
$reviews_count = $conn->query("SELECT COUNT(*) as count FROM seller_ratings")->fetch_assoc()['count'];

// 7. RECENT ACTIVITY FEED
$recent_activity = [];

// Get recent products
$rp_res = $conn->query("SELECT title, price, is_marketplace, created_at FROM products ORDER BY created_at DESC LIMIT 5");
while($row = $rp_res->fetch_assoc()) {
    $recent_activity[] = [
        'type' => 'PRODUCT_ADDED',
        'title' => $row['title'],
        'meta' => ($row['is_marketplace'] ? 'MARKET' : 'SHOP') . " - $" . number_format($row['price'], 2),
        'date' => $row['created_at']
    ];
}

// Get recent orders
$ro_res = $conn->query("SELECT o.id, p.title, o.amount, o.created_at, o.seller_id FROM orders o JOIN products p ON o.product_id = p.id ORDER BY o.created_at DESC LIMIT 5");
while($row = $ro_res->fetch_assoc()) {
    $recent_activity[] = [
        'type' => 'ORDER_PLACED',
        'title' => "Order #" . $row['id'] . " - " . $row['title'],
        'meta' => ($row['seller_id'] > 0 ? 'MARKET' : 'SHOP') . " Sale - $" . number_format($row['amount'], 2),
        'date' => $row['created_at']
    ];
}

// Get recent users
$ru_res = $conn->query("SELECT username, created_at FROM users ORDER BY created_at DESC LIMIT 5");
while($row = $ru_res->fetch_assoc()) {
    $recent_activity[] = [
        'type' => 'USER_REGISTERED',
        'title' => "New Skater Joined: " . $row['username'],
        'meta' => 'USER REGISTRATION',
        'date' => $row['created_at']
    ];
}

// Sort by date DESC
usort($recent_activity, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Take top 8
$recent_activity = array_slice($recent_activity, 0, 8);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkateShop | ADMIN COMMAND CENTER</title>
    <link rel="stylesheet" href="../assets/style.css"> 
    <link rel="stylesheet" href="../assets/shop.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" href="../assets/images/skateshop_favicon.png" type="image/png">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .activity-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--charcoal);
            color: var(--textwhite);
            border-radius: 50%;
            font-size: 1.2rem;
            flex-shrink: 0;
            border: 2px solid var(--textwhite);
            box-shadow: 2px 2px 0px #000;
        }
        .activity-icon.product { background: #3b82f6; }
        .activity-icon.order { background: #22c55e; }
        .activity-icon.user { background: #f59e0b; }
        
        @media (max-width: 992px) {
            .charts-grid {
                grid-template-columns: 1fr !important;
                min-width: 0 !important;
            }
            .charts-grid .grainy-card {
                padding: 10px !important;
                max-width: 100vw;
            }
            canvas {
                max-width: 100% !important;
                height: auto !important;
            }
            .admin-layout.container {
                padding: 0 10px;
            }
        }
    </style>
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
                <h2 class="glitch-text-admin">COMMAND <span class="text-primary">CENTER</span></h2>
                <p class="admin-text-shop">SYSTEM OVERVIEW & ANALYTICS</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card grainy-card">
                <h3><?php echo $total_items; ?></h3>
                <p>TOTAL GEAR IN SYSTEM</p>
            </div>
            <div class="stat-card grainy-card">
                <h3><?php echo $market_count; ?></h3>
                <p>MARKETPLACE LISTINGS</p>
            </div>
            <div class="stat-card grainy-card">
                <h3>$<?php echo number_format($shop_revenue, 2); ?></h3>
                <p>SHOP REVENUE</p>
            </div>
            <div class="stat-card grainy-card">
                <h3 style="color: var(--primary);">$<?php echo number_format($platform_earnings, 2); ?></h3>
                <p>PLATFORM EARNINGS</p>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="charts-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem;">
            <div class="grainy-card" style="padding: 20px;">
                <h3 class="admin-table-h3">SALES <span class="header-span">ANALYTICS</span> (LAST 7 DAYS)</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <div class="grainy-card" style="padding: 20px;">
                <h3 class="admin-table-h3">MARKET <span class="header-span">ACTIVITY</span></h3>
                <div style="position: relative; height: 250px; width: 100%; display: flex; justify-content: center;">
                    <canvas id="marketChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="charts-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
            <div class="grainy-card" style="padding: 20px;">
                <h3 class="admin-table-h3">USER <span class="header-span">GROWTH</span> (LAST 7 DAYS)</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="userChart"></canvas>
                </div>
            </div>
            
            <div class="grainy-card" style="padding: 20px;">
                <h3 class="admin-table-h3">CONTENT <span class="header-span">STATS</span></h3>
                <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 3px solid var(--charcoal); background: #fff; box-shadow: 3px 3px 0px var(--charcoal);">
                        <strong style="font-family: 'Staatliches', sans-serif; font-size: 1.3rem; letter-spacing: 1px;"><i class="fa-solid fa-video" style="margin-right: 10px; color: var(--primary);"></i> TOTAL REELS</strong>
                        <span style="font-size: 1.5rem; font-family: 'Staatliches', sans-serif;"><?php echo $reels_count; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 3px solid var(--charcoal); background: #fff; box-shadow: 3px 3px 0px var(--charcoal);">
                        <strong style="font-family: 'Staatliches', sans-serif; font-size: 1.3rem; letter-spacing: 1px;"><i class="fa-solid fa-question-circle" style="margin-right: 10px; color: var(--primary);"></i> Q&A POSTS</strong>
                        <span style="font-size: 1.5rem; font-family: 'Staatliches', sans-serif;"><?php echo $qna_count; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 3px solid var(--charcoal); background: #fff; box-shadow: 3px 3px 0px var(--charcoal);">
                        <strong style="font-family: 'Staatliches', sans-serif; font-size: 1.3rem; letter-spacing: 1px;"><i class="fa-solid fa-bullhorn" style="margin-right: 10px; color: var(--primary);"></i> SHOUTOUTS</strong>
                        <span style="font-size: 1.5rem; font-family: 'Staatliches', sans-serif;"><?php echo $shoutouts_count; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 3px solid var(--charcoal); background: #fff; box-shadow: 3px 3px 0px var(--charcoal);">
                        <strong style="font-family: 'Staatliches', sans-serif; font-size: 1.3rem; letter-spacing: 1px;"><i class="fa-solid fa-star" style="margin-right: 10px; color: var(--primary);"></i> REVIEWS</strong>
                        <span style="font-size: 1.5rem; font-family: 'Staatliches', sans-serif;"><?php echo $reviews_count; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="grainy-card" style="padding: 20px; margin-top: 2rem; margin-bottom: 3rem;">
            <h3 class="admin-table-h3">RECENT <span class="header-span">ACTIVITY</span></h3>
            
            <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 1.5rem;">
                <?php if (empty($recent_activity)): ?>
                    <p style="text-align: center; font-family: 'Staatliches', sans-serif; font-size: 1.2rem;">NO RECENT ACTIVITY.</p>
                <?php else: ?>
                    <?php foreach ($recent_activity as $act): ?>
                        <div style="display: flex; align-items: center; gap: 15px; padding: 15px; border: 3px solid var(--charcoal); background: #fff;">
                            <?php if ($act['type'] == 'PRODUCT_ADDED'): ?>
                                <div class="activity-icon product"><i class="fa-solid fa-box"></i></div>
                            <?php elseif ($act['type'] == 'ORDER_PLACED'): ?>
                                <div class="activity-icon order"><i class="fa-solid fa-shopping-cart"></i></div>
                            <?php elseif ($act['type'] == 'USER_REGISTERED'): ?>
                                <div class="activity-icon user"><i class="fa-solid fa-user"></i></div>
                            <?php endif; ?>
                            
                            <div style="flex-grow: 1;">
                                <h4 style="margin: 0; font-family: 'Staatliches', sans-serif; font-size: 1.3rem; letter-spacing: 1px;"><?php echo htmlspecialchars($act['title']); ?></h4>
                                <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: #555; text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars($act['meta']); ?></p>
                            </div>
                            
                            <div style="text-align: right;">
                                <span style="font-family: 'Staatliches', sans-serif; color: var(--charcoal); font-size: 1.1rem;">
                                    <?php 
                                        $time_diff = time() - strtotime($act['date']);
                                        if ($time_diff < 3600) {
                                            echo floor($time_diff / 60) . " mins ago";
                                        } elseif ($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . " hours ago";
                                        } else {
                                            echo date('M j', strtotime($act['date']));
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>
</section>

<!-- Chart.js Initialization -->
<script>
    // Common Chart Configuration to match Brutalist theme
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#2D2D2D';
    Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.1)';
    Chart.defaults.plugins.tooltip.backgroundColor = '#2D2D2D';
    Chart.defaults.plugins.tooltip.titleFont = { family: "'Staatliches', sans-serif", size: 16 };
    Chart.defaults.plugins.tooltip.bodyFont = { size: 14 };
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 0;

    // 1. Sales Analytics Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($sales_labels); ?>,
            datasets: [
                {
                    label: 'Shop Sales ($)',
                    data: <?php echo json_encode($sales_shop_data); ?>,
                    backgroundColor: '#E11D48',
                    borderColor: '#2D2D2D',
                    borderWidth: 2
                },
                {
                    label: 'Market Sales ($)',
                    data: <?php echo json_encode($sales_market_data); ?>,
                    backgroundColor: '#3b82f6',
                    borderColor: '#2D2D2D',
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: "'Staatliches', sans-serif", size: 14 }
                    }
                }
            }
        }
    });

    // 2. Marketplace Activity Chart
    const marketCtx = document.getElementById('marketChart').getContext('2d');
    new Chart(marketCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Sold', 'Pending'],
            datasets: [{
                data: [
                    <?php echo $market_active; ?>, 
                    <?php echo $market_sold; ?>, 
                    <?php echo $market_pending; ?>
                ],
                backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b'],
                borderColor: '#2D2D2D',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: "'Staatliches', sans-serif", size: 14 }
                    }
                }
            }
        }
    });

    // 3. User Growth Chart
    const userCtx = document.getElementById('userChart').getContext('2d');
    new Chart(userCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($user_labels); ?>,
            datasets: [{
                label: 'New Registrations',
                data: <?php echo json_encode($user_data); ?>,
                borderColor: '#E11D48',
                backgroundColor: 'rgba(225, 29, 72, 0.2)',
                borderWidth: 3,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#2D2D2D',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: "'Staatliches', sans-serif", size: 14 }
                    }
                }
            }
        }
    });
</script>

</body>
</html>