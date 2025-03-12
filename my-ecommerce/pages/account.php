<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $sql = "SELECT username, email, phone_number FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found.");
    }
} catch (PDOException $e) {
    error_log("Database error in account.php: " . $e->getMessage());
    die("Error loading user details: " . $e->getMessage());
} catch (Exception $e) {
    error_log("User error in account.php: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}

try {
    $stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->execute([$user_id]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching addresses in account.php: " . $e->getMessage());
    $addresses = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        $update_sql = "UPDATE users SET email = ?, phone_number = ?";
        $params = [$email, $phone_number];

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_sql .= ", password = ?";
            $params[] = $hashed_password;
        }

        $update_sql .= " WHERE id = ?";
        $params[] = $user_id;

        $stmt = $conn->prepare($update_sql);
        $stmt->execute($params);

        if ($email !== $user['email']) {
            $_SESSION['email'] = $email;
        }

        header('Location: account.php?success=1');
        exit;
    } catch (PDOException $e) {
        error_log("Database error updating user in account.php: " . $e->getMessage());
        $error = "Error updating details: " . $e->getMessage();
    }
}

$success = isset($_GET['success']) && $_GET['success'] == 1;
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - My E-Commerce</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            padding-top: 70px;
        }

        .account-content {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            gap: 30px;
        }

        .page-title {
            color: #124E66;
            text-align: center;
            margin-bottom: 20px;
            font-size: 28px;
        }

        .account-form-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 45%;
        }

        .account-form .form-group {
            margin-bottom: 15px;
        }

        .account-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .account-form input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box; /* Ensure padding doesn't overflow */
        }

        .account-form .btn-submit {
            background: #124E66;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }

        .success-message {
            color: green;
            text-align: center;
            margin-bottom: 15px;
        }

        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }

        .address-section {
            width: 50%;
        }

        .address-section h2 {
            color: #124E66;
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }

        .address-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .address-item {
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .address-item.default {
            border: 2px solid #124E66;
        }

        .address-item p {
            margin: 5px 0;
        }

        .address-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .btn-edit, .btn-delete {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            display: inline-block;
        }

        .btn-edit {
            background: #ff6b6b;
        }

        .btn-edit:hover {
            background: #d84343;
        }

        .btn-delete {
            background: #666;
        }

        .btn-delete:hover {
            background: #444;
        }

        .no-addresses {
            text-align: center;
            color: #666;
            font-size: 18px;
        }

        .btn-add-address {
            display: block;
            width: 200px;
            margin: 20px auto 0;
            padding: 10px;
            background: #124E66;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
        }

        .btn-add-address:hover {
            background: #0e3b4e;
        }

        @media (max-width: 768px) {
            .account-content {
                flex-direction: column;
                padding: 10px;
                gap: 20px;
            }

            .account-form-container, .address-section {
                width: 100%;
                padding: 15px;
            }

            .page-title {
                font-size: 24px;
            }

            .account-form .form-group {
                margin-bottom: 12px;
            }

            .account-form input {
                padding: 6px;
                font-size: 14px;
            }

            .account-form .btn-submit {
                padding: 8px;
                font-size: 16px;
            }

            .address-section h2 {
                font-size: 20px;
            }

            .address-item {
                padding: 10px;
            }

            .address-item p {
                font-size: 14px;
            }

            .address-actions {
                flex-direction: column;
                gap: 8px;
            }

            .btn-edit, .btn-delete {
                padding: 6px 12px;
                font-size: 14px;
                width: 100%;
                text-align: center;
            }

            .btn-add-address {
                width: 100%;
                padding: 8px;
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 20px;
            }

            .account-form input {
                font-size: 12px;
            }

            .address-section h2 {
                font-size: 18px;
            }

            .address-item p {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <main class="account-content">
        <div class="address-section">
            <h2>My Addresses</h2>
            <div class="address-list">
                <?php if (!empty($addresses)): ?>
                    <?php foreach ($addresses as $address): ?>
                        <div class="address-item <?php echo $address['is_default'] ? 'default' : ''; ?>">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($address['name']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($address['phone_number']); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($address['address']); ?></p>
                            <?php if (!empty($address['landmark'])): ?>
                                <p><strong>Landmark:</strong> <?php echo htmlspecialchars($address['landmark']); ?></p>
                            <?php endif; ?>
                            <p><strong>Locality:</strong> <?php echo htmlspecialchars($address['locality']); ?></p>
                            <p><strong>Pincode:</strong> <?php echo htmlspecialchars($address['pincode']); ?></p>
                            <p><strong>City:</strong> <?php echo htmlspecialchars($address['city']); ?></p>
                            <?php if (!empty($address['alternate_number'])): ?>
                                <p><strong>Alternate Phone:</strong> <?php echo htmlspecialchars($address['alternate_number']); ?></p>
                            <?php endif; ?>
                            <p><strong>Default:</strong> <?php echo $address['is_default'] ? 'Yes' : 'No'; ?></p>
                            <div class="address-actions">
                                <a href="edit-address.php?id=<?php echo $address['id']; ?>&return_to=account" class="btn-edit">Edit</a>
                                <a href="delete-address.php?id=<?php echo $address['id']; ?>&return_to=account" class="btn-delete" onclick="return confirm('Are you sure you want to delete this address?');">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-addresses">No addresses found.</p>
                <?php endif; ?>
            </div>
            <a href="add-address.php?return_to=account" class="btn-add-address">Add New Address</a>
        </div>

        <div class="account-form-container">
            <h1 class="page-title">My Account</h1>
            <?php if ($success): ?>
                <p class="success-message">Details updated successfully!</p>
            <?php elseif (isset($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST" class="account-form">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone_number">Phone Number:</label>
                    <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required pattern="[0-9]{10}" title="Please enter a 10-digit phone number">
                </div>
                <div class="form-group">
                    <label for="password">New Password (Leave blank to keep current):</label>
                    <input type="password" id="password" name="password" placeholder="Enter new password (optional)">
                </div>
                <button type="submit" class="btn-submit">Update Details</button>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cartCountElement = document.getElementById('cart-count');
            if (cartCountElement) {
                async function updateCartCount() {
                    try {
                        const response = await fetch('../cart.php?action=get_count', {
                            method: 'GET',
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });

                        console.log('HTTP Status for get_count on account:', response.status);
                        const rawText = await response.text();
                        console.log('Raw response from get_count on account:', rawText);

                        if (!rawText) {
                            throw new Error('Empty response from server');
                        }

                        let data;
                        try {
                            data = JSON.parse(rawText);
                        } catch (parseError) {
                            throw new Error('Failed to parse JSON on account: ' + parseError.message + ' Raw response: ' + rawText);
                        }

                        cartCountElement.textContent = data.count || 0;
                        cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                    } catch (error) {
                        console.error('Error fetching cart count on account:', error);
                    }
                }

                updateCartCount();

                const eventSource = new EventSource('../cart.php?action=listen_cart&user_id=' . encodeURIComponent('<?php echo $_SESSION['user_id'] ?? 'anonymous'; ?>'));

                eventSource.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        console.log('SSE event data on account:', data);
                        if (data.count !== undefined) {
                            cartCountElement.textContent = data.count;
                            cartCountElement.style.display = (data.count > 0) ? 'inline-block' : 'none';
                            console.log('Cart count updated via SSE on account to:', data.count);
                        }
                    } catch (e) {
                        console.error('Error parsing SSE data on account:', e);
                        console.log('Raw SSE data on account:', event.data);
                    }
                };

                eventSource.onerror = function() {
                    console.error('SSE connection error on account, attempting to reconnect...');
                    setTimeout(() => {
                        if (eventSource.readyState === EventSource.CLOSED) {
                            eventSource.close();
                            const newSource = new EventSource('../cart.php?action=listen_cart&user_id=' . encodeURIComponent('<?php echo $_SESSION['user_id'] ?? 'anonymous'; ?>'));
                            eventSource.onmessage = eventSource.onmessage;
                            eventSource.onerror = eventSource.onerror;
                        }
                    }, 5000);
                };
            } else {
                console.error('Cart count element (#cart-count) not found in DOM on account');
            }

            const cartIcon = document.getElementById('cart-icon');
            if (cartIcon) {
                cartIcon.addEventListener('click', () => {
                    window.location.href = '../cart.php';
                });
            } else {
                console.error('Cart icon element (#cart-icon) not found in DOM on account');
            }
        });
    </script>
</body>
</html>