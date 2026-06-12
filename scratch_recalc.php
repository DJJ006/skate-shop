<?php
include 'db.php';

echo "Recalculating product ratings...\n";

$sql = "UPDATE products p 
        SET p.average_rating = (SELECT IFNULL(AVG(rating), 0) FROM product_reviews WHERE product_id = p.id AND status = 'Approved'),
            p.review_count = (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'Approved')";

if ($conn->query($sql)) {
    echo "Successfully updated " . $conn->affected_rows . " products.\n";
} else {
    echo "Error updating products: " . $conn->error . "\n";
}
