<?php
session_start();
include '../config/db.php'; // Path from pages/ to root config/

if (!isset($_SESSION['user_id'])) {
    echo '<p class="no-addresses">Please log in to manage your addresses.</p>';
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Add index if not exists
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_addresses_user_default ON addresses(user_id, is_default)");

    // Server-side caching (file-based)
    $cacheFile = '../cache/addresses_' . $user_id . '.html';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) { // Cache for 1 hour
        echo file_get_contents($cacheFile);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, name, phone_number, address, landmark, pincode, locality, alternate_number, city, is_default, created_at 
                            FROM addresses 
                            WHERE user_id = ? 
                            ORDER BY is_default DESC, created_at DESC 
                            LIMIT 10");
    $stmt->execute([$user_id]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    if (empty($addresses)) {
        $html = '<p class="no-addresses">No addresses added yet. Add a new address below.</p>';
    } else {
        foreach ($addresses as $address) {
            $html .= "<div class='address-card'>
                        <p><strong>Name:</strong> " . htmlspecialchars($address['name']) . "</p>
                        <p><strong>Phone:</strong> " . htmlspecialchars($address['phone_number']) . "</p>
                        <p><strong>Address:</strong> " . htmlspecialchars($address['address'] . ", " . $address['locality'] . ", " . $address['city'] . ", " . $address['pincode']) . "</p>";
            if ($address['landmark']) $html .= "<p><strong>Landmark:</strong> " . htmlspecialchars($address['landmark']) . "</p>";
            if ($address['alternate_number']) $html .= "<p><strong>Alternate Phone:</strong> " . htmlspecialchars($address['alternate_number']) . "</p>";
            $html .= "<p><strong>Default:</strong> " . ($address['is_default'] ? '<span style=\"color: #2ecc71;\">Yes</span>' : 'No') . "</p>
                        <div class='address-actions'>
                            <a href='edit-address.php?id=" . $address['id'] . "' class='btn-action'><i class='fas fa-edit'></i> Edit</a>
                            <a href='delete-address.php?id=" . $address['id'] . "' class='btn-action btn-delete' onclick='return confirm(\"Are you sure you want to delete this address?\");'><i class='fas fa-trash'></i> Delete</a>";
            if (!$address['is_default']) {
                $html .= " <a href='set-default-address.php?id=" . $address['id'] . "' class='btn-action'><i class='fas fa-star'></i> Set as Default</a>";
            }
            $html .= "</div>
                  </div>";
        }
    }
    // Save to cache
    if (!is_dir('../cache')) mkdir('../cache', 0777, true);
    file_put_contents($cacheFile, $html);
    echo $html;
} catch (PDOException $e) {
    error_log("Error fetching addresses in get-addresses.php: " . $e->getMessage());
    echo '<p class="no-addresses">Error loading addresses. Please try again later.</p>';
}