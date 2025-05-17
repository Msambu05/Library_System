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

// Fetch borrowing and return data
$sql = "
SELECT 
    br.BorrowID,
    u.FullName,
    b.Title,
    br.BorrowDate,
    br.DueDate,
    r.ReturnDate,
    r.FineAmount
FROM Borrowing br
JOIN Users u ON br.UserID = u.UserID
JOIN Books b ON br.BookID = b.BookID
LEFT JOIN Returns r ON br.BorrowID = r.BorrowID
ORDER BY br.BorrowDate DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Borrowed Books</title>
    <style>
        body { font-family: Arial; margin: 0; background: #f4f4f4; }
        .nav { background: #1a237e; padding: 12px; }
        .nav a { color: white; margin-right: 20px; text-decoration: none; font-weight: bold; }
        .container { padding: 20px; }
        h2 { color: #1a237e; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background: #3949ab; color: white; }
        .overdue { background-color: #ffcccc; }
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
    <h2>Borrowed & Returned Books</h2>
    <table>
        <tr>
            <th>User</th>
            <th>Book Title</th>
            <th>Borrow Date</th>
            <th>Due Date</th>
            <th>Actual Return</th>
            <th>Status</th>
            <th>Fine</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()): 
            $today = date('Y-m-d');
            $isReturned = !empty($row['ReturnDate']);
            $isOverdue = !$isReturned && $row['DueDate'] < $today;
        ?>
        <tr class="<?= $isOverdue ? 'overdue' : '' ?>">
            <td><?= htmlspecialchars($row['FullName']) ?></td>
            <td><?= htmlspecialchars($row['Title']) ?></td>
            <td><?= $row['BorrowDate'] ?></td>
            <td><?= $row['DueDate'] ?></td>
            <td><?= $isReturned ? $row['ReturnDate'] : 'Not returned' ?></td>
            <td><?= $isReturned ? 'Returned' : ($isOverdue ? 'Overdue' : 'Borrowed') ?></td>
            <td><?= $row['FineAmount'] ? 'R' . number_format($row['FineAmount'], 2) : '0.00' ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
