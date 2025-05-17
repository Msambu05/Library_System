<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change this in production!
define('DB_PASS', '');     // Change this in production!
define('DB_NAME', 'librarydb');
define('DB_PORT', 3306);

// Error reporting (development only)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Create PDO instance
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";port=".DB_PORT.";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Set timezone if needed
    $conn->exec("SET time_zone = '+00:00'");
    
} catch (PDOException $e) {
    // Log error securely
    error_log("Database connection failed: " . $e->getMessage());
    
    // Don't expose errors to users
    die("System maintenance in progress. Please try again later.");
}
    ?>