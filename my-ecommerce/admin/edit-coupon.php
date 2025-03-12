<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$success = '';
$error = '';
$selectedCoupon = null;

$coupons = $conn->query("SELECT id, code FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['id']) && $_GET['id']) {
    $coupon_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, code, discount, expiry_date FROM coupons WHERE id = ?");
    $stmt->execute([$coupon_id]);
    $selectedCoupon = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['id'];
    $code = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_STRING);
    $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT);
    $expiry_date = filter_input(INPUT_POST, 'expiry_date', FILTER_SANITIZE_STRING);

    if (!$code || !$discount || !$expiry_date) {
        $error = "All fields are required.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE coupons SET code=?, discount=?, expiry_date=? WHERE id=?");
            $stmt->execute([$code, $discount, $expiry_date, $id]);
            $success = "Coupon updated successfully!";
            $stmt = $conn->prepare("SELECT id, code, discount, expiry_date FROM coupons WHERE id = ?");
            $stmt->execute([$id]);
            $selectedCoupon = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Edit Coupon - Meatcircle Admin</title>
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
        <h1 class="products-title"><i class="fas fa-ticket-alt"></i> Edit Coupon</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <div class="card edit-card">
                <h3 class="card-title">Select Coupon to Edit</h3>
                <form method="GET" class="category-form">
                    <div class="form-group">
                        <label for="id">Coupon:</label>
                        <select name="id" id="id" class="input-field" onchange="this.form.submit()">
                            <option value="">Select a coupon</option>
                            <?php foreach ($coupons as $coupon): ?>
                                <option value="<?php echo $coupon['id']; ?>" <?php echo $selectedCoupon && $selectedCoupon['id'] == $coupon['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($coupon['code']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($selectedCoupon): ?>
                    <h3 class="card-title">Edit Coupon: <?php echo htmlspecialchars($selectedCoupon['code']); ?></h3>
                    <form method="POST" class="category-form">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $selectedCoupon['id']; ?>">
                        <div class="form-group">
                            <label for="editCode">Coupon Code:</label>
                            <input type="text" name="code" id="editCode" value="<?php echo htmlspecialchars($selectedCoupon['code']); ?>" class="input-field" required>
                        </div>
                        <div class="form-group">
                            <label for="editDiscount">Discount (%):</label>
                            <input type="number" name="discount" id="editDiscount" step="0.01" min="0" max="100" value="<?php echo $selectedCoupon['discount']; ?>" class="input-field" required>
                        </div>
                        <div class="form-group">
                            <label for="editExpiryDate">Expiry Date:</label>
                            <input type="date" name="expiry_date" id="editExpiryDate" value="<?php echo $selectedCoupon['expiry_date']; ?>" class="input-field" required>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary">Update Coupon</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='?page=coupons&subpage=edit-coupon'">Cancel</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>