<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Define fixed shipping cost (same as in cart.php and payments.php)
$shipping_cost = 30.00;

// Initialize session variable for cancel message
if (!isset($_SESSION['cancel_message'])) {
    $_SESSION['cancel_message'] = '';
}

// Handle order cancellation
$updated_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order']) && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    try {
        // Log the attempt
        error_log("Attempting to cancel order ID: $order_id for user ID: $user_id");

        // Update the order status
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancellation_note = 'Cancelled by Customer' WHERE id = :order_id AND user_id = :user_id AND status = 'pending'");
        $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
        $affected_rows = $stmt->rowCount();

        if ($affected_rows > 0) {
            // Wait briefly to allow database update to propagate
            usleep(100000); // 100ms delay
            // Verify the update
            $verify_stmt = $conn->prepare("SELECT status FROM orders WHERE id = :order_id");
            $verify_stmt->execute([':order_id' => $order_id]);
            $updated_status = $verify_stmt->fetchColumn() ?: 'cancelled'; // Default to 'cancelled' if null

            error_log("Order ID: $order_id cancelled successfully (affected rows: $affected_rows), verified status: $updated_status");
            $_SESSION['cancel_message'] = 'Order cancelled successfully.';
            $_SESSION['updated_order_id'] = $order_id;
            $_SESSION['updated_status'] = $updated_status;
        } else {
            error_log("Failed to cancel order ID: $order_id for user ID: $user_id - No rows affected");
            $_SESSION['cancel_message'] = 'Order cannot be cancelled (status is not pending or already processed).';
        }

        // Redirect to avoid form resubmission
        header('Location: orders.php');
        exit;
    } catch (PDOException $e) {
        error_log("Error cancelling order ID: $order_id for user ID: $user_id - " . $e->getMessage());
        $_SESSION['cancel_message'] = 'Error cancelling order: ' . $e->getMessage();
        header('Location: orders.php');
        exit;
    }
}

// Handle reorder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder']) && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    try {
        // Verify the order belongs to the user
        $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
        if ($stmt->fetch()) {
            // Fetch order items
            $stmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                // Check if product exists (assuming active products are available)
                $stmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                if ($stmt->fetch()) {
                    // Check if already in cart
                    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$user_id, $product_id]);
                    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($cart_item) {
                        // Update quantity
                        $new_quantity = $cart_item['quantity'] + $quantity;
                        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                        $stmt->execute([$new_quantity, $cart_item['id']]);
                    } else {
                        // Insert new cart item
                        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$user_id, $product_id, $quantity]);
                    }
                }
            }
            // Clear coupon session variables to prevent auto-applying discount
            unset($_SESSION['coupon_discount']);
            unset($_SESSION['coupon_code']);
            // Redirect to cart.php
            header('Location: ../cart.php');
            exit;
        } else {
            $_SESSION['reorder_message'] = 'Order not found or not authorized.';
            header('Location: orders.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error reordering order ID: $order_id for user_id: $user_id - " . $e->getMessage());
        $_SESSION['reorder_message'] = 'Error reordering: ' . $e->getMessage();
        header('Location: orders.php');
        exit;
    }
}

require_once '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Meatcircle</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            padding-top: 70px;
            font-family: 'Arial', sans-serif;
            background: #f4f4f4;
            margin: 0;
        }

        .orders-content {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .page-title {
            color: #124E66;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }

        .order-box {
            margin-bottom: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 15px;
            position: relative;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
        }

        .order-header {
            background: #124E66;
            color: #fff;
            padding: 10px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .order-items-table th, .order-items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .order-items-table th {
            background: #ff6b6b;
            color: #fff;
            font-weight: 600;
        }

        .order-items-table tr:nth-child(even) {
            background: #f9f9f9;
        }

        .order-items-table tr:hover {
            background: #f1f1f1;
            transition: background 0.3s;
        }

        .order-details {
            margin-top: 20px;
            color: #333;
        }

        .order-details div {
            margin-bottom: 8px;
        }

        .order-details div:last-child {
            margin-bottom: 0;
        }

        .order-details strong {
            color: #000;
            font-weight: bold;
        }

        .shipping-address {
            display: none;
            padding: 15px;
            background: #f9f9f9;
            margin-top: 10px;
            border-radius: 0 0 8px 8px;
            color: #666;
        }

        .toggle-arrow {
            position: absolute;
            bottom: 15px;
            right: 15px;
            font-size: 16px;
            cursor: pointer;
            color: #124E66;
        }

        .no-orders {
            text-align: center;
            color: #666;
            font-size: 18px;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .download-invoice {
            cursor: pointer;
            color: #fff;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .download-invoice:hover {
            color: #ff6b6b;
        }

        .cancel-form {
            display: inline;
        }

        .cancel-order-btn {
            background: #d32f2f;
            color: #fff;
            padding: 6px 12px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.3s ease, transform 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cancel-order-btn:hover {
            background: #c62828;
            transform: scale(1.05);
        }

        .cancel-order-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .cancel-message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            display: block;
            text-align: center;
        }

        .cancel-message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .cancel-message.error {
            background-color: #ffebee;
            color: #d32f2f;
        }

        /* Reorder styles */
        .order-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        }

        .reorder-form {
            display: inline;
        }

        .reorder-btn {
            background-color: #28a745; /* Matches Meatcircle green theme */
            color: #fff;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
            text-decoration: none;
            display: inline-block;
        }

        .reorder-btn:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.4);
        }

        .reorder-message.error {
            background-color: #ffebee;
            color: #d32f2f;
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }

        /* Status-specific styling with borders */
        .status-pending { 
            background: #fff3e0; 
            color: #e65100; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #e65100; 
        }
        .status-accepted { 
            background: #e1f5fe; 
            color: #01579b; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #01579b; 
        }
        .status-processing { 
            background: #fff8e1; 
            color: #f57c00; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #f57c00; 
        }
        .status-ready-for-shipping { 
            background: #e0f7fa; 
            color: #00695c; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #00695c; 
        }
        .status-shipped { 
            background: #e8f5e9; 
            color: #2e7d32; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #2e7d32; 
        }
        .status-out-for-delivery { 
            background: #fffde7; 
            color: #f9a825; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #f9a825; 
        }
        .status-delivered { 
            background: #e8f5e9; 
            color: #2e7d32; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #2e7d32; 
        }
        .status-cancelled { 
            background: #ffebee; 
            color: #d32f2f; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #d32f2f; 
        }
        .status-unknown { 
            background: #f0f0f0; 
            color: #666; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            display: inline-block; 
            border: 1px solid #666; 
        }

        /* Discount and shipping row styling */
        .discount-row {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 8px;
            border-radius: 8px;
            margin-top: 10px;
            display: inline-block;
        }

        .shipping-row {
            background: #f1f1f1;
            padding: 4px 8px;
            border-radius: 8px;
            margin-top: 10px;
            display: inline-block;
        }

        .delivery-row {
            background: #e0f7fa;
            color: #00695c;
            padding: 4px 8px;
            border-radius: 8px;
            margin-top: 10px;
            display: inline-block;
        }

        @media (max-width: 768px) {
            .orders-content {
                padding: 10px;
            }

            .order-items-table th, .order-items-table td {
                padding: 8px;
                font-size: 14px;
            }

            .order-box {
                margin-bottom: 15px;
            }

            .order-details {
                margin-top: 15px;
            }

            .order-details div {
                margin-bottom: 6px;
            }

            .toggle-arrow {
                bottom: 10px;
                right: 10px;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .download-invoice {
                margin-top: 5px;
            }

            .order-actions {
                flex-direction: column;
                gap: 8px;
            }

            .reorder-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="orders-content">
        <h1 class="page-title">My Orders</h1>
        <?php if ($_SESSION['cancel_message']): ?>
            <div class="cancel-message <?php echo strpos($_SESSION['cancel_message'], 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php 
                echo htmlspecialchars($_SESSION['cancel_message']);
                // Clear the message after displaying
                $_SESSION['cancel_message'] = '';
                if (isset($_SESSION['updated_order_id']) && isset($_SESSION['updated_status'])) {
                    echo '<input type="hidden" id="updated_order_id" value="' . htmlspecialchars($_SESSION['updated_order_id']) . '">';
                    echo '<input type="hidden" id="updated_status" value="' . htmlspecialchars($_SESSION['updated_status']) . '">';
                    // Clear session variables after use
                    unset($_SESSION['updated_order_id']);
                    unset($_SESSION['updated_status']);
                }
                ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['reorder_message'])): ?>
            <div class="reorder-message error">
                <?php 
                echo htmlspecialchars($_SESSION['reorder_message']);
                unset($_SESSION['reorder_message']);
                ?>
            </div>
        <?php endif; ?>
        <?php
        try {
            // Debug: Log the user_id being queried
            error_log("Querying orders for user_id: $user_id");
            $sql_orders = "SELECT o.id AS order_id, o.total_amount, o.discount_amount, o.payment_method, o.status, o.created_at, o.address_id, o.cancellation_note, o.delivery_date, o.time_slot_id 
                          FROM orders o 
                          WHERE o.user_id = ? 
                          ORDER BY o.created_at DESC";
            $stmt_orders = $conn->prepare($sql_orders);
            $stmt_orders->execute([$user_id]);

            // Debug: Log the number of rows retrieved
            $row_count = $stmt_orders->rowCount();
            error_log("Retrieved $row_count orders for user_id: $user_id");

            if ($row_count > 0) {
                while ($order = $stmt_orders->fetch(PDO::FETCH_ASSOC)) {
                    // Debug: Log each order ID retrieved
                    error_log("Found order ID: " . $order['order_id']);
                    echo '<div class="order-box">';
                    echo '<div class="order-header" data-order-id="' . htmlspecialchars($order['order_id']) . '">';
                    echo '<strong>Order #' . htmlspecialchars($order['order_id']) . '</strong>';
                    echo '<i class="fas fa-download download-invoice" data-order-id="' . htmlspecialchars($order['order_id']) . '" title="Download Invoice"></i>';
                    echo '</div>';

                    $sql_items = "SELECT p.name AS product_name, oi.quantity, oi.price 
                                 FROM order_items oi 
                                 JOIN products p ON oi.product_id = p.id 
                                 WHERE oi.order_id = ?";
                    $stmt_items = $conn->prepare($sql_items);
                    $stmt_items->execute([$order['order_id']]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                    if ($stmt_items->rowCount() > 0) {
                        echo '<table class="order-items-table">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th>Product</th>';
                        echo '<th>Quantity (kg)</th>';
                        echo '<th>Price (₹)</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';

                        $subtotal = 0;
                        foreach ($items as $item) {
                            $item_total = $item['price'] * $item['quantity'];
                            $subtotal += $item_total;
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($item['product_name']) . '</td>';
                            echo '<td>' . htmlspecialchars(number_format($item['quantity'], 2)) . '</td>';
                            echo '<td>₹' . number_format($item_total, 2) . '</td>';
                            echo '</tr>';
                        }

                        echo '</tbody>';
                        echo '</table>';

                        // Calculate discount considering shipping cost
                        $discount_amount = $order['discount_amount'] ?? 0;
                        echo '<div class="order-details">';
                        echo '<div><strong>Subtotal:</strong> ₹' . number_format($subtotal, 2) . '</div>';
                        if ($discount_amount > 0) {
                            echo '<div class="discount-row"><strong>Discount:</strong> -₹' . number_format($discount_amount, 2) . '</div>';
                        }
                        echo '<div class="shipping-row"><strong>Shipping Cost:</strong> ₹' . number_format($shipping_cost, 2) . '</div>';
                        echo '<div><strong>Total:</strong> ₹' . number_format($order['total_amount'], 2) . '</div>';
                        echo '<div><strong>Date:</strong> ' . htmlspecialchars(date('Y-m-d H:i', strtotime($order['created_at']))) . '</div>';
                        echo '<div><strong>Payment Method:</strong> ' . htmlspecialchars(ucfirst($order['payment_method'])) . '</div>';
                        echo '<div><strong>Status:</strong> <span class="status-' . ($order['status'] ? strtolower(str_replace(' ', '-', $order['status'])) : 'unknown') . '" id="status-' . htmlspecialchars($order['order_id']) . '">' . htmlspecialchars($order['status'] ? ucfirst($order['status']) : 'Unknown') . '</span></div>';
                        if ($order['delivery_date']) {
                            echo '<div class="delivery-row"><strong>Delivery Date:</strong> ' . htmlspecialchars(date('Y-m-d', strtotime($order['delivery_date']))) . '</div>';
                        }
                        if ($order['time_slot_id']) {
                            $stmt = $conn->prepare("SELECT time_range FROM time_slots WHERE id = ? AND is_active = TRUE");
                            $stmt->execute([$order['time_slot_id']]);
                            $time_slot = $stmt->fetchColumn();
                            if ($time_slot === false) {
                                error_log("Time slot not found for ID: " . $order['time_slot_id']);
                                $time_slot = 'N/A';
                            }
                            echo '<div class="delivery-row"><strong>Time Slot:</strong> ' . htmlspecialchars($time_slot) . '</div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<p>No items found for this order.</p>';
                    }

                    $stmt_address = $conn->prepare("SELECT address, city, pincode FROM addresses WHERE id = ?");
                    $stmt_address->execute([$order['address_id']]);
                    $address = $stmt_address->fetch(PDO::FETCH_ASSOC);
                    if ($address) {
                        echo '<div class="shipping-address" id="address-' . htmlspecialchars($order['order_id']) . '">';
                        echo '<strong>Shipping Address:</strong> ' . htmlspecialchars($address['address'] . ', ' . $address['city'] . ' - ' . $address['pincode']);
                        echo '</div>';
                    }

                    echo '<div class="order-actions">';
                    if ($order['status'] === 'pending') {
                        echo '<form class="cancel-form" method="POST" action="orders.php">';
                        echo '<input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">';
                        echo '<input type="hidden" name="cancel_order" value="1">';
                        echo '<button type="submit" class="cancel-order-btn">Cancel Order</button>';
                        echo '</form>';
                    } elseif ($order['cancellation_note']) {
                        echo '<div><strong>Cancellation Note:</strong> ' . htmlspecialchars($order['cancellation_note']) . '</div>';
                    }
                    echo '<form class="reorder-form" method="POST" action="orders.php">';
                    echo '<input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">';
                    echo '<input type="hidden" name="reorder" value="1">';
                    echo '<button type="submit" class="reorder-btn">Reorder</button>';
                    echo '</form>';
                    echo '</div>';

                    echo '<span class="toggle-arrow">▼</span>';
                    echo '</div>';
                }
            } else {
                echo '<p class="no-orders">You have no orders yet.</p>';
            }
        } catch (PDOException $e) {
            error_log("Database error in orders.php: " . $e->getMessage());
            echo '<p class="error-message">Error loading orders: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const orderBoxes = document.querySelectorAll('.order-box');
            const updatedOrderId = document.getElementById('updated_order_id') ? document.getElementById('updated_order_id').value : null;
            const updatedStatus = document.getElementById('updated_status') ? document.getElementById('updated_status').value : null;

            if (updatedOrderId && updatedStatus) {
                const statusElement = document.getElementById('status-' + updatedOrderId);
                if (statusElement) {
                    statusElement.textContent = ucfirst(updatedStatus);
                    // Ensure the class is correctly set to match the status
                    statusElement.className = 'status-' + updatedStatus.toLowerCase().replace(' ', '-');
                    // Hide the message after updating
                    setTimeout(() => {
                        document.querySelector('.cancel-message').style.display = 'none';
                    }, 2000); // Hide after 2 seconds
                }
            }

            orderBoxes.forEach(box => {
                const header = box.querySelector('.order-header');
                const arrow = box.querySelector('.toggle-arrow');
                const orderId = header.getAttribute('data-order-id');
                const addressDiv = document.getElementById('address-' + orderId);
                const downloadIcon = box.querySelector('.download-invoice');

                if (addressDiv) {
                    addressDiv.style.display = 'none';

                    const toggleAddress = () => {
                        if (addressDiv.style.display === 'none') {
                            addressDiv.style.display = 'block';
                            arrow.textContent = '▲';
                        } else {
                            addressDiv.style.display = 'none';
                            arrow.textContent = '▼';
                        }
                    };

                    header.addEventListener('click', toggleAddress);
                    arrow.addEventListener('click', toggleAddress);
                }

                if (downloadIcon) {
                    downloadIcon.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const orderId = downloadIcon.getAttribute('data-order-id');
                        window.location.href = `../pages/invoice.php?order_id=${orderId}`;
                    });
                }
            });

            // Helper function to capitalize first letter
            function ucfirst(str) {
                return str.charAt(0).toUpperCase() + str.slice(1);
            }
        });
    </script>
</body>
</html>