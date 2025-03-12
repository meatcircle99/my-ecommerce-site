<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $name = trim($_POST['name']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $landmark = trim($_POST['landmark'] ?? '');
    $pincode = trim($_POST['pincode']);
    $locality = trim($_POST['locality']);
    $alternate_number = trim($_POST['alternate_number'] ?? '');
    $city = trim($_POST['city']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    $return_to = isset($_GET['return_to']) ? $_GET['return_to'] : 'account'; // Default to account if not specified

    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_addresses_user_default ON addresses(user_id, is_default)");

        if ($is_default) {
            $stmt = $conn->prepare("UPDATE addresses SET is_default = FALSE WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }

        $stmt = $conn->prepare("INSERT INTO addresses (user_id, name, phone_number, address, landmark, pincode, locality, alternate_number, city, is_default) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE name = VALUES(name), phone_number = VALUES(phone_number), address = VALUES(address), 
                                landmark = VALUES(landmark), pincode = VALUES(pincode), locality = VALUES(locality), 
                                alternate_number = VALUES(alternate_number), city = VALUES(city), is_default = VALUES(is_default)");
        $stmt->execute([$user_id, $name, $phone_number, $address, $landmark, $pincode, $locality, $alternate_number, $city, $is_default]);
        header("Location: $return_to.php");
        exit;
    } catch (PDOException $e) {
        error_log("Error adding address: " . $e->getMessage());
        header("Location: $return_to.php?error=" . urlencode('Error adding address: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Address - Meatcircle</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .address-form { max-width: 600px; margin: 80px auto; padding: 2rem; background-color: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .form-header { color: #2c3e50; font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; color: #2c3e50; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { border-color: #2c3e50; outline: none; }
        .default-checkbox { margin-top: 1.2rem; }
        .default-checkbox label { color: #333; font-size: 0.9rem; }
        .btn-proceed { background-color: #2c3e50; color: white; padding: 0.7rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; transition: background-color 0.3s; }
        .btn-proceed:hover { background-color: #34495e; }
        @media (max-width: 768px) {
            .address-form { margin: 60px 1rem; padding: 1.5rem; }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&libraries=places"></script>
    <script>
        $(document).ready(function() {
            let pincodeCache = {};
            $('#pincode').on('input', async function() {
                const pincode = $(this).val().trim();
                if (pincode.length === 6) {
                    try {
                        if (pincodeCache[pincode]) {
                            $('#city').val(pincodeCache[pincode].city);
                            $('#locality').val(pincodeCache[pincode].locality);
                        } else {
                            const geocoder = new google.maps.Geocoder();
                            geocoder.geocode({ 'address': pincode + ', India' }, (results, status) => {
                                if (status === 'OK') {
                                    let city = '';
                                    let locality = '';
                                    results[0].address_components.forEach(component => {
                                        if (component.types.includes('locality')) locality = component.long_name;
                                        if (component.types.includes('administrative_area_level_2')) city = component.long_name;
                                        if (component.types.includes('administrative_area_level_1') && !city) city = component.long_name;
                                    });
                                    $('#city').val(city || '');
                                    $('#locality').val(locality || '');
                                    pincodeCache[pincode] = { city: city || '', locality: locality || '' };
                                } else {
                                    alert('Error fetching location. Please enter city/locality manually.');
                                    $('#city').val('');
                                    $('#locality').val('');
                                }
                            });
                        }
                    } catch (error) {
                        console.error('Error fetching pincode:', error);
                        alert('Error fetching pincode details. Please enter city/locality manually.');
                        $('#city').val('');
                        $('#locality').val('');
                    }
                }
            });
        });
    </script>
</head>
<body>
    <div class="address-form">
        <h2 class="form-header"><i class="fas fa-plus"></i> Add New Address</h2>
        <form method="POST">
            <div class="form-group">
                <label for="name">Full Name:</label>
                <input type="text" id="name" name="name" required placeholder="Enter your full name">
            </div>
            <div class="form-group">
                <label for="phone_number">Phone Number:</label>
                <input type="tel" id="phone_number" name="phone_number" pattern="[0-9]{10}" required placeholder="Enter 10-digit phone number">
            </div>
            <div class="form-group">
                <label for="address">Address:</label>
                <textarea id="address" name="address" required placeholder="Enter your full address"></textarea>
            </div>
            <div class="form-group">
                <label for="landmark">Landmark (Optional):</label>
                <input type="text" id="landmark" name="landmark" placeholder="e.g., Near Park, Opposite Mall">
            </div>
            <div class="form-group">
                <label for="pincode">Pincode:</label>
                <input type="text" id="pincode" name="pincode" maxlength="6" required placeholder="Enter 6-digit pincode">
            </div>
            <div class="form-group">
                <label for="locality">Locality:</label>
                <input type="text" id="locality" name="locality" required placeholder="e.g., Street Name">
            </div>
            <div class="form-group">
                <label for="alternate_number">Alternate Number (Optional):</label>
                <input type="tel" id="alternate_number" name="alternate_number" pattern="[0-9]{10}" placeholder="Enter 10-digit alternate number">
            </div>
            <div class="form-group">
                <label for="city">City:</label>
                <input type="text" id="city" name="city" required placeholder="e.g., Mumbai">
            </div>
            <div class="form-group default-checkbox">
                <label>
                    <input type="checkbox" name="is_default" value="1"> Set as Default Address
                </label>
            </div>
            <button type="submit" class="btn-proceed">Add Address</button>
        </form>
    </div>
</body>
</html>