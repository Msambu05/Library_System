<?php
require_once '../config/connection.php';
require_once '../includes/auth_check.php';

// Verify member role
if ($_SESSION['role'] !== 'Member') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

// Initialize variables
$userId = $_SESSION['user_id'];
$message = '';
$error = '';
$selectedBookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : null;
$books = [];

// Check current borrow limit
try {
    $maxBooks = (int)$conn->query("SELECT SettingValue FROM SystemSettings WHERE SettingKey = 'max_books_per_user'")->fetchColumn();
    $currentBorrowed = $conn->prepare("SELECT COUNT(*) FROM Borrowing WHERE UserID = ? AND Status = 'Active'");
    $currentBorrowed->execute([$userId]);
    $currentCount = $currentBorrowed->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "System error. Please try again later.";
}

// Handle book borrowing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow'])) {
    if ($currentCount >= $maxBooks) {
        $error = "You have reached your maximum borrowing limit of $maxBooks books.";
    } else {
        try {
            $bookIds = isset($_POST['selected_books']) ? $_POST['selected_books'] : [];
            
            if (empty($bookIds)) {
                $error = "Please select at least one book to borrow.";
            } else {
                $successCount = 0;
                
                foreach ($bookIds as $bookId) {
                    $bookId = (int)$bookId;
                    
                    // Check if book is available
                    $available = $conn->prepare("SELECT AvailableCopies FROM Books WHERE BookID = ?");
                    $available->execute([$bookId]);
                    $copies = $available->fetchColumn();
                    
                    if ($copies > 0) {
                        // Borrow the book
                        $stmt = $conn->prepare("
                            INSERT INTO Borrowing (UserID, BookID, BorrowDate, DueDate, Status)
                            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 'Active')
                        ");
                        $stmt->execute([$userId, $bookId]);
                        
                        // Update available copies
                        $conn->exec("UPDATE Books SET AvailableCopies = AvailableCopies - 1 WHERE BookID = $bookId");
                        
                        // Log activity
                        $conn->prepare("
                            INSERT INTO ActivityLog (UserID, ActivityType, Description)
                            VALUES (?, 'BORROW', ?)
                        ")->execute([$userId, "Borrowed book ID: $bookId"]);
                        
                        $successCount++;
                    }
                }
                
                if ($successCount > 0) {
                    $message = "Successfully borrowed $successCount book(s).";
                    header("Location: member_dashboard.php?success=" . urlencode($message));
                    exit();
                } else {
                    $error = "No books were borrowed. They may be unavailable.";
                }
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $error = "Error borrowing books. Please try again.";
        }
    }
}

// Get available books
try {
    $sql = "SELECT * FROM Books WHERE AvailableCopies > 0";
    
    if ($selectedBookId) {
        $sql .= " AND BookID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selectedBookId]);
    } else {
        $stmt = $conn->query($sql);
    }
    
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Error loading books. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Books - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/member.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="member_dashboard.php">Library System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="member_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="borrow_books.php"><i class="bi bi-book"></i> Borrow Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="return_books.php"><i class="bi bi-arrow-return-left"></i> Return Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php"><i class="bi bi-clock-history"></i> History</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">Borrow Books</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <strong>Borrowing Limit:</strong> You can borrow up to <?= $maxBooks ?> books at a time. 
                    You currently have <?= $currentCount ?> books borrowed.
                </div>
                
                <?php if (!empty($books)): ?>
                    <form method="post">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="50px">Select</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Category</th>
                                        <th>Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($books as $book): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_books[]" 
                                                       value="<?= $book['BookID'] ?>" 
                                                       <?= $selectedBookId && $book['BookID'] == $selectedBookId ? 'checked' : '' ?>>
                                            </td>
                                            <td><?= htmlspecialchars($book['Title']) ?></td>
                                            <td><?= htmlspecialchars($book['Author']) ?></td>
                                            <td><?= htmlspecialchars($book['Category']) ?></td>
                                            <td><?= $book['AvailableCopies'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" name="borrow" class="btn btn-success btn-lg">
                                <i class="bi bi-cart-check"></i> Borrow Selected Books
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        No books available for borrowing at this time.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-select if coming from single book link
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('book_id')) {
                document.querySelector('form').scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    </script>
</body>
</html>
