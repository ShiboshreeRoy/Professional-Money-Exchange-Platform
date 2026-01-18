<?php
require_once 'config/config.php';

// Add super_admin column to users table if it doesn't exist
$check_super_admin = $conn->query("SHOW COLUMNS FROM users LIKE 'super_admin'");
if ($check_super_admin->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN super_admin BOOLEAN DEFAULT FALSE");
    echo "Added super_admin column to users table\n";
}

// Add additional columns for enhanced features
$check_columns = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
    echo "Added is_active column to users table\n";
}

$check_balance = $conn->query("SHOW COLUMNS FROM users LIKE 'balance'");
if ($check_balance->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN balance DECIMAL(10,2) DEFAULT 0.00");
    echo "Added balance column to users table\n";
}

$check_verification = $conn->query("SHOW COLUMNS FROM users LIKE 'verified'");
if ($check_verification->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN verified BOOLEAN DEFAULT FALSE");
    echo "Added verified column to users table\n";
}

// Create transactions table if it doesn't exist
$transactions_table = $conn->query("
    CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('deposit', 'withdrawal', 'exchange', 'commission') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency_from VARCHAR(10),
        currency_to VARCHAR(10),
        rate DECIMAL(10,4),
        fee DECIMAL(10,2),
        status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
        reference VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

// Create commissions table if it doesn't exist
$commissions_table = $conn->query("
    CREATE TABLE IF NOT EXISTS commissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        card_id INT,
        amount DECIMAL(10,2) NOT NULL,
        percentage DECIMAL(5,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL
    )
");

// Create notifications table if it doesn't exist
$notifications_table = $conn->query("
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

// Create settings table if it doesn't exist
$settings_table = $conn->query("
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Insert default settings
$default_settings = [
    ['maintenance_mode', '0', 'Site maintenance mode'],
    ['min_deposit', '10.00', 'Minimum deposit amount'],
    ['max_withdrawal', '10000.00', 'Maximum withdrawal amount'],
    ['commission_rate', '2.00', 'Commission rate percentage'],
    ['auto_approve_limit', '100.00', 'Auto-approve cards up to this amount'],
    ['verification_required', '1', 'Require user verification'],
];

foreach ($default_settings as $setting) {
    $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
    $stmt->execute();
    $stmt->close();
}

// Update existing admin user to be super admin
$update_admin = $conn->query("UPDATE users SET super_admin = TRUE, role = 'admin' WHERE username = 'admin'");
if ($update_admin) {
    echo "Updated admin user to super admin\n";
}

echo "Database schema updated successfully!\n";
$conn->close();
?>