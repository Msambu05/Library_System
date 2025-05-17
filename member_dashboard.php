<?php
session_start();

// Check login and member role
if (!isset($_SESSION['RegID']) || strtolower($_SESSION['Role'] ?? '') !== 'member') {
    header("Location: login.php");
    exit();
}

include '../db/connection.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM Books WHERE AvailableCopies > 0";
if (!empty($search)) {
    $sql .= " AND (Title LIKE ? OR Author LIKE ? OR Category LIKE ?)";
    $stmt = $conn->prepare($sql);
    $param = "%" . $search . "%";
    $stmt->bind_param("sss", $param, $param, $param);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Dashboard - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-title {
            color: #007bff;
            font-weight: bold;
            margin-top: 20px;
        }

        .nav-tabs .nav-link.active {
            background-color: #007bff;
            color: white;
        }

        .table th {
            background-color: #343a40;
            color: white;
        }

        .search-bar input {
            border-radius: 0;
        }

        .card {
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="container mt-4">
<h2 class="dashboard-title text-center">📚 Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Member'); ?>!</h2>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs my-4">
        <li class="nav-item"><a class="nav-link active" href="member_dashboard.php">View Books</a></li>
        <li class="nav-item"><a class="nav-link" href="borrow_books.php">Borrow Books</a></li>
        <li class="nav-item"><a class="nav-link" href="return_books.php">Return Books</a></li>
        <li class="nav-item"><a class="nav-link" href="history.php">History</a></li>
        <li class="nav-item ms-auto"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
    </ul>

    <!-- Search Form -->
    <form method="get" class="search-bar mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by title, author or category" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <!-- Book List Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Available Books</h5>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>ISBN</th>
                        <th>Category</th>
                        <th>Available Copies</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['Title']); ?></td>
                                <td><?php echo htmlspecialchars($row['Author']); ?></td>
                                <td><?php echo htmlspecialchars($row['ISBN']); ?></td>
                                <td><?php echo htmlspecialchars($row['Category']); ?></td>
                                <td><?php echo $row['AvailableCopies']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No books found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
