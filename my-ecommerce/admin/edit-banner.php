<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$success = '';
$error = '';
$selectedBanner = null;

// Select only the existing columns
$banners = $conn->query("SELECT id, image FROM banners ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['id']) && $_GET['id']) {
    $banner_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, image, created_at FROM banners WHERE id = ?");
    $stmt->execute([$banner_id]);
    $selectedBanner = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['id'];
    $image = $_FILES['image']['name'] ?? '';

    if (!$id) {
        $error = "Invalid banner ID.";
    } else {
        try {
            if ($image) {
                $target_dir = "../images/";
                $target_file = $target_dir . basename($image);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $stmt = $conn->prepare("UPDATE banners SET image = ? WHERE id = ?");
                    $stmt->execute([$image, $id]);
                } else {
                    $error = "Failed to upload image.";
                }
            }

            if (!$error) {
                $success = "Banner updated successfully!";
                $stmt = $conn->prepare("SELECT id, image, created_at FROM banners WHERE id = ?");
                $stmt->execute([$id]);
                $selectedBanner = $stmt->fetch(PDO::FETCH_ASSOC);
            }
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
    <title>Edit Banner - Meatcircle Admin</title>
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
        <h1 class="products-title"><i class="fas fa-image"></i> Edit Banner</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <div class="card edit-card">
                <h3 class="card-title">Select Banner to Edit</h3>
                <form method="GET" class="category-form">
                    <div class="form-group">
                        <label for="id">Banner:</label>
                        <select name="id" id="id" class="input-field" onchange="this.form.submit()">
                            <option value="">Select a banner</option>
                            <?php foreach ($banners as $banner): ?>
                                <option value="<?php echo $banner['id']; ?>" <?php echo $selectedBanner && $selectedBanner['id'] == $banner['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($banner['id']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($selectedBanner): ?>
                    <h3 class="card-title">Edit Banner: ID <?php echo htmlspecialchars($selectedBanner['id']); ?></h3>
                    <form method="POST" enctype="multipart/form-data" class="category-form">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $selectedBanner['id']; ?>">
                        <div class="form-group">
                            <label for="editImage">Banner Image (Leave blank to keep current):</label>
                            <input type="file" name="image" id="editImage" class="input-file" accept="image/*">
                            <?php if (!empty($selectedBanner['image']) && file_exists("../images/" . $selectedBanner['image'])): ?>
                                <img src="../images/<?php echo htmlspecialchars($selectedBanner['image']); ?>" alt="Current Image" class="edit-image-preview">
                            <?php endif; ?>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary">Update Banner</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='?page=banners&subpage=edit-banner'">Cancel</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>