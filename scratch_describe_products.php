<?php
include 'c:/Users/meguc/Desktop/DJJ/WEB/BackEnd/serveraFaili/skate-shop/db.php';
$res = $conn->query('DESCRIBE products');
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
