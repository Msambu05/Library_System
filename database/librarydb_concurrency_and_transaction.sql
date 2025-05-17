-- librarydb_concurrency_and_transaction.sql
USE librarydb;

DELIMITER //

-- 1. Drop existing procedures if they exist
DROP PROCEDURE IF EXISTS BorrowBook//
DROP PROCEDURE IF EXISTS ReturnBook//

-- 2. Create triggers first
DROP TRIGGER IF EXISTS update_available_copies_after_borrow//
DROP TRIGGER IF EXISTS update_available_copies_after_return//

-- Trigger for book borrowing
CREATE TRIGGER update_available_copies_after_borrow
AFTER INSERT ON Borrowing
FOR EACH ROW
BEGIN
    -- Decrement available copies
    UPDATE Books 
    SET AvailableCopies = AvailableCopies - 1 
    WHERE BookID = NEW.BookID;
    
    -- Log the activity
    INSERT INTO ActivityLog(UserID, ActivityType, Description)
    VALUES (NEW.UserID, 'BORROW', CONCAT('Borrowed book ID: ', NEW.BookID));
END//

-- Trigger for book returns
CREATE TRIGGER update_available_copies_after_return
AFTER UPDATE ON Borrowing
FOR EACH ROW
BEGIN
    -- Only execute if status changed to Returned
    IF NEW.Status = 'Returned' AND OLD.Status != 'Returned' THEN
        -- Increment available copies
        UPDATE Books 
        SET AvailableCopies = AvailableCopies + 1 
        WHERE BookID = NEW.BookID;
        
        -- Log the return activity
        INSERT INTO ActivityLog(UserID, ActivityType, Description)
        VALUES (NEW.ReturnedBy, 'RETURN', CONCAT('Processed return of book ID: ', NEW.BookID));
    END IF;
END//

-- 3. Create modified stored procedures

-- BorrowBook procedure (now lets trigger handle copies update)
CREATE PROCEDURE BorrowBook(
    IN p_user_id INT,
    IN p_book_id INT,
    OUT p_result VARCHAR(100)
)
BEGIN
    DECLARE available_copies INT;
    DECLARE max_books INT;
    DECLARE current_loans INT;
    DECLARE has_reservation BOOLEAN DEFAULT FALSE;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'Error occurred during borrowing';
    END;
    
    START TRANSACTION;
    
    -- Get system settings
    SELECT CAST(SettingValue AS UNSIGNED) INTO max_books
    FROM SystemSettings WHERE SettingKey = 'max_books_per_user';
    
    -- Check user's current loans
    SELECT COUNT(*) INTO current_loans
    FROM Borrowing 
    WHERE UserID = p_user_id AND Status = 'Active';
    
    IF current_loans >= max_books THEN
        ROLLBACK;
        SET p_result = CONCAT('Maximum borrowing limit reached (', max_books, ' books)');
    ELSE
        -- Lock the book row for update
        SELECT AvailableCopies INTO available_copies 
        FROM Books WHERE BookID = p_book_id FOR UPDATE;
        
        IF available_copies > 0 THEN
            -- Insert borrowing record (trigger will update AvailableCopies)
            INSERT INTO Borrowing (UserID, BookID, BorrowDate, DueDate, ReturnStateBorrowed)
            VALUES (p_user_id, p_book_id, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 'Good');
            
            COMMIT;
            SET p_result = 'Book borrowed successfully';
        ELSE
            -- Check for reservation system
            SELECT EXISTS (
                SELECT 1 FROM Reservations 
                WHERE BookID = p_book_id 
                AND Status = 'Pending' 
                AND UserID = p_user_id
            ) INTO has_reservation;
            
            IF has_reservation THEN
                UPDATE Reservations SET Status = 'Fulfilled' 
                WHERE BookID = p_book_id AND UserID = p_user_id;
                
                -- Insert borrowing record for reserved book
                INSERT INTO Borrowing (UserID, BookID, BorrowDate, DueDate, ReturnStateBorrowed)
                VALUES (p_user_id, p_book_id, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 'Good');
                
                COMMIT;
                SET p_result = 'Reserved book borrowed successfully';
            ELSE
                ROLLBACK;
                SET p_result = 'Book not available';
            END IF;
        END IF;
    END IF;
END//

-- ReturnBook procedure (now lets trigger handle copies update)
CREATE PROCEDURE ReturnBook(
    IN p_borrow_id INT,
    IN p_librarian_id INT,
    IN p_condition ENUM('New', 'Good', 'Fair', 'Poor', 'Damaged', 'Lost'),
    OUT p_result VARCHAR(100)
)
BEGIN
    DECLARE v_book_id INT;
    DECLARE v_user_id INT;
    DECLARE v_due_date DATETIME;
    DECLARE v_fine_amount DECIMAL(10,2) DEFAULT 0;
    DECLARE v_fine_per_day DECIMAL(10,2);
    DECLARE v_days_overdue INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = CONCAT('Error occurred during return: ', COALESCE(SQLERRM, 'Unknown error'));
    END;
    
    START TRANSACTION;
    
    -- Get system settings
    SELECT CAST(SettingValue AS DECIMAL(10,2)) INTO v_fine_per_day
    FROM SystemSettings WHERE SettingKey = 'fine_per_day';
    
    -- Lock borrowing record
    SELECT BookID, UserID, DueDate INTO v_book_id, v_user_id, v_due_date
    FROM Borrowing 
    WHERE BorrowID = p_borrow_id FOR UPDATE;
    
    -- Calculate overdue days
    SET v_days_overdue = DATEDIFF(NOW(), v_due_date);
    
    -- Determine fine based on condition and overdue status
    IF p_condition = 'Lost' THEN
        -- Charge full book price + admin fee
        SELECT 50.00 + (SELECT 0.1 * Price FROM Books WHERE BookID = v_book_id) 
        INTO v_fine_amount;
    ELSEIF p_condition = 'Damaged' THEN
        -- Flat damage fee
        SET v_fine_amount = 20.00;
    ELSEIF v_days_overdue > 0 THEN
        -- Standard overdue fine
        SET v_fine_amount = v_days_overdue * v_fine_per_day;
    END IF;
    
    -- Update borrowing record (trigger will handle AvailableCopies)
    UPDATE Borrowing
    SET Status = 'Returned',
        ReturnedBy = p_librarian_id,
        ReturnDate = NOW(),
        ReturnStateReturned = p_condition,
        Notes = CONCAT('Returned in ', p_condition, ' condition')
    WHERE BorrowID = p_borrow_id;
    
    -- Record fine if applicable
    IF v_fine_amount > 0 THEN
        INSERT INTO Fines (BorrowID, UserID, Amount, Reason)
        VALUES (p_borrow_id, v_user_id, v_fine_amount, 
               CASE 
                   WHEN p_condition = 'Lost' THEN 'Lost'
                   WHEN p_condition = 'Damaged' THEN 'Damage'
                   ELSE 'Overdue'
               END);
    END IF;
    
    -- Check for pending reservations
    IF EXISTS (SELECT 1 FROM Reservations 
               WHERE BookID = v_book_id AND Status = 'Pending') THEN
        -- Update the first pending reservation
        UPDATE Reservations 
        SET Status = 'Fulfilled',
            NotificationSent = TRUE
        WHERE ReservationID = (
            SELECT MIN(ReservationID) 
            FROM Reservations 
            WHERE BookID = v_book_id AND Status = 'Pending'
        );
    END IF;
    
    COMMIT;
    SET p_result = CONCAT('Book returned successfully. ', 
                         CASE WHEN v_fine_amount > 0 
                              THEN CONCAT('Fine: R', FORMAT(v_fine_amount, 2))
                              ELSE 'No fines assessed'
                         END);
END//

DELIMITER ;