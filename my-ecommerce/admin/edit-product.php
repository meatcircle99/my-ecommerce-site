<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$success = '';
$error = '';
$selectedProduct = null;

$products = $conn->query("SELECT p.id, p.name FROM products p ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $conn->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['id']) && $_GET['id']) {
    $product_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $selectedProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['id'];
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $unit_type = filter_input(INPUT_POST, 'unit_type', FILTER_SANITIZE_STRING);
    $increment_value = filter_input(INPUT_POST, 'increment_value', FILTER_VALIDATE_FLOAT);
    $stock_quantity = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT); // New field
    $image = $_FILES['image']['name'] ?? '';

    if (!$name || !$price || !$description || !$category_id || !$unit_type || !$increment_value || $stock_quantity === false) {
        $error = "All fields are required.";
    } else {
        try {
            if ($image) {
                $target_dir = "../images/";
                $target_file = $target_dir . basename($image);
                move_uploaded_file($_FILES['image']['tmp_name'], $target_file);
                $stmt = $conn->prepare("UPDATE products SET name=?, price=?, description=?, category_id=?, image=?, unit_type=?, increment_value=?, stock_quantity=? WHERE id=?");
                $stmt->execute([$name, $price, $description, $category_id, $image, $unit_type, $increment_value, $stock_quantity, $id]);
            } else {
                $stmt = $conn->prepare("UPDATE products SET name=?, price=?, description=?, category_id=?, unit_type=?, increment_value=?, stock_quantity=? WHERE id=?");
                $stmt->execute([$name, $price, $description, $category_id, $unit_type, $increment_value, $stock_quantity, $id]);
            }
            $success = "Product updated successfully!";
            // Refresh the product list and selected product
            $products = $conn->query("SELECT p.id, p.name FROM products p ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $selectedProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Meatcircle Admin</title>
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
            padding: 15px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .products-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        .form-container {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding: 0 10px;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            width: 100%;
        }
        .card-title {
            color: #1e293b;
            font-size: 1.25rem;
            margin-bottom: 10px;
        }
        .category-form {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .form-group label {
            font-size: 0.9rem;
        }
        .input-field {
            padding: 8px;
            border: none;
            border-radius: 20px;
            background: #f8fafc;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
            color: #1e293b;
            transition: box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .input-field:focus {
            outline: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            border: 1px solid #6366f1;
        }
        .input-file {
            padding: 8px 0;
            font-size: 0.9rem;
        }
        .edit-image-preview {
            max-width: 120px;
            margin-top: 5px;
            border-radius: 4px;
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
        .btn-primary {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            color: white;
        }
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.4);
        }
        .btn-secondary {
            background: #9ca3af;
            color: white;
        }
        .btn-secondary:hover {
            background: #6b7280;
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(107, 114, 128, 0.4);
        }
        @media (max-width: 768px) {
            .form-container {
                flex-direction: column;
            }
            .card {
                width: 100%;
            }
            .input-field {
                padding: 6px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="products-container">
        <h1 class="products-title"><i class="fas fa-box"></i> Edit Product</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Select Product to Edit -->
        <div class="form-container">
            <div class="card edit-card">
                <h3 class="card-title">Select Product to Edit</h3>
                <form method="GET" class="category-form">
                    <div class="form-group">
                        <label for="id">Product:</label>
                        <select name="id" id="id" class="input-field" onchange="this.form.submit()">
                            <option value="">Select a product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" <?php echo $selectedProduct && $selectedProduct['id'] == $product['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($selectedProduct): ?>
                    <h3 class="card-title">Edit Product: <?php echo htmlspecialchars($selectedProduct['name']); ?></h3>
                    <form method="POST" enctype="multipart/form-data" class="category-form">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $selectedProduct['id']; ?>">
                        <div class="form-group">
                            <label for="editName">Product Name:</label>
                            <input type="text" name="name" id="editName" value="<?php echo htmlspecialchars($selectedProduct['name']); ?>" class="input-field" required>
                        </div>
                        <div class="form-group">
                            <label for="editPrice">Price (INR):</label>
                            <input type="number" name="price" id="editPrice" step="0.01" min="0" value="<?php echo $selectedProduct['price']; ?>" class="input-field" required>
                        </div>
                        <div class="form-group">
                            <label for="editDescription">Description:</label>
                            <textarea name="description" id="editDescription" rows="3" class="input-field" required><?php echo htmlspecialchars($selectedProduct['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="editCategoryId">Category:</label>
                            <select name="category_id" id="editCategoryId" class="input-field" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $selectedProduct['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editUnitType">Unit Type:</label>
                            <select name="unit_type" id="editUnitType" class="input-field" required>
                                <option value="kg" <?php echo $selectedProduct['unit_type'] == 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                                <option value="units" <?php echo $selectedProduct['unit_type'] == 'units' ? 'selected' : ''; ?>>Units</option>
                                <option value="pieces" <?php echo $selectedProduct['unit_type'] == 'pieces' ? 'selected' : ''; ?>>Pieces</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editIncrementValue">Increment Value:</label>
                            <input type="number" name="increment_value" id="editIncrementValue" step="0.01" min="0.01" value="<?php echo $selectedProduct['increment_value']; ?>" class="input-field" required>
                        </div>
                        <div class="form-group">
                            <label for="editStockQuantity">Stock Quantity:</label>
                            <input type="number" name="stock_quantity" id="editStockQuantity" min="0" value="<?php echo htmlspecialchars($selectedProduct['stock_quantity']); ?>" class="input-field" required>
                        </div>
                        <div class="form-group">
                            <label for="editImage">Product Image (Leave blank to keep current):</label>
                            <input type="file" name="image" id="editImage" class="input-file" accept="image/*">
                            <?php if (!empty($selectedProduct['image']) && file_exists("../images/" . $selectedProduct['image'])): ?>
                                <img src="../images/<?php echo htmlspecialchars($selectedProduct['image']); ?>" alt="Current Image" class="edit-image-preview">
                            <?php endif; ?>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary">Update Product</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='?page=products&subpage=edit-product'">Cancel</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>