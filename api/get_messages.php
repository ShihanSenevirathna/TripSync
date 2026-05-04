<?php
header('Content-Type: application/json');
require_once '../includes/auth_check.php';
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
    exit();
}

try {
    // Fetch messages for this booking
    $stmt = $conn->prepare("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.booking_id = ? ORDER BY m.created_at ASC");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Mark messages as read if receiver is current user
    $ustmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE booking_id = ? AND receiver_id = ?");
    $ustmt->bind_param("ii", $booking_id, $user_id);
    $ustmt->execute();

    echo json_encode(['success' => true, 'messages' => $messages]);
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
