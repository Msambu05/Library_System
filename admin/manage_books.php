<?php
require_once '../config/connection.php';
require_once '../includes/auth_check.php';

// Verify admin role
if ($_SESSION['role'] !== 'Librarian') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

// Initialize variables
$message = '';
$book = null;
$categories = [];

// Get all categories
try {
    $categories = $conn->query("SELECT CategoryID, Name FROM Categories")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $message = "Error loading categories. Please try again.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid form submission.";
    } else {
        // Sanitize inputs
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $author = filter_input(INPUT_POST, 'author', FILTER_SANITIZE_STRING);
        $isbn = filter_input(INPUT_POST, 'isbn', FILTER_SANITIZE_STRING);
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $total_copies = filter_input(INPUT_POST, 'total_copies', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $available_copies = filter_input(INPUT_POST, 'available_copies', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $publication_year = filter_input(INPUT_POST, 'publication_year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1000, 'max_range' => date('Y')]]);
        $publisher = filter_input(INPUT_POST, 'publisher', FILTER_SANITIZE_STRING);
        $shelf_location = filter_input(INPUT_POST, 'shelf_location', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (empty($title) || empty($author) || empty($isbn) || $total_copies === false || $available_copies === false) {
            $message = "Please fill all required fields with valid data.";
        } elseif ($available_copies > $total_copies) {
            $message = "Available copies cannot exceed total copies.";
        } else {
            try {
                if (isset($_POST['update'])) {
                    // Update existing book
                    $book_id = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
                    
                    $stmt = $conn->prepare("
                        UPDATE Books SET 
                            Title = ?, 
                            Author = ?, 
                            ISBN = ?, 
                            CategoryID = ?, 
                            TotalCopies = ?, 
                            AvailableCopies = ?,
                            Description = ?,
                            PublicationYear = ?,
                            Publisher = ?,
                            ShelfLocation = ?
                        WHERE BookID = ?
                    ");
                    
                    $stmt->execute([
                        $title, $author, $isbn, $category_id, $total_copies, 
                        $available_copies, $description, $publication_year,
                        $publisher, $shelf_location, $book_id
                    ]);
                    
                    $message = "Book updated successfully!";
                    
                    // Log activity
                    $conn->prepare("
                        INSERT INTO ActivityLog (UserID, ActivityType, Description)
                        VALUES (?, 'UPDATE', ?)
                    ")->execute([$_SESSION['user_id'], "Updated book: $title"]);
                    
                } else {
                    // Add new book
                    $stmt = $conn->prepare("
                        INSERT INTO Books (
                            Title, Author, ISBN, CategoryID, TotalCopies, 
                            AvailableCopies, Description, PublicationYear,
                            Publisher, ShelfLocation, AddedBy
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $title, $author, $isbn, $category_id, $total_copies, 
                        $available_copies, $description, $publication_year,
                        $publisher, $shelf_location, $_SESSION['user_id']
                    ]);
                    
                    $message = "Book added successfully!";
                    
                    // Log activity
                    $conn->prepare("
                        INSERT INTO ActivityLog (UserID, ActivityType, Description)
                        VALUES (?, 'INSERT', ?)
                    ")->execute([$_SESSION['user_id'], "Added new book: $title"]);
                }
                
                // Clear form after successful submission
                unset($_POST);
                header("Location: manage_books.php?success=" . urlencode($message));
                exit();
                
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $message = "Error saving book. Please try again.";
                
                if ($e->getCode() == 23000) { // Duplicate entry
                    $message = "A book with this ISBN already exists.";
                }
            }
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $book_id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    
    if ($book_id) {
        try {
            // Check if book is currently borrowed
            $check = $conn->prepare("SELECT COUNT(*) FROM Borrowing WHERE BookID = ? AND Status = 'Active'");
            $check->execute([$book_id]);
            $borrowed = $check->fetchColumn();
            
            if ($borrowed > 0) {
                $message = "Cannot delete book that is currently borrowed.";
            } else {
                // Get book title for logging
                $title = $conn->query("SELECT Title FROM Books WHERE BookID = $book_id")->fetchColumn();
                
                // Delete book
                $conn->exec("DELETE FROM Books WHERE BookID = $book_id");
                
                // Log activity
                $conn->prepare("
                    INSERT INTO ActivityLog (UserID, ActivityType, Description)
                    VALUES (?, 'DELETE', ?)
                ")->execute([$_SESSION['user_id'], "Deleted book: $title"]);
                
                $message = "Book deleted successfully!";
                header("Location: manage_books.php?success=" . urlencode($message));
                exit();
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $message = "Error deleting book. Please try again.";
        }
    }
}

// Handle edit action
if (isset($_GET['edit'])) {
    $book_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    
    if ($book_id) {
        try {
            $book = $conn->query("SELECT * FROM Books WHERE BookID = $book_id")->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $message = "Error loading book details. Please try again.";
        }
    }
}

// Get all books with category names
try {
    $books = $conn->query("
        SELECT b.*, c.Name AS CategoryName 
        FROM Books b
        LEFT JOIN Categories c ON b.CategoryID = c.CategoryID
        ORDER BY b.Title
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $message = "Error loading books. Please try again.";
    $books = [];
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Library System</title>
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
                    <h1 class="h2">Manage Books</h1>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
                <?php elseif (!empty($message)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <!-- Book Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><?= isset($book) ? 'Edit Book' : 'Add New Book' ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <?php if (isset($book)): ?>
                                <input type="hidden" name="book_id" value="<?= $book['BookID'] ?>">
                            <?php endif; ?>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label">Title*</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?= htmlspecialchars($book['Title'] ?? $_POST['title'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="author" class="form-label">Author*</label>
                                    <input type="text" class="form-control" id="author" name="author" 
                                           value="<?= htmlspecialchars($book['Author'] ?? $_POST['author'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="isbn" class="form-label">ISBN*</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" 
                                           value="<?= htmlspecialchars($book['ISBN'] ?? $_POST['isbn'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">-- Select Category --</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['CategoryID'] ?>" 
                                                <?= (isset($book) && $book['CategoryID'] == $category['CategoryID']) || 
                                                    (isset($_POST['category_id']) && $_POST['category_id'] == $category['CategoryID']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['Name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="total_copies" class="form-label">Total Copies*</label>
                                    <input type="number" class="form-control" id="total_copies" name="total_copies" min="1" 
                                           value="<?= htmlspecialchars($book['TotalCopies'] ?? $_POST['total_copies'] ?? 1) ?>" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="available_copies" class="form-label">Available Copies*</label>
                                    <input type="number" class="form-control" id="available_copies" name="available_copies" min="0" 
                                           value="<?= htmlspecialchars($book['AvailableCopies'] ?? $_POST['available_copies'] ?? 1) ?>" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="publication_year" class="form-label">Publication Year</label>
                                    <input type="number" class="form-control" id="publication_year" name="publication_year" 
                                           min="1000" max="<?= date('Y') ?>"
                                           value="<?= htmlspecialchars($book['PublicationYear'] ?? $_POST['publication_year'] ?? '') ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="publisher" name="publisher" 
                                           value="<?= htmlspecialchars($book['Publisher'] ?? $_POST['publisher'] ?? '') ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="shelf_location" class="form-label">Shelf Location</label>
                                    <input type="text" class="form-control" id="shelf_location" name="shelf_location" 
                                           value="<?= htmlspecialchars($book['ShelfLocation'] ?? $_POST['shelf_location'] ?? '') ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= 
                                        htmlspecialchars($book['Description'] ?? $_POST['description'] ?? '') 
                                    ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="<?= isset($book) ? 'update' : 'add' ?>" class="btn btn-primary">
                                        <?= isset($book) ? 'Update Book' : 'Add Book' ?>
                                    </button>
                                    <?php if (isset($book)): ?>
                                        <a href="manage_books.php" class="btn btn-outline-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Books Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Books</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>ISBN</th>
                                        <th>Category</th>
                                        <th>Total</th>
                                        <th>Available</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($books as $bookItem): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($bookItem['Title']) ?></td>
                                        <td><?= htmlspecialchars($bookItem['Author']) ?></td>
                                        <td><?= htmlspecialchars($bookItem['ISBN']) ?></td>
                                        <td><?= htmlspecialchars($bookItem['CategoryName'] ?? 'Uncategorized') ?></td>
                                        <td><?= $bookItem['TotalCopies'] ?></td>
                                        <td>
                                            <span class="<?= $bookItem['AvailableCopies'] == 0 ? 'text-danger' : '' ?>">
                                                <?= $bookItem['AvailableCopies'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?edit=<?= $bookItem['BookID'] ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?delete=<?= $bookItem['BookID'] ?>" class="btn btn-outline-danger" title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this book?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($books)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No books found</td>
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
    <script>
        // Auto-set available copies when total copies changes
        document.getElementById('total_copies').addEventListener('change', function() {
            const total = parseInt(this.value);
            const available = document.getElementById('available_copies');
            const currentAvailable = parseInt(available.value);
            
            if (isNaN(total) || total < 1) {
                this.value = 1;
                return;
            }
            
            if (isNaN(currentAvailable)) {
                available.value = total;
            } else if (currentAvailable > total) {
                available.value = total;
            }
        });
    </script>
</body>
</html>
