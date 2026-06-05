<?php
$conn = mysqli_connect('localhost', 'root', '', 'skateshop');
$res=$conn->query('SHOW CREATE TABLE orders');
print_r($res->fetch_assoc());
$res2=$conn->query('SHOW CREATE TABLE products');
print_r($res2->fetch_assoc());
?>
