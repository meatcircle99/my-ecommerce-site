<?php
session_start();
include '../config/db.php';

// Check if user or admin is logged in
$is_admin = isset($_GET['is_admin']) && $_GET['is_admin'] == 1;
if (!$is_admin && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
} elseif ($is_admin && !isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Include Dompdf
require_once '../libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Fetch order details (admin can see all orders, user only their own)
if ($is_admin) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id");
    $stmt->execute([':id' => $order_id]);
} else {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $order_id, ':user_id' => $user_id]);
}
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found or you do not have permission to access this order.");
}

// Fetch order items
$item_stmt = $conn->prepare("SELECT p.name, oi.quantity, oi.price 
                             FROM order_items oi 
                             JOIN products p ON oi.product_id = p.id 
                             WHERE oi.order_id = :order_id");
$item_stmt->execute([':order_id' => $order_id]);
$items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate subtotal
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Calculate discount (difference between subtotal and total_amount)
$discount_amount = $subtotal - $order['total_amount'];

// Fetch shipping address
$address_stmt = $conn->prepare("SELECT address, city, pincode FROM addresses WHERE id = :address_id");
$address_stmt->execute([':address_id' => $order['address_id']]);
$address = $address_stmt->fetch(PDO::FETCH_ASSOC);

// Generate HTML invoice content with DejaVu Sans font
$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #' . $order_id . '</title>
    <style>
        @font-face {
            font-family: "DejaVu Sans";
            src: url("dompdf/lib/fonts/DejaVuSans.ttf") format("truetype");
        }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #124E66;
            background: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #124E66;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #124E66;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            font-size: 12px;
            color: #666;
        }
        .details, .address, .items {
            margin-bottom: 20px;
        }
        .details p {
            margin: 5px 0;
            font-size: 14px;
        }
        .address p {
            margin: 5px 0;
            font-size: 14px;
        }
        .items table {
            width: 100%;
            border-collapse: collapse;
        }
        .items th, .items td {
            border: 1px solid #124E66;
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }
        .items th {
            background: #124E66;
            color: #fff;
        }
        .items .total {
            font-weight: bold;
            text-align: right;
            padding-top: 10px;
            font-size: 16px;
        }
        .summary {
            margin-top: 10px;
            font-size: 14px;
            text-align: right;
        }
        .summary div {
            margin-bottom: 5px;
        }
        .summary .discount {
            color: #2e7d32;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #124E66;
            padding-top: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <h1>Meatcircle Invoice</h1>
            <p>Your Trusted Meat Supplier</p>
            <p>Invoice #' . $order_id . '</p>
        </div>
        <div class="details">
            <p><strong>Date:</strong> ' . date('Y-m-d H:i', strtotime($order['created_at'])) . '</p>
            <p><strong>Payment Method:</strong> ' . ucfirst($order['payment_method']) . '</p>
            <p><strong>Status:</strong> ' . ucfirst($order['status']) . '</p>';

if ($is_admin) {
    $html .= '<p><strong>User ID:</strong> ' . $order['user_id'] . '</p>';
}

$html .= '</div>';

if ($address) {
    $html .= '<div class="address">
            <p><strong>Shipping Address:</strong></p>
            <p>' . htmlspecialchars($address['address']) . ', ' . htmlspecialchars($address['city']) . ' - ' . htmlspecialchars($address['pincode']) . '</p>
        </div>';
}

$html .= '<div class="items">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity (kg)</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>';

foreach ($items as $item) {
    $html .= '<tr>
                <td>' . htmlspecialchars($item['name']) . '</td>
                <td>' . number_format($item['quantity'], 2) . '</td>
                <td>₹' . number_format($item['price'] * $item['quantity'], 2) . '</td>
            </tr>';
}

$html .= '</tbody>
            </table>
            <div class="summary">
                <div><strong>Subtotal:</strong> ₹' . number_format($subtotal, 2) . '</div>';
if ($discount_amount > 0) {
    $html .= '<div class="discount"><strong>Discount:</strong> -₹' . number_format($discount_amount, 2) . '</div>';
}
$html .= '<div class="total"><strong>Total:</strong> ₹' . number_format($order['total_amount'], 2) . '</div>
            </div>
        </div>
        <div class="footer">
            <p>Meatcircle - Contact: support@meatcircle.com | Phone: +91 123-456-7890</p>
            <p>Thank you for choosing Meatcircle!</p>
        </div>
    </div>
</body>
</html>';

// Initialize Dompdf
$dompdf = new Dompdf();

// Load HTML content
$dompdf->loadHtml($html, 'UTF-8');

// Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the PDF
$dompdf->render();

// Output the PDF for auto-download
$dompdf->stream("invoice_{$order_id}.pdf", ['Attachment' => true]);
exit;
?>