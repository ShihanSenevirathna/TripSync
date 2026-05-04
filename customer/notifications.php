<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('customer');

$user_id = $_SESSION['user_id'];

// Actions
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'mark_read' && isset($_GET['id'])) {
        $nid = intval($_GET['id']);
        $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $nid AND user_id = $user_id");
    } elseif ($_GET['action'] == 'mark_all_read') {
        $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    }
    header("Location: notifications.php");
    exit();
}

$filter = $_GET['filter'] ?? 'all';
$query = "SELECT * FROM notifications WHERE user_id = $user_id";
if ($filter == 'unread') $query .= " AND is_read = 0";
$query .= " ORDER BY created_at DESC";

$res = $conn->query($query);
$notifications = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$counts = [
    'all' => count($notifications),
    'unread' => 0,
    'trip' => 0,
    'booking' => 0,
    'system' => 0
];

// Recalculate counts properly
$all_res = $conn->query("SELECT type, is_read FROM notifications WHERE user_id = $user_id");
while($row = $all_res->fetch_assoc()) {
    if (!$row['is_read']) $counts['unread']++;
    if (strpos($row['type'], 'trip') !== false) $counts['trip']++;
    if (strpos($row['type'], 'booking') !== false) $counts['booking']++;
    if ($row['type'] == 'broadcast' || $row['type'] == 'info') $counts['system']++;
}

function getNotifyStyle($type) {
    switch($type) {
        case 'trip_update': return ['icon' => 'ri-map-pin-line', 'color' => 'teal'];
        case 'booking_update': return ['icon' => 'ri-hotel-line', 'color' => 'amber'];
        case 'wallet_update': return ['icon' => 'ri-wallet-line', 'color' => 'emerald'];
        case 'success': return ['icon' => 'ri-checkbox-circle-line', 'color' => 'emerald'];
        case 'warning': return ['icon' => 'ri-error-warning-line', 'color' => 'amber'];
        case 'broadcast': return ['icon' => 'ri-megaphone-line', 'color' => 'rose'];
        case 'support_update': return ['icon' => 'ri-chat-1-line', 'color' => 'teal'];
        default: return ['icon' => 'ri-notification-3-line', 'color' => 'gray'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - TripSync</title>
    <meta name="description" content="Stay updated on your trips, bookings, and more with TripSync notifications.">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script type="module" src="../assets/js/main.js"></script>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen bg-gradient-to-br from-gray-50 to-teal-50/30">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>

            <main class="pt-24 pb-16 px-4">
                <div class="max-w-4xl mx-auto">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 font-outfit">Notifications</h1>
                            <p class="text-sm text-gray-500 mt-1">Stay updated on your trips, bookings, and more</p>
                        </div>
                        <a href="?action=mark_all_read"
                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-teal-600 hover:bg-teal-50 rounded-lg transition-colors cursor-pointer whitespace-nowrap">
                            <i class="ri-check-double-line text-base"></i>Mark All Read
                        </a>
                    </div>


                    <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1 no-scrollbar">
                        <a href="?filter=all"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer whitespace-nowrap <?php echo $filter == 'all' ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 border border-gray-200'; ?>">All<span
                                class="text-xs px-1.5 py-0.5 rounded-full <?php echo $filter == 'all' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'; ?>"><?php echo $counts['all']; ?></span></a>
                        <a href="?filter=unread"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer whitespace-nowrap <?php echo $filter == 'unread' ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 border border-gray-200'; ?>">Unread<span
                                class="text-xs px-1.5 py-0.5 rounded-full <?php echo $filter == 'unread' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'; ?>"><?php echo $counts['unread']; ?></span></a>
                    </div>

                    <?php if ($counts['unread'] > 0): ?>
                    <div class="flex items-center gap-3 bg-teal-50 border border-teal-200 rounded-xl px-4 py-3 mb-6">
                        <div class="w-8 h-8 flex items-center justify-center bg-teal-100 rounded-lg flex-shrink-0 text-teal-600">
                            <i class="ri-notification-3-line text-base"></i>
                        </div>
                        <p class="text-sm text-teal-800">You have <strong><?php echo $counts['unread']; ?></strong> unread notifications</p>
                    </div>
                    <?php endif; ?>

                    <div class="space-y-3">
                        <?php if (empty($notifications)): ?>
                            <div class="flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-dashed border-gray-200 text-gray-400">
                                <i class="ri-notification-off-line text-4xl mb-3"></i>
                                <p class="text-sm">No notifications found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): 
                                $style = getNotifyStyle($n['type']);
                                $bgClass = $n['is_read'] ? 'bg-white border-gray-100' : 'bg-'.$style['color'].'-50/40 border-'.$style['color'].'-100';
                            ?>
                                <script>
                                    function handleNotificationClick(id, type, title) {
                                        if (type === 'support_update') {
                                            const match = title.match(/#ST-(\d+)/);
                                            if (match) {
                                                window.location.href = `view_ticket.php?id=${parseInt(match[1])}&action=mark_read&id=${id}`;
                                                return;
                                            }
                                        }
                                        window.location.href = `?action=mark_read&id=${id}`;
                                    }
                                </script>
                                <div class="relative flex gap-4 p-4 rounded-xl border transition-all hover:shadow-md cursor-pointer <?php echo $bgClass; ?>" onclick="handleNotificationClick(<?php echo $n['id']; ?>, '<?php echo $n['type']; ?>', '<?php echo htmlspecialchars($n['title']); ?>')">
                                    <?php if (!$n['is_read']): ?>
                                    <div class="absolute top-4 right-4 w-2.5 h-2.5 rounded-full bg-<?php echo $style['color']; ?>-500"></div>
                                    <?php endif; ?>
                                    <div class="w-11 h-11 flex items-center justify-center rounded-xl flex-shrink-0 bg-<?php echo $style['color']; ?>-100 text-<?php echo $style['color']; ?>-600">
                                        <i class="<?php echo $style['icon']; ?> text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0 pr-6">
                                        <h4 class="text-sm font-semibold text-gray-900 mb-1 leading-snug"><?php echo htmlspecialchars($n['title']); ?></h4>
                                        <p class="text-xs text-gray-500 leading-relaxed mb-2 line-clamp-2"><?php echo htmlspecialchars($n['message']); ?></p>
                                        <div class="flex items-center gap-3">
                                            <span class="text-[11px] text-gray-400"><?php echo time_elapsed_string($n['created_at']); ?></span>
                                            <?php if (!$n['is_read']): ?>
                                            <span class="text-[11px] text-<?php echo $style['color']; ?>-600 font-bold uppercase tracking-widest">Mark Read</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="relative flex-shrink-0 mt-2">
                                        <i class="ri-arrow-right-s-line text-gray-300"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mt-10 text-center">
                        <a class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors cursor-pointer"
                            href="dashboard.php">
                            <i class="ri-arrow-left-line text-base"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </main>

            <!-- Footer -->
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
                                        href="dashboard.php">Dashboard</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="plan_trip.php">Plan Trip</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="marketplace.php">Marketplace</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="wallet.php">Wallet</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="trip_history.php">Trip History</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Support</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="help.php">Help Center</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Terms of Service</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Privacy Policy</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Connect With Us</h3>
                            <div class="flex gap-3 mb-4">
                                <a href="https://facebook.com/" target="_blank"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-facebook-fill text-lg"></i></a>
                                <a href="https://instagram.com/" target="_blank"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-instagram-line text-lg"></i></a>
                                <a href="https://twitter.com/" target="_blank"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-twitter-x-line text-lg"></i></a>
                            </div>
                            <p class="text-teal-50 text-sm">info@tripsync.lk</p>
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
