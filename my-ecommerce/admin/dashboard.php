<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include '../config/db.php'; // Ensure $conn is set up
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    die("Database connection error: " . htmlspecialchars($e->getMessage()));
}

// Determine which page to load
$page = isset($_GET['page']) ? $_GET['page'] : 'welcome';

// Define default subpage with proper parentheses
$subpage = isset($_GET['subpage']) ? $_GET['subpage'] : (
    ($page === 'products') ? 'add-product' : (
        ($page === 'coupons') ? 'add-coupon' : (
            ($page === 'banners') ? 'add-banner' : (
                ($page === 'categories') ? 'add-category' : (
                    ($page === 'blogs') ? 'add-blog' : (
                        ($page === 'time-slots') ? 'add-time-slot' : 'add-product'
                    )
                )
            )
        )
    )
);

$validPages = ['welcome', 'products', 'categories', 'coupons', 'orders', 'users', 'banners', 'earnings', 'blogs', 'maintenance', 'time-slots'];
if (!in_array($page, $validPages)) {
    $page = 'welcome';
}

// Determine active subpage for each section
$validSubpages = [
    'products' => ['add-product', 'edit-product', 'list-products'],
    'coupons' => ['add-coupon', 'edit-coupon', 'list-coupons'],
    'banners' => ['add-banner', 'edit-banner', 'list-banners'],
    'categories' => ['add-category', 'edit-category', 'list-categories'],
    'blogs' => ['add-blog', 'edit-blog', 'list-blogs'],
    'time-slots' => ['add-time-slot', 'list-time-slots']
];
if (isset($validSubpages[$page]) && !in_array($subpage, $validSubpages[$page])) {
    $subpage = $validSubpages[$page][0]; // Default to first subpage
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo ucfirst($page); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0e7ff, #f3f4f6);
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }
        .dashboard-container {
            display: flex;
            width: 100%;
        }
        .sidebar {
            width: 250px;
            background: #1e293b;
            color: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100%;
            overflow-y: auto;
        }
        .sidebar h1 {
            font-size: 1.5rem;
            margin: 0 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #a5b4fc;
        }
        .nav-tabs {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .nav-tab {
            background: #334155;
            color: #e2e8f0;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .nav-tab.active {
            background: linear-gradient(90deg, #6366f1, #4f46e5);
            color: white;
        }
        .nav-tab:hover {
            background: #475569;
            color: white;
        }
        .sub-menu {
            display: none;
            margin-left: 20px;
            flex-direction: column;
            gap: 5px;
            margin-top: 5px;
        }
        .sub-menu a {
            background: #475569;
            color: #e2e8f0;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            width: 150px;
        }
        .sub-menu a.active {
            background: linear-gradient(90deg, #818cf8, #a5b4fc);
            color: white;
        }
        .sub-menu a:hover {
            background: #4a5568;
            color: white;
        }
        .main-content {
            flex: 1;
            padding: 40px;
            margin-left: 250px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        h2 {
            color: #1e293b;
            font-size: 1.8rem;
            margin-bottom: 20px;
        }
        .form-container, .list-container {
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #475569;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .btn-submit {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-submit:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        }
        .logout-btn {
            background: linear-gradient(90deg, #ef4444, #dc2626);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .logout-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        th, td {
            padding: 15px;
            text-align: left;
        }
        th {
            background: #334155;
            color: white;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background: #f8fafc;
        }
        .action-btn {
            background: #4f46e5;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .action-btn:hover {
            background: #6366f1;
        }
        .delete-btn {
            background: #ef4444;
        }
        .delete-btn:hover {
            background: #dc2626;
        }
        .debug-info {
            color: #666;
            font-size: 0.9rem;
            margin-top: 10px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 4px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <div class="nav-tabs">
                <a href="?page=welcome" class="nav-tab <?php echo $page === 'welcome' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a>
                <div class="nav-tab" onclick="toggleSubMenu('products')">
                    <i class="fas fa-box"></i> Products
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div id="products-sub-menu" class="sub-menu">
                    <a href="?page=products&subpage=add-product" class="<?php echo $page === 'products' && $subpage === 'add-product' ? 'active' : ''; ?>"><i class="fas fa-plus"></i> Add Product</a>
                    <a href="?page=products&subpage=edit-product" class="<?php echo $page === 'products' && $subpage === 'edit-product' ? 'active' : ''; ?>"><i class="fas fa-edit"></i> Edit Product</a>
                    <a href="?page=products&subpage=list-products" class="<?php echo $page === 'products' && $subpage === 'list-products' ? 'active' : ''; ?>"><i class="fas fa-list"></i> List Products</a>
                </div>
                <div class="nav-tab" onclick="toggleSubMenu('coupons')">
                    <i class="fas fa-ticket-alt"></i> Coupons
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div id="coupons-sub-menu" class="sub-menu">
                    <a href="?page=coupons&subpage=add-coupon" class="<?php echo $page === 'coupons' && $subpage === 'add-coupon' ? 'active' : ''; ?>"><i class="fas fa-plus"></i> Add Coupon</a>
                    <a href="?page=coupons&subpage=edit-coupon" class="<?php echo $page === 'coupons' && $subpage === 'edit-coupon' ? 'active' : ''; ?>"><i class="fas fa-edit"></i> Edit Coupon</a>
                    <a href="?page=coupons&subpage=list-coupons" class="<?php echo $page === 'coupons' && $subpage === 'list-coupons' ? 'active' : ''; ?>"><i class="fas fa-list"></i> List Coupons</a>
                </div>
                <div class="nav-tab" onclick="toggleSubMenu('banners')">
                    <i class="fas fa-image"></i> Banners
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div id="banners-sub-menu" class="sub-menu">
                    <a href="?page=banners&subpage=add-banner" class="<?php echo $page === 'banners' && $subpage === 'add-banner' ? 'active' : ''; ?>"><i class="fas fa-plus"></i> Add Banner</a>
                    <a href="?page=banners&subpage=edit-banner" class="<?php echo $page === 'banners' && $subpage === 'edit-banner' ? 'active' : ''; ?>"><i class="fas fa-edit"></i> Edit Banner</a>
                    <a href="?page=banners&subpage=list-banners" class="<?php echo $page === 'banners' && $subpage === 'list-banners' ? 'active' : ''; ?>"><i class="fas fa-list"></i> List Banners</a>
                </div>
                <div class="nav-tab" onclick="toggleSubMenu('categories')">
                    <i class="fas fa-tags"></i> Categories
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div id="categories-sub-menu" class="sub-menu">
                    <a href="?page=categories&subpage=add-category" class="<?php echo $page === 'categories' && $subpage === 'add-category' ? 'active' : ''; ?>"><i class="fas fa-plus"></i> Add Category</a>
                    <a href="?page=categories&subpage=list-categories" class="<?php echo $page === 'categories' && $subpage === 'list-categories' ? 'active' : ''; ?>"><i class="fas fa-list"></i> List Categories</a>
                </div>
                <div class="nav-tab" onclick="toggleSubMenu('blogs')">
                    <i class="fas fa-blog"></i> Manage Blogs
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div id="blogs-sub-menu" class="sub-menu">
                    <a href="?page=blogs&subpage=add-blog" class="<?php echo $page === 'blogs' && $subpage === 'add-blog' ? 'active' : ''; ?>"><i class="fas fa-plus"></i> Add Blog</a>
                    <a href="?page=blogs&subpage=edit-blog" class="<?php echo $page === 'blogs' && $subpage === 'edit-blog' ? 'active' : ''; ?>"><i class="fas fa-edit"></i> Edit Blog</a>
                    <a href="?page=blogs&subpage=list-blogs" class="<?php echo $page === 'blogs' && $subpage === 'list-blogs' ? 'active' : ''; ?>"><i class="fas fa-list"></i> List Blogs</a>
                </div>
                <div class="nav-tab" onclick="toggleSubMenu('time-slots')">
                    <i class="fas fa-clock"></i> Time Slots
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div id="time-slots-sub-menu" class="sub-menu">
                    <a href="?page=time-slots&subpage=add-time-slot" class="<?php echo $page === 'time-slots' && $subpage === 'add-time-slot' ? 'active' : ''; ?>"><i class="fas fa-plus"></i> Add Time Slot</a>
                    <a href="?page=time-slots&subpage=list-time-slots" class="<?php echo $page === 'time-slots' && $subpage === 'list-time-slots' ? 'active' : ''; ?>"><i class="fas fa-list"></i> List Time Slots</a>
                </div>
                <a href="?page=orders" class="nav-tab <?php echo $page === 'orders' ? 'active' : ''; ?>"><i class="fas fa-shopping-bag"></i> Orders</a>
                <a href="?page=users" class="nav-tab <?php echo $page === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a>
                <a href="?page=earnings" class="nav-tab <?php echo $page === 'earnings' ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave"></i> Earnings</a>
                <a href="?page=maintenance" class="nav-tab <?php echo $page === 'maintenance' ? 'active' : ''; ?>"><i class="fas fa-tools"></i> Maintenance Mode</a>
            </div>
            <form action="logout.php" method="POST" style="margin-top: 30px;">
                <button type="submit" name="logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </div>
        <div class="main-content">
            <?php
            // Directly handle "Edit Coupon" case to bypass dynamic include
            if ($page === 'coupons' && $subpage === 'edit-coupon') {
                $editCouponPath = __DIR__ . '/edit-coupon.php';
                if (file_exists($editCouponPath)) {
                    include $editCouponPath;
                } else {
                    echo "<div style='color: #ef4444; padding: 10px;'>Error: 'edit-coupon.php' not found at $editCouponPath.</div>";
                }
            } else {
                // Fallback to original dynamic include logic for other pages
                if (in_array($page, ['products', 'coupons', 'banners', 'categories', 'blogs', 'time-slots'])) {
                    $pageFile = $subpage . '.php';
                } else {
                    $pageFile = $page . '.php';
                }

                if (file_exists($pageFile)) {
                    include $pageFile;
                } else {
                    echo "<div style='color: #ef4444; padding: 10px;'>Error: Page '$pageFile' not found.</div>";
                }
            }
            ?>
        </div>
    </div>

    <script>
        function toggleSubMenu(menuId) {
            const subMenu = document.getElementById(menuId + '-sub-menu');
            if (subMenu.style.display === 'flex') {
                subMenu.style.display = 'none';
            } else {
                subMenu.style.display = 'flex';
            }
        }

        // Auto-open the sub-menu if on a relevant subpage
        document.addEventListener('DOMContentLoaded', function() {
            const pagesWithSubmenus = ['products', 'coupons', 'banners', 'categories', 'blogs', 'time-slots'];
            if (pagesWithSubmenus.includes('<?php echo $page; ?>')) {
                const subMenu = document.getElementById('<?php echo $page; ?>-sub-menu');
                subMenu.style.display = 'flex';
            }
        });

        // Redirect to dashboard.php on page refresh only if no query parameters
        window.addEventListener('load', function() {
            if (performance.navigation.type === 1) { // 1 indicates a page refresh
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.toString()) { // No query parameters
                    window.location.href = 'dashboard.php';
                }
            }
        });
    </script>
</body>
</html>