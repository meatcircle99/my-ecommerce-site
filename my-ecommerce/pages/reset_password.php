<?php
ob_start();
include '../includes/header.php';
include '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = $success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if ($token) {
    try {
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > ?");
        $stmt->execute([$token, time()]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            $error = "Invalid or expired reset token!";
        }
    } catch (PDOException $e) {
        $error = "Error verifying token: " . $e->getMessage();
    }
}

if (isset($_POST['reset_password']) && $token && !$error) {
    $new_password = $_POST['new_password'];
    try {
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > ?");
        $stmt->execute([$token, time()]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            $email = $reset['email'];
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);

            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            $success = "Password reset successfully! You can now log in.";
        } else {
            $error = "Invalid or expired reset token!";
        }
    } catch (PDOException $e) {
        $error = "Error resetting password: " . $e->getMessage();
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .reset-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 2rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-reset {
            background-color: #2c3e50;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-reset:hover {
            background-color: #34495e;
        }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Reset Password</h2>
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>
        <?php if ($success) echo "<p class='success'>$success</p>"; ?>

        <?php if (!$error && $token): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" name="new_password" id="new_password" required>
                </div>
                <button type="submit" name="reset_password" class="btn-reset">Reset Password</button>
            </form>
        <?php else: ?>
            <p>Please check your email for a valid reset link or request a new one.</p>
            <a href="http://localhost/my-ecommerce/pages/login.php">Back to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>