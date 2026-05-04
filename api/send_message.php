<?php
header('Content-Type: application/json');
require_once '../includes/auth_check.php';
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['booking_id']) || !isset($data['receiver_id']) || !isset($data['message'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$booking_id = (int)$data['booking_id'];
$receiver_id = (int)$data['receiver_id'];
$message = trim($data['message']);

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit();
}

try {
    $stmt = $conn->prepare("INSERT INTO messages (booking_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $booking_id, $sender_id, $receiver_id, $message);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    }
    else {
        throw new Exception($stmt->error);
    }
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
