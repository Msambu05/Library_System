<?php
require_once '../config/connection.php';
require_once '../includes/auth_check.php';

// Verify member role
if ($_SESSION['role'] !== 'Member') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

// Initialize variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$books = [];
$categories = [];
$currentBorrowings = [];

// Get available categories
try {
    $categories = $conn->query("SELECT DISTINCT Category FROM Books WHERE AvailableCopies > 0")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Get current borrowings for the member
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM Borrowing 
        WHERE UserID = ? AND Status = 'Active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $currentBorrowings = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Get available books with search and filter
try {
    $sql = "SELECT * FROM Books WHERE AvailableCopies > 0";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (Title LIKE ? OR Author LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($categoryFilter)) {
        $sql .= " AND Category = ?";
        $params[] = $categoryFilter;
    }
    
    $sql .= " ORDER BY Title";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $message = "Error loading books. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - Library System</title>
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
                        <a class="nav-link active" href="member_dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="borrow_books.php"><i class="bi bi-book"></i> Borrow Books</a>
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
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h2>
                <p class="lead">Browse and manage your library books.</p>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title">Your Current Borrowings</h5>
                        <h1 class="display-4"><?= $currentBorrowings ?></h1>
                        <a href="borrow_books.php" class="btn btn-primary">Borrow More</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search Books</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by title or author" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="category" class="form-label">Filter by Category</label>
                        <select class="form-select" id="category" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" 
                                    <?= $category === $categoryFilter ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="member_dashboard.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Books List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Available Books</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($books)): ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php foreach ($books as $book): ?>
                            <div class="col">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($book['Title']) ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($book['Author']) ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">Category: <?= htmlspecialchars($book['Category']) ?></small><br>
                                            <small class="text-muted">ISBN: <?= htmlspecialchars($book['ISBN']) ?></small>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?= $book['AvailableCopies'] > 0 ? 'success' : 'danger' ?>">
                                                Available: <?= $book['AvailableCopies'] ?>
                                            </span>
                                            <a href="borrow_books.php?book_id=<?= $book['BookID'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-cart-plus"></i> Borrow
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No books found matching your criteria.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


