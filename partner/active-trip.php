<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('partner');

$user_id = $_SESSION['user_id'];

// Fetch active trip: confirmed -> arrived -> in_progress
$stmt = $conn->prepare("
    SELECT b.*, u.name as customer_name, u.phone as customer_phone, u.profile_pic as customer_pic 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.assigned_partner_id = ? 
    AND b.status IN ('confirmed', 'arrived', 'in_progress') 
    ORDER BY b.start_date ASC LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_trip = $stmt->get_result()->fetch_assoc();

if (!$active_trip) {
    header("Location: dashboard.php?msg=noactive");
    exit();
}

// Fetch itinerary destinations if linked to a plan
$destinations = [];
if (!empty($active_trip['plan_id'])) {
    $plan_id = $active_trip['plan_id'];
    $dstmt = $conn->prepare("SELECT * FROM destinations WHERE plan_id = ? ORDER BY day_number ASC, arrival_time ASC");
    $dstmt->bind_param("i", $plan_id);
    $dstmt->execute();
    $destinations = $dstmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$status_labels = [
    'confirmed' => 'Waiting for Arrival',
    'arrived' => 'At Pickup Point',
    'in_progress' => 'Heading to Destination'
];

$status_colors = [
    'confirmed' => 'bg-amber-500 text-white px-3',
    'arrived' => 'bg-blue-600 text-white px-3',
    'in_progress' => 'bg-emerald-600 text-white px-3'
];

$current_status = $active_trip['status'];
$customer_pic_path = getProfilePic($active_trip['customer_pic'] ?? '', '../');

// Fetch next destination (Multi-stop logic)
$next_stop = null;
if (!empty($destinations)) {
    foreach ($destinations as $dest) {
        if ($dest['status'] !== 'completed') {
            $next_stop = $dest;
            break;
        }
    }
}

// Fetch upcoming jobs (future confirmed bookings)
$upcoming_stmt = $conn->prepare("
    SELECT b.*, u.name as customer_name, u.profile_pic as customer_pic 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.assigned_partner_id = ? 
    AND b.status = 'confirmed' 
    AND b.id != ? 
    ORDER BY b.start_date ASC LIMIT 3
");
$upcoming_stmt->bind_param("ii", $user_id, $active_trip['id']);
$upcoming_stmt->execute();
$upcoming_jobs = $upcoming_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate Progress (arbitrary for demo)
$progress = 0;
if ($current_status == 'confirmed')
    $progress = 5;
if ($current_status == 'arrived')
    $progress = 15;
if ($current_status == 'in_progress')
    $progress = 60;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Trip Hub - TripSync Partner</title>
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="module" src="../assets/js/main.js"></script>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        .chat-bubble-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
        }

        .chat-toggle-btn {
            width: 3.5rem;
            height: 3.5rem;
            background: #0d9488;
            color: white;
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(13, 148, 136, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .chat-toggle-btn:hover {
            transform: translateY(-2px);
            background: #0f766e;
        }

        .chat-badge {
            position: absolute;
            top: -0.25rem;
            right: -0.25rem;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            display: none;
        }

        .chat-window {
            width: 320px;
            height: 450px;
            background: white;
            border-radius: 2rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            display: none;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
            animation: slideInUp 0.3s ease-out;
        }

        @keyframes slideInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .chat-messages {
            flex: 1;
            padding: 1.25rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: #f8fafc;
        }

        .message-row {
            display: flex;
            flex-direction: column;
            max-width: 80%;
        }

        .message-row.sent {
            align-self: flex-end;
            align-items: flex-end;
        }

        .message-row.received {
            align-self: flex-start;
            align-items: flex-start;
        }

        .message-bubble {
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            line-height: 1.4;
        }

        .sent .message-bubble {
            background: #0d9488;
            color: white;
            border-bottom-right-radius: 0.25rem;
        }

        .received .message-bubble {
            background: white;
            color: #1e293b;
            border-bottom-left-radius: 0.25rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .message-time {
            font-size: 0.6rem;
            color: #94a3b8;
            margin-top: 0.25rem;
            font-weight: 600;
        }
    </style>
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>

            <main class="pt-20 pb-16 px-4">
                <div class="max-w-7xl mx-auto">
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold text-gray-900 leading-tight">Live Trip Hub</h1>
                        <p class="text-sm text-gray-500 mt-1">Manage your active trip and navigate in real-time</p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Left Column: Active Trip Summary -->
                        <div class="space-y-6">
                            <div class="bg-white rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-100 overflow-hidden">
                                <div class="p-8">
                                    <div class="flex items-center justify-between mb-8">
                                        <div class="flex items-center gap-3">
                                            <h3 class="text-xl font-bold text-gray-900">Active Trip</h3>
                                            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase tracking-wider rounded-full">
                                                <?php echo $current_status === 'in_progress' ? 'In Progress' : $status_labels[$current_status]; ?>
                                            </span>
                                        </div>
                                        <span class="text-xs font-bold text-gray-300 uppercase tracking-widest">TRIP-<?php echo str_pad($active_trip['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                    </div>

                                    <div class="flex items-center gap-4 mb-8">
                                        <img class="w-14 h-14 rounded-full object-cover ring-4 ring-gray-50 shadow-sm"
                                            src="<?php echo $customer_pic_path; ?>" alt="Customer">
                                        <div class="flex-1">
                                            <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($active_trip['customer_name']); ?></h4>
                                            <p class="text-[11px] text-gray-500 leading-relaxed font-medium">2 passengers · Flight arrives at 10:00 AM · Terminal 1</p>
                                        </div>
                                        <?php if ($active_trip['customer_phone']): ?>
                                        <a href="tel:<?php echo $active_trip['customer_phone']; ?>"
                                            class="w-10 h-10 flex items-center justify-center bg-emerald-50 text-emerald-600 rounded-full hover:bg-emerald-100 transition-all cursor-pointer">
                                            <i class="ri-phone-fill text-lg"></i>
                                        </a>
                                        <?php
endif; ?>
                                    </div>

                                    <div class="relative space-y-8 mb-8 pl-4">
                                        <div class="absolute left-[3px] top-2 bottom-2 w-[1px] bg-gray-100 border-l border-dashed border-gray-300"></div>
                                        <div class="relative">
                                            <div class="absolute -left-[17px] top-1.5 w-2 h-2 rounded-full bg-emerald-500 ring-4 ring-emerald-50"></div>
                                            <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mb-1">Pickup</p>
                                            <p id="partner-pickup" class="text-sm font-bold text-gray-900 leading-tight"><?php echo htmlspecialchars($active_trip['pickup_location']); ?></p>
                                            <p class="text-[10px] text-gray-400 mt-1 font-bold">Started at 10:35 AM</p>
                                        </div>
                                        <div class="relative">
                                            <div class="absolute -left-[17px] top-1.5 w-2 h-2 rounded-full bg-rose-500 ring-4 ring-rose-50"></div>
                                            <p class="text-[10px] font-black text-rose-600 uppercase tracking-widest mb-1">Drop-off</p>
                                            <p id="partner-dropoff" class="text-sm font-bold text-gray-900 leading-tight"><?php echo htmlspecialchars($active_trip['dropoff_location']); ?></p>
                                            <p id="partner-eta" class="text-[10px] text-gray-400 mt-1 font-bold">ETA: Calculating...</p>
                                        </div>
                                    </div>

                                    <div class="mb-8">
                                        <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">
                                            <span>Trip Progress</span>
                                            <span class="text-emerald-600"><?php echo $progress; ?>%</span>
                                        </div>
                                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-500 rounded-full transition-all duration-1000" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <p class="text-[10px] text-gray-400 mt-3 flex items-center gap-1 font-bold">
                                            <i class="ri-map-pin-line"></i> Currently at: <?php echo $current_status === 'in_progress' ? 'En-route to destination' : 'Pickup point'; ?>
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-3 gap-3 mb-8">
                                        <div class="p-3 rounded-2xl bg-gray-50 border border-gray-100 text-center">
                                            <p class="text-[9px] font-black text-gray-400 uppercase mb-1">Distance</p>
                                            <p id="partner-dist" class="text-sm font-bold text-gray-900">TBD</p>
                                        </div>
                                        <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-100/50 text-center">
                                            <p class="text-[9px] font-black text-emerald-600 uppercase mb-1">Fare</p>
                                            <p class="text-sm font-bold text-emerald-700">LKR <?php echo number_format($active_trip['total_price'], 0); ?></p>
                                        </div>
                                        <div class="p-3 rounded-2xl bg-gray-50 border border-gray-100 text-center">
                                            <p class="text-[9px] font-black text-gray-400 uppercase mb-1">Passengers</p>
                                            <p class="text-sm font-bold text-gray-900">2</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <?php if ($current_status == 'confirmed'): ?>
                                            <button onclick="updateStatus('arrived')" class="flex-1 py-4 bg-amber-500 hover:bg-amber-600 text-white rounded-2xl font-bold transition-all shadow-lg shadow-amber-100 flex items-center justify-center gap-2">
                                                <i class="ri-map-pin-2-fill"></i> I have Arrived at Pickup
                                            </button>
                                        <?php
elseif ($current_status == 'arrived'): ?>
                                            <button onclick="updateStatus('in_progress')" class="flex-1 py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-bold transition-all shadow-lg shadow-emerald-100 flex items-center justify-center gap-2">
                                                <i class="ri-play-fill"></i> Start Trip (Pick up Customer)
                                            </button>
                                        <?php
elseif ($current_status == 'in_progress' && $next_stop): ?>
                                            <?php if ($next_stop['status'] === 'pending'): ?>
                                                <button onclick="updateStopStatus(<?php echo $next_stop['id']; ?>, 'arrived')" class="flex-1 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-bold transition-all shadow-lg shadow-blue-100 flex items-center justify-center gap-2">
                                                    <i class="ri-map-pin-range-fill"></i> Arrived at: <?php echo htmlspecialchars($next_stop['location_name']); ?>
                                                </button>
                                            <?php
    else: // Must be 'arrived' ?>
                                                <button onclick="updateStopStatus(<?php echo $next_stop['id']; ?>, 'completed')" class="flex-1 py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-bold transition-all shadow-lg shadow-emerald-100 flex items-center justify-center gap-2">
                                                    <i class="ri-check-double-line text-lg"></i> Depart for Next Stop
                                                </button>
                                            <?php
    endif; ?>
                                        <?php
else: ?>
                                            <button onclick="updateStatus('completed')" class="flex-1 py-4 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl font-bold transition-all shadow-lg shadow-rose-100 flex items-center justify-center gap-2">
                                                <i class="ri-flag-2-fill"></i> Complete Full Trip
                                            </button>
                                        <?php
endif; ?>

                                        <div class="flex gap-3">
                                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($next_stop ? $next_stop['location_name'] : $active_trip['dropoff_location']); ?>" target="_blank"
                                            class="flex-1 h-14 flex items-center justify-center bg-sky-100 text-sky-600 rounded-2xl hover:bg-sky-200 transition-all shadow-lg shadow-sky-50 font-bold gap-2">
                                                <i class="ri-navigation-fill text-xl rotate-45"></i> Navigate to <?php echo $next_stop ? 'Next Stop' : 'Final Dropoff'; ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button onclick="triggerSOS()" class="w-full py-5 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl font-black uppercase tracking-wider text-sm shadow-xl shadow-rose-100 transition-all flex items-center justify-center gap-3">
                                <i class="ri-alarm-warning-fill text-xl"></i> One-Tap SOS Emergency
                            </button>
                        </div>

                        <!-- Right Column: Map and Future Jobs -->
                        <div class="lg:col-span-2 space-y-8">
                            <!-- Live Route Map -->
                            <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 overflow-hidden">
                                <div class="p-8 border-b border-gray-50">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-xl font-bold text-gray-900">Live Route</h3>
                                        <div class="flex items-center gap-2 px-3 py-1 bg-emerald-50 rounded-full">
                                            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                            <span class="text-[10px] font-black text-emerald-600 uppercase">GPS ACTIVE</span>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-400 font-medium"><?php echo htmlspecialchars($active_trip['pickup_location']); ?> → <?php echo htmlspecialchars($active_trip['dropoff_location']); ?></p>
                                </div>
                                <div class="h-[500px] bg-gray-50 relative">
                                    <iframe src="../assets/maps/tracking_map.php?booking_id=<?php echo $active_trip['id']; ?>" class="w-full h-full border-0"
                                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                    title="Live Trip Tracking Map"></iframe>
                                </div>
                                <div id="route-breadcrumbs" class="px-8 py-6 bg-gray-50/50 border-t border-gray-50">
                                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-2">
                                        <?php if (!empty($destinations)): ?>
                                            <?php foreach ($destinations as $index => $dest): ?>
                                                <div class="flex items-center gap-3 whitespace-nowrap group cursor-pointer" onclick="cycleStopStatus(<?php echo $dest['id']; ?>)">
                                                    <div class="flex items-center gap-2 text-[11px] font-bold <?php echo $dest['status'] === 'completed' ? 'text-emerald-600' : ($dest['status'] === 'arrived' ? 'text-amber-500' : 'text-gray-400'); ?>">
                                                        <i class="<?php echo $dest['status'] === 'completed' ? 'ri-checkbox-circle-fill' : ($dest['status'] === 'arrived' ? 'ri-record-circle-fill animate-pulse' : 'ri-checkbox-blank-circle-line'); ?>"></i>
                                                        <span class="group-hover:underline"><?php echo htmlspecialchars($dest['location_name']); ?></span>
                                                    </div>
                                                    <?php if ($index < count($destinations) - 1): ?>
                                                        <i class="ri-arrow-right-s-line text-gray-300"></i>
                                                    <?php
        endif; ?>
                                                </div>
                                            <?php
    endforeach; ?>
                                        <?php
else: ?>
                                            <div class="flex items-center gap-3 text-[11px] font-bold text-emerald-600 whitespace-nowrap">
                                                <i class="ri-checkbox-circle-fill"></i> <?php echo htmlspecialchars($active_trip['pickup_location']); ?>
                                                <i class="ri-arrow-right-s-line text-gray-300"></i>
                                            </div>
                                            <div class="flex items-center gap-3 text-[11px] font-bold text-gray-400 whitespace-nowrap">
                                                <i class="ri-map-pin-2-fill text-rose-500"></i> <?php echo htmlspecialchars($active_trip['dropoff_location']); ?>
                                            </div>
                                        <?php
endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Trip Itinerary (Replaced Upcoming Jobs) -->
                            <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 overflow-hidden">
                                <div class="p-8 border-b border-gray-50 flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900">Current Trip Itinerary</h3>
                                        <p class="text-sm text-gray-400 mt-1 font-medium">Step-by-step route for TRIP-<?php echo str_pad($active_trip['id'], 3, '0', STR_PAD_LEFT); ?></p>
                                    </div>
                                    <div class="px-4 py-2 bg-gray-50 rounded-2xl">
                                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest"><?php echo count($destinations); ?> STOPS</span>
                                    </div>
                                </div>
                                <div id="partner-itinerary-list" class="p-8 space-y-4">
                                    <?php if (empty($destinations)): ?>
                                        <div class="flex items-center gap-4 p-4 rounded-3xl border border-dashed border-gray-200">
                                            <div class="w-10 h-10 flex items-center justify-center bg-gray-50 rounded-2xl">
                                                <i class="ri-map-pin-2-line text-gray-300"></i>
                                            </div>
                                            <p class="text-sm text-gray-400 font-bold italic">No intermediate stops defined.</p>
                                        </div>
                                    <?php
else: ?>
                                        <?php foreach ($destinations as $index => $dest): ?>
                                            <div class="flex items-center gap-4 p-5 rounded-3xl border <?php echo $dest['status'] === 'completed' ? 'bg-emerald-50/50 border-emerald-100' : ($dest['status'] === 'arrived' ? 'bg-amber-50/50 border-amber-100 ring-2 ring-amber-100 ring-offset-2' : 'bg-gray-50/30 border-gray-100'); ?> transition-all group">
                                                <div class="w-12 h-12 flex items-center justify-center rounded-2xl <?php echo $dest['status'] === 'completed' ? 'bg-emerald-100 text-emerald-600' : ($dest['status'] === 'arrived' ? 'bg-amber-100 text-amber-600 animate-pulse' : 'bg-white text-gray-400 shadow-sm'); ?>">
                                                    <span class="text-sm font-black"><?php echo $index + 1; ?></span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($dest['location_name']); ?></h4>
                                                    <p class="text-[10px] <?php echo $dest['status'] === 'completed' ? 'text-emerald-600' : ($dest['status'] === 'arrived' ? 'text-amber-500' : 'text-gray-400'); ?> font-black uppercase tracking-widest mt-0.5">
                                                        <?php echo $dest['status'] === 'completed' ? 'Visited & Completed' : ($dest['status'] === 'arrived' ? 'Currently Here' : 'Upcoming Destination'); ?>
                                                    </p>
                                                </div>
                                                <?php if ($dest['status'] !== 'completed'): ?>
                                                    <button onclick="showStopDetails(<?php echo $index; ?>)" 
                                                            class="px-4 py-2 bg-white border border-gray-100 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-all shadow-sm flex items-center gap-2">
                                                        <i class="ri-information-line text-sm"></i>
                                                        Details
                                                    </button>
                                                <?php
        else: ?>
                                                    <i class="ri-checkbox-circle-fill text-emerald-500 text-xl"></i>
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
                    </div>
                </div>
            </main>

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
                                        href="#">Safety Rules</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Payout Terms</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Emergency</h3>
                            <p class="text-teal-50 text-xs mb-3">Our 24/7 safety team is always available.</p>
                            <p class="text-lg font-black text-rose-300 tracking-wider">HOTLINE: 119 / 1919</p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script>
        const bookingId = <?php echo $active_trip['id']; ?>;
        
        async function updateStatus(newStatus) {
            if (newStatus === 'completed') {
                if (!confirm('Are you sure the trip is completed?')) return;
            }

            try {
                // Get current location
                let lat = null, lng = null;
                if (navigator.geolocation) {
                    const pos = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null));
                    });
                    if (pos) {
                        lat = pos.coords.latitude;
                        lng = pos.coords.longitude;
                    }
                }

                const response = await fetch('../api/update_trip_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: bookingId,
                        status: newStatus,
                        latitude: lat,
                        longitude: lng
                    })
                });

                const data = await response.json();
                if (data.success) {
                    if (newStatus === 'completed') {
                        window.location.href = 'dashboard.php?msg=completed';
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error updating status:', error);
                alert('An unexpected error occurred.');
            }
        }

        async function triggerSOS() {
            if (!confirm('🚨 EMERGENCY SOS\n\nAre you sure you want to trigger an emergency alert? This will broadcast your current location to our 24/7 safety team.')) return;

            try {
                let lat = null, lng = null;
                if (navigator.geolocation) {
                    const pos = await new Promise((resolve) => {
                        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null));
                    });
                    if (pos) {
                        lat = pos.coords.latitude;
                        lng = pos.coords.longitude;
                    }
                }

                const response = await fetch('../api/trigger_sos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: bookingId,
                        latitude: lat,
                        longitude: lng
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('🚨 SOS SENT: Help is on the way. Please stay where you are and wait for contact.');
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('SOS failed:', error);
                alert('An error occurred while sending SOS. Please call 119 directly.');
            }
        }
        
        // Live UI Update Hub
        window.lastTripData = null; // Global cache for destinations modal

        function updateTripUI(data) {
            if (!data.success) return;
            window.lastTripData = data; // Cache for the modal

            // 1. Update Status Badge
            const statusBadge = document.querySelector('.px-3.py-1.bg-emerald-100');
            if (statusBadge) {
                statusBadge.textContent = data.status === 'in_progress' ? 'In Progress' : data.status.charAt(0).toUpperCase() + data.status.slice(1);
            }

            // 2. Update Progress Bar
            const progressBar = document.querySelector('.h-full.bg-emerald-500');
            if (progressBar) progressBar.style.width = data.progress + '%';
            
            const progressPct = document.querySelector('.text-emerald-600');
            if (progressPct && progressPct.parentElement.classList.contains('font-black')) {
                progressPct.textContent = data.progress + '%';
            }

            // 3. Update ETA and Distance using IDs
            const partnerDist = document.getElementById('partner-dist');
            if (partnerDist) partnerDist.textContent = data.distance;

            const partnerEta = document.getElementById('partner-eta');
            if (partnerEta) partnerEta.textContent = 'ETA: ' + data.eta;

            const partnerPickup = document.getElementById('partner-pickup');
            if (partnerPickup) partnerPickup.textContent = data.pickup_location;

            const partnerDropoff = document.getElementById('partner-dropoff');
            if (partnerDropoff) partnerDropoff.textContent = data.dropoff_location;

            // 4. Re-render Itinerary List
            const itineraryContainer = document.getElementById('partner-itinerary-list');
            if (itineraryContainer && data.destinations) {
                let list = [];
                if (data.destinations.length > 0) {
                    list = data.destinations.map(d => ({ name: d.location_name, status: d.status, type: 'stop' }));
                } else {
                    // Solo booking stops
                    list = [
                        { name: data.pickup_location, status: (data.status === 'confirmed' ? 'pending' : 'completed'), type: 'pickup' },
                        { name: data.dropoff_location, status: 'pending', type: 'dropoff' }
                    ];
                }

                let html = '';
                list.forEach((stop, i) => {
                    const isCompleted = stop.status === 'completed';
                    const isArrived = stop.status === 'arrived';
                    html += `
                        <div class="flex items-center gap-4 p-5 rounded-3xl border ${isCompleted ? 'bg-emerald-50/50 border-emerald-100' : (isArrived ? 'bg-amber-50/50 border-amber-100 ring-2 ring-amber-100 ring-offset-2' : 'bg-gray-50/30 border-gray-100')} transition-all group">
                            <div class="w-12 h-12 flex items-center justify-center rounded-2xl ${isCompleted ? 'bg-emerald-100 text-emerald-600' : (isArrived ? 'bg-amber-100 text-amber-600 animate-pulse' : 'bg-white text-gray-400 shadow-sm')}">
                                <span class="text-sm font-black">${i + 1}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-gray-900 truncate">${stop.name}</h4>
                                <p class="text-[10px] ${isCompleted ? 'text-emerald-600' : (isArrived ? 'text-amber-500' : 'text-gray-400')} font-black uppercase tracking-widest mt-0.5">
                                    ${isCompleted ? 'Visited & Completed' : (isArrived ? 'Currently Here' : 'Upcoming')}
                                </p>
                            </div>
                            ${(!isCompleted && stop.type === 'stop') ? `
                                <button onclick="showStopDetails(${i})" 
                                        class="px-4 py-2 bg-white border border-gray-100 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-all shadow-sm flex items-center gap-2">
                                    <i class="ri-information-line text-sm"></i>
                                    Details
                                </button>
                            ` : (isCompleted ? '<i class="ri-checkbox-circle-fill text-emerald-500 text-xl"></i>' : '')}
                        </div>
                    `;
                });
                itineraryContainer.innerHTML = html;
            }

            // 5. Reload if status changed externally
            if (data.status === 'completed' || data.status !== "<?php echo $current_status; ?>") {
                if (data.status === 'completed') window.location.href = 'dashboard.php?msg=completed';
            }
        }

        function showStopDetails(index) {
            const data = window.lastTripData;
            if (!data || !data.destinations[index]) return;
            
            const dest = data.destinations[index];
            document.getElementById('modal-stop-number').textContent = index + 1;
            document.getElementById('modal-location-name').textContent = dest.location_name;
            document.getElementById('modal-arrival-time').textContent = dest.arrival_time || 'No set time';
            document.getElementById('modal-status').textContent = dest.status.charAt(0).toUpperCase() + dest.status.slice(1);
            
            const navBtn = document.getElementById('modal-nav-btn');
            if (navBtn) {
                const encodedDest = encodeURIComponent(dest.location_name);
                navBtn.href = `https://www.google.com/maps/dir/?api=1&destination=${encodedDest}`;
            }

            const modal = document.getElementById('stop-details-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeStopDetails() {
            const modal = document.getElementById('stop-details-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function updateStopStatus(destId, newStatus) {
            try {
                const response = await fetch('../api/update_stop_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        destination_id: destId,
                        status: newStatus
                    })
                });
                const res = await response.json();
                if (res.success) window.location.reload();
                else alert(res.message);
            } catch (e) { console.error(e); }
        }

        async function cycleStopStatus(destId) {
            // ... (keeping this for the breadcrumb click as well)
            updateStopStatus(destId, 'completed');
        }

        async function startRealTimeUpdates() {
            setInterval(async () => {
                try {
                    const response = await fetch(`../api/get_tracking_data.php?booking_id=${bookingId}`);
                    const data = await response.json();
                    updateTripUI(data);
                } catch (e) {
                    console.error("Polling error:", e);
                }
            }, 5000); // Poll every 5 seconds for status/progress sync
        }

        startRealTimeUpdates();

        // Background location sync: Update partner position every 30s
        if ("<?php echo $current_status; ?>" !== "completed") {
            setInterval(() => {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(pos => {
                        fetch('../api/update_trip_status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                booking_id: bookingId,
                                status: "<?php echo $current_status; ?>",
                                latitude: pos.coords.latitude,
                                longitude: pos.coords.longitude
                            })
                        });
                    }, null, { enableHighAccuracy: true });
                }
            }, 30000);
        }
    </script>
    <!-- Details Pop-up Modal -->
    <div id="stop-details-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm animate-in fade-in duration-300">
        <!-- ... existing modal content ... -->
    </div>

    <!-- Quick Chat Bubble (New) -->
    <div class="chat-bubble-container">
        <div id="chat-window" class="chat-window">
            <div class="p-4 bg-teal-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="<?php echo $customer_pic_path; ?>" class="w-8 h-8 rounded-full border-2 border-white/20">
                    <div>
                        <h4 class="text-xs font-bold"><?php echo htmlspecialchars($active_trip['customer_name']); ?></h4>
                        <p class="text-[9px] text-teal-100 font-bold uppercase tracking-widest">Customer</p>
                    </div>
                </div>
                <button onclick="toggleChat()" class="text-white/60 hover:text-white"><i class="ri-close-line text-lg"></i></button>
            </div>
            <div id="chat-messages" class="chat-messages no-scrollbar">
                <!-- Messages dynamic -->
                <div class="flex items-center justify-center h-full opacity-40">
                    <p class="text-[10px] font-black uppercase tracking-widest">No messages yet</p>
                </div>
            </div>
            <div class="p-4 bg-white border-t border-gray-100 flex gap-2">
                <input type="text" id="chat-input" placeholder="Type a message..." 
                       class="flex-1 bg-gray-50 border-0 rounded-xl px-4 py-2 text-xs font-medium focus:ring-2 focus:ring-teal-500/20 outline-none">
                <button onclick="sendMessage()" class="w-10 h-10 bg-teal-600 text-white rounded-xl flex items-center justify-center hover:bg-teal-700 transition-all">
                    <i class="ri-send-plane-2-fill"></i>
                </button>
            </div>
        </div>
        <button onclick="toggleChat()" class="chat-toggle-btn">
            <i class="ri-message-3-fill"></i>
            <span id="chat-unread-badge" class="chat-badge">0</span>
        </button>
    </div>

    <script>
        const receiverId = <?php echo (int)$active_trip['user_id']; ?>;
        let lastMessageCount = 0;

        function toggleChat() {
            const chatWin = document.getElementById('chat-window');
            if (chatWin.style.display === 'flex') {
                chatWin.style.display = 'none';
            } else {
                chatWin.style.display = 'flex';
                loadMessages();
                document.getElementById('chat-unread-badge').style.display = 'none';
            }
        }

        async function loadMessages() {
            try {
                const response = await fetch(`../api/get_messages.php?booking_id=${bookingId}`);
                const data = await response.json();
                if (data.success) {
                    const container = document.getElementById('chat-messages');
                    const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
                    
                    let html = '';
                    data.messages.forEach(msg => {
                        const isSent = msg.sender_id == <?php echo $user_id; ?>;
                        html += `
                            <div class="message-row ${isSent ? 'sent' : 'received'}">
                                <div class="message-bubble">${msg.message}</div>
                                <span class="message-time">${new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                            </div>
                        `;
                    });

                    if (data.messages.length > 0) {
                        container.innerHTML = html;
                    }

                    if (data.messages.length > lastMessageCount) {
                        const chatWin = document.getElementById('chat-window');
                        if (chatWin.style.display !== 'flex') {
                            const badge = document.getElementById('chat-unread-badge');
                            badge.textContent = data.messages.filter(m => !m.is_read && m.sender_id != <?php echo $user_id; ?>).length;
                            if (parseInt(badge.textContent) > 0) badge.style.display = 'flex';
                        }
                        
                        if (isAtBottom || lastMessageCount === 0) {
                            container.scrollTop = container.scrollHeight;
                        }
                        lastMessageCount = data.messages.length;
                    }
                }
            } catch (error) {
                console.error('Chat poll error:', error);
            }
        }

        async function sendMessage() {
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            if (!message) return;

            try {
                const response = await fetch('../api/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: bookingId,
                        receiver_id: receiverId,
                        message: message
                    })
                });
                const data = await response.json();
                if (data.success) {
                    input.value = '';
                    loadMessages();
                }
            } catch (error) {
                console.error('Send message error:', error);
            }
        }

        // Poll every 3 seconds
        setInterval(loadMessages, 3000);

        // Enter to send
        document.getElementById('chat-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    </script>
</body>
</html>

