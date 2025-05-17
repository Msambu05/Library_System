<?php
// Database connection
$host = "localhost";
$user = "root";       
$password = ""; 
$dbname = "librarydb";   

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Book stats
$bookStats = $conn->query("SELECT 
    COUNT(*) AS total_books,
    SUM(AvailableCopies) AS available_books,
    SUM(TotalCopies - AvailableCopies) AS borrowed_books
 FROM Books")->fetch_assoc();

// Book list
$bookList = $conn->query("SELECT * FROM Books");

// Current borrowings
$borrowList = $conn->query("SELECT Borrowing.*, Users.FullName, Books.Title
 FROM Borrowing
 JOIN Users ON Borrowing.UserID = Users.UserID
 JOIN Books ON Borrowing.BookID = Books.BookID
 WHERE Returned = FALSE
 ORDER BY DueDate ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Library</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }

        .header {
            background-color: #1a237e;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .nav-tabs {
            background-color: #283593;
            overflow: hidden;
        }

        .nav-tabs a {
            float: left;
            display: block;
            color: white;
            text-align: center;
            padding: 14px 20px;
            text-decoration: none;
            font-weight: bold;
        }

        .nav-tabs a:hover {
            background-color: #3949ab;
        }

        .dashboard {
            padding: 20px;
        }

        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
        }

        .card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            width: 30%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .card h2 {
            margin: 10px 0;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #3949ab;
            color: white;
        }

        .section-title {
            font-size: 20px;
            color: #1a237e;
            margin: 20px 0 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Library Admin Dashboard</h1>
    </div>

    <!-- Top Tab Navigation -->
    <div class="nav-tabs">
        <a href="admindashboard.php">📊 Dashboard</a>
        <a href="manage_books.php">📚 Manage Books</a>
        <a href="borrowed_books.php">📖 Borrowed & Returned</a>
        <a href="manage_users.php">👥 Manage Users</a>
    </div>

    <div class="dashboard">
        <!-- Stats Cards -->
        <div class="stats">
            <div class="card">
                <h3>Total Books</h3>
                <h2><?php echo $bookStats['total_books']; ?></h2>
            </div>
            <div class="card">
                <h3>Available</h3>
                <h2><?php echo $bookStats['available_books']; ?></h2>
            </div>
            <div class="card">
                <h3>Borrowed</h3>
                <h2><?php echo $bookStats['borrowed_books']; ?></h2>
            </div>
        </div>

        <!-- Book List -->
        <div>
            <h2 class="section-title">Book List</h2>
            <table>
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>ISBN</th>
                    <th>Category</th>
                    <th>Total Copies</th>
                    <th>Available</th>
                </tr>
                <?php while ($book = $bookList->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($book['Title']); ?></td>
                        <td><?php echo htmlspecialchars($book['Author']); ?></td>
                        <td><?php echo htmlspecialchars($book['ISBN']); ?></td>
                        <td><?php echo htmlspecialchars($book['Category']); ?></td>
                        <td><?php echo $book['TotalCopies']; ?></td>
                        <td><?php echo $book['AvailableCopies']; ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>

        <!-- Current Borrowings -->
        <div>
            <h2 class="section-title">Current Borrowings</h2>
            <table>
                <tr>
                    <th>Member</th>
                    <th>Book Title</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                </tr>
                <?php while ($borrow = $borrowList->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($borrow['FullName']); ?></td>
                        <td><?php echo htmlspecialchars($borrow['Title']); ?></td>
                        <td><?php echo $borrow['BorrowDate']; ?></td>
                        <td><?php echo $borrow['DueDate']; ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>
</html>
