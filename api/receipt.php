<?php
require_once('../includes/config.php');
require_once('../includes/auth_check.php');
require_once('../includes/functions.php');

// Simple authentication check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Access denied. Please login.");
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    die("Invalid request.");
}

// Fetch booking and trip details
$sql = "SELECT b.*, u.name as customer_name, u.email as customer_email, 
               tp.name as plan_name, tp.start_date, tp.end_date 
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        LEFT JOIN travel_plans tp ON b.plan_id = tp.id
        WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Record not found or access denied.");
}

// Fetch destinations
$dest_stmt = $conn->prepare("SELECT location_name FROM destinations WHERE plan_id = ? ORDER BY day_number ASC");
$dest_stmt->bind_param("i", $booking['plan_id']);
$dest_stmt->execute();
$dest_res = $dest_stmt->get_result();
$destinations = [];
while ($row = $dest_res->fetch_assoc()) {
    $destinations[] = $row['location_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?php echo $booking['reference_no']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-gray-100 py-10 px-4">
    <div class="max-w-3xl mx-auto bg-white shadow-xl rounded-2xl overflow-hidden overflow-hidden border border-gray-100">
        <!-- Receipt Header -->
        <div class="bg-teal-600 px-8 py-10 text-white">
            <div class="flex justify-between items-start">
                <div>
                    <img src="../assets/images/logo.png" alt="TripSync" class="h-12 mb-4 brightness-0 invert">
                    <h1 class="text-3xl font-bold">Trip Receipt</h1>
                </div>
                <div class="text-right">
                    <p class="text-teal-100 text-sm">Reference No:</p>
                    <p class="font-mono text-xl font-bold"><?php echo $booking['reference_no']; ?></p>
                    <p class="mt-2 text-sm text-teal-100">Date Issued:</p>
                    <p class="font-medium"><?php echo date('M d, Y'); ?></p>
                </div>
            </div>
        </div>

        <div class="p-8">
            <!-- Customer & Trip Info -->
            <div class="grid grid-cols-2 gap-8 mb-10 pb-10 border-b border-gray-100">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Customer Information</h3>
                    <p class="text-lg font-bold text-gray-900"><?php echo clean($booking['customer_name']); ?></p>
                    <p class="text-gray-500"><?php echo clean($booking['customer_email']); ?></p>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Trip Details</h3>
                    <p class="text-lg font-bold text-gray-900"><?php echo clean($booking['plan_name']); ?></p>
                    <p class="text-gray-500">
                        <?php echo date('M d, Y', strtotime($booking['start_date'])); ?> - 
                        <?php echo date('M d, Y', strtotime($booking['end_date'])); ?>
                    </p>
                </div>
            </div>

            <!-- Booking Breakdown -->
            <div class="mb-10">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-xs font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100 pb-4">
                            <th class="py-4">Service Description</th>
                            <th class="py-4 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr>
                            <td class="py-6">
                                <p class="font-bold text-gray-900">Full Trip Booking (<?php echo $booking['type']; ?>)</p>
                                <p class="text-sm text-gray-500 lowercase first-letter:uppercase">Route: <?php echo implode(' → ', array_map('clean', $destinations)); ?></p>
                            </td>
                            <td class="py-6 text-right font-bold text-gray-900">
                                <?php echo formatCurrency($booking['total_price']); ?>
                            </td>
                        </tr>
                        <!-- Tax row removed as not present in DB schema -->
                    </tbody>
                </table>
            </div>

            <!-- Total -->
            <div class="flex justify-end pt-10 border-t border-gray-100">
                <div class="w-64">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="font-bold text-gray-900"><?php echo formatCurrency($booking['total_price']); ?></span>
                    </div>
                    <?php 
                    // Calculate a simulated service fee (5%) which is already included in total_price
                    $service_fee = $booking['total_price'] * 0.05;
                    ?>
                    <div class="flex justify-between items-center mb-6 text-sm text-gray-400 italic">
                        <span>(Includes <?php echo formatCurrency($service_fee); ?> Service Fee)</span>
                    </div>
                    <div class="flex justify-between items-center bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <span class="font-bold text-gray-900">Total Paid</span>
                        <span class="text-2xl font-black text-teal-600"><?php echo formatCurrency($booking['total_price']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Status Badge -->
            <div class="mt-10 flex justify-center">
                <div class="inline-flex items-center gap-2 px-6 py-2 bg-emerald-50 text-emerald-600 font-bold rounded-full border border-emerald-100 uppercase tracking-widest text-xs">
                    <i class="ri-checkbox-circle-fill"></i>
                    Booking Completed & Paid
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="bg-gray-50 px-8 py-6 text-center border-t border-gray-100">
            <p class="text-sm text-gray-500 mb-2">Thank you for traveling with TripSync. If you have any questions, please contact our support team.</p>
            <div class="flex justify-center gap-6 mt-4 no-print">
                <button onclick="window.print()" class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-800 transition-colors">
                    <i class="ri-printer-line"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
    
    <div class="mt-8 text-center no-print">
        <a href="../customer/trip_history.php" class="text-sm font-medium text-teal-600 hover:text-teal-700 flex items-center justify-center gap-1">
            <i class="ri-arrow-left-line"></i> Back to History
        </a>
    </div>
</body>
</html>
