<?php
// DB connection
$host = "localhost";
$user = "root";       
$password = ""; 
$dbname = "librarydb";  

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle user deactivation/reactivation
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $toggle = $_GET['toggle'] === 'deactivate' ? 0 : 1;
    $conn->query("UPDATE Users SET Active = $toggle WHERE UserID = $id");
    header("Location: manage_users.php");
    exit;
}

// Handle new user creation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_user'])) {
    $name = $_POST['fullname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO Users (FullName, Email, PasswordHash, Role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $role);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_users.php");
    exit;
}

// Fetch all users
$users = $conn->query("SELECT * FROM Users ORDER BY UserID DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <style>
        body { font-family: Arial; margin: 0; background: #f0f0f0; }
        .nav { background: #1a237e; padding: 12px; }
        .nav a { color: white; margin-right: 20px; text-decoration: none; font-weight: bold; }
        .container { padding: 20px; }
        h2 { color: #1a237e; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
        th { background: #3949ab; color: white; }
        .inactive { background-color: #fdd; }
        .form-box { background: white; padding: 20px; margin-top: 20px; border: 1px solid #ccc; }
    </style>
</head>
<body>

<div class="nav">
<a href="admindashboard.php">📊 Dashboard</a>
        <a href="manage_books.php">📚 Manage Books</a>
        <a href="borrowed_books.php">📖 Borrowed & Returned</a>
        <a href="manage_users.php">👥 Manage Users</a>
</div>

<div class="container">
    <h2>Registered Users</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $users->fetch_assoc()): ?>
            <tr class="<?= $row['Active'] ? '' : 'inactive' ?>">
                <td><?= $row['UserID'] ?></td>
                <td><?= htmlspecialchars($row['FullName']) ?></td>
                <td><?= htmlspecialchars($row['Email']) ?></td>
                <td><?= $row['Role'] ?></td>
                <td><?= $row['Active'] ? 'Active' : 'Inactive' ?></td>
                <td>
                    <a href="?toggle=<?= $row['Active'] ? 'deactivate' : 'activate' ?>&id=<?= $row['UserID'] ?>">
                        <?= $row['Active'] ? 'Deactivate' : 'Activate' ?>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <div class="form-box">
        <h3>Add New Member</h3>
        <form method="POST">
            <label>Full Name:</label><br>
            <input type="text" name="fullname" required><br><br>

            <label>Email:</label><br>
            <input type="email" name="email" required><br><br>

            <label>Password:</label><br>
            <input type="password" name="password" required><br><br>

            <label>Role:</label><br>
            <select name="role" required>
                <option value="Member">Member</option>
                <option value="Librarian">Librarian</option>
            </select><br><br>

            <input type="submit" name="add_user" value="Add User">
        </form>
    </div>
</div>

</body>
</html>