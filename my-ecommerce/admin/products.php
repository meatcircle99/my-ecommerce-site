<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

// Handle actions
$success = '';
$error = '';
$showSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $unit_type = filter_input(INPUT_POST, 'unit_type', FILTER_SANITIZE_STRING);
        $increment_value = filter_input(INPUT_POST, 'increment_value', FILTER_VALIDATE_FLOAT);
        $image = $_FILES['image']['name'] ?? '';

        if (!$name || !$price || !$description || !$category_id || !$unit_type || !$increment_value || !$image) {
            $error = "All fields are required.";
        } else {
            $target_dir = "../images/";
            $target_file = $target_dir . basename($image);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO products (category_id, name, price, image, description, unit_type, increment_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$category_id, $name, $price, $image, $description, $unit_type, $increment_value]);
                    $success = "Product added successfully!";
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Failed to upload image.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $unit_type = filter_input(INPUT_POST, 'unit_type', FILTER_SANITIZE_STRING);
        $increment_value = filter_input(INPUT_POST, 'increment_value', FILTER_VALIDATE_FLOAT);
        $image = $_FILES['image']['name'] ?? '';

        if (!$name || !$price || !$description || !$category_id || !$unit_type || !$increment_value) {
            $error = "All fields are required.";
        } else {
            try {
                if ($image) {
                    $target_dir = "../images/";
                    $target_file = $target_dir . basename($image);
                    move_uploaded_file($_FILES['image']['tmp_name'], $target_file);
                    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, description=?, category_id=?, image=?, unit_type=?, increment_value=? WHERE id=?");
                    $stmt->execute([$name, $price, $description, $category_id, $image, $unit_type, $increment_value, $id]);
                } else {
                    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, description=?, category_id=?, unit_type=?, increment_value=? WHERE id=?");
                    $stmt->execute([$name, $price, $description, $category_id, $unit_type, $increment_value, $id]);
                }
                $success = "Product updated successfully!";
                $showSuccess = true;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Product deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting product: " . $e->getMessage();
    }
}

// Fetch categories and products
$categories = $conn->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$products = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Meatcircle Admin</title>
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .products-title {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .products-title i {
            color: #4f46e5;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }
        .products-title i:hover {
            color: #7c3aed;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
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
        .form-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 0 15px;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
        }
        .card-title {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .category-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .input-field {
            padding: 12px;
            border: none;
            border-radius: 25px;
            background: #f8fafc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 1rem;
            color: #1e293b;
            transition: box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .input-field:focus {
            outline: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid #6366f1;
        }
        .input-file {
            padding: 12px 0;
        }
        .edit-image-preview {
            max-width: 150px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            color: white;
        }
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        }
        .btn-secondary {
            background: #9ca3af;
            color: white;
        }
        .btn-secondary:hover {
            background: #6b7280;
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .btn-edit {
            background: linear-gradient(90deg, #10b981, #059669);
            color: white;
        }
        .btn-edit:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .btn-delete {
            background: linear-gradient(90deg, #ef4444, #dc2626);
            color: white;
            text-decoration: none;
        }
        .btn-delete:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }
        .products-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .products-table th, .products-table td {
            padding: 15px;
            text-align: left;
            font-size: 0.95rem;
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
            max-width: 80px;
            max-height: 60px;
            object-fit: contain;
            border-radius: 4px;
        }
        .no-image {
            color: #78909c;
            font-style: italic;
        }
        .no-products {
            text-align: center;
            padding: 30px;
            color: #78909c;
            font-size: 1.1rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        @media (max-width: 768px) {
            .form-container {
                flex-direction: column;
            }
            .card {
                width: 100%;
            }
            .products-table th, .products-table td {
                padding: 10px;
                font-size: 0.85rem;
            }
            .table-image {
                max-width: 60px;
                max-height: 45px;
            }
        }
    </style>
</head>
<body>
    <div class="products-container">
        <h1 class="products-title"><i class="fas fa-box"></i> Manage Products</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Two-Column Layout -->
        <div class="form-container">
            <div class="card add-card">
                <h3 class="card-title">Add New Product</h3>
                <form method="POST" enctype="multipart/form-data" class="category-form">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="name">Product Name:</label>
                        <input type="text" name="name" id="name" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="price">Price (INR):</label>
                        <input type="number" name="price" id="price" step="0.01" min="0" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea name="description" id="description" rows="4" class="input-field" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="category_id">Category:</label>
                        <select name="category_id" id="category_id" class="input-field" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="unit_type">Unit Type:</label>
                        <select name="unit_type" id="unit_type" class="input-field" required>
                            <option value="kg">Kilograms (kg)</option>
                            <option value="units">Units</option>
                            <option value="pieces">Pieces</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="increment_value">Increment Value:</label>
                        <input type="number" name="increment_value" id="increment_value" step="0.01" min="0.01" value="0.5" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="image">Product Image:</label>
                        <input type="file" name="image" id="image" class="input-file" accept="image/*" required>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Add Product</button>
                </form>
            </div>

            <div id="editForm" style="display: none;" class="card edit-card">
                <h3 class="card-title">Edit Product</h3>
                <form method="POST" enctype="multipart/form-data" id="editProductForm" class="category-form">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    <div class="form-group">
                        <label for="editName">Product Name:</label>
                        <input type="text" name="name" id="editName" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="editPrice">Price (INR):</label>
                        <input type="number" name="price" id="editPrice" step="0.01" min="0" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description:</label>
                        <textarea name="description" id="editDescription" rows="4" class="input-field" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="editCategoryId">Category:</label>
                        <select name="category_id" id="editCategoryId" class="input-field" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editUnitType">Unit Type:</label>
                        <select name="unit_type" id="editUnitType" class="input-field" required>
                            <option value="kg">Kilograms (kg)</option>
                            <option value="units">Units</option>
                            <option value="pieces">Pieces</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editIncrementValue">Increment Value:</label>
                        <input type="number" name="increment_value" id="editIncrementValue" step="0.01" min="0.01" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="editImage">Product Image (Leave blank to keep current):</label>
                        <input type="file" name="image" id="editImage" class="input-file" accept="image/*">
                        <img id="editImagePreview" src="" alt="Current Image" class="edit-image-preview">
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Update Product</button>
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card table-card">
            <table class="products-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Price (INR)</th>
                        <th>Category</th>
                        <th>Image</th>
                        <th>Description</th>
                        <th>Unit Type</th>
                        <th>Increment Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['id']); ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($product['image']) && file_exists("../images/" . $product['image'])): ?>
                                    <img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="Product" class="table-image">
                                <?php else: ?>
                                    <span class="no-image">No image</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['description'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($product['unit_type'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($product['increment_value'], 2); ?></td>
                            <td>
                                <button class="btn btn-edit" onclick="editProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', '<?php echo $product['price']; ?>', '<?php echo htmlspecialchars($product['description']); ?>', '<?php echo $product['category_id']; ?>', '<?php echo htmlspecialchars($product['unit_type']); ?>', '<?php echo $product['increment_value']; ?>', '<?php echo htmlspecialchars($product['image'] ?? ''); ?>')"><i class="fas fa-edit"></i> Edit</button>
                                <a href="?page=products&action=delete&id=<?php echo $product['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="9" class="no-products">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($showSuccess): ?>
                document.getElementById('editForm').style.display = 'none';
            <?php endif; ?>
        });

        function editProduct(id, name, price, description, category_id, unit_type, increment_value, image) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editPrice').value = price;
            document.getElementById('editDescription').value = description;
            document.getElementById('editCategoryId').value = category_id;
            document.getElementById('editUnitType').value = unit_type;
            document.getElementById('editIncrementValue').value = increment_value;
            const preview = document.getElementById('editImagePreview');
            if (image && file_exists("../images/" + image)) {
                preview.src = "../images/" + image;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
            document.getElementById('editForm').style.display = 'block';
        }

        function cancelEdit() {
            document.getElementById('editForm').style.display = 'none';
            document.getElementById('editProductForm').reset();
            document.getElementById('editImagePreview').style.display = 'none';
        }

        function file_exists(url) {
            var http = new XMLHttpRequest();
            http.open('HEAD', url, false);
            http.send();
            return http.status !== 404;
        }
    </script>
</body>
</html>