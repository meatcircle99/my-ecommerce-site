<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db.php';
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Meatcircle</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Main content with margin to avoid header overlap -->
    <main class="about-content">
        <h1 class="page-title">About Us</h1>
        <div class="about-container">
            <p>Welcome to <strong>Meatcircle</strong>, your trusted partner in delivering fresh, high-quality meat products directly to your doorstep. At Meatcircle, we are passionate about providing premium poultry, mutton, fish, and more, ensuring every bite is fresh, flavorful, and responsibly sourced.</p>
            
            <h2>Our Story</h2>
            <p>Founded in 2023, Meatcircle was born from a vision to revolutionize the meat industry with transparency, quality, and convenience. Based in [Your Location], we source our products from local farmers and trusted suppliers, adhering to strict hygiene and sustainability standards. Our mission is to bring the farm-fresh taste to your table, making every meal a delightful experience.</p>
            
            <h2>Why Choose Us?</h2>
            <ul>
                <li><strong>Freshness Guaranteed:</strong> Our products are delivered within 24 hours of processing.</li>
                <li><strong>Quality Assurance:</strong> We maintain rigorous quality checks at every step.</li>
                <li><strong>Convenience:</strong> Order online and enjoy doorstep delivery with our user-friendly platform.</li>
                <li><strong>Sustainable Practices:</strong> We prioritize eco-friendly packaging and ethical sourcing.</li>
            </ul>
            
            <h2>Our Team</h2>
            <p>Our dedicated team of experts, including farmers, butchers, and customer service professionals, works tirelessly to ensure your satisfaction. We’re committed to building a community around fresh, healthy eating and supporting local agriculture.</p>
            
            <p>For inquiries, contact us at <a href="mailto:contact@meatcircle.com">contact@meatcircle.com</a> or call us at +91-9876543210. Follow us on social media for updates and promotions!</p>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Ensure cart count updates
            const cartCountElement = document.getElementById('cart-count');
            if (cartCountElement) {
                async function updateCartCount() {
                    try {
                        const response = await fetch('../cart.php?action=get_count', {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        });

                        console.log('HTTP Status for get_count on about:', response.status);
                        const rawText = await response.text();
                        console.log('Raw response from get_count on about:', rawText);

                        if (!rawText) {
                            throw new Error('Empty response from server');
                        }

                        let data;
                        try {
                            data = JSON.parse(rawText);
                        } catch (parseError) {
                            throw new Error('Failed to parse JSON on about: ' + parseError.message + ' Raw response: ' + rawText);
                        }

                        cartCountElement.textContent = data.count || 0;
                        cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                    } catch (error) {
                        console.error('Error fetching cart count on about:', error);
                    }
                }

                // Initial cart count load on about page
                updateCartCount();

                // Set up SSE for real-time updates (optional, but consistent with other pages)
                const eventSource = new EventSource('../cart.php?action=listen_cart&user_id=' . encodeURIComponent('<?php echo $_SESSION['user_id'] ?? 'anonymous'; ?>'));

                eventSource.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        console.log('SSE event data on about:', data);
                        if (data.count !== undefined) {
                            cartCountElement.textContent = data.count;
                            cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                            console.log('Cart count updated via SSE on about to:', data.count);
                        }
                    } catch (e) {
                        console.error('Error parsing SSE data on about:', e);
                        console.log('Raw SSE data on about:', event.data);
                    }
                };

                eventSource.onerror = function() {
                    console.error('SSE connection error on about, attempting to reconnect...');
                    setTimeout(() => {
                        if (eventSource.readyState === EventSource.CLOSED) {
                            eventSource.close();
                            const newSource = new EventSource('../cart.php?action=listen_cart&user_id=' . encodeURIComponent('<?php echo $_SESSION['user_id'] ?? 'anonymous'; ?>'));
                            eventSource.onmessage = eventSource.onmessage; // Reattach event handler
                            eventSource.onerror = eventSource.onerror; // Reattach error handler
                        }
                    }, 5000); // Retry after 5 seconds
                };
            } else {
                console.error('Cart count element (#cart-count) not found in DOM on about');
            }

            // Navigate to cart page on cart icon click
            const cartIcon = document.getElementById('cart-icon');
            if (cartIcon) {
                cartIcon.addEventListener('click', () => {
                    window.location.href = '../cart.php';
                });
            } else {
                console.error('Cart icon element (#cart-icon) not found in DOM on about');
            }
        });
    </script>
</body>
</html>