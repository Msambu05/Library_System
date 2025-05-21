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
$user = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid form submission.";
    } else {
        // Sanitize inputs
        $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $date_of_birth = filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_STRING);
        $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
        
        // Validate inputs
        if (empty($full_name) || empty($email) || empty($username) || empty($role)) {
            $message = "Please fill all required fields.";
        } else {
            try {
                if (isset($_POST['update'])) {
                    // Update existing user
                    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                    
                    $stmt = $conn->prepare("
                        UPDATE Users SET 
                            FullName = ?, 
                            Email = ?, 
                            Username = ?, 
                            Phone = ?, 
                            Address = ?, 
                            DateOfBirth = ?,
                            Role = ?
                        WHERE UserID = ?
                    ");
                    
                    $stmt->execute([
                        $full_name, $email, $username, $phone, 
                        $address, $date_of_birth, $role, $user_id
                    ]);
                    
                    $message = "User updated successfully!";
                    
                    // Log activity
                    $conn->prepare("
                        INSERT INTO ActivityLog (UserID, ActivityType, Description)
                        VALUES (?, 'UPDATE', ?)
                    ")->execute([$_SESSION['user_id'], "Updated user: $username"]);
                    
                } elseif (isset($_POST['add'])) {
                    // Add new user
                    $password = password_hash('Library@123', PASSWORD_DEFAULT); // Default password
                    
                    $stmt = $conn->prepare("
                        INSERT INTO Users (
                            FullName, Email, Username, PasswordHash, 
                            Phone, Address, DateOfBirth, Role
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $full_name, $email, $username, $password, 
                        $phone, $address, $date_of_birth, $role
                    ]);
                    
                    $message = "User added successfully! Default password: Library@123";
                    
                    // Log activity
                    $conn->prepare("
                        INSERT INTO ActivityLog (UserID, ActivityType, Description)
                        VALUES (?, 'INSERT', ?)
                    ")->execute([$_SESSION['user_id'], "Added new user: $username"]);
                }
                
                // Clear form after successful submission
                unset($_POST);
                header("Location: manage_users.php?success=" . urlencode($message));
                exit();
                
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $message = "Error saving user. Please try again.";
                
                if ($e->getCode() == 23000) { // Duplicate entry
                    if (strpos($e->getMessage(), 'Username') !== false) {
                        $message = "Username already exists. Please choose another.";
                    } elseif (strpos($e->getMessage(), 'Email') !== false) {
                        $message = "Email already exists. Please use another email.";
                    }
                }
            }
        }
    }
}

// Handle toggle active status
if (isset($_GET['toggle'])) {
    $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $action = $_GET['toggle'];
    
    if ($user_id && in_array($action, ['activate', 'deactivate'])) {
        try {
            // Get username for logging
            $username = $conn->query("SELECT Username FROM Users WHERE UserID = $user_id")->fetchColumn();
            
            // Update status
            $is_active = $action === 'activate' ? 1 : 0;
            $conn->exec("UPDATE Users SET IsActive = $is_active WHERE UserID = $user_id");
            
            // Log activity
            $conn->prepare("
                INSERT INTO ActivityLog (UserID, ActivityType, Description)
                VALUES (?, 'UPDATE', ?)
            ")->execute([$_SESSION['user_id'], "$action user: $username"]);
            
            $message = "User " . $action . "d successfully!";
            header("Location: manage_users.php?success=" . urlencode($message));
            exit();
            
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $message = "Error updating user status. Please try again.";
        }
    }
}

// Handle reset password
if (isset($_GET['reset_password'])) {
    $user_id = filter_input(INPUT_GET, 'reset_password', FILTER_VALIDATE_INT);
    
    if ($user_id) {
        try {
            // Get username for logging
            $username = $conn->query("SELECT Username FROM Users WHERE UserID = $user_id")->fetchColumn();
            
            // Reset password
            $password = password_hash('Library@123', PASSWORD_DEFAULT);
            $conn->exec("UPDATE Users SET PasswordHash = '$password' WHERE UserID = $user_id");
            
            // Log activity
            $conn->prepare("
                INSERT INTO ActivityLog (UserID, ActivityType, Description)
                VALUES (?, 'UPDATE', ?)
            ")->execute([$_SESSION['user_id'], "Reset password for user: $username"]);
            
            $message = "Password reset successfully! New password: Library@123";
            header("Location: manage_users.php?success=" . urlencode($message));
            exit();
            
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $message = "Error resetting password. Please try again.";
        }
    }
}

// Handle edit action
if (isset($_GET['edit'])) {
    $user_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    
    if ($user_id) {
        try {
            $user = $conn->query("SELECT * FROM Users WHERE UserID = $user_id")->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $message = "Error loading user details. Please try again.";
        }
    }
}

// Get all users
try {
    $users = $conn->query("
        SELECT * FROM Users 
        ORDER BY Role, FullName
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $message = "Error loading users. Please try again.";
    $users = [];
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Library System</title>
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
                    <h1 class="h2">Manage Users</h1>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
                <?php elseif (!empty($message)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <!-- User Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><?= isset($user) ? 'Edit User' : 'Add New User' ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <?php if (isset($user)): ?>
                                <input type="hidden" name="user_id" value="<?= $user['UserID'] ?>">
                            <?php endif; ?>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name*</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?= htmlspecialchars($user['FullName'] ?? $_POST['full_name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email*</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($user['Email'] ?? $_POST['email'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username*</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($user['Username'] ?? $_POST['username'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="role" class="form-label">Role*</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="Member" <?= (isset($user) && $user['Role'] == 'Member') || 
                                            (isset($_POST['role']) && $_POST['role'] == 'Member') ? 'selected' : '' ?>>Member</option>
                                        <option value="Librarian" <?= (isset($user) && $user['Role'] == 'Librarian') || 
                                            (isset($_POST['role']) && $_POST['role'] == 'Librarian') ? 'selected' : '' ?>>Librarian</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($user['Phone'] ?? $_POST['phone'] ?? '') ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?= htmlspecialchars($user['DateOfBirth'] ?? $_POST['date_of_birth'] ?? '') ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?= 
                                        htmlspecialchars($user['Address'] ?? $_POST['address'] ?? '') 
                                    ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="<?= isset($user) ? 'update' : 'add' ?>" class="btn btn-primary">
                                        <?= isset($user) ? 'Update User' : 'Add User' ?>
                                    </button>
                                    <?php if (isset($user)): ?>
                                        <a href="manage_users.php" class="btn btn-outline-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $userItem): ?>
                                    <tr class="<?= $userItem['IsActive'] ? '' : 'table-secondary' ?>">
                                        <td><?= $userItem['UserID'] ?></td>
                                        <td><?= htmlspecialchars($userItem['FullName']) ?></td>
                                        <td><?= htmlspecialchars($userItem['Username']) ?></td>
                                        <td><?= htmlspecialchars($userItem['Email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $userItem['Role'] === 'Librarian' ? 'primary' : 'success' ?>">
                                                <?= $userItem['Role'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $userItem['IsActive'] ? 'success' : 'danger' ?>">
                                                <?= $userItem['IsActive'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?edit=<?= $userItem['UserID'] ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?toggle=<?= $userItem['IsActive'] ? 'deactivate' : 'activate' ?>&id=<?= $userItem['UserID'] ?>" 
                                                   class="btn btn-outline-<?= $userItem['IsActive'] ? 'warning' : 'success' ?>" title="<?= $userItem['IsActive'] ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="bi bi-power"></i>
                                                </a>
                                                <a href="?reset_password=<?= $userItem['UserID'] ?>" class="btn btn-outline-info" title="Reset Password"
                                                   onclick="return confirm('Reset password to default for <?= htmlspecialchars($userItem['Username']) ?>?')">
                                                    <i class="bi bi-key"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No users found</td>
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
</body>
</html>