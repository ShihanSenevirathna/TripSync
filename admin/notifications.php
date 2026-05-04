<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('admin');

$admin_id = $_SESSION['user_id'];

// Handle Actions
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'clear_logs') {
        $conn->query("DELETE FROM notifications WHERE user_id = $admin_id");
        $msg = "Operational logs cleared.";
    } elseif ($_GET['action'] == 'mark_read' && isset($_GET['id'])) {
        $nid = intval($_GET['id']);
        $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $nid AND user_id = $admin_id");
    }
}

// Fetch counts for tabs
$pending_partners_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'partner' AND status = 'pending'")->fetch_assoc()['count'];
$finance_alerts_count = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'")->fetch_assoc()['count'];
$support_alerts_count = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'")->fetch_assoc()['count'];
$total_alerts = $pending_partners_count + $finance_alerts_count + $support_alerts_count;

// Fetch mixed notifications (simulated logs + real DB notifications)
$notifications = [];

// 1. Pending Partners
$partners = $conn->query("SELECT name, created_at, 'verification' as type FROM users WHERE role = 'partner' AND status = 'pending' ORDER BY created_at DESC LIMIT 5");
while ($row = $partners->fetch_assoc()) {
    $notifications[] = [
        'title' => "New Partner Application: " . $row['name'],
        'time' => $row['created_at'],
        'type' => 'verification',
        'icon' => 'ri-shield-user-line',
        'color' => 'amber',
        'link' => 'verification.php'
    ];
}

// 2. Pending Transactions
$trans = $conn->query("SELECT amount, created_at, 'finance' as type FROM transactions WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
while ($row = $trans->fetch_assoc()) {
    $notifications[] = [
        'title' => "Pending Transaction: LKR " . number_format($row['amount']),
        'time' => $row['created_at'],
        'type' => 'finance',
        'icon' => 'ri-bank-line',
        'color' => 'emerald',
        'link' => 'finance.php'
    ];
}

// 3. Open Tickets
$tickets = $conn->query("SELECT id, subject, created_at, 'support' as type FROM support_tickets WHERE status = 'open' ORDER BY created_at DESC LIMIT 5");
while ($row = $tickets->fetch_assoc()) {
    $notifications[] = [
        'title' => "Open Support Ticket: " . $row['subject'],
        'time' => $row['created_at'],
        'type' => 'support',
        'icon' => 'ri-customer-service-2-line',
        'color' => 'rose',
        'link' => "view_ticket.php?id=" . $row['id']
    ];
}

// 4. Real Notifications for Admin
$real_n = $conn->query("SELECT * FROM notifications WHERE user_id = $admin_id ORDER BY created_at DESC LIMIT 10");
while ($row = $real_n->fetch_assoc()) {
    $notifications[] = [
        'title' => $row['title'],
        'time' => $row['created_at'],
        'type' => 'system',
        'icon' => 'ri-notification-3-line',
        'color' => 'slate',
        'link' => '#'
    ];
}

// Sort by time
usort($notifications, function ($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Handle Broadcast
if (isset($_POST['send_broadcast'])) {
    $title = $_POST['broadcast_title'];
    $message = $_POST['broadcast_message'];
    $target = $_POST['broadcast_target']; // 'all', 'customer', 'partner'

    $query_users = "SELECT id FROM users";
    if ($target == 'customer') $query_users .= " WHERE role = 'customer'";
    if ($target == 'partner') $query_users .= " WHERE role = 'partner'";

    $all_users = $conn->query($query_users);
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'broadcast')");
    while ($u = $all_users->fetch_assoc()) {
        $uid = $u['id'];
        $stmt->bind_param("iss", $uid, $title, $message);
        $stmt->execute();
    }
    $msg = "Broadcast sent successfully to " . ($target == 'all' ? "all users" : $target . "s") . ".";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - TripSync</title>
    <meta name="description" content="Administrative notifications hub for managing TripSync platform updates, security alerts, and operational tasks.">
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="assets/files/index-BjODfbg0.css">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen bg-gray-50/50">
             <!-- Navigation -->
             <?php include 'includes/navbar.php'; ?>

            <main class="pt-24 pb-16 px-4 font-inter">
                <div class="max-w-4xl mx-auto">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 font-outfit">System Notifications</h1>
                            <p class="text-sm text-gray-500 mt-1">Platform-wide alerts, security logs, and operational tasks</p>
                        </div>
                        <a href="?action=clear_logs" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer whitespace-nowrap">
                            <i class="ri-delete-bin-line text-base"></i>Clear Logs
                        </a>
                    </div>

                    <?php if (isset($msg)): ?>
                        <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-100 flex items-center gap-2">
                            <i class="ri-checkbox-circle-line"></i> <?php echo $msg; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Broadcast Form -->
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-8 shadow-sm">
                        <h2 class="text-sm font-black uppercase tracking-widest text-gray-900 mb-4 flex items-center gap-2">
                            <i class="ri-megaphone-line text-lg text-rose-500"></i>
                            Send Platform Broadcast
                        </h2>
                        <form method="POST" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Notification Title</label>
                                    <input name="broadcast_title" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 focus:outline-none transition-all" placeholder="e.g. Scheduled System Maintenance">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Target Audience</label>
                                    <select name="broadcast_target" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 focus:outline-none cursor-pointer transition-all">
                                        <option value="all">All Users</option>
                                        <option value="customer">Travelers Only</option>
                                        <option value="partner">Partners Only</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Message Content</label>
                                <textarea name="broadcast_message" required rows="2" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 focus:outline-none transition-all" placeholder="Briefly describe the update or alert..."></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="send_broadcast" class="flex items-center gap-2 px-6 py-2.5 bg-rose-600 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-rose-700 transition-all cursor-pointer shadow-lg shadow-rose-200">
                                    <i class="ri-send-plane-fill"></i> Send Broadcast
                                </button>
                            </div>
                        </form>
                    </div>


                    <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1 no-scrollbar">
                        <button class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-slate-900 text-white">All Logs<span class="text-xs px-1.5 py-0.5 rounded-full bg-white/20 text-white"><?php echo count($notifications); ?></span></button>
                        <a href="verification.php" class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-gray-300">Verification<span class="text-xs px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-600"><?php echo $pending_partners_count; ?></span></a>
                        <a href="finance.php" class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-gray-300">Finance<span class="text-xs px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-600"><?php echo $finance_alerts_count; ?></span></a>
                        <a href="support.php" class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-gray-300">Support Hub<span class="text-xs px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-600"><?php echo $support_alerts_count; ?></span></a>
                    </div>

                    <?php if ($total_alerts > 0): ?>
                    <div class="flex items-center gap-3 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 mb-6">
                        <div class="w-8 h-8 flex items-center justify-center bg-rose-100 rounded-lg flex-shrink-0 text-rose-600">
                            <i class="ri-error-warning-fill text-base"></i>
                        </div>
                        <p class="text-sm text-rose-800 font-medium">System Alert: <strong><?php echo $total_alerts; ?></strong> high-priority updates require your attention.</p>
                    </div>
                    <?php endif; ?>

                    <div class="space-y-3">
                        <?php if (empty($notifications)): ?>
                            <div class="flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-dashed border-gray-200 text-gray-400">
                                <i class="ri-notification-off-line text-4xl mb-3"></i>
                                <p class="text-sm">No notifications found at the moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                                <div class="relative flex gap-4 p-4 rounded-xl border transition-all hover:shadow-md cursor-pointer bg-white border-gray-100 group" onclick="window.location.href='<?php echo $n['link']; ?>'">
                                    <div class="w-11 h-11 flex items-center justify-center rounded-xl flex-shrink-0 bg-gray-50 text-<?php echo $n['color']; ?>-600 group-hover:bg-<?php echo $n['color']; ?>-50 transition-all">
                                        <i class="<?php echo $n['icon']; ?> text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0 pr-6">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="text-sm font-bold text-gray-900 leading-snug"><?php echo htmlspecialchars($n['title']); ?></h4>
                                            <span class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded bg-gray-100 text-gray-400"><?php echo $n['type']; ?></span>
                                        </div>
                                        <div class="flex items-center gap-3 text-[11px] text-gray-400">
                                            <span class="font-medium"><?php echo time_elapsed_string($n['time']); ?></span>
                                            <span class="w-1 h-1 bg-gray-200 rounded-full"></span>
                                            <span class="text-rose-600 font-black uppercase tracking-tighter hover:underline">Take Action <i class="ri-arrow-right-line ml-0.5"></i></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>

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
