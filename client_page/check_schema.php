<?php
include '../db.php';
echo "--- PRODUCTS ---\n";
$res = $conn->query("DESCRIBE products");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
echo "\n--- ORDERS ---\n";
$res = $conn->query("DESCRIBE orders");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
