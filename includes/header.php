<?php
// Prevent direct access to this file
if (!defined('BASE_PATH')) {
    die('Direct access to this file is not allowed.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Library Management System' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Librarian'): ?>
    <link href="<?= BASE_URL ?>/assets/css/admin.css" rel="stylesheet">
    <?php else: ?>
    <link href="<?= BASE_URL ?>/assets/css/member.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/<?= $_SESSION['role'] === 'Librarian' ? 'admin/admin_dashboard.php' : 'member/member_dashboard.php' ?>">
                Library System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Librarian'): ?>
                    <!-- Admin Navigation -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/admin_dashboard.php">
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'books' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/manage_books.php">
                            <i class="bi bi-book"></i> Manage Books
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/manage_users.php">
                            <i class="bi bi-people"></i> Manage Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'borrowing' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/borrowing.php">
                            <i class="bi bi-arrow-left-right"></i> Borrowing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/reports.php">
                            <i class="bi bi-file-earmark-text"></i> Reports
                        </a>
                    </li>
                    <?php else: ?>
                    <!-- Member Navigation -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/member_dashboard.php">
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'borrow' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/borrow_books.php">
                            <i class="bi bi-book"></i> Borrow Books
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'return' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/return_books.php">
                            <i class="bi bi-arrow-return-left"></i> Return Books
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'history' ? 'active' : '' ?>" href="<?= BASE_URL ?>/member/history.php">
                            <i class="bi bi-clock-history"></i> History
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <span class="nav-link"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/auth/login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/auth/register.php"><i class="bi bi-person-plus"></i> Register</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>