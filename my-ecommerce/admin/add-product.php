<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = htmlspecialchars(strip_tags(filter_input(INPUT_POST, 'name', FILTER_DEFAULT)), ENT_QUOTES, 'UTF-8') ?? '';
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT) ?? 0.0;
    $description = htmlspecialchars(strip_tags(filter_input(INPUT_POST, 'description', FILTER_DEFAULT)), ENT_QUOTES, 'UTF-8') ?? '';
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?? 0;
    $unit_type = htmlspecialchars(strip_tags(filter_input(INPUT_POST, 'unit_type', FILTER_DEFAULT)), ENT_QUOTES, 'UTF-8') ?? '';
    $increment_value = filter_input(INPUT_POST, 'increment_value', FILTER_VALIDATE_FLOAT) ?? 0.0;
    $stock_quantity = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT) ?? 0;
    $image = $_FILES['image']['name'] ?? '';

    if (!$name || !$price || !$description || !$category_id || !$unit_type || !$increment_value || $stock_quantity === false || !$image) {
        $error = "All fields are required.";
    } else {
        $target_dir = "../images/";
        $target_file = $target_dir . basename($image);
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            try {
                $stmt = $conn->prepare("INSERT INTO products (category_id, name, price, image, description, unit_type, increment_value, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $name, $price, $image, $description, $unit_type, $increment_value, $stock_quantity]);
                $success = "Product added successfully!";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "Failed to upload image.";
        }
    }
}

$categories = $conn->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Meatcircle Admin</title>
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
        <h1 class="products-title"><i class="fas fa-box"></i> Add Product</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Product Form -->
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
                        <textarea name="description" id="description" rows="3" class="input-field" required></textarea>
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
                        <label for="stock_quantity">Stock Quantity:</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" min="0" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="image">Product Image:</label>
                        <input type="file" name="image" id="image" class="input-file" accept="image/*" required>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Add Product</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
