<?php
session_start();

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Member') {
    header("Location: login.php");
    exit();
}

include("connection.php");

$success = "";
$error = "";

// Handle multiple book borrowing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selected_books'])) {
    $userId = $_SESSION['UserID'];
    $borrowDate = date('Y-m-d'); // Current date
    $dueDate = date('Y-m-d', strtotime("+14 days")); // Due date

    $borrowedCount = 0;

    foreach ($_POST['selected_books'] as $bookId) {
        $stmt = $conn->prepare("CALL BorrowBook(?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $borrowDate, $dueDate, $bookId);
        
        if ($stmt->execute()) {
            $borrowedCount++;
        } else {
            $error = "Error borrowing book ID $bookId: " . $stmt->error;
        }

        $stmt->close();
    }

    if ($borrowedCount > 0) {
        $success = "$borrowedCount book(s) borrowed successfully!";
    } else {
        $error = "No books were borrowed. They may be unavailable.";
    }
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $stmt = $conn->prepare("SELECT * FROM Books WHERE AvailableCopies > 0 AND (Title LIKE ? OR Author LIKE ?)");
    $like = "%$search%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $books = $stmt->get_result();
    $stmt->close();
} else {
    $books = $conn->query("SELECT * FROM Books WHERE AvailableCopies > 0");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Borrow Books</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <span class="navbar-brand">Library System</span>
        <div class="collapse navbar-collapse justify-content-center">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="member_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="borrow_books.php">Borrow Book</a></li>
                <li class="nav-item"><a class="nav-link" href="return_books.php">Return Book</a></li>
                <li class="nav-item"><a class="nav-link" href="history.php">History</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="container my-5">
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-center text-primary mb-4">Borrow Books</h2>

            <?php if ($success): ?>
                <div class="alert alert-success text-center"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger text-center"><?= $error ?></div>
            <?php endif; ?>

            <!-- Search -->
            <form method="get" class="row g-3 mb-4">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control" placeholder="Search by title or author" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Search</button>
                </div>
            </form>

            <!-- Books Table -->
            <?php if ($books->num_rows > 0): ?>
                <form method="post">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">Select</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Author</th>
                                    <th scope="col">Available Copies</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($row = $books->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center"><input type="checkbox" name="selected_books[]" value="<?= $row['BookID'] ?>"></td>
                                    <td><?= htmlspecialchars($row['Title']) ?></td>
                                    <td><?= htmlspecialchars($row['Author']) ?></td>
                                    <td class="text-center"><?= $row['AvailableCopies'] ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">Borrow Selected Books</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-center text-muted">No books found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
