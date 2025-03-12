<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $link = filter_input(INPUT_POST, 'link', FILTER_SANITIZE_STRING) ?? ''; // Optional link
    $image = $_FILES['image']['name'] ?? '';

    if (!$image) {
        $error = "Banner image is required.";
    } else {
        $target_dir = "../images/";
        $target_file = $target_dir . basename($image);
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            try {
                $stmt = $conn->prepare("INSERT INTO banners (image, link) VALUES (?, ?)");
                $stmt->execute([$image, $link]);
                $success = "Banner added successfully!";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "Failed to upload image.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Banner - Meatcircle Admin</title>
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
        }
    </style>
</head>
<body>
    <div class="products-container">
        <h1 class="products-title"><i class="fas fa-image"></i> Add Banner</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <div class="card add-card">
                <h3 class="card-title">Add New Banner</h3>
                <form method="POST" enctype="multipart/form-data" class="category-form">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="image">Banner Image:</label>
                        <input type="file" name="image" id="image" class="input-file" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label for="link">Link (Optional):</label>
                        <input type="url" name="link" id="link" class="input-field" placeholder="Enter link (optional)">
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Add Banner</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>