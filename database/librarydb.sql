-- Database: librarydb
DROP DATABASE IF EXISTS librarydb;
CREATE DATABASE librarydb;
USE librarydb;

-- =============================================
-- TABLES FOR USER MANAGEMENT
-- =============================================

-- Users Table (main user storage)
CREATE TABLE Users (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Username VARCHAR(50) UNIQUE NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    FullName VARCHAR(100) NOT NULL,
    Email VARCHAR(100) UNIQUE NOT NULL,
    Phone VARCHAR(20),
    Address TEXT,
    DateOfBirth DATE,
    Role ENUM('Librarian', 'Member') NOT NULL DEFAULT 'Member',
    IsActive BOOLEAN DEFAULT TRUE,
    RegistrationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    LastLogin TIMESTAMP NULL,
    ResetToken VARCHAR(255) NULL,
    ResetTokenExpiry TIMESTAMP NULL,
    INDEX idx_role (Role),
    INDEX idx_email (Email),
    INDEX idx_active (IsActive)
) ENGINE=InnoDB;

-- Password Reset Tokens (separate table for security)
CREATE TABLE PasswordResets (
    ResetID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    Token VARCHAR(255) NOT NULL,
    Expiry TIMESTAMP NOT NULL,
    Used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE,
    INDEX idx_token (Token),
    INDEX idx_expiry (Expiry)
) ENGINE=InnoDB;

-- RegistrationRequests Table (for approval workflow)
CREATE TABLE RegistrationRequests (
    RequestID INT AUTO_INCREMENT PRIMARY KEY,
    FullName VARCHAR(100) NOT NULL,
    Email VARCHAR(100) UNIQUE NOT NULL,
    Phone VARCHAR(20),
    Address TEXT,
    DateOfBirth DATE,
    RequestDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    ProcessedBy INT NULL,
    ProcessedDate TIMESTAMP NULL,
    FOREIGN KEY (ProcessedBy) REFERENCES Users(UserID) ON DELETE SET NULL,
    INDEX idx_status (Status)
) ENGINE=InnoDB;

-- =============================================
-- TABLES FOR BOOK MANAGEMENT
-- =============================================

-- Categories Table (for book classification)
CREATE TABLE Categories (
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(50) NOT NULL,
    Description TEXT,
    UNIQUE INDEX idx_name (Name)
) ENGINE=InnoDB;

-- Books Table (enhanced)
CREATE TABLE Books (
    BookID INT AUTO_INCREMENT PRIMARY KEY,
    ISBN VARCHAR(20) UNIQUE NOT NULL,
    Title VARCHAR(200) NOT NULL,
    Author VARCHAR(100) NOT NULL,
    Publisher VARCHAR(100),
    PublicationYear INT,
    CategoryID INT,
    TotalCopies INT NOT NULL DEFAULT 1,
    AvailableCopies INT NOT NULL DEFAULT 1,
    ShelfLocation VARCHAR(20),
    Description TEXT,
    CoverImage VARCHAR(255),
    AddedBy INT, -- Librarian who added the book
    DateAdded TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CategoryID) REFERENCES Categories(CategoryID) ON DELETE SET NULL,
    FOREIGN KEY (AddedBy) REFERENCES Users(UserID) ON DELETE SET NULL,
    INDEX idx_title (Title),
    INDEX idx_author (Author),
    INDEX idx_isbn (ISBN),
    FULLTEXT INDEX ft_search (Title, Author, Description)
) ENGINE=InnoDB;

-- =============================================
-- TABLES FOR BORROWING AND RETURNS
-- =============================================

-- Borrowing Table (with enhanced return tracking)
CREATE TABLE Borrowing (
    BorrowID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    BookID INT NOT NULL,
    BorrowDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    DueDate DATETIME NOT NULL,
    ReturnDate DATETIME NULL,
    ReturnedBy INT NULL, -- Librarian who processed return
    Status ENUM('Active', 'Returned', 'Overdue', 'Lost') DEFAULT 'Active',
    ReturnStateBorrowed ENUM('New', 'Good', 'Fair', 'Poor') DEFAULT 'Good',
    ReturnStateReturned ENUM('New', 'Good', 'Fair', 'Poor', 'Damaged', 'Lost') NULL,
    Notes TEXT,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE,
    FOREIGN KEY (BookID) REFERENCES Books(BookID) ON DELETE CASCADE,
    FOREIGN KEY (ReturnedBy) REFERENCES Users(UserID) ON DELETE SET NULL,
    INDEX idx_user (UserID),
    INDEX idx_book (BookID),
    INDEX idx_due_date (DueDate),
    INDEX idx_status (Status)
) ENGINE=InnoDB;

-- Returns Table (detailed return information)
CREATE TABLE Returns (
    ReturnID INT AUTO_INCREMENT PRIMARY KEY,
    BorrowID INT NOT NULL,
    ProcessedBy INT NOT NULL, -- Librarian who processed return
    ReturnDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ReturnState ENUM('New', 'Good', 'Fair', 'Poor', 'Damaged', 'Lost') NOT NULL,
    FineAssessed DECIMAL(10,2) DEFAULT 0.00,
    Notes TEXT,
    FOREIGN KEY (BorrowID) REFERENCES Borrowing(BorrowID) ON DELETE CASCADE,
    FOREIGN KEY (ProcessedBy) REFERENCES Users(UserID) ON DELETE CASCADE,
    INDEX idx_return_date (ReturnDate)
) ENGINE=InnoDB;

-- Fines Table (with payment tracking)
CREATE TABLE Fines (
    FineID INT AUTO_INCREMENT PRIMARY KEY,
    BorrowID INT NOT NULL,
    UserID INT NOT NULL,
    Amount DECIMAL(10,2) NOT NULL,
    Reason ENUM('Overdue', 'Damage', 'Lost') NOT NULL,
    IssuedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PaidDate DATETIME NULL,
    PaidAmount DECIMAL(10,2) NULL,
    Status ENUM('Pending', 'Partially Paid', 'Paid', 'Waived') DEFAULT 'Pending',
    FOREIGN KEY (BorrowID) REFERENCES Borrowing(BorrowID) ON DELETE CASCADE,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE,
    INDEX idx_status (Status),
    INDEX idx_user (UserID)
) ENGINE=InnoDB;

-- =============================================
-- ADDITIONAL FUNCTIONALITY TABLES
-- =============================================

-- Reservations Table
CREATE TABLE Reservations (
    ReservationID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    BookID INT NOT NULL,
    ReservationDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ExpiryDate DATETIME NOT NULL,
    Status ENUM('Pending', 'Fulfilled', 'Cancelled', 'Expired') DEFAULT 'Pending',
    NotificationSent BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE,
    FOREIGN KEY (BookID) REFERENCES Books(BookID) ON DELETE CASCADE,
    INDEX idx_user_book (UserID, BookID),
    INDEX idx_status (Status),
    INDEX idx_expiry (ExpiryDate)
) ENGINE=InnoDB;

-- Activity Log
CREATE TABLE ActivityLog (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NULL,
    ActivityType ENUM('LOGIN', 'LOGOUT', 'BORROW', 'RETURN', 'FINE', 'RESERVE', 'REGISTER', 'SYSTEM') NOT NULL,
    Description TEXT,
    IPAddress VARCHAR(45),
    Timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE SET NULL,
    INDEX idx_activity_type (ActivityType),
    INDEX idx_timestamp (Timestamp)
) ENGINE=InnoDB;

-- System Settings Table
CREATE TABLE SystemSettings (
    SettingID INT AUTO_INCREMENT PRIMARY KEY,
    SettingKey VARCHAR(50) UNIQUE NOT NULL,
    SettingValue TEXT NOT NULL,
    Description TEXT,
    LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- SAMPLE DATA INSERTION
-- =============================================

-- Insert Categories
INSERT INTO Categories (Name, Description) VALUES
('Fiction', 'Novels and short stories'),
('Science', 'Scientific books and journals'),
('History', 'Historical accounts and analyses'),
('Technology', 'Computer science and IT related books'),
('Biography', 'Autobiographies and biographies');

-- Insert Users (passwords are hashed versions of 'password123')
INSERT INTO Users (Username, PasswordHash, FullName, Email, Phone, Role, Address, DateOfBirth) VALUES
('Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin@library.com', '1234567890', 'Librarian', '123 Library St, City', '1980-01-15'),
('Msambu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'msambu@example.com', '0987654321', 'Member', '456 Reader Ave, Town', '1995-05-20'),
('Mawandla', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'mawandla@example.com', '1122334455', 'Member', '789 Book Rd, Village', '1988-11-30'),
('Seluleko', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Robert Johnson', 'seluleko@example.com', '5566778899', 'Librarian', '321 Shelf Lane, County', '1975-07-10');

-- Insert Registration Requests
INSERT INTO RegistrationRequests (FullName, Email, Phone, Address, DateOfBirth, Status, ProcessedBy, ProcessedDate) VALUES
('Alice Brown', 'alice@example.com', '3344556677', '101 Novel Street, Town', '1992-03-25', 'Approved', 1, NOW()),
('Bob White', 'bob@example.com', '4455667788', '202 Chapter Road, City', '1987-09-12', 'Pending', NULL, NULL);

-- Insert Books
INSERT INTO Books (ISBN, Title, Author, Publisher, PublicationYear, CategoryID, TotalCopies, AvailableCopies, ShelfLocation, Description, AddedBy) VALUES
('978-0061120084', 'To Kill a Mockingbird', 'Harper Lee', 'HarperCollins', 1960, 1, 5, 3, 'FIC-001', 'A classic novel about racial injustice in the American South', 1),
('978-0451524935', '1984', 'George Orwell', 'Signet Classics', 1949, 1, 3, 2, 'FIC-002', 'Dystopian novel about totalitarianism', 1),
('978-0743273565', 'The Great Gatsby', 'F. Scott Fitzgerald', 'Scribner', 1925, 1, 4, 4, 'FIC-003', 'Novel about the American Dream in the 1920s', 4),
('978-0553418026', 'The Martian', 'Andy Weir', 'Broadway Books', 2014, 2, 2, 1, 'SCI-001', 'Science fiction about an astronaut stranded on Mars', 4);

-- Insert Borrowing Records
INSERT INTO Borrowing (UserID, BookID, BorrowDate, DueDate, ReturnDate, ReturnedBy, Status, ReturnStateBorrowed, ReturnStateReturned) VALUES
(2, 1, '2023-05-01 10:00:00', '2023-05-15 10:00:00', '2023-05-14 15:30:00', 1, 'Returned', 'Good', 'Good'),
(2, 4, '2023-05-10 14:30:00', '2023-05-24 14:30:00', NULL, NULL, 'Active', 'Good', NULL),
(3, 2, '2023-05-05 11:15:00', '2023-05-19 11:15:00', '2023-05-20 10:30:00', 4, 'Returned', 'Good', 'Fair');

-- Insert Returns Records
INSERT INTO Returns (BorrowID, ProcessedBy, ReturnDate, ReturnState, FineAssessed) VALUES
(1, 1, '2023-05-14 15:30:00', 'Good', 0.00),
(3, 4, '2023-05-20 10:30:00', 'Fair', 0.50);

-- Insert Fines
INSERT INTO Fines (BorrowID, UserID, Amount, Reason, Status) VALUES
(3, 3, 0.50, 'Damage', 'Paid');

-- Insert Reservations
INSERT INTO Reservations (UserID, BookID, ReservationDate, ExpiryDate, Status) VALUES
(3, 1, '2023-05-20 10:00:00', '2023-05-23 10:00:00', 'Pending'),
(2, 3, '2023-05-18 14:00:00', '2023-05-21 14:00:00', 'Fulfilled');

-- Insert System Settings
INSERT INTO SystemSettings (SettingKey, SettingValue, Description) VALUES
('loan_period_days', '14', 'Number of days books can be borrowed'),
('fine_per_day', '0.50', 'Fine amount per day for overdue books'),
('max_books_per_user', '5', 'Maximum number of books a user can borrow at once'),
('reservation_expiry_days', '3', 'Number of days a reservation is held before expiry'),
('registration_approval', 'true', 'Whether new registrations require approval');

-- Insert Activity Log
INSERT INTO ActivityLog (UserID, ActivityType, Description, IPAddress) VALUES
(1, 'LOGIN', 'User logged in', '192.168.1.100'),
(2, 'BORROW', 'Borrowed book: To Kill a Mockingbird', '192.168.1.101'),
(1, 'RETURN', 'Processed return for borrow ID: 1', '192.168.1.100'),
(4, 'RETURN', 'Processed return for borrow ID: 3', '192.168.1.103'),
(3, 'RESERVE', 'Reserved book: To Kill a Mockingbird', '192.168.1.102'),
(1, 'REGISTER', 'Approved registration for Alice Brown', '192.168.1.100');

CREATE VIEW OverdueBooks AS
SELECT b.BorrowID, u.FullName, bk.Title, b.DueDate, 
       DATEDIFF(CURRENT_DATE, b.DueDate) AS DaysOverdue
FROM Borrowing b
JOIN Users u ON b.UserID = u.UserID
JOIN Books bk ON b.BookID = bk.BookID
WHERE b.Status = 'Active' AND b.DueDate < CURRENT_DATE;

