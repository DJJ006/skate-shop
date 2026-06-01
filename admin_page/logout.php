<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Destroy session tokens related to admin
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
// We do not destroy the entire session, in case they are also logged into the client panel on the same browser.

header("Location: admin-login.php");
exit();
?>
