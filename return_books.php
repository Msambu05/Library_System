<?php
session_start();
include("connection.php");

// Check if user is logged in
if (!isset($_SESSION['RegID']) || $_SESSION['Role'] !== 'Member') {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['RegID'];
$success = "";
$error = "";

// Handle return submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_books'])) {
    foreach ($_POST['return_books'] as $borrowId) {
        $stmt = $conn->prepare("CALL ReturnBook(?)");
        $stmt->bind_param("i", $borrowId);
        
        if ($stmt->execute()) {
            $success = "Selected books have been returned successfully.";
        } else {
            $error = "Error returning book ID $borrowId: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Fetch books currently borrowed
$stmt = $conn->prepare("
    SELECT br.BorrowID, bk.Title, bk.Author, br.BorrowDate, br.DueDate
    FROM Borrowing br
    JOIN Books bk ON br.BookID = bk.BookID
    WHERE br.UserID = ? AND br.Returned = 0
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$books = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Return Books</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; font-family: Arial, sans-serif; }
        .container { width: 90%; max-width: 900px; margin: 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #007bff; }
        .success, .error { text-align: center; font-weight: bold; margin: 10px 0; }
        .success { color: green; }
        .error { color: red; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: center; border: 1px solid #ddd; }
        th { background-color: #343a40; color: white; }
        button { margin: 20px auto; display: block; background: #28a745; color: white; padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; }
        button:hover { background: #218838; }
    </style>
</head>
<body>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs my-4">
    <li class="nav-item"><a class="nav-link" href="member_dashboard.php">View Books</a></li>
    <li class="nav-item"><a class="nav-link" href="borrow_books.php">Borrow Books</a></li>
    <li class="nav-item"><a class="nav-link active" href="return_books.php">Return Books</a></li>
    <li class="nav-item"><a class="nav-link" href="history.php">History</a></li>
    <li class="nav-item ms-auto"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
</ul>

<div class="container">
    <h2>Return Borrowed Books</h2>

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($books->num_rows > 0): ?>
        <form method="post">
            <table>
                <tr>
                    <th>Select</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Borrowed On</th>
                    <th>Due Date</th>
                </tr>
                <?php while ($row = $books->fetch_assoc()): ?>
                    <tr>
                        <td><input type="checkbox" name="return_books[]" value="<?= $row['BorrowID'] ?>"></td>
                        <td><?= htmlspecialchars($row['Title']) ?></td>
                        <td><?= htmlspecialchars($row['Author']) ?></td>
                        <td><?= $row['BorrowDate'] ?></td>
                        <td><?= $row['DueDate'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <button type="submit">Return Selected Books</button>
        </form>
    <?php else: ?>
        <p style="text-align:center;">You have no books to return.</p>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
