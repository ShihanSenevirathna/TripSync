<?php
// TripSync Booking Confirmation (Integrated UI)
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
checkAuth('customer');

// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

$userId = $_SESSION['user_id'];
$bookingId = (isset($_GET['id']) && is_numeric($_GET['id']) && !isset($_GET['type'])) ? intval($_GET['id']) : 0;
$success = isset($_GET['status']) && $_GET['status'] == 'success' ? 'Payment completed successfully!' : null;
$error = isset($_GET['status']) && $_GET['status'] == 'cancel' ? 'Payment was cancelled.' : (isset($_GET['status']) && $_GET['status'] == 'error' ? 'Payment failed.' : null);

// Handle new booking creation
if (isset($_GET['type']) && (isset($_GET['id']) || isset($_GET['service_id'])) && isset($_GET['total']) && $bookingId === 0) {
    $type = $_GET['type'];
    $item_id = $_GET['id'] ?? $_GET['service_id'];
    $total_price = (float)$_GET['total'];
    $start_date = $_GET['checkin'] ?? date('Y-m-d');
    $end_date = $_GET['checkout'] ?? date('Y-m-d', strtotime('+1 day'));
    $reference_no = strtoupper(substr($type, 0, 1)) . time();
    $plan_id = isset($_GET['plan_id']) && is_numeric($_GET['plan_id']) ? (int)$_GET['plan_id'] : null;
    $pickup = $_GET['pickup'] ?? null;
    $dropoff = $_GET['dropoff'] ?? null;

    // Check for existing active bookings of the same type that overlap with these dates
    $checkStmt = $conn->prepare("SELECT id FROM bookings WHERE user_id = ? AND type = ? AND status IN ('pending', 'confirmed', 'arrived', 'in_progress') AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?) OR (? <= start_date AND ? >= end_date))");
    $checkStmt->bind_param("isssssss", $userId, $type, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        header("Location: marketplace.php?tab=" . ($type === 'vehicle' ? 'vehicles' : 'hotels') . "&error=already_booked");
        exit;
    }

    // Fetch owner if it's a vehicle
    $assigned_partner_id = null;
    if ($type === 'vehicle') {
        $vRes = $conn->query("SELECT owner_id FROM vehicles WHERE id = $item_id");
        if ($vRes && $vRes->num_rows > 0) {
            $assigned_partner_id = $vRes->fetch_assoc()['owner_id'];
        }
    }

    $status = 'pending';

    // Log the variables for debugging
    file_put_contents('../debug_vars.log', "Booking Attempt: type=$type, item_id=$item_id, total=$total_price, start=$start_date, end=$end_date, ref=$reference_no\n", FILE_APPEND);

    $stmt = $conn->prepare("INSERT INTO bookings (user_id, plan_id, item_id, type, total_price, start_date, end_date, reference_no, status, assigned_partner_id, pickup_location, dropoff_location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissdssssiss", $userId, $plan_id, $item_id, $type, $total_price, $start_date, $end_date, $reference_no, $status, $assigned_partner_id, $pickup, $dropoff);
    
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        header("Location: booking_confirmation.php?id=" . $newId);
        exit;
    } else {
        die("Booking creation failed: " . $stmt->error);
    }
}

if ($bookingId === 0) {
    header("Location: dashboard.php");
    exit;
}

// Fetch booking
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $bookingId, $userId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: dashboard.php");
    exit;
}

// Fallback for Local Sandbox Testing: process PayHere return since Ngrok webhook might be offline
if (isset($_GET['status']) && $_GET['status'] == 'success' && PAYHERE_MODE === 'sandbox' && $booking['status'] === 'pending') {
    $conn->begin_transaction();
    try {
        $upB = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
        $upB->bind_param("i", $bookingId);
        $upB->execute();

        $ref = "BOOKING_" . $bookingId . "_" . $userId . "_mock"; // Fallback ref
        $amount = $booking['total_price'];
        $insT = $conn->prepare("INSERT INTO transactions (user_id, booking_id, amount, type, method, status, transaction_ref) VALUES (?, ?, ?, 'debit', 'payhere', 'completed', ?)");
        $insT->bind_param("iids", $userId, $bookingId, $amount, $ref);
        $insT->execute();

        $conn->commit();
        $booking['status'] = 'confirmed';
    } catch (Exception $e) {
        $conn->rollback();
    }
}

$isConfirmed = ($booking['status'] === 'confirmed' || $booking['status'] === 'completed');

// Handle Wallet Payment
if (isset($_POST['pay_wallet']) && !$isConfirmed) {
    $conn->begin_transaction();
    try {
        // Lock the wallet row for update
        $walletStmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
        $walletStmt->bind_param("i", $userId);
        $walletStmt->execute();
        $walletResult = $walletStmt->get_result();

        if ($walletResult->num_rows > 0) {
            $wallet = $walletResult->fetch_assoc();

            if ($wallet['balance'] >= $booking['total_price']) {
                // Deduct from wallet
                $deductStmt = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
                $deductStmt->bind_param("di", $booking['total_price'], $userId);
                if (!$deductStmt->execute()) {
                    throw new Exception("Failed to deduct wallet balance: " . $deductStmt->error);
                }

                // Confirm the booking
                $confirmStmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND user_id = ?");
                $confirmStmt->bind_param("ii", $bookingId, $userId);
                if (!$confirmStmt->execute()) {
                    throw new Exception("Failed to confirm booking: " . $confirmStmt->error);
                }

                // Log transaction
                $ref = "WALLET_" . $bookingId . "_" . time();
                $transStmt = $conn->prepare("INSERT INTO transactions (user_id, booking_id, amount, type, method, status, transaction_ref) VALUES (?, ?, ?, 'debit', 'wallet', 'completed', ?)");
                $transStmt->bind_param("iids", $userId, $bookingId, $booking['total_price'], $ref);
                if (!$transStmt->execute()) {
                    throw new Exception("Failed to log transaction: " . $transStmt->error);
                }

                $conn->commit();
                $isConfirmed = true;
                $success = "Booking successfully paid with wallet.";
                $booking['status'] = 'confirmed';
            } else {
                $conn->rollback();
                $error = "Insufficient wallet balance. Current balance: LKR " . number_format($wallet['balance'], 2);
            }
        } else {
            $conn->rollback();
            $error = "Wallet not found. Please top up your wallet first.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Payment failed: " . $e->getMessage();
    }
}

$service = null;
if ($booking['type'] === 'hotel') {
    if (strpos($booking['item_id'], 'google_') === 0 || strlen($booking['item_id']) > 15) {
        require_once '../api/google_places_api.php';
        $googleApi = new GooglePlacesAPI();
        $place_id = str_replace('google_', '', $booking['item_id']);
        $details = $googleApi->getDetailsByPlaceId($place_id);
        if ($details) {
            $service = [
                'name' => $details['name'],
                'image' => !empty($details['photos']) ? $details['photos'][0] : '../assets/images/placeholder.jpg',
                'location' => $details['address'],
                'stars' => $details['rating']
            ];
        }
    } else {
        $hotelStmt = $conn->prepare("SELECT * FROM hotels WHERE id = ?");
        $hotelStmt->bind_param("i", $booking['item_id']);
        $hotelStmt->execute();
        $hotel = $hotelStmt->get_result()->fetch_assoc();
        if ($hotel) {
            $service = [
                'name' => $hotel['name'],
                'image' => !empty($hotel['image_path']) ? '../assets/images/' . $hotel['image_path'] : '../assets/images/placeholder.jpg',
                'location' => $hotel['location'],
                'stars' => $hotel['stars']
            ];
        }
    }
} else if ($booking['type'] === 'vehicle') {
    $vStmt = $conn->prepare("SELECT v.*, u.name as owner_name, u.profile_pic as owner_pic FROM vehicles v LEFT JOIN users u ON v.owner_id = u.id WHERE v.id = ?");
    $vStmt->bind_param("s", $booking['item_id']);
    $vStmt->execute();
    $veh = $vStmt->get_result()->fetch_assoc();
    if ($veh) {
        $service = [
            'name' => $veh['model'],
            'image' => !empty($veh['image_path']) ? '../assets/images/' . $veh['image_path'] : '../assets/images/placeholder.jpg',
            'capacity' => $veh['capacity'],
            'owner_name' => $veh['owner_name'],
            'owner_pic' => $veh['owner_pic'],
            'rating' => $veh['rating']
        ];
    }
}

$userStmt = $conn->prepare("SELECT name, email, phone, profile_pic FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

// PayHere Credentials
$merchant_id = PAYHERE_MERCHANT_ID;
$order_id = "BOOKING_" . $bookingId . "_" . $userId . "_" . time();
$amount_formatted = number_format($booking['total_price'], 2, '.', '');
$merchant_secret = PAYHERE_SECRET;
$currency = "LKR";
$hash = strtoupper(md5($merchant_id . $order_id . $amount_formatted . $currency . strtoupper(md5($merchant_secret))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - TripSync</title>
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>
<body class="bg-gray-50">
    <!-- Nav -->
    <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a class="flex items-center gap-3" href="../index.php">
                    <img alt="TripSync Logo" class="h-12 w-auto" src="../assets/images/logo.png">
                </a>
                <div class="hidden md:flex items-center gap-8">
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="../index.php">Home</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="dashboard.php">Dashboard</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="plan_trip.php">Plan Trip</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="marketplace.php">Marketplace</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="wallet.php">Wallet</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="reviews.php">Reviews</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="trip_history.php">Trip History</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="help.php">Help</a>
                    <div class="flex items-center gap-2">
                        <a class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-gray-600 hover:bg-gray-100"
                            href="notifications.php">
                            <i class="ri-notification-3-line text-lg"></i>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-rose-500 rounded-full"></span>
                        </a>
                        <a class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-gray-600 hover:bg-gray-100"
                            href="profile.php">
                            <i class="ri-user-line text-lg"></i>
                        </a>
                    </div>
                </div>
                <button class="md:hidden w-8 h-8 flex items-center justify-center cursor-pointer text-gray-700">
                    <i class="ri-menu-line text-2xl"></i>
                </button>
            </div>
        </div>
    </nav>

    <div class="pt-24 pb-16 px-4">
        <div class="max-w-5xl mx-auto mb-8 animate-in fade-in slide-in-from-top-4 duration-700">
            <div class="bg-gradient-to-r <?php echo $isConfirmed ? 'from-emerald-50 via-teal-50 to-emerald-50 border-emerald-100' : 'from-amber-50 via-teal-50 to-amber-50 border-amber-100'; ?> rounded-2xl p-8 text-center border">
                <div class="w-16 h-16 flex items-center justify-center <?php echo $isConfirmed ? 'bg-emerald-500 shadow-emerald-200' : 'bg-amber-500 shadow-amber-200'; ?> rounded-full mx-auto mb-4 shadow-lg">
                    <i class="ri-<?php echo $isConfirmed ? 'check-line' : 'bank-card-line'; ?> text-3xl text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo $isConfirmed ? 'Booking Confirmed!' : 'Complete Your Payment'; ?></h1>
                <p class="text-gray-500 text-sm mb-4"><?php echo $isConfirmed ? 'Your trip has been successfully booked. Get ready for an amazing adventure!' : 'Please review your booking details and complete the payment to secure your trip.'; ?></p>
                <div class="inline-flex items-center gap-2 bg-white px-4 py-2 rounded-full border border-gray-200 shadow-sm">
                    <span class="text-xs text-gray-400">Booking Ref:</span>
                    <span class="font-mono font-bold text-gray-900 text-sm"><?php echo $booking['reference_no']; ?></span>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="max-w-5xl mx-auto mb-6 bg-rose-50 border border-rose-100 text-rose-700 p-4 rounded-xl text-sm flex items-center gap-3">
                <i class="ri-error-warning-line text-lg"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="max-w-5xl mx-auto mb-6 bg-emerald-50 border border-emerald-100 text-emerald-700 p-4 rounded-xl text-sm flex items-center gap-3">
                <i class="ri-checkbox-circle-line text-lg"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="max-w-5xl mx-auto mb-6">
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <a class="hover:text-teal-600 transition-colors pointer" href="../index.php">Home</a>
                <i class="ri-arrow-right-s-line text-sm"></i>
                <a class="hover:text-teal-600 transition-colors pointer" href="dashboard.php">Dashboard</a>
                <i class="ri-arrow-right-s-line text-sm"></i>
                <span class="text-gray-600 font-medium"><?php echo $isConfirmed ? 'Booking Confirmation' : 'Checkout'; ?></span>
            </div>
        </div>

        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <?php if ($booking['type'] === 'hotel'): ?>
                    <!-- Accommodation Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-lg overflow-hidden group">
                        <?php if ($service['image']): ?>
                        <div class="relative w-full h-48 overflow-hidden">
                            <img alt="<?php echo htmlspecialchars($service['name']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" src="<?php echo $service['image']; ?>">
                            <div class="absolute top-3 right-3 flex items-center gap-1 bg-white/90 backdrop-blur-sm px-2.5 py-1 rounded-full border border-gray-100">
                                <i class="ri-star-fill text-amber-500 text-xs"></i>
                                <span class="text-xs font-bold text-gray-800"><?php echo $service['stars'] ?? '4.5'; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="p-5">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg mb-0.5"><?php echo htmlspecialchars($service['name']); ?></h3>
                                    <p class="text-xs text-gray-500 flex items-center gap-1">
                                        <i class="ri-map-pin-2-fill text-teal-500 text-xs"></i><?php echo htmlspecialchars($service['location']); ?>
                                    </p>
                                </div>
                                <span class="px-2.5 py-1 bg-teal-50 text-teal-700 text-xs font-semibold rounded-full whitespace-nowrap">
                                    <?php 
                                        $diff = strtotime($booking['end_date']) - strtotime($booking['start_date']);
                                        echo round($diff / (60 * 60 * 24)); 
                                    ?> Nights
                                </span>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-4 mb-3 border border-gray-100">
                                <p class="text-[10px] font-black text-teal-600 uppercase tracking-widest mb-3">Stay Summary</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 flex items-center justify-center bg-teal-100 rounded-lg text-teal-600"><i class="ri-calendar-check-line text-lg"></i></div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 uppercase">Check-in</p>
                                            <p class="text-xs font-bold text-gray-800"><?php echo date('D, M j, Y', strtotime($booking['start_date'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 flex items-center justify-center bg-rose-100 rounded-lg text-rose-500"><i class="ri-calendar-close-line text-lg"></i></div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 uppercase">Check-out</p>
                                            <p class="text-xs font-bold text-gray-800"><?php echo date('D, M j, Y', strtotime($booking['end_date'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($booking['type'] === 'vehicle'): ?>
                    <!-- Vehicle Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-lg overflow-hidden group">
                        <?php if ($service['image']): ?>
                        <div class="relative w-full h-44 overflow-hidden">
                            <img alt="<?php echo htmlspecialchars($service['name']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" src="<?php echo $service['image']; ?>">
                        </div>
                        <?php endif; ?>
                        <div class="p-5">
                            <h3 class="font-bold text-gray-900 text-lg mb-1"><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p class="text-xs text-gray-500 mb-4 flex items-center gap-1"><i class="ri-user-line text-teal-500"></i>Up to <?php echo $service['capacity']; ?> passengers</p>
                            
                            <?php if ($service['owner_name']): ?>
                            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-xl p-4 mb-4 border border-teal-100 flex items-center gap-4">
                                <img class="w-12 h-12 rounded-full object-cover border-2 border-white" src="<?php echo !empty($service['owner_pic']) ? '../assets/images/'.$service['owner_pic'] : '../assets/images/default-avatar.jpg'; ?>">
                                <div class="flex-1">
                                    <p class="text-sm font-bold text-gray-900"><?php echo $service['owner_name']; ?></p>
                                    <div class="flex items-center gap-1"><i class="ri-star-fill text-amber-500 text-xs"></i><span class="text-xs font-medium text-gray-600"><?php echo $service['rating']; ?> rating</span></div>
                                </div>
                                <span class="px-2.5 py-1 bg-emerald-500 text-white text-[10px] font-bold rounded-full uppercase">Assigned</span>
                            </div>
                            <?php endif; ?>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 flex items-center justify-center bg-teal-100 rounded-lg text-teal-600"><i class="ri-map-pin-time-line text-lg"></i></div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase">Pick-up</p>
                                        <p class="text-xs font-bold text-gray-800"><?php echo date('D, M j, Y', strtotime($booking['start_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 flex items-center justify-center bg-rose-100 rounded-lg text-rose-500"><i class="ri-flag-line text-lg"></i></div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase">Return</p>
                                        <p class="text-xs font-bold text-gray-800"><?php echo date('D, M j, Y', strtotime($booking['end_date'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Trip Timeline -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-lg p-6">
                        <h3 class="font-bold text-gray-900 text-lg mb-6 flex items-center gap-2">
                            <i class="ri-map-pin-line text-teal-600"></i> What Happens Next
                        </h3>
                        <div class="flex items-start justify-between mb-1 relative">
                            <div class="absolute top-4 left-0 w-full h-0.5 bg-gray-100 -z-0"></div>
                            <div class="absolute top-4 left-0 <?php echo $isConfirmed ? 'w-1/2' : 'w-1/4'; ?> h-0.5 bg-teal-500 transition-all duration-1000 -z-0"></div>

                            <div class="flex-1 flex flex-col items-center relative z-10">
                                <div class="w-8 h-8 flex items-center justify-center rounded-full mb-3 <?php echo $isConfirmed ? 'bg-teal-500 text-white' : 'bg-amber-500 text-white animate-pulse'; ?>">
                                    <i class="ri-<?php echo $isConfirmed ? 'check-line' : 'time-line'; ?> text-sm"></i>
                                </div>
                                <p class="text-[11px] font-bold text-gray-800">Payment</p>
                            </div>
                            <div class="flex-1 flex flex-col items-center relative z-10">
                                <div class="w-8 h-8 flex items-center justify-center rounded-full mb-3 bg-gray-100 text-gray-400">
                                    <i class="ri-calendar-event-line text-sm"></i>
                                </div>
                                <p class="text-[11px] font-bold text-gray-400">Preparation</p>
                            </div>
                            <div class="flex-1 flex flex-col items-center relative z-10">
                                <div class="w-8 h-8 flex items-center justify-center rounded-full mb-3 bg-gray-100 text-gray-400">
                                    <i class="ri-checkbox-circle-line text-sm"></i>
                                </div>
                                <p class="text-[11px] font-bold text-gray-400">Ready</p>
                            </div>
                            <div class="flex-1 flex flex-col items-center relative z-10">
                                <div class="w-8 h-8 flex items-center justify-center rounded-full mb-3 bg-gray-100 text-gray-400">
                                    <i class="ri-flight-takeoff-line text-sm"></i>
                                </div>
                                <p class="text-[11px] font-bold text-gray-400">Enjoy!</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-lg overflow-hidden sticky top-24">
                        <div class="bg-gradient-to-r from-teal-600 to-teal-700 px-6 py-5">
                            <h3 class="text-white font-bold text-base flex items-center gap-2">
                                <i class="ri-bank-card-line"></i> <?php echo $isConfirmed ? 'Payment Details' : 'Payment Summary'; ?>
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4 mb-6">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">Booking Amount</span>
                                    <span class="font-bold text-gray-900">LKR <?php echo number_format($booking['total_price'], 2); ?></span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">Service Fee</span>
                                    <span class="font-bold text-gray-900">LKR 0.00</span>
                                </div>
                            </div>
                            <div class="border-t border-dashed border-gray-200 pt-6 mb-6">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-gray-900 text-lg"><?php echo $isConfirmed ? 'Paid Total' : 'Total Amount'; ?></span>
                                    <span class="text-2xl font-black text-teal-700">LKR <?php echo number_format($booking['total_price'], 2); ?></span>
                                </div>
                            </div>

                            <?php if (!$isConfirmed): ?>
                            <div class="space-y-3">
                                <form action="https://sandbox.payhere.lk/pay/checkout" method="post">
                                    <input type="hidden" name="merchant_id" value="<?php echo $merchant_id; ?>">
                                    <input type="hidden" name="return_url" value="<?php echo BASE_URL; ?>customer/booking_confirmation.php?id=<?php echo $bookingId; ?>&status=success">
                                    <input type="hidden" name="cancel_url" value="<?php echo BASE_URL; ?>customer/booking_confirmation.php?id=<?php echo $bookingId; ?>&status=cancel">
                                    <input type="hidden" name="notify_url" value="https://woozy-unmelodramatically-nancey.ngrok-free.dev/TripSync/api/payhere-notify.php">
                                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                    <input type="hidden" name="items" value="TripSync Booking #<?php echo $bookingId; ?>">
                                    <input type="hidden" name="currency" value="LKR">
                                    <input type="hidden" name="amount" value="<?php echo $amount_formatted; ?>">
                                    <input type="hidden" name="first_name" value="<?php echo $user['name']; ?>">
                                    <input type="hidden" name="last_name" value="">
                                    <input type="hidden" name="email" value="<?php echo $user['email']; ?>">
                                    <input type="hidden" name="phone" value="<?php echo $user['phone']; ?>">
                                    <input type="hidden" name="address" value="Sri Lanka">
                                    <input type="hidden" name="city" value="Colombo">
                                    <input type="hidden" name="country" value="Sri Lanka">
                                    <input type="hidden" name="hash" value="<?php echo $hash; ?>">
                                    <button type="submit" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition-all font-bold text-sm shadow-lg">
                                        <i class="ri-bank-card-line text-lg"></i> Pay with Card
                                    </button>
                                </form>
                                <form method="post">
                                    <button type="submit" name="pay_wallet" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-white border-2 border-teal-600 text-teal-600 rounded-xl hover:bg-teal-50 transition-all font-bold text-sm">
                                        <i class="ri-wallet-3-line text-lg"></i> Pay with Wallet
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 flex items-center justify-center bg-white rounded-lg border border-emerald-100 text-emerald-600 shadow-sm">
                                        <i class="ri-checkbox-circle-fill text-2xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-gray-900">Payment Successful</p>
                                        <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest">Booking Confirmed</p>
                                    </div>
                                    <span class="px-2.5 py-1 bg-emerald-500 text-white text-[10px] font-black rounded-full">CONFIRMED</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Traveler Info -->
                        <div class="p-6 bg-gray-50 border-t border-gray-200">
                            <h3 class="font-bold text-gray-900 text-xs uppercase tracking-widest mb-4">Traveler Details</h3>
                            <div class="flex items-center gap-3 mb-4">
                                <img alt="<?php echo $user['name']; ?>" class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm" src="<?php echo !empty($user['profile_pic']) ? '../assets/images/'.$user['profile_pic'] : '../assets/images/default-avatar.jpg'; ?>">
                                <div>
                                    <p class="text-sm font-bold text-gray-900"><?php echo $user['name']; ?></p>
                                    <p class="text-[10px] text-gray-400 font-medium italic">Primary Traveler</p>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center gap-3 text-xs text-gray-600 font-medium underline decoration-teal-500/30">
                                    <i class="ri-mail-line text-teal-600 w-4"></i> <?php echo $user['email']; ?>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-gray-600 font-medium underline decoration-teal-500/30">
                                    <i class="ri-phone-line text-teal-600 w-4"></i> <?php echo $user['phone'] ?? '+94 XXX XXX XXX'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
            <img alt="TripSync Logo" class="h-12 w-auto mb-6 mx-auto" src="../assets/images/logo.png">
            <p class="text-teal-50 text-sm mb-6 max-w-xs mx-auto">Your trusted partner for seamless travel planning across Sri Lanka.</p>
            <div class="flex justify-center gap-4 mb-8">
                <a href="#" class="w-10 h-10 flex items-center justify-center bg-white/10 rounded-full hover:bg-teal-500 transition-all"><i class="ri-facebook-fill text-xl"></i></a>
                <a href="#" class="w-10 h-10 flex items-center justify-center bg-white/10 rounded-full hover:bg-teal-500 transition-all"><i class="ri-instagram-line text-xl"></i></a>
            </div>
            <div class="border-t border-teal-500/30 pt-8 text-teal-50/70 text-sm">
                <p>&copy; 2026 TripSync. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
