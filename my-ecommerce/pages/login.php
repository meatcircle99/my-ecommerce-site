<?php
ob_start(); // Start output buffering to prevent header issues
include '../includes/header.php';
include '../config/db.php';

// Check if session is already started to avoid the notice
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            ob_end_clean(); // Clear buffer before redirect
            header('Location: http://localhost/my-ecommerce/pages/home.php');
            echo "<script>window.location.href='http://localhost/my-ecommerce/pages/home.php';</script>"; // Fallback
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    } catch (PDOException $e) {
        $error = "Error logging in: " . $e->getMessage();
    }
}

// Handle signup
if (isset($_POST['signup'])) {
    $signup_method = $_POST['signup_method'];
    $identifier = trim($_POST['identifier']); // Gmail or phone number
    $password = $_POST['password'];

    // Validate and prepare data
    if ($signup_method === 'gmail') {
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : ''; // Validate email format
        $phone_number = '';
        if (empty($email)) {
            $error = "Invalid Gmail format. Please use a valid email address.";
            goto display_forms; // Skip to form display if validation fails
        }
    } else {
        $phone_number = preg_match('/^\+?[1-9]\d{9,14}$/', $identifier) ? $identifier : ''; // Validate phone format (e.g., +919876543210)
        $email = '';
        if (empty($phone_number)) {
            $error = "Invalid phone number format. Please use + followed by 10-15 digits (e.g., +919876543210).";
            goto display_forms; // Skip to form display if validation fails
        }
    }

    try {
        // Check if identifier (username, email, or phone) already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone_number = ?");
        $stmt->execute([$identifier, $email, $phone_number]);
        if ($stmt->fetch()) {
            $error = "This email, phone number, or username already exists!";
        } else {
            // Hash password and insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, phone_number, role) VALUES (?, ?, ?, ?, 'customer')");
            $stmt->execute([$identifier, $hashed_password, $email, $phone_number]);

            // Set success message instead of redirecting immediately
            $success = "Signup done successfully! You can now log in.";
        }
    } catch (PDOException $e) {
        $error = "Error signing up: " . $e->getMessage();
    }
}

// Handle OTP login
if (isset($_POST['login_with_otp'])) {
    $phone_number = $_POST['phone_number'];
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Simulate OTP (for demo, generate a random 6-digit OTP)
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_phone'] = $phone_number;
            $_SESSION['otp_expiry'] = time() + 300; // OTP valid for 5 minutes
            $otp_message = "Your OTP for My E-Commerce login is: $otp";
            // In a real scenario, send $otp_message via SMS; here, we just display it or log it
            echo "<script>alert('OTP sent to your phone: $otp');</script>";
        } else {
            $error = "Phone number not registered!";
        }
    } catch (PDOException $e) {
        $error = "Error requesting OTP: " . $e->getMessage();
    }
}

// Verify OTP
if (isset($_POST['verify_otp'])) {
    $otp = $_POST['otp'];
    if (isset($_SESSION['otp']) && isset($_SESSION['otp_phone']) && isset($_SESSION['otp_expiry'])) {
        if ($_SESSION['otp'] == $otp && time() < $_SESSION['otp_expiry']) {
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE phone_number = ?");
                $stmt->execute([$_SESSION['otp_phone']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['user_id'] = $user['id'];
                unset($_SESSION['otp'], $_SESSION['otp_phone'], $_SESSION['otp_expiry']);
                ob_end_clean(); // Clear buffer before redirect
                header('Location: http://localhost/my-ecommerce/pages/home.php');
                echo "<script>window.location.href='http://localhost/my-ecommerce/pages/home.php';</script>"; // Fallback
                exit;
            } catch (PDOException $e) {
                $error = "Error verifying OTP: " . $e->getMessage();
            }
        } else {
            $error = "Invalid or expired OTP!";
        }
    } else {
        $error = "Session expired. Please request a new OTP.";
    }
}

// Handle Forgot Password Request
if (isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate a reset token
            $token = bin2hex(random_bytes(16)); // 32-character token
            $expires_at = time() + 3600; // Token expires in 1 hour

            // Store token in password_resets table
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
            $stmt->execute([$email, $token, $expires_at, $token, $expires_at]);

            // Simulate sending email (replace with real email in production)
            $reset_link = "http://localhost/my-ecommerce/pages/reset_password.php?token=$token";
            echo "<script>alert('Password reset link sent to $email: $reset_link');</script>";
            $success = "Password reset link has been sent to your email!";
        } else {
            $error = "No account found with that email!";
        }
    } catch (PDOException $e) {
        $error = "Error requesting password reset: " . $e->getMessage();
    }
}

display_forms: // Label for jumping to form display in case of validation errors
ob_end_flush(); // Flush output buffer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .login-container {
            max-width: 600px; /* Increased width */
            width: 100%;
            padding: 3rem;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #ecf0f1;
            animation: slideIn 0.6s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 2.5rem;
            font-size: 2rem;
            font-weight: 700;
            position: relative;
        }
        h2::before {
            content: "➤";
            margin-right: 0.5rem;
            color: #3498db;
        }
        .tabs-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2.5rem;
        }
        .btn-tab {
            background: #3498db;
            color: #fff;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        .btn-tab:hover {
            background: #2980b9;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }
        .btn-tab:active {
            transform: translateY(0);
        }
        .form-group {
            margin-bottom: 2rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 1rem;
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: #f9fbfd;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.2);
        }
        .btn-submit {
            background: #3498db;
            color: #fff;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            width: 100%;
        }
        .btn-submit:hover {
            background: #2980b9;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }
        .btn-submit:active {
            transform: translateY(0);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .error {
            color: #e74c3c;
            background: #fadbd8;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.1rem;
        }
        .success {
            color: #27ae60;
            background: #e8f5e9;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.1rem;
        }
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap');
    </style>
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        }

        // Show login tab by default
        window.onload = function() {
            showTab('login');
        };
    </script>
</head>
<body>
    <div class="login-container">
        <h2>➤ Login / Signup</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>

        <!-- Tabs Navigation -->
        <div class="tabs-nav">
            <button onclick="showTab('login')" class="btn-tab">Login with Username</button>
            <button onclick="showTab('signup')" class="btn-tab">Signup</button>
            <button onclick="showTab('otp')" class="btn-tab">Login with OTP</button>
            <button onclick="showTab('forgot')" class="btn-tab">Forgot Password</button>
        </div>

        <!-- Login Tab -->
        <div id="login" class="tab-content active">
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" name="username" id="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button type="submit" name="login" class="btn-submit">Login</button>
            </form>
        </div>

        <!-- Signup Tab -->
        <div id="signup" class="tab-content">
            <form method="POST">
                <div class="form-group">
                    <label>Signup Method:</label>
                    <select name="signup_method" id="signup_method" required onchange="toggleIdentifier()">
                        <option value="gmail">Gmail</option>
                        <option value="phone">Phone Number</option>
                    </select>
                </div>
                <div class="form-group" id="identifier-group">
                    <label for="identifier">Gmail / Phone Number:</label>
                    <input type="text" name="identifier" id="identifier" placeholder="Enter Gmail or Phone (e.g., +1234567890)" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button type="submit" name="signup" class="btn-submit">Signup</button>
            </form>
        </div>

        <!-- OTP Login Tab -->
        <div id="otp" class="tab-content">
            <form method="POST" id="otp-form">
                <div class="form-group">
                    <label for="phone_number">Phone Number:</label>
                    <input type="text" name="phone_number" id="phone_number" placeholder="Enter Phone (e.g., +1234567890)" required>
                </div>
                <button type="submit" name="login_with_otp" class="btn-submit">Send OTP</button>
            </form>
            <form method="POST" id="verify-otp-form" style="display: none;">
                <div class="form-group">
                    <label for="otp">Enter OTP:</label>
                    <input type="text" name="otp" id="otp" required>
                </div>
                <button type="submit" name="verify_otp" class="btn-submit">Verify OTP</button>
            </form>
        </div>

        <!-- Forgot Password Tab -->
        <div id="forgot" class="tab-content">
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" placeholder="Enter your registered email" required>
                </div>
                <button type="submit" name="forgot_password" class="btn-submit">Request Password Reset</button>
            </form>
        </div>

        <script>
            function toggleIdentifier() {
                const method = document.getElementById('signup_method').value;
                const identifierInput = document.getElementById('identifier');
                if (method === 'gmail') {
                    identifierInput.placeholder = 'Enter Gmail (e.g., user@gmail.com)';
                } else {
                    identifierInput.placeholder = 'Enter Phone (e.g., +1234567890)';
                }
            }

            document.getElementById('otp-form').addEventListener('submit', function(e) {
                e.preventDefault();
                this.style.display = 'none';
                document.getElementById('verify-otp-form').style.display = 'block';
            });
        </script>
    </div>
</body>
</html>