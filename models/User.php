
connection.php  
 PDO::ERRMODE_EXCEPTION,         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         PDO::ATTR_EMULATE_PREPARES => false,     ];          $conn = new PDO($dsn, DB_USER, DB_PASS, $options);          // Set timezone if needed     $conn->exec("SET time_zone = '+00:00'");      } catch (PDOException $e) {     // Log error securely     error_log("Database connection failed: " . $e->getMessage());          // Don't expose errors to users     die("System maintenance in progress. Please try again later."); }     ?>  

constants.php //no code

Edit
It looks like you've shared a portion of a PHP connection file for database access. This appears to be the middle or end of a PDO connection setup, but it's missing some important parts of the code. I don't see the beginning of the file where you would typically define constants like DB_USER, DB_PASS, etc.

Would you like me to help you complete this database connection file? I could create a proper connection.php file with the necessary constants (which you mentioned are missing from constants.php) and ensure your database connection is set up securely.





<?php session_start(); require '../config/connection.php'; $message = ''; $formData = [ 'username' => '', 'email' => '', 'full_name' => '', 'phone' => '', 'role' => 'Member' ]; if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Sanitize inputs $formData = [

pasted

does this help?

Edit
Yes, this helps a lot! I can see you've shared a full registration page for a library system. Now I have a better understanding of what you're working with.

Based on the code you provided, it looks like you're building a library management system with user registration functionality. The code includes form handling, validation, and database operations using PDO.

I notice that your initial snippet was showing the end of a database connection file, but it was missing the beginning part with your database credentials. Looking at your registration code, it's importing a connection file (require '../config/connection.php').

Let me help you complete your connection.php file to properly connect to your database. Here's what a complete version would look like:


connection.php
Code 
And for the constants.php file that's referenced in your connection.php:


constants.php
Code 
These two files work together to establish a secure database connection for your library system. Here's what each file does:

constants.php:
Contains sensitive database credentials
Should be stored outside of your web root directory for security
You'll need to update the values with your actual database information
connection.php:
Creates a PDO connection object using the constants
Sets up important security options like error handling and prepared statements
Handles connection errors gracefully without exposing sensitive information
Your registration page looks well-structured with good form validation and secure password handling. The connection system follows PHP best practices with:

PDO for database access
Prepared statements to prevent SQL injection
Password hashing for security
Error handling that doesn't expose sensitive information
Would you like me to explain any specific part of the code in more detail or help with any other aspects of your library system?





<?php require_once '../config/connection.php'; require_once '../includes/auth_check.php'; // Verify admin role if ($_SESSION['role'] !== 'Librarian') { header("Location: ../auth/unauthorized.php"); exit(); } // Initialize variables $message = ''; $book = null; $categories = []

pasted


<?php require_once '../config/connection.php'; require_once '../includes/auth_check.php'; // Verify admin role if ($_SESSION['role'] !== 'Librarian') { header("Location: ../auth/unauthorized.php"); exit(); } // Initialize variables $reportData = []; $reportType = $_GET['report'

pasted

take a look and provide (models)
Book.php
report.php
Transaction.php
User.php

Edit
I see you've shared more code snippets from your library management system. Now you're looking for model files to organize your code better using an object-oriented approach. I'll create these model files for you based on the code you've shared.

Let me create the model files that will help organize your code and separate database logic from presentation:


Book.php
Code 

User.php
Code 

Drafting artifact...
 




You’re almost out of usage - your limits will reset at 3:00 AM
Continue

<?php
/**
 * User Model
 * 
 * Handles database operations related to users in the library system
 */
class User
{
    private $conn;
    
    /**
     * User properties
     */
    public $id;
    public $username;
    public $email;
    public $fullName;
    public $phone;
    public $role;
    public $isActive;
    public $registrationDate;
    
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
     * Get user by ID
     * 
     * @param int $userId User ID to retrieve
     * @return array|bool User data or false if not found
     */
    public function getUserById($userId)
    {
        try {
            $query = "SELECT * FROM Users WHERE UserID = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$userId]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                // Map database fields to object properties
                $this->id = $user['UserID'];
                $this->username = $user['Username'];
                $this->email = $user['Email'];
                $this->fullName = $user['FullName'];
                $this->phone = $user['Phone'];
                $this->role = $user['Role'];
                $this->isActive = $user['IsActive'];
                $this->registrationDate = $user['RegistrationDate'];
                
                return $user;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by username
     * 
     * @param string $username Username to retrieve
     * @return array|bool User data or false if not found
     */
    public function getUserByUsername($username)
    {
        try {
            $query = "SELECT * FROM Users WHERE Username = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$username]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                // Map database fields to object properties
                $this->id = $user['UserID'];
                $this->username = $user['Username'];
                $this->email = $user['Email'];
                $this->fullName = $user['FullName'];
                $this->phone = $user['Phone'];
                $this->role = $user['Role'];
                $this->isActive = $user['IsActive'];
                $this->registrationDate = $user['RegistrationDate'];
                
                return $user;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Register a new user
     * 
     * @param array $userData User data to register
     * @return bool|string True on success, error message on failure
     */
    public function register($userData)
    {
        try {
            // Check if username or email exists
            $checkQuery = "SELECT UserID FROM Users WHERE Username = ? OR Email = ?";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$userData['username'], $userData['email']]);
            
            if ($checkStmt->fetch()) {
                return "Username or email already exists.";
            }
            
            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert new user
            $query = "
                INSERT INTO Users 
                (Username, PasswordHash, Email, FullName, Phone, Role, RegistrationDate, IsActive) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
            ";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([
                $userData['username'],
                $passwordHash,
                $userData['email'],
                $userData['full_name'],
                $userData['phone'],
                $userData['role']
            ]);
            
            if ($result) {
                // Get the new user ID
                $this->id = $this->conn->lastInsertId();
                $this->username = $userData['username'];
                $this->email = $userData['email'];
                $this->fullName = $userData['full_name'];
                $this->phone = $userData['phone'];
                $this->role = $userData['role'];
                $this->isActive = 1;
                
                return true;
            }
            
            return "Registration failed. Please try again.";
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return "System error. Please try again later.";
        }
    }
    
    /**
     * Authenticate a user login
     * 
     * @param string $username Username to authenticate
     * @param string $password Password to verify
     * @return array|bool User data on success, false on failure
     */
    public function login($username, $password)
    {
        try {
            $query = "SELECT * FROM Users WHERE Username = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$username]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['PasswordHash'])) {
                // If account is inactive
                if (!$user['IsActive']) {
                    return 'inactive';
                }
                
                // Map database fields to object properties
                $this->id = $user['UserID'];
                $this->username = $user['Username'];
                $this->email = $user['Email'];
                $this->fullName = $user['FullName'];
                $this->phone = $user['Phone'];
                $this->role = $user['Role'];
                $this->isActive = $user['IsActive'];
                $this->registrationDate = $user['RegistrationDate'];
                
                // Log the login activity
                $this->logActivity($user['UserID'], 'LOGIN', "User logged in: {$user['Username']}");
                
                return $user;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user profile
     * 
     * @param int $userId ID of user to update
     * @param array $userData Updated user data
     * @return bool True on success, false on failure
     */
    public function updateProfile($userId, $userData)
    {
        try {
            $query = "
                UPDATE Users SET 
                    Email = ?, 
                    FullName = ?, 
                    Phone = ?
                WHERE UserID = ?
            ";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([
                $userData['email'],
                $userData['full_name'],
                $userData['phone'],
                $userId
            ]);
            
            if ($result) {
                // Update object properties
                $this->email = $userData['email'];
                $this->fullName = $userData['full_name'];
                $this->phone = $userData['phone'];
                
                // Log activity
                $this->logActivity($userId, 'UPDATE', "User updated profile");
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change user password
     * 
     * @param int $userId ID of user
     * @param string $currentPassword Current password to verify
     * @param string $newPassword New password to set
     * @return bool|string True on success, error message on failure
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        try {
            // Get current password hash
            $passwordQuery = "SELECT PasswordHash FROM Users WHERE UserID = ?";
            $passwordStmt = $this->conn->prepare($passwordQuery);
            $passwordStmt->execute([$userId]);
            $currentHash = $passwordStmt->fetchColumn();
            
            // Verify current password
            if (!password_verify($currentPassword, $currentHash)) {
                return "Current password is incorrect.";
            }
            
            // Hash new password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $updateQuery = "UPDATE Users SET PasswordHash = ? WHERE UserID = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $result = $updateStmt->execute([$newHash, $userId]);
            
            if ($result) {
                // Log activity
                $this->logActivity($userId, 'UPDATE', "User changed password");
                return true;
            }
            
            return "Failed to update password. Please try again.";
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return "System error. Please try again later.";
        }
    }
    
    /**
     * Get all users (for admin)
     * 
     * @param string $role Filter by role (optional)
     * @return array List of users
     */
    public function getAllUsers($role = null)
    {
        try {
            $query = "SELECT * FROM Users";
            $params = [];
            
            if ($role) {
                $query .= " WHERE Role = ?";
                $params[] = $role;
            }
            
            $query .= " ORDER BY FullName";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active members with borrowing stats
     * 
     * @return array List of active members with stats
     */
    public function getActiveMembers()
    {
        try {
            $query = "
                SELECT u.UserID, u.FullName, u.Email, u.Phone,
                       COUNT(br.BorrowID) AS TotalBorrowed,
                       MAX(br.BorrowDate) AS LastBorrowed
                FROM Users u
                LEFT JOIN Borrowing br ON u.UserID = br.UserID
                WHERE u.Role = 'Member' AND u.IsActive = 1
                GROUP BY u.UserID
                ORDER BY TotalBorrowed DESC
            ";
            
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set user account status (active/inactive)
     * 
     * @param int $userId ID of user to update
     * @param bool $isActive Active status (true/false)
     * @return bool True on success, false on failure
     */
    public function setAccountStatus($userId, $isActive)
    {
        try {
            // Check if there are active borrowings for this user
            if (!$isActive) {
                $checkQuery = "SELECT COUNT(*) FROM Borrowing WHERE UserID = ? AND Status = 'Active'";
                $checkStmt = $this->conn->prepare($checkQuery);
                $checkStmt->execute([$userId]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    return 'active_borrowings';
                }
            }
            
            $query = "UPDATE Users SET IsActive = ? WHERE UserID = ?";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$isActive ? 1 : 0, $userId]);
            
            if ($result) {
                // Log activity
                $action = $isActive ? "activated" : "deactivated";
                $this->logActivity($_SESSION['user_id'], 'UPDATE', "User account $action: $userId");
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log an activity in the ActivityLog table
     * 
     * @param int $userId User ID performing the action
     * @param string $activityType Type of activity (LOGIN, UPDATE, etc.)
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
