<?php
require_once '../config/connection.php';
require_once '../includes/auth_check.php';

// Verify admin role
if ($_SESSION['role'] !== 'Librarian') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

// Get book statistics
try {
    $bookStats = $conn->query("
        SELECT 
            COUNT(*) AS total_books,
            SUM(AvailableCopies) AS available_books,
            SUM(TotalCopies - AvailableCopies) AS borrowed_books,
            (SELECT COUNT(*) FROM Users WHERE Role = 'Member') AS total_members,
            (SELECT COUNT(*) FROM Borrowing WHERE Status = 'Active') AS active_loans
        FROM Books
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $bookStats = ['total_books' => 0, 'available_books' => 0, 'borrowed_books' => 0];
}

// Get recent activity
try {
    $recentActivity = $conn->query("
        SELECT ActivityType, Description, Timestamp 
        FROM ActivityLog 
        ORDER BY Timestamp DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $recentActivity = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <?php include '../includes/admin_nav.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar-admin.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard Overview</h1>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Total Books</h5>
                                        <h2 class="mb-0"><?= $bookStats['total_books'] ?></h2>
                                    </div>
                                    <i class="bi bi-book fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Available Books</h5>
                                        <h2 class="mb-0"><?= $bookStats['available_books'] ?></h2>
                                    </div>
                                    <i class="bi bi-bookmark-check fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Active Loans</h5>
                                        <h2 class="mb-0"><?= $bookStats['active_loans'] ?></h2>
                                    </div>
                                    <i class="bi bi-arrow-left-right fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Details</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivity as $activity): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($activity['ActivityType']) ?></td>
                                        <td><?= htmlspecialchars($activity['Description']) ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($activity['Timestamp'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentActivity)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No recent activity</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Overdue Books -->
                <div class="card">
                    <div class="card-header">
                        <h5>Overdue Books</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $overdueBooks = $conn->query("
                                SELECT b.Title, u.FullName, br.DueDate, 
                                       DATEDIFF(NOW(), br.DueDate) AS DaysOverdue
                                FROM Borrowing br
                                JOIN Books b ON br.BookID = b.BookID
                                JOIN Users u ON br.UserID = u.UserID
                                WHERE br.Status = 'Active' AND br.DueDate < NOW()
                                ORDER BY DaysOverdue DESC
                                LIMIT 10
                            ")->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            error_log("Database error: " . $e->getMessage());
                            $overdueBooks = [];
                        }
                        ?>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Borrower</th>
                                        <th>Due Date</th>
                                        <th>Days Overdue</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdueBooks as $book): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($book['Title']) ?></td>
                                        <td><?= htmlspecialchars($book['FullName']) ?></td>
                                        <td><?= date('M j, Y', strtotime($book['DueDate'])) ?></td>
                                        <td><span class="badge bg-danger"><?= $book['DaysOverdue'] ?> days</span></td>
                                        <td>
                                            <a href="send_reminder.php?user=<?= $book['UserID'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-envelope"></i> Send Reminder
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($overdueBooks)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No overdue books</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
