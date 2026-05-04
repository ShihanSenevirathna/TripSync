<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;

    if (!$user_id || $role !== 'partner') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $bank_name = $_POST['bank_name'] ?? '';
    $branch_name = $_POST['branch_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';

    if (empty($bank_name) || empty($account_number)) {
        throw new Exception("Bank name and Account number are required.");
    }

    $stmt = $conn->prepare("UPDATE users SET bank_name = ?, branch_name = ?, account_number = ? WHERE id = ?");
    $stmt->bind_param("sssi", $bank_name, $branch_name, $account_number, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Bank details updated successfully.']);
    }
    else {
        throw new Exception("Failed to update bank details.");
    }

}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
