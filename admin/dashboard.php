<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('admin');

// 1. Active Users
$active_users_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$active_users = $active_users_res->fetch_assoc()['count'];

// 2. Active Partners
$active_partners_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'partner' AND status = 'active'");
$active_partners = $active_partners_res->fetch_assoc()['count'];

// 2b. Total Revenue (All time)
$total_revenue_res = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE status = 'completed' AND type = 'credit'");
$total_revenue = $total_revenue_res->fetch_assoc()['total'] ?: 0;

// 3. Today's Revenue
$today_revenue_res = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed' AND type = 'credit'");
$today_revenue = $today_revenue_res->fetch_assoc()['total'] ?: 0;

// 4. Pending Partner Approvals
$pending_approvals_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'partner' AND status = 'pending'");
$pending_approvals = $pending_approvals_res->fetch_assoc()['count'];

// 5. Ongoing Trips
$active_trips_res = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status IN ('confirmed', 'arrived', 'in_progress')");
$active_trips = $active_trips_res->fetch_assoc()['count'];

// 6. Recent Activity
$recent_activity = $conn->query("(SELECT 'user_reg' as type, name as detail, created_at FROM users ORDER BY created_at DESC LIMIT 5) 
    UNION 
    (SELECT 'booking_update' as type, reference_no as detail, created_at FROM bookings ORDER BY created_at DESC LIMIT 5) 
    ORDER BY created_at DESC LIMIT 8");

// 7. Pending Verifications
$pending_list = $conn->query("SELECT u.*, v.type as vehicle_type 
    FROM users u 
    LEFT JOIN vehicles v ON u.id = v.owner_id 
    WHERE u.role = 'partner' AND u.status = 'pending' 
    LIMIT 3");

// 8. Weekly Revenue for Chart
$weekly_revenue = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $rev_res = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE DATE(created_at) = '$date' AND status = 'completed' AND type = 'credit'");
    $weekly_revenue[] = [
        'day' => date('D', strtotime($date)),
        'amount' => $rev_res->fetch_assoc()['total'] ?: 0
    ];
}
$max_rev = 0;
foreach ($weekly_revenue as $day)
    if ($day['amount'] > $max_rev)
        $max_rev = $day['amount'];
if ($max_rev == 0)
    $max_rev = 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripSync - Admin Command Center</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/files/index-BjODfbg0.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }

        /* Custom scrollbar for activity feed */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }

        /* Color Utility Fixes */
        .bg-teal-50 { background-color: #f0fdfa !important; }
        .bg-emerald-50 { background-color: #ecfdf5 !important; }
        .bg-emerald-100 { background-color: #d1fae5 !important; }
        .bg-amber-50 { background-color: #fffbeb !important; }
        .bg-rose-50 { background-color: #fff1f2 !important; }
        .bg-rose-500\/20 { background-color: rgba(244, 63, 94, 0.2) !important; }
        .bg-emerald-500\/20 { background-color: rgba(16, 185, 129, 0.2) !important; }
        .bg-white\/10 { background-color: rgba(255, 255, 255, 0.1) !important; }
        .bg-white\/5 { background-color: rgba(255, 255, 255, 0.05) !important; }
        
        .text-teal-600 { color: #0d9488 !important; }
        .text-emerald-600 { color: #059669 !important; }
        .text-emerald-700 { color: #047857 !important; }
        .text-amber-600 { color: #d97706 !important; }
        .text-rose-600 { color: #e11d48 !important; }
        .text-rose-400 { color: #fb7185 !important; }
        .text-emerald-400 { color: #34d399 !important; }
    </style>
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen bg-gray-50/50">
            <!-- Navigation -->
            <?php include 'includes/navbar.php'; ?>

            <div class="pt-20 pb-16 px-4">
                <div class="max-w-7xl mx-auto">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Admin Command Center</h1>
                            <p class="text-sm text-gray-500 mt-1"><?php echo date('l, F j, Y'); ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">Active Trips:</span>
                            <span
                                class="px-3 py-1 bg-emerald-100 text-emerald-700 text-sm font-bold rounded-full"><?php echo $active_trips; ?></span>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="mb-6">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <div
                                class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-11 h-11 flex items-center justify-center bg-teal-50 rounded-xl">
                                        <i class="ri-group-line text-xl text-teal-600"></i>
                                    </div>
                                    <span
                                        class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full whitespace-nowrap">
                                        <i class="ri-arrow-up-s-line"></i>LIVE
                                    </span>
                                </div>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($active_users); ?></p>
                                <p class="text-xs text-gray-500 mt-1">Active Users</p>
                            </div>
                            <div
                                class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-11 h-11 flex items-center justify-center bg-emerald-50 rounded-xl">
                                        <i class="ri-money-dollar-circle-line text-xl text-emerald-600"></i>
                                    </div>
                                    <span
                                        class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full whitespace-nowrap">
                                        <i class="ri-arrow-up-s-line"></i>Total
                                    </span>
                                </div>
                                <p class="text-2xl font-bold text-gray-900">LKR <?php echo number_format($total_revenue); ?></p>
                                <p class="text-xs text-gray-500 mt-1">Total Revenue</p>
                            </div>
                            <div
                                class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-11 h-11 flex items-center justify-center bg-amber-50 rounded-xl">
                                        <i class="ri-money-dollar-circle-line text-xl text-amber-600"></i>
                                    </div>
                                    <span
                                        class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full whitespace-nowrap">
                                        <i class="ri-arrow-up-s-line"></i>Today
                                    </span>
                                </div>
                                <p class="text-2xl font-bold text-gray-900">LKR <?php echo number_format($today_revenue); ?></p>
                                <p class="text-xs text-gray-500 mt-1">Today's Revenue</p>
                            </div>
                            <div
                                class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="w-11 h-11 flex items-center justify-center bg-rose-50 rounded-xl">
                                        <i class="ri-time-line text-xl text-rose-600"></i>
                                    </div>
                                </div>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $pending_approvals; ?></p>
                                <p class="text-xs text-gray-500 mt-1">Pending Approvals</p>
                            </div>
                        </div>
                    </div>

                    <!-- Map Section -->
                    <div class="mb-6">
                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                            <div class="p-5 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-lg font-bold text-gray-900">Live Operations Map</h3>
                                        <div class="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 rounded-full">
                                            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                            <span class="text-xs font-medium text-emerald-700"><?php echo $active_trips; ?> Active</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="relative">
                                <div id="global-map" class="w-full h-96 z-10"></div>
                                <div class="absolute bottom-4 left-4 z-20 flex flex-col gap-1">
                                    <div class="flex items-center gap-2 bg-white/90 backdrop-blur-sm p-2 rounded-lg shadow-sm border border-gray-100">
                                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
                                        <span class="text-[10px] font-black tracking-widest text-gray-700 uppercase">Live Operations Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Revenue Overview -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                                <div class="flex items-center justify-between mb-5">
                                    <h3 class="text-lg font-bold text-gray-900">Revenue Overview</h3>
                                    <span
                                        class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full whitespace-nowrap">Last
                                        7 Days</span>
                                </div>
                                <div class="h-64 flex items-end justify-between gap-2 px-2 pb-2">
                                    <?php foreach ($weekly_revenue as $day):
    $height = ($day['amount'] / $max_rev) * 100;
?>
                                        <div class="flex-1 flex flex-col items-center group">
                                            <div class="w-full bg-teal-50 rounded-t-lg relative transition-all hover:bg-teal-100" style="height: <?php echo max(5, $height); ?>%;">
                                                <div class="absolute -top-10 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                                                    LKR <?php echo number_format($day['amount']); ?>
                                                </div>
                                            </div>
                                            <span class="text-[10px] text-gray-400 mt-2 font-bold uppercase tracking-tighter"><?php echo $day['day']; ?></span>
                                        </div>
                                    <?php
endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div>
                            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                                <div class="flex items-center justify-between mb-5">
                                    <h3 class="text-lg font-bold text-gray-900">Recent Activity</h3>
                                    <span class="text-xs text-gray-500"><?php echo $recent_activity->num_rows; ?> events</span>
                                </div>
                                <div class="space-y-4 max-h-[420px] overflow-y-auto pr-1 custom-scrollbar">
                                    <?php if ($recent_activity->num_rows == 0): ?>
                                        <p class="text-sm text-gray-500 italic">No recent activity</p>
                                    <?php
else: ?>
                                        <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                            <div class="flex items-start gap-3">
                                                <div class="w-9 h-9 flex items-center justify-center <?php echo $activity['type'] == 'user_reg' ? 'bg-teal-50' : 'bg-emerald-50'; ?> rounded-lg flex-shrink-0 mt-0.5">
                                                    <i class="<?php echo $activity['type'] == 'user_reg' ? 'ri-user-add-line text-teal-600' : 'ri-road-map-line text-emerald-600'; ?> text-sm"></i>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm text-gray-700 leading-snug">
                                                        <?php if ($activity['type'] == 'user_reg'): ?>
                                                            New user <strong><?php echo $activity['detail']; ?></strong> registered.
                                                        <?php
        else: ?>
                                                            Booking <strong>#<?php echo $activity['detail']; ?></strong> updated.
                                                        <?php
        endif; ?>
                                                    </p>
                                                    <p class="text-xs text-gray-400 mt-1"><?php echo date('h:i A', strtotime($activity['created_at'])); ?></p>
                                                </div>
                                            </div>
                                        <?php
    endwhile; ?>
                                    <?php
endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Verifications & Quick Actions -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Pending Verifications -->
                        <div class="bg-white rounded-2xl border border-gray-100 p-6">
                             <div class="flex items-center justify-between mb-5">
                                <h3 class="text-lg font-bold text-gray-900">Pending Verifications</h3>
                                <a href="verification.php" class="text-xs text-teal-600 font-medium hover:underline">View All</a>
                             </div>
                             <div class="space-y-4">
                                <?php if ($pending_list->num_rows == 0): ?>
                                    <p class="text-sm text-gray-500 italic">No pending verifications</p>
                                <?php
else: ?>
                                    <?php while ($p = $pending_list->fetch_assoc()): ?>
                                        <div class="flex items-center justify-between p-3 border border-gray-50 rounded-xl hover:bg-gray-50 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center overflow-hidden">
                                                    <?php if ($p['profile_pic']): ?>
                                                        <img src="../assets/images/<?php echo $p['profile_pic']; ?>" class="w-full h-full object-cover">
                                                    <?php
        else: ?>
                                                        <i class="ri-user-line text-slate-400"></i>
                                                    <?php
        endif; ?>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-gray-900"><?php echo $p['name']; ?></p>
                                                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider"><?php echo $p['vehicle_type']; ?></p>
                                                </div>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-medium"><?php echo date('h:i A', strtotime($p['created_at'])); ?></span>
                                        </div>
                                    <?php
    endwhile; ?>
                                <?php
endif; ?>
                             </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="bg-white rounded-2xl border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-5">Quick Actions</h3>
                            <div class="grid grid-cols-1 gap-3">
                                <a href="verification.php" class="flex items-center justify-between p-4 border border-teal-50 bg-teal-50/20 rounded-xl hover:bg-teal-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <i class="ri-shield-check-line text-teal-600"></i>
                                        <span class="text-sm font-medium text-gray-900">Verify Partners</span>
                                    </div>
                                    <i class="ri-arrow-right-s-line text-gray-400"></i>
                                </a>
                                <a href="finance.php" class="flex items-center justify-between p-4 border border-amber-50 bg-amber-50/20 rounded-xl hover:bg-amber-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <i class="ri-bank-line text-amber-600"></i>
                                        <span class="text-sm font-medium text-gray-900">Manage Finance</span>
                                    </div>
                                    <i class="ri-arrow-right-s-line text-gray-400"></i>
                                </a>
                                <a href="support.php" class="flex items-center justify-between p-4 border border-rose-50 bg-rose-50/20 rounded-xl hover:bg-rose-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <i class="ri-customer-service-2-line text-rose-600"></i>
                                        <span class="text-sm font-medium text-gray-900">Customer Support</span>
                                    </div>
                                    <i class="ri-arrow-right-s-line text-gray-400"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="bg-slate-900 text-slate-400 border-t border-slate-800">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-2">
                            <img alt="TripSync Logo" class="h-8 w-auto opacity-50" src="../assets/images/logo.png">
                            <p class="text-sm">© 2026 TripSync Admin. All rights reserved.</p>
                        </div>
                        <div class="flex gap-6 text-sm">
                            <a href="#" class="hover:text-white transition-colors">Privacy Policy</a>
                            <a href="#" class="hover:text-white transition-colors">Terms of Service</a>
                            <a href="support.php" class="hover:text-white transition-colors">Support</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <!-- Map Scripts -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        // Global Map Initialization
        const map = L.map('global-map').setView([7.8731, 80.7718], 7.5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let markers = {};

        async function updateMap() {
            try {
                const response = await fetch('../api/admin_get_active_trips.php');
                const data = await response.json();
                if (data.success && data.trips) {
                    const currentTrips = data.trips;
                    
                    // Remove markers that are no longer active
                    const tripIds = currentTrips.map(t => t.id.toString());
                    Object.keys(markers).forEach(id => {
                        if (!tripIds.includes(id)) {
                            markers[id].remove();
                            delete markers[id];
                        }
                    });

                    // Add or Update markers
                    currentTrips.forEach(trip => {
                        const iconHtml = `<div class="w-8 h-8 rounded-full border-2 border-white shadow-lg flex items-center justify-center bg-rose-600 text-white animate-bounce-subtle">
                                            <i class="ri-car-fill text-sm"></i>
                                          </div>`;
                        const customIcon = L.divIcon({
                            html: iconHtml,
                            className: 'custom-div-icon',
                            iconSize: [32, 32],
                            iconAnchor: [16, 16]
                        });

                        if (markers[trip.id]) {
                            markers[trip.id].setLatLng([trip.latitude, trip.longitude]);
                        } else {
                            const marker = L.marker([trip.latitude, trip.longitude], { icon: customIcon })
                                .addTo(map)
                                .bindPopup(`
                                    <div class="px-2 py-1">
                                        <h4 class="font-black text-slate-900 border-b pb-1 mb-1">#${trip.reference_no}</h4>
                                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Status: <span class="text-rose-600">${trip.status}</span></p>
                                        <div class="flex items-center gap-2 mb-2">
                                            <div class="w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center"><i class="ri-steering-2-line text-xs"></i></div>
                                            <span class="text-[11px] font-bold text-slate-700">${trip.partner_name}</span>
                                        </div>
                                        <a href="support.php" class="block w-full text-center py-1.5 bg-rose-600 text-white text-[10px] font-black uppercase rounded hover:bg-rose-700 transition-all">Track Hub</a>
                                    </div>
                                `);
                            markers[trip.id] = marker;
                        }
                    });
                }
            } catch (error) {
                console.error("Map Update Error:", error);
            }
        }

        // Poll every 10 seconds
        setInterval(updateMap, 10000);
        updateMap();
    </script>

    <script src="assets/files/index-Dammfq5V.js.download"></script>
</body>

</html>
