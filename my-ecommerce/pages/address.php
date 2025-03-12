<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../config/db.php';

// Check database connection
if (!$conn) {
    die("Database connection failed. Please try again later.");
}

// Fetch same-day delivery setting
try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'same_day_delivery_enabled'");
    $stmt->execute();
    $same_day_setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $same_day_enabled = $same_day_setting ? (int)$same_day_setting['setting_value'] : 0;
} catch (PDOException $e) {
    error_log("Error fetching same-day delivery setting: " . $e->getMessage());
    $same_day_enabled = 0;
}
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Address Management - Meatcircle</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    /* Global Reset and Base Styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Roboto', sans-serif;
      background: #f0f2f5;
      color: #333;
      line-height: 1.6;
    }
    h2 {
      font-size: 1.8rem;
      color: #2c3e50;
      margin-bottom: 1rem;
      border-bottom: 2px solid #2c3e50;
      padding-bottom: 0.5rem;
    }
    h2 i {
      margin-right: 10px;
      color: #3498db;
    }
    h3 {
      font-size: 1.1rem;
      color: #2c3e50;
      margin-bottom: 10px;
    }
    h3 i {
      margin-right: 8px;
      color: #28a745;
    }
    /* Main Container: Full Width with No Side Gaps */
    .address-content {
      display: flex;
      flex-wrap: wrap;
      width: 100%;
      padding: 40px 20px;
      background: #fff;
    }
    .address-management, .add-address {
      flex: 1;
      padding: 20px;
    }
    /* Address List Cards */
    .address-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }
    .address-card {
      background: #fff;
      border: 1px solid #e3e3e3;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      cursor: pointer;
    }
    .address-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    .address-card p {
      margin-bottom: 10px;
      font-size: 0.95rem;
    }
    .address-card p strong {
      color: #2c3e50;
    }
    .address-actions {
      margin-top: 10px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    /* Button Styles */
    .btn-action, .btn-proceed {
      display: inline-block;
      padding: 10px 16px;
      border: none;
      border-radius: 4px;
      font-size: 0.9rem;
      font-weight: 500;
      text-decoration: none;
      color: #fff;
      background: #3498db;
      transition: background 0.3s ease, transform 0.3s ease;
    }
    .btn-action:hover, .btn-proceed:hover {
      background: #2980b9;
      transform: scale(1.02);
    }
    .btn-delete {
      background: #e74c3c;
    }
    .btn-delete:hover {
      background: #c0392b;
    }
    /* Form Styling */
    .address-form {
      background: #fff;
      border: 1px solid #e3e3e3;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
      color: #2c3e50;
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 0.95rem;
      transition: border-color 0.3s ease;
    }
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
      border-color: #3498db;
      outline: none;
    }
    .default-checkbox label {
      font-size: 0.9rem;
      color: #555;
    }
    .no-addresses {
      text-align: center;
      padding: 15px;
      color: #777;
      font-size: 0.95rem;
    }
    /* Delivery Options */
    .delivery-options {
      margin-top: 20px;
      background: #f8f9fa;
      padding: 20px;
      border: 1px solid #e3e3e3;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    .delivery-options h3 {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
    }
    .delivery-options h3 i {
      margin-right: 10px;
    }
    .delivery-options p {
      margin-bottom: 15px;
      font-size: 1rem;
      color: #555;
    }
    .delivery-option-group {
      margin-bottom: 20px;
    }
    .delivery-option-group h4 {
      font-size: 1rem;
      font-weight: 500;
      color: #2c3e50;
      margin-bottom: 10px;
      border-bottom: 1px solid #e3e3e3;
      padding-bottom: 5px;
    }
    .delivery-option-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 10px;
    }
    .delivery-option {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 8px;
      background: #fff;
      border: 2px solid #e3e3e3;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .delivery-option input[type="radio"] {
      display: none; /* Hide the default radio button */
    }
    .delivery-option span {
      font-size: 0.95rem;
      color: #333;
      width: 100%;
      text-align: center;
    }
    .delivery-option.selected {
      background: #3498db;
      border-color: #2980b9;
    }
    .delivery-option.selected span {
      color: #fff;
    }
    /* Proceed Payment Button */
    .btn-proceed-payment {
      display: block;
      margin: 20px auto;
      padding: 12px 24px;
      font-size: 1.1rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      background: #28a745;
      color: #fff;
      transition: background 0.3s ease, transform 0.3s ease;
    }
    .btn-proceed-payment:hover {
      background: #218838;
      transform: scale(1.02);
    }
    /* Responsive Adjustments */
    @media (max-width: 768px) {
      .address-content {
        flex-direction: column;
        padding: 20px 10px;
      }
      .address-list {
        grid-template-columns: 1fr;
      }
      .delivery-option-grid {
        grid-template-columns: 1fr;
      }
    }
    /* Footer Styling */
    footer.footer {
      background: #2c3e50;
      color: #fff;
      text-align: center;
      padding: 10px 0;
      margin-top: 30px;
    }
    footer.footer a.footer-link {
      color: #fff;
      text-decoration: none;
      margin: 0 5px;
      font-size: 0.9rem;
    }
    footer.footer a.footer-link:hover {
      text-decoration: underline;
    }
    footer.footer .footer-text {
      margin-top: 5px;
      font-size: 0.85rem;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&libraries=places"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      let pincodeCache = {};
      let debounceTimer;

      $('#pincode').on('input', function() {
        clearTimeout(debounceTimer);
        const pincode = $(this).val().trim();
        if (pincode.length === 6) {
          debounceTimer = setTimeout(() => {
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
                  alert('Error fetching location. Enter city/locality manually.');
                  $('#city').val('');
                  $('#locality').val('');
                }
              });
            }
          }, 300);
        }
      });

      document.querySelector('#add-address-form .btn-proceed').addEventListener('click', (e) => {
        e.preventDefault();
        const phone = document.getElementById('phone_number').value;
        if (!/^[0-9]{10}$/.test(phone)) {
          alert('Please enter a valid 10-digit phone number.');
          return;
        }
        document.getElementById('add-address-form').submit();
      });

      document.querySelectorAll('.address-card').forEach(card => {
        card.addEventListener('click', (e) => {
          if (!e.target.closest('.address-actions')) {
            const radio = card.querySelector('input[type="radio"]');
            if (radio) {
              radio.checked = true;
            }
          }
        });
      });

      const form = document.querySelector('.address-management form');
      if (form) {
        form.addEventListener('submit', function(e) {
          const timeSlot = form.querySelector('input[name="time_slot"]:checked');
          if (!form.querySelector('input[name="address_id"]:checked')) {
            e.preventDefault();
            alert('Please select an address to proceed.');
          } else if (!timeSlot) {
            e.preventDefault();
            alert('Please select a time slot.');
          }
        });
      }

      // Handle time slot selection with box styling
      document.querySelectorAll('.delivery-option input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
          // Remove selected style from all siblings
          document.querySelectorAll('.delivery-option').forEach(option => {
            option.classList.remove('selected');
          });
          // Add selected style to the clicked option
          if (this.checked) {
            this.parentElement.classList.add('selected');
          }
        });
      });

      const cartCountElement = document.getElementById('cart-count');
      if (cartCountElement) {
        async function updateCartCount() {
          try {
            const response = await fetch('../cart.php?action=get_count', { credentials: 'same-origin' });
            const data = await response.json();
            cartCountElement.textContent = data.count || 0;
            cartCountElement.style.display = data.count > 0 ? 'inline-block' : 'none';
          } catch (error) {
            console.error('Error fetching cart count:', error);
          }
        }
        updateCartCount();
        setInterval(updateCartCount, 500);
      }

      const cartIcon = document.getElementById('cart-icon');
      if (cartIcon) {
        cartIcon.addEventListener('click', () => {
          window.location.href = '../cart.php';
        });
      }
    });
  </script>
</head>
<body>
  <main class="address-content">
    <div class="address-management">
      <h2><i class="fas fa-address-book"></i> Address Management</h2>
      <?php
      try {
        if (!isset($_SESSION['user_id'])) {
          echo "<p class='no-addresses'>Please log in to manage your addresses.</p>";
        } else {
          $user_id = $_SESSION['user_id'];
          $conn->exec("CREATE INDEX IF NOT EXISTS idx_addresses_user_default ON addresses(user_id, is_default)");
          $stmt = $conn->prepare("SELECT id, name, phone_number, address, landmark, pincode, locality, alternate_number, city, is_default, created_at 
                                  FROM addresses 
                                  WHERE user_id = ? 
                                  ORDER BY is_default DESC, created_at DESC 
                                  LIMIT 10");
          $stmt->execute([$user_id]);
          $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

          if (empty($addresses)) {
            echo "<p class='no-addresses'>No addresses added yet. Please add an address to proceed.</p>";
          } else {
            echo '<form id="select-address-form" method="GET" action="payments.php">';
            echo '<div class="address-list">';
            foreach ($addresses as $address) {
              echo "<div class='address-card'>";
              echo "<input type='radio' name='address_id' value='" . htmlspecialchars($address['id']) . "' " . ($address['is_default'] ? 'checked' : '') . ">";
              echo "<p><strong>Name:</strong> " . htmlspecialchars($address['name']) . "</p>";
              echo "<p><strong>Phone:</strong> " . htmlspecialchars($address['phone_number']) . "</p>";
              echo "<p><strong>Address:</strong> " . htmlspecialchars($address['address'] . ", " . $address['locality'] . ", " . $address['city'] . ", " . $address['pincode']) . "</p>";
              if ($address['landmark']) echo "<p><strong>Landmark:</strong> " . htmlspecialchars($address['landmark']) . "</p>";
              if ($address['alternate_number']) echo "<p><strong>Alternate Phone:</strong> " . htmlspecialchars($address['alternate_number']) . "</p>";
              echo "<p><strong>Default:</strong> " . ($address['is_default'] ? '<span style=\"color: #2ecc71;\">Yes</span>' : 'No') . "</p>";
              echo "<div class='address-actions'>";
              echo "<a href='edit-address.php?id=" . htmlspecialchars($address['id']) . "&return_to=address' class='btn-action'><i class='fas fa-edit'></i> Edit</a>";
              echo "<a href='delete-address.php?id=" . htmlspecialchars($address['id']) . "&return_to=address' class='btn-action btn-delete' onclick='return confirm(\"Are you sure you want to delete this address?\");'><i class='fas fa-trash'></i> Delete</a>";
              if (!$address['is_default']) {
                echo " <a href='set-default-address.php?id=" . htmlspecialchars($address['id']) . "&return_to=address' class='btn-action'><i class='fas fa-star'></i> Set as Default</a>";
              }
              echo "</div>";
              echo "</div>";
            }
            echo '</div>';

            // Delivery Options Section
            echo '<div class="delivery-options">';
            echo '<h3><i class="fas fa-truck"></i> Delivery Options</h3>';
            echo '<p>Select Delivery Date:</p>';
            
            // Fetch and sort time slots from the time_slots table
            try {
              $stmt = $conn->prepare("SELECT id, time_range, delivery_type FROM time_slots WHERE is_active = TRUE ORDER BY delivery_type, time_range");
              $stmt->execute();
              $time_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

              $same_day_slots = [];
              $next_day_slots = [];
              foreach ($time_slots as $slot) {
                if ($slot['delivery_type'] === 'same_day') {
                  $same_day_slots[] = $slot;
                } else {
                  $next_day_slots[] = $slot;
                }
              }
            } catch (PDOException $e) {
              error_log("Error fetching time slots: " . $e->getMessage());
              $same_day_slots = [];
              $next_day_slots = [];
            }

            // Time Slot Selection
            echo '<div class="form-group">';
            // Removed $same_day_enabled check to always show same day slots if available
            if (!empty($same_day_slots)) {
              echo '<div class="delivery-option-group">';
              echo '<h4>Same Day Delivery</h4>';
              echo '<div class="delivery-option-grid">';
              foreach ($same_day_slots as $slot) {
                echo '<label class="delivery-option">';
                echo '<input type="radio" name="time_slot" value="' . htmlspecialchars($slot['id']) . '" required>';
                echo '<span>' . htmlspecialchars($slot['time_range']) . '</span>';
                echo '</label>';
              }
              echo '</div>';
              echo '</div>';
            }
            if (!empty($next_day_slots)) {
              echo '<div class="delivery-option-group">';
              echo '<h4>Next Day Delivery</h4>';
              echo '<div class="delivery-option-grid">';
              foreach ($next_day_slots as $slot) {
                echo '<label class="delivery-option">';
                echo '<input type="radio" name="time_slot" value="' . htmlspecialchars($slot['id']) . '" required>';
                echo '<span>' . htmlspecialchars($slot['time_range']) . '</span>';
                echo '</label>';
              }
              echo '</div>';
              echo '</div>';
            }
            if (empty($same_day_slots) && empty($next_day_slots)) {
              echo '<p>No active time slots available.</p>';
            }
            echo '</div>';
            echo '</div>';
            echo '</form>';
          }
        }
      } catch (PDOException $e) {
        error_log("Error loading addresses: " . $e->getMessage());
        echo "<p class='no-addresses'>Error loading addresses: " . htmlspecialchars($e->getMessage()) . "</p>";
      }
      ?>
    </div>
    <div class="add-address">
      <h2><i class="fas fa-plus-circle"></i> Add New Address</h2>
      <div class="address-form">
        <form method="POST" action="add-address.php?return_to=address" id="add-address-form">
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
            <input type="text" id="landmark" name="landmark" placeholder="e.g., Near Park">
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
    </div>
  </main>

  <?php if (!empty($addresses) && (!empty($same_day_slots) || !empty($next_day_slots))): ?>
    <button type="submit" form="select-address-form" class="btn-proceed-payment">Proceed to Payment</button>
  <?php endif; ?>

  <footer class="footer">
    <div class="footer-links">
      <a href="../pages/about.php" class="footer-link">About Us</a> |
      <a href="../pages/terms.php" class="footer-link">Terms & Conditions</a> |
      <a href="../pages/contact.php" class="footer-link">Contact Us</a>
    </div>
    <p class="footer-text">© 2025 Meatcircle. All rights reserved.</p>
  </footer>
</body>
</html>
