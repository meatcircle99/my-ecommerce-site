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
        $stmt = $conn->prepare("DELETE FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Banner deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting banner: " . $e->getMessage();
    }
}

// Use specific columns to avoid ambiguity
$banners = $conn->query("SELECT id, title, image, link, status FROM banners ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Banners - Meatcircle Admin</title>
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
        .no-banners {
            text-align: center;
            padding: 20px;
            color: #78909c;
            font-size: 1rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
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
        <h1 class="products-title"><i class="fas fa-image"></i> List Banners</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card table-card">
            <table class="products-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>TITLE</th>
                        <th>IMAGE</th>
                        <th>LINK</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $banner): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($banner['id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($banner['title'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (isset($banner['image']) && !empty($banner['image']) && file_exists("../images/" . $banner['image'])): ?>
                                    <img src="../images/<?php echo htmlspecialchars($banner['image']); ?>" alt="Banner" class="table-image">
                                <?php else: ?>
                                    <span>No image</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($banner['link'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($banner['status'] ?? 'N/A'); ?></td>
                            <td>
                                <a href="?page=banners&subpage=edit-banner&id=<?php echo $banner['id']; ?>" class="btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                <a href="?page=banners&subpage=list-banners&action=delete&id=<?php echo $banner['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($banners)): ?>
                        <tr><td colspan="6" class="no-banners">No banners found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>