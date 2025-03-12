<?php
$host = 'localhost';
$dbname = 'ecommerce_db1';
$username = 'root';
$password = ''; // Default XAMPP password is empty

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed");
}
?>