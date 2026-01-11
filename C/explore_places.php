<?php
session_start();
require_once '../config.php';

/* ---------- Fetch All Places with Review Counts and Average Ratings ---------- */
$placesQuery = $conn->query("
   SELECT 
    p.place_id,
    p.place_name,
    p.description,
    p.full_description,
    p.image_url,

    -- count reviews for each place
    (SELECT COUNT(*)
        FROM reviews r
        WHERE r.place_id = p.place_id
    )AS review_count,

    -- calculate average rating for each place
    (SELECT COALESCE(AVG(r2.rating), 0)
        FROM reviews r2
        WHERE r2.place_id = p.place_id
    )AS avg_rating

FROM places p
ORDER BY p.place_name ASC;
");

/* ---------- Fetch Reviews for Each Place ---------- */
function getPlaceReviews($conn, $place_id, $limit = 3) {
    $stmt = $conn->prepare("
       SELECT 
    r.review,
    r.rating,
    r.created_at,
    (
        SELECT d.UserName
        FROM data d
        WHERE d.id = r.user_id
    ) AS UserName
        FROM reviews r
        WHERE r.place_id = ?
        ORDER BY r.created_at DESC
        LIMIT ?
");
    $stmt->bind_param("ii", $place_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore World Cultures - Cultural Echo</title>
    <link rel="icon" href="favicon.jpg">
    <link rel="stylesheet" href="explore_places1.css">
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
            <li><a href="../main.php">Home</a></li>
            <li><a href="../quiz.php">Sustainable Tourism</a></li>
            <li><a href="../booking.php">Book A Flight</a></li>
            <li><a href="../add_your_memory.php">Add Memory</a></li>
            <li><a href="explore_places.php" class="active">Take a Tour</a></li>
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
                <a href="../logout.php" class="btn btn-outline">Logout</a>
            <?php else: ?>
                <a href="../main.php" class="btn btn-outline">Login</a>
                <a href="../main.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            Discover Cultures
        </div>
        <h1 class="hero-title">
            Explore <span class="gradient-text">World Cultures</span>
        </h1>
        <p class="hero-subtitle">
            Discover the beauty and richness of cultures from around the globe. 
            Each destination tells a unique story waiting to be explored.
        </p>
    </div>
</section>

<!-- Main Content -->
<div class="container">
    
    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-bar">
            <div class="search-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="text" id="searchInput" placeholder="Search destinations..." onkeyup="filterPlaces()">
            </div>
            <div class="filter-options">
                <button class="filter-btn active" onclick="filterByRating('all')">All Places</button>
                <button class="filter-btn" onclick="filterByRating('5')">5 Stars</button>
                <button class="filter-btn" onclick="filterByRating('4')">4+ Stars</button>
                <button class="filter-btn" onclick="filterByRating('3')">3+ Stars</button>
            </div>
        </div>
    </div>

    <!-- Places Grid -->
    <div class="places-grid">
        <?php while ($place = $placesQuery->fetch_assoc()): ?>
            <div class="culture-card" data-rating="<?= round($place['avg_rating']) ?>">
                <div class="card-image-wrapper">
                    <img src="<?= htmlspecialchars($place['image_url']) ?>" 
                         alt="<?= htmlspecialchars($place['place_name']) ?>"
                         onerror="this.src='https://via.placeholder.com/400x300?text=<?= urlencode($place['place_name']) ?>'">
                    <div class="card-overlay">
                        <button class="explore-btn" onclick="openModal('modal-<?= $place['place_id'] ?>')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            Explore
                        </button>
                    </div>
                </div>
                
                <div class="card-content">
                    <h3 class="place-name"><?= htmlspecialchars($place['place_name']) ?></h3>
                    <p class="place-description"><?= htmlspecialchars($place['description']) ?></p>
                    
                    <div class="card-footer">
                        <div class="rating-display">
                            <?php 
                            $rating = round($place['avg_rating']);
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <span class="star <?= $i <= $rating ? 'filled' : '' ?>">
                                    <?= $i <= $rating ? '★' : '☆' ?>
                                </span>
                            <?php endfor; ?>
                            <span class="rating-text">
                                <?= number_format($place['avg_rating'], 1) ?>
                            </span>
                        </div>
                        <div class="review-count">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <?= $place['review_count'] ?> reviews
                        </div>
                    </div>
                </div>

                <!-- Modal -->
                <div id="modal-<?= $place['place_id'] ?>" class="modal">
                    <div class="modal-overlay" onclick="closeModal('modal-<?= $place['place_id'] ?>')"></div>
                    <div class="modal-content">
                        <button class="modal-close" onclick="closeModal('modal-<?= $place['place_id'] ?>')">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        
                        <div class="modal-header">
                            
                            <div class="modal-title-wrapper">
                                <h2><?= htmlspecialchars($place['place_name']) ?></h2>
                                <div class="modal-rating">
                                    <?php 
                                    $rating = round($place['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <span class="star <?= $i <= $rating ? 'filled' : '' ?>">★</span>
                                    <?php endfor; ?>
                                    <span>(<?= number_format($place['avg_rating'], 1) ?> / 5)</span>
                                </div>
                            </div>
                        </div>

                        <div class="modal-body">
                            <div class="modal-section">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                        <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                    </svg>
                                    About This Destination
                                </h3>
                                <p><?= nl2br(htmlspecialchars($place['full_description'])) ?></p>
                            </div>

                            <?php 
                            $reviews = getPlaceReviews($conn, $place['place_id'], 3);
                            if ($reviews->num_rows > 0): 
                            ?>
                            <div class="modal-section">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    Recent Reviews
                                </h3>
                                <div class="reviews-list">
                                    <?php while ($review = $reviews->fetch_assoc()): ?>
                                        <div class="review-item">
                                            <div class="review-header-modal">
                                                <div class="review-author-modal">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="12" cy="7" r="4"></circle>
                                                    </svg>
                                                    <strong><?= htmlspecialchars($review['UserName']) ?></strong>
                                                </div>
                                                <div class="review-rating-modal">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span class="star-small <?= $i <= $review['rating'] ? 'filled' : '' ?>">★</span>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <p class="review-text-modal"><?= htmlspecialchars($review['review']) ?></p>
                                            <span class="review-date-modal">
                                                <?= date('M d, Y', strtotime($review['created_at'])) ?>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="modal-actions">
                                <?php if (isset($_SESSION['email'])): ?>
                                    <a href="../add_your_memory.php" class="btn btn-primary btn-large">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                        Share Your Experience
                                    </a>
                                <?php else: ?>
                                    <a href="../main.php" class="btn btn-primary btn-large">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                            <polyline points="10 17 15 12 10 7"></polyline>
                                            <line x1="15" y1="12" x2="3" y2="12"></line>
                                        </svg>
                                        Login to Share Your Experience
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        <?php endwhile; ?>
    </div>

    <!-- Empty State -->
    <div class="empty-state" id="emptyState" style="display: none;">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
        </svg>
        <h3>No destinations found</h3>
        <p>Try adjusting your search or filters</p>
    </div>

</div>

<script>
// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
       // document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = 'auto';
    }
});

// Search Filter
function filterPlaces() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.culture-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const placeName = card.querySelector('.place-name').textContent.toLowerCase();
        const description = card.querySelector('.place-description').textContent.toLowerCase();
        
        if (placeName.includes(searchInput) || description.includes(searchInput)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Show/hide empty state
    document.getElementById('emptyState').style.display = visibleCount === 0 ? 'flex' : 'none';
}

// Rating Filter
function filterByRating(minRating) {
    const cards = document.querySelectorAll('.culture-card');
    const buttons = document.querySelectorAll('.filter-btn');
    let visibleCount = 0;

    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    cards.forEach(card => {
        const rating = parseInt(card.dataset.rating);
        
        if (minRating === 'all' || rating >= parseInt(minRating)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Show/hide empty state
    document.getElementById('emptyState').style.display = visibleCount === 0 ? 'flex' : 'none';
}

// Animate cards on scroll
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

document.querySelectorAll('.culture-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
    observer.observe(card);
});
</script>

</body>
</html>