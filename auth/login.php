<?php
session_start();
include '../db/connection.php'; // Adjust path if needed

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $message = "Please fill in all fields.";
    } else {
        // Case-insensitive comparison for username
        $stmt = $conn->prepare("SELECT * FROM Register WHERE LOWER(Username) = LOWER(?)");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // Check password using password_verify()
            if (password_verify($password, $row["Password"])) {
                $_SESSION["RegID"] = $row["RegID"];
                $_SESSION["UserID"] = $row["RegID"];
                $_SESSION["Username"] = $row["Username"]; // Uppercase 'U' to match dashboard
                $_SESSION["Role"] = $row["Role"];
                $_SESSION["FullName"] = $row["FullName"];

                // Redirect based on role
                if (strtolower($row["Role"]) === "member") {
                    header("Location: member_dashboard.php");
                    exit();
                } elseif (strtolower($row["Role"]) === "librarian") {
                    header("Location: admindashboard.php");
                    exit();
                } else {
                    $message = "Invalid role detected.";
                }
            } else {
                $message = "Incorrect password.";
            }
        } else {
            $message = "Invalid username.";
        }

        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body {
            font-family: Arial;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .login-box {
            width: 400px;
            background: white;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-radius: 8px;
        }
        .login-box h2 {
            text-align: center;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        .message {
            color: red;
            text-align: center;
            margin-bottom: 10px;
        }
        .register {
            text-align: center;
            margin-top: 10px;
        }
    </style>
    <script>
        function togglePassword() {
            const pass = document.getElementById("password");
            pass.type = pass.type === "password" ? "text" : "password";
        }
    </script>
</head>
<body>

<div class="login-box">
    <h2>Login</h2>
    <?php if (!empty($message)) echo "<p class='message'>$message</p>"; ?>
    <form method="post">
        <div class="form-group">
            <label>Username (Email/Phone)</label>
            <input type="text" name="username" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" id="password" name="password" required>
            <input type="checkbox" onclick="togglePassword()"> Show Password
        </div>

        <input type="submit" name="login" value="Login" class="btn">
    </form>

    <div class="register">
        <a href="register.php">Don't have an account? Register</a>
    </div>
</div>

</body>
</html>