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

// Handle book returns
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_books'])) {
    try {
        $returnedCount = 0;
        
        foreach ($_POST['return_books'] as $borrowId) {
            $borrowId = (int)$borrowId;
            
            // Verify this borrow record belongs to the current user
            $verify = $conn->prepare("SELECT UserID FROM Borrowing WHERE BorrowID = ?");
            $verify->execute([$borrowId]);
            $result = $verify->fetch();
            
            if ($result && $result['UserID'] == $userId) {
                // Process return
                $stmt = $conn->prepare("
                    UPDATE Borrowing 
                    SET Status = 'Returned', ReturnDate = NOW() 
                    WHERE BorrowID = ?
                ");
                $stmt->execute([$borrowId]);
                
                // Update book availability
                $bookId = $conn->query("SELECT BookID FROM Borrowing WHERE BorrowID = $borrowId")->fetchColumn();
                $conn->exec("UPDATE Books SET AvailableCopies = AvailableCopies + 1 WHERE BookID = $bookId");
                
                // Log activity
                $conn->prepare("
                    INSERT INTO ActivityLog (UserID, ActivityType, Description)
                    VALUES (?, 'RETURN', ?)
                ")->execute([$userId, "Returned book from borrow ID: $borrowId"]);
                
                $returnedCount++;
            }
        }
        
        if ($returnedCount > 0) {
            $message = "Successfully returned $returnedCount book(s).";
            header("Location: member_dashboard.php?success=" . urlencode($message));
            exit();
        } else {
            $error = "No books were returned. Please try again.";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "Error returning books. Please try again.";
    }
}

// Get currently borrowed books
try {
    $stmt = $conn->prepare("
        SELECT b.BorrowID, bk.Title, bk.Author, b.BorrowDate, b.DueDate,
               DATEDIFF(NOW(), b.DueDate) AS DaysLate
        FROM Borrowing b
        JOIN Books bk ON b.BookID = bk.BookID
        WHERE b.UserID = ? AND b.Status = 'Active'
        ORDER BY b.DueDate ASC
    ");
    $stmt->execute([$userId]);
    $borrowedBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Error loading borrowed books. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Books - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/member.css" rel="stylesheet">
    <style>
        .overdue {
            background-color: rgba(220, 53, 69, 0.1);
        }
        .due-soon {
            background-color: rgba(255, 193, 7, 0.1);
        }
        .badge-overdue {
            background-color: #dc3545;
        }
        .badge-due-soon {
            background-color: #ffc107;
            color: #212529;
        }
    </style>
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
                        <a class="nav-link" href="borrow_books.php"><i class="bi bi-book"></i> Borrow Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="return_books.php"><i class="bi bi-arrow-return-left"></i> Return Books</a>
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
                <h2 class="mb-0">Return Books</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if (!empty($borrowedBooks)): ?>
                    <form method="post">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="50px">Select</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Borrowed Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowedBooks as $book): 
                                        $isOverdue = $book['DaysLate'] > 0;
                                        $isDueSoon = !$isOverdue && $book['DaysLate'] >= -3;
                                        $rowClass = $isOverdue ? 'overdue' : ($isDueSoon ? 'due-soon' : '');
                                    ?>
                                        <tr class="<?= $rowClass ?>">
                                            <td>
                                                <input type="checkbox" name="return_books[]" value="<?= $book['BorrowID'] ?>">
                                            </td>
                                            <td><?= htmlspecialchars($book['Title']) ?></td>
                                            <td><?= htmlspecialchars($book['Author']) ?></td>
                                            <td><?= date('M j, Y', strtotime($book['BorrowDate'])) ?></td>
                                            <td><?= date('M j, Y', strtotime($book['DueDate'])) ?></td>
                                            <td>
                                                <?php if ($isOverdue): ?>
                                                    <span class="badge badge-overdue">
                                                        Overdue (<?= $book['DaysLate'] ?> days)
                                                    </span>
                                                <?php elseif ($isDueSoon): ?>
                                                    <span class="badge badge-due-soon">
                                                        Due soon
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">
                                                        On time
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i> Return Selected Books
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        You currently have no books to return.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all checkbox functionality
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.createElement('input');
            selectAllCheckbox.type = 'checkbox';
            selectAllCheckbox.id = 'selectAll';
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="return_books[]"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });
            
            const firstTh = document.querySelector('thead tr th:first-child');
            firstTh.appendChild(selectAllCheckbox);
            firstTh.style.position = 'relative';
            
            const label = document.createElement('label');
            label.htmlFor = 'selectAll';
            label.style.position = 'absolute';
            label.style.width = '100%';
            label.style.height = '100%';
            label.style.left = '0';
            label.style.top = '0';
            label.style.cursor = 'pointer';
            firstTh.appendChild(label);
        });
    </script>
</body>
</html>
