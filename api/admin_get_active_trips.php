<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Fetch all active bookings and their latest tracking coordinates
$query = "
    SELECT 
        b.id, b.reference_no, b.status, b.type, b.total_price,
        u.name as customer_name,
        p.name as partner_name, p.phone as partner_phone,
        t.latitude, t.longitude, t.updated_at as last_update
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN users p ON b.assigned_partner_id = p.id
    LEFT JOIN trip_tracking t ON b.id = t.booking_id
    WHERE b.status IN ('confirmed', 'arrived', 'in_progress')
    AND t.latitude IS NOT NULL
    AND t.updated_at >= (NOW() - INTERVAL 1 HOUR)
";

$res = $conn->query($query);
$trips = [];
while ($row = $res->fetch_assoc()) {
    $trips[] = $row;
}

echo json_encode([
    'success' => true,
    'count' => count($trips),
    'trips' => $trips
]);
