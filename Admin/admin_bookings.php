<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../main.php");
    exit();
}

$message = '';
$error = '';

// Handle Update Booking Status
if (isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE booking_id = ?");
    $stmt->bind_param("si", $new_status, $booking_id);
    
    if ($stmt->execute()) {
        $message = "Booking status updated successfully";
    } else {
        $error = "Error updating booking status";
    }
}

// Handle Delete Booking
if (isset($_GET['delete'])) {
    $booking_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    
    if ($stmt->execute()) {
        $message = "Booking deleted successfully";
    } else {
        $error = "Error deleting booking";
    }
}

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_clauses[] = "b.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_filter === 'today') {
    $where_clauses[] = "DATE(b.created_at) = CURDATE()";
} elseif ($date_filter === 'week') {
    $where_clauses[] = "b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $where_clauses[] = "b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

if (!empty($search)) {
    $where_clauses[] = "(d.UserName LIKE ? OR d.Email LIKE ? OR p.place_name LIKE ? OR b.booking_id LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch bookings with user and place info
$query = "
 SELECT 
    b.*,

    -- get user name
    (SELECT d.UserName
        FROM data d
        WHERE d.id = b.user_id
    ) AS UserName,

    -- get user email
    (SELECT d.Email
        FROM data d
        WHERE d.id = b.user_id
    ) AS Email,

    -- get place name
    (SELECT p.place_name
        FROM places p
        WHERE p.place_id = b.place_id
    )AS place_name,

    -- get place image
    (SELECT p.image_url
        FROM places p
        WHERE p.place_id = b.place_id
    )AS image_url,

    -- calculate days until departure
    DATEDIFF(b.departure_date, CURDATE()) AS days_until_departure

FROM bookings b
$where_sql
ORDER BY b.created_at DESC;
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $bookingsResult = $stmt->get_result();
} else {
    $bookingsResult = $conn->query($query);
}

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_bookings,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings,
        SUM(total_price) as total_revenue,
        SUM(CASE WHEN status = 'confirmed' THEN total_price ELSE 0 END) as confirmed_revenue,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_bookings
    FROM bookings
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - Cultural Echo Admin</title>
    <link rel="icon" href="favicon.jpg">
    <link rel="stylesheet" href="admin_bookings.css">
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
            <li><a href="admin_page.php">User Management</a></li>
            <li><a href="admin_bookings.php" class="active">Bookings Management</a></li>
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
            <h1>Booking Management</h1>
            <p>Manage flight bookings, update status, and monitor revenue</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_bookings']) ?></h3>
                <p>Total Bookings</p>
                <span class="stat-badge">+<?= $stats['today_bookings'] ?> today</span>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['pending_bookings']) ?></h3>
                <p>Pending Bookings</p>
                <span class="stat-badge">Needs attention</span>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['confirmed_bookings']) ?></h3>
                <p>Confirmed</p>
                <span class="stat-badge">Active bookings</span>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3>$<?= number_format($stats['confirmed_revenue'], 2) ?></h3>
                <p>Confirmed Revenue</p>
                <span class="stat-badge">From confirmed bookings</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label>Status:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Date:</label>
                <select name="date" onchange="this.form.submit()">
                    <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>This Month</option>
                </select>
            </div>
            
            <div class="filter-group search-group">
                <label>Search:</label>
                <input type="text" name="search" placeholder="Search bookings..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    Search
                </button>
            </div>
            
            <?php if ($status_filter !== 'all' || $date_filter !== 'all' || !empty($search)): ?>
                <a href="admin_bookings.php" class="btn btn-outline">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bookings Grid -->
    <div class="bookings-grid">
        <?php if ($bookingsResult->num_rows > 0): ?>
            <?php while ($booking = $bookingsResult->fetch_assoc()): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <span class="booking-status status-<?= $booking['status'] ?>">
                            <?= ucfirst($booking['status']) ?>
                        </span>
                        <span class="booking-id-badge">#<?= $booking['booking_id'] ?></span>
                    </div>
                    
                    <div class="booking-body">
                        <h3><?= htmlspecialchars($booking['place_name']) ?></h3>
                        
                        <div class="booking-user">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span><?= htmlspecialchars($booking['UserName']) ?></span>
                        </div>
                        
                        <div class="booking-details-grid">
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <div>
                                    <small>Departure</small>
                                    <strong><?= date('M d, Y', strtotime($booking['departure_date'])) ?></strong>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <div>
                                    <small>Return</small>
                                    <strong><?= date('M d, Y', strtotime($booking['return_date'])) ?></strong>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                </svg>
                                <div>
                                    <small>Passengers</small>
                                    <strong><?= $booking['passengers'] ?></strong>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                </svg>
                                <div>
                                    <small>Class</small>
                                    <strong><?= ucfirst($booking['class']) ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($booking['days_until_departure'] >= 0): ?>
                            <div class="departure-alert <?= $booking['days_until_departure'] <= 7 ? 'urgent' : '' ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Departs in <?= $booking['days_until_departure'] ?> day<?= $booking['days_until_departure'] != 1 ? 's' : '' ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="booking-footer">
                            <div class="booking-price">$<?= number_format($booking['total_price'], 2) ?></div>
                            <div class="booking-date">
                                <small>Booked: <?= date('M d, Y', strtotime($booking['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="booking-actions">
                        <button class="btn-action btn-edit" onclick="openStatusModal(<?= $booking['booking_id'] ?>, '<?= $booking['status'] ?>')" title="Update Status">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Update Status
                        </button>
                        <button class="btn-action btn-view" onclick="openDetailsModal(<?= htmlspecialchars(json_encode($booking), ENT_QUOTES) ?>)" title="View Details">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            Details
                        </button>
                        <a href="?delete=<?= $booking['booking_id'] ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this booking?')" title="Delete">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Delete
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <h3>No bookings found</h3>
                <p>Try adjusting your filters or search terms</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Update Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-overlay" onclick="closeStatusModal()"></div>
    <div class="modal-content">
        <button class="modal-close" onclick="closeStatusModal()">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <div class="modal-header">
            <h2>Update Booking Status</h2>
            <p>Change the status of this booking</p>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="booking_id" id="status_booking_id">
            <div class="input-group">
                <label for="status">Booking Status</label>
                <select name="status" id="status" required>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <button type="submit" name="update_status" class="btn btn-primary btn-full">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Update Status
            </button>
        </form>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-overlay" onclick="closeDetailsModal()"></div>
    <div class="modal-content modal-large">
        <button class="modal-close" onclick="closeDetailsModal()">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <div class="modal-header">
            <h2>Booking Details</h2>
        </div>
        <div id="detailsContent" class="details-content">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<script>
// Status Modal
function openStatusModal(bookingId, currentStatus) {
    document.getElementById('status_booking_id').value = bookingId;
    document.getElementById('status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Details Modal
function openDetailsModal(booking) {
    const content = `
        <div class="details-grid">
            <div class="detail-section">
                <h3>Booking Information</h3>
                <p><strong>Booking ID:</strong> #${booking.booking_id}</p>
                <p><strong>Status:</strong> <span class="badge badge-${booking.status}">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</span></p>
                <p><strong>Booking Date:</strong> ${new Date(booking.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</p>
            </div>
            
            <div class="detail-section">
                <h3>Customer Information</h3>
                <p><strong>Name:</strong> ${booking.UserName}</p>
                <p><strong>Email:</strong> ${booking.Email}</p>
            </div>
            
            <div class="detail-section">
                <h3>Flight Details</h3>
                <p><strong>Destination:</strong> ${booking.place_name}</p>
                <p><strong>Departure:</strong> ${new Date(booking.departure_date).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</p>
                <p><strong>Return:</strong> ${new Date(booking.return_date).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</p>
                <p><strong>Passengers:</strong> ${booking.passengers}</p>
                <p><strong>Class:</strong> ${booking.class.charAt(0).toUpperCase() + booking.class.slice(1)}</p>
            </div>
            
            <div class="detail-section">
                <h3>Payment Information</h3>
                <p><strong>Total Price:</strong> <span style="font-size: 1.5rem; color: var(--primary-color);">$${parseFloat(booking.total_price).toFixed(2)}</span></p>
            </div>
        </div>
    `;
    
    document.getElementById('detailsContent').innerHTML = content;
    document.getElementById('detailsModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.animation = 'slideOut 0.5s ease-out forwards';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Close modals on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStatusModal();
        closeDetailsModal();
    }
});
</script>

</body>
</html>