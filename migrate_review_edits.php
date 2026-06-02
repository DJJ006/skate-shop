<?php
require 'db.php';
$conn->query("ALTER TABLE product_reviews ADD COLUMN previous_rating INT DEFAULT NULL");
$conn->query("ALTER TABLE product_reviews ADD COLUMN previous_comment VARCHAR(100) DEFAULT NULL");
echo "Migration successful.\n";
?>
