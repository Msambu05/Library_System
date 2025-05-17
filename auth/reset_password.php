<?php
session_start();
require '../config/connection.php';

$message = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$token = $_GET['token'] ?? '';

// Step 1: Request reset
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    if ($email) {
        try {
            // Check if email exists
            $stmt = $conn->prepare("SELECT UserID FROM Users WHERE Email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                
                // Delete any existing tokens
                $conn->prepare("DELETE FROM PasswordResets WHERE UserID = ?")->execute([$user['UserID']]);
                
                // Store new token
                $insertStmt = $conn->prepare("
                    INSERT INTO PasswordResets (UserID, Token, Expiry) 
                    VALUES (?, ?, ?)
                ");
                $insertStmt->execute([$user['UserID'], $token, $expiry]);
                
                // In a real system, you would send an email here
                $resetLink = "http://yourdomain.com/auth/reset_password.php?step=2&token=$token";
                
                // For demo purposes, we'll just show the link
                $message = "Reset link: <a href='$resetLink'>$resetLink</a>";
            } else {
                $message = "If this email exists in our system, you'll receive a reset link.";
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $message = "System error. Please try again later.";
        }
    } else {
        $message = "Please enter a valid email address.";
    }
}

// Step 2: Verify token and show password form
if ($step === 2 && !empty($token)) {
    try {
        $stmt = $conn->prepare("
            SELECT r.*, u.Email 
            FROM PasswordResets r
            JOIN Users u ON r.UserID = u.UserID
            WHERE r.Token = ? AND r.Used = 0 AND r.Expiry > NOW()
        ");
        $stmt->execute([$token]);
        $resetRequest = $stmt->fetch();
        
        if (!$resetRequest) {
            $message = "Invalid or expired reset token.";
            $step = 1; // Go back to step 1
        }
    } catch (PDOException $e) {
        error_log("Token verification error: " . $e->getMessage());
        $message = "System error. Please try again later.";
        $step = 1;
    }
}

// Step 2: Process password reset
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        try {
            // Verify token again
            $stmt = $conn->prepare("
                SELECT r.UserID 
                FROM PasswordResets r
                WHERE r.Token = ? AND r.Used = 0 AND r.Expiry > NOW()
            ");
            $stmt->execute([$token]);
            $resetRequest = $stmt->fetch();
            
            if ($resetRequest) {
                // Update password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("
                    UPDATE Users 
                    SET PasswordHash = ? 
                    WHERE UserID = ?
                ");
                $updateStmt->execute([$passwordHash, $resetRequest['UserID']]);
                
                // Mark token as used
                $conn->prepare("
                    UPDATE PasswordResets 
                    SET Used = 1 
                    WHERE Token = ?
                ")->execute([$token]);
                
                $message = "Password updated successfully. You can now <a href='login.php'>login</a>.";
                $step = 3; // Success step
            } else {
                $message = "Invalid or expired reset token.";
                $step = 1;
            }
        } catch (PDOException $e) {
            error_log("Password update error: " . $e->getMessage());
            $message = "System error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library System - Reset Password</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .reset-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .reset-header p {
            color: #7f8c8d;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .btn-reset {
            width: 100%;
            padding: 0.75rem;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-reset:hover {
            background-color: #2980b9;
        }
        
        .message {
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: 4px;
        }
        
        .error {
            background-color: #fdecea;
            color: #c62828;
        }
        
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .reset-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #7f8c8d;
        }
        
        .reset-footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .reset-footer a:hover {
            text-decoration: underline;
        }
        
        .password-toggle {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
        }
        
        .password-toggle input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1>Reset Password</h1>
            <p><?php 
                if ($step === 1) echo "Enter your email to receive a reset link";
                elseif ($step === 2) echo "Enter your new password";
                else echo "Password reset successful";
            ?></p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <form method="post" action="reset_password.php?step=1">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <button type="submit" class="btn-reset">Send Reset Link</button>
            </form>
            
            <div class="reset-footer">
                Remember your password? <a href="login.php">Login here</a>
            </div>
        
        <?php elseif ($step === 2): ?>
            <form method="post" action="reset_password.php?step=2&token=<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    <div class="password-toggle">
                        <input type="checkbox" id="showPassword" onclick="togglePassword()">
                        <label for="showPassword">Show password</label>
                    </div>
                </div>
                
                <button type="submit" class="btn-reset">Reset Password</button>
            </form>
        
        <?php elseif ($step === 3): ?>
            <div class="reset-footer">
                <a href="login.php">Return to login page</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            const showPassword = document.getElementById('showPassword');
            
            if (passwordField && confirmField) {
                passwordField.type = showPassword.checked ? 'text' : 'password';
                confirmField.type = showPassword.checked ? 'text' : 'password';
            }
        }
    </script>
</body>
</html>