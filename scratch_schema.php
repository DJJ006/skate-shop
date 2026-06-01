<?php
include 'db.php';
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $table = $row[0];
    $tables[] = $table;
}
foreach ($tables as $table) {
    echo "TABLE: $table\n";
    $res = $conn->query("DESCRIBE $table");
    while ($col = $res->fetch_assoc()) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "\n";
}
?>
