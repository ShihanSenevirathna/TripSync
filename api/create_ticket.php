<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('partner');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$subject = $data['subject'] ?? '';
$description = $data['description'] ?? '';
$priority = $data['priority'] ?? 'medium';

if (empty($subject) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Subject and description are required']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, description, priority, status) VALUES (?, ?, ?, ?, 'open')");
    $stmt->bind_param("isss", $user_id, $subject, $description, $priority);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Ticket created successfully']);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Failed to create ticket']);
    }
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
