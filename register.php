<?php 
// --- DATABASE CONNECTION ---
$host = "localhost";
$user = "root";       
$password = ""; 
$dbname = "librarydb"; 

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- HANDLE REGISTRATION ---
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password_raw = trim($_POST['password']);
    $role = trim($_POST['role']);

    if (empty($username) || empty($password_raw) || empty($role)) {
        $message = "All fields are required.";
    } else {
        // Securely hash the password
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        // Check if the username already exists
        $check = $conn->prepare("SELECT * FROM Register WHERE Username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "Username already exists. Redirecting to login page...";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 3000);
            </script>";
        } else {
            // Insert new user into the database
            $stmt = $conn->prepare("INSERT INTO Register (Username, Password, Role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password, $role);

            if ($stmt->execute()) {
                $message = "Registration successful. Redirecting to login page...";
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 3000);
                </script>";
            } else {
                $message = "Registration failed: " . $stmt->error;
                error_log("SQL Error: " . $stmt->error);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #eef2f7;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .form-container {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 12px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            color: #333;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        input[type="submit"] {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background-color: #4CAF50;
            border: none;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        .message {
            text-align: center;
            color: green;
            margin-top: 10px;
        }

        .error {
            color: red;
            text-align: center;
            margin-top: 10px;
        }

        .redirect-message {
            text-align: center;
            font-size: 14px;
            margin-top: 10px;
        }

        .toggle-password {
            margin-left: 5px;
        }
    </style>

    <script>
        function validateRegister() {
            const username = document.forms["regForm"]["username"].value.trim();
            const password = document.forms["regForm"]["password"].value.trim();
            const role = document.forms["regForm"]["role"].value;

            if (username === "" || password === "" || role === "") {
                alert("All fields are required.");
                return false;
            }
            return true;
        }

        function togglePassword(id) {
            const field = document.getElementById(id);
            field.type = field.type === "password" ? "text" : "password";
        }
    </script>
</head>
<body>

<div class="form-container">
    <h2>User Registration</h2>

    <?php if (!empty($message)): ?>
        <p class="<?php echo (strpos($message, 'failed') !== false || strpos($message, 'required') !== false) ? 'error' : 'message'; ?>">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <p class="redirect-message">
        Already have an account? <a href="login.php" style="color: #4CAF50;">Login here</a>.
    </p>

    <form name="regForm" method="post" onsubmit="return validateRegister();">
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">

        <label for="password">Password:</label>
        <input type="password" name="password" id="regPass">
        <input type="checkbox" class="toggle-password" onclick="togglePassword('regPass')"> Show Password

        <label for="role">Role:</label>
        <select name="role" id="role">
            <option value="">-- Select Role --</option>
            <option value="Librarian" <?php if (isset($role) && $role === 'Librarian') echo 'selected'; ?>>Librarian</option>
            <option value="Member" <?php if (isset($role) && $role === 'Member') echo 'selected'; ?>>Member</option>
        </select>

        <input type="submit" value="Register">
    </form>
</div>

</body>
</html>
