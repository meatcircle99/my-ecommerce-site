<?php
// Check if session is not already started before starting it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if required fields are set and not empty
    if (isset($_POST['time_range'], $_POST['delivery_type']) && !empty(trim($_POST['time_range'])) && !empty(trim($_POST['delivery_type']))) {
        $time_range = trim($_POST['time_range']);
        $delivery_type = trim($_POST['delivery_type']);

        // Validate time range format (e.g., "7AM-10AM" or "7:00AM-10:00AM")
        if (!preg_match('/^(\d{1,2}(?::\d{2})?(?:AM|PM))\s*-\s*(\d{1,2}(?::\d{2})?(?:AM|PM))$/i', $time_range)) {
            $message = '<div class="error-message">Invalid time range format. Use e.g., "7AM-10AM" or "7:00AM-10:00AM".</div>';
        } elseif (in_array($delivery_type, ['same_day', 'next_day'])) {
            try {
                // Insert the new time slot (active by default)
                $stmt = $conn->prepare("INSERT INTO time_slots (time_range, delivery_type, is_active, created_at) VALUES (:time_range, :delivery_type, TRUE, NOW())");
                $stmt->execute([
                    ':time_range' => $time_range,
                    ':delivery_type' => $delivery_type
                ]);
                // If the added slot is for same day delivery, update the setting so that it appears in address.php
                if ($delivery_type === 'same_day') {
                    $stmt = $conn->prepare("UPDATE settings SET setting_value = '1' WHERE setting_key = 'same_day_delivery_enabled'");
                    $stmt->execute();
                }
                $message = '<div class="success-message">Time slot added successfully!</div>';
            } catch (PDOException $e) {
                error_log("Error adding time slot: " . $e->getMessage());
                $message = '<div class="error-message">Error adding time slot: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="error-message">Invalid delivery type selected.</div>';
        }
    } else {
        $message = '<div class="error-message">Please fill in all required fields (Time Range and Delivery Type).</div>';
    }
}
?>

<h2>Add Time Slots</h2>
<?php if (!empty($message)) echo $message; ?>
<div class="form-container">
    <form method="POST" action="">
        <div class="form-group">
            <label for="time_range">Add Time Slots (e.g., 7AM-10AM or 7:00AM-10:00AM):</label>
            <input type="text" id="time_range" name="time_range" required value="<?php echo isset($_POST['time_range']) ? htmlspecialchars($_POST['time_range']) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="delivery_type">Delivery Type:</label>
            <select id="delivery_type" name="delivery_type" required>
                <option value="" disabled <?php echo !isset($_POST['delivery_type']) ? 'selected' : ''; ?>>Select Delivery Type</option>
                <option value="same_day" <?php echo isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'same_day' ? 'selected' : ''; ?>>Same Day Delivery</option>
                <option value="next_day" <?php echo isset($_POST['delivery_type']) && $_POST['delivery_type'] === 'next_day' ? 'selected' : ''; ?>>Next Day Delivery</option>
            </select>
        </div>
        <button type="submit" class="btn-submit">Add Time Slot</button>
    </form>
</div>
