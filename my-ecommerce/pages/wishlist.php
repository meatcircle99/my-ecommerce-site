<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
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

// Fetch wishlisted products
try {
    $stmt = $conn->prepare("SELECT w.id, w.product_id, p.name, p.price, p.image, p.stock_quantity, p.description 
                           FROM wishlist w 
                           JOIN products p ON w.product_id = p.id 
                           WHERE w.user_id = ? 
                           ORDER BY w.added_at DESC");
    $stmt->execute([$user_id]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Number of wishlist items: " . count($wishlist_items));
} catch (PDOException $e) {
    error_log("Database error in wishlist.php (fetch): " . $e->getMessage());
    $wishlist_items = [];
}

// Fetch header.php content
$header_path = '../includes/header.php';
$header_content = '';
if (file_exists($header_path)) {
    ob_start();
    include $header_path;
    $header_content = ob_get_clean();
} else {
    error_log("header.php not found at: " . realpath('../includes'));
    $header_content = '<p>Error: Header file not found.</p>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist - Meatcircle</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Custom styles for the page, ensuring compatibility with the fixed header */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        /* Wrapper to push content below the fixed header */
        .content-wrapper {
            padding-top: 70px; /* Matches header.php's fixed height */
            min-height: calc(100vh - 70px); /* Ensure content takes full height minus header */
        }

        .wishlist-content { 
            max-width: 800px; 
            margin: 20px auto; /* Adjusted margin to account for padding-top */
            padding: 2rem; 
            background-color: white; 
            border-radius: 8px; 
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); 
        }
        .wishlist-container { 
            margin-top: 1rem; 
        }
        .wishlist-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 1rem; 
        }
        .wishlist-table th, .wishlist-table td { 
            padding: 0.5rem; 
            border: 1px solid #ddd; 
            text-align: left; 
        }
        .wishlist-table th { 
            background-color: #2c3e50; 
            color: white; 
        }
        .wishlist-table .action-btn { 
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
        .wishlist-table .action-btn:hover { 
            background-color: #34495e; 
        }
        .wishlist-table .remove-btn { 
            background-color: #e74c3c; 
        }
        .wishlist-table .remove-btn:hover { 
            background-color: #c0392b; 
        }
        .wishlist-table .disabled-btn { 
            background-color: #cccccc; 
            cursor: not-allowed; 
        }
        .wishlist-table .disabled-btn:hover { 
            background-color: #cccccc; 
        }
        .no-wishlist-items { 
            color: #666; 
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .quantity-btn {
            padding: 0.3rem 0.5rem;
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .quantity-btn:hover {
            background-color: #34495e;
        }
        .quantity-input {
            width: 60px;
            padding: 0.3rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .maintenance-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .total-price {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Inject header.php content -->
    <?php echo $header_content; ?>

    <div class="content-wrapper">
        <main class="wishlist-content">
            <h1 class="page-title">Your Wishlist</h1>
            <?php if ($maintenance_mode): ?>
                <div class="maintenance-message">
                    <strong>Notice:</strong> The website is currently under maintenance. You can browse your wishlist, but adding to cart or removing items is disabled.
                </div>
            <?php endif; ?>
            <div class="wishlist-container">
                <?php if (empty($wishlist_items)): ?>
                    <p class="no-wishlist-items">Your wishlist is empty.</p>
                <?php else: ?>
                    <table class="wishlist-table">
                        <thead>
                            <tr>
                                <th>Product Image</th>
                                <th>Product Name</th>
                                <th>Price (₹/kg)</th>
                                <th>Quantity</th>
                                <th>Total Price (₹)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wishlist_items as $item): ?>
                                <?php
                                $quantity = 0.5; // Default quantity
                                $total_price = $item['price'] * $quantity;
                                ?>
                                <tr data-product-id="<?php echo $item['product_id']; ?>" data-price-per-kg="<?php echo $item['price']; ?>">
                                    <td><img src="../images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 50px; height: auto;"></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <div class="quantity-controls">
                                            <button type="button" class="quantity-btn minus" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>-</button>
                                            <input type="number" class="quantity-input" value="<?php echo $quantity; ?>" step="0.5" min="0.5" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>
                                            <button type="button" class="quantity-btn plus" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>+</button>
                                        </div>
                                    </td>
                                    <td class="total-price">₹<?php echo number_format($total_price, 2); ?></td>
                                    <td>
                                        <form id="add-to-cart-form-<?php echo $item['product_id']; ?>" style="display: inline;">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            <input type="hidden" name="quantity" class="wishlist-quantity" value="<?php echo $quantity; ?>">
                                            <input type="hidden" name="add_to_cart" value="1">
                                            <button type="submit" class="action-btn <?php echo $maintenance_mode || $item['stock_quantity'] <= 0 ? 'disabled-btn' : ''; ?>" <?php echo $maintenance_mode || $item['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>Add to Cart</button>
                                        </form>
                                        <button class="action-btn remove-btn <?php echo $maintenance_mode ? 'disabled-btn' : ''; ?>" data-wishlist-id="<?php echo $item['id']; ?>" <?php echo $maintenance_mode ? 'disabled' : ''; ?>>Remove</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Quantity controls with price update
            const quantityControls = document.querySelectorAll('.quantity-controls');
            quantityControls.forEach(control => {
                const minusBtn = control.querySelector('.minus');
                const plusBtn = control.querySelector('.plus');
                const input = control.querySelector('.quantity-input');
                const form = control.closest('tr').querySelector('form');
                const quantityInput = form.querySelector('.wishlist-quantity');
                const row = control.closest('tr');
                const pricePerKg = parseFloat(row.dataset.pricePerKg);
                const totalPriceCell = row.querySelector('.total-price');

                const updateTotalPrice = () => {
                    const quantity = parseFloat(input.value) || 0.5;
                    const totalPrice = quantity * pricePerKg;
                    totalPriceCell.textContent = `₹${totalPrice.toFixed(2)}`;
                    quantityInput.value = quantity;
                };

                minusBtn.addEventListener('click', () => {
                    if (minusBtn.disabled) return;
                    let value = parseFloat(input.value) || 0.5;
                    if (value > 0.5) {
                        input.value = (value - 0.5).toFixed(1);
                        updateTotalPrice();
                    }
                });

                plusBtn.addEventListener('click', () => {
                    if (plusBtn.disabled) return;
                    let value = parseFloat(input.value) || 0.5;
                    input.value = (value + 0.5).toFixed(1);
                    updateTotalPrice();
                });

                input.addEventListener('input', () => {
                    if (input.disabled) return;
                    let value = parseFloat(input.value);
                    if (isNaN(value) || value < 0.5) {
                        input.value = 0.5;
                    }
                    updateTotalPrice();
                });

                updateTotalPrice();
            });

            // Handle Add to Cart
            const addToCartForms = document.querySelectorAll('.wishlist-table form');
            addToCartForms.forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    console.log('Add to Cart form submitted');
                    const addToCartBtn = form.querySelector('[name="add_to_cart"]');
                    if (addToCartBtn.disabled) {
                        alert('This product is out of stock or the website is under maintenance.');
                        return;
                    }

                    const formData = new FormData(form);
                    const data = new URLSearchParams();
                    formData.forEach((value, key) => data.append(key, value));

                    console.log('Form data sent:', data.toString());

                    try {
                        const response = await fetch('./wishlist_action.php', {
                            method: 'POST',
                            body: data,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        });

                        console.log('HTTP Status for add_to_cart:', response.status);
                        const rawText = await response.text();
                        console.log('Raw response from add_to_cart:', rawText);

                        if (!rawText) {
                            throw new Error('Empty response from server');
                        }

                        let result;
                        try {
                            result = JSON.parse(rawText);
                        } catch (parseError) {
                            throw new Error('Failed to parse JSON: ' + parseError.message + ' Raw response: ' + rawText);
                        }

                        if (result.status === 'success') {
                            alert(result.message);
                            form.closest('tr').remove();
                            // Trigger cart count update from header.php
                            if (typeof updateCartCount === 'function') {
                                updateCartCount();
                            }
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Fetch error:', error);
                        alert('An error occurred while adding to cart: ' + error.message);
                    }
                });
            });

            // Handle Remove from Wishlist
            const removeButtons = document.querySelectorAll('.remove-btn');
            removeButtons.forEach(button => {
                button.addEventListener('click', async (e) => {
                    if (button.disabled) return;
                    const wishlistId = e.target.dataset.wishlistId;
                    if (confirm('Are you sure you want to remove this item from your wishlist?')) {
                        try {
                            const response = await fetch('./wishlist_action.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: new URLSearchParams({
                                    'remove_wishlist': true,
                                    'wishlist_id': wishlistId
                                }),
                                credentials: 'same-origin'
                            });

                            console.log('HTTP Status for remove_wishlist:', response.status);
                            const rawText = await response.text();
                            console.log('Raw response from remove_wishlist:', rawText);

                            const result = JSON.parse(rawText);
                            if (result.status === 'success') {
                                e.target.closest('tr').remove();
                                // Trigger cart count update from header.php
                                if (typeof updateCartCount === 'function') {
                                    updateCartCount();
                                }
                                alert(result.message);
                            } else {
                                alert('Error: ' + result.message);
                            }
                        } catch (error) {
                            console.error('Error removing from wishlist:', error);
                            alert('An error occurred while removing from wishlist');
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>