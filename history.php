<?php
session_start();
include("connection.php");

// Check if user is logged in
if (!isset($_SESSION['RegID']) || $_SESSION['Role'] !== 'Member') {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['RegID'];

// Fetch user's borrowing history (including returned books)
$stmt = $conn->prepare("
    SELECT bk.Title, bk.Author, br.BorrowDate, br.DueDate, r.ReturnDate, r.FineAmount
    FROM Borrowing br
    JOIN Books bk ON br.BookID = bk.BookID
    LEFT JOIN Returns r ON br.BorrowID = r.BorrowID
    WHERE br.UserID = ?
    ORDER BY br.BorrowDate DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$history = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Borrowing History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .container {
            width: 90%;
            max-width: 900px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #007bff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: center;
            border: 1px solid #ddd;
        }
        th {
            background-color: #343a40;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .nav-tabs {
            margin-bottom: 20px;
        }
        .success, .error {
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs my-4">
    <li class="nav-item"><a class="nav-link" href="member_dashboard.php">View Books</a></li>
    <li class="nav-item"><a class="nav-link" href="borrow_books.php">Borrow Books</a></li>
    <li class="nav-item"><a class="nav-link" href="return_books.php">Return Books</a></li>
    <li class="nav-item"><a class="nav-link active" href="history.php">History</a></li>
    <li class="nav-item ms-auto"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
</ul>

<div class="container">
    <h2>Your Borrowing History</h2>

    <?php if ($history->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Borrowed On</th>
                    <th>Due Date</th>
                    <th>Returned On</th>
                    <th>Fine Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $history->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Title']) ?></td>
                        <td><?= htmlspecialchars($row['Author']) ?></td>
                        <td><?= $row['BorrowDate'] ?></td>
                        <td><?= $row['DueDate'] ?></td>
                        <td><?= $row['ReturnDate'] ? $row['ReturnDate'] : 'Not Returned Yet' ?></td>
                        <td>
                            <?= $row['FineAmount'] > 0 ? '₹ ' . number_format($row['FineAmount'], 2) : 'No Fine' ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align:center;">You have no borrowing history yet.</p>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
