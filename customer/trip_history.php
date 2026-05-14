<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is logged in as a customer
checkAuth('customer');

$user_id = $_SESSION['user_id'];

// Get summary stats
$stats_query = "SELECT 
    COUNT(id) as total_trips,
    IFNULL(SUM(DATEDIFF(end_date, start_date) + 1), 0) as total_days
    FROM travel_plans 
    WHERE user_id = ? AND status = 'completed'";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats_result = $stmt->get_result()->fetch_assoc();

$spent_query = "SELECT IFNULL(SUM(total_price), 0) as total_spent FROM bookings WHERE user_id = ? AND (status = 'completed' OR status = 'confirmed')";
$stmt = $conn->prepare($spent_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$spent_result = $stmt->get_result()->fetch_assoc();

$rating_query = "SELECT IFNULL(AVG(rating), 0) as avg_rating FROM reviews WHERE reviewer_id = ?";
$stmt = $conn->prepare($rating_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rating_result = $stmt->get_result()->fetch_assoc();

// Get unique destinations count (for "Top Destination" stat)
$dest_query = "SELECT location_name, COUNT(*) as count FROM destinations d 
               JOIN travel_plans tp ON d.plan_id = tp.id 
               WHERE tp.user_id = ? GROUP BY location_name ORDER BY count DESC LIMIT 1";
$stmt = $conn->prepare($dest_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$top_dest = $stmt->get_result()->fetch_assoc();

// Get total distance (simulation or actual if available in future, for now let's use a scale of days * 100km)
$total_distance = $stats_result['total_days'] * 125;

// Fetch trips (Grouped by Plan if available)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$order_by = "MAX(b.created_at) DESC";
if ($sort === 'oldest') $order_by = "MAX(b.created_at) ASC";
if ($sort === 'cost') $order_by = "SUM(b.total_price) DESC";

$where_clause = "b.user_id = ? AND (b.status = 'completed' OR b.status = 'confirmed' OR b.status = 'pending')";
if ($filter === 'reviewed') {
    $where_clause .= " AND r.id IS NOT NULL";
} elseif ($filter === 'needs_review') {
    $where_clause .= " AND r.id IS NULL AND b.status = 'completed'";
}

// Optimized query: Group by plan_id to avoid "duplication" of cards for same trip
$trips_query = "SELECT 
                    b.plan_id,
                    GROUP_CONCAT(b.id) as booking_ids,
                    GROUP_CONCAT(b.type) as booking_types,
                    GROUP_CONCAT(b.status) as booking_statuses,
                    GROUP_CONCAT(b.reference_no) as reference_nos,
                    SUM(b.total_price) as grand_total,
                    MAX(b.status) as latest_status,
                    tp.name as plan_name, tp.start_date as tp_start, tp.end_date as tp_end,
                    MAX(r.rating) as rating, MAX(r.id) as review_id,
                    MAX(u_partner.name) as partner_name, MAX(u_partner.profile_pic) as partner_pic,
                    MAX(v.model) as vehicle_model, MAX(v.image_path) as vehicle_img,
                    MAX(h.name) as hotel_name, MAX(h.image_path) as hotel_img,
                    MAX(b.start_date) as b_start, MAX(b.end_date) as b_end
                FROM bookings b
                LEFT JOIN travel_plans tp ON b.plan_id = tp.id
                LEFT JOIN reviews r ON b.id = r.booking_id
                LEFT JOIN users u_partner ON b.assigned_partner_id = u_partner.id
                LEFT JOIN vehicles v ON (b.type = 'vehicle' AND b.item_id = v.id)
                LEFT JOIN hotels h ON (b.type = 'hotel' AND b.item_id = h.id)
                WHERE $where_clause
                GROUP BY b.plan_id, (CASE WHEN b.plan_id IS NULL THEN b.id ELSE 0 END)
                ORDER BY $order_by";

$stmt = $conn->prepare($trips_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trips_res = $stmt->get_result();
$trips = [];
while ($row = $trips_res->fetch_assoc()) {
    $row['booking_id'] = explode(',', $row['booking_ids'])[0]; // Principal ID for links
    $row['types_array'] = explode(',', $row['booking_types']);
    
    if (!$row['plan_id']) {
        $row['name'] = (in_array('vehicle', $row['types_array'])) ? "Vehicle Rental: " . $row['vehicle_model'] : "Hotel Stay: " . $row['hotel_name'];
        $row['start_date'] = $row['b_start']; 
        $row['end_date'] = $row['b_end'];
        $row['cities'] = (in_array('vehicle', $row['types_array'])) ? ['Car Rental'] : ['Accommodation'];
    } else {
        $row['name'] = $row['plan_name'];
        $row['start_date'] = $row['tp_start'];
        $row['end_date'] = $row['tp_end'];
        
        $dest_stmt = $conn->prepare("SELECT location_name FROM destinations WHERE plan_id = ? ORDER BY day_number ASC");
        $dest_stmt->bind_param("i", $row['plan_id']);
        $dest_stmt->execute();
        $dest_res = $dest_stmt->get_result();
        $destinations = [];
        while ($dest_row = $dest_res->fetch_assoc()) {
            $destinations[] = $dest_row['location_name'];
        }
        $row['cities'] = $destinations;
    }

    // Combined Price
    $row['total_price'] = $row['grand_total'];
    $row['reference_no'] = explode(',', $row['reference_nos'])[0];

    // Determine representative image
    if (in_array('vehicle', $row['types_array']) && !empty($row['vehicle_img'])) {
        $row['display_img'] = getVehicleImage($row['vehicle_img'], '../');
    } elseif (in_array('hotel', $row['types_array']) && !empty($row['hotel_img'])) {
        $row['display_img'] = '../assets/images/' . $row['hotel_img'];
    } else {
        $row['display_img'] = '../assets/images/placeholder.jpg';
    }

    $trips[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip History - TripSync</title>
    <meta name="description"
        content="TripSync is your trusted trip planning platform connecting travelers with hotels, drivers, and travel agencies in Sri Lanka.">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script type="module" src="../assets/js/main.js"></script>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>

<body>
    <div id="root">
        <div class="min-h-screen bg-gradient-to-br from-gray-50 to-teal-50/30">
            <?php include 'includes/navbar.php'; ?>

            <div class="pt-24 pb-16 px-4">
                <div class="max-w-6xl mx-auto">
                    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 mb-1">Trip History</h1>
                            <p class="text-gray-500 text-sm">All your past completed trips with receipts and reviews</p>
                        </div>
                        <a class="flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer"
                            href="plan_trip.php" data-discover="true">
                            <div class="w-4 h-4 flex items-center justify-center"><i class="ri-add-line text-sm"></i>
                            </div>Plan New Trip
                        </a>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                        <div class="bg-teal-50 rounded-xl p-4">
                            <div class="w-9 h-9 flex items-center justify-center bg-teal-100 rounded-lg mb-2"><i
                                    class="ri-suitcase-3-line text-lg text-teal-600"></i></div>
                            <p class="text-lg font-bold text-gray-900"><?php echo $stats_result['total_trips']; ?></p>
                            <p class="text-xs text-gray-500">Total Trips</p>
                        </div>
                        <div class="bg-orange-50 rounded-xl p-4">
                            <div class="w-9 h-9 flex items-center justify-center bg-orange-100 rounded-lg mb-2"><i
                                    class="ri-money-dollar-circle-line text-lg text-orange-600"></i></div>
                            <p class="text-lg font-bold text-gray-900"><?php echo formatCurrency($spent_result['total_spent']); ?></p>
                            <p class="text-xs text-gray-500">Total Spent</p>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-4">
                            <div class="w-9 h-9 flex items-center justify-center bg-emerald-100 rounded-lg mb-2"><i
                                    class="ri-calendar-check-line text-lg text-emerald-600"></i></div>
                            <p class="text-lg font-bold text-gray-900"><?php echo $stats_result['total_days']; ?></p>
                            <p class="text-xs text-gray-500">Travel Days</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl p-4">
                            <div class="w-9 h-9 flex items-center justify-center bg-amber-100 rounded-lg mb-2"><i
                                    class="ri-star-line text-lg text-amber-600"></i></div>
                            <p class="text-lg font-bold text-gray-900"><?php echo number_format($rating_result['avg_rating'], 1); ?></p>
                            <p class="text-xs text-gray-500">Avg Rating</p>
                        </div>
                        <div class="bg-rose-50 rounded-xl p-4">
                            <div class="w-9 h-9 flex items-center justify-center bg-rose-100 rounded-lg mb-2"><i
                                    class="ri-heart-3-line text-lg text-rose-600"></i></div>
                            <p class="text-lg font-bold text-gray-900 overflow-hidden text-ellipsis whitespace-nowrap" title="<?php echo $top_dest['location_name'] ?? 'N/A'; ?>">
                                <?php echo $top_dest['location_name'] ?? 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-500">Top Destination</p>
                        </div>
                        <div class="bg-indigo-50 rounded-xl p-4">
                            <div class="w-9 h-9 flex items-center justify-center bg-indigo-100 rounded-lg mb-2"><i
                                    class="ri-road-map-line text-lg text-indigo-600"></i></div>
                            <p class="text-lg font-bold text-gray-900"><?php echo number_format($total_distance / 1000, 1) . 'K'; ?> km</p>
                            <p class="text-xs text-gray-500">Distance</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
                        <div class="flex items-center gap-1 bg-white rounded-full p-1 shadow-sm border border-gray-200">
                            <a href="?filter=all"
                                class="px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap <?php echo $filter === 'all' ? 'bg-teal-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'; ?>">All
                                Trips</a>
                            <a href="?filter=reviewed"
                                class="px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap <?php echo $filter === 'reviewed' ? 'bg-teal-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'; ?>">Reviewed</a>
                            <a href="?filter=needs_review"
                                class="px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap <?php echo $filter === 'needs_review' ? 'bg-teal-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'; ?>">Needs
                                Review</a>
                        </div>
                        <form method="GET" class="flex gap-2">
                            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                            <select name="sort" onchange="this.form.submit()"
                                class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm text-gray-700 focus:ring-2 focus:ring-teal-500 focus:border-transparent cursor-pointer">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="cost" <?php echo $sort === 'cost' ? 'selected' : ''; ?>>Highest Cost</option>
                            </select>
                        </form>
                    </div>

                    <div class="space-y-5">
                        <?php if (empty($trips)): ?>
                            <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="ri-suitcase-line text-4xl text-gray-300"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">No trips found</h3>
                                <p class="text-gray-500 mb-6">You haven't any trips in this category yet. Start planning your next adventure!</p>
                                <a href="plan_trip.php" class="inline-flex items-center gap-2 px-6 py-3 bg-teal-600 text-white font-medium rounded-full hover:bg-teal-700 transition-all shadow-lg hover:shadow-teal-200">
                                    <i class="ri-add-line"></i> Plan a New Trip
                                </a>
                            </div>
                        <?php
else: ?>
                        <?php foreach ($trips as $trip): ?>
                            <?php
        $start_date = new DateTime($trip['start_date']);
        $end_date = new DateTime($trip['end_date']);
        $interval = $start_date->diff($end_date);
        $days = $interval->days + 1;

        $img_path = $trip['display_img'] ?: '../assets/images/placeholder.jpg';
        $status = $trip['latest_status'];
?>
                            <div
                                class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-all">
                                <div class="flex flex-col md:flex-row">
                                    <div class="w-full md:w-64 h-48 md:h-auto flex-shrink-0 relative">
                                        <img alt="<?php echo clean($trip['name']); ?>" class="w-full h-full object-cover object-top"
                                            src="<?php echo $img_path; ?>">
                                        <div class="absolute top-3 left-3">
                                            <?php if ($status == 'confirmed'): ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap bg-emerald-500 text-white shadow-sm ring-2 ring-emerald-200">Confirmed</span>
                                            <?php
        elseif ($trip['review_id']): ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-teal-600 text-white">Reviewed</span>
                                            <?php
        elseif ($status == 'completed'): ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-amber-500 text-white">Needs Review</span>
                                            <?php
        elseif ($status == 'pending'): ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-indigo-500 text-white">Pending Payment</span>
                                            <?php
        else: ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap bg-gray-500 text-white"><?php echo ucfirst($status ?? 'Upcoming'); ?></span>
                                            <?php
        endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex-1 p-5">
                                        <div class="flex items-start justify-between mb-3">
                                            <div>
                                                <h3 class="text-lg font-bold text-gray-900 mb-1"><?php echo clean($trip['name']); ?></h3>
                                                <p class="text-xs text-gray-500"><i class="ri-calendar-line mr-1"></i>
                                                    <?php echo $start_date->format('M j, Y'); ?> — <?php echo $end_date->format('M j, Y'); ?> ·
                                                    <?php echo $days; ?> days</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-lg font-bold text-gray-900"><?php echo formatCurrency($trip['total_price']); ?></p>
                                                <p class="text-xs text-gray-400">Ref: <?php echo $trip['reference_no']; ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 mb-4 flex-wrap">
                                            <?php foreach ($trip['cities'] as $i => $city): ?>
                                                <span class="flex items-center gap-1.5 text-xs text-gray-600">
                                                    <?php echo clean($city); ?>
                                                    <?php if ($i < count($trip['cities']) - 1): ?>
                                                        <i class="ri-arrow-right-s-line text-gray-300 text-xs"></i>
                                                    <?php
            endif; ?>
                                                </span>
                                            <?php
        endforeach; ?>
                                        </div>
                                        <div class="flex items-center gap-6 mb-4 flex-wrap">
                                            <?php if (in_array('vehicle', $trip['types_array'])): ?>
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 bg-gray-100 flex items-center justify-center border border-gray-100">
                                                    <?php if (!empty($trip['partner_name'])): ?>
                                                        <img src="<?php echo getProfilePic($trip['partner_pic'], '../'); ?>" class="w-full h-full object-cover" title="<?php echo htmlspecialchars($trip['partner_name']); ?>">
                                                    <?php
        else: ?>
                                                        <i class="ri-user-line text-gray-400 text-sm"></i>
                                                    <?php
        endif; ?>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-900"><?php echo !empty($trip['partner_name']) ? htmlspecialchars($trip['partner_name']) : 'Partner Assigned'; ?></p>
                                                    <p class="text-xs text-gray-400">Verified Driver</p>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (in_array('hotel', $trip['types_array'])): ?>
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="w-8 h-8 flex items-center justify-center bg-teal-50 rounded-full flex-shrink-0">
                                                    <i class="ri-hotel-line text-sm text-teal-600"></i>
                                                </div>
                                                <p class="text-xs text-gray-600">Premium Stay Included</p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center justify-between flex-wrap gap-3">
                                            <div>
                                                <?php if ($trip['review_id']): ?>
                                                    <div class="flex items-center gap-1">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="text-sm ri-star-fill <?php echo $i <= $trip['rating'] ? 'text-amber-400' : 'text-gray-200'; ?>"></i>
                                                        <?php
            endfor; ?>
                                                        <span class="text-xs text-gray-500 ml-1">(<?php echo number_format($trip['rating'], 1); ?>)</span>
                                                    </div>
                                                <?php
        else: ?>
                                                    <span class="text-xs text-amber-600 font-medium"><i
                                                        class="ri-star-line mr-1"></i>Rate this trip
                                                    to earn points</span>
                                                <?php
        endif; ?>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a href="../api/receipt.php?booking_id=<?php echo $trip['booking_id']; ?>" target="_blank"
                                                    class="flex items-center gap-1.5 px-4 py-2 border border-gray-200 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap cursor-pointer"><i
                                                        class="ri-file-list-3-line text-xs"></i>Receipt</a>
                                                
                                                <?php if ($status == 'confirmed'): ?>
                                                    <a class="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700 transition-colors whitespace-nowrap cursor-pointer"
                                                        href="track_trip.php?booking_id=<?php echo $trip['booking_id']; ?>" data-discover="true"><i
                                                            class="ri-map-pin-line text-xs"></i>Track Trip</a>
                                                <?php
        endif; ?>

                                                <?php if ($trip['review_id']): ?>
                                                    <a class="flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-200 transition-colors whitespace-nowrap cursor-pointer"
                                                        href="reviews.php" data-discover="true"><i
                                                            class="ri-eye-line text-xs"></i>View Review</a>
                                                <?php
        elseif ($status == 'completed'): ?>
                                                    <a class="flex items-center gap-1.5 px-4 py-2 bg-teal-600 text-white text-xs font-medium rounded-lg hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer"
                                                        href="rate_trip.php?booking_id=<?php echo $trip['booking_id']; ?>" data-discover="true"><i
                                                            class="ri-star-line text-xs"></i>Write Review</a>
                                                <?php
        endif; ?>
                                                
                                                <a class="flex items-center gap-1.5 px-4 py-2 border border-gray-200 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap cursor-pointer"
                                                    href="messages.php" data-discover="true"><i
                                                        class="ri-message-3-line text-xs"></i>Message</a>

                                                <?php if ($status == 'pending' || $status == 'confirmed'): ?>
                                                    <button onclick="cancelBooking(<?php echo $trip['booking_id']; ?>)" 
                                                        class="flex items-center gap-1.5 px-4 py-2 border border-rose-200 text-rose-600 text-xs font-bold rounded-lg hover:bg-rose-50 transition-colors whitespace-nowrap cursor-pointer">
                                                        <i class="ri-close-circle-line text-xs"></i>Cancel Booking
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
    endforeach; ?>
                        <?php
endif; ?>
                    </div>
                </div>
            </div>

            <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div>
                            <img alt="TripSync Logo" class="h-12 w-auto mb-4" src="../assets/images/logo.png">
                            <p class="text-teal-50 text-sm leading-relaxed">Your trusted partner for seamless travel
                                planning across
                                Sri Lanka.</p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Quick Links</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="dashboard.php" data-discover="true">Dashboard</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="plan_trip.php" data-discover="true">Plan Trip</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="marketplace.php" data-discover="true">Marketplace</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="wallet.php" data-discover="true">Wallet</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="trip_history.php" data-discover="true">Trip History</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="messages.php" data-discover="true">Messages</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="booking_confirmation.php" data-discover="true">Booking Confirmation</a>
                                </li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#" data-discover="true">Become a Partner</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Support</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="help.php" data-discover="true">Help Center</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#" data-discover="true">Contact Us</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#" data-discover="true">Terms of Service</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#" data-discover="true">Privacy Policy</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Connect With Us</h3>
                            <div class="flex gap-3 mb-4">
                                <a href="https://facebook.com/" target="_blank" rel="noopener noreferrer"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer">
                                    <i class="ri-facebook-fill text-lg"></i>
                                </a>
                                <a href="https://instagram.com/" target="_blank" rel="noopener noreferrer"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer">
                                    <i class="ri-instagram-line text-lg"></i>
                                </a>
                                <a href="https://twitter.com/" target="_blank" rel="noopener noreferrer"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer">
                                    <i class="ri-twitter-x-line text-lg"></i>
                                </a>
                            </div>
                            <p class="text-teal-50 text-sm"><i class="ri-mail-line mr-2"></i>info@tripsync.lk</p>
                        </div>
                    </div>
                    <div class="border-t border-teal-500/30 mt-8 pt-6 text-center text-teal-50 text-sm">
                        <p>© 2026 TripSync. All rights reserved.</p>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script>
    async function cancelBooking(bookingId) {
        if (!confirm('Are you sure you want to cancel this booking? If you have paid via wallet, the amount will be refunded to your TripSync Wallet.')) {
            return;
        }

        try {
            const response = await fetch('../api/update_trip_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    booking_id: bookingId,
                    status: 'cancelled'
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('Booking cancelled successfully! Your refund (if applicable) has been processed.');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error cancelling booking:', error);
            alert('An unexpected error occurred. Please try again.');
        }
    }
    </script>
</body>

</html>
