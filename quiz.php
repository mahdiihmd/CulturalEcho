<?php
session_start();
require_once 'config.php';

$message = "";
$error = "";
$quiz_result = "";

/* ---------- Handle Eco-Pledge Submission ---------- */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_pledge'])) {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $pledge = trim($_POST['pledge']);
        
        if (!empty($pledge)) {
            $stmt = $conn->prepare("INSERT INTO eco_pledges (user_id, pledge_text) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $pledge);
            
            if ($stmt->execute()) {
                $message = "Thank you for your eco-pledge! Together we make a difference.";
            } else {
                $error = "Error saving your pledge. Please try again.";
            }
        } else {
            $error = "Please write your pledge before submitting.";
        }
    } else {
        $error = "Please login to make a pledge.";
    }
}

/* ---------- Handle Quiz Submission (PRG Pattern) ---------- */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_quiz'])) {
    $correct = 0;
    $total = 5;

    // Check answers
    if (isset($_POST['q1']) && $_POST['q1'] == 'c') $correct++;
    if (isset($_POST['q2']) && $_POST['q2'] == 'b') $correct++;
    if (isset($_POST['q3']) && $_POST['q3'] == 'a') $correct++;
    if (isset($_POST['q4']) && $_POST['q4'] == 'c') $correct++;
    if (isset($_POST['q5']) && $_POST['q5'] == 'b') $correct++;

    // Save quiz result if logged in
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO quiz_results (user_id, score, total_questions) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $correct, $total);
        $stmt->execute();
    }

    // Redirect to GET with quiz result
    header("Location: quiz.php?quiz_done=1&score=$correct");
    exit;
}

/* ---------- Handle GET quiz result ---------- */
if (isset($_GET['quiz_done']) && isset($_GET['score'])) {
    $score = (int) $_GET['score'];
    $total = 5;
    $percentage = ($score / $total) * 100;
    $quiz_result = "$score out of $total correct!";
    $correct = $score; // for message display
}

/* ---------- Fetch Recent Eco-Pledges ---------- */
$pledgesQuery = $conn->query("
SELECT 
    ep.pledge_text,
    ep.created_at,

    -- get user name
    (SELECT d.UserName
        FROM data d
        WHERE d.id = ep.user_id
    )AS UserName

FROM eco_pledges ep
ORDER BY ep.created_at DESC
LIMIT 10;

");

/* ---------- Get Pledge Statistics ---------- */
$stats = $conn->query("SELECT COUNT(*) as total_pledges FROM eco_pledges")->fetch_assoc();
$total_pledges = $stats['total_pledges'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sustainable Tourism - Cultural Echo</title>
    <link rel="icon" href="favicon.jpg">
    <link rel="stylesheet" href="quiz.css">
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
            <li><a href="C/explore_places.php">Take a Tour</a></li>
            <li><a href="booking.php">Book A Flight</a></li>
            <li><a href="add_your_memory.php">Add Memory</a></li>
            <li><a href="quiz.php" class="active">Sustainable Tourism</a></li>
            
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
                <a href="main.php" class="btn btn-outline">Login</a>
                <a href="main.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<header class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
            </svg>
            Travel Responsibly
        </div>
        <h1 class="hero-title">
            <span class="gradient-text">Sustainable Tourism</span>
        </h1>
        <p class="hero-subtitle">
            Explore responsibly, protect cultures, and preserve the planet for future generations.
        </p>
        <div class="hero-stats">
            <div class="stat-card">
                <div class="stat-icon">🌍</div>
                <div class="stat-number"><?= $total_pledges ?></div>
                <div class="stat-label">Eco Pledges</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">♻️</div>
                <div class="stat-number">100%</div>
                <div class="stat-label">Commitment</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🌱</div>
                <div class="stat-number">Together</div>
                <div class="stat-label">We Make Change</div>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<div class="container">

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

    <!-- Why It Matters Section -->
    <section class="info-section">
        <div class="section-icon">🌍</div>
        <h2>Why Sustainable Tourism Matters</h2>
        <p>Sustainable tourism ensures that our adventures today don't harm the environments and communities we visit, preserving them for future generations. It's about making positive impacts while exploring the world.</p>
        <div class="impact-cards">
            <div class="impact-card">
                <div class="impact-icon">🌿</div>
                <h3>Environmental Protection</h3>
                <p>Reduce carbon footprint and protect natural ecosystems</p>
            </div>
            <div class="impact-card">
                <div class="impact-icon">👥</div>
                <h3>Community Support</h3>
                <p>Empower local communities and preserve cultures</p>
            </div>
            <div class="impact-card">
                <div class="impact-icon">🐾</div>
                <h3>Wildlife Conservation</h3>
                <p>Protect endangered species and natural habitats</p>
            </div>
        </div>
    </section>

    <!-- Tips Section -->
    <section class="tips-section">
        <h2>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
            </svg>
            Tips for Traveling Sustainably
        </h2>
        <div class="tips-grid">
            <div class="tip-card">
                <div class="tip-number">1</div>
                <h3>Pack Smart</h3>
                <p>Bring reusable items like bottles, utensils, and shopping bags to reduce waste.</p>
            </div>
            <div class="tip-card">
                <div class="tip-number">2</div>
                <h3>Support Local</h3>
                <p>Buy from artisans and eat at local restaurants to support the community.</p>
            </div>
            <div class="tip-card">
                <div class="tip-number">3</div>
                <h3>Respect Culture</h3>
                <p>Honor traditions and dress codes when visiting sacred sites.</p>
            </div>
            <div class="tip-card">
                <div class="tip-number">4</div>
                <h3>Choose Eco-Friendly</h3>
                <p>Select accommodations and transport that prioritize sustainability.</p>
            </div>
            <div class="tip-card">
                <div class="tip-number">5</div>
                <h3>Protect Wildlife</h3>
                <p>Avoid activities that exploit animals or harm the environment.</p>
            </div>
            <div class="tip-card">
                <div class="tip-number">6</div>
                <h3>Use Public Transport</h3>
                <p>Choose buses, trains, or bicycles to reduce emissions.</p>
            </div>
            <div class="tip-card">
                <div class="tip-number">7</div>
                <h3>Learn Local Language</h3>
                <p>A few words in the local language build meaningful connections.</p>
            </div>
            <div class="tip-card">
                <div class="tip-number">8</div>
                <h3>Travel Off-Season</h3>
                <p>Reduce strain on infrastructure and enjoy quieter experiences.</p>
            </div>
        </div>
    </section>

    <!-- Eco-Destinations Section -->
    <section class="destinations-section">
        <h2>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
            Top Eco-Tourism Destinations
        </h2>
        <p class="section-description">These destinations prioritize sustainable practices while offering unique travel experiences.</p>
        
        <div class="destinations-grid">
            <div class="destination-card">
                <div class="destination-flag">🇨🇷</div>
                <h3>Costa Rica</h3>
                <p>Famous for eco-lodges, rainforest tours, and biodiversity conservation.</p>
                <div class="destination-tags">
                    <span class="tag">Rainforests</span>
                    <span class="tag">Wildlife</span>
                </div>
            </div>
            <div class="destination-card">
                <div class="destination-flag">🇧🇹</div>
                <h3>Bhutan</h3>
                <p>High-value, low-impact tourism with strong cultural preservation.</p>
                <div class="destination-tags">
                    <span class="tag">Culture</span>
                    <span class="tag">Mountains</span>
                </div>
            </div>
            <div class="destination-card">
                <div class="destination-flag">🇳🇴</div>
                <h3>Norway</h3>
                <p>Stunning fjords with strict conservation rules and eco-friendly policies.</p>
                <div class="destination-tags">
                    <span class="tag">Fjords</span>
                    <span class="tag">Nature</span>
                </div>
            </div>
            <div class="destination-card">
                <div class="destination-flag">🇺🇸</div>
                <h3>Alaska, USA</h3>
                <p>Pristine wilderness, glaciers, and diverse wildlife protection.</p>
                <div class="destination-tags">
                    <span class="tag">Glaciers</span>
                    <span class="tag">Wildlife</span>
                </div>
            </div>
            <div class="destination-card">
                <div class="destination-flag">🇫🇮</div>
                <h3>Finland</h3>
                <p>Global leader in eco-tourism with clean air and sustainable practices.</p>
                <div class="destination-tags">
                    <span class="tag">Forests</span>
                    <span class="tag">Clean</span>
                </div>
            </div>
            <div class="destination-card">
                <div class="destination-flag">🇲🇬</div>
                <h3>Madagascar</h3>
                <p>Unique ecosystems and diverse wildlife found nowhere else on Earth.</p>
                <div class="destination-tags">
                    <span class="tag">Unique</span>
                    <span class="tag">Wildlife</span>
                </div>
            </div>
            <div class="destination-card">
                <div class="destination-flag">🇸🇮</div>
                <h3>Slovenia</h3>
                <p>Hidden European gem focusing on forests, lakes, and sustainability.</p>
                <div class="destination-tags">
                    <span class="tag">Lakes</span>
                    <span class="tag">Green</span>
                </div>
            </div>
        </div>
    </section>

       <!-- Interactive Quiz Section -->
    <section class="quiz-section">
        <h2>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            Test Your Sustainable Travel Knowledge
        </h2>
        
        <?php if ($quiz_result): ?>
            <div class="quiz-result">
                <h3>Your Score: <?= $quiz_result ?></h3>
                <p>
                    <?php if ($correct >= 4): ?>
                        🌟 Excellent! You're a sustainable travel champion!
                    <?php elseif ($correct >= 3): ?>
                        👍 Good job! Keep learning about sustainable practices.
                    <?php else: ?>
                        📚 Keep exploring! Every journey towards sustainability counts.
                    <?php endif; ?>
                </p>
                <a href="quiz.php" class="btn btn-primary">Take Quiz Again</a>
            </div>
        <?php else: ?>
            <form method="POST" class="quiz-form">
                <div class="quiz-question">
                    <p><strong>1. What is the main goal of sustainable tourism?</strong></p>
                    <label><input type="radio" name="q1" value="a"> Maximize profits</label>
                    <label><input type="radio" name="q1" value="b"> Attract more tourists</label>
                    <label><input type="radio" name="q1" value="c"> Minimize negative impact on environment and culture</label>
                </div>
                <div class="quiz-question">
                    <p><strong>2. Which is a sustainable travel practice?</strong></p>
                    <label><input type="radio" name="q2" value="a"> Using single-use plastics</label>
                    <label><input type="radio" name="q2" value="b"> Supporting local businesses</label>
                    <label><input type="radio" name="q2" value="c"> Ignoring local customs</label>
                </div>
                <div class="quiz-question">
                    <p><strong>3. What does eco-tourism focus on?</strong></p>
                    <label><input type="radio" name="q3" value="a"> Conservation and education</label>
                    <label><input type="radio" name="q3" value="b"> Luxury accommodations only</label>
                    <label><input type="radio" name="q3" value="c"> Mass tourism</label>
                </div>
                <div class="quiz-question">
                    <p><strong>4. Which country is known for "high-value, low-impact" tourism?</strong></p>
                    <label><input type="radio" name="q4" value="a"> Spain</label>
                    <label><input type="radio" name="q4" value="b"> Italy</label>
                    <label><input type="radio" name="q4" value="c"> Bhutan</label>
                </div>
                <div class="quiz-question">
                    <p><strong>5. What's the best way to reduce your carbon footprint while traveling?</strong></p>
                    <label><input type="radio" name="q5" value="a"> Drive everywhere</label>
                    <label><input type="radio" name="q5" value="b"> Use public transportation</label>
                    <label><input type="radio" name="q5" value="c"> Take multiple short flights</label>
                </div>
                <button type="submit" name="submit_quiz" class="btn btn-primary btn-large">Submit Quiz</button>
            </form>
        <?php endif; ?>
    </section>
    
    <!-- Eco-Pledge Section -->
    <section class="pledge-section">
        <h2>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            Make Your Eco-Pledge
        </h2>
        <p class="section-description">Commit to sustainable travel practices and inspire others!</p>
        
        <?php if (isset($_SESSION['email'])): ?>
            <form method="POST" class="pledge-form">
                <textarea 
                    name="pledge" 
                    rows="4" 
                    placeholder="I pledge to travel sustainably by..."
                    required
                ></textarea>
                <button type="submit" name="submit_pledge" class="btn btn-primary btn-large">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Make My Pledge
                </button>
            </form>
        <?php else: ?>
            <div class="login-prompt">
                <p>Please login to make your eco-pledge!</p>
                <a href="main.php" class="btn btn-primary">Login</a>
            </div>
        <?php endif; ?>
        
        <!-- Recent Pledges -->
        <?php if ($pledgesQuery && $pledgesQuery->num_rows > 0): ?>
            <div class="pledges-list">
                <h3>Recent Eco-Pledges from Our Community</h3>
                <div class="pledges-grid">
                    <?php while ($pledge = $pledgesQuery->fetch_assoc()): ?>
                        <div class="pledge-card">
                            <p class="pledge-text">"<?= htmlspecialchars($pledge['pledge_text']) ?>"</p>
                            <div class="pledge-footer">
                                <span class="pledge-author">— <?= htmlspecialchars($pledge['UserName']) ?></span>
                                <span class="pledge-date"><?= date('M d, Y', strtotime($pledge['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

</div>

<script>
// Smooth scroll animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('section').forEach(section => {
    section.style.opacity = '0';
    section.style.transform = 'translateY(30px)';
    section.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
    observer.observe(section);
});

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
</script>

</body>
</html>