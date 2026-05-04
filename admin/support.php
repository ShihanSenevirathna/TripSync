<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('admin');

// Fetch Stats
$total_tickets_res = $conn->query("SELECT COUNT(*) as count FROM support_tickets");
$total_tickets = $total_tickets_res ? $total_tickets_res->fetch_assoc()['count'] : 0;

$open_tickets_res = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
$open_tickets = $open_tickets_res ? $open_tickets_res->fetch_assoc()['count'] : 0;

$high_priority_res = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE priority IN ('high', 'emergency') AND status != 'resolved'");
$high_priority = $high_priority_res ? $high_priority_res->fetch_assoc()['count'] : 0;

// Calculate Average Response (Real)
$avg_res = $conn->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_time FROM support_tickets WHERE status = 'resolved'");
$avg_time_val = $avg_res->fetch_assoc()['avg_time'];
$average_response = ($avg_time_val > 0) ? round($avg_time_val) . "m" : "N/A";

// Fetch Latest Emergency Alert
$emergency_alert = $conn->query("SELECT t.*, u.name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.priority = 'emergency' AND t.status != 'resolved' ORDER BY t.created_at DESC LIMIT 1")->fetch_assoc();

// Fetch Active Conversations (Latest Open Tickets)
$live_convs = $conn->query("SELECT t.*, u.name as user_name, u.role FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.status = 'open' ORDER BY t.created_at DESC LIMIT 3");
// Fetch Latest Tickets
$tickets = $conn->query("SELECT t.*, u.name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 10");

// Handle Ticket Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $tid = intval($_GET['id']);
    $action = $_GET['action'];
    if ($action == 'resolve') {
        $conn->query("UPDATE support_tickets SET status = 'resolved' WHERE id = $tid");
        $msg = "Ticket #ST-" . str_pad($tid, 4, '0', STR_PAD_LEFT) . " has been marked as resolved.";
        // Refresh data
        $tickets = $conn->query("SELECT t.*, u.name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 10");
        $open_tickets_res = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
        $open_tickets = $open_tickets_res ? $open_tickets_res->fetch_assoc()['count'] : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Safety Hub - TripSync Admin</title>
    <meta name="description" content="TripSync Administrative Support & Safety Hub for managing traveler issues and platform security.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="assets/files/index-BjODfbg0.css">
    
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

        .priority-high { color: #e11d48; background-color: #fff1f2; border-color: #ffe4e6; }
        .priority-medium { color: #d97706; background-color: #fffbeb; border-color: #fef3c7; }
        .priority-low { color: #059669; background-color: #ecfdf5; border-color: #d1fae5; }
        
        /* Color Utility Fixes */
        .bg-rose-900 { background-color: #4c0519 !important; }
        .bg-rose-800 { background-color: #881337 !important; }
        .bg-rose-700 { background-color: #be123c !important; }
        .bg-rose-600 { background-color: #e11d48 !important; }
        .bg-rose-500 { background-color: #f43f5e !important; }
        .bg-rose-50 { background-color: #fff1f2 !important; }
        .bg-teal-50 { background-color: #f0fdfa !important; }
        .bg-amber-50 { background-color: #fffbeb !important; }
        .bg-emerald-50 { background-color: #ecfdf5 !important; }
        .bg-white\/10 { background-color: rgba(255, 255, 255, 0.1) !important; }
        .bg-white\/5 { background-color: rgba(255, 255, 255, 0.05) !important; }
        
        .text-rose-100 { color: #ffe4e6 !important; }
        .text-rose-300 { color: #fda4af !important; }
        .text-rose-900 { color: #4c0519 !important; }
        
        .border-rose-800 { border-color: #881337 !important; }
        .border-rose-600 { border-color: #be123c !important; }
        .border-white\/20 { border-color: rgba(255, 255, 255, 0.2) !important; }
    </style>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen bg-gray-50/50">
            <!-- Navigation -->
            <?php include 'includes/navbar.php'; ?>

            <div class="pt-20 pb-16 px-4">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                        <h1 class="text-2xl font-bold text-gray-900">Support & Safety Hub</h1>
                        <p class="text-sm text-gray-500 mt-1">Resolve traveler issues and monitor platform safety</p>
                    </div>

                    <?php if (isset($msg)): ?>
                        <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-100 flex items-center gap-2">
                            <i class="ri-checkbox-circle-line"></i> <?php echo $msg; ?>
                        </div>
                    <?php
endif; ?>

                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                            <div class="w-11 h-11 flex items-center justify-center bg-teal-50 rounded-xl mb-3">
                                <i class="ri-ticket-2-line text-xl text-teal-600"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_tickets; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Total Tickets</p>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                            <div class="w-11 h-11 flex items-center justify-center bg-amber-50 rounded-xl mb-3">
                                <i class="ri-history-line text-xl text-amber-600"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $open_tickets; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Open Cases</p>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                            <div class="w-11 h-11 flex items-center justify-center bg-rose-50 rounded-xl mb-3">
                                <i class="ri-error-warning-line text-xl text-rose-600"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $high_priority; ?></p>
                            <p class="text-xs text-gray-500 mt-1">High Priority</p>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md transition-shadow">
                            <div class="w-11 h-11 flex items-center justify-center bg-emerald-50 rounded-xl mb-3">
                                <i class="ri-time-line text-xl text-emerald-600"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $average_response; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Avg Response</p>
                        </div>
                    </div>

                    <!-- Active Safety Alert (Real) -->
                    <?php if ($emergency_alert): ?>
                    <div class="mb-8 p-5 bg-rose-900 rounded-2xl border border-rose-800 shadow-xl shadow-rose-900/20 text-white relative overflow-hidden">
                        <div class="absolute top-[-20%] right-[-10%] w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="w-14 h-14 flex items-center justify-center bg-white/10 backdrop-blur-md rounded-2xl border border-white/20">
                                <i class="ri-alarm-warning-fill text-2xl text-rose-300"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-lg font-black uppercase tracking-wider">Active Emergency Alert</h3>
                                    <span class="px-2 py-0.5 bg-rose-500 text-[10px] font-black uppercase rounded animate-pulse">Live</span>
                                </div>
                                <p class="text-rose-100 text-sm mt-1">
                                    <strong><?php echo $emergency_alert['user_name']; ?>:</strong> <?php echo $emergency_alert['subject']; ?> - <?php echo $emergency_alert['description']; ?>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <a href="view_ticket.php?id=<?php echo $emergency_alert['id']; ?>" class="px-6 py-2.5 bg-white text-rose-900 text-xs font-black uppercase tracking-widest rounded-xl hover:bg-rose-50 transition-all cursor-pointer">Take Action</a>
                                <a href="dashboard.php" class="px-6 py-2.5 bg-rose-700 text-white text-xs font-black uppercase tracking-widest rounded-xl border border-rose-600 hover:bg-rose-600 transition-all cursor-pointer">View Map</a>
                            </div>
                        </div>
                    </div>
                    <?php
endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Support Tickets Table -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                                    <h3 class="text-lg font-bold text-gray-900">Recent Support Tickets</h3>
                                    <div class="flex items-center gap-2">
                                        <button class="p-2 bg-gray-50 text-gray-400 rounded-lg hover:text-teal-600 transition-all"><i class="ri-filter-3-line"></i></button>
                                        <button class="p-2 bg-gray-50 text-gray-400 rounded-lg hover:text-teal-600 transition-all"><i class="ri-refresh-line"></i></button>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left">
                                        <thead class="bg-gray-50 border-b border-gray-100">
                                            <tr>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase text-gray-400 tracking-widest">User</th>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase text-gray-400 tracking-widest">Subject</th>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase text-gray-400 tracking-widest">Priority</th>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase text-gray-400 tracking-widest">Status</th>
                                                <th class="px-6 py-4 text-[10px] font-black uppercase text-gray-400 tracking-widest text-right">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <?php if ($tickets && $tickets->num_rows > 0): ?>
                                                <?php while ($t = $tickets->fetch_assoc()):
        $priority_class = ($t['priority'] == 'high' ? 'priority-high' : ($t['priority'] == 'medium' ? 'priority-medium' : 'priority-low'));
?>
                                                    <tr class="hover:bg-gray-50 transition-colors group cursor-pointer" onclick="window.location.href='view_ticket.php?id=<?php echo $t['id']; ?>'">
                                                        <td class="px-6 py-4">
                                                            <div class="flex items-center gap-3">
                                                                <div class="w-9 h-9 bg-slate-100 rounded-full flex items-center justify-center group-hover:bg-teal-50 transition-all">
                                                                    <i class="ri-<?php echo $t['priority'] === 'emergency' ? 'alarm-warning' : 'user-line'; ?> text-slate-400 group-hover:text-teal-600"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="text-sm font-bold text-gray-900"><?php echo $t['user_name']; ?></p>
                                                                    <p class="text-xs text-gray-400 tracking-tighter uppercase font-bold">ID: #ST-<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            <p class="text-sm text-gray-700 font-medium"><?php echo $t['subject']; ?></p>
                                                            <p class="text-xs text-gray-400 mt-0.5 truncate max-w-[200px]"><?php echo $t['description']; ?></p>
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border <?php echo ($t['priority'] === 'emergency' ? 'bg-rose-600 text-white' : $priority_class); ?>">
                                                                <?php echo strtoupper($t['priority']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 text-sm">
                                                            <span class="<?php echo $t['status'] == 'open' ? 'text-amber-600' : 'text-emerald-600'; ?> font-medium flex items-center gap-1.5">
                                                                <div class="w-1.5 h-1.5 rounded-full <?php echo $t['status'] == 'open' ? 'bg-amber-600 animate-pulse' : 'bg-emerald-600'; ?>"></div>
                                                                <?php echo ucfirst($t['status']); ?>
                                                            </span>
                                                        </td>
                                                         <td class="px-6 py-4 text-sm text-gray-400 text-right">
                                                            <div class="flex items-center justify-end gap-2">
                                                                <span><?php echo date('M j, Y', strtotime($t['created_at'])); ?></span>
                                                                <?php if ($t['status'] == 'open'): ?>
                                                                    <a href="?action=resolve&id=<?php echo $t['id']; ?>" class="ml-2 p-1.5 bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-600 hover:text-white transition-all" title="Mark as Resolved">
                                                                        <i class="ri-check-line"></i>
                                                                    </a>
                                                                <?php
        endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php
    endwhile; ?>
                                            <?php
else: ?>
                                                <tr>
                                                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 italic text-sm">No support tickets found</td>
                                                </tr>
                                            <?php
endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-4 border-t border-gray-100 bg-gray-50/30">
                                    <button class="w-full py-2.5 text-xs font-black uppercase tracking-widest text-teal-700 hover:bg-teal-50 rounded-xl transition-all cursor-pointer">View All Tickets</button>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar Actions & Monitor -->
                        <div class="space-y-6">
                            <!-- Live Chat Monitor -->
                            <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm text-inter">
                                <h3 class="text-sm font-black uppercase tracking-widest text-gray-900 mb-5 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Live Conversations
                                </h3>
                                <div class="space-y-4">
                                    <?php if ($live_convs && $live_convs->num_rows > 0): ?>
                                        <?php while ($conv = $live_convs->fetch_assoc()): ?>
                                            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100 group hover:border-teal-200 transition-all cursor-pointer">
                                                <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-white shadow-sm bg-teal-50 flex items-center justify-center text-teal-600">
                                                    <i class="ri-<?php echo($conv['role'] == 'partner' ? 'steering-2-line' : 'user-3-line'); ?>"></i>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-[10px] font-black uppercase text-gray-400 tracking-tighter"><?php echo strtoupper($conv['role']); ?> REQUEST</p>
                                                    <p class="text-xs font-bold text-gray-800 truncate"><?php echo $conv['user_name']; ?>: <?php echo $conv['subject']; ?></p>
                                                </div>
                                            </div>
                                        <?php
    endwhile; ?>
                                    <?php
else: ?>
                                        <div class="text-center py-4 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                            <p class="text-xs text-gray-400">No active requests</p>
                                        </div>
                                    <?php
endif; ?>
                                </div>
                            </div>

                            <!-- Emergency Management -->
                            <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl shadow-slate-900/10">
                                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 flex items-center gap-2">
                                    <i class="ri-shield-flash-line text-lg text-amber-500"></i>
                                    Safety Protocol
                                </h3>
                                <div class="space-y-3">
                                    <button class="w-full py-3 bg-rose-600/20 border border-rose-500/30 text-rose-300 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-rose-600 hover:text-white transition-all cursor-pointer">Global System Alert</button>
                                    <button class="w-full py-3 bg-teal-600/20 border border-teal-500/30 text-teal-300 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-teal-600 hover:text-white transition-all cursor-pointer">Incident Reporting</button>
                                    <button class="w-full py-3 bg-slate-800 border border-slate-700 text-slate-300 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-slate-700 hover:text-white transition-all cursor-pointer">Safety Manual</button>
                                </div>
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
</body>

</html>
