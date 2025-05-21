<?php
/**
 * Common functions for the Library Management System
 */

// Prevent direct access to this file
if (!defined('BASE_PATH')) {
    die('Direct access to this file is not allowed.');
}

/**
 * Log activity to the database
 * 
 * @param PDO $conn Database connection
 * @param string $activityType Type of activity (LOGIN, LOGOUT, BORROW, etc.)
 * @param string $description Description of the activity
 * @param int|null $userId ID of the user performing the activity (null for system activities)
 * @return bool True on success, false on failure
 */
function logActivity($conn, $activityType, $description, $userId = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO ActivityLog (UserID, ActivityType, Description, IPAddress)
            VALUES (?, ?, ?, ?)
        ");
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt->execute([$userId, $activityType, $description, $ipAddress]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get system setting from database
 * 
 * @param PDO $conn Database connection
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getSystemSetting($conn, $key, $default = null) {
    try {
        $stmt = $conn->prepare("SELECT SettingValue FROM SystemSettings WHERE SettingKey = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['SettingValue'];
        }
        
        return $default;
    } catch (PDOException $e) {
        error_log("Error getting system setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Calculate fine for overdue book
 * 
 * @param PDO $conn Database connection
 * @param string $dueDate Due date
 * @param string|null $returnDate Return date or current date if null
 * @return float Fine amount
 */
function calculateFine($conn, $dueDate, $returnDate = null) {
    $finePerDay = (float)getSystemSetting($conn, 'fine_per_day', 0.50);
    
    // Use current date if return date not provided
    if ($returnDate === null) {
        $returnDate = date('Y-m-d H:i:s');
    }
    
    $dueDateTime = new DateTime($dueDate);
    $returnDateTime = new DateTime($returnDate);
    
    // No fine if returned on or before due date
    if ($returnDateTime <= $dueDateTime) {
        return 0;
    }
    
    $daysLate = $returnDateTime->diff($dueDateTime)->days;
    return $daysLate * $finePerDay;
}

/**
 * Check if user has reached maximum allowed books
 * 
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return bool True if user has reached limit, false otherwise
 */
function hasReachedBorrowLimit($conn, $userId) {
    try {
        $maxBooks = (int)getSystemSetting($conn, 'max_books_per_user', 5);
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS current_borrowed 
            FROM Borrowing 
            WHERE UserID = ? AND Status = 'Active'
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['current_borrowed'] >= $maxBooks;
    } catch (PDOException $e) {
        error_log("Error checking borrow limit: " . $e->getMessage());
        return true; // Assume limit reached on error
    }
}

/**
 * Format date for display
 * 
 * @param string $date Date to format
 * @param string $format Format to use
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Generate a secure random token
 * 
 * @param int $length Length of token
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if a user has any outstanding fines
 * 
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return bool True if user has outstanding fines, false otherwise
 */
function hasOutstandingFines($conn, $userId) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM Fines 
            WHERE UserID = ? AND Status IN ('Pending', 'Partially Paid')
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking outstanding fines: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's total outstanding fine amount
 * 
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return float Total fine amount
 */
function getTotalFines($conn, $userId) {
    try {
        $stmt = $conn->prepare("
            SELECT SUM(
                CASE 
                    WHEN Status = 'Partially Paid' THEN Amount - COALESCE(PaidAmount, 0)
                    WHEN Status = 'Pending' THEN Amount
                    ELSE 0
                END
            ) AS total_due
            FROM Fines 
            WHERE UserID = ? AND Status IN ('Pending', 'Partially Paid')
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetchColumn();
        return $result ? (float)$result : 0;
    } catch (PDOException $e) {
        error_log("Error calculating total fines: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get all active categories
 * 
 * @param PDO $conn Database connection
 * @return array List of categories
 */
function getAllCategories($conn) {
    try {
        $categories = $conn->query("SELECT CategoryID, Name FROM Categories ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
        return $categories;
    } catch (PDOException $e) {
        error_log("Error getting categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Display alert message
 * 
 * @param string $message Alert message
 * @param string $type Alert type (success, danger, warning, info)
 * @return string HTML for alert
 */
function displayAlert($message, $type = 'info') {
    return "
    <div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
        {$message}
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>";
}