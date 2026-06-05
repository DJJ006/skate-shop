<?php
include 'c:/Users/meguc/Desktop/DJJ/WEB/BackEnd/serveraFaili/skate-shop/db.php';
$res=$conn->query('SHOW CREATE TABLE orders');
print_r($res->fetch_assoc());
$res2=$conn->query('SHOW CREATE TABLE products');
print_r($res2->fetch_assoc());
?>
