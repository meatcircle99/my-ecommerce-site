<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$time_slot_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$time_slot = null;

if ($time_slot_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM time_slots WHERE id = ?");
    $stmt->execute([$time_slot_id]);
    $time_slot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$time_slot) {
        $error = "Time slot not found.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $time_slot) {
    $time_range = trim($_POST['time_range']);
    $delivery_type = $_POST['delivery_type'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($time_range)) {
        $error = "Time range is required.";
    } elseif (!preg_match('/^(\d{1,2}(?::\d{2})?(?:AM|PM))\s*-\s*(\d{1,2}(?::\d{2})?(?:AM|PM))$/i', $time_range)) {
        $error = "Invalid time range format. Use e.g., '7AM-10AM' or '7:00AM-10:00AM'.";
    } elseif (!in_array($delivery_type, ['same_day', 'next_day'])) {
        $error = "Invalid delivery type.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE time_slots SET time_range = ?, delivery_type = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$time_range, $delivery_type, $is_active, $time_slot_id]);
            $success = "Time slot updated successfully.";
            $time_slot['time_range'] = $time_range;
            $time_slot['delivery_type'] = $delivery_type;
            $time_slot['is_active'] = $is_active;
        } catch (PDOException $e) {
            $error = "Error updating time slot: " . $e->getMessage();
        }
    }
}
?>

<h2>Edit Time Slot</h2>
<?php if (isset($success)): ?>
    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($time_slot): ?>
    <div class="form-container">
        <form method="POST">
            <div class="form-group">
                <label for="time_range">Time Range (e.g., 7AM-10AM or 7:00AM-10:00AM):</label>
                <input type="text" id="time_range" name="time_range" value="<?php echo htmlspecialchars($time_slot['time_range']); ?>" required>
            </div>
            <div class="form-group">
                <label for="delivery_type">Delivery Type:</label>
                <select id="delivery_type" name="delivery_type" required>
                    <option value="same_day" <?php echo $time_slot['delivery_type'] === 'same_day' ? 'selected' : ''; ?>>Same-Day Delivery</option>
                    <option value="next_day" <?php echo $time_slot['delivery_type'] === 'next_day' ? 'selected' : ''; ?>>Next-Day Delivery</option>
                </select>
            </div>
            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" <?php echo $time_slot['is_active'] ? 'checked' : ''; ?>> Active
                </label>
            </div>
            <button type="submit" class="btn-submit">Update Time Slot</button>
        </form>
    </div>
<?php else: ?>
    <p>Please select a time slot to edit.</p>
    <a href="?page=time-slots&subpage=list-time-slots" class="action-btn">Go to Time Slots List</a>
<?php endif; ?>