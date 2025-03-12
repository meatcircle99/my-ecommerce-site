<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session if not already started (handled by header.php, but safe to keep)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Check if header.php exists and is included
if (!file_exists(dirname(__DIR__) . '/includes/header.php')) {
    die('Header file not found at: ' . dirname(__DIR__) . '/includes/header.php');
}

// Include the header using require_once with a verified path
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php
include '../config/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Meatcircle</title>
    <!-- Load header.php’s styles first to ensure precedence, then style.css -->
    <style>
        /* Inline styles from header.php (copied here temporarily for debugging) */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #fff; /* Match header.php background */
            padding: 15px 20px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        body {
            padding-top: 10px; /* Adjust for fixed header */
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
        }
    </style>
    <!-- Load style.css after header.php’s styles to allow overrides for contact content -->
    <link rel="stylesheet" href="../css/style.css">
    <!-- Load Font Awesome last for icons, ensuring no header conflicts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Page-specific content starts here, wrapped in a container with padding for fixed header -->
    <div class="container" style="padding-top: 70px;">
        <main class="contact-content">
            <h1 class="page-title">Contact Us</h1>
            <div class="contact-container">
                <p>We’d love to hear from you! Whether you have questions about our products, need assistance with an order, or want to provide feedback, our team at <strong>Meatcircle</strong> is here to help.</p>

                <h2>Contact Information</h2>
                <p><strong>Email:</strong> <a href="mailto:contact@meatcircle.com">contact@meatcircle.com</a></p>
                <p><strong>Phone:</strong> +91-9876543210 (Monday - Friday, 9 AM - 6 PM IST)</p>
                <p><strong>Address:</strong> 123 Meat Lane, Fresh City, [Your State], [Your Country]</p>

                <h2>Contact Form</h2>
                <form method="POST" class="contact-form">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message:</label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Send Message</button>
                </form>

                <p>Follow us on social media for the latest updates:</p>
                <div class="social-links">
                    <a href="https://facebook.com/meatcircle" target="_blank" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://twitter.com/meatcircle" target="_blank" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="https://instagram.com/meatcircle" target="_blank" class="social-icon"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Debug: Check if header elements and styles are loaded
        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOM loaded, checking header elements and styles...');
            const header = document.querySelector('.header');
            const cartCount = document.getElementById('cart-count');
            const cartIcon = document.getElementById('cart-icon');
            const searchButton = document.getElementById('search-button');

            if (header) {
                console.log('Header element found:', header);
                // Check computed styles
                const computedStyles = window.getComputedStyle(header);
                console.log('Header computed styles:', {
                    backgroundColor: computedStyles.backgroundColor,
                    position: computedStyles.position,
                    zIndex: computedStyles.zIndex
                });
            } else {
                console.error('Header element (.header) not found');
            }

            if (cartCount) console.log('Cart count element found:', cartCount);
            else console.error('Cart count element (#cart-count) not found');

            if (cartIcon) console.log('Cart icon element found:', cartIcon);
            else console.error('Cart icon element (#cart-icon) not found');

            if (searchButton) console.log('Search button element found:', searchButton);
            else console.error('Search button element (#search-button) not found');

            // Handle contact form submission (simple client-side validation)
            const contactForm = document.querySelector('.contact-form');
            if (contactForm) {
                contactForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const name = document.getElementById('name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const message = document.getElementById('message').value.trim();

                    if (name && email && message) {
                        alert('Thank you for your message! We will get back to you soon.');
                        contactForm.reset();
                    } else {
                        alert('Please fill in all fields.');
                    }
                });
            }
        });
    </script>
</body>
</html>