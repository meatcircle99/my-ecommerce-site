<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db.php'; // Adjust path as needed

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle coupon application via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $coupon_code = trim($_POST['coupon_code']);
    $current_date = date('Y-m-d');

    try {
        // Case-insensitive coupon code check using UPPER
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE UPPER(code) = UPPER(?) AND expiry_date >= ? AND status = 'active' LIMIT 1");
        $stmt->execute([$coupon_code, $current_date]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($coupon) {
            $discount_percentage = floatval($coupon['discount']);
            $response = [
                'status' => 'success',
                'message' => "Coupon applied successfully! You saved {$discount_percentage}% on your order.",
                'discount_percentage' => $discount_percentage
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Invalid, expired, or inactive coupon code.'
            ];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (PDOException $e) {
        error_log("Database error validating coupon: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error validating coupon: ' . $e->getMessage()]);
    }
    exit;
}

// Handle coupon list retrieval (for potential future use or debugging)
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    try {
        $stmt = $conn->query("SELECT code, discount, expiry_date, status FROM coupons ORDER BY created_at DESC");
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'coupons' => $coupons]);
    } catch (PDOException $e) {
        error_log("Database error fetching coupons: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error fetching coupons: ' . $e->getMessage()]);
    }
    exit;
}

// Default response for invalid requests
header('HTTP/1.1 400 Bad Request');
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid endpoint. Use POST with apply_coupon or GET with action=list.']);
exit;