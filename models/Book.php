<?php
/**
 * Book Model
 * 
 * Handles database operations related to books in the library system
 */
class Book
{
    private $conn;
    
    /**
     * Book properties
     */
    public $id;
    public $title;
    public $author;
    public $isbn;
    public $categoryId;
    public $totalCopies;
    public $availableCopies;
    public $description;
    public $publicationYear;
    public $publisher;
    public $shelfLocation;
    public $addedBy;
    
    /**
     * Constructor
     * 
     * @param PDO $dbConnection Database connection object
     */
    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }
    
    /**
     * Get all books with category information
     * 
     * @return array Array of books with their details
     */
    public function getAllBooks()
    {
        try {
            $query = "
                SELECT b.*, c.Name AS CategoryName 
                FROM Books b
                LEFT JOIN Categories c ON b.CategoryID = c.CategoryID
                ORDER BY b.Title
            ";
            
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a single book by ID
     * 
     * @param int $bookId Book ID to retrieve
     * @return array|bool Book data or false if not found
     */
    public function getBookById($bookId)
    {
        try {
            $query = "SELECT * FROM Books WHERE BookID = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$bookId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add a new book to the database
     * 
     * @param array $bookData Book data to insert
     * @return bool True on success, false on failure
     */
    public function addBook($bookData)
    {
        try {
            $query = "
                INSERT INTO Books (
                    Title, Author, ISBN, CategoryID, TotalCopies, 
                    AvailableCopies, Description, PublicationYear,
                    Publisher, ShelfLocation, AddedBy
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->conn->prepare($query);
            
            $result = $stmt->execute([
                $bookData['title'],
                $bookData['author'],
                $bookData['isbn'],
                $bookData['category_id'],
                $bookData['total_copies'],
                $bookData['available_copies'],
                $bookData['description'],
                $bookData['publication_year'],
                $bookData['publisher'],
                $bookData['shelf_location'],
                $bookData['added_by']
            ]);
            
            // Log activity if successful
            if ($result) {
                $this->logActivity(
                    $bookData['added_by'], 
                    'INSERT', 
                    "Added new book: {$bookData['title']}"
                );
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update an existing book
     * 
     * @param int $bookId ID of the book to update
     * @param array $bookData Updated book data
     * @return bool True on success, false on failure
     */
    public function updateBook($bookId, $bookData)
    {
        try {
            $query = "
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
            ";
            
            $stmt = $this->conn->prepare($query);
            
            $result = $stmt->execute([
                $bookData['title'],
                $bookData['author'],
                $bookData['isbn'],
                $bookData['category_id'],
                $bookData['total_copies'],
                $bookData['available_copies'],
                $bookData['description'],
                $bookData['publication_year'],
                $bookData['publisher'],
                $bookData['shelf_location'],
                $bookId
            ]);
            
            // Log activity if successful
            if ($result) {
                $this->logActivity(
                    $bookData['user_id'], 
                    'UPDATE', 
                    "Updated book: {$bookData['title']}"
                );
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a book by ID
     * 
     * @param int $bookId ID of the book to delete
     * @param int $userId ID of the user performing the deletion
     * @return bool True on success, false on failure
     */
    public function deleteBook($bookId, $userId)
    {
        try {
            // Check if book is currently borrowed
            $checkQuery = "SELECT COUNT(*) FROM Borrowing WHERE BookID = ? AND Status = 'Active'";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$bookId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                return 'borrowed'; // Book is currently borrowed
            }
            
            // Get book title for logging
            $titleQuery = "SELECT Title FROM Books WHERE BookID = ?";
            $titleStmt = $this->conn->prepare($titleQuery);
            $titleStmt->execute([$bookId]);
            $title = $titleStmt->fetchColumn();
            
            // Delete the book
            $deleteQuery = "DELETE FROM Books WHERE BookID = ?";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $result = $deleteStmt->execute([$bookId]);
            
            // Log activity if successful
            if ($result) {
                $this->logActivity($userId, 'DELETE', "Deleted book: $title");
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Search for books based on criteria
     * 
     * @param string $keyword Search keyword
     * @param int $categoryId Optional category filter
     * @return array Matching books
     */
    public function searchBooks($keyword, $categoryId = null)
    {
        try {
            $params = [];
            $query = "
                SELECT b.*, c.Name AS CategoryName 
                FROM Books b
                LEFT JOIN Categories c ON b.CategoryID = c.CategoryID
                WHERE (
                    b.Title LIKE ? OR 
                    b.Author LIKE ? OR
                    b.ISBN LIKE ?
                )
            ";
            
            $searchPattern = "%$keyword%";
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            
            if ($categoryId) {
                $query .= " AND b.CategoryID = ?";
                $params[] = $categoryId;
            }
            
            $query .= " ORDER BY b.Title";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all categories
     * 
     * @return array List of all categories
     */
    public function getAllCategories()
    {
        try {
            $query = "SELECT CategoryID, Name FROM Categories";
            $stmt = $this->conn->query($query);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if book is available for borrowing
     * 
     * @param int $bookId Book ID to check
     * @return bool True if available, false if not
     */
    public function isAvailable($bookId)
    {
        try {
            $query = "SELECT AvailableCopies FROM Books WHERE BookID = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$bookId]);
            
            return ($stmt->fetchColumn() > 0);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update book availability after borrowing/returning
     * 
     * @param int $bookId Book ID to update
     * @param int $change Amount to change (positive for returns, negative for borrows)
     * @return bool True on success, false on failure
     */
    public function updateAvailability($bookId, $change)
    {
        try {
            $query = "
                UPDATE Books 
                SET AvailableCopies = AvailableCopies + ?
                WHERE BookID = ?
            ";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$change, $bookId]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log an activity in the ActivityLog table
     * 
     * @param int $userId User ID performing the action
     * @param string $activityType Type of activity (INSERT, UPDATE, DELETE)
     * @param string $description Description of the activity
     * @return bool True on success, false on failure
     */
    private function logActivity($userId, $activityType, $description)
    {
        try {
            $query = "
                INSERT INTO ActivityLog (UserID, ActivityType, Description)
                VALUES (?, ?, ?)
            ";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$userId, $activityType, $description]);
        } catch (PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
            return false;
        }
    }
}