<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
checkAuth('customer');

$userId = $_SESSION['user_id'];

// Get wallet balance
$walletStmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$walletStmt->bind_param("i", $userId);
$walletStmt->execute();
$walletResult = $walletStmt->get_result();
$wallet = $walletResult->fetch_assoc();
$balance = $wallet ? $wallet['balance'] : 0.00;

// Get upcoming trips
$plansStmt = $conn->prepare("SELECT * FROM travel_plans WHERE user_id = ? AND status IN ('planning', 'confirmed') ORDER BY start_date ASC");
$plansStmt->bind_param("i", $userId);
$plansStmt->execute();
$plansResult = $plansStmt->get_result();
$upcomingTrips = [];
while ($row = $plansResult->fetch_assoc()) {
    $upcomingTrips[] = $row;
}

// Get travel stats
$statsStmt = $conn->prepare("SELECT COUNT(*) as total_trips FROM travel_plans WHERE user_id = ?");
$statsStmt->bind_param("i", $userId);
$statsStmt->execute();
$totalTrips = $statsStmt->get_result()->fetch_assoc()['total_trips'];

// Get recent bookings
$bookingsStmt = $conn->prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
$bookingsStmt->bind_param("i", $userId);
$bookingsStmt->execute();
$bookingsResult = $bookingsStmt->get_result();
$recentBookings = [];
while ($row = $bookingsResult->fetch_assoc()) {
    $recentBookings[] = $row;
}
// Check for active trips to track
$activeTripStmt = $conn->prepare("SELECT id FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'arrived', 'in_progress') LIMIT 1");
$activeTripStmt->bind_param("i", $userId);
$activeTripStmt->execute();
$hasActiveTrip = $activeTripStmt->get_result()->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - TripSync</title>
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
        <div class="min-h-screen bg-gradient-to-br from-gray-50 to-teal-50">
            <?php include 'includes/navbar.php'; ?>
            <div class="pt-24 pb-16 px-4">
                <div class="max-w-7xl mx-auto">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back, <?php echo clean(explode(' ', $_SESSION['user_name'])[0]); ?>!</h1>
                            <p class="text-gray-600">Your next adventure awaits</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a class="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-full hover:bg-emerald-700 transition-colors whitespace-nowrap cursor-pointer"
                                href="<?php echo $hasActiveTrip ? 'track_trip.php' : 'dashboard.php?msg=noactive'; ?>">
                                <i class="ri-map-pin-time-line text-base"></i>Track Trip
                            </a>
                            <a class="flex items-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors whitespace-nowrap cursor-pointer"
                                href="rate_trip.php">
                                <i class="ri-star-line text-base"></i>Rate Trip
                            </a>
                        </div>
                    </div>

                    <?php if (isset($_GET['msg'])): ?>
                    <div class="mb-6">
                        <?php if ($_GET['msg'] == 'noactive'): ?>
                        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl flex items-center gap-3">
                            <i class="ri-information-line text-xl"></i>
                            <p class="text-sm font-medium">You don't have an active trip at the moment.</p>
                        </div>
                        <?php elseif ($_GET['msg'] == 'rated'): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-3">
                            <i class="ri-checkbox-circle-line text-xl"></i>
                            <p class="text-sm font-medium">Thank you for your feedback! Your rating helps us improve.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-bold text-gray-900">Upcoming Trips</h2>
                                <a class="text-teal-600 hover:text-teal-700 text-sm font-medium cursor-pointer whitespace-nowrap"
                                    href="#">+ New Trip</a>
                            </div>
                            <div class="space-y-4">
                                <?php if (empty($upcomingTrips)): ?>
                                    <div class="text-center py-12 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                                        <i class="ri-map-pin-line text-4xl text-gray-300 mb-3 block"></i>
                                        <p class="text-gray-500">No upcoming trips planned yet.</p>
                                        <a href="plan_trip.php" class="text-teal-600 font-medium mt-2 inline-block">Start Planning Now</a>
                                    </div>
                                <?php
else: ?>
                                    <?php foreach ($upcomingTrips as $trip): ?>
                                    <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-shadow">
                                        <div class="flex flex-col md:flex-row">
                                            <div class="w-full md:w-48 h-40 md:h-auto">
                                                <img alt="<?php echo clean($trip['name']); ?>" class="w-full h-full object-cover"
                                                    src="../assets/images/galle_trip.jpg">
                                            </div>
                                            <div class="flex-1 p-6">
                                                <div class="flex items-start justify-between mb-3">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo clean($trip['name']); ?></h3>
                                                        <p class="text-sm text-gray-600"><i
                                                                class="ri-calendar-line mr-1"></i><?php echo $trip['start_date']; ?> to <?php echo $trip['end_date']; ?>
                                                        </p>
                                                    </div>
                                                    <?php
        $daysLeft = (strtotime($trip['start_date']) - time()) / (60 * 60 * 24);
        if ($daysLeft > 0):
?>
                                                    <div class="bg-teal-100 text-teal-700 px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap">
                                                        <?php echo ceil($daysLeft); ?> days left</div>
                                                    <?php
        else: ?>
                                                    <div class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap">
                                                        Started</div>
                                                    <?php
        endif; ?>
                                                </div>
                                                <div class="mt-4 flex gap-3">
                                                    <a class="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer"
                                                        href="itinerary-builder.php?plan_id=<?php echo $trip['id']; ?>">View Itinerary</a>
                                                    <a href="plan_trip.php?edit=<?php echo $trip['id']; ?>"
                                                        class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap cursor-pointer">Edit
                                                        Trip</a>
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
                        <div class="space-y-6">
                            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                                <h2 class="text-xl font-bold text-gray-900 mb-6">Travel Stats</h2>
                                <div class="space-y-4">
                                    <div
                                        class="flex items-center justify-between p-4 bg-gradient-to-r from-teal-50 to-teal-100 rounded-xl">
                                        <div>
                                            <p class="text-sm text-gray-600 mb-1">Total Trips</p>
                                            <p class="text-2xl font-bold text-teal-700"><?php echo $totalTrips; ?></p>
                                        </div>
                                        <div
                                            class="w-12 h-12 flex items-center justify-center bg-teal-600 rounded-full">
                                            <i class="ri-map-pin-2-fill text-2xl text-white"></i>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center justify-between p-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl">
                                        <div>
                                            <p class="text-sm text-gray-600 mb-1">Distance Traveled</p>
                                            <p class="text-2xl font-bold text-orange-700">2,847 km</p>
                                        </div>
                                        <div
                                            class="w-12 h-12 flex items-center justify-center bg-orange-600 rounded-full">
                                            <i class="ri-road-map-line text-2xl text-white"></i>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-indigo-100 rounded-xl">
                                        <div>
                                            <p class="text-sm text-gray-600 mb-1">Districts Visited</p>
                                            <p class="text-2xl font-bold text-indigo-700">12/25</p>
                                        </div>
                                        <div
                                            class="w-12 h-12 flex items-center justify-center bg-indigo-600 rounded-full">
                                            <i class="ri-map-2-fill text-2xl text-white"></i>
                                        </div>
                                    </div>
                                    <div class="p-4 bg-gradient-to-r from-pink-50 to-pink-100 rounded-xl">
                                        <p class="text-sm text-gray-600 mb-1">Favorite Destination</p>
                                        <p class="text-lg font-bold text-pink-700">Ella</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                                <h2 class="text-xl font-bold text-gray-900 mb-6">Quick Actions</h2>
                                <div class="grid grid-cols-2 gap-3">
                                    <a class="bg-blue-500 p-4 rounded-xl text-white hover:opacity-90 transition-opacity cursor-pointer"
                                        href="#">
                                        <div
                                            class="w-10 h-10 flex items-center justify-center bg-white/20 rounded-lg mb-3">
                                            <i class="ri-hotel-line text-2xl"></i>
                                        </div>
                                        <p class="text-sm font-medium">Find Hotels</p>
                                    </a>
                                    <a class="bg-teal-500 p-4 rounded-xl text-white hover:opacity-90 transition-opacity cursor-pointer"
                                        href="#">
                                        <div
                                            class="w-10 h-10 flex items-center justify-center bg-white/20 rounded-lg mb-3">
                                            <i class="ri-car-line text-2xl"></i>
                                        </div>
                                        <p class="text-sm font-medium">Book Vehicle</p>
                                    </a>
                                    <a class="bg-orange-500 p-4 rounded-xl text-white hover:opacity-90 transition-opacity cursor-pointer"
                                        href="#">
                                        <div
                                            class="w-10 h-10 flex items-center justify-center bg-white/20 rounded-lg mb-3">
                                            <i class="ri-add-circle-line text-2xl"></i>
                                        </div>
                                        <p class="text-sm font-medium">New Trip</p>
                                    </a>
                                    <a class="bg-purple-500 p-4 rounded-xl text-white hover:opacity-90 transition-opacity cursor-pointer"
                                        href="#">
                                        <div
                                            class="w-10 h-10 flex items-center justify-center bg-white/20 rounded-lg mb-3">
                                            <i class="ri-wallet-3-line text-2xl"></i>
                                        </div>
                                        <p class="text-sm font-medium">My Wallet (LKR <?php echo number_format($balance, 2); ?>)</p>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Recent Bookings</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php if (empty($recentBookings)): ?>
                                <div class="md:col-span-2 text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                                    <p class="text-gray-500 text-sm">No recent bookings found.</p>
                                </div>
                            <?php
else: ?>
                                <?php foreach ($recentBookings as $booking): ?>
                                <div class="flex gap-4 p-4 border border-gray-200 rounded-xl hover:shadow-md transition-shadow">
                                    <div class="w-20 h-20 flex-shrink-0 bg-teal-50 rounded-lg flex items-center justify-center">
                                        <i class="ri-<?php echo $booking['type'] === 'hotel' ? 'hotel' : 'car'; ?>-line text-2xl text-teal-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-start justify-between mb-2">
                                            <h3 class="font-semibold text-gray-900 text-sm capitalize"><?php echo $booking['type']; ?> Booking</h3>
                                            <span class="bg-teal-100 text-teal-700 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap"><?php echo $booking['status']; ?></span>
                                        </div>
                                        <p class="text-xs text-gray-600 mb-2"><i class="ri-file-list-3-line mr-1"></i><?php echo $booking['reference_no']; ?></p>
                                        <p class="text-sm font-bold text-gray-900">LKR <?php echo number_format($booking['total_price'], 0); ?></p>
                                    </div>
                                </div>
                                <?php
    endforeach; ?>
                            <?php
endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div>
                            <img alt="TripSync Logo" class="h-12 w-auto mb-4" src="../assets/images/logo.png">
                            <p class="text-teal-50 text-sm leading-relaxed">Your trusted partner for seamless travel
                                planning across Sri Lanka.</p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Quick Links</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="./dashboard.php">Dashboard</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="plan_trip.php">Plan Trip</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="marketplace.php">Marketplace</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="wallet.php">Wallet</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="trip_history.php">Trip History</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Messages</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Booking Confirmation</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="../partner/register.php">Become a Partner</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Support</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="help.php">Help Center</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Contact Us</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Terms of Service</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Privacy Policy</a></li>
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
</body>

</html>
