<?php
session_start();
require_once 'config.php';

$errors = ['login' => '', 'register' => ''];

// Handle Login
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM data WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['Password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['UserName'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: Admin/admin_page.php");
            } else {
                header("Location: " . $_SERVER['PHP_SELF']);
            }
        } else {
            $errors['login'] = "Incorrect password";
        }
    } else {
        $errors['login'] = "Email not found";
    }
}

// Handle Register
if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("SELECT Email FROM data WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $errors['register'] = "Email is already registered";
    } else {
        $stmt = $conn->prepare("INSERT INTO data (UserName, Email, Password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);
        $stmt->execute();
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="style1.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cultural Echo - Explore the World</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <h1>Cultural Echo</h1>
        </div>
        <ul class="nav-menu">
            <li><a href="C/explore_places.php">Take a Tour</a></li>
            <li><a href="quiz.php">Sustainable Tourism</a></li>
            <li><a href="booking.php">Book A Flight</a></li>
            <li><a href="add_your_memory.php">Add Memory</a></li>
        </ul>
        <div class="nav-auth">
            <?php if (isset($_SESSION['email'])): ?>
                <span class="user-welcome">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <?= htmlspecialchars($_SESSION['name']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            <?php else: ?>
                <button class="btn btn-outline" onclick="openForm('login')">Login</button>
                <button class="btn btn-primary" onclick="openForm('register')">Sign Up</button>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<main class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">Discover • Share • Inspire</div>
        <h1 class="hero-title">
            Welcome to <span class="gradient-text">Cultural Echo</span>
        </h1>
        <p class="hero-subtitle">
            Where diverse perspectives unite in a symphony of global insights. Explore travel memories from around the world and share your own unique stories.
        </p>
        <div class="hero-cta">
            <a href="explore.html" class="btn btn-large btn-primary">Start Exploring</a>
            <a href="add_your_memory.php" class="btn btn-large btn-secondary">Share Your Story</a>
        </div>
        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-number">1000+</div>
                <div class="stat-label">Travel Stories</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">50+</div>
                <div class="stat-label">Countries</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">500+</div>
                <div class="stat-label">Travelers</div>
            </div>
        </div>
    </div>
</main>

<!-- Login Modal -->
<div class="modal" id="login" style="<?= !empty($errors['login']) ? 'display:flex;' : 'display:none;' ?>">
    <div class="modal-overlay" onclick="closeForm('login')"></div>
    <div class="form-box">
        <span class="close" onclick="closeForm('login')">&times;</span>
        <div class="form-header">
            <h2>Welcome Back</h2>
            <p>Login to continue your journey</p>
        </div>
        <form method="POST">
            <?php if (!empty($errors['login'])): ?>
                <div class="error-message">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?= htmlspecialchars($errors['login']) ?>
                </div>
            <?php endif; ?>
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@example.com" value="<?= isset($_POST['login']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary btn-full">Login</button>
            <div class="form-footer">
                Don't have an account? <a href="javascript:void(0);" class="link" onclick="switchForm('register')">Sign up</a>
            </div>
        </form>
    </div>
</div>

<!-- Register Modal -->
<div class="modal" id="register" style="<?= !empty($errors['register']) ? 'display:flex;' : 'display:none;' ?>">
    <div class="modal-overlay" onclick="closeForm('register')"></div>
    <div class="form-box">
        <span class="close" onclick="closeForm('register')">&times;</span>
        <div class="form-header">
            <h2>Join Cultural Echo</h2>
            <p>Start sharing your travel experiences</p>
        </div>
        <form method="POST">
            <?php if (!empty($errors['register'])): ?>
                <div class="error-message">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?= htmlspecialchars($errors['register']) ?>
                </div>
            <?php endif; ?>
            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="John Doe" value="<?= isset($_POST['register']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
            </div>
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@example.com" value="<?= isset($_POST['register']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a strong password" required>
            </div>
            <div class="input-group">
                <label>I am a</label>
                <select name="role" required>
                    <option value="">Select your role</option>
                    <option value="user" <?= (isset($_POST['register']) && $_POST['role'] === 'user') ? 'selected' : '' ?>>Traveler</option>
                    <option value="admin" <?= (isset($_POST['register']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>
            <button type="submit" name="register" class="btn btn-primary btn-full">Create Account</button>
            <div class="form-footer">
                Already have an account? <a href="javascript:void(0);" class="link" onclick="switchForm('login')">Login</a>
            </div>
        </form>
    </div>
</div>

<script>
function openForm(formId) {
    const form = document.getElementById(formId);
    if(form) {
        form.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeForm(formId) {
    const form = document.getElementById(formId);
    if(form) {
        form.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function switchForm(formId) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.style.display = 'none';
    });
    openForm(formId);
    return false;
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        const modal = event.target.parentElement;
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Add smooth scroll behavior
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if(target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});
</script>
</body>
</html>