<?php
require_once '../config/connection.php';
require_once '../includes/auth_check.php';

// Verify admin role
if ($_SESSION['role'] !== 'Librarian') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

// Initialize variables
$reportData = [];
$reportType = $_GET['report'] ?? 'overdue';

// Generate reports based on type
try {
    switch ($reportType) {
        case 'overdue':
            $reportTitle = "Overdue Books Report";
            $reportData = $conn->query("
                SELECT b.Title, u.FullName, br.DueDate, 
                       DATEDIFF(NOW(), br.DueDate) AS DaysOverdue
                FROM Borrowing br
                JOIN Books b ON br.BookID = b.BookID
                JOIN Users u ON br.UserID = u.UserID
                WHERE br.Status = 'Active' AND br.DueDate < NOW()
                ORDER BY DaysOverdue DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'popular':
            $reportTitle = "Most Popular Books Report";
            $reportData = $conn->query("
                SELECT b.Title, b.Author, COUNT(br.BorrowID) AS BorrowCount
                FROM Books b
                LEFT JOIN Borrowing br ON b.BookID = br.BookID
                GROUP BY b.BookID
                ORDER BY BorrowCount DESC
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'members':
            $reportTitle = "Active Members Report";
            $reportData = $conn->query("
                SELECT u.UserID, u.FullName, u.Email, u.Phone,
                       COUNT(br.BorrowID) AS TotalBorrowed,
                       MAX(br.BorrowDate) AS LastBorrowed
                FROM Users u
                LEFT JOIN Borrowing br ON u.UserID = br.UserID
                WHERE u.Role = 'Member' AND u.IsActive = 1
                GROUP BY u.UserID
                ORDER BY TotalBorrowed DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'fines':
            $reportTitle = "Outstanding Fines Report";
            $reportData = $conn->query("
                SELECT u.FullName, u.Email, 
                       SUM(f.Amount) AS TotalFine,
                       COUNT(f.FineID) AS FineCount
                FROM Fines f
                JOIN Users u ON f.UserID = u.UserID
                WHERE f.Status = 'Pending'
                GROUP BY f.UserID
                HAVING TotalFine > 0
                ORDER BY TotalFine DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        default:
            $reportTitle = "Library Activity Report";
            $reportData = $conn->query("
                SELECT ActivityType, Description, Timestamp
                FROM ActivityLog
                ORDER BY Timestamp DESC
                LIMIT 50
            ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $message = "Error generating report. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1 class="h2">Library Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="reports.php?report=overdue" class="btn btn-sm btn-outline-secondary <?= $reportType === 'overdue' ? 'active' : '' ?>">
                                Overdue
                            </a>
                            <a href="reports.php?report=popular" class="btn btn-sm btn-outline-secondary <?= $reportType === 'popular' ? 'active' : '' ?>">
                                Popular Books
                            </a>
                            <a href="reports.php?report=members" class="btn btn-sm btn-outline-secondary <?= $reportType === 'members' ? 'active' : '' ?>">
                                Active Members
                            </a>
                            <a href="reports.php?report=fines" class="btn btn-sm btn-outline-secondary <?= $reportType === 'fines' ? 'active' : '' ?>">
                                Outstanding Fines
                            </a>
                        </div>
                        <a href="export_report.php?type=<?= $reportType ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download"></i> Export
                        </a>
                    </div>
                </div>

                <!-- Report Title -->
                <h3 class="mb-4"><?= $reportTitle ?></h3>

                <!-- Report Content -->
                <div class="card mb-4">
                    <div class="card-body">
                        <?php if (!empty($reportData)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <?php foreach (array_keys($reportData[0]) as $column): ?>
                                                <th><?= ucwords(str_replace('_', ' ', $column)) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $value): ?>
                                                    <td>
                                                        <?php 
                                                            if (is_numeric($value) && strpos($value, '.') !== false) {
                                                                echo number_format($value, 2);
                                                            } elseif (strtotime($value) !== false && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                                                                echo date('M j, Y', strtotime($value));
                                                            } else {
                                                                echo htmlspecialchars($value);
                                                            }
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Chart for popular books report -->
                            <?php if ($reportType === 'popular'): ?>
                                <div class="mt-5">
                                    <canvas id="popularBooksChart" height="300"></canvas>
                                    <script>
                                        const ctx = document.getElementById('popularBooksChart').getContext('2d');
                                        const chart = new Chart(ctx, {
                                            type: 'bar',
                                            data: {
                                                labels: <?= json_encode(array_column($reportData, 'Title')) ?>,
                                                datasets: [{
                                                    label: 'Number of Borrows',
                                                    data: <?= json_encode(array_column($reportData, 'BorrowCount')) ?>,
                                                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                                    borderColor: 'rgba(54, 162, 235, 1)',
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                plugins: {
                                                    legend: {
                                                        display: false
                                                    },
                                                    title: {
                                                        display: true,
                                                        text: 'Most Borrowed Books'
                                                    }
                                                },
                                                scales: {
                                                    y: {
                                                        beginAtZero: true,
                                                        ticks: {
                                                            stepSize: 1
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    </script>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-info">No data available for this report.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card text-white bg-info mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Total Books</h5>
                                        <?php
                                            $totalBooks = $conn->query("SELECT COUNT(*) FROM Books")->fetchColumn();
                                        ?>
                                        <h2 class="mb-0"><?= $totalBooks ?></h2>
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
                                        <h5 class="card-title">Active Members</h5>
                                        <?php
                                            $activeMembers = $conn->query("SELECT COUNT(*) FROM Users WHERE Role = 'Member' AND IsActive = 1")->fetchColumn();
                                        ?>
                                        <h2 class="mb-0"><?= $activeMembers ?></h2>
                                    </div>
                                    <i class="bi bi-people fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Overdue Books</h5>
                                        <?php
                                            $overdueBooks = $conn->query("SELECT COUNT(*) FROM Borrowing WHERE Status = 'Active' AND DueDate < NOW()")->fetchColumn();
                                        ?>
                                        <h2 class="mb-0"><?= $overdueBooks ?></h2>
                                    </div>
                                    <i class="bi bi-exclamation-triangle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>