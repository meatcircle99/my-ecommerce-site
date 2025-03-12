<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db.php';

// Optional: Check for AJAX request (commented out for now to test)
if (false && (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'This endpoint is for AJAX requests only']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to perform this action']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check maintenance mode
try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance_mode = $stmt->fetchColumn() === '1';
} catch (PDOException $e) {
    error_log("Error checking maintenance mode: " . $e->getMessage());
    $maintenance_mode = false;
}

if ($maintenance_mode) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'The website is under maintenance. All wishlist actions are disabled.']);
    exit;
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("AJAX request received in wishlist_action.php: " . print_r($_POST, true)); // Debug log

    if (isset($_POST['remove_wishlist'])) {
        $wishlist_id = (int)$_POST['wishlist_id'];
        try {
            $stmt = $conn->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
            $stmt->execute([$wishlist_id, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Product removed from wishlist']);
        } catch (PDOException $e) {
            error_log("Database error in wishlist_action.php (remove): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error removing from wishlist']);
        }
    } elseif (isset($_POST['add_to_cart'])) {
        $product_id = (int)$_POST['product_id'];
        $quantity = floatval($_POST['quantity']);
        try {
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product || $product['stock_quantity'] < $quantity) {
                echo json_encode(['status' => 'error', 'message' => 'Product not available or insufficient stock']);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
            $stmt->execute([$user_id, $product_id, $quantity, $quantity]);

            $new_stock = $product['stock_quantity'] - $quantity;
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$new_stock, $product_id]);

            $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);

            echo json_encode(['status' => 'success', 'message' => 'Product added to cart and removed from wishlist']);
        } catch (PDOException $e) {
            error_log("Database error in wishlist_action.php (add_to_cart): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error adding to cart']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action (missing add_to_cart or remove_wishlist)']);
    }
    exit;
}

// If not a POST request, return an error
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
exit;
?>