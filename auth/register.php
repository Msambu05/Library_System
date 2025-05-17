<?php
session_start();
require '../config/connection.php';

$message = '';
$formData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'phone' => '',
    'role' => 'Member'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'role' => $_POST['role'] ?? 'Member'
    ];

    // Validate inputs
    $errors = [];

    if (empty($formData['username'])) {
        $errors[] = "Username is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $formData['username'])) {
        $errors[] = "Username must be 3-20 characters (letters, numbers, underscores).";
    }

    if (empty($formData['email'])) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($formData['full_name'])) {
        $errors[] = "Full name is required.";
    }

    if (empty($formData['password'])) {
        $errors[] = "Password is required.";
    } elseif (strlen($formData['password']) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        try {
            // Check if username or email exists
            $checkStmt = $conn->prepare("SELECT UserID FROM Users WHERE Username = ? OR Email = ?");
            $checkStmt->execute([$formData['username'], $formData['email']]);
            
            if ($checkStmt->fetch()) {
                $message = "Username or email already exists.";
            } else {
                // Hash password
                $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
                
                // Insert new user
                $insertStmt = $conn->prepare("
                    INSERT INTO Users 
                    (Username, PasswordHash, Email, FullName, Phone, Role, RegistrationDate) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $insertStmt->execute([
                    $formData['username'],
                    $passwordHash,
                    $formData['email'],
                    $formData['full_name'],
                    $formData['phone'],
                    $formData['role']
                ]);
                
                $_SESSION['registration_success'] = true;
                header("Location: login.php");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $message = "System error. Please try again later.";
        }
    } else {
        $message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library System - Register</title>
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
        
        .register-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
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
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .btn-register {
            width: 100%;
            padding: 0.75rem;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-register:hover {
            background-color: #27ae60;
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
        
        .register-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #7f8c8d;
        }
        
        .register-footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .register-footer a:hover {
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
        
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background-color: #eee;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            background-color: #e74c3c;
            transition: width 0.3s, background-color 0.3s;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Create an Account</h1>
            <p>Join our library community</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message error"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($formData['username']); ?>" 
                       required
                       pattern="[a-zA-Z0-9_]{3,20}"
                       title="3-20 characters (letters, numbers, underscores)">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($formData['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?php echo htmlspecialchars($formData['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?php echo htmlspecialchars($formData['phone']); ?>">
            </div>
            
            <div class="form-group">
                <label for="role">Account Type</label>
                <select id="role" name="role" required>
                    <option value="Member" <?php echo $formData['role'] === 'Member' ? 'selected' : ''; ?>>Member</option>
                    <option value="Librarian" <?php echo $formData['role'] === 'Librarian' ? 'selected' : ''; ?>>Librarian</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8">
                <div class="password-strength">
                    <div class="password-strength-bar" id="password-strength-bar"></div>
                </div>
                <div class="password-toggle">
                    <input type="checkbox" id="showPassword" onclick="togglePassword()">
                    <label for="showPassword">Show password</label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            
            <button type="submit" class="btn-register">Register</button>
        </form>
        
        <div class="register-footer">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            const showPassword = document.getElementById('showPassword');
            
            passwordField.type = showPassword.checked ? 'text' : 'password';
            confirmField.type = showPassword.checked ? 'text' : 'password';
        }
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Complexity checks
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            return strength;
        }
        
        document.getElementById('password').addEventListener('input', function(e) {
            const strength = checkPasswordStrength(e.target.value);
            const bar = document.getElementById('password-strength-bar');
            
            // Update strength bar
            if (strength <= 2) {
                bar.style.width = (strength * 25) + '%';
                bar.style.backgroundColor = '#e74c3c';
            } else if (strength <= 4) {
                bar.style.width = (strength * 25) + '%';
                bar.style.backgroundColor = '#f39c12';
            } else {
                bar.style.width = '100%';
                bar.style.backgroundColor = '#2ecc71';
            }
        });
    </script>
</body>
</html>
