<?php
session_start();
require_once '../includes/config.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'partner') {
        throw new Exception('Unauthorized access');
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['destination_id']) || empty($data['status'])) {
        throw new Exception('Missing destination ID or status');
    }

    $dest_id = (int)$data['destination_id'];
    $new_status = $data['status']; // 'pending', 'arrived', 'completed'

    // Security check: Destination belongs to a plan assigned to this partner
    $stmt = $conn->prepare("
        SELECT d.id 
        FROM destinations d 
        JOIN travel_plans tp ON d.plan_id = tp.id 
        JOIN bookings b ON b.plan_id = tp.id 
        WHERE d.id = ? AND b.assigned_partner_id = ?
    ");
    $stmt->bind_param("ii", $dest_id, $_SESSION['user_id']);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception('Destination not found or unauthorized');
    }

    $update = $conn->prepare("UPDATE destinations SET status = ? WHERE id = ?");
    $update->bind_param("si", $new_status, $dest_id);
    
    if ($update->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Database update failed');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
