<?php
include 'db.php';

// Check and add new columns to `users` table
$users_columns = [
    'verification_type' => "VARCHAR(20) DEFAULT NULL",
    'verification_date' => "DATETIME DEFAULT NULL",
    'verification_admin_id' => "INT DEFAULT NULL",
    'total_sales' => "INT DEFAULT 0",
    'total_seller_reviews' => "INT DEFAULT 0",
    'average_seller_rating' => "DECIMAL(3,2) DEFAULT 0.00",
    'verified_status' => "VARCHAR(20) DEFAULT 'None'"
];

foreach ($users_columns as $col => $type) {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN $col $type");
        echo "Added $col to users table.\n";
    }
}

// Migrate is_verified to verified_status if it exists
$check_is_verified = $conn->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
if ($check_is_verified->num_rows > 0) {
    $conn->query("UPDATE users SET verified_status = 'Verified' WHERE is_verified = 1");
    // We will keep is_verified for backward compatibility and ease of use, as requested in plan (we use both or rely on is_verified mostly)
    echo "Migrated is_verified to verified_status.\n";
}

// Alter `seller_ratings`
$ratings_columns = [
    'comment' => "VARCHAR(100) DEFAULT NULL",
    'status' => "VARCHAR(20) DEFAULT 'Pending Approval'",
    'transaction_id' => "INT DEFAULT NULL",
    'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

$check_table = $conn->query("SHOW TABLES LIKE 'seller_ratings'");
if ($check_table->num_rows > 0) {
    foreach ($ratings_columns as $col => $type) {
        $check = $conn->query("SHOW COLUMNS FROM seller_ratings LIKE '$col'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE seller_ratings ADD COLUMN $col $type");
            echo "Added $col to seller_ratings table.\n";
        }
    }
} else {
    // Create if it doesn't exist for some reason
    $sql = "CREATE TABLE seller_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        buyer_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        transaction_id INT DEFAULT NULL,
        rating INT NOT NULL,
        comment VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'Pending Approval',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    echo "Created seller_ratings table.\n";
}

echo "Migration completed successfully.\n";
