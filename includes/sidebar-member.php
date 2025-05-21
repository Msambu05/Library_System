<?php
// Prevent direct access to this file
if (!defined('BASE_PATH')) {
    die('Direct access to this file is not allowed.');
}

// Check if user is authenticated and is a member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
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
    
    // Get outstanding fines
    $outstandingFines = getTotalFines($conn, $_SESSION['user_id']);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $currentBorrowings = 0;
    $outstandingFines = 0;
}
?>

<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="p-3 text-center">
            <div class="mb-3">
                <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
            </div>
            <h5><?= htmlspecialchars($_SESSION['full_name']) ?></h5>
            <p class="text-muted mb-0">Member</p>
        </div>
        
        <hr>
        
        <div class="p-3">
            <div class="d-flex justify-content-between mb-2">
                <span>Books Borrowed:</span>
                <span class="badge bg-primary"><?= $currentBorrowings ?></span>
            </div>
            <?php if ($outstandingFines > 0): ?>
            <div class="d-flex justify-content-between">
                <span>Outstanding Fines:</span>
                <span class="badge bg-danger">$<?= number_format($outstandingFines, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <hr>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/member_dashboard.php">
                    <i class="bi bi-house-door me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'borrow' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/borrow_books.php">
                    <i class="bi bi-book me-2"></i> Borrow Books
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'return' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/return_books.php">
                    <i class="bi bi-arrow-return-left me-2"></i> Return Books
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'history' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/history.php">
                    <i class="bi bi-clock-history me-2"></i> Borrowing History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'reservations' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/my_reservations.php">
                    <i class="bi bi-bookmark me-2"></i> My Reservations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'fines' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/my_fines.php">
                    <i class="bi bi-cash-coin me-2"></i> My Fines
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/profile.php">
                    <i class="bi bi-person me-2"></i> My Profile
                </a>
            </li>
        </ul>
        
        <hr>
        
        <div class="px-3">
            <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline-danger w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        </div>
    </div>
</div>