<?php
// Database connection
$host = "localhost";
$user = "root";       
$password = ""; 
$dbname = "librarydb"; 

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add Book
if (isset($_POST['add'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $isbn = $_POST['isbn'];
    $category = $_POST['category'];
    $total = $_POST['total'];
    $available = $_POST['available'];

    $stmt = $conn->prepare("INSERT INTO Books (Title, Author, ISBN, Category, TotalCopies, AvailableCopies) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssii", $title, $author, $isbn, $category, $total, $available);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_books.php");
    exit();
}

// Delete Book
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM Books WHERE BookID=$id");
    header("Location: manage_books.php");
    exit();
}

// Update Book
if (isset($_POST['update'])) {
    $id = $_POST['book_id'];
    $title = $_POST['title'];
    $author = $_POST['author'];
    $isbn = $_POST['isbn'];
    $category = $_POST['category'];
    $total = $_POST['total'];
    $available = $_POST['available'];

    $stmt = $conn->prepare("UPDATE Books SET Title=?, Author=?, ISBN=?, Category=?, TotalCopies=?, AvailableCopies=? WHERE BookID=?");
    $stmt->bind_param("ssssiis", $title, $author, $isbn, $category, $total, $available, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_books.php");
    exit();
}

// Fetch all books
$books = $conn->query("SELECT * FROM Books");

// If editing
$editBook = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM Books WHERE BookID=$id");
    $editBook = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Books</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .nav {
            background: #1a237e;
            padding: 12px;
        }

        .nav a {
            color: white;
            margin-right: 20px;
            text-decoration: none;
            font-weight: bold;
        }

        .container {
            padding: 20px;
        }

        form input, select {
            padding: 8px;
            margin: 6px;
            width: 250px;
        }

        form button {
            padding: 8px 20px;
            background: #1a237e;
            color: white;
            border: none;
            cursor: pointer;
        }

        table {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
        }

        th {
            background: #3949ab;
            color: white;
        }

        .edit-btn, .delete-btn {
            padding: 6px 12px;
            color: white;
            text-decoration: none;
        }

        .edit-btn {
            background: #007bff;
        }

        .delete-btn {
            background: #c62828;
        }

        h2 {
            color: #1a237e;
        }
    </style>
</head>
<body>

<div class="nav">
    <a href="admindashboard.php">Admin Dashboard</a>
    <a href="manage_books.php">Manage Books</a>
    <a href="borrowed_books.php">📖 Borrowed & Returned</a>
    <a href="manage_users.php">👥 Manage Users</a>
</div>

<div class="container">
    <h2><?php echo $editBook ? 'Edit Book' : 'Add New Book'; ?></h2>
    <form method="post">
        <input type="hidden" name="book_id" value="<?php echo $editBook['BookID'] ?? ''; ?>">
        <input type="text" name="title" placeholder="Title" required value="<?php echo $editBook['Title'] ?? ''; ?>"><br>
        <input type="text" name="author" placeholder="Author" value="<?php echo $editBook['Author'] ?? ''; ?>"><br>
        <input type="text" name="isbn" placeholder="ISBN" value="<?php echo $editBook['ISBN'] ?? ''; ?>"><br>
        <input type="text" name="category" placeholder="Category" value="<?php echo $editBook['Category'] ?? ''; ?>"><br>
        <input type="number" name="total" placeholder="Total Copies" required value="<?php echo $editBook['TotalCopies'] ?? ''; ?>"><br>
        <input type="number" name="available" placeholder="Available Copies" required value="<?php echo $editBook['AvailableCopies'] ?? ''; ?>"><br>
        <button type="submit" name="<?php echo $editBook ? 'update' : 'add'; ?>">
            <?php echo $editBook ? 'Update Book' : 'Add Book'; ?>
        </button>
    </form>

    <h2>All Books</h2>
    <table>
        <tr>
            <th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Total</th><th>Available</th><th>Actions</th>
        </tr>
        <?php while ($row = $books->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['Title']); ?></td>
                <td><?php echo htmlspecialchars($row['Author']); ?></td>
                <td><?php echo htmlspecialchars($row['ISBN']); ?></td>
                <td><?php echo htmlspecialchars($row['Category']); ?></td>
                <td><?php echo $row['TotalCopies']; ?></td>
                <td><?php echo $row['AvailableCopies']; ?></td>
                <td>
                    <a href="?edit=<?php echo $row['BookID']; ?>" class="edit-btn">Edit</a>
                    <a href="?delete=<?php echo $row['BookID']; ?>" class="delete-btn" onclick="return confirm('Delete this book?');">Delete</a>
                </td>
            </tr>
        <?php } ?>
    </table>
</div>

</body>
</html>
