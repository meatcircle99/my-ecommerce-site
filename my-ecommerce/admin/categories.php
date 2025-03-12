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
        $name = trim($_POST['name']);
        $image = $_FILES['image']['name'];
        $target_dir = "../images/";
        $target_file = $target_dir . basename($image);

        try {
            $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                $error = "Category '$name' already exists.";
            } else {
                if (!empty($image) && !move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $error = "Error uploading image.";
                }
                if (empty($error)) {
                    $stmt = $conn->prepare("INSERT INTO categories (name, image) VALUES (?, ?)");
                    $stmt->execute([$name, $image]);
                    $success = "Category added successfully!";
                }
            }
        } catch (PDOException $e) {
            $error = "Error adding category: " . $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $image = $_FILES['image']['name'];
        $target_dir = "../images/";
        $target_file = $target_dir . basename($image);

        try {
            if ($image && !move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $error = "Error uploading image.";
            }
            if (empty($error)) {
                if ($image) {
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, image = ? WHERE id = ?");
                    $stmt->execute([$name, $image, $id]);
                } else {
                    $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
                    $stmt->execute([$name, $id]);
                }
                $success = "Category updated successfully!";
                $showSuccess = true;
            }
        } catch (PDOException $e) {
            $error = "Error updating category: " . $e->getMessage();
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Category deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting category: " . $e->getMessage();
    }
}

// Fetch categories
try {
    $stmt = $conn->query("SELECT * FROM categories ORDER BY id DESC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching categories: " . $e->getMessage();
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Meatcircle Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .categories-container {
            width: 100%;
            margin: 40px 0;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .categories-title {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .categories-title i {
            color: #4f46e5;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }
        .categories-title i:hover {
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
        .categories-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .categories-table th, .categories-table td {
            padding: 15px;
            text-align: left;
            font-size: 0.95rem;
        }
        .categories-table th {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .categories-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .categories-table tr:hover {
            background: #eef2ff;
            transition: background 0.2s ease;
        }
        .categories-table td {
            color: #475569;
        }
        .table-image {
            max-width: 80px; /* Same as banners.php */
            max-height: 60px;
            object-fit: contain;
            border-radius: 4px;
        }
        .no-image {
            color: #78909c;
            font-style: italic;
        }
        .no-categories {
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
            .categories-table th, .categories-table td {
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
    <div class="categories-container">
        <h1 class="categories-title"><i class="fas fa-folder"></i> Manage Categories</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Two-Column Layout -->
        <div class="form-container">
            <div class="card add-card">
                <h3 class="card-title">Add New Category</h3>
                <form method="POST" enctype="multipart/form-data" class="category-form">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="name">Category Name:</label>
                        <input type="text" name="name" id="name" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="image">Category Image:</label>
                        <input type="file" name="image" id="image" class="input-file" accept="image/*" required>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Add Category</button>
                </form>
            </div>

            <div id="editForm" style="display: none;" class="card edit-card">
                <h3 class="card-title">Edit Category</h3>
                <form method="POST" enctype="multipart/form-data" id="editCategoryForm" class="category-form">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    <div class="form-group">
                        <label for="editName">Category Name:</label>
                        <input type="text" name="name" id="editName" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="editImage">Category Image (leave blank to keep current):</label>
                        <input type="file" name="image" id="editImage" class="input-file" accept="image/*">
                        <img id="editImagePreview" src="" alt="Current Image" class="edit-image-preview">
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Update Category</button>
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="card table-card">
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['id']); ?></td>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td>
                                <?php if (!empty($category['image']) && file_exists("../images/" . $category['image'])): ?>
                                    <img src="../images/<?php echo htmlspecialchars($category['image']); ?>" alt="Category" class="table-image">
                                <?php else: ?>
                                    <span class="no-image">No image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-edit" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['image']); ?>')"><i class="fas fa-edit"></i> Edit</button>
                                <a href="?page=categories&action=delete&id=<?php echo $category['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="4" class="no-categories">No categories found.</td></tr>
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

        function editCategory(id, name, image) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
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
            document.getElementById('editCategoryForm').reset();
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