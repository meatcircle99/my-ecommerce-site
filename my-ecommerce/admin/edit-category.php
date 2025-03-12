<?php
ini_set('display_errors', 1); // Enable error reporting
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); // Start output buffering

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    ob_end_clean();
    exit;
}

include '../config/db.php';

$success = '';
$error = '';
$name = '';
$image = '';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php?page=categories&subpage=list-categories');
    ob_end_clean();
    exit;
}

$category_id = (int)$_GET['id'];

// Fetch the category
try {
    $stmt = $conn->prepare("SELECT name, image FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $error = "Category not found.";
    } else {
        $name = $category['name'];
        $image = $category['image'];
    }
} catch (PDOException $e) {
    $error = "Error fetching category: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        try {
            // Handle image upload if a new image is provided
            $new_image = $image; // Keep the old image by default
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['image']['tmp_name'];
                $file_name = $_FILES['image']['name'];
                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION); // Get the extension (no validation)

                // Generate a unique filename with the original extension
                $new_file_name = uniqid('category_', true) . ($file_ext ? '.' . $file_ext : '');
                $upload_path = '../images/' . $new_file_name;

                // Debugging output
                error_log("Uploaded file: $file_name, Extension: $file_ext, Error: " . $_FILES['image']['error']);

                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Delete the old image if it exists
                    if (!empty($image) && file_exists("../images/" . $image)) {
                        unlink("../images/" . $image);
                    }
                    $new_image = $new_file_name;
                } else {
                    $error = "Failed to upload image. Check directory permissions.";
                }
            }

            if (empty($error)) {
                $stmt = $conn->prepare("UPDATE categories SET name = ?, image = ? WHERE id = ?");
                $stmt->execute([$name, $new_image, $category_id]);
                $success = "Category updated successfully!";
                $image = $new_image; // Update the displayed image
            }
        } catch (PDOException $e) {
            $error = "Error updating category: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - Meatcircle Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .edit-category-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .edit-category-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .edit-category-title i {
            color: #4f46e5;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }
        .edit-category-title i:hover {
            color: #7c3aed;
        }
        .alert {
            padding: 8px;
            margin-bottom: 15px;
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .form-group input[type="text"],
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 1rem;
            color: #1e293b;
            transition: border-color 0.3s ease;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="file"]:focus {
            outline: none;
            border-color: #6366f1;
        }
        .current-image {
            margin-top: 10px;
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
        }
        .no-image {
            color: #78909c;
            font-style: italic;
        }
        .btn-submit {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-submit:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        }
        @media (max-width: 768px) {
            .edit-category-container {
                margin: 20px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="edit-category-container">
        <h1 class="edit-category-title"><i class="fas fa-edit"></i> Edit Category</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Category Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter category name" required>
            </div>
            <div class="form-group">
                <label for="image">Category Image</label>
                <?php if (!empty($image) && file_exists("../images/" . $image)): ?>
                    <div>
                        <p>Current Image:</p>
                        <img src="../images/<?php echo htmlspecialchars($image); ?>" alt="Current Category Image" class="current-image">
                    </div>
                <?php else: ?>
                    <p class="no-image">No image uploaded</p>
                <?php endif; ?>
                <input type="file" id="image" name="image">
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Category</button>
        </form>
    </div>
    <?php ob_end_flush(); // Flush the output buffer ?>
</body>
</html>