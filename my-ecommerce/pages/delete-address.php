<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
include '../config/db.php';

$address_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return_to = isset($_GET['return_to']) ? $_GET['return_to'] : 'account'; // Default to account if not specified

$stmt = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
$stmt->execute([$address_id, $_SESSION['user_id']]);

$cacheFile = '../cache/addresses_' . $_SESSION['user_id'] . '.html';
if (file_exists($cacheFile)) unlink($cacheFile);

header("Location: $return_to.php");
exit;