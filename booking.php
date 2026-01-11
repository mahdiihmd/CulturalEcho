<?php
session_start();
require_once 'config.php';

$message = "";
$error = "";

/* ---------- Handle Booking Submission ---------- */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_booking'])) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $error = "Please login to book a flight.";
    } else {
        // Get form data
        $user_id = intval($_SESSION['user_id']);
        $place_id = isset($_POST['destination']) ? intval($_POST['destination']) : 0;
        $departure_date = isset($_POST['departure_date']) ? trim($_POST['departure_date']) : '';
        $return_date = isset($_POST['return_date']) ? trim($_POST['return_date']) : '';
        $passengers = isset($_POST['passengers']) ? intval($_POST['passengers']) : 0;
        $class = isset($_POST['class']) ? trim($_POST['class']) : '';
        $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;
        
        // Validation
        if (empty($place_id)) {
            $error = "Please select a destination.";
        } elseif (empty($departure_date)) {
            $error = "Please select a departure date.";
        } elseif (empty($return_date)) {
            $error = "Please select a return date.";
        } elseif (strtotime($departure_date) < strtotime(date('Y-m-d'))) {
            $error = "Departure date cannot be in the past.";
        } elseif (strtotime($return_date) <= strtotime($departure_date)) {
            $error = "Return date must be after departure date.";
        } elseif ($passengers < 1 || $passengers > 10) {
            $error = "Number of passengers must be between 1 and 10.";
        } elseif (empty($class) || !in_array($class, ['economy', 'business', 'first'])) {
            $error = "Please select a valid class.";
        } else {
            // Prepare SQL statement
            $sql = "INSERT INTO bookings (user_id, place_id, departure_date, return_date, passengers, class, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                $error = "Database error: " . $conn->error;
            } else {
                // Bind parameters: i=integer, s=string, d=double
                $stmt->bind_param("iissisd", $user_id, $place_id, $departure_date, $return_date, $passengers, $class, $total_price);
                
                // Execute the statement
                if ($stmt->execute()) {
                    $booking_id = $stmt->insert_id;
                    
                    header("Location: booking.php?success=1&booking_id=" . $booking_id);
                    exit;
                } else {
                    $error = "Error saving booking: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
    }
}
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['booking_id'])) {
    $message = "Booking successful! Your booking ID is #" . intval($_GET['booking_id']) . ". Your booking is pending confirmation.";
}



/* ---------- Fetch Available Destinations ---------- */
$placesQuery = $conn->query("SELECT place_id, place_name, image_url FROM places ORDER BY place_name ASC");

if (!$placesQuery) {
    die("Error fetching places: " . $conn->error);
}

/* ---------- Fetch User's Bookings ---------- */
$userBookings = null;
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    
    $sql = "SELECT b.*, p.place_name, p.image_url, DATEDIFF(b.departure_date, CURDATE()) as days_until_departure 
            FROM bookings b 
            JOIN places p ON b.place_id = p.place_id 
            WHERE b.user_id = ? 
            ORDER BY b.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userBookings = $stmt->get_result();
}

/* ---------- Base Prices by Class ---------- */
$basePrices = [
    'economy' => 500,
    'business' => 1200,
    'first' => 2500
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Flight - Cultural Echo</title>
    <link rel="icon" href="favicon.jpg">
    <link rel="stylesheet" href="booking1.css">
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
            <li><a href="C/explore_places.php">Explore</a></li>
            <li><a href="add_your_memory.php">Add Memory</a></li>
            <li><a href="quiz.php">Sustainable Tourism</a></li>
            <li><a href="booking.php" class="active">Book Flight</a></li>
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
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            </svg>
            Travel Made Easy
        </div>
        <h1 class="hero-title">
            Book Your <span class="gradient-text">Dream Flight</span>
        </h1>
        <p class="hero-subtitle">
            Discover amazing destinations around the world. Book your flight today and start your adventure!
        </p>
    </div>
</section>

<!-- Main Content -->
<div class="container">

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success" id="successAlert">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error" id="errorAlert">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Booking Form Section -->
    <?php if (isset($_SESSION['email']) && isset($_SESSION['user_id'])): ?>
    <section class="booking-section">
        <div class="section-header">
            <h2>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 12L3 12M23 12L16 5M23 12L16 19"></path>
                </svg>
                Search & Book Your Flight
            </h2>
            <p>Fill in your travel details to find the best flights</p>
        </div>

        <form method="POST" class="booking-form" id="bookingForm">
            <div class="form-grid">
                <!-- Destination -->
                <div class="input-group full-width">
                    <label for="destination">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Destination *
                    </label>
                    <select name="destination" id="destination" required>
                        <option value="">Select your destination</option>
                        <?php 
                        if ($placesQuery && $placesQuery->num_rows > 0) {
                            while ($place = $placesQuery->fetch_assoc()): 
                        ?>
                            <option value="<?= $place['place_id'] ?>">
                                <?= htmlspecialchars($place['place_name']) ?>
                            </option>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <!-- Departure Date -->
                <div class="input-group">
                    <label for="departure_date">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        Departure Date *
                    </label>
                    <input type="date" name="departure_date" id="departure_date" min="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- Return Date -->
                <div class="input-group">
                    <label for="return_date">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        Return Date *
                    </label>
                    <input type="date" name="return_date" id="return_date" min="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- Passengers -->
                <div class="input-group">
                    <label for="passengers">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Passengers *
                    </label>
                    <input type="number" name="passengers" id="passengers" value="1" min="1" max="10" required>
                </div>

                <!-- Class -->
                <div class="input-group">
                    <label for="class">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                            <path d="M2 17l10 5 10-5"></path>
                            <path d="M2 12l10 5 10-5"></path>
                        </svg>
                        Class *
                    </label>
                    <select name="class" id="class" required>
                        <option value="economy">Economy - $500 per person</option>
                        <option value="business">Business - $1,200 per person</option>
                        <option value="first">First Class - $2,500 per person</option>
                    </select>
                </div>
            </div>

            <!-- Price Summary -->
            <div class="price-summary">
                <div class="price-breakdown">
                    <div class="price-item">
                        <span>Base Price:</span>
                        <span id="basePrice">$500</span>
                    </div>
                    <div class="price-item">
                        <span>Passengers:</span>
                        <span id="passengerCount">1</span>
                    </div>
                    <div class="price-item total">
                        <span>Total Price:</span>
                        <span id="totalPrice">$500</span>
                    </div>
                </div>
            </div>

            <input type="hidden" name="total_price" id="total_price_input" value="500">
        
            <button type="submit" name="submit_booking" id="submitBtn" class="btn btn-primary btn-large btn-full">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14"></path>
                    <path d="M12 5l7 7-7 7"></path>
                </svg>
                Book Flight Now
            </button>
        </form>
    </section>
    <?php else: ?>
    <section class="login-required">
        <div class="login-card">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                <polyline points="10 17 15 12 10 7"></polyline>
                <line x1="15" y1="12" x2="3" y2="12"></line>
            </svg>
            <h2>Login Required</h2>
            <p>Please login to book your flight and manage your bookings.</p>
            <a href="main.php" class="btn btn-primary btn-large">Login / Sign Up</a>
        </div>
    </section>
    <?php endif; ?>

    <!-- User's Bookings -->
    <?php if ($userBookings && $userBookings->num_rows > 0): ?>
    <section class="bookings-section">
        <div class="section-header">
            <h2>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Your Bookings
            </h2>
            <p>Manage and track your flight bookings</p>
        </div>

        <div class="bookings-grid">
            <?php while ($booking = $userBookings->fetch_assoc()): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="booking-status status-<?= htmlspecialchars($booking['status']) ?>">
                            <?= ucfirst(htmlspecialchars($booking['status'])) ?>
                        </div>
                    </div>
                    <div class="booking-content">
                        <h3><?= htmlspecialchars($booking['place_name']) ?></h3>
                        
                        <?php if ($booking['days_until_departure'] >= 0): ?>
                            <div class="departure-countdown <?= $booking['days_until_departure'] <= 7 ? 'urgent' : '' ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span>Departs in <?= $booking['days_until_departure'] ?> day<?= $booking['days_until_departure'] != 1 ? 's' : '' ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="booking-details">
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <span><?= date('M d, Y', strtotime($booking['departure_date'])) ?> - <?= date('M d, Y', strtotime($booking['return_date'])) ?></span>
                            </div>
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                </svg>
                                <span><?= $booking['passengers'] ?> Passenger<?= $booking['passengers'] > 1 ? 's' : '' ?></span>
                            </div>
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                </svg>
                                <span><?= ucfirst(htmlspecialchars($booking['class'])) ?> Class</span>
                            </div>
                        </div>
                        
                        <?php if ($booking['status'] === 'pending'): ?>
                            <div class="status-info">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <span>Your booking is pending admin confirmation</span>
                            </div>
                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                            <div class="status-info confirmed">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>Your booking is confirmed! Have a great trip!</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="booking-footer">
                            <div class="booking-price">$<?= number_format($booking['total_price'], 2) ?></div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<script>
// Price calculation
const basePrices = {
    'economy': 500,
    'business': 1200,
    'first': 2500
};

function updatePrice() {
    const classSelect = document.getElementById('class');
    const passengersInput = document.getElementById('passengers');
    
    if (!classSelect || !passengersInput) return;
    
    const selectedClass = classSelect.value;
    const passengers = parseInt(passengersInput.value) || 1;
    const basePrice = basePrices[selectedClass] || 500;
    const totalPrice = basePrice * passengers;
    
    document.getElementById('basePrice').textContent = '$' + basePrice.toLocaleString();
    document.getElementById('passengerCount').textContent = passengers;
    document.getElementById('totalPrice').textContent = '$' + totalPrice.toLocaleString();
    document.getElementById('total_price_input').value = totalPrice;
}

// Initialize price on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePrice();
});

// Event listeners for price updates
const classSelect = document.getElementById('class');
const passengersInput = document.getElementById('passengers');

if (classSelect) {
    classSelect.addEventListener('change', updatePrice);
}

if (passengersInput) {
    passengersInput.addEventListener('input', updatePrice);
}

// Auto-hide alerts after 7 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 500);
    });
}, 7000);



</script>

</body>
</html>