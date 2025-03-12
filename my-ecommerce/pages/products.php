<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Handle wishlist toggle (AJAX request, no HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_wishlist'])) {
    include '../config/db.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You must be logged in to modify the wishlist']);
        exit;
    }

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
        echo json_encode(['status' => 'error', 'message' => 'Wishlist actions are disabled during maintenance']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $product_id = (int)$_POST['product_id'];

    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['status' => 'removed']);
        } else {
            $stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id, added_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['status' => 'added']);
        }
        exit;
    } catch (PDOException $e) {
        error_log("Database error in products.php (wishlist): " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error updating wishlist']);
        exit;
    }
}

// Non-AJAX request: Render the page
include '../config/db.php';

// Check maintenance mode
try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance_mode = $stmt->fetchColumn() === '1';
} catch (PDOException $e) {
    error_log("Error checking maintenance mode: " . $e->getMessage());
    $maintenance_mode = false;
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
error_log("User ID: " . ($user_id ?? 'Not logged in'));

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
error_log("Category ID: $category_id, Search Query: $search_query");

// Fetch category name (if category is specified)
$category_name = '';
if ($category_id > 0) {
    $category_name = $conn->query("SELECT name FROM categories WHERE id = $category_id")->fetchColumn();
}

// Fetch products
try {
    $sql = "SELECT * FROM products WHERE 1=1";
    $params = [];

    if ($category_id > 0) {
        $sql .= " AND category_id = ?";
        $params[] = $category_id;
    }

    if ($search_query) {
        error_log("Searching for: $search_query");
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    error_log("SQL Query: $sql, Params: " . json_encode($params));

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Number of products found: " . count($products));
} catch (PDOException $e) {
    error_log("Database error in products.php: " . $e->getMessage());
    echo '<p class="error-message">Error fetching products: ' . $e->getMessage() . '</p>';
    exit;
}

// Check wishlist status for each product
$wishlisted_products = [];
if ($user_id) {
    try {
        $stmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wishlisted_products = $stmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("Wishlisted products: " . json_encode($wishlisted_products));
    } catch (PDOException $e) {
        error_log("Error fetching wishlist: " . $e->getMessage());
    }
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
    <title><?php echo htmlspecialchars($category_name ?: 'Products'); ?> - Meatcircle</title>
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
            padding-top: 30px; /* Reduced from 70px to minimize gap */
            min-height: calc(100vh - 50px); /* Adjusted to match padding-top */
        }

        /* Reduce additional spacing in main and heading */
        main {
            margin: 0; /* Remove default margin */
            padding: 0 20px; /* Add minimal padding for sides */
        }

        .category-heading {
            margin: 10px 0; /* Reduced margin to minimize gap */
            font-size: 1.8rem;
            text-align: center;
        }

        .stock-status {
            margin-top: 10px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .stock-low {
            color: #e74c3c;
        }
        .stock-out {
            color: #e74c3c;
            font-weight: bold;
        }
        .btn-add-to-cart.disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .maintenance-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .wishlist-message {
            position: fixed;
            top: 60px; /* Adjusted to match reduced padding-top */
            left: 50%;
            transform: translateX(-50%);
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            display: none;
            z-index: 1001;
        }
        .product-details {
            position: relative;
            padding-bottom: 10px;
        }
        .btn-wishlist {
            background: linear-gradient(90deg, #ff7e5f, #feb47b);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 10px;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-wishlist:hover {
            background: linear-gradient(90deg, #ff6347, #ff9f5b);
            transform: translateY(-2px);
        }
        .btn-wishlist.wishlisted {
            background: linear-gradient(90deg, #ff69b4, #ff8c9e);
        }
        .btn-wishlist:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <!-- Inject header.php content -->
    <?php echo $header_content; ?>

    <div class="content-wrapper">
        <main>
            <?php if ($maintenance_mode): ?>
                <div class="maintenance-message">
                    <strong>Notice:</strong> The website is currently under maintenance. You cannot add products to the cart or wishlist.
                </div>
            <?php endif; ?>

            <h1 class="category-heading"><?php echo htmlspecialchars($category_name ?: 'All Products'); ?> Products</h1>
            <div class="product-grid">
                <?php
                $products_found = false;
                foreach ($products as $row) {
                    $products_found = true;
                    $stock_quantity = $row['stock_quantity'];
                    $is_out_of_stock = $maintenance_mode || $stock_quantity <= 0;
                    $is_low_stock = !$maintenance_mode && $stock_quantity <= 3 && $stock_quantity > 0;

                    $stock_message = '';
                    if ($is_out_of_stock) {
                        $stock_message = '<p class="stock-status stock-out">No Stock</p>';
                    } elseif ($is_low_stock) {
                        $stock_message = '<p class="stock-status stock-low">Stock: ' . $stock_quantity . ' qty</p>';
                    }

                    $is_wishlisted = in_array($row['id'], $wishlisted_products);
                    echo '<div class="product-card">';
                    echo '<img src="../images/' . htmlspecialchars($row['image']) . '" alt="' . htmlspecialchars($row['name']) . '" class="product-img">';
                    echo '<div class="product-details">';
                    echo '<h3>' . htmlspecialchars($row['name']) . '</h3>';
                    echo '<p class="description">' . htmlspecialchars($row['description']) . '</p>';
                    echo '<p class="price">Price: ₹' . number_format($row['price'], 2) . ' per kg</p>';
                    echo $stock_message;
                    echo '<button class="btn-wishlist ' . ($is_wishlisted ? 'wishlisted' : '') . '" data-product-id="' . $row['id'] . '" ' . ($maintenance_mode ? 'disabled' : '') . '>';
                    echo $is_wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist';
                    echo '</button>';
                    echo '<form class="quantity-form" id="add-to-cart-form-' . $row['id'] . '" ' . ($maintenance_mode ? 'onsubmit="return false;"' : '') . '>';
                    echo '<input type="hidden" name="product_id" value="' . $row['id'] . '">';
                    echo '<div class="quantity-controls">';
                    echo '<button type="button" class="quantity-btn minus" ' . ($maintenance_mode ? 'disabled' : '') . '>-</button>';
                    echo '<input type="number" name="quantity" value="0.5" step="0.5" min="0.5" class="quantity-input" ' . ($maintenance_mode ? 'disabled' : '') . '>';
                    echo '<button type="button" class="quantity-btn plus" ' . ($maintenance_mode ? 'disabled' : '') . '>+</button>';
                    echo '</div>';
                    echo '<button type="submit" name="add_to_cart" class="btn-add-to-cart' . ($is_out_of_stock ? ' disabled' : '') . '" ' . ($is_out_of_stock || $maintenance_mode ? 'disabled' : '') . '>Add to Cart</button>';
                    echo '</form>';
                    echo '</div>';
                    echo '</div>';
                }
                if (!$products_found) {
                    echo '<p class="no-results">No products found.</p>';
                }
                ?>
            </div>
            <div class="wishlist-message" id="wishlist-message"></div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Quantity controls
            const quantityControls = document.querySelectorAll('.quantity-controls');
            quantityControls.forEach(control => {
                const minusBtn = control.querySelector('.minus');
                const plusBtn = control.querySelector('.plus');
                const input = control.querySelector('.quantity-input');

                minusBtn.addEventListener('click', () => {
                    if (minusBtn.disabled) return;
                    let value = parseFloat(input.value) || 0.5;
                    if (value > 0.5) input.value = (value - 0.5).toFixed(1);
                });

                plusBtn.addEventListener('click', () => {
                    if (plusBtn.disabled) return;
                    let value = parseFloat(input.value) || 0.5;
                    input.value = (value + 0.5).toFixed(1);
                });
            });

            // Handle Add to Cart with fetch API
            const quantityForms = document.querySelectorAll('.quantity-form');
            quantityForms.forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const addToCartBtn = form.querySelector('.btn-add-to-cart');
                    if (addToCartBtn.disabled) {
                        alert('This product is out of stock or the website is under maintenance.');
                        return;
                    }

                    const formData = new FormData(form);
                    const data = new URLSearchParams(formData);
                    data.append('add_to_cart', true);

                    try {
                        const response = await fetch('../cart.php', {
                            method: 'POST',
                            body: data,
                            headers: {
                                'Accept': 'application/json'
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
                            alert('Product has been added to the cart successfully');
                            updateCartCount();
                            shakeCartIcon();
                        } else {
                            alert('Error adding product to cart: ' + (result.message || 'Unknown error'));
                            console.error('Error response:', result);
                        }
                    } catch (error) {
                        console.error('Fetch error:', error);
                        alert('An error occurred while adding to cart: ' + error.message);
                    }
                });
            });

            // Handle Wishlist Toggle
            const wishlistButtons = document.querySelectorAll('.btn-wishlist');
            console.log('Wishlist Buttons Found:', wishlistButtons.length);
            wishlistButtons.forEach(button => {
                console.log('Button found with product ID:', button.dataset.productId);
                button.addEventListener('click', async (e) => {
                    if (button.disabled) {
                        alert('Wishlist functionality is disabled during maintenance mode.');
                        return;
                    }

                    const productId = button.dataset.productId;
                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'Accept': 'application/json'
                            },
                            body: new URLSearchParams({
                                'toggle_wishlist': true,
                                'product_id': productId
                            }),
                            credentials: 'same-origin'
                        });

                        const result = await response.json();
                        if (result.status === 'added') {
                            button.classList.add('wishlisted');
                            button.textContent = 'Remove from Wishlist';
                            showWishlistMessage('Product added to wishlist!');
                        } else if (result.status === 'removed') {
                            button.classList.remove('wishlisted');
                            button.textContent = 'Add to Wishlist';
                            showWishlistMessage('Product removed from wishlist!');
                        } else if (result.status === 'error') {
                            alert(result.message);
                        } else {
                            alert('Error updating wishlist');
                        }
                    } catch (error) {
                        console.error('Error toggling wishlist:', error);
                        alert('An error occurred while updating wishlist: ' + error.message);
                    }
                });
            });

            // Show wishlist message
            function showWishlistMessage(message) {
                const messageElement = document.getElementById('wishlist-message');
                messageElement.textContent = message;
                messageElement.style.display = 'block';
                setTimeout(() => {
                    messageElement.style.display = 'none';
                }, 2000);
            }

            // Update cart count
            async function updateCartCount() {
                try {
                    const response = await fetch('../cart.php?action=get_count', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    });

                    console.log('HTTP Status for get_count:', response.status);
                    const rawText = await response.text();
                    console.log('Raw response from get_count:', rawText);

                    if (!rawText) {
                        throw new Error('Empty response from server');
                    }

                    let data;
                    try {
                        data = JSON.parse(rawText);
                    } catch (parseError) {
                        throw new Error('Failed to parse JSON: ' + parseError.message + ' Raw response: ' + rawText);
                    }

                    const cartCountElement = document.getElementById('cart-count');
                    if (cartCountElement) {
                        cartCountElement.textContent = data.count || 0;
                        cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                    }
                } catch (error) {
                    console.error('Error fetching cart count:', error);
                }
            }

            // Shake cart icon animation
            function shakeCartIcon() {
                const cartIcon = document.getElementById('cart-icon');
                if (cartIcon) {
                    cartIcon.classList.add('shake');
                    setTimeout(() => cartIcon.classList.remove('shake'), 500);
                } else {
                    console.error('Cart icon element (#cart-icon) not found in DOM');
                }
            }

            // Navigate to cart page on cart icon click
            const cartIcon = document.getElementById('cart-icon');
            if (cartIcon) {
                cartIcon.addEventListener('click', () => {
                    window.location.href = '../cart.php';
                });
            }

            // Initial cart count load
            updateCartCount();
        });
    </script>
</body>
</html>