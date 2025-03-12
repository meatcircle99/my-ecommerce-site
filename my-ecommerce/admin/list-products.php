<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$success = '';
$error = '';

// Pagination settings
$products_per_page = 10;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $products_per_page;

// Initialize search query
$search_query = isset($_GET['search']) && !empty(trim($_GET['search'])) ? trim($_GET['search']) : '';

// Build the products SQL query
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";
$count_sql = "SELECT COUNT(DISTINCT p.id) as total 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE 1=1";
$params = [];

if ($search_query) {
    $sql .= " AND (p.name LIKE :search OR p.description LIKE :search OR c.name LIKE :search OR p.unit_type LIKE :search OR CAST(p.price AS CHAR) LIKE :search)";
    $count_sql .= " AND (p.name LIKE :search OR p.description LIKE :search OR c.name LIKE :search OR p.unit_type LIKE :search OR CAST(p.price AS CHAR) LIKE :search)";
    $params[':search'] = "%$search_query%";
}

// Add pagination to the main query
$sql .= " ORDER BY p.id DESC LIMIT :offset, :limit";

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Product deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting product: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Products - Meatcircle Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .products-container {
            width: 100%;
            margin: 40px 0;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .products-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .products-title i {
            color: #4f46e5;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }
        .products-title i:hover {
            color: #7c3aed;
        }
        .alert {
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .search-bar {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        .search-bar input[type="text"] {
            width: 100%;
            padding: 12px 40px 12px 20px;
            border: 1px solid #e5e7eb;
            border-radius: 25px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            font-size: 1rem;
            color: #374151;
            transition: box-shadow 0.3s ease;
        }
        .search-bar input[type="text"]:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
            border-color: #3b82f6;
        }
        .search-bar i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #10b981;
            font-size: 1.2rem;
            cursor: pointer;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            font-size: 0.8rem;
        }
        .btn-edit {
            background: linear-gradient(90deg, #10b981, #059669);
            color: white;
        }
        .btn-edit:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
        }
        .btn-delete {
            background: linear-gradient(90deg, #ef4444, #dc2626);
            color: white;
            text-decoration: none;
        }
        .btn-delete:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .products-table th, .products-table td {
            padding: 8px;
            text-align: left;
            font-size: 0.85rem;
            border-bottom: 1px solid #eee;
        }
        .products-table th {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .products-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .products-table tr:hover {
            background: #eef2ff;
            transition: background 0.2s ease;
        }
        .products-table td {
            color: #475569;
        }
        .table-image {
            max-width: 60px;
            max-height: 45px;
            object-fit: contain;
            border-radius: 4px;
        }
        .no-image {
            color: #78909c;
            font-style: italic;
        }
        .no-products {
            text-align: center;
            padding: 20px;
            color: #78909c;
            font-size: 1rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
        }
        .pagination a {
            background: #ffffff;
            color: #1a3c34;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .pagination a:hover {
            background: #eef2ff;
            transform: scale(1.05);
        }
        .pagination a.disabled {
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
            transform: none;
        }
        @media (max-width: 768px) {
            .products-table th, .products-table td {
                padding: 6px;
                font-size: 0.75rem;
            }
            .table-image {
                max-width: 50px;
                max-height: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="products-container">
        <h1 class="products-title"><i class="fas fa-box"></i> List Products</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" action="dashboard.php" id="search-form">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by Name, Description, Category, Unit Type, or Price...">
                <i class="fas fa-search" id="search-icon"></i>
                <input type="hidden" name="page" value="products">
                <input type="hidden" name="subpage" value="list-products">
                <input type="hidden" name="p" value="<?php echo $page; ?>">
            </form>
        </div>

        <!-- Products Table -->
        <div class="card table-card">
            <table class="products-table">
                <thead>
                    <tr>
                        <th>USER ID</th>
                        <th>NAME</th>
                        <th>PRICE (INR)</th>
                        <th>CATEGORY</th>
                        <th>IMAGE</th>
                        <th>DESCRIPTION</th>
                        <th>UNIT TYPE</th>
                        <th>INCREMENT VALUE</th>
                        <th>STOCK QUANTITY</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // Count total products for pagination
                        $count_stmt = $conn->prepare($count_sql);
                        foreach ($params as $key => $value) {
                            if ($key !== ':offset' && $key !== ':limit') {
                                $count_stmt->bindValue($key, $value);
                            }
                        }
                        $count_stmt->execute();
                        $total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        $total_pages = ceil($total_products / $products_per_page);

                        // Fetch products
                        $stmt = $conn->prepare($sql);
                        foreach ($params as $key => $value) {
                            if ($key !== ':offset' && $key !== ':limit') {
                                $stmt->bindValue($key, $value);
                            }
                        }
                        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                        $stmt->bindValue(':limit', $products_per_page, PDO::PARAM_INT);
                        $stmt->execute();
                        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($products) > 0) {
                            foreach ($products as $product) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($product['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                                echo "<td>" . number_format($product['price'], 2) . "</td>";
                                echo "<td>" . htmlspecialchars($product['category_name'] ?? 'N/A') . "</td>";
                                echo "<td>";
                                if (!empty($product['image']) && file_exists("../images/" . $product['image'])) {
                                    echo "<img src='../images/" . htmlspecialchars($product['image']) . "' alt='Product' class='table-image'>";
                                } else {
                                    echo "<span class='no-image'>No image</span>";
                                }
                                echo "</td>";
                                echo "<td>" . htmlspecialchars($product['description'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($product['unit_type'] ?? 'N/A') . "</td>";
                                echo "<td>" . number_format($product['increment_value'], 2) . "</td>";
                                echo "<td>" . htmlspecialchars($product['stock_quantity']) . "</td>";
                                echo "<td>";
                                echo "<a href='?page=products&subpage=edit-product&id=" . htmlspecialchars($product['id']) . "' class='btn btn-edit'><i class='fas fa-edit'></i> Edit</a>";
                                echo "<a href='?page=products&subpage=list-products&action=delete&id=" . htmlspecialchars($product['id']) . "' class='btn btn-delete' onclick='return confirm(\"Are you sure?\")'><i class='fas fa-trash'></i> Delete</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='10' class='no-products'>No products found.</td></tr>";
                        }

                        // Pagination
                        echo "</tbody></table>";
                        echo "<div class='pagination'>";
                        $prev_page = $page > 1 ? $page - 1 : 1;
                        $next_page = $page < $total_pages ? $page + 1 : $total_pages;
                        $query_string_prev = http_build_query(array_merge($_GET, ['p' => $prev_page]));
                        $query_string_next = http_build_query(array_merge($_GET, ['p' => $next_page]));
                        echo "<a href='dashboard.php?$query_string_prev' class='pagination-link " . ($page <= 1 ? 'disabled' : '') . "'><i class='fas fa-chevron-left'></i> Previous</a>";
                        echo "<a href='dashboard.php?$query_string_next' class='pagination-link " . ($page >= $total_pages ? 'disabled' : '') . "'><i class='fas fa-chevron-right'></i> Next</a>";
                        echo "</div>";
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='10' class='no-products'>Error fetching products: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                    }
                    ?>
                </div>
            </div>
        </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const handleSearch = (form) => {
                const formData = new FormData(form);
                const url = 'dashboard.php?' + new URLSearchParams(formData).toString();
                console.log('Search Submitted:', url);
                fetch(url, { method: 'GET' })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                    })
                    .then(data => {
                        console.log('Search Response Received');
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(data, 'text/html');
                        const newContent = doc.querySelector('.products-container');
                        if (newContent) {
                            document.querySelector('.products-container').innerHTML = newContent.innerHTML;
                            attachEventListeners();
                        } else {
                            document.querySelector('.products-container').innerHTML = '<div class="no-products">Error: Invalid response from server</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Search Error:', error);
                        document.querySelector('.products-container').innerHTML = '<div class="no-products">Error loading results</div>';
                    });
            };

            function attachEventListeners() {
                const searchForm = document.getElementById('search-form');
                const searchIcon = document.getElementById('search-icon');
                const paginationLinks = document.querySelectorAll('.pagination-link');

                if (searchForm) {
                    searchForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        handleSearch(searchForm);
                    });
                }

                if (searchIcon) {
                    searchIcon.addEventListener('click', () => {
                        handleSearch(searchForm);
                    });
                }

                paginationLinks.forEach(link => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        const url = link.href;
                        console.log('Pagination Clicked:', url);
                        fetch(url)
                            .then(response => {
                                if (!response.ok) throw new Error('Network response was not ok');
                                return response.text();
                            })
                            .then(data => {
                                console.log('Pagination Response Received');
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(data, 'text/html');
                                const newContent = doc.querySelector('.products-container');
                                if (newContent) {
                                    document.querySelector('.products-container').innerHTML = newContent.innerHTML;
                                    attachEventListeners();
                                } else {
                                    document.querySelector('.products-container').innerHTML = '<div class="no-products">Error: Invalid response from server</div>';
                                }
                            })
                            .catch(error => {
                                console.error('Pagination Error:', error);
                                document.querySelector('.products-container').innerHTML = '<div class="no-products">Error loading results</div>';
                            });
                    });
                });
            }

            attachEventListeners();
        });
    </script>
</body>
</html>