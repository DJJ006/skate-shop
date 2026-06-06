<?php
$_SESSION['admin_username'] = 'TestAdmin';
ob_start();
include 'admin_header.php';
$html = ob_get_clean();
echo $html;
?>

