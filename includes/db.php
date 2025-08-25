<?php
// Database connection file - includes/db.php

// Only define constants if they haven't been defined yet
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'talent_acquisition');
}

// Create PDO database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // Keep the MySQLi connection for backward compatibility if needed
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check MySQLi connection
    if ($connection->connect_error) {
        throw new Exception("MySQLi connection failed: " . $connection->connect_error);
    }
    
    // Set charset for MySQLi
    $connection->set_charset("utf8mb4");
    
} catch (PDOException $e) {
    die("PDO Database connection error: " . $e->getMessage());
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}
?>