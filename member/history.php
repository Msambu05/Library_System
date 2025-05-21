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
$borrowingHistory = [];

// Get borrowing history
try {
    $stmt = $conn->prepare("
        SELECT 
            b.Title, 
            b.Author, 
            br.BorrowDate, 
            br.DueDate, 
            br.ReturnDate,
            br.Status,
            DATEDIFF(IFNULL(br.ReturnDate, NOW()), br.DueDate) AS DaysLate,
            f.Amount AS FineAmount,
            f.Status AS FineStatus
        FROM Borrowing br
        JOIN Books b ON br.BookID = b.BookID
        LEFT JOIN Fines f ON br.BorrowID = f.BorrowID
        WHERE br.UserID = ?
        ORDER BY br.BorrowDate DESC
    ");
    $stmt->execute([$userId]);
    $borrowingHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $message = "Error retrieving your borrowing history. Please try again.";
}

// Calculate statistics
$totalBorrowed = count($borrowingHistory);
$totalLateReturns = 0;
$totalFines = 0;

foreach ($borrowingHistory as $record) {
    if ($record['DaysLate'] > 0) {
        $totalLateReturns++;
    }
    if ($record['FineAmount']) {
        $totalFines += $record['FineAmount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing History - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/member.css" rel="stylesheet">
    <style>
        .late-return {
            background-color: rgba(220, 53, 69, 0.1);
        }
        .current-late {
            background-color: rgba(255, 193, 7, 0.2);
        }
        .fine-paid {
            text-decoration: line-through;
            color: #6c757d;
        }
        .badge-late {
            background-color: #dc3545;
        }
        .badge-current-late {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-active {
            background-color: #28a745;
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
                        <a class="nav-link" href="return_books.php"><i class="bi bi-arrow-return-left"></i> Return Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="history.php"><i class="bi bi-clock-history"></i> History</a>
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
                <h2 class="mb-0">Your Borrowing History</h2>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Total Borrowed</h5>
                                        <h2 class="mb-0"><?= $totalBorrowed ?></h2>
                                    </div>
                                    <i class="bi bi-book fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Late Returns</h5>
                                        <h2 class="mb-0"><?= $totalLateReturns ?></h2>
                                    </div>
                                    <i class="bi bi-exclamation-triangle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-danger mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Total Fines</h5>
                                        <h2 class="mb-0">₹<?= number_format($totalFines, 2) ?></h2>
                                    </div>
                                    <i class="bi bi-cash-stack fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- History Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Borrowed</th>
                                <th>Due Date</th>
                                <th>Returned</th>
                                <th>Status</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrowingHistory as $record): 
                                $isLate = $record['DaysLate'] > 0;
                                $isCurrentLate = $record['Status'] === 'Active' && $record['DueDate'] < date('Y-m-d');
                                $rowClass = '';
                                
                                if ($isLate) {
                                    $rowClass = 'late-return';
                                } elseif ($isCurrentLate) {
                                    $rowClass = 'current-late';
                                }
                            ?>
                                <tr class="<?= $rowClass ?>">
                                    <td><?= htmlspecialchars($record['Title']) ?></td>
                                    <td><?= htmlspecialchars($record['Author']) ?></td>
                                    <td><?= date('M j, Y', strtotime($record['BorrowDate'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($record['DueDate'])) ?></td>
                                    <td>
                                        <?= $record['ReturnDate'] ? date('M j, Y', strtotime($record['ReturnDate'])) : '--' ?>
                                    </td>
                                    <td>
                                        <?php if ($record['Status'] === 'Active'): ?>
                                            <?php if ($isCurrentLate): ?>
                                                <span class="badge badge-current-late">
                                                    Overdue (<?= $record['DaysLate'] ?> days)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-active">
                                                    Active
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($isLate): ?>
                                            <span class="badge badge-late">
                                                Late (<?= $record['DaysLate'] ?> days)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                Returned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= $record['FineStatus'] === 'Paid' ? 'fine-paid' : '' ?>">
                                        <?= $record['FineAmount'] ? '₹' . number_format($record['FineAmount'], 2) : '--' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($borrowingHistory)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No borrowing history found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>