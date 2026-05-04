<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('partner');

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// In a real app, we'd filter by proximity. 
// For now, fetch current pending bookings that are NOT assigned to anyone (Global Pool)
// OR bookings that were rejected by their assigned partner (status='pending' and assigned_partner_id IS NULL or different)

try {
    // Let's assume global jobs have assigned_partner_id = NULL
    $stmt = $conn->prepare("
        SELECT b.*, u.name as customer_name 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        WHERE b.status = 'pending' 
        AND b.type IN ('vehicle', 'tour')
        AND (b.assigned_partner_id IS NULL OR b.assigned_partner_id = 0)
        ORDER BY b.created_at DESC LIMIT 10
    ");
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'broadcasts' => $jobs]);
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
