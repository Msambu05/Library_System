<?php
$host = "localhost";
$user = "root";       
$password = ""; 
$dbname = "librarydb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Default role (can be changed using the toggle)
$userRole = isset($_GET['role']) ? $_GET['role'] : 'Member';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: #333;
            color: white;
            padding: 15px 0;
            text-align: center;
        }
        nav {
            background-color: #444;
            overflow: hidden;
        }
        nav a {
            float: left;
            display: block;
            color: white;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
        }
        nav a:hover {
            background-color: #555;
        }
        .book-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .book-card {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 15px;
            width: calc(33.33% - 20px);
            box-sizing: border-box;
        }
        .btn {
            display: inline-block;
            padding: 8px 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-danger {
            background-color: #f44336;
        }
        .role-section {
            text-align: right;
            padding: 10px;
            background-color: #f1f1f1;
        }
        @media (max-width: 768px) {
            .book-card {
                width: calc(50% - 20px);
            }
        }
        @media (max-width: 480px) {
            .book-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Library Management System</h1>
    </header>
    
    <div class="role-section">
        Current Mode: <?php echo $userRole; ?> 
        <?php if($userRole == 'Member'): ?>
            <a href="index.php?role=Librarian" class="btn">Switch to Librarian Mode</a>
        <?php else: ?>
            <a href="index.php?role=Member" class="btn">Switch to Member Mode</a>
        <?php endif; ?>
    </div>
    
    <nav>
        <a href="index.php?role=<?php echo $userRole; ?>">Home</a>
        <a href="books.php?role=<?php echo $userRole; ?>">Browse Books</a>
        <a href="my_books.php?role=<?php echo $userRole; ?>">My Borrowed Books</a>
        <?php if($userRole == 'Librarian'): ?>
            <a href="manage_books.php?role=<?php echo $userRole; ?>">Manage Books</a>
            <a href="manage_users.php?role=<?php echo $userRole; ?>">Manage Users</a>
            <a href="returns.php?role=<?php echo $userRole; ?>">Process Returns</a>
        <?php endif; ?>
    </nav>
    
    <div class="container">
        <h2>Welcome to the Library Management System</h2>
        
        <h3>Available Books</h3>
        <div class="book-list">
            <?php
            // Query to get available books
            $sql = "SELECT BookID, Title FROM Books WHERE Status = 'Available'";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo '<div class="book-card">';
                    echo '<h3>' . htmlspecialchars($row["Title"]) . '</h3>';
                    echo '<p>Status: Available</p>';
                    echo '<a href="borrow.php?id=' . $row["BookID"] . '&role=' . $userRole . '" class="btn">Borrow</a>';
                    echo '</div>';
                }
            } else {
                echo "<p>No available books at the moment.</p>";
            }
            ?>
        </div>
    </div>
</body>
</html>

<?php
// Close connection
$conn->close();
?>