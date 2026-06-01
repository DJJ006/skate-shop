<?php
$files = [
    'accept-product.php',
    'accept-reels.php',
    'cancel-order.php',
    'client-orders.php',
    'get-ticket.php',
    'index.php',
    'mag.php',
    'marketplace-products.php',
    'reels.php',
    'registered-users.php',
    'reports.php',
    'review-qna.php',
    'review-shoutouts.php',
    'shop-products.php',
    'verify-seller.php'
];

foreach ($files as $file) {
    $path = 'admin_page/' . $file;
    if (!file_exists($path)) continue;
    
    $content = file_get_contents($path);
    
    // Check if it already has admin_auth.php
    if (strpos($content, 'admin_auth.php') !== false) {
        continue;
    }

    // Usually files start with <?php \n include '../db.php'; or similar
    // We want to insert require_once 'admin_auth.php'; right after <?php
    
    // Sometimes there are session_start() or checks for admin_logged_in. 
    // We can just inject it at the top.
    
    $content = preg_replace('/<\?php\s*/', "<?php\nrequire_once 'admin_auth.php';\n", $content, 1);
    
    file_put_contents($path, $content);
    echo "Updated $file\n";
}
?>
