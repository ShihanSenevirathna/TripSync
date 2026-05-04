<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'partner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$is_online = isset($data['is_online']) ? (int)$data['is_online'] : 0;

$stmt = $conn->prepare("UPDATE users SET is_online = ? WHERE id = ?");
$stmt->bind_param("ii", $is_online, $user_id);
$success = $stmt->execute();

if ($success) {
    // Also sync with vehicle status
    $v_status = $is_online ? 'available' : 'inactive';
    $vstmt = $conn->prepare("UPDATE vehicles SET status = ? WHERE owner_id = ?");
    $vstmt->bind_param("si", $v_status, $user_id);
    $vstmt->execute();
    $vstmt->close();

    echo json_encode(['success' => true, 'is_online' => $is_online, 'vehicle_status' => $v_status]);
}
else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
