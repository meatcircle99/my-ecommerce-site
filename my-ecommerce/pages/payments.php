<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Define fixed shipping cost
$shipping_cost = 30.00;

// For this approach, try to get delivery_date and time_slot from GET.
// If not provided, they will be empty and the user must choose on this page.
$delivery_date = isset($_GET['delivery_date']) ? trim($_GET['delivery_date']) : '';
$time_slot_id = isset($_GET['time_slot']) ? trim($_GET['time_slot']) : '';

// Handle AJAX order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    header('Content-Type: application/json');
    ob_start();

    $payment_method = $_POST['payment_method'] ?? 'COD';
    $address_id = $_POST['address_id'] ?? null;
    // Now we read the delivery options from the POST hidden inputs
    $delivery_date = $_POST['delivery_date'] ?? '';
    $time_slot_id = $_POST['time_slot'] ?? '';

    try {
        $conn->beginTransaction();

        // Fetch cart items
        $stmt = $conn->prepare("SELECT c.id, c.quantity, p.price, p.id AS product_id 
                               FROM cart c 
                               JOIN products p ON c.product_id = p.id 
                               WHERE c.user_id = ?");
        $stmt->execute([$user_id]);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cart_items)) {
            throw new Exception('Cart is empty');
        }

        $subtotal = 0;
        foreach ($cart_items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        // Coupon discount if any
        $discount_amount = 0;
        $coupon_code = $_SESSION['coupon_code'] ?? null;
        if (isset($_SESSION['coupon_discount'])) {
            $discount = (float)$_SESSION['coupon_discount'];
            $discount_amount = $subtotal * ($discount / 100);
            $total_price = $subtotal - $discount_amount + $shipping_cost;
        } else {
            $total_price = $subtotal + $shipping_cost;
        }

        // Validate address
        if (!$address_id) {
            $stmt = $conn->prepare("SELECT id FROM addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $address_id = $stmt->fetchColumn();
        }
        if (!$address_id) {
            throw new Exception('No address found');
        }

        // Check that both delivery_date and time_slot are provided
        if (!$delivery_date || !$time_slot_id) {
            error_log("Missing delivery date or time slot: delivery_date=$delivery_date, time_slot_id=$time_slot_id");
            throw new Exception('Delivery date and time slot are required');
        }

        // Fetch time slot details (time_range and delivery_type)
        $stmt = $conn->prepare("SELECT time_range, delivery_type FROM time_slots WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$time_slot_id]);
        $time_slot_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$time_slot_data) {
            throw new Exception('Invalid or inactive time slot');
        }
        $time_slot_name = $time_slot_data['time_range'];
        $time_slot_delivery_type = $time_slot_data['delivery_type'];

        // Validate delivery date format and allowed date based on delivery type
        $delivery_date_obj = DateTime::createFromFormat('Y-m-d', $delivery_date);
        if (!$delivery_date_obj) {
            throw new Exception('Invalid delivery date format');
        }
        $delivery_date_obj->setTime(0, 0, 0);
        if ($time_slot_delivery_type === 'same_day') {
            // Allow same-day: delivery date must be today or later
            $today = (new DateTime())->setTime(0, 0, 0);
            if ($delivery_date_obj < $today) {
                throw new Exception('Delivery date for same day delivery cannot be in the past');
            }
        } else {
            // Next day: delivery date must be at least tomorrow
            $tomorrow = (new DateTime())->modify('+1 day')->setTime(0, 0, 0);
            if ($delivery_date_obj < $tomorrow) {
                throw new Exception('Delivery date must be at least one day in the future for next day delivery');
            }
        }

        // Generate unique 4-digit order ID
        do {
            $order_id = rand(1000, 9999);
            $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $exists = $stmt->fetchColumn();
        } while ($exists > 0);

        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (id, user_id, total_amount, discount_amount, coupon_code, payment_method, address_id, status, created_at, delivery_date, time_slot_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?, ?)");
        $stmt->execute([$order_id, $user_id, $total_price, $discount_amount, $coupon_code, $payment_method, $address_id, $delivery_date, $time_slot_id]);

        // Insert order items
        foreach ($cart_items as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
        }

        // Clear cart and coupon session variables
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        unset($_SESSION['coupon_discount'], $_SESSION['coupon_code']);

        $conn->commit();

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Order placed successfully', 'redirect' => '../pages/orders.php']);
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in payments.php: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error placing order: ' . $e->getMessage()]);
        exit;
    }
}

// Include header for non-AJAX requests
require_once dirname(__DIR__) . '/includes/header.php';

// Fetch cart items and address for display
try {
    $stmt = $conn->prepare("SELECT c.id, c.quantity, p.name AS product_name, p.price 
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id 
                            WHERE c.user_id = ?");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cart_items)) {
        echo "<div class='error-message'>Your cart is empty. <a href='../pages/home.php'>Continue shopping</a></div>";
        exit;
    }
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    if (isset($_SESSION['coupon_discount'])) {
        $discount = (float)$_SESSION['coupon_discount'];
        $discount_amount = $subtotal * ($discount / 100);
        $total_price = $subtotal - $discount_amount + $shipping_cost;
    } else {
        $total_price = $subtotal + $shipping_cost;
    }

    // Get address
    $address_id = $_GET['address_id'] ?? null;
    if ($address_id) {
        $stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$address_id, $user_id]);
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$address) {
        echo "<div class='no-address'>No address found. <a href='../pages/address.php' class='btn-add-address'>Add an address</a></div>";
        exit;
    }

    // If time_slot_id is provided, fetch its time_range for display
    $time_slot_name = '';
    if ($time_slot_id) {
        $stmt = $conn->prepare("SELECT time_range FROM time_slots WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$time_slot_id]);
        $time_slot_name = $stmt->fetchColumn() ?: 'N/A';
    }
} catch (PDOException $e) {
    error_log("Database error in payments.php (fetch): " . $e->getMessage());
    echo "<div class='error-message'>Error fetching cart or address: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Meatcircle</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            padding-top: 70px;
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #ff6b6b, #f9c74f);
            margin: 0;
        }
        .payment-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            gap: 20px;
            justify-content: space-between;
        }
        .order-summary, .payment-method {
            flex: 1;
            background: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0px 10px 20px rgba(0,0,0,0.2);
            border: 2px solid #ff6b6b;
        }
        .order-summary h3, .payment-method h3 {
            color: #ff6b6b;
            font-size: 20px;
            margin-bottom: 15px;
            text-align: center;
        }
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .order-table th, .order-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .order-table th {
            background-color: #ff6b6b;
            color: #fff;
            font-weight: bold;
        }
        .total-row, .discount-row, .shipping-row, .delivery-row {
            font-weight: bold;
        }
        .discount-row { background: #e8f5e9; color: #2e7d32; }
        .shipping-row { background: #f1f1f1; }
        .delivery-row { background: #e0f7fa; color: #00695c; }
        .total-row { background: #f1f1f1; }
        .address-details, .delivery-details {
            margin-top: 15px;
            font-size: 16px;
            color: #333;
        }
        /* New Delivery Options section if values are missing */
        .delivery-options {
            margin: 20px 0;
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #e3e3e3;
            border-radius: 8px;
        }
        .delivery-options h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .delivery-options .form-group {
            margin-bottom: 15px;
        }
        .delivery-options label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }
        .delivery-option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }
        .delivery-option {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            background: #fff;
            border: 2px solid #e3e3e3;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .delivery-option input[type="radio"] {
            display: none;
        }
        .delivery-option span {
            font-size: 0.95rem;
            color: #333;
            width: 100%;
            text-align: center;
        }
        .delivery-option.selected {
            background: #3498db;
            border-color: #2980b9;
        }
        .delivery-option.selected span {
            color: #fff;
        }
        .payment-method-options label {
            display: block;
            padding: 8px;
            cursor: pointer;
            font-size: 16px;
            color: #333;
        }
        .payment-method-options input[type="radio"] {
            margin-right: 10px;
        }
        .btn-place-order {
            width: 100%;
            padding: 12px;
            background: #ff6b6b;
            color: #fff;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 15px;
            transition: 0.3s;
            box-shadow: 0px 5px 15px rgba(255,107,107,0.3);
        }
        .btn-place-order:hover {
            background: #d84343;
            box-shadow: 0px 7px 20px rgba(216,67,67,0.4);
        }
        @media (max-width:768px) {
            .payment-content { flex-direction: column; gap:15px; }
            .order-summary, .payment-method { width:100%; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="payment-content">
        <div class="order-summary">
            <h3>Your Order Summary</h3>
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty (kg)</th>
                        <th>Price (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="2"><strong>Subtotal</strong></td>
                        <td><strong>₹<?php echo number_format($subtotal, 2); ?></strong></td>
                    </tr>
                    <?php if ($discount_amount > 0): ?>
                        <tr class="discount-row">
                            <td colspan="2"><strong>Discount (<?php echo isset($_SESSION['coupon_code']) ? htmlspecialchars($_SESSION['coupon_code']) . ' (' . $_SESSION['coupon_discount'] . '%)' : $_SESSION['coupon_discount'] . '%'; ?>)</strong></td>
                            <td><strong>-₹<?php echo number_format($discount_amount, 2); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="shipping-row">
                        <td colspan="2"><strong>Shipping Cost</strong></td>
                        <td><strong>₹<?php echo number_format($shipping_cost, 2); ?></strong></td>
                    </tr>
                    <?php if ($delivery_date && $time_slot_name): ?>
                        <tr class="delivery-row">
                            <td colspan="2"><strong>Delivery Date & Time</strong></td>
                            <td><strong><?php echo htmlspecialchars(date('Y-m-d', strtotime($delivery_date)) . ' (' . $time_slot_name . ')'); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td colspan="2"><strong>Total Amount</strong></td>
                        <td><strong>₹<?php echo number_format($total_price, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            <div class="address-details">
                <strong>Shipping Address:</strong> <?php echo htmlspecialchars($address['address'] . ', ' . $address['city'] . ' - ' . $address['pincode']); ?>
            </div>
            <?php if ($delivery_date && $time_slot_name): ?>
                <div class="delivery-details">
                    <strong>Delivery Date:</strong> <?php echo htmlspecialchars(date('Y-m-d', strtotime($delivery_date))); ?><br>
                    <strong>Time Slot:</strong> <?php echo htmlspecialchars($time_slot_name); ?>
                </div>
            <?php endif; ?>
            <!-- If delivery options not provided, show selection UI -->
            <?php if (!$delivery_date || !$time_slot_id): ?>
                <div class="delivery-options">
                    <h3>Select Delivery Options</h3>
                    <div class="form-group">
                        <label for="delivery_date_input">Delivery Date:</label>
                        <input type="date" id="delivery_date_input" name="delivery_date_input" required>
                    </div>
                    <div class="form-group">
                        <label>Available Time Slots:</label>
                        <div class="delivery-option-grid">
                            <?php
                            // Fetch all active time slots
                            $stmt = $conn->prepare("SELECT id, time_range, delivery_type FROM time_slots WHERE is_active = TRUE ORDER BY delivery_type, time_range");
                            $stmt->execute();
                            $available_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($available_slots as $slot) {
                                echo '<label class="delivery-option">';
                                echo '<input type="radio" name="time_slot_input" value="' . htmlspecialchars($slot['id']) . '" required>';
                                echo '<span>' . htmlspecialchars($slot['time_range']) . '</span>';
                                echo '</label>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <script>
                    // When the user selects delivery options, update the hidden fields
                    document.getElementById('delivery_date_input').addEventListener('change', function(){
                        document.querySelector('input[name="delivery_date"]').value = this.value;
                    });
                    document.querySelectorAll('input[name="time_slot_input"]').forEach(function(radio){
                        radio.addEventListener('change', function(){
                            document.querySelector('input[name="time_slot"]').value = this.value;
                            // Optional: add visual indication of selection
                            document.querySelectorAll('.delivery-option').forEach(el => el.classList.remove('selected'));
                            this.parentElement.classList.add('selected');
                        });
                    });
                </script>
            <?php endif; ?>
        </div>

        <div class="payment-method">
            <h3>Select Payment Method</h3>
            <form id="payment-form" class="payment-method-options">
                <label><input type="radio" name="payment_method" value="credit_card" required> Credit Card</label>
                <label><input type="radio" name="payment_method" value="debit_card"> Debit Card</label>
                <label><input type="radio" name="payment_method" value="wallets"> Wallets</label>
                <label><input type="radio" name="payment_method" value="upi"> UPI</label>
                <label><input type="radio" name="payment_method" value="cod" checked> Cash on Delivery</label>
                <!-- Hidden inputs to store delivery options -->
                <input type="hidden" name="place_order" value="1">
                <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($address['id']); ?>">
                <input type="hidden" name="delivery_date" value="<?php echo htmlspecialchars($delivery_date); ?>">
                <input type="hidden" name="time_slot" value="<?php echo htmlspecialchars($time_slot_id); ?>">
                <button type="submit" class="btn-place-order">Place Order</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('payment-form');
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                try {
                    const response = await fetch(window.location.pathname, {
                        method: 'POST',
                        body: formData,
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const result = await response.json();
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: result.message,
                            confirmButtonColor: '#ff6b6b',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = result.redirect || '../pages/orders.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: result.message,
                            confirmButtonColor: '#ff6b6b'
                        });
                    }
                } catch (error) {
                    console.error('Error placing order:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Something went wrong. Please try again.',
                        confirmButtonColor: '#ff6b6b'
                    });
                }
            });
        });
    </script>
</body>
</html>
