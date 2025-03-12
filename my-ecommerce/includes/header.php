<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine the base path dynamically
$basePath = '/my-ecommerce'; // Base path for the project
?>

<header class="header">
    <div class="brand">
        <a href="<?php echo $basePath; ?>/pages/home.php" class="brand-link">
            <span>Meatcircle</span>
            <img src="<?php echo $basePath; ?>/images/logo.png" alt="Meatcircle" class="logo-img" style="display: none;">
        </a>
    </div>
    <div class="search-container">
        <input type="text" placeholder="Search products..." class="search-input" id="search-input">
        <button class="search-button" id="search-button">Search</button>
    </div>
    <div class="user-actions">
        <div class="menu">
            <i class="fas fa-user icon"></i>
            <span>Menu</span>
            <div class="dropdown">
                <a href="<?php echo $basePath; ?>/pages/orders.php">My Orders</a>
                <a href="<?php echo $basePath; ?>/pages/account.php">My Account</a>
                <a href="<?php echo $basePath; ?>/pages/blogs.php">Blogs</a>
                <a href="<?php echo $basePath; ?>/pages/wishlist.php">Wishlist</a> <!-- Added Wishlist link -->
                <a href="<?php echo $basePath; ?>/pages/logout.php">Logout</a>
            </div>
        </div>
        <div class="cart" id="cart-icon">
            <i class="fas fa-shopping-cart icon"></i>
            <span>Cart</span>
            <span class="cart-count" id="cart-count">0</span>
        </div>
    </div>
</header>

<style>
    /* Make the header fixed */
    .header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        background-color: #124E66; /* Adjust as needed */
        padding: 15px 20px;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    body {
        padding-top: 70px; /* Adjust to prevent content from being hidden behind header */
    }

    /* Dropdown styling */
    .dropdown a {
        display: block;
        padding: 10px;
        color: #124E66;
        text-decoration: none;
    }

    .dropdown a:hover {
        background-color: #f1f1f1;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const basePath = '<?php echo $basePath; ?>';
        console.log('Base path in JS:', basePath);

        async function updateCartCount() {
            try {
                const url = basePath + '/cart.php?action=get_count&t=' + new Date().getTime();
                console.log('Fetching cart count from:', url);
                const response = await fetch(url, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });

                const rawText = await response.text();
                console.log('Raw response from cart.php:', rawText);
                if (!rawText) throw new Error('Empty response from server');

                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (parseError) {
                    throw new Error('Failed to parse JSON: ' + parseError.message);
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

        document.getElementById('cart-icon')?.addEventListener('click', () => {
            window.location.href = basePath + '/cart.php';
        });

        updateCartCount();

        document.getElementById('search-button')?.addEventListener('click', () => {
            const query = document.getElementById('search-input').value.trim();
            if (query) {
                window.location.href = basePath + '/pages/products.php?search=' + encodeURIComponent(query);
            } else {
                alert('Please enter a search term');
            }
        });

        document.getElementById('search-input')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('search-button').click();
            }
        });
    });
</script>