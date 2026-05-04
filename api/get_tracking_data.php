<?php
require_once '../includes/config.php';
require_once '../includes/maps_helper.php';
header('Content-Type: application/json');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
    exit();
}

$maps = new MapsHelper();

// 1. Fetch current booking details
$b_sql = "SELECT status, pickup_location, dropoff_location, plan_id, start_date, total_price FROM bookings WHERE id = ?";
$b_stmt = $conn->prepare($b_sql);
$b_stmt->bind_param("i", $booking_id);
$b_stmt->execute();
$booking = $b_stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit();
}

// 2. Fetch live tracking data (Last known position)
$t_sql = "SELECT latitude, longitude, updated_at FROM trip_tracking WHERE booking_id = ? ORDER BY updated_at DESC LIMIT 1";
$t_stmt = $conn->prepare($t_sql);
$t_stmt->bind_param("i", $booking_id);
$t_stmt->execute();
$tracking = $t_stmt->get_result()->fetch_assoc();

// 3. Fetch Itinerary / Destinations status
$destinations = [];
if ($booking['plan_id']) {
    $d_stmt = $conn->prepare("SELECT id, location_name, day_number, arrival_time, status FROM destinations WHERE plan_id = ? ORDER BY day_number ASC, arrival_time ASC");
    $d_stmt->bind_param("i", $booking['plan_id']);
    $d_stmt->execute();
    $destinations = $d_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 4. Calculate dynamic progress, ETA, and Distance
$progress_map = ['pending' => 5, 'confirmed' => 15, 'arrived' => 35, 'in_progress' => 65, 'completed' => 100, 'cancelled' => 0];
$progress_pct = $progress_map[$booking['status']] ?? 0;

$eta = ($booking['status'] === 'cancelled') ? 'Cancelled' : (($booking['status'] === 'completed') ? 'Arrived' : 'Calculating...');
$dist = ($booking['status'] === 'cancelled') ? 'N/A' : (($booking['status'] === 'completed') ? '0 km' : 'TBD');

// Real-Time Google Maps Integration (Step 02: Handling Solo Bookings)
if ($tracking && !empty($tracking['latitude']) && !in_array($booking['status'], ['cancelled', 'completed'])) {
    $origin = $tracking['latitude'] . ',' . $tracking['longitude'];
    
    // Choose Next Target (Fallback to dropoff for solo bookings)
    $target = $booking['dropoff_location'];
    if ($booking['status'] === 'confirmed' || $booking['status'] === 'arrived') {
        $target = $booking['pickup_location'];
    } elseif (!empty($destinations)) {
        foreach ($destinations as $dest) {
            if ($dest['status'] !== 'completed') {
                $target = $dest['location_name'];
                break;
            }
        }
    }

    if ($target) {
        $matrix = $maps->getDistanceMatrix($origin, $target);
        if ($matrix['success']) {
            $eta = $matrix['duration'];
            $dist = $matrix['distance'];
        }
    }
} elseif (in_array($booking['status'], ['confirmed', 'arrived', 'in_progress'])) {
    // If no tracking yet but trip is active
    if ($booking['status'] === 'in_progress') {
        $eta = "Pending GPS";
        $dist = "Calculating...";
    } else {
        $eta = "Awaiting Driver";
        $dist = "Driver en route";
    }
}

echo json_encode([
    'success' => true,
    'status' => $booking['status'],
    'latitude' => $tracking['latitude'] ?? null,
    'longitude' => $tracking['longitude'] ?? null,
    'last_updated' => $tracking['updated_at'] ?? null,
    'progress' => $progress_pct,
    'eta' => $eta,
    'distance' => $dist,
    'destinations' => $destinations,
    'pickup_location' => $booking['pickup_location'],
    'dropoff_location' => $booking['dropoff_location'],
    'total_price' => $booking['total_price']
]);
