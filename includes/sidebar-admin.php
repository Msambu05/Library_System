<?php
// Prevent direct access to this file
if (!defined('BASE_PATH')) {
    die('Direct access to this file is not allowed.');
}

// Check if user is authenticated and is a librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Librarian') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}
?>

<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/admin_dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'books' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/manage_books.php">
                    <i class="bi bi-book me-2"></i> Manage Books
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'add_book' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/add_book.php">
                    <i class="bi bi-plus-circle me-2"></i> Add New Book
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'categories' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/categories.php">
                    <i class="bi bi-tags me-2"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/manage_users.php">
                    <i class="bi bi-people me-2"></i> Manage Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'add_user' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/add_user.php">
                    <i class="bi bi-person-plus me-2"></i> Add New User
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Borrowing Operations</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'borrowing' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/borrowing.php">
                    <i class="bi bi-arrow-left-right me-2"></i> Manage Borrowing
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'returns' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/returns.php">
                    <i class="bi bi-arrow-return-left me-2"></i> Process Returns
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'reservations' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/reservations.php">
                    <i class="bi bi-bookmark me-2"></i> Reservations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'fines' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/fines.php">
                    <i class="bi bi-cash-coin me-2"></i> Manage Fines
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Reports & System</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/reports.php">
                    <i class="bi bi-file-earmark-text me-2"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'activity_log' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/activity_log.php">
                    <i class="bi bi-clock-history me-2"></i> Activity Log
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/settings.php">
                    <i class="bi bi-gear me-2"></i> System Settings
                </a>
            </li>
        </ul>
    </div>
</div>