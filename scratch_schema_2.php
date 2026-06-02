<?php
$conn = mysqli_connect('localhost', 'root', '', 'skateshop');
if (!$conn) die('Connection failed');
$res = $conn->query('SHOW TABLES');
$output = '';
while ($row = $res->fetch_row()) {
    $output .= "Table: " . $row[0] . "\n";
    $res2 = $conn->query('DESCRIBE ' . $row[0]);
    while ($row2 = $res2->fetch_assoc()) {
        $output .= "  - " . $row2['Field'] . " (" . $row2['Type'] . ")\n";
    }
}
file_put_contents('schema_dump.txt', $output);
