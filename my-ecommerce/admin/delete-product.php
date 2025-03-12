<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
include '../config/db.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    header("Location: dashboard.php?msg=Product+deleted+successfully");
    exit;
} catch (PDOException $e) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Error</title>
      <!-- Bootstrap CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
      <div class="container mt-5">
        <div class="alert alert-danger">Error deleting product: <?php echo htmlspecialchars($e->getMessage()); ?></div>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}
?>
