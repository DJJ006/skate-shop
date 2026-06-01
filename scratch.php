<?php
include 'db.php';
$res = $conn->query("SHOW COLUMNS FROM users");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
