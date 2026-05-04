<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('partner');

$user_id = $_SESSION['user_id'];

// Fetch partner details
$stmt = $conn->prepare("SELECT name, profile_pic, status, is_online, is_busy FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$is_busy = $user_data['is_busy'] ?? 0;

// Fetch vehicle details
$stmt = $conn->prepare("SELECT model, reg_number FROM vehicles WHERE owner_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicle_data = $stmt->get_result()->fetch_assoc();

$partner_name = $user_data['name'] ?? 'Partner';
$profile_pic_path = getProfilePic($user_data['profile_pic'] ?? '', '../');
$is_online = $user_data['is_online'] ?? 0;
$vehicle_info = $vehicle_data ? $vehicle_data['model'] . " · " . $vehicle_data['reg_number'] : "No vehicle registered";

// Fetch Today's Jobs
$stmt = $conn->prepare("SELECT b.*, u.name as customer_name, u.profile_pic as customer_pic FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.assigned_partner_id = ? AND DATE(b.start_date) = CURDATE() ORDER BY b.start_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$today_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Pending Requests (New Bookings Needing Approval)
$stmt = $conn->prepare("SELECT b.*, u.name as customer_name, u.profile_pic as customer_pic, u.phone as customer_phone FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.assigned_partner_id = ? AND b.status = 'pending' ORDER BY b.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Upcoming Trips (Next few days)
$stmt = $conn->prepare("SELECT b.*, u.name as customer_name, u.profile_pic as customer_pic FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.assigned_partner_id = ? AND b.status IN ('confirmed') AND b.start_date > CURDATE() ORDER BY b.start_date ASC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch earnings
$stmt = $conn->prepare("SELECT SUM(total_price) as total FROM bookings WHERE assigned_partner_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$today_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(total_price) as total FROM bookings WHERE assigned_partner_id = ? AND status = 'completed' AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$week_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(total_price) as total FROM bookings WHERE assigned_partner_id = ? AND status = 'completed' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$month_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(total_price) as total FROM bookings WHERE assigned_partner_id = ? AND status = 'confirmed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Dashboard - TripSync</title>
    <meta name="description"
        content="TripSync is your trusted trip planning platform connecting travelers with hotels, drivers, and travel agencies in Sri Lanka.">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="module" src="../assets/js/main.js"></script>
    <style>
        .online-toggle.active {
            background-color: #10b981;
        }

        .online-toggle.active .toggle-circle {
            transform: translateX(1.25rem);
        }
    </style>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <!-- Leaflet for Heatmap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>

            <div class="pt-20 pb-16 px-4">
                <div class="max-w-7xl mx-auto">
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">Partner Dashboard</h1>
                        <p class="text-sm text-gray-500 mt-1" id="current-date">Friday, February 20, 2026</p>
                    </div>

                    <?php if (isset($_GET['msg'])): ?>
                    <div class="mb-6">
                        <?php if ($_GET['msg'] == 'noactive'): ?>
                        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl flex items-center gap-3">
                            <i class="ri-information-line text-xl"></i>
                            <p class="text-sm font-medium">You don't have an active trip at the moment.</p>
                        </div>
                        <?php
    elseif ($_GET['msg'] == 'completed'): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-3">
                            <i class="ri-checkbox-circle-line text-xl"></i>
                            <p class="text-sm font-medium">Trip completed successfully! Keep up the good work.</p>
                        </div>
                        <?php
    endif; ?>
                    </div>
                    <?php
endif; ?>

                    <!-- Profile/Status Bar -->
                    <div class="mb-6">
                        <div
                            class="rounded-2xl p-6 border transition-all duration-300 bg-gradient-to-br <?php echo $is_busy ? 'from-amber-50 to-amber-100/50 border-amber-200' : 'from-emerald-50 to-emerald-100/50 border-emerald-200'; ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        <div
                                            class="w-14 h-14 rounded-full overflow-hidden border-2 border-white shadow-md">
                                            <img alt="<?php echo htmlspecialchars($partner_name); ?>" class="w-full h-full object-cover object-top"
                                                src="<?php echo $profile_pic_path; ?>">
                                        </div>
                                        <div id="status-dot"
                                            class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full border-2 border-white <?php echo $is_busy ? 'bg-amber-500' : ($is_online ? 'bg-emerald-500' : 'bg-gray-400'); ?>">
                                        </div>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($partner_name); ?></h2>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($vehicle_info); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <?php if ($is_busy): ?>
                                    <span class="px-3 py-1 bg-amber-500 text-white text-[10px] font-black rounded-full uppercase tracking-widest shadow-sm">Currently Busy</span>
                                    <?php
endif; ?>
                                    <span id="status-text" class="text-sm font-medium <?php echo $is_busy ? 'text-amber-700' : ($is_online ? 'text-emerald-700' : 'text-gray-500'); ?>">
                                        <?php
if ($is_busy)
    echo "Driving — Ongoing Trip";
else
    echo $is_online ? "You're Online — Accepting Jobs" : "You're Offline — Taking a Break";
?>
                                    </span>
                                    <button id="online-toggle"
                                        class="relative w-12 h-6 rounded-full transition-all duration-300 cursor-pointer <?php echo $is_online ? 'bg-emerald-500 online-toggle active' : 'bg-gray-300 online-toggle'; ?>"
                                        <?php echo $is_busy ? 'disabled' : ''; ?>>
                                        <div
                                            class="toggle-circle absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-all duration-300 flex items-center justify-center">
                                            <i id="toggle-icon" class="text-[10px] <?php echo $is_online ? 'ri-wifi-line text-emerald-500' : 'ri-wifi-off-line text-gray-400'; ?>"></i>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
// Fetch current active trip (arrived or in_progress)
$stmt = $conn->prepare("SELECT b.*, u.name as customer_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.assigned_partner_id = ? AND b.status IN ('arrived', 'in_progress') LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_active_trip = $stmt->get_result()->fetch_assoc();
?>

                    <?php if ($current_active_trip): ?>
                    <!-- Live Trip Banner -->
                    <div class="mb-6 animate-pulse">
                        <a href="active-trip.php" class="block bg-emerald-700 rounded-2xl p-4 text-white shadow-lg hover:shadow-xl transition-all border-2 border-emerald-400/30">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                        <i class="ri-steering-2-fill text-2xl animate-bounce"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-emerald-100 uppercase tracking-widest">Live Trip In Progress</p>
                                        <h3 class="text-lg font-black leading-tight">Driving <?php echo htmlspecialchars($current_active_trip['customer_name']); ?></h3>
                                        <p class="text-xs text-emerald-50/80 mt-0.5">To: <?php echo htmlspecialchars($current_active_trip['dropoff_location']); ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-black uppercase border border-white/20 whitespace-nowrap">
                                        <?php echo $current_active_trip['status'] == 'in_progress' ? 'On Route' : 'At Pickup'; ?>
                                    </span>
                                    <span class="text-xs mt-2 font-bold flex items-center gap-1">GO TO HUB <i class="ri-arrow-right-line"></i></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php
endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Left & Center: Job Feed -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Pending Requests (NEW) -->
                            <?php if (!empty($pending_requests)): ?>
                            <div class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-500">
                                <div class="bg-emerald-50 px-6 py-4 border-b border-emerald-100 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center text-white shadow-md">
                                            <i class="ri-notification-3-fill text-lg"></i>
                                        </div>
                                        <h3 class="text-lg font-black text-emerald-900 uppercase tracking-tight">Pending Job Requests</h3>
                                    </div>
                                    <span class="px-3 py-1 bg-emerald-200 text-emerald-800 text-[10px] font-black rounded-full uppercase"><?php echo count($pending_requests); ?> New</span>
                                </div>
                                <div class="divide-y divide-gray-100">
                                    <?php foreach ($pending_requests as $request): ?>
                                    <div class="p-6 hover:bg-emerald-50/30 transition-colors">
                                        <div class="flex flex-col md:flex-row gap-6 items-start">
                                            <div class="flex items-center gap-4 flex-1">
                                                <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-white shadow-md flex-shrink-0">
                                                    <img src="<?php echo getProfilePic($request['customer_pic'], '../'); ?>" class="w-full h-full object-cover">
                                                </div>
                                                <div class="min-w-0">
                                                    <h4 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($request['customer_name']); ?></h4>
                                                    <div class="flex items-center gap-3 mt-1">
                                                        <span class="text-xs font-bold text-emerald-600 flex items-center gap-1">
                                                            <i class="ri-calendar-event-line"></i> <?php echo date('M j, Y', strtotime($request['start_date'])); ?>
                                                        </span>
                                                        <span class="text-xs text-gray-400">·</span>
                                                        <span class="text-xs font-bold text-gray-500 flex items-center gap-1">
                                                            <i class="ri-time-line"></i> <?php echo date('h:i A', strtotime($request['start_date'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-2 text-right">
                                                <?php $partner_share = $request['total_price'] * 0.8; ?>
                                                <p class="text-xl font-black text-emerald-600">LKR <?php echo number_format($partner_share); ?></p>
                                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Est. Your Share (80%)</p>
                                                <p class="text-[9px] text-gray-300 font-medium">Total: LKR <?php echo number_format($request['total_price']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-6 flex flex-wrap gap-4 items-center justify-between border-t border-gray-100 pt-6">
                                            <div class="flex items-center gap-6">
                                                <div class="flex flex-col">
                                                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pickup Location</span>
                                                    <span class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($request['pickup_location'] ?: 'To be selected'); ?></span>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Trip Type</span>
                                                    <span class="text-sm font-bold text-gray-700 capitalize"><?php echo $request['type']; ?> Rental</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <button onclick="handleRequest(<?php echo $request['id']; ?>, 'decline')" class="px-6 py-2.5 bg-gray-100 text-gray-600 text-xs font-black rounded-xl hover:bg-gray-200 transition-all uppercase tracking-wider">Decline</button>
                                                <button onclick="handleRequest(<?php echo $request['id']; ?>, 'confirmed')" class="px-8 py-2.5 bg-emerald-600 text-white text-xs font-black rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200 uppercase tracking-wider">Accept Job</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
    endforeach; ?>
                                </div>
                            </div>
                            <?php
endif; ?>

                            <!-- Today's Jobs -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                <div class="flex items-center justify-between mb-5">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-lg font-bold text-gray-900">Today's Jobs</h3>
                                        <span class="px-2.5 py-0.5 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full"><?php echo count($today_jobs); ?> Today</span>
                                    </div>
                                    <a class="text-sm text-emerald-600 hover:text-emerald-700 font-medium cursor-pointer whitespace-nowrap" href="active-trip.php">Go to Live Hub</a>
                                </div>
                                <div class="space-y-4">
                                    <?php if (empty($today_jobs)): ?>
                                        <div class="text-center py-12">
                                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <i class="ri-calendar-check-line text-gray-300 text-3xl"></i>
                                            </div>
                                            <p class="text-sm text-gray-500 font-medium">No jobs confirmed for today yet.</p>
                                            <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest">Go online to receive requests</p>
                                        </div>
                                    <?php
else: ?>
                                        <?php foreach ($today_jobs as $job): ?>
                                            <div class="group border border-gray-100 rounded-2xl p-5 hover:border-emerald-200 hover:bg-emerald-50/10 transition-all">
                                                <div class="flex items-start justify-between mb-4">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 flex-shrink-0 border border-gray-100">
                                                            <img src="<?php echo getProfilePic($job['customer_pic'], '../'); ?>" class="w-full h-full object-cover">
                                                        </div>
                                                        <div>
                                                            <p class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($job['customer_name']); ?></p>
                                                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tight"><?php echo date('h:i A', strtotime($job['start_date'])); ?> · <?php echo ucfirst($job['type']); ?></p>
                                                        </div>
                                                    </div>
                                                    <span class="px-2.5 py-1 text-[10px] font-black uppercase rounded-full tracking-wider 
                                                        <?php
        echo $job['status'] === 'confirmed' ? 'bg-amber-100 text-amber-700' :
            ($job['status'] === 'completed' ? 'bg-gray-100 text-gray-700' : ($job['status'] === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-500 text-white shadow-sm shadow-emerald-200'));
?>">
                                                        <?php echo $job['status']; ?>
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-4 mb-4">
                                                    <div class="flex-1 flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg group-hover:bg-white border border-transparent group-hover:border-gray-100 transition-colors">
                                                        <i class="ri-map-pin-2-fill text-emerald-500"></i>
                                                        <span class="text-xs text-gray-600 font-medium truncate"><?php echo htmlspecialchars($job['pickup_location'] ?: 'Location Pending'); ?></span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs font-black text-gray-900 whitespace-nowrap">LKR <?php echo number_format($job['total_price']); ?></span>
                                                    </div>
                                                </div>
                                                <?php if ($job['status'] === 'confirmed'): ?>
                                                <a href="active-trip.php" class="w-full py-2.5 bg-gray-900 text-white rounded-xl text-xs font-black uppercase tracking-wider text-center block hover:bg-emerald-600 transition-all">Start Service</a>
                                                <?php
        endif; ?>
                                            </div>
                                        <?php
    endforeach; ?>
                                    <?php
endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Sidebar -->
                        <div class="lg:col-span-1 space-y-6">
                            <!-- Quick Earnings -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                <h3 class="text-lg font-bold text-gray-900 mb-5">Earnings</h3>
                                <div class="grid grid-cols-2 gap-3 mb-6">
                                    <div class="bg-emerald-50 rounded-xl p-4">
                                        <p class="text-[10px] text-emerald-600 font-black uppercase tracking-widest mb-1">Today</p>
                                        <p class="text-lg font-black text-emerald-700 italic">LKR <?php echo number_format($today_earnings); ?></p>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl p-4">
                                        <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-1">Pending</p>
                                        <p class="text-lg font-black text-gray-900 italic">LKR <?php echo number_format($pending_earnings); ?></p>
                                    </div>
                                </div>
                                <a href="earnings.php" class="w-full py-3 border-2 border-dashed border-gray-200 rounded-xl text-xs font-black uppercase text-gray-500 hover:text-emerald-600 hover:border-emerald-200 transition-all text-center block tracking-widest">Detailed Report</a>
                            </div>

                            <!-- Upcoming Trips (NEW) -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                                <h3 class="text-lg font-bold text-gray-900 mb-5">Upcoming Trips</h3>
                                <?php if (empty($upcoming_trips)): ?>
                                    <p class="text-xs text-gray-400 italic text-center py-4">No confirmed future trips.</p>
                                <?php
else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($upcoming_trips as $trip): ?>
                                            <div class="flex items-center gap-3 pb-4 border-b border-gray-50 last:border-0 last:pb-0">
                                                <div class="w-10 h-10 rounded-lg bg-gray-100 flex-shrink-0 flex flex-col items-center justify-center">
                                                    <span class="text-[10px] font-black text-gray-400 uppercase leading-none"><?php echo date('M', strtotime($trip['start_date'])); ?></span>
                                                    <span class="text-sm font-black text-gray-900 leading-none"><?php echo date('d', strtotime($trip['start_date'])); ?></span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($trip['customer_name']); ?></p>
                                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tight"><?php echo ucfirst($trip['type']); ?> · <?php echo date('h:i A', strtotime($trip['start_date'])); ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-xs font-black text-emerald-600 whitespace-nowrap">LKR <?php echo number_format($trip['total_price'] / 1000, 1); ?>K</p>
                                                </div>
                                            </div>
                                        <?php
    endforeach; ?>
                                    </div>
                                <?php
endif; ?>
                            </div>

                            <!-- Performance Score -->
                            <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl p-6 text-white shadow-xl shadow-gray-200">
                                <div class="flex justify-between items-start mb-6">
                                    <div>
                                        <p class="text-[10px] text-emerald-400 font-black uppercase tracking-widest">Excellence Score</p>
                                        <h4 class="text-3xl font-black italic">4.8<span class="text-xs text-gray-400 not-italic ml-1">/ 5.0</span></h4>
                                    </div>
                                    <div class="p-2 bg-white/10 rounded-xl backdrop-blur-sm">
                                        <i class="ri-medal-line text-2xl text-amber-400"></i>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between text-[11px] font-bold">
                                        <span class="text-gray-400">Job Completion</span>
                                        <span class="text-white">99.2%</span>
                                    </div>
                                    <div class="w-full bg-white/10 rounded-full h-1">
                                        <div class="bg-emerald-500 h-1 rounded-full" style="width: 99%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Demand Heatmap & Nearby Hub -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div class="lg:col-span-2">
                             <div class="bg-white rounded-2xl shadow-lg border-2 border-emerald-500/20 overflow-hidden h-full">
                                <div class="bg-emerald-600 px-6 py-4 flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-white">
                                        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                                            <i class="ri-broadcast-line text-lg animate-pulse"></i>
                                        </div>
                                        <h3 class="text-lg font-black uppercase tracking-tight">Nearby Hub: Live Broadcasts</h3>
                                    </div>
                                    <span class="px-3 py-1 bg-white/20 text-white text-[10px] font-black rounded-full uppercase">Global Feed</span>
                                </div>
                                
                                <div id="broadcast-feed" class="divide-y divide-gray-50 max-h-[500px] overflow-y-auto custom-scrollbar">
                                    <!-- Dynamic broadcast items will be injected here -->
                                    <div class="p-12 text-center text-gray-400">
                                        <i class="ri-radar-line text-4xl mb-2 animate-spin-slow"></i>
                                        <p class="text-sm font-medium">Scanning for nearby jobs...</p>
                                    </div>
                                </div>
                             </div>
                        </div>

                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden h-full flex flex-col">
                                <div class="p-6 pb-4">
                                    <h3 class="text-lg font-bold text-gray-900">Demand Heatmap</h3>
                                    <p class="text-xs text-gray-500 mt-1">Real-time traveler activity in your area</p>
                                </div>
                                <div id="demand-map" class="flex-1 bg-gray-100 relative min-h-[300px]">
                                    <!-- Map injected by JS -->
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i class="ri-map-pin-time-line text-4xl text-gray-300 animate-pulse"></i>
                                    </div>
                                </div>
                                <div class="p-4 bg-gray-50 border-t border-gray-100 grid grid-cols-2 gap-2">
                                    <div class="text-center">
                                        <p class="text-[10px] font-black text-gray-400 uppercase">Avg. Fare</p>
                                        <p class="text-sm font-bold text-emerald-600">LKR 4,200</p>
                                    </div>
                                    <div class="text-center border-l border-gray-200">
                                        <p class="text-[10px] font-black text-gray-400 uppercase">Wait Time</p>
                                        <p class="text-sm font-bold text-amber-600">~12 Mins</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Left & Center: Job Feed -->

                </div>
            </div>

            <!-- Footer -->
            <footer class="bg-teal-800 text-white">
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
                                        href="dashboard.php">Dashboard</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="active-trip.php">Active Trip</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="earnings.php">Earnings</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="messages.php">Messages</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Support</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Help Center</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Terms of Service</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Privacy Policy</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Connect With Us</h3>
                            <div class="flex gap-3 mb-4">
                                <a href="#"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-facebook-fill text-lg"></i></a>
                                <a href="#"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-instagram-line text-lg"></i></a>
                                <a href="#"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-twitter-x-line text-lg"></i></a>
                            </div>
                            <p class="text-teal-50 text-sm font-medium">info@tripsync.lk</p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // Update current date
        const dateEl = document.getElementById('current-date');
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        dateEl.textContent = now.toLocaleDateString('en-US', options);

        // Online/Offline Toggle
        const toggleBtn = document.getElementById('online-toggle');
        const statusText = document.getElementById('status-text');
        const statusDot = document.getElementById('status-dot');
        const toggleIcon = document.getElementById('toggle-icon');

        toggleBtn.addEventListener('click', async () => {
            const currentIsActive = toggleBtn.classList.contains('active');
            const newIsActive = !currentIsActive;

            try {
                const response = await fetch('../api/toggle_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ is_online: newIsActive ? 1 : 0 })
                });

                const data = await response.json();

                if (data.success) {
                    toggleBtn.classList.toggle('active');
                    
                    if (newIsActive) {
                        toggleBtn.classList.replace('bg-gray-300', 'bg-emerald-500');
                        statusText.textContent = "You're Online — Accepting Jobs";
                        statusText.classList.replace('text-gray-500', 'text-emerald-700');
                        statusDot.classList.replace('bg-gray-400', 'bg-emerald-500');
                        toggleIcon.classList.replace('ri-wifi-off-line', 'ri-wifi-line');
                        toggleIcon.style.color = '#10b981';
                    } else {
                        toggleBtn.classList.replace('bg-emerald-500', 'bg-gray-300');
                        statusText.textContent = "You're Offline — Taking a Break";
                        statusText.classList.replace('text-emerald-700', 'text-gray-500');
                        statusDot.classList.replace('bg-emerald-500', 'bg-gray-400');
                        toggleIcon.classList.replace('ri-wifi-line', 'ri-wifi-off-line');
                        toggleIcon.style.color = '#9ca3af';
                    }
                } else {
                    alert('Failed to update status: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error toggling status:', error);
                alert('An error occurred. Please try again.');
            }
        });

        // Handle Booking Request (Accept/Decline)
        async function handleRequest(bookingId, newStatus) {
            const actionText = newStatus === 'confirmed' ? 'accept' : 'decline';
            if (!confirm(`Are you sure you want to ${actionText} this request?`)) return;

            try {
                const response = await fetch('../api/update_trip_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: bookingId,
                        status: newStatus === 'decline' ? 'cancelled' : newStatus
                    })
                });

                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error updating status:', error);
                alert('An unexpected error occurred.');
            }
        }

        // Demand Heatmap Implementation
        document.addEventListener('DOMContentLoaded', function() {
            const mapEl = document.getElementById('demand-map');
            if (mapEl) {
                const map = L.map('demand-map', {
                    scrollWheelZoom: false,
                    zoomControl: false
                }).setView([6.9271, 79.8612], 12);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OSM'
                }).addTo(map);

                const spots = [
                    { lat: 6.9271, lng: 79.8612, color: 'red', label: 'Colombo Fort (Very High)' },
                    { lat: 7.1889, lng: 79.8839, color: 'red', label: 'Bandaranaike Airport (Peak)' },
                    { lat: 6.8483, lng: 79.9265, color: 'orange', label: 'Maharagama (Medium)' }
                ];

                spots.forEach(spot => {
                    L.circle([spot.lat, spot.lng], {
                        color: spot.color,
                        fillColor: spot.color,
                        fillOpacity: 0.4,
                        radius: 1200,
                        stroke: false
                    }).addTo(map).bindPopup(spot.label);
                });
            }

            // Simulate Live Broadcasts
            simulateBroadcasts();
        });

        async function simulateBroadcasts() {
            const feed = document.getElementById('broadcast-feed');
            
            try {
                const response = await fetch('../api/get_broadcasts.php');
                const result = await response.json();
                
                let broadcasts = [];
                if (result.success && result.broadcasts.length > 0) {
                    broadcasts = result.broadcasts.map(b => ({
                        id: b.id,
                        customer: b.customer_name,
                        from: b.pickup_location || 'Colombo (Pickup)',
                        to: b.dropoff_location || 'Destination',
                        fare: b.total_price,
                        type: b.type
                    }));
                } else {
                    // Fallback mock data for demo
                    broadcasts = [
                        { id: 101, customer: 'Sarah', from: 'Colombo 07', to: 'Mount Lavinia', fare: 13500, type: 'sedan' },
                        { id: 102, customer: 'Robert Chen', from: 'Airport (CMB)', to: 'Kandy', fare: 29200, type: 'van' },
                        { id: 103, customer: 'Alice Wong', from: 'Galle Face', to: 'Sigiriya', fare: 50900, type: 'suv' }
                    ];
                }

                feed.innerHTML = broadcasts.map(job => `
                    <div class="p-6 hover:bg-emerald-50/20 transition-all group relative overflow-hidden">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-400">
                                    <i class="ri-user-smile-fill text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900">${job.customer}</h4>
                                    <p class="text-[10px] font-black text-emerald-600 uppercase tabular-nums">Broadcasted Now</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-black text-gray-900">LKR ${Math.round(job.fare).toLocaleString()}</p>
                                <p class="text-[9px] text-gray-400 font-bold uppercase">${job.type} Requested</p>
                            </div>
                        </div>
                        <div class="space-y-2 mb-4">
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <i class="ri-map-pin-user-line text-emerald-500"></i>
                                <span class="font-bold">From:</span> ${job.from}
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <i class="ri-map-pin-5-line text-rose-500"></i>
                                <span class="font-bold">To:</span> ${job.to}
                            </div>
                        </div>
                        <button onclick="acceptBroadcast(${job.id})" class="w-full py-2.5 bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                            Accept Job Instantly
                        </button>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Failed to fetch broadcasts:', error);
            }
        }

        async function acceptBroadcast(id) {
            if (!confirm('Are you sure you want to accept this broadcasted job?')) return;
            
            try {
                const response = await fetch('../api/accept_broadcast.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: id
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert("Job Accepted! Redirecting to setup...");
                    window.location.href = "active-trip.php";
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An unexpected error occurred during acceptance.');
            }
        }
    </script>
</body>
</html>
