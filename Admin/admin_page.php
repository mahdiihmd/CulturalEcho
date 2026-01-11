<?php
session_start();
require_once '../config.php';

// Check if user is admin (from data table with role='admin')
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../main.php");
    exit();
}

$message = '';
$error = '';

// Handle Delete User
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM data WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $message = "User deleted successfully";
    } else {
        $error = "Error deleting user";
    }
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    // Check if email already exists for another user
    $checkStmt = $conn->prepare("SELECT id FROM data WHERE Email = ? AND id != ?");
    $checkStmt->bind_param("si", $email, $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $error = "Email already exists for another user";
    } else {
        $stmt = $conn->prepare("UPDATE data SET UserName = ?, Email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $role, $user_id);
        if ($stmt->execute()) {
            $message = "User updated successfully";
        } else {
            $error = "Error updating user";
        }
    }
}

// Handle Reset Password
if (isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE data SET Password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_password, $user_id);
    if ($stmt->execute()) {
        $message = "Password reset successfully";
    } else {
        $error = "Error resetting password";
    }
}

// Fetch all users with their statistics (exclude current admin from deletion)
$usersQuery = $conn->query("
   SELECT 
    d.id,
    d.UserName,
    d.Email,
    d.role,
    d.created_at,

    -- total bookings per user
    (SELECT COUNT(*)
        FROM bookings b
        WHERE b.user_id = d.id
    )AS total_bookings,

    -- total reviews per user
    (SELECT COUNT(*)
        FROM reviews r
        WHERE r.user_id = d.id
    )AS total_reviews,

    -- total amount spent by user
    (SELECT COALESCE(SUM(b2.total_price), 0)
        FROM bookings b2
        WHERE b2.user_id = d.id
    )AS total_spent

FROM data d
ORDER BY d.role DESC, d.id DESC;
");

// Get statistics
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM data WHERE role = 'user') as total_users,
        (SELECT COUNT(*) FROM data WHERE role = 'admin') as total_admins,
        (SELECT COUNT(*) FROM data WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND role = 'user') as new_users_week,
        (SELECT COUNT(*) FROM bookings) as total_bookings,
        (SELECT COUNT(*) FROM reviews) as total_reviews,
        (SELECT SUM(total_price) FROM bookings) as total_revenue
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cultural Echo</title>
    <link rel="icon" href="favicon.jpg">
    <link rel="stylesheet" href="admin_page.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <h1>Cultural Echo Admin</h1>
        </div>
         <ul class="nav-menu">
            <li><a href="admin_page.php" class="active">User Management</a></li>
            <li><a href="admin_bookings.php">Bookings Management</a></li>
        </ul>
        <div class="nav-auth">
            <span class="user-welcome">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <?= htmlspecialchars($_SESSION['name']) ?> (Admin)
            </span>
            <a href="../logout.php" class="btn btn-outline">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="admin-container">
    
    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span><?= $error ?></span>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>User Management Dashboard</h1>
            <p>Manage users, bookings, and monitor platform activity</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_users']) ?></h3>
                <p>Total Users</p>
                <span class="stat-badge">+<?= $stats['new_users_week'] ?> this week</span>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                    <path d="M2 17l10 5 10-5"></path>
                    <path d="M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_admins']) ?></h3>
                <p>Total Admins</p>
                <span class="stat-badge">System administrators</span>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_bookings']) ?></h3>
                <p>Total Bookings</p>
                <span class="stat-badge">All time</span>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_reviews']) ?></h3>
                <p>Total Reviews</p>
                <span class="stat-badge">Community feedback</span>
            </div>
        </div>

        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3>$<?= number_format($stats['total_revenue'])?></h3>
                <p>Total Revenue</p>
                <span class="stat-badge">From bookings</span>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-section">
        <div class="table-header">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                All Users
            </h2>
            <div class="table-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="text" id="searchInput" placeholder="Search users..." onkeyup="searchUsers()">
            </div>
        </div>

        <div class="table-responsive">
            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Bookings</th>
                        <th>Reviews</th>
                        <th>Total Spent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $usersQuery->fetch_assoc()): ?>
                    <tr>
                        <td><span class="user-id">#<?= $user['id'] ?></span></td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar"><?= strtoupper(substr($user['UserName'], 0, 1)) ?></div>
                                <span><?= htmlspecialchars($user['UserName']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($user['Email']) ?></td>
                        <td>
                            <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        <td><span class="badge badge-blue"><?= $user['total_bookings'] ?></span></td>
                        <td><span class="badge badge-purple"><?= $user['total_reviews'] ?></span></td>
                        <td><strong>$<?= number_format($user['total_spent'], 2) ?></strong></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon btn-edit" onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['UserName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['Email'], ENT_QUOTES) ?>', '<?= $user['role'] ?>')" title="Edit User">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                <button class="btn-icon btn-password" onclick="openPasswordModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['UserName'], ENT_QUOTES) ?>')" title="Reset Password">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <a href="?delete=<?= $user['id'] ?>" class="btn-icon btn-delete" onclick="return confirm('Are you sure you want to delete this user? This will also delete all their bookings and reviews.')" title="Delete User">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-overlay" onclick="closeEditModal()"></div>
    <div class="modal-content">
        <button class="modal-close" onclick="closeEditModal()">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <div class="modal-header">
            <h2>Edit User</h2>
            <p>Update user information</p>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="input-group">
                <label for="edit_name">Full Name</label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="input-group">
                <label for="edit_email">Email Address</label>
                <input type="email" name="email" id="edit_email" required>
            </div>
            <div class="input-group">
                <label for="edit_role">Role</label>
                <select name="role" id="edit_role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="edit_user" class="btn btn-primary btn-full">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Update User
            </button>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-overlay" onclick="closePasswordModal()"></div>
    <div class="modal-content">
        <button class="modal-close" onclick="closePasswordModal()">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <div class="modal-header">
            <h2>Reset Password</h2>
            <p>Set new password for <strong id="password_username"></strong></p>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="user_id" id="password_user_id">
            <div class="input-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" required minlength="6" placeholder="Minimum 6 characters">
                <small>Password must be at least 6 characters long</small>
            </div>
            <button type="submit" name="reset_password" class="btn btn-primary btn-full">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Reset Password
            </button>
        </form>
    </div>
</div>

<script>
// Modal Functions
function openEditModal(id, name, email, role) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('editModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function openPasswordModal(id, name) {
    document.getElementById('password_user_id').value = id;
    document.getElementById('password_username').textContent = name;
    document.getElementById('passwordModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Search Users
function searchUsers() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('usersTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < cells.length - 1; j++) {
            if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }
        
        rows[i].style.display = found ? '' : 'none';
    }
}

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.animation = 'slideOut 0.5s ease-out forwards';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
        closePasswordModal();
    }
});
</script>

</body>
</html>