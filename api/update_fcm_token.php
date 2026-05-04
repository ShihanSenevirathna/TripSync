<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$token = $data['token'];

// Update user's FCM token
$stmt = $conn->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
$stmt->bind_param("si", $token, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'FCM Token updated']);
}
else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
