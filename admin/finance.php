<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('admin');

// 1. Fetch Stats
$today_revenue_res = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed' AND type = 'credit'");
$today_revenue = $today_revenue_res->fetch_assoc()['total'] ?: 0;

$monthly_payout_res = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND status = 'completed' AND type = 'debit'");
$monthly_payout = $monthly_payout_res->fetch_assoc()['total'] ?: 0;

$pending_payout_res = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE type = 'debit' AND status = 'pending'");
$pending_payouts = $pending_payout_res->fetch_assoc()['count'];

$disputes_res = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE subject LIKE '%Refund%' AND status != 'resolved'");
$disputes = $disputes_res->fetch_assoc()['count'];

// 2. Handle Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    $reason = isset($_GET['reason']) ? $conn->real_escape_string($_GET['reason']) : 'No reason provided';

    if ($action == 'approve_payout') {
        $stmt = $conn->prepare("UPDATE transactions SET status = 'completed' WHERE id = ? AND type = 'debit'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = "Payout approved successfully.";
    }
    elseif ($action == 'refund') {
        $conn->query("START TRANSACTION");
        try {
            // Get transaction details
            $res = $conn->query("SELECT user_id, amount, booking_id FROM transactions WHERE id = $id");
            $trans = $res->fetch_assoc();
            $cust_id = $trans['user_id'];
            $amount = $trans['amount'];
            $booking_id = $trans['booking_id'];

            // 1. Update Transaction
            $conn->query("UPDATE transactions SET status = 'refunded' WHERE id = $id");
            
            // 2. Update Booking
            $conn->query("UPDATE bookings SET status = 'refunded' WHERE id = $booking_id");

            // 3. Credit Customer Wallet
            $conn->query("INSERT INTO wallets (user_id, balance, updated_at) VALUES ($cust_id, $amount, CURRENT_TIMESTAMP) 
                         ON DUPLICATE KEY UPDATE balance = balance + $amount, updated_at = CURRENT_TIMESTAMP");

            // 4. Create Credit Transaction Log for the refund
            $ref = 'REF-' . time();
            $conn->query("INSERT INTO transactions (user_id, booking_id, amount, type, method, status, transaction_ref) 
                         VALUES ($cust_id, $booking_id, $amount, 'credit', 'cash', 'completed', '$ref')");

            // 5. Notify Customer
            $title = "Refund Processed";
            $message = "Your refund of LKR " . number_format($amount) . " has been credited to your TripSync Wallet.";
            $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($cust_id, '$title', '$message', 'success')");

            $conn->query("COMMIT");
            $msg = "Refund processed and credited to user wallet successfully.";
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            $msg = "Error processing refund: " . $e->getMessage();
        }
    }
    elseif ($action == 'verify_payment') {
        $stmt = $conn->prepare("UPDATE transactions SET status = 'completed' WHERE id = ? AND method = 'bank_transfer'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Bank transfer verified successfully.";
        }
    }
}

// 3. Fetch Data for Tables
$payout_requests = $conn->query("SELECT t.*, u.name as user_name, u.profile_pic, v.type as vehicle_type 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN vehicles v ON u.id = v.owner_id
    WHERE t.type = 'debit' 
    ORDER BY t.created_at DESC");

$pending_transfers = $conn->query("SELECT t.*, u.name as user_name, b.reference_no 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN bookings b ON t.booking_id = b.id
    WHERE t.method = 'bank_transfer' AND t.status = 'pending' AND t.type = 'credit'
    ORDER BY t.created_at DESC");

$refund_disputes = $conn->query("SELECT t.*, u.name as user_name, b.reference_no, b.status as booking_status
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    JOIN bookings b ON t.booking_id = b.id
    WHERE t.status = 'completed' AND b.status = 'cancelled'
    ORDER BY t.created_at DESC");

// 4. Monthly Revenue for Chart
$monthly_revenue = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $month_num = date('m', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $res = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE MONTH(created_at) = $month_num AND YEAR(created_at) = $year AND status = 'completed' AND type = 'credit'");
    $total = $res->fetch_assoc()['total'] ?: 0;
    $monthly_revenue[] = ['month' => $month, 'total' => $total];
}
$max_revenue = 0;
foreach ($monthly_revenue as $r)
    if ($r['total'] > $max_revenue)
        $max_revenue = $r['total'];

// 5. Revenue Breakdown by Category
$category_rev = $conn->query("SELECT b.type, SUM(t.amount) as total 
    FROM transactions t 
    JOIN bookings b ON t.booking_id = b.id 
    WHERE t.status = 'completed' AND t.type = 'credit' 
    GROUP BY b.type");
$categories = [];
$total_cat_rev = 0;
while ($c = $category_rev->fetch_assoc()) {
    $categories[$c['type']] = $c['total'];
    $total_cat_rev += $c['total'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripSync - Financial Manager</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="assets/files/index-BjODfbg0.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }

        /* Color Utility Fixes */
        .bg-teal-50 { background-color: #f0fdfa !important; }
        .bg-teal-100 { background-color: #ccfbf1 !important; }
        .bg-teal-500 { background-color: #14b8a6 !important; }
        .bg-emerald-50 { background-color: #ecfdf5 !important; }
        .bg-amber-50 { background-color: #fffbeb !important; }
        .bg-amber-100 { background-color: #fef3c7 !important; }
        .bg-rose-50 { background-color: #fff1f2 !important; }
        .bg-rose-100 { background-color: #ffe4e6 !important; }
        .bg-rose-500\/20 { background-color: rgba(244, 63, 94, 0.2) !important; }
        .bg-emerald-500\/20 { background-color: rgba(16, 185, 129, 0.2) !important; }
        .bg-white\/10 { background-color: rgba(255, 255, 255, 0.1) !important; }
        
        .text-teal-600 { color: #0d9488 !important; }
        .text-teal-700 { color: #0f766e !important; }
        .text-emerald-600 { color: #059669 !important; }
        .text-amber-600 { color: #d97706 !important; }
        .text-amber-700 { color: #b45309 !important; }
        .text-rose-600 { color: #e11d48 !important; }
        .text-rose-700 { color: #be123c !important; }
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
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Financial & Dispute Manager</h1>
                            <p class="text-sm text-gray-500 mt-1">Manage payouts, refunds, and commission rates</p>
                        </div>
                    </div>

                    <?php if (isset($msg)): ?>
                        <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-100 flex items-center gap-2">
                            <i class="ri-checkbox-circle-line"></i> <?php echo $msg; ?>
                        </div>
                    <?php
endif; ?>

                    <!-- Stats -->
                    <div class="mb-6">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                                <div class="w-11 h-11 flex items-center justify-center bg-teal-50 rounded-xl mb-3"><i
                                        class="ri-bank-line text-xl text-teal-600"></i></div>
                                <p class="text-2xl font-bold text-gray-900">LKR <?php echo number_format($monthly_payout / 1000, 1); ?>K</p>
                                <p class="text-xs text-gray-500 mt-1">Total Payouts (Month)</p>
                            </div>
                            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                                <div class="w-11 h-11 flex items-center justify-center bg-amber-50 rounded-xl mb-3"><i
                                        class="ri-time-line text-xl text-amber-600"></i></div>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $pending_payouts; ?></p>
                                <p class="text-xs text-gray-500 mt-1">Pending Payouts</p>
                            </div>
                            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                                <div class="w-11 h-11 flex items-center justify-center bg-rose-50 rounded-xl mb-3"><i
                                        class="ri-error-warning-line text-xl text-rose-600"></i></div>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $disputes; ?></p>
                                <p class="text-xs text-gray-500 mt-1">Open Disputes</p>
                            </div>
                            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                                <div class="w-11 h-11 flex items-center justify-center bg-emerald-50 rounded-xl mb-3"><i
                                        class="ri-money-dollar-circle-line text-xl text-emerald-600"></i></div>
                                <p class="text-2xl font-bold text-gray-900">LKR <?php echo number_format($today_revenue / 1000, 1); ?>K</p>
                                <p class="text-xs text-gray-500 mt-1">Today's Revenue</p>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Revenue Overview -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                                <div class="flex items-center justify-between mb-5">
                                    <h3 class="text-lg font-bold text-gray-900">Revenue Overview</h3>
                                    <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full">Last 6 Months</span>
                                </div>
                                <div class="h-64 flex items-end justify-between gap-4 px-2">
                                    <?php foreach ($monthly_revenue as $r): ?>
                                        <?php $height = $max_revenue > 0 ? ($r['total'] / $max_revenue) * 100 : 0; ?>
                                        <div class="flex-1 flex flex-col items-center gap-3">
                                            <div class="w-full bg-teal-500 rounded-t-lg transition-all hover:bg-teal-600"
                                                style="height: <?php echo max(5, $height); ?>%"></div>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase"><?php echo $r['month']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Category Breakdown -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl border border-gray-100 p-6 h-full flex flex-col">
                                <h3 class="text-lg font-bold text-gray-900 mb-5">Service Breakdown</h3>
                                <div class="space-y-6 flex-1">
                                    <?php 
                                    $types = ['hotel' => 'bg-amber-500', 'vehicle' => 'bg-emerald-500', 'tour' => 'bg-rose-500'];
                                    foreach($types as $type => $color): 
                                        $amount = $categories[$type] ?? 0;
                                        $pct = $total_cat_rev > 0 ? ($amount / $total_cat_rev) * 100 : 0;
                                    ?>
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-2 h-2 rounded-full <?php echo $color; ?>"></div>
                                                    <span class="text-xs font-bold text-gray-700 capitalize"><?php echo $type; ?>s</span>
                                                </div>
                                                <span class="text-xs font-bold text-gray-900"><?php echo round($pct); ?>%</span>
                                            </div>
                                            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full <?php echo $color; ?> transition-all duration-1000" style="width: <?php echo $pct; ?>%"></div>
                                            </div>
                                            <p class="text-[10px] text-gray-400 mt-1 font-medium">Total: LKR <?php echo number_format($amount); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="pt-5 mt-5 border-t border-gray-50">
                                    <p class="text-xs text-gray-500 text-center">Total Platform Revenue: <span class="font-bold text-gray-900">LKR <?php echo number_format($total_cat_rev); ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 p-1 mb-6 inline-flex">
                        <button id="payouts-btn" onclick="switchTab('payouts')"
                            class="px-5 py-2.5 text-sm font-medium rounded-xl transition-colors cursor-pointer whitespace-nowrap bg-slate-900 text-white">Payouts</button>
                        <button id="refunds-btn" onclick="switchTab('refunds')"
                            class="px-5 py-2.5 text-sm font-medium rounded-xl transition-colors cursor-pointer whitespace-nowrap text-gray-600 hover:text-gray-900">Refunds
                            & Disputes</button>
                        <button id="verification-btn" onclick="switchTab('verification')"
                            class="px-5 py-2.5 text-sm font-medium rounded-xl transition-colors cursor-pointer whitespace-nowrap text-gray-600 hover:text-gray-900">Payment Verification</button>
                    </div>

                    <!-- Payouts Tab -->
                    <div id="payouts-content" class="tab-content transition-all">
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 relative">
                            <div class="flex items-center justify-between mb-5">
                                <h3 class="text-lg font-bold text-gray-900">Payout Requests</h3>
                                <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full whitespace-nowrap">
                                    <?php echo $pending_payouts; ?> Pending
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-100">
                                            <th class="text-left py-3 px-3 text-xs font-medium text-gray-500 uppercase">Partner</th>
                                            <th class="text-left py-3 px-3 text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                            <th class="text-right py-3 px-3 text-xs font-medium text-gray-500 uppercase">Amount</th>
                                            <th class="text-center py-3 px-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="text-right py-3 px-3 text-xs font-medium text-gray-500 uppercase">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($payout_requests->num_rows == 0): ?>
                                            <tr><td colspan="5" class="py-12 text-center text-gray-500">No payout requests found</td></tr>
                                        <?php
else: ?>
                                            <?php while ($payout = $payout_requests->fetch_assoc()): ?>
                                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                                    <td class="py-3 px-3">
                                                        <p class="font-medium text-gray-900"><?php echo $payout['user_name']; ?></p>
                                                        <p class="text-xs text-gray-500">PTR-<?php echo $payout['user_id']; ?></p>
                                                    </td>
                                                    <td class="py-3 px-3 text-gray-600"><?php echo ucfirst($payout['vehicle_type']); ?></td>
                                                    <td class="py-3 px-3 text-right font-semibold text-gray-900">LKR <?php echo number_format($payout['amount'], 2); ?></td>
                                                    <td class="py-3 px-3 text-center">
                                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize <?php
        echo $payout['status'] == 'completed' ? 'bg-teal-100 text-teal-700' : 'bg-amber-100 text-amber-700';
?>"><?php echo $payout['status']; ?></span>
                                                    </td>
                                                    <td class="py-3 px-3 text-right">
                                                        <?php if ($payout['status'] == 'pending'): ?>
                                                            <a href="?action=approve_payout&id=<?php echo $payout['id']; ?>"
                                                                class="px-3 py-1.5 bg-teal-600 text-white text-xs font-medium rounded-lg hover:bg-teal-700 cursor-pointer whitespace-nowrap">Process</a>
                                                        <?php
        else: ?>
                                                            <span class="text-xs text-gray-400 font-medium tracking-widest"><i class="ri-check-line"></i> Done</span>
                                                        <?php
        endif; ?>
                                                    </td>
                                                </tr>
                                            <?php
    endwhile; ?>
                                        <?php
endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Refunds Tab -->
                    <div id="refunds-content" class="tab-content hidden transition-all">
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 relative">
                            <div class="flex items-center justify-between mb-5">
                                <h3 class="text-lg font-bold text-gray-900">Refund Requests</h3>
                                <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full whitespace-nowrap">
                                    <?php echo $refund_disputes->num_rows; ?> Active
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-100">
                                            <th class="text-left py-3 px-3 text-xs font-medium text-gray-500 uppercase">Booking Ref</th>
                                            <th class="text-left py-3 px-3 text-xs font-medium text-gray-500 uppercase">Customer</th>
                                            <th class="text-right py-3 px-3 text-xs font-medium text-gray-500 uppercase">Amount</th>
                                            <th class="text-center py-3 px-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="text-right py-3 px-3 text-xs font-medium text-gray-500 uppercase">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($refund_disputes->num_rows == 0): ?>
                                            <tr><td colspan="5" class="py-12 text-center text-gray-500">No active refund disputes</td></tr>
                                        <?php
else: ?>
                                            <?php while ($refund = $refund_disputes->fetch_assoc()): ?>
                                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                                    <td class="py-3 px-3 font-medium text-teal-600">#<?php echo $refund['reference_no']; ?></td>
                                                    <td class="py-3 px-3"><?php echo $refund['user_name']; ?></td>
                                                    <td class="py-3 px-3 text-right font-semibold text-gray-900">LKR <?php echo number_format($refund['amount'], 2); ?></td>
                                                    <td class="py-3 px-3 text-center">
                                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-rose-100 text-rose-700 italic">Cancelled</span>
                                                    </td>
                                                    <td class="py-3 px-3 text-right">
                                                        <a href="?action=refund&id=<?php echo $refund['id']; ?>"
                                                            class="px-3 py-1.5 bg-rose-600 text-white text-xs font-medium rounded-lg hover:bg-rose-700 cursor-pointer whitespace-nowrap">Refund</a>
                                                    </td>
                                                </tr>
                                            <?php
    endwhile; ?>
                                        <?php
endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Verification Tab -->
                    <div id="verification-content" class="tab-content hidden transition-all">
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 relative">
                            <div class="flex items-center justify-between mb-5">
                                <h3 class="text-lg font-bold text-gray-900">Bank Transfer Verifications</h3>
                                <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full whitespace-nowrap">
                                    <?php echo $pending_transfers->num_rows; ?> Pending
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-100">
                                            <th class="text-left py-3 px-3 text-xs font-medium text-gray-500 uppercase">Customer</th>
                                            <th class="text-left py-3 px-3 text-xs font-medium text-gray-500 uppercase">Booking Ref</th>
                                            <th class="text-right py-3 px-3 text-xs font-medium text-gray-500 uppercase">Amount</th>
                                            <th class="text-right py-3 px-3 text-xs font-medium text-gray-500 uppercase">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($pending_transfers->num_rows == 0): ?>
                                            <tr><td colspan="4" class="py-12 text-center text-gray-500">No bank transfers pending verification</td></tr>
                                        <?php
else: ?>
                                            <?php while ($transfer = $pending_transfers->fetch_assoc()): ?>
                                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                                    <td class="py-3 px-3 font-medium text-gray-900"><?php echo $transfer['user_name']; ?></td>
                                                    <td class="py-3 px-3">#<?php echo $transfer['reference_no']; ?></td>
                                                    <td class="py-3 px-3 text-right font-semibold text-gray-900">LKR <?php echo number_format($transfer['amount'], 2); ?></td>
                                                    <td class="py-3 px-3 text-right">
                                                        <a href="?action=verify_payment&id=<?php echo $transfer['id']; ?>"
                                                            class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700 cursor-pointer whitespace-nowrap">Verify</a>
                                                    </td>
                                                </tr>
                                            <?php
    endwhile; ?>
                                        <?php
endif; ?>
                                    </tbody>
                                </table>
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
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function switchTab(tab) {
            ['payouts', 'refunds', 'verification'].forEach(t => {
                const btn = document.getElementById(`${t}-btn`);
                const content = document.getElementById(`${t}-content`);

                if (t === tab) {
                    btn.classList.add('bg-slate-900', 'text-white');
                    btn.classList.remove('text-gray-600', 'hover:text-gray-900');
                    content.classList.remove('hidden');
                } else {
                    btn.classList.remove('bg-slate-900', 'text-white');
                    btn.classList.add('text-gray-600', 'hover:text-gray-900');
                    content.classList.add('hidden');
                }
            });
        }
    </script>
    <script src="assets/files/index-Dammfq5V.js.download"></script>
</body>

</html>
