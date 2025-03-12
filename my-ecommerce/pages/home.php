<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db.php';
?>

<?php
include '../includes/header.php';
include '../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meatcircle</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Header (included via header.php, already updated) -->

    <!-- Banner Slider -->
    <div class="banner-slider">
    <div class="banner-slides">
        <?php
        try {
            $stmt = $conn->query("SELECT * FROM banners");
            $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($banners) > 0) {
                foreach ($banners as $index => $banner) {
                    echo '<img src="../images/' . htmlspecialchars($banner['image']) . '" alt="Banner ' . ($index + 1) . '" class="slide' . ($index === 0 ? ' active' : '') . '"';
                    if (!empty($banner['link'])) {
                        echo ' onclick="window.location.href=\'' . htmlspecialchars($banner['link']) . '\'" style="cursor: pointer;"';
                    }
                    echo ' onerror="this.style.display=\'none\';">';
                }
            } else {
                echo '<p>No banners available.</p>';
            }
        } catch (PDOException $e) {
            echo "<p>Error fetching banners: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
    <button class="arrow left"><i class="fas fa-chevron-left"></i></button>
    <button class="arrow right"><i class="fas fa-chevron-right"></i></button>
</div>

    <!-- Categories Section (Styled Smaller Square Cards, 5 per Row, Names Under Cards, Wrapping for More) -->
    <section class="categories">
        <h2 class="section-title">Shop by Categories</h2>
        <div class="category-list">
            <?php
            try {
                $stmt = $conn->query("SELECT * FROM categories");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<div class="category-card-wrapper">';
                    echo '<a href="products.php?category=' . $row['id'] . '" class="category-link">';
                    echo '<div class="category-card">';
                    echo '<div class="category-image">';
                    echo '<img src="../images/' . $row['image'] . '" alt="' . $row['name'] . '" class="category-img">';
                    echo '</div>';
                    echo '</div>';
                    echo '</a>';
                    echo '<a href="products.php?category=' . $row['id'] . '" class="category-label">' . htmlspecialchars($row['name']) . '</a>';
                    echo '</div>';
                }
            } catch (PDOException $e) {
                echo "Error fetching categories: " . $e->getMessage();
            }
            ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-links">
            <a href="about.php">About Us</a> |
            <a href="terms.php">Terms & Conditions</a> |
            <a href="contact.php">Contact Us</a>
        </div>
        <p>© 2025 Meatcircle. All rights reserved.</p>
    </footer>

    <script>
        // Ensure DOM is fully loaded before running scripts
        document.addEventListener('DOMContentLoaded', () => {
            // Banner slider functionality (automatic sliding included)
            let currentSlide = 0;
            const slides = document.querySelectorAll('.slide');
            const totalSlides = slides.length;
            let slideInterval; // To store the interval for automatic sliding

            function showSlide(index) {
                slides.forEach((slide, i) => {
                    slide.classList.remove('active');
                    if (i === index) slide.classList.add('active');
                });
            }

            // Manual navigation (left and right arrows)
            document.querySelector('.arrow.left').addEventListener('click', () => {
                clearInterval(slideInterval); // Stop auto-slide on manual click
                currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                showSlide(currentSlide);
                startAutoSlide(); // Restart auto-slide
            });

            document.querySelector('.arrow.right').addEventListener('click', () => {
                clearInterval(slideInterval); // Stop auto-slide on manual click
                currentSlide = (currentSlide + 1) % totalSlides;
                showSlide(currentSlide);
                startAutoSlide(); // Restart auto-slide
            });

            // Automatic sliding function
            function startAutoSlide() {
                slideInterval = setInterval(() => {
                    currentSlide = (currentSlide + 1) % totalSlides;
                    showSlide(currentSlide);
                }, 3000); // Slide every 3 seconds (adjust as needed)
            }

            // Initialize slider (show first slide and start auto-slide)
            showSlide(currentSlide);
            startAutoSlide();

            // Search functionality (enhanced with debugging)
            const searchInput = document.getElementById('search-input');
            const searchButton = document.getElementById('search-button');

            if (searchInput && searchButton) {
                console.log('Search elements found:', { searchInput, searchButton }); // Debug

                searchButton.addEventListener('click', () => {
                    const query = searchInput.value.trim();
                    console.log('Search button clicked with query:', query); // Debug
                    if (query) {
                        window.location.href = `../pages/products.php?search=${encodeURIComponent(query)}`;
                    } else {
                        alert('Please enter a search term');
                    }
                });

                searchInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        console.log('Enter key pressed with query:', searchInput.value.trim()); // Debug
                        searchButton.click();
                    }
                });
            } else {
                console.error('Search elements not found in DOM:', { searchInput, searchButton });
            }

            // Initialize cart count with SSE
            const cartCountElement = document.getElementById('cart-count');
            if (cartCountElement) {
                // Initial fetch to get the current count
                async function fetchInitialCartCount() {
                    try {
                        const response = await fetch('../cart.php?action=get_count', {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        });

                        console.log('HTTP Status for initial get_count:', response.status);
                        const rawText = await response.text();
                        console.log('Raw response from initial get_count:', rawText);

                        if (!rawText) {
                            throw new Error('Empty response from server');
                        }

                        let data;
                        try {
                            data = JSON.parse(rawText);
                        } catch (parseError) {
                            throw new Error('Failed to parse initial JSON: ' + parseError.message + ' Raw response: ' + rawText);
                        }

                        cartCountElement.textContent = data.count || 0;
                        cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                        console.log('Initial cart count set to:', data.count || 0);
                    } catch (error) {
                        console.error('Error fetching initial cart count:', error);
                        cartCountElement.textContent = '0';
                        cartCountElement.style.display = 'none';
                    }
                }

                // Call initial fetch
                fetchInitialCartCount();

                // Set up SSE for real-time updates
                const eventSource = new EventSource('../cart.php?action=listen_cart&user_id=' . encodeURIComponent('<?php echo $_SESSION['user_id'] ?? 'anonymous'; ?>'));

                eventSource.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        console.log('SSE event data:', data);
                        if (data.count !== undefined) {
                            cartCountElement.textContent = data.count;
                            cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                            console.log('Cart count updated via SSE to:', data.count);
                        }
                    } catch (e) {
                        console.error('Error parsing SSE data:', e);
                        console.log('Raw SSE data:', event.data);
                    }
                };

                eventSource.onerror = function() {
                    console.error('SSE connection error, attempting to reconnect...');
                    setTimeout(() => {
                        if (eventSource.readyState === EventSource.CLOSED) {
                            eventSource.close();
                            // Reconnect after a delay
                            const newSource = new EventSource('../cart.php?action=listen_cart&user_id=' . encodeURIComponent('<?php echo $_SESSION['user_id'] ?? 'anonymous'; ?>'));
                            eventSource.onmessage = eventSource.onmessage; // Reattach event handler
                            eventSource.onerror = eventSource.onerror; // Reattach error handler
                        }
                    }, 5000); // Retry after 5 seconds
                };
            } else {
                console.error('Cart count element (#cart-count) not found in DOM on home');
            }

            // Navigate to cart page on cart icon click
            const cartIcon = document.getElementById('cart-icon');
            if (cartIcon) {
                cartIcon.addEventListener('click', () => {
                    window.location.href = '../cart.php';
                });
            } else {
                console.error('Cart icon element (#cart-icon) not found in DOM');
            }
        });
    </script>
</body>
</html>