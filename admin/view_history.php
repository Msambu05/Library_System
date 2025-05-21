<?php
require_once '../config/connection.php';
require_once '../includes/auth_check.php';

// Verify admin role
if ($_SESSION['role'] !== 'Librarian') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

// Initialize variables
$userId = null;
$userInfo = null;
$borrowingHistory = [];
$message = '';

// Check if viewing specific user's history
if (isset($_GET['user_id'])) {
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    
    if ($userId) {
        try {
            // Get user information
            $stmt = $conn->prepare("SELECT UserID, FullName, Email FROM Users WHERE UserID = ?");
            $stmt->execute([$userId]);
            $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userInfo) {
                // Get borrowing history
                $stmt = $conn->prepare("
                    SELECT 
                        b.Title, 
                        b.Author, 
                        br.BorrowDate, 
                        br.DueDate, 
                        br.ReturnDate,
                        br.Status,
                        DATEDIFF(IFNULL(br.ReturnDate, NOW()), br.DueDate) AS DaysLate,
                        CASE 
                            WHEN br.Status = 'Returned' THEN 0
                            WHEN br.DueDate < NOW() THEN DATEDIFF(NOW(), br.DueDate)
                            ELSE 0
                        END AS CurrentDaysLate,
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
            } else {
                $message = "User not found.";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $message = "Error retrieving user history. Please try again.";
        }
    }
} else {
    // Get all recent borrowing activity
    try {
        $stmt = $conn->query("
            SELECT 
                u.UserID,
                u.FullName,
                b.Title, 
                b.Author, 
                br.BorrowDate, 
                br.DueDate, 
                br.ReturnDate,
                br.Status,
                DATEDIFF(IFNULL(br.ReturnDate, NOW()), br.DueDate) AS DaysLate,
                f.Amount AS FineAmount
            FROM Borrowing br
            JOIN Books b ON br.BookID = b.BookID
            JOIN Users u ON br.UserID = u.UserID
            LEFT JOIN Fines f ON br.BorrowID = f.BorrowID
            ORDER BY br.BorrowDate DESC
            LIMIT 100
        ");
        $borrowingHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $message = "Error retrieving borrowing history. Please try again.";
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
    <link href="../assets/css/admin.css" rel="stylesheet">
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
    <?php include '../includes/admin_nav.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar-admin.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <?= $userInfo ? "Borrowing History: " . htmlspecialchars($userInfo['FullName']) : "All Borrowing History" ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print
                            </button>
                            <a href="export_history.php?<?= $userId ? 'user_id='.$userId : '' ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i> Export
                            </a>
                        </div>
                        <?php if ($userInfo): ?>
                            <a href="mailto:<?= htmlspecialchars($userInfo['Email']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-envelope"></i> Contact User
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-8">
                                <label for="userSearch" class="form-label">Search User History</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="userSearch" name="search" placeholder="Search by name or email">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="statusFilter" class="form-label">Filter by Status</label>
                                <select class="form-select" id="statusFilter" onchange="filterTable()">
                                    <option value="all">All Records</option>
                                    <option value="active">Active Loans</option>
                                    <option value="overdue">Overdue Loans</option>
                                    <option value="returned">Returned</option>
                                    <option value="fines">With Fines</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- History Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="historyTable">
                                <thead>
                                    <tr>
                                        <?php if (!$userInfo): ?>
                                            <th>User</th>
                                        <?php endif; ?>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>Borrowed</th>
                                        <th>Due Date</th>
                                        <th>Returned</th>
                                        <th>Status</th>
                                        <th>Fine</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowingHistory as $record): ?>
                                        <?php
                                        $isLate = $record['DaysLate'] > 0;
                                        $isCurrentLate = $record['CurrentDaysLate'] > 0;
                                        $hasFine = !empty($record['FineAmount']);
                                        $rowClass = '';
                                        
                                        if ($isLate) {
                                            $rowClass = 'late-return';
                                        } elseif ($isCurrentLate) {
                                            $rowClass = 'current-late';
                                        }
                                        ?>
                                        <tr class="<?= $rowClass ?>">
                                            <?php if (!$userInfo): ?>
                                                <td>
                                                    <a href="?user_id=<?= $record['UserID'] ?>">
                                                        <?= htmlspecialchars($record['FullName']) ?>
                                                    </a>
                                                </td>
                                            <?php endif; ?>
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
                                                            Overdue (<?= $record['CurrentDaysLate'] ?> days)
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
                                            <td class="<?= $hasFine && $record['FineStatus'] === 'Paid' ? 'fine-paid' : '' ?>">
                                                <?= $hasFine ? '₹' . number_format($record['FineAmount'], 2) : '--' ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="book_details.php?id=<?= $record['BookID'] ?>" 
                                                       class="btn btn-outline-primary" title="View Book">
                                                        <i class="bi bi-book"></i>
                                                    </a>
                                                    <?php if ($record['Status'] === 'Active'): ?>
                                                        <a href="process_return.php?borrow_id=<?= $record['BorrowID'] ?>" 
                                                           class="btn btn-outline-success" title="Process Return">
                                                            <i class="bi bi-arrow-return-left"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($isLate || $isCurrentLate): ?>
                                                        <a href="assess_fine.php?borrow_id=<?= $record['BorrowID'] ?>" 
                                                           class="btn btn-outline-warning" title="Assess Fine">
                                                            <i class="bi bi-cash-stack"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($borrowingHistory)): ?>
                                        <tr>
                                            <td colspan="<?= $userInfo ? '8' : '9' ?>" class="text-center">
                                                No borrowing records found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <?php if ($userInfo && !empty($borrowingHistory)): ?>
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Borrowing Summary</h5>
                                    <?php
                                    $totalBorrowed = count($borrowingHistory);
                                    $totalLate = array_reduce($borrowingHistory, function($carry, $item) {
                                        return $carry + ($item['DaysLate'] > 0 ? 1 : 0);
                                    }, 0);
                                    $totalFines = array_reduce($borrowingHistory, function($carry, $item) {
                                        return $carry + ($item['FineAmount'] ?? 0);
                                    }, 0);
                                    ?>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Total Borrowed
                                            <span class="badge bg-primary rounded-pill"><?= $totalBorrowed ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Late Returns
                                            <span class="badge bg-danger rounded-pill"><?= $totalLate ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Total Fines
                                            <span class="badge bg-warning rounded-pill">₹<?= number_format($totalFines, 2) ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Borrowing Timeline</h5>
                                    <div style="height: 250px;">
                                        <canvas id="timelineChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Filter table by status
        function filterTable() {
            const filter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#historyTable tbody tr');
            
            rows.forEach(row => {
                const statusCell = row.querySelector('td:nth-child(<?= $userInfo ? 6 : 7 ?>)');
                const status = statusCell.textContent.toLowerCase();
                const hasFine = row.querySelector('td:nth-child(<?= $userInfo ? 7 : 8 ?>)').textContent !== '--';
                
                let showRow = false;
                
                switch(filter) {
                    case 'all':
                        showRow = true;
                        break;
                    case 'active':
                        showRow = status.includes('active');
                        break;
                    case 'overdue':
                        showRow = status.includes('overdue') || status.includes('late');
                        break;
                    case 'returned':
                        showRow = status.includes('returned');
                        break;
                    case 'fines':
                        showRow = hasFine;
                        break;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }
        
        // Initialize timeline chart if viewing user history
        <?php if ($userInfo && !empty($borrowingHistory)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('timelineChart').getContext('2d');
                
                // Prepare data for chart
                const months = [];
                const borrowCounts = [];
                const returnCounts = [];
                
                // Group by month (simplified example)
                const monthlyData = {};
                <?php foreach ($borrowingHistory as $record): ?>
                    const borrowDate = new Date('<?= $record['BorrowDate'] ?>');
                    const monthYear = borrowDate.toLocaleString('default', { month: 'short', year: 'numeric' });
                    
                    if (!monthlyData[monthYear]) {
                        monthlyData[monthYear] = { borrows: 0, returns: 0 };
                    }
                    monthlyData[monthYear].borrows++;
                    
                    <?php if ($record['ReturnDate']): ?>
                        const returnDate = new Date('<?= $record['ReturnDate'] ?>');
                        const returnMonthYear = returnDate.toLocaleString('default', { month: 'short', year: 'numeric' });
                        
                        if (!monthlyData[returnMonthYear]) {
                            monthlyData[returnMonthYear] = { borrows: 0, returns: 0 };
                        }
                        monthlyData[returnMonthYear].returns++;
                    <?php endif; ?>
                <?php endforeach; ?>
                
                // Sort months chronologically
                const sortedMonths = Object.keys(monthlyData).sort((a, b) => {
                    return new Date(a) - new Date(b);
                });
                
                // Prepare chart data
                sortedMonths.forEach(month => {
                    months.push(month);
                    borrowCounts.push(monthlyData[month].borrows);
                    returnCounts.push(monthlyData[month].returns);
                });
                
                // Create chart
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: months,
                        datasets: [
                            {
                                label: 'Books Borrowed',
                                data: borrowCounts,
                                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Books Returned',
                                data: returnCounts,
                                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Monthly Borrowing Activity'
                            }
                        }
                    }
                });
            });
        <?php endif; ?>
    </script>
</body>
</html>
