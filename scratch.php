<?php
$host = "localhost";
$username = "grobina1_jaunarajs";
$password = 'Nej$v3Hw0J7t';
$database = "grobina1_jaunarajs";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    echo "-- Table: $table\n";
    $res2 = $conn->query("SHOW CREATE TABLE `$table`");
    if ($row2 = $res2->fetch_array()) {
        echo $row2[1] . ";\n\n";
    }
}
?>
