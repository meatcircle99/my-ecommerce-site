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
    <title>Terms & Conditions - Meatcircle</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Main content with margin to avoid header overlap -->
    <main class="terms-content">
        <h1 class="page-title">Terms & Conditions</h1>
        <div class="terms-container">
            <p>Welcome to <strong>Meatcircle</strong>. By accessing and using our website, you agree to comply with and be bound by the following terms and conditions. Please read them carefully.</p>

            <h2>1. Acceptance of Terms</h2>
            <p>By using our services, you agree to these Terms & Conditions, our Privacy Policy, and any additional terms applicable to specific services. If you do not agree, please do not use our website.</p>

            <h2>2. Use of Service</h2>
            <ul>
                <li>You must be at least 18 years old or have parental consent to use our services.</li>
                <li>You agree to use our site only for lawful purposes and in accordance with these terms.</li>
                <li>You are responsible for maintaining the confidentiality of your account and password.</li>
            </ul>

            <h2>3. Product Purchases</h2>
            <p>All products are subject to availability. Prices are subject to change without notice. We reserve the right to refuse or cancel orders at our discretion, including due to errors, fraud, or unavailability.</p>

            <h2>4. Payment and Delivery</h2>
            <p>We accept payments via Credit Card, Debit Card, and Cash on Delivery. Delivery times are estimates and may vary based on location and availability. You are responsible for providing accurate delivery information.</p>

            <h2>5. Intellectual Property</h2>
            <p>All content on this website, including text, images, logos, and trademarks, is the property of Meatcircle and protected by copyright and intellectual property laws. You may not use, reproduce, or distribute any content without permission.</p>

            <h2>6. Limitation of Liability</h2>
            <p>Meatcircle is not liable for any indirect, incidental, or consequential damages arising from the use of our services. Our liability is limited to the amount paid for the products.</p>

            <h2>7. Governing Law</h2>
            <p>These terms are governed by the laws of [Your Country/State]. Any disputes will be resolved in the courts of [Your Location].</p>

            <p>For questions or concerns, contact us at <a href="mailto:contact@meatcircle.com">contact@meatcircle.com</a> or +91-9876543210. These terms may be updated periodically, and continued use constitutes acceptance of changes.</p>
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

                        console.log('HTTP Status for get_count on terms:', response.status);
                        const rawText = await response.text();
                        console.log('Raw response from get_count on terms:', rawText);

                        if (!rawText) {
                            throw new Error('Empty response from server');
                        }

                        let data;
                        try {
                            data = JSON.parse(rawText);
                        } catch (parseError) {
                            throw new Error('Failed to parse JSON on terms: ' + parseError.message + ' Raw response: ' + rawText);
                        }

                        cartCountElement.textContent = data.count || 0;
                        cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                    } catch (error) {
                        console.error('Error fetching cart count on terms:', error);
                    }
                }

                // Initial cart count load on terms page
                updateCartCount();

                // Set up SSE for real-time updates (optional, but consistent with other pages)
                const eventSource = new EventSource('../cart.php?action=listen_cart&user_id=' . encodeURIComponent('<?php echo $_SESSION['user_id'] ?? 'anonymous'; ?>'));

                eventSource.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        console.log('SSE event data on terms:', data);
                        if (data.count !== undefined) {
                            cartCountElement.textContent = data.count;
                            cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                            console.log('Cart count updated via SSE on terms to:', data.count);
                        }
                    } catch (e) {
                        console.error('Error parsing SSE data on terms:', e);
                        console.log('Raw SSE data on terms:', event.data);
                    }
                };

                eventSource.onerror = function() {
                    console.error('SSE connection error on terms, attempting to reconnect...');
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
                console.error('Cart count element (#cart-count) not found in DOM on terms');
            }

            // Navigate to cart page on cart icon click
            const cartIcon = document.getElementById('cart-icon');
            if (cartIcon) {
                cartIcon.addEventListener('click', () => {
                    window.location.href = '../cart.php';
                });
            } else {
                console.error('Cart icon element (#cart-icon) not found in DOM on terms');
            }
        });
    </script>
</body>
</html>