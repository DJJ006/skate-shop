<?php
include 'db.php';
$res = $conn->query('SHOW COLUMNS FROM product_reviews');
echo "product_reviews columns:\n";
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "\nseller_ratings columns:\n";
$res2 = $conn->query('SHOW COLUMNS FROM seller_ratings');
while ($row2 = $res2->fetch_assoc()) {
    echo $row2['Field'] . "\n";
}
?>
