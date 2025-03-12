<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
include '../config/db.php'; // Path from pages/ to root config/

$address_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

try {
    // Add index if not exists
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_addresses_user_default ON addresses(user_id, is_default)");

    // Unset any existing default address
    $stmt = $conn->prepare("UPDATE addresses SET is_default = FALSE WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Set the new default address
    $stmt = $conn->prepare("UPDATE addresses SET is_default = TRUE WHERE id = ? AND user_id = ?");
    $stmt->execute([$address_id, $user_id]);
    header('Location: address.php'); // Use relative path for same directory
    exit;
} catch (PDOException $e) {
    error_log("Error setting default address: " . $e->getMessage());
    header('Location: address.php?error=' . urlencode('Error setting default address: ' . htmlspecialchars($e->getMessage())));
    exit;
}