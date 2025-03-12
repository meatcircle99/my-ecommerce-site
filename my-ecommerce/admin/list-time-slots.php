<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

include '../config/db.php';

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM time_slots WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success = "Time slot deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error deleting time slot: " . $e->getMessage();
    }
}

// Determine sorting
$sort = isset($_GET['sort']) && $_GET['sort'] === 'time_range' ? 'time_range' : 'created_at';
$order = ($sort === 'time_range') ? 'ASC' : 'DESC';
$stmt = $conn->query("SELECT * FROM time_slots ORDER BY $sort $order");
$time_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>List Time Slots</h2>
<?php if (isset($success)): ?>
    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<div class="list-container">
    <div style="margin-bottom: 10px;">
        <label>Sort by:</label>
        <a href="?page=time-slots&subpage=list-time-slots&sort=created_at" class="action-btn <?php echo $sort === 'created_at' ? 'active' : ''; ?>">Created At</a>
        <a href="?page=time-slots&subpage=list-time-slots&sort=time_range" class="action-btn <?php echo $sort === 'time_range' ? 'active' : ''; ?>">Time Range</a>
    </div>
    <?php if (empty($time_slots)): ?>
        <p>No time slots available.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Time Range</th>
                    <th>Delivery Type</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($time_slots as $slot): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($slot['id']); ?></td>
                        <td><?php echo htmlspecialchars($slot['time_range']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $slot['delivery_type'])); ?></td>
                        <td><?php echo $slot['is_active'] ? 'Active' : 'Inactive'; ?></td>
                        <td><?php echo htmlspecialchars($slot['created_at']); ?></td>
                        <td>
                            <a href="?page=time-slots&subpage=edit-time-slot&id=<?php echo $slot['id']; ?>" class="action-btn"><i class="fas fa-edit"></i> Edit</a>
                            <a href="?page=time-slots&subpage=list-time-slots&delete_id=<?php echo $slot['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this time slot?');"><i class="fas fa-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>