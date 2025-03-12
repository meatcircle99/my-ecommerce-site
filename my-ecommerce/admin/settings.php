<?php
// settings.php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

try {
    include '../config/db.php';

    // Fetch current maintenance mode setting
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance_mode = $stmt->fetchColumn() === '1';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_mode = isset($_POST['maintenance_mode']) && $_POST['maintenance_mode'] === '1' ? '1' : '0';
        try {
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
            $stmt->execute([$new_mode]);
            $success_message = "Maintenance mode updated successfully!";
            $maintenance_mode = ($new_mode === '1');
        } catch (PDOException $e) {
            error_log("Error updating maintenance mode: " . $e->getMessage());
            $error_message = "Error updating maintenance mode.";
        }
    }
} catch (PDOException $e) {
    error_log("Database error in settings.php: " . $e->getMessage());
    $error_message = "Database connection error.";
}
?>

<h2>Maintenance Mode Settings</h2>
<?php if (isset($success_message)): ?>
    <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if (isset($error_message)): ?>
    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <div class="form-group">
            <label>
                <input type="checkbox" name="maintenance_mode" value="1" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                Enable Maintenance Mode (Disables cart and orders site-wide)
            </label>
        </div>
        <button type="submit" class="btn-submit">Save Changes</button>
    </form>
    <p><strong>Note:</strong> When enabled, all products will appear as out of stock, and users cannot add items to the cart or place orders.</p>
</div>