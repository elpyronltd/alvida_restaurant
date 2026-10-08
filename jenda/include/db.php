<?php
/**
 * Jenda Restaurant - Database Connection
 * Establishes a secure PDO connection to the MySQL database.
 */

// Database Configuration
$host = 'localhost';
$db = 'jenda_restaurant_db';
$user = 'root';
$pass = ''; // Set your database password here if applicable
$charset = 'utf8mb4';

// PDO DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// PDO Options for Security and Performance
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
    PDO::ATTR_EMULATE_PREPARES => false,                  // Use actual prepared statements for security
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"     // Force UTF-8 communication
];

try {
    // Instantiate PDO instance
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log the error internally (never output raw connection details to the browser/client)
    error_log("Database Connection Failure: " . $e->getMessage());

    // Display a user-friendly error message
    die("<h3>Database connection error</h3><p>We are experiencing technical difficulties. Please try again later.</p>");
}
?>