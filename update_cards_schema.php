<?php
require_once 'config/config.php';

// Add card_image column to cards table if it doesn't exist
$check_image = $conn->query("SHOW COLUMNS FROM cards LIKE 'card_image'");
if ($check_image->num_rows == 0) {
    $conn->query("ALTER TABLE cards ADD COLUMN card_image VARCHAR(255)");
    echo "Added card_image column to cards table\n";
} else {
    echo "card_image column already exists\n";
}

// Add card_details column to cards table if it doesn't exist
$check_details = $conn->query("SHOW COLUMNS FROM cards LIKE 'card_details'");
if ($check_details->num_rows == 0) {
    $conn->query("ALTER TABLE cards ADD COLUMN card_details TEXT");
    echo "Added card_details column to cards table\n";
} else {
    echo "card_details column already exists\n";
}

// Add transaction_id column to cards table if it doesn't exist
$check_trans = $conn->query("SHOW COLUMNS FROM cards LIKE 'transaction_id'");
if ($check_trans->num_rows == 0) {
    $conn->query("ALTER TABLE cards ADD COLUMN transaction_id VARCHAR(100)");
    echo "Added transaction_id column to cards table\n";
} else {
    echo "transaction_id column already exists\n";
}

// Create a sample card_images table to store card image metadata
$image_table = $conn->query("
    CREATE TABLE IF NOT EXISTS card_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
    )
");

echo "Database schema updated successfully!\n";
$conn->close();
?>