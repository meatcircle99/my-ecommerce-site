<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Disable SSE for instant reloads
define('DEBUG_SSE', true);

session_start();
include 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: pages/login.php');
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
    $maintenance_mode = false; // Default to normal mode on error
}

// Define fixed shipping cost
$shipping_cost = 30.00;

// Handle cart actions (add, update, remove, apply coupon, remove coupon)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($maintenance_mode) {
        echo json_encode(['status' => 'error', 'message' => 'The website is currently under maintenance. All cart actions are disabled.']);
        exit;
    }

    if (isset($_POST['add_to_cart'])) {
        $product_id = (int)$_POST['product_id'];
        $quantity = floatval($_POST['quantity']);
        
        try {
            // Check product and stock quantity
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                echo json_encode(['status' => 'error', 'message' => 'Product not found']);
                exit;
            }

            // Check if there's enough stock
            if ($product['stock_quantity'] < $quantity) {
                echo json_encode(['status' => 'error', 'message' => 'Not enough stock available. Only ' . $product['stock_quantity'] . ' units left.']);
                exit;
            }

            // Check if product already exists in cart
            $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cart_item) {
                $new_quantity = $cart_item['quantity'] + $quantity;
                if ($product['stock_quantity'] < $new_quantity) {
                    echo json_encode(['status' => 'error', 'message' => 'Not enough stock available. Only ' . $product['stock_quantity'] . ' units left.']);
                    exit;
                }
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$new_quantity, $user_id, $product_id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$user_id, $product_id, $quantity]);
            }

            // Update stock quantity
            $new_stock = $product['stock_quantity'] - $quantity;
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$new_stock, $product_id]);

            echo json_encode(['status' => 'success', 'message' => 'Product added to cart']);
        } catch (PDOException $e) {
            error_log("Database error in cart.php (add): " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Error adding to cart']);
        }
        exit;
    } elseif (isset($_POST['update_quantity'])) {
        $cart_id = (int)$_POST['cart_id'];
        $quantity = floatval($_POST['quantity']);

        try {
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$quantity, $cart_id, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Quantity updated', 'quantity' => $quantity]);
        } catch (PDOException $e) {
            error_log("Database error in cart.php (update): " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Error updating quantity']);
        }
        exit;
    } elseif (isset($_POST['remove_item'])) {
        $cart_id = (int)$_POST['cart_id'];

        try {
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $affected_rows = $stmt->execute([$cart_id, $user_id]);

            if ($affected_rows) {
                echo json_encode(['status' => 'success', 'message' => 'Item removed from cart']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Item not found or not authorized']);
            }
        } catch (PDOException $e) {
            error_log("Database error in cart.php (remove): " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Error removing item']);
        }
        exit;
    } elseif (isset($_POST['apply_coupon'])) {
        $coupon_code = trim($_POST['coupon_code']);
        $current_date = date('Y-m-d');

        try {
            // Step 1: Check if the coupon exists and is valid
            $stmt = $conn->prepare("SELECT * FROM coupons WHERE UPPER(code) = UPPER(?) AND status = 'active' AND expiry_date >= ?");
            $stmt->execute([$coupon_code, $current_date]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$coupon) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid, expired, or inactive coupon code']);
                exit;
            }

            // Step 2: Check if user has completed 2 or more orders
            $order_count_stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
            $order_count_stmt->execute([$user_id]);
            $completed_orders = $order_count_stmt->fetchColumn();

            if ($completed_orders >= 2) {
                echo json_encode(['status' => 'error', 'message' => 'Coupon is only valid for new users (first 2 orders). You have already completed ' . $completed_orders . ' orders.']);
                exit;
            }

            // Step 3: Check if this coupon has been used by the user before
            $usage_stmt = $conn->prepare("SELECT COUNT(*) FROM user_coupon_usage WHERE user_id = ? AND coupon_code = ?");
            $usage_stmt->execute([$user_id, $coupon_code]);
            $usage_count = $usage_stmt->fetchColumn();

            if ($usage_count > 0) {
                echo json_encode(['status' => 'error', 'message' => 'This coupon has already been applied to your account.']);
                exit;
            }

            // Step 4: Apply the coupon
            $discount = $coupon['discount'];
            $_SESSION['coupon_discount'] = $discount;
            $_SESSION['coupon_code'] = $coupon_code;

            // Step 5: Record coupon usage
            $insert_usage_stmt = $conn->prepare("INSERT INTO user_coupon_usage (user_id, coupon_code, used_at) VALUES (?, ?, NOW())");
            $insert_usage_stmt->execute([$user_id, $coupon_code]);

            echo json_encode(['status' => 'success', 'message' => 'Coupon applied successfully!', 'discount' => $discount]);
        } catch (PDOException $e) {
            error_log("Database error in cart.php (apply_coupon): " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Error applying coupon. Please try again.']);
        }
        exit;
    } elseif (isset($_POST['remove_coupon'])) {
        unset($_SESSION['coupon_discount']);
        unset($_SESSION['coupon_code']);
        echo json_encode(['status' => 'success', 'message' => 'Coupon removed']);
        exit;
    }
}

// Fetch cart items with product and category details
try {
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_cart_user_product ON cart(user_id, product_id)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id)");

    $sql = "SELECT c.id, c.quantity, p.name AS product_name, p.price, p.category_id, cat.name AS category_name 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            JOIN categories cat ON p.category_id = cat.id 
            WHERE c.user_id = ? 
            ORDER BY c.added_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }

    $discount_amount = 0;
    if (isset($_SESSION['coupon_discount']) && !$maintenance_mode) {
        $discount = $_SESSION['coupon_discount'];
        $discount_amount = $subtotal * ($discount / 100);
        $total_price = $subtotal - $discount_amount + $shipping_cost;
    } else {
        $total_price = $subtotal + $shipping_cost;
    }
} catch (PDOException $e) {
    error_log("Database error in cart.php (fetch): " . $e->getMessage());
    $cart_items = [];
    $subtotal = 0;
    $total_price = 0;
    $discount_amount = 0;
}

// Handle cart count request
if (isset($_GET['action']) && $_GET['action'] === 'get_count') {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->fetchColumn();
        echo json_encode(['count' => $count]);
    } catch (PDOException $e) {
        echo json_encode(['count' => 0]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - Meatcircle</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .cart-content { 
            max-width: 800px; 
            margin: 80px auto; 
            padding: 2rem; 
            background-color: white; 
            border-radius: 8px; 
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); 
        }
        .cart-container { 
            margin-top: 1rem; 
        }
        .cart-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 1rem; 
        }
        .cart-table th, .cart-table td { 
            padding: 0.5rem; 
            border: 1px solid #ddd; 
            text-align: left; 
        }
        .cart-table th { 
            background-color: #2c3e50; 
            color: white; 
        }
        .cart-table .action-btn { 
            background-color: #2c3e50; 
            color: white; 
            padding: 0.3rem 0.7rem; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-right: 0.5rem; 
            text-decoration: none; 
            display: inline-block; 
        }
        .cart-table .action-btn:hover { 
            background-color: #34495e; 
        }
        .cart-table .delete-btn { 
            background-color: #e74c3c; 
        }
        .cart-table .delete-btn:hover { 
            background-color: #c0392b; 
        }
        .cart-table .disabled-btn { 
            background-color: #cccccc; 
            cursor: not-allowed; 
        }
        .cart-table .disabled-btn:hover { 
            background-color: #cccccc; 
        }
        .no-cart-items { 
            color: #666; 
        }
        .cart-total { 
            margin-top: 1rem; 
            font-weight: bold; 
        }
        .cart-total p { 
            margin: 0.5rem 0; 
        }
        .cart-quantity { 
            width: 60px; 
            padding: 0.3rem; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        .btn-proceed-address {
            display: block;
            width: fit-content;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        .btn-proceed-address:hover {
            background-color: #218838;
        }
        .btn-proceed-address.disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .btn-proceed-address.disabled:hover {
            background-color: #cccccc;
        }
        .coupon-section {
            margin-top: 1.5rem;
            padding: 1rem;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .coupon-section h3 {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 0.75rem;
        }
        .coupon-input-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .coupon-input-group i {
            font-size: 1.5rem;
            color: #28a745;
        }
        #coupon-code {
            flex: 1;
            padding: 0.75rem 0.75rem 0.75rem 2.5rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            background: white url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%2328a745" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 4H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><path d="M1 10h22"></path></svg>') no-repeat 0.75rem center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        #coupon-code::placeholder {
            color: #999;
        }
        #apply-coupon, #remove-coupon {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        #apply-coupon {
            background-color: #28a745;
            color: white;
        }
        #apply-coupon:hover {
            background-color: #218838;
            transform: scale(1.05);
        }
        #apply-coupon:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
        }
        #remove-coupon {
            background-color: #dc3545;
            color: white;
            display: <?php echo isset($_SESSION['coupon_code']) && !$maintenance_mode ? 'inline-block' : 'none'; ?>;
        }
        #remove-coupon:hover {
            background-color: #c82333;
            transform: scale(1.05);
        }
        #remove-coupon:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
        }
        #coupon-message {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #28a745;
        }
        .maintenance-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="cart-content">
        <h1 class="page-title">Your Cart</h1>
        <?php if ($maintenance_mode): ?>
            <div class="maintenance-message">
                <strong>Notice:</strong> The website is currently under maintenance. You can browse your cart, but adding, updating, or proceeding to checkout is disabled.
            </div>
        <?php endif; ?>
        <div class="cart-container">
            <?php if (empty($cart_items)): ?>
                <p class="no-cart-items">Your cart is empty.</p>
            <?php else: ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price (₹/kg)</th>
                            <th>Weight (kg)</th>
                            <th>Category</th>
                            <th>Total Price (₹)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr data-price="<?php echo $item['price']; ?>">
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <input type="number" class="cart-quantity" data-cart-id="<?php echo $item['id']; ?>" value="<?php echo number_format($item['quantity'], 2); ?>" step="0.5" min="0.5" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>
                                </td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td class="item-total"><?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                <td>
                                    <button class="action-btn delete-btn <?php echo $maintenance_mode ? 'disabled-btn' : ''; ?>" data-cart-id="<?php echo $item['id']; ?>" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="cart-total">
                    <p>Subtotal: ₹<span id="cart-subtotal"><?php echo number_format($subtotal, 2); ?></span></p>
                    <p id="discount-display" style="display: <?php echo $discount_amount > 0 && !$maintenance_mode ? 'block' : 'none'; ?>;">Discount: -₹<span id="discount-amount"><?php echo number_format($discount_amount, 2); ?></span></p>
                    <p>Shipping Cost: ₹<span id="shipping-cost"><?php echo number_format($shipping_cost, 2); ?></span></p>
                    <h3>Total: ₹<span id="cart-total-price"><?php echo number_format($total_price, 2); ?></span></h3>
                </div>
                <div class="coupon-section">
                    <h3>Apply Coupon Code</h3>
                    <div class="coupon-input-group">
                        <i class="fas fa-ticket-alt"></i>
                        <input type="text" id="coupon-code" placeholder="Enter coupon code" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>
                        <button id="apply-coupon" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>Apply Coupon</button>
                        <button id="remove-coupon" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>Remove Coupon</button>
                    </div>
                    <div id="coupon-message"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($cart_items)): ?>
            <a href="pages/address.php" class="btn-proceed-address <?php echo $maintenance_mode ? 'disabled' : ''; ?>" <?php echo $maintenance_mode ? 'onclick="return false;"' : ''; ?>>Proceed to Add Address</a>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="footer-links">
            <a href="pages/about.php">About Us</a> |
            <a href="pages/terms.php">Terms & Conditions</a> |
            <a href="pages/contact.php">Contact Us</a>
        </div>
        <p>© 2025 Meatcircle. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let couponDiscount = <?php echo isset($_SESSION['coupon_discount']) && !$maintenance_mode ? $_SESSION['coupon_discount'] : 0; ?>;
            const shippingCost = <?php echo $shipping_cost; ?>;

            const quantityInputs = document.querySelectorAll('.cart-quantity');
            quantityInputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    if (input.disabled) return; // Skip if disabled during maintenance mode
                    let quantity = parseFloat(e.target.value) || 0.5;
                    if (quantity < 0.5) {
                        quantity = 0.5;
                        e.target.value = quantity;
                    }
                    const row = e.target.closest('tr');
                    const price = parseFloat(row.dataset.price);
                    const itemTotal = price * quantity;
                    row.querySelector('.item-total').textContent = numberFormat(itemTotal);
                    updateCartTotal();
                });

                input.addEventListener('change', async (e) => {
                    if (e.target.disabled) return; // Skip if disabled during maintenance mode
                    const cartId = e.target.dataset.cartId;
                    let quantity = parseFloat(e.target.value) || 0.5;
                    if (quantity < 0.5) {
                        quantity = 0.5;
                        e.target.value = quantity;
                    }
                    try {
                        const response = await fetch('cart.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                'update_quantity': true,
                                'cart_id': cartId,
                                'quantity': quantity
                            })
                        });
                        const result = await response.json();
                        if (result.status === 'success') {
                            updateCartTotal();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (error) {
                        alert('Error updating quantity');
                    }
                });
            });

            const removeButtons = document.querySelectorAll('.delete-btn');
            removeButtons.forEach(button => {
                button.addEventListener('click', async (e) => {
                    if (button.disabled) return; // Skip if disabled during maintenance mode
                    const cartId = e.target.dataset.cartId;
                    if (confirm('Are you sure you want to remove this item?')) {
                        try {
                            const response = await fetch('cart.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    'remove_item': true,
                                    'cart_id': cartId
                                })
                            });
                            const result = await response.json();
                            if (result.status === 'success') {
                                e.target.closest('tr').remove();
                                updateCartTotal();
                                updateCartCount();
                            } else {
                                alert('Error: ' + result.message);
                            }
                        } catch (error) {
                            alert('Error removing item');
                        }
                    }
                });
            });

            document.getElementById('apply-coupon').addEventListener('click', async () => {
                const applyButton = document.getElementById('apply-coupon');
                if (applyButton.disabled) return; // Skip if disabled during maintenance mode
                const couponCode = document.getElementById('coupon-code').value.trim();
                const messageElement = document.getElementById('coupon-message');
                if (!couponCode) {
                    messageElement.textContent = 'Please enter a coupon code.';
                    messageElement.style.color = '#e74c3c';
                    return;
                }
                try {
                    const response = await fetch('cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            'apply_coupon': true,
                            'coupon_code': couponCode
                        })
                    });
                    const result = await response.json();
                    messageElement.textContent = result.message;
                    if (result.status === 'success') {
                        messageElement.style.color = '#28a745';
                        couponDiscount = parseFloat(result.discount);
                        document.getElementById('remove-coupon').style.display = 'inline-block';
                        document.getElementById('coupon-code').value = '';
                        updateCartTotal();
                    } else {
                        messageElement.style.color = '#e74c3c';
                    }
                } catch (error) {
                    messageElement.textContent = 'Error applying coupon.';
                    messageElement.style.color = '#e74c3c';
                }
            });

            document.getElementById('remove-coupon').addEventListener('click', async () => {
                const removeButton = document.getElementById('remove-coupon');
                if (removeButton.disabled) return; // Skip if disabled during maintenance mode
                try {
                    const response = await fetch('cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            'remove_coupon': true
                        })
                    });
                    const result = await response.json();
                    const messageElement = document.getElementById('coupon-message');
                    messageElement.textContent = result.message;
                    if (result.status === 'success') {
                        messageElement.style.color = '#28a745';
                        document.getElementById('remove-coupon').style.display = 'none';
                        couponDiscount = 0;
                        updateCartTotal();
                    } else {
                        messageElement.style.color = '#e74c3c';
                    }
                } catch (error) {
                    messageElement.textContent = 'Error removing coupon.';
                    messageElement.style.color = '#e74c3c';
                }
            });

            async function updateCartCount() {
                try {
                    const response = await fetch('cart.php?action=get_count');
                    const data = await response.json();
                    const cartCountElement = document.getElementById('cart-count');
                    if (cartCountElement) {
                        cartCountElement.textContent = data.count || 0;
                        cartCountElement.style.display = data.count > 0 ? 'inline-block' : 'none';
                    }
                } catch (error) {
                    console.error('Error fetching cart count');
                }
            }
            updateCartCount();

            function numberFormat(number) {
                return parseFloat(number).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function updateCartTotal() {
                let subtotal = 0;
                document.querySelectorAll('.cart-table tbody tr').forEach(row => {
                    const price = parseFloat(row.dataset.price);
                    const quantity = parseFloat(row.querySelector('.cart-quantity').value) || 0.5;
                    subtotal += price * quantity;
                });
                let total = subtotal;
                let discountAmount = 0;
                if (couponDiscount > 0 && !<?php echo $maintenance_mode ? 'true' : 'false'; ?>) {
                    discountAmount = subtotal * (couponDiscount / 100);
                    total = subtotal - discountAmount;
                    document.getElementById('discount-display').style.display = 'block';
                    document.getElementById('discount-amount').textContent = numberFormat(discountAmount);
                } else {
                    document.getElementById('discount-display').style.display = 'none';
                }
                // Add shipping cost to total
                total += shippingCost;
                document.getElementById('cart-subtotal').textContent = numberFormat(subtotal);
                document.getElementById('cart-total-price').textContent = numberFormat(total);
            }
            updateCartTotal();
        });
    </script>
</body>
</html>