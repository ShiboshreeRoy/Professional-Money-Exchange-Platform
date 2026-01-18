<?php
require_once 'config/config.php';

// Check if admin_cards table exists
$check_admin_cards = $conn->query("SHOW TABLES LIKE 'admin_cards'");
if ($check_admin_cards->num_rows == 0) {
    $sql = "CREATE TABLE admin_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_type VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        sell_price DECIMAL(10,2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "Created admin_cards table successfully\n";
        
        // Insert some sample data
        $sample_cards = [
            ['PayPal_USD', 50.00, 10, 45.00, 'Verified PayPal account with $50 balance'],
            ['Apple_Gift_Card', 25.00, 5, 22.00, 'Apple Gift Card - iTunes/App Store'],
            ['PayPal_UK', 30.00, 8, 27.00, 'UK PayPal account with £30 balance'],
            ['ACH_Bank', 100.00, 3, 95.00, 'ACH bank transfer ready account']
        ];
        
        foreach ($sample_cards as $card) {
            $stmt = $conn->prepare("INSERT INTO admin_cards (card_type, amount, quantity, sell_price, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdids", $card[0], $card[1], $card[2], $card[3], $card[4]);
            $stmt->execute();
            $stmt->close();
        }
        
        echo "Added sample admin cards to inventory\n";
    } else {
        echo "Error creating admin_cards table: " . $conn->error . "\n";
    }
} else {
    echo "Admin cards table already exists\n";
}

$conn->close();
?>