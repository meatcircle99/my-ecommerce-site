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
        $code = trim($_POST['code']);
        $discount = (int)$_POST['discount'];
        $expiry_date = $_POST['expiry_date'];

        try {
            $stmt = $conn->prepare("SELECT id FROM coupons WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $error = "Coupon code '$code' already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO coupons (code, discount, expiry_date) VALUES (?, ?, ?)");
                $stmt->execute([$code, $discount, $expiry_date]);
                $success = "Coupon added successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error adding coupon: " . $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $code = trim($_POST['code']);
        $discount = (int)$_POST['discount'];
        $expiry_date = $_POST['expiry_date'];

        try {
            $stmt = $conn->prepare("UPDATE coupons SET code = ?, discount = ?, expiry_date = ? WHERE id = ?");
            $stmt->execute([$code, $discount, $expiry_date, $id]);
            $success = "Coupon updated successfully!";
            $showSuccess = true;
        } catch (PDOException $e) {
            $error = "Error updating coupon: " . $e->getMessage();
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Coupon deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting coupon: " . $e->getMessage();
    }
}

// Fetch coupons
try {
    $stmt = $conn->query("SELECT * FROM coupons ORDER BY id DESC");
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching coupons: " . $e->getMessage();
    $coupons = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Coupons - Meatcircle Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .coupons-container {
            width: 100%;
            margin: 40px 0;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .coupons-title {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: default;
        }
        .coupons-title i {
            color: #4f46e5;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }
        .coupons-title i:hover {
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
        .coupons-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .coupons-table th, .coupons-table td {
            padding: 15px;
            text-align: left;
            font-size: 0.95rem;
        }
        .coupons-table th {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .coupons-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .coupons-table tr:hover {
            background: #eef2ff;
            transition: background 0.2s ease;
        }
        .coupons-table td {
            color: #475569;
        }
        .no-coupons {
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
            .coupons-table th, .coupons-table td {
                padding: 10px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="coupons-container">
        <h1 class="coupons-title"><i class="fas fa-tags"></i> Manage Coupons</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Two-Column Layout -->
        <div class="form-container">
            <div class="card add-card">
                <h3 class="card-title">Add New Coupon</h3>
                <form method="POST" enctype="multipart/form-data" class="category-form">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="code">Coupon Code:</label>
                        <input type="text" name="code" id="code" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="discount">Discount (%):</label>
                        <input type="number" name="discount" id="discount" class="input-field" min="1" max="100" required>
                    </div>
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date:</label>
                        <input type="date" name="expiry_date" id="expiry_date" class="input-field" required>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Add Coupon</button>
                </form>
            </div>

            <div id="editForm" style="display: none;" class="card edit-card">
                <h3 class="card-title">Edit Coupon</h3>
                <form method="POST" enctype="multipart/form-data" id="editCouponForm" class="category-form">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    <div class="form-group">
                        <label for="editCode">Coupon Code:</label>
                        <input type="text" name="code" id="editCode" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="editDiscount">Discount (%):</label>
                        <input type="number" name="discount" id="editDiscount" class="input-field" min="1" max="100" required>
                    </div>
                    <div class="form-group">
                        <label for="editExpiryDate">Expiry Date:</label>
                        <input type="date" name="expiry_date" id="editExpiryDate" class="input-field" required>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Update Coupon</button>
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Coupons Table -->
        <div class="card table-card">
            <table class="coupons-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Expiry Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($coupon['id']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['code']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['discount']); ?>%</td>
                            <td><?php echo htmlspecialchars($coupon['expiry_date']); ?></td>
                            <td>
                                <button class="btn btn-edit" onclick="editCoupon(<?php echo $coupon['id']; ?>, '<?php echo htmlspecialchars($coupon['code']); ?>', '<?php echo htmlspecialchars($coupon['discount']); ?>', '<?php echo htmlspecialchars($coupon['expiry_date']); ?>')"><i class="fas fa-edit"></i> Edit</button>
                                <a href="?page=coupons&action=delete&id=<?php echo $coupon['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($coupons)): ?>
                        <tr><td colspan="5" class="no-coupons">No coupons found.</td></tr>
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

        function editCoupon(id, code, discount, expiry_date) {
            document.getElementById('editId').value = id;
            document.getElementById('editCode').value = code;
            document.getElementById('editDiscount').value = discount;
            document.getElementById('editExpiryDate').value = expiry_date;
            document.getElementById('editForm').style.display = 'block';
        }

        function cancelEdit() {
            document.getElementById('editForm').style.display = 'none';
            document.getElementById('editCouponForm').reset();
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