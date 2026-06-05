<?php
require 'db.php';

$queries = [
    "ALTER TABLE orders ADD COLUMN order_group_id VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(10,2) DEFAULT 0.00"
];

foreach ($queries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Successfully executed: $query\n";
    } else {
        echo "Error or already exists: " . $conn->error . "\n";
    }
}
?>
