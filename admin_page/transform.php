<?php
$content = file_get_contents('review-sellers.php');
$content = str_replace('review-products.php', 'review-sellers.php', $content);
$content = preg_replace('/product_reviews/i', 'seller_ratings', $content);
$content = str_replace('r.user_id', 'r.buyer_id', $content);
$content = str_replace('r.product_id', 'r.seller_id', $content);
$content = str_replace('p.title as product_title', 'u_seller.username as seller_username', $content);
$content = str_replace('product_title', 'seller_username', $content);
$content = str_replace('JOIN products p ON r.seller_id = p.id', 'JOIN users u_seller ON r.seller_id = u_seller.id', $content);
$content = str_replace("recalc_product_rating(\$conn, \$row['seller_id']);", "recalculateSellerStats(\$conn, \$row['seller_id']); checkAutoVerification(\$conn, \$row['seller_id']);", $content);
$content = str_replace('recalc_product_rating', 'recalculateSellerStats', $content);
$content = str_replace('product_image', 'profile_pic', $content);
$content = str_replace('p.image_url', 'u_seller.profile_pic', $content);
$content = str_replace('u.username', 'u.username as buyer_username', $content);
$content = str_replace('MODERATE REVIEWS', 'MODERATE SELLER REVIEWS', $content);
$content = str_replace('GEAR REVIEWS', 'SELLER REVIEWS', $content);
$content = str_replace('GEAR_TITLE', 'SELLER_USERNAME', $content);
$content = str_replace("['product_id']", "['seller_id']", $content);
$content = str_replace("['product_id']", "['seller_id']", $content);

file_put_contents('review-sellers.php', $content);
