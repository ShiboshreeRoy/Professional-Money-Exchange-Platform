<?php
// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Database configuration from environment variables
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_USERNAME', EnvLoader::get('DB_USERNAME', 'root'));
define('DB_PASSWORD', EnvLoader::get('DB_PASSWORD', ''));
define('DB_NAME', EnvLoader::get('DB_NAME', 'website_db'));
define('DB_PORT', EnvLoader::get('DB_PORT', '3306'));

// Application settings
define('APP_NAME', EnvLoader::get('APP_NAME', 'Professional Website'));
define('APP_ENV', EnvLoader::get('APP_ENV', 'local'));
define('APP_DEBUG', EnvLoader::get('APP_DEBUG', true));
define('APP_URL', EnvLoader::get('APP_URL', 'http://localhost/fff'));

// Create connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Enable error reporting in debug mode
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>