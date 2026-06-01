<?php
include 'db.php';
$tables = [];
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

foreach($tables as $table) {
    echo "--- TABLE: $table ---\n";
    $desc = $conn->query("DESCRIBE $table");
    while($row = $desc->fetch_assoc()) {
        $pk = ($row['Key'] == 'PRI') ? 'PK' : '';
        $fk = ($row['Key'] == 'MUL') ? 'FK' : '';
        echo "{$row['Field']} | {$row['Type']} | {$pk} {$fk}\n";
    }
    echo "\n";
}
?>
