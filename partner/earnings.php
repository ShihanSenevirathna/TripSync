<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('partner');

$user_id = $_SESSION['user_id'];

// 1. Fetch Wallet Balance & Bank Details
$stmt = $conn->prepare("SELECT balance, bank_name, account_number, branch_name FROM wallets w LEFT JOIN users u ON w.user_id = u.id WHERE w.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data_row = $stmt->get_result()->fetch_assoc();
$balance = $data_row['balance'] ?? 0.00;
$bank_name = $data_row['bank_name'] ?? '';
$account_number = $data_row['account_number'] ?? '';
$branch_name = $data_row['branch_name'] ?? '';

// 2. Fetch Earnings Stats
// Today
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'credit' AND DATE(created_at) = CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$today_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// This Week
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'credit' AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$week_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// This Month
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'credit' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$month_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// 4. Fetch 7-Day Revenue for Bar Chart (Net vs Gross)
$weekly_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    // Net
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'credit' AND DATE(created_at) = ?");
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $net_total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Gross
    $stmt = $conn->prepare("SELECT SUM(b.total_price) as total FROM bookings b JOIN transactions t ON b.id = t.booking_id WHERE t.user_id = ? AND t.type = 'credit' AND DATE(t.created_at) = ?");
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $gross_total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    $weekly_data[date('D', strtotime($date))] = [
        'net' => $net_total,
        'gross' => $gross_total
    ];
}

// 5. Fetch 6-Month Trend for Line Chart
$trend_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'credit' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->bind_param("is", $user_id, $month);
    $stmt->execute();
    $month_total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $trend_data[date('M', strtotime($month . "-01"))] = $month_total;
}

// 6. Performance Metrics
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE target_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rev_data = $stmt->get_result()->fetch_assoc();
$avg_rating = round($rev_data['avg_rating'] ?? 0, 1) ?: 'N/A';
$total_reviews = $rev_data['total_reviews'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE assigned_partner_id = ? AND status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_trips = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE assigned_partner_id = ? AND status = 'cancelled'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cancelled_trips = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$fulfillment_rate = ($completed_trips + $cancelled_trips > 0) ? round(($completed_trips / ($completed_trips + $cancelled_trips)) * 100) : 100;

// 7. Restore Commission Logs
$stmt = $conn->prepare("
    SELECT t.*, b.total_price as gross_fare, u.name as customer_name, b.start_date 
    FROM transactions t 
    JOIN bookings b ON t.booking_id = b.id 
    JOIN users u ON b.user_id = u.id 
    WHERE t.user_id = ? AND t.type = 'credit' 
    ORDER BY t.created_at DESC LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings & Performance - TripSync Partner</title>
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>

<body class="bg-gray-100 flex flex-col min-h-screen">
    <div id="root">
        <div class="min-h-screen">
            <!-- Navigation -->
            <?php include 'includes/navbar.php'; ?>

            <main class="pt-20 pb-16 px-4">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Earnings & Performance</h1>
                            <p class="text-sm text-gray-500 mt-1">Track your revenue and achievements</p>
                        </div>
                        <!-- Tab Controls -->
                        <div
                            class="flex items-center bg-white rounded-full p-1 border border-gray-200 shadow-sm self-start">
                            <button id="earnings-tab-btn"
                                class="px-6 py-2 text-sm font-bold rounded-full transition-all bg-emerald-600 text-white shadow-md">Earnings</button>
                            <button id="performance-tab-btn"
                                class="px-6 py-2 text-sm font-bold rounded-full transition-all text-gray-600 hover:text-gray-900">Performance</button>
                        </div>
                    </div>

                    <!-- Earnings Content Section -->
                    <div id="earnings-section" class="space-y-6">
                        <!-- Overview Cards -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-wider">Earnings Overview
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <!-- Today -->
                                <div
                                    class="bg-gray-50/50 rounded-2xl p-6 border border-gray-100 flex flex-col items-start transition-all hover:shadow-md cursor-default">
                                    <div
                                        class="w-12 h-12 flex items-center justify-center bg-emerald-600 rounded-xl mb-4 shadow-sm text-white">
                                        <i class="ri-money-dollar-circle-fill text-2xl"></i>
                                    </div>
                                    <p class="text-xs text-gray-400 font-bold uppercase mb-1">Today's Share (80%)</p>
                                    <p class="text-2xl font-black text-gray-900">LKR <?php echo number_format($today_earnings); ?></p>
                                </div>
                                <!-- This Week -->
                                <div
                                    class="bg-gray-50/50 rounded-2xl p-6 border border-gray-100 flex flex-col items-start transition-all hover:shadow-md cursor-default">
                                    <div
                                        class="w-12 h-12 flex items-center justify-center bg-amber-600 rounded-xl mb-4 shadow-sm text-white">
                                        <i class="ri-calendar-line text-2xl"></i>
                                    </div>
                                    <p class="text-xs text-gray-400 font-bold uppercase mb-1">Weekly Total</p>
                                    <p class="text-2xl font-black text-gray-900">LKR <?php echo number_format($week_earnings); ?></p>
                                </div>
                                <!-- This Month -->
                                <div
                                    class="bg-gray-50/50 rounded-2xl p-6 border border-gray-100 flex flex-col items-start transition-all hover:shadow-md cursor-default">
                                    <div
                                        class="w-12 h-12 flex items-center justify-center bg-sky-600 rounded-xl mb-4 shadow-sm text-white">
                                        <i class="ri-bar-chart-box-line text-2xl"></i>
                                    </div>
                                    <p class="text-xs text-gray-400 font-bold uppercase mb-1">Monthly Total</p>
                                    <p class="text-2xl font-black text-gray-900">LKR <?php echo number_format($month_earnings); ?></p>
                                </div>
                                <!-- Wallet Balance / Pending Payout -->
                                <div
                                    class="bg-emerald-700 rounded-2xl p-6 border border-emerald-800 flex flex-col items-start transition-all hover:shadow-md cursor-default relative overflow-hidden group">
                                    <div class="absolute top-0 right-0 p-4 opacity-10 transform translate-x-4 -translate-y-4 group-hover:translate-x-0 group-hover:translate-y-0 transition-all duration-500">
                                        <i class="ri-wallet-3-line text-8xl text-white"></i>
                                    </div>
                                    <div class="w-12 h-12 flex items-center justify-center bg-white/20 rounded-xl mb-4 shadow-sm text-white">
                                        <i class="ri-wallet-3-fill text-2xl"></i>
                                    </div>
                                    <p class="text-xs text-emerald-100 font-bold uppercase mb-1">Available Balance</p>
                                    <p class="text-2xl font-black text-white">LKR <?php echo number_format($balance); ?></p>
                                    <button 
                                        id="request-payout-btn"
                                        onclick="requestPayout(<?php echo $balance; ?>, '<?php echo $account_number; ?>')"
                                        class="mt-4 w-full py-2 bg-white text-emerald-700 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-emerald-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        Request Payout
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Bank Disbursal Settings</h3>
                                <?php if (!$account_number): ?>
                                    <span class="px-3 py-1 bg-red-100 text-red-600 text-[10px] font-bold rounded-full uppercase tracking-widest animate-pulse">Action Required</span>
                                <?php
else: ?>
                                    <span class="px-3 py-1 bg-emerald-100 text-emerald-600 text-[10px] font-bold rounded-full uppercase tracking-widest">Verified</span>
                                <?php
endif; ?>
                            </div>
                            <form id="bank-details-form" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Bank Name</label>
                                    <input type="text" name="bank_name" value="<?php echo htmlspecialchars($bank_name); ?>" placeholder="e.g. Commercial Bank"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Branch Name</label>
                                    <input type="text" name="branch_name" value="<?php echo htmlspecialchars($branch_name); ?>" placeholder="e.g. Colombo 07"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Account Number</label>
                                    <input type="text" name="account_number" value="<?php echo htmlspecialchars($account_number); ?>" placeholder="0000 0000 0000 0000"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none transition-all">
                                </div>
                                <div class="md:col-span-3">
                                    <button type="submit" 
                                        class="px-8 py-3 bg-gray-900 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-black transition-all shadow-lg hover:shadow-gray-200">
                                        Update Bank Details
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Charts Container -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Weekly Revenue Chart -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em]">Weekly Revenue</h3>
                                    <span class="text-[10px] bg-emerald-50 text-emerald-600 font-bold px-3 py-1 rounded-full border border-emerald-100">Last 7 Days</span>
                                </div>
                                <div class="h-72 relative">
                                    <canvas id="weeklyRevenueChart"></canvas>
                                </div>
                            </div>

                            <!-- 6-Month Trend Chart -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em]">6-Month Growth</h3>
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 bg-indigo-500 rounded-full"></div>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase">Growth Rate</span>
                                    </div>
                                </div>
                                <div class="h-72 relative">
                                    <canvas id="monthlyTrendChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Commission Log Table -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-center justify-between mb-8">
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Commission Logs
                                </h3>
                                <div class="px-4 py-1.5 bg-gray-50 rounded-full border border-gray-100">
                                    <span class="text-[11px] text-gray-400 font-bold uppercase tracking-widest">Partner Share:
                                        <span class="text-emerald-600">80%</span></span>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr
                                            class="text-xs font-black text-gray-300 uppercase tracking-[0.1em] text-left">
                                            <th class="pb-6 px-2">Date</th>
                                            <th class="pb-6 px-2">Customer</th>
                                            <th class="pb-6 px-2 text-right">Gross Fare</th>
                                            <th class="pb-6 px-2 text-right">Commission (20%)</th>
                                            <th class="pb-6 px-2 text-right">Net Earning</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50 border-t border-gray-50">
                                        <?php if (empty($logs)): ?>
                                            <tr>
                                                <td colspan="5" class="py-12 text-center">
                                                    <div class="flex flex-col items-center">
                                                        <i class="ri-history-line text-4xl text-gray-200 mb-2"></i>
                                                        <p class="text-gray-400 font-medium">No transaction history yet</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
else: ?>
                                            <?php foreach ($logs as $log):
        $commission = $log['gross_fare'] * 0.20;
?>
                                                <tr class="group hover:bg-gray-50/50 transition-all">
                                                    <td class="py-5 px-2 text-gray-500 font-medium text-xs"><?php echo date('Y-m-d', strtotime($log['created_at'])); ?></td>
                                                    <td class="py-5 px-2 text-gray-900 font-bold"><?php echo htmlspecialchars($log['customer_name']); ?></td>
                                                    <td class="py-5 px-2 text-right text-gray-500 font-medium whitespace-nowrap">LKR <?php echo number_format($log['gross_fare']); ?></td>
                                                    <td class="py-5 px-2 text-right text-red-500 font-bold whitespace-nowrap">- LKR <?php echo number_format($commission); ?></td>
                                                    <td class="py-5 px-2 text-right text-emerald-600 font-black whitespace-nowrap">LKR <?php echo number_format($log['amount']); ?></td>
                                                </tr>
                                            <?php
    endforeach; ?>
                                        <?php
endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Content Section (Placeholder) -->
                    <div id="performance-section" class="hidden space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col items-center text-center">
                                <div class="w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mb-4">
                                    <i class="ri-star-fill text-3xl text-amber-500"></i>
                                </div>
                                <h4 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Average</h4>
                                <p class="text-3xl font-black text-gray-900"><?php echo $avg_rating; ?></p>
                                <p class="text-xs text-gray-500 mt-2">Based on <?php echo $total_reviews; ?> reviews</p>
                            </div>
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col items-center text-center">
                                <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mb-4">
                                    <i class="ri-checkbox-circle-fill text-3xl text-emerald-600"></i>
                                </div>
                                <h4 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Fulfillment</h4>
                                <p class="text-3xl font-black text-gray-900"><?php echo $fulfillment_rate; ?>%</p>
                                <p class="text-xs text-gray-500 mt-2"><?php echo $completed_trips; ?> Successful Trips</p>
                            </div>
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col items-center text-center">
                                <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4">
                                    <i class="ri-medal-fill text-3xl text-blue-600"></i>
                                </div>
                                <h4 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Status</h4>
                                <p class="text-3xl font-black text-gray-900">Elite</p>
                                <p class="text-xs text-gray-500 mt-2">Top 5% of Partners</p>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-wider">Achievements</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="p-4 rounded-xl border border- emerald-100 bg-emerald-50/50 flex flex-col items-center text-center">
                                    <i class="ri-flashlight-fill text-2xl text-emerald-600 mb-2"></i>
                                    <span class="text-[10px] font-bold text-emerald-800 uppercase">Fast Starter</span>
                                </div>
                                <div class="p-4 rounded-xl border border-blue-100 bg-blue-50/50 flex flex-col items-center text-center">
                                    <i class="ri-shield-check-fill text-2xl text-blue-600 mb-2"></i>
                                    <span class="text-[10px] font-bold text-blue-800 uppercase">Pro Verified</span>
                                </div>
                                <div class="p-4 rounded-xl border border-gray-100 opacity-40 flex flex-col items-center text-center grayscale">
                                    <i class="ri-honour-fill text-2xl text-gray-400 mb-2"></i>
                                    <span class="text-[10px] font-bold text-gray-500 uppercase">100 Trips</span>
                                </div>
                                <div class="p-4 rounded-xl border border-gray-100 opacity-40 flex flex-col items-center text-center grayscale">
                                    <i class="ri-heart-fill text-2xl text-gray-400 mb-2"></i>
                                    <span class="text-[10px] font-bold text-gray-500 uppercase">5 Star Club</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-teal-800 text-white mt-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div>
                            <img alt="TripSync Logo" class="h-12 w-auto mb-4" src="../assets/images/logo.png">
                            <p class="text-teal-50 text-sm leading-relaxed">Your trusted partner for seamless travel planning across Sri Lanka.</p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4 text-white">Quick Links</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-100 text-sm hover:text-white transition-colors" href="dashboard.php">Dashboard</a></li>
                                <li><a class="text-teal-100 text-sm hover:text-white transition-colors" href="active-trip.php">Active Trip</a></li>
                                <li><a class="text-teal-100 text-sm hover:text-white transition-colors" href="earnings.php">Earnings</a></li>
                                <li><a class="text-teal-100 text-sm hover:text-white transition-colors" href="profile.php">Profile</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4 text-white">Support</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-100 text-sm hover:text-white transition-colors" href="#">Help Center</a></li>
                                <li><a class="text-teal-100 text-sm hover:text-white transition-colors" href="#">Payout Terms</a></li>
                                <li><a class="text-teal-100 text-sm hover:text-white transition-colors" href="#">Safety Guidelines</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4 text-white">Partner Bank</h3>
                            <div class="bg-white/10 rounded-xl p-4 border border-white/5 shadow-inner">
                                <p class="text-[10px] uppercase font-black text-teal-300 mb-1 tracking-widest">Linked Account</p>
                                <p class="text-sm font-black text-white">Commercial Bank / 8011 **** ****</p>
                                <p class="text-[9px] text-teal-200 mt-2 font-medium">Auto-payouts every Monday</p>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-white/10 mt-12 pt-8 text-center">
                        <p class="text-teal-300 text-[10px] font-bold uppercase tracking-widest">© 2026 TripSync SRI LANKA. Empowering Local Partners.</p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Tab & Payout Functionality -->
    <script>
        // Chart.js Initialization
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Weekly Revenue Chart
            const weeklyCtx = document.getElementById('weeklyRevenueChart').getContext('2d');
            new Chart(weeklyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_keys($weekly_data)); ?>,
                    datasets: [{
                        label: 'Net Earnings',
                        data: <?php echo json_encode(array_column($weekly_data, 'net')); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 8,
                        hoverBackgroundColor: '#10b981'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: { display: false },
                            ticks: { 
                                font: { weight: 'bold', size: 10 },
                                callback: val => 'LKR ' + (val >= 1000 ? (val/1000)+'k' : val)
                            }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { font: { weight: 'bold', size: 10 } }
                        }
                    }
                }
            });

            // 2. Monthly Trend Chart
            const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
            const gradient = monthlyCtx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(79, 70, 229, 0.2)');
            gradient.addColorStop(1, 'rgba(79, 70, 229, 0)');

            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_keys($trend_data)); ?>,
                    datasets: [{
                        label: 'Monthly Trend',
                        data: <?php echo json_encode(array_values($trend_data)); ?>,
                        fill: true,
                        backgroundColor: gradient,
                        borderColor: '#4f46e5',
                        borderWidth: 3,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#4f46e5',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: { color: '#f3f4f6' },
                            ticks: { 
                                font: { weight: 'bold', size: 10 },
                                callback: val => 'LKR ' + (val >= 1000 ? (val/1000)+'k' : val)
                            }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { font: { weight: 'bold', size: 10 } }
                        }
                    }
                }
            });
        });

        // Tab Switching Logic
        const earningsBtn = document.getElementById('earnings-tab-btn');
        const performanceBtn = document.getElementById('performance-tab-btn');
        const earningsSection = document.getElementById('earnings-section');
        const performanceSection = document.getElementById('performance-section');

        earningsBtn.addEventListener('click', () => {
            earningsBtn.classList.add('bg-emerald-600', 'text-white', 'shadow-md');
            earningsBtn.classList.remove('text-gray-600', 'hover:text-gray-900');
            performanceBtn.classList.remove('bg-emerald-600', 'text-white', 'shadow-md');
            performanceBtn.classList.add('text-gray-600', 'hover:text-gray-900');
            earningsSection.classList.remove('hidden');
            performanceSection.classList.add('hidden');
        });

        performanceBtn.addEventListener('click', () => {
            performanceBtn.classList.add('bg-emerald-600', 'text-white', 'shadow-md');
            performanceBtn.classList.remove('text-gray-600', 'hover:text-gray-900');
            earningsBtn.classList.remove('bg-emerald-600', 'text-white', 'shadow-md');
            earningsBtn.classList.add('text-gray-600', 'hover:text-gray-900');
            performanceSection.classList.remove('hidden');
            earningsSection.classList.add('hidden');
        });

        // Bank Details Form Submission
        document.getElementById('bank-details-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const btn = e.target.querySelector('button[type="submit"]');
            const originalText = btn.innerText;

            btn.disabled = true;
            btn.innerText = "Saving...";

            try {
                const response = await fetch('../api/update_bank_details.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert("Bank details updated successfully!");
                    location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            } catch (error) {
                console.error("Update error:", error);
                alert("Failed to update bank details.");
            } finally {
                btn.disabled = false;
                btn.innerText = originalText;
            }
        });

        async function requestPayout(balance, accountNumber) {
            if (!accountNumber) {
                alert("Please add your bank account details first before requesting a payout.");
                document.getElementById('bank-details-form').scrollIntoView({ behavior: 'smooth' });
                return;
            }

            if (balance <= 0) {
                alert("You have no balance available for payout.");
                return;
            }

            if (!confirm(`Are you sure you want to request a payout for LKR ${new Intl.NumberFormat().format(balance)} to your linked account?`)) {
                return;
            }

            const btn = document.getElementById('request-payout-btn');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = "Processing...";

            try {
                const response = await fetch('../api/request_payout.php', {
                    method: 'POST'
                });
                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert("Failed to submit request: " + data.message);
                }
            } catch (error) {
                console.error("Payout error:", error);
                alert("An unexpected error occurred. Please try again later.");
            } finally {
                btn.disabled = false;
                btn.innerText = originalText;
            }
        }
    </script>
</body>

</html>
