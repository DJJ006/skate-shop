<?php
include 'db.php';
$res = $conn->query('SHOW CREATE TABLE orders');
print_r($res->fetch_assoc());
$res = $conn->query('SHOW CREATE TABLE products');
print_r($res->fetch_assoc());
$res = $conn->query('SHOW CREATE TABLE users');
print_r($res->fetch_assoc());
?>
