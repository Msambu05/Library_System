<?php
session_start();
require '../config/connection.php';

// Initialize variables
$message = '';
$username = '';
$login_blocked = false;

// Check for brute force attempts
if (isset($_SESSION['login_attempts']) {
    if ($_SESSION['login_attempts'] >= 5) {
        $login_blocked = true;
        $remaining_time = (60 * 5) - (time() - $_SESSION['last_attempt_time']);
        if ($remaining_time <= 0) {
            unset($_SESSION['login_attempts']);
            $login_blocked = false;
        } else {
            $message = "Too many failed attempts. Please try again in " . ceil($remaining_time/60) . " minutes.";
        }
    }
}

// Process login form
if (!$login_blocked && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $message = "Please enter both username and password.";
    } else {
        try {
            // Get user from database
            $stmt = $conn->prepare("SELECT * FROM Users WHERE Username = ? OR Email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user) {
                // Verify password
                if (password_verify($password, $user['PasswordHash'])) {
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);
                    
                    // Store user in session
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['role'] = $user['Role'];
                    $_SESSION['full_name'] = $user['FullName'];
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    $_SESSION['last_login'] = time();

                    // Update last login time
                    $update_stmt = $conn->prepare("UPDATE Users SET LastLogin = NOW() WHERE UserID = ?");
                    $update_stmt->execute([$user['UserID']]);

                    // Reset login attempts
                    unset($_SESSION['login_attempts']);

                    // Redirect based on role
                    if ($user['Role'] === 'Librarian') {
                        header("Location: ../admin/index.php");
                    } else {
                        header("Location: ../member/index.php");
                    }
                    exit();
                } else {
                    // Increment failed attempts
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    $_SESSION['last_attempt_time'] = time();
                    $message = "Invalid username or password.";
                }
            } else {
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['last_attempt_time'] = time();
                $message = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
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
    <title>Library System - Login</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        .login-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
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
        
        .btn-login {
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
        
        .btn-login:hover {
            background-color: #2980b9;
        }
        
        .btn-login:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
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
        
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #7f8c8d;
        }
        
        .login-footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .login-footer a:hover {
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
    <div class="login-container">
        <div class="login-header">
            <h1>Library System</h1>
            <p>Please login to continue</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <div class="password-toggle">
                    <input type="checkbox" id="showPassword" onclick="togglePassword()">
                    <label for="showPassword">Show password</label>
                </div>
            </div>
            
            <button type="submit" name="login" class="btn-login" <?php echo $login_blocked ? 'disabled' : ''; ?>>Login</button>
        </form>
        
        <div class="login-footer">
            <a href="reset_password.php">Forgot password?</a> | 
            <a href="register.php">Create an account</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const showPassword = document.getElementById('showPassword');
            passwordField.type = showPassword.checked ? 'text' : 'password';
        }
    </script>
</body>
</html>