<?php
session_start();
require_once 'config.php';

// Ensure the user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: main.php");
    exit();
}

$success = "";
$error = "";

/* ---------- Handle Form Submission ---------- */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $review = trim($_POST['comments']);
    $user_id = $_SESSION['user_id'];  
    $place_id = $_POST['place_id'];
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

    if (empty($review)) {
        $error = "Review cannot be empty.";
    } elseif ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (user_id, place_id, review, rating) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $user_id, $place_id, $review, $rating);

        if ($stmt->execute()) {
            $success = "Thank you! Your memory has been shared.";
            // Clear form
            $_POST = array();
        } else {
            $error = "Error saving your review.";
        }
    }
}

/* ---------- Fetch All Reviews ---------- */
$reviewsQuery = $conn->query("
 SELECT 
    r.review,
    r.rating,
    r.created_at,

    -- get user name
    (SELECT d.UserName
        FROM data d
        WHERE d.id = r.user_id
    )AS user_name,

    -- get place name
    (SELECT p.place_name
        FROM places p
        WHERE p.place_id = r.place_id
    )AS place_name

FROM reviews r
ORDER BY r.created_at DESC;
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Your Memory - Cultural Echo</title>
    <link rel="icon" href="favicon.jpg">
    <link rel="stylesheet" href="add_your_memoryy.css">
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
            <li><a href="main.php">Home</a></li>
            <li><a href="C/explore_places.php">Take a tour</a></li>
            <li><a href="booking.php">Book A Flight</a></li>
            <li><a href="quiz.php">Sustainable Tourism</a></li>
            <li><a href="add_your_memory.php" class="active">Add Memory</a></li>
        </ul>
        <div class="nav-auth">
            <span class="user-welcome">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </span>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
            </svg>
            Share Your Experience
        </div>
        <h1 class="hero-title">Add Your <span class="gradient-text">Memory</span></h1>
        <p class="hero-subtitle">
            Your stories inspire others to explore new destinations and discover hidden gems. 
            Share your experiences and help fellow travelers create unforgettable memories.
        </p>
    </div>
</section>

<!-- Gallery Section -->
<section class="gallery-section">
    <div class="gallery-grid">
        <div class="gallery-item">
            <img src="memory1.jpg" alt="Travel destination">
            <div class="gallery-overlay">
                <p>Capture Moments</p>
            </div>
        </div>
        <div class="gallery-item">
            <img src="memory2.jpg" alt="Travel experience">
            <div class="gallery-overlay">
                <p>Share Stories</p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container">
    
    <!-- Success & Error Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <span><?= $success ?></span>
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

    <!-- Review Form -->
    <div class="form-section">
        <div class="form-card">
            <div class="form-header">
                <h2>Share Your Travel Story</h2>
                <p>Tell us about your amazing experience</p>
            </div>
            
            <form method="POST" class="review-form" id="reviewForm">
                <div class="input-group">
                    <label for="place_id">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Choose a Destination
                    </label>
                    <select name="place_id" id="place_id" required>
                        <option value="">-- Select a place --</option>
                        <?php
                            $places = $conn->query("SELECT place_id, place_name FROM places ORDER BY place_name ASC");
                            while ($p = $places->fetch_assoc()):
                        ?>
                            <option value="<?= $p['place_id'] ?>">
                                <?= htmlspecialchars($p['place_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label for="comments">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        Your Review
                    </label>
                    <textarea 
                        name="comments" 
                        id="comments" 
                        rows="6" 
                        placeholder="Share your experience, tips, and recommendations for fellow travelers..." 
                        required
                    ></textarea>
                    <div class="char-count">
                        <span id="charCount">0</span> characters
                    </div>
                </div>

                <div class="input-group">
                    <label for="rating">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        Rate Your Experience
                    </label>
                    <div class="star-rating">
                        <input type="radio" name="rating" value="5" id="star5" required>
                        <label for="star5" title="5 stars">★</label>
                        <input type="radio" name="rating" value="4" id="star4">
                        <label for="star4" title="4 stars">★</label>
                        <input type="radio" name="rating" value="3" id="star3">
                        <label for="star3" title="3 stars">★</label>
                        <input type="radio" name="rating" value="2" id="star2">
                        <label for="star2" title="2 stars">★</label>
                        <input type="radio" name="rating" value="1" id="star1">
                        <label for="star1" title="1 star">★</label>
                    </div>
                    <p class="rating-description">Click on the stars to rate (1 = Poor, 5 = Excellent)</p>
                </div>

                <button type="submit" class="btn btn-primary btn-large btn-full">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    Share Your Memory
                </button>
            </form>
        </div>
    </div>

    <!-- Reviews Section -->
    <div class="reviews-section">
        <div class="section-header">
            <h2>Recent Memories</h2>
            <p>Explore experiences shared by our community</p>
        </div>

        <div class="reviews-grid">
            <?php if ($reviewsQuery->num_rows > 0): ?>
                <?php while ($r = $reviewsQuery->fetch_assoc()): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="place-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                            </div>
                            <h3 class="place-name"><?= htmlspecialchars($r['place_name']) ?></h3>
                        </div>
                        <p class="review-text"><?= htmlspecialchars($r['review']) ?></p>
                        <div class="review-footer">
                            <div class="review-author">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span><?= htmlspecialchars($r['user_name']) ?></span>
                            </div>
                            <div class="review-date">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <h3>No memories yet</h3>
                    <p>Be the first to share your travel experience!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
// Character counter for textarea
const textarea = document.getElementById('comments');
const charCount = document.getElementById('charCount');

if (textarea && charCount) {
    textarea.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
}

// Star rating visual feedback
const starRatingInputs = document.querySelectorAll('.star-rating input[type="radio"]');
const ratingDescription = document.querySelector('.rating-description');

starRatingInputs.forEach(input => {
    input.addEventListener('change', function() {
        const rating = this.value;
        const descriptions = {
            '1': '1 Star - Poor',
            '2': '2 Stars - Fair',
            '3': '3 Stars - Good',
            '4': '4 Stars - Very Good',
            '5': '5 Stars - Excellent'
        };
        if (ratingDescription) {
            ratingDescription.textContent = descriptions[rating] || 'Click on the stars to rate';
            ratingDescription.style.color = '#fbbf24';
            ratingDescription.style.fontWeight = '600';
        }
    });
});

// Form submission animation
const form = document.getElementById('reviewForm');
if (form) {
    form.addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        btn.classList.add('loading');
        btn.innerHTML = '<span class="spinner"></span> Submitting...';
    });
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.animation = 'slideOut 0.5s ease-out forwards';
        setTimeout(function() {
            alert.remove();
        }, 500);
    });
}, 5000);

// Smooth scroll for navigation
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if(target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>

</body>
</html>