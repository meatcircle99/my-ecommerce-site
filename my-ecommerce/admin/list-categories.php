<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$success = '';
$error = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        // Fetch the category to get the image path
        $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($category && !empty($category['image'])) {
            // Delete the image file if it exists
            $image_path = "../images/" . $category['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        // Delete the category from the database
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Category deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting category: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Categories - Meatcircle Admin</title>
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
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .categories-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .categories-title i {
            color: #4f46e5;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }
        .categories-title i:hover {
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
        .categories-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .categories-table th, .categories-table td {
            padding: 8px;
            text-align: left;
            font-size: 0.85rem;
            border-bottom: 1px solid #eee;
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
            max-width: 60px;
            max-height: 45px;
            object-fit: contain;
            border-radius: 4px;
        }
        .no-image {
            color: #78909c;
            font-style: italic;
        }
        .no-categories {
            text-align: center;
            padding: 20px;
            color: #78909c;
            font-size: 1rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        @media (max-width: 768px) {
            .categories-table th, .categories-table td {
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
    <div class="categories-container">
        <h1 class="categories-title"><i class="fas fa-list-alt"></i> List Categories</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Categories Table -->
        <div class="card table-card">
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, name, image FROM categories ORDER BY id DESC");
                        $stmt->execute();
                        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($categories) > 0) {
                            foreach ($categories as $category) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($category['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($category['name']) . "</td>";
                                echo "<td>";
                                if (!empty($category['image']) && file_exists("../images/" . $category['image'])) {
                                    echo "<img src='../images/" . htmlspecialchars($category['image']) . "' alt='Category Image' class='table-image'>";
                                } else {
                                    echo "<span class='no-image'>No image</span>";
                                }
                                echo "</td>";
                                echo "<td>";
                                echo "<a href='?page=categories&subpage=edit-category&id=" . htmlspecialchars($category['id']) . "' class='btn btn-edit'><i class='fas fa-edit'></i> Edit</a>";
                                echo "<a href='?page=categories&subpage=list-categories&action=delete&id=" . htmlspecialchars($category['id']) . "' class='btn btn-delete' onclick='return confirm(\"Are you sure you want to delete this category?\")'><i class='fas fa-trash'></i> Delete</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='no-categories'>No categories found.</td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='4' class='no-categories'>Error fetching categories: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>