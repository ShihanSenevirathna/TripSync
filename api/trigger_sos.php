<?php
header('Content-Type: application/json');
require_once '../includes/auth_check.php';
require_once '../includes/config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['booking_id']) || !isset($data['latitude']) || !isset($data['longitude'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$booking_id = (int)$data['booking_id'];
$lat = $data['latitude'];
$lng = $data['longitude'];

try {
    // 1. Log the system-level SOS alert (ensure table exists with correct schema if missing)
    $stmt = $conn->prepare("INSERT INTO emergency_alerts (booking_id, user_id, latitude, longitude, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->bind_param("iidd", $booking_id, $user_id, $lat, $lng);
    $stmt->execute();

    // 2. Create a high-priority ticket in the Support Hub for the Admin
    $subject = "🚨 SOS EMERGENCY ALERT";
    $description = "EMERGENCY TRIGGERED by Partner. Location: https://www.google.com/maps?q=$lat,$lng. Stay in immediate contact.";
    $priority = 'emergency';

    $tstmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, description, priority, status) VALUES (?, ?, ?, ?, 'open')");
    $tstmt->bind_param("isss", $user_id, $subject, $description, $priority);

    if ($tstmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'SOS triggered successfully. Our safety team has been notified and is tracking your location.']);
    }
    else {
        throw new Exception($tstmt->error);
    }
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to trigger SOS: ' . $e->getMessage()]);
}
?>
