<?php
require_once 'config/config.php';

// Create tables if they don't exist
$sql_queries = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    // Prices table
    "CREATE TABLE IF NOT EXISTS prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        currency_type VARCHAR(50) NOT NULL,
        rate DECIMAL(10, 2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    // Cards table
    "CREATE TABLE IF NOT EXISTS cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        card_type VARCHAR(50) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        quantity INT NOT NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50),
        payment_number VARCHAR(50),
        coupon_code VARCHAR(50) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Insert default prices
    "INSERT IGNORE INTO prices (currency_type, rate, description) VALUES
    ('PayPal_USD', 115.00, 'PayPal US Dollar to BDT'),
    ('PayPal_UK', 113.00, 'PayPal UK Pound to BDT'),
    ('Apple_Gift_Card', 104.00, 'Apple Gift Card to BDT'),
    ('ACH_Bank', 115.00, 'ACH Bank to BDT')"
];

foreach ($sql_queries as $query) {
    if (!$conn->query($query)) {
        echo "Error creating table: " . $conn->error . "\n";
    }
}

// Create admin user if not exists
$admin_username = 'admin';
$admin_email = 'admin@example.com';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);

$check_admin = $conn->query("SELECT id FROM users WHERE username='$admin_username'");
if ($check_admin->num_rows == 0) {
    $insert_admin = "INSERT INTO users (username, email, password, role) VALUES ('$admin_username', '$admin_email', '$admin_password', 'admin')";
    if ($conn->query($insert_admin)) {
        echo "Admin user created successfully!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } else {
        echo "Error creating admin user: " . $conn->error . "\n";
    }
}

echo "Database setup completed successfully!\n";
$conn->close();
?>