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
    $role = $_SESSION['role'] ?? null;

    if (!$user_id || $role !== 'partner') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $conn->begin_transaction();

    // 1. Check current balance
    $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
    $balance = $wallet['balance'] ?? 0;

    if ($balance <= 0) {
        throw new Exception("Insufficient balance for payout.");
    }

    // 2. Create a pending debit transaction
    $ref = 'PAY-' . $user_id . '-' . time();
    $tstmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, method, status, transaction_ref) VALUES (?, ?, 'debit', 'bank_transfer', 'pending', ?)");
    $tstmt->bind_param("ids", $user_id, $balance, $ref);
    $tstmt->execute();

    // 3. Clear wallet balance (or move to frozen?)
    // For now, let's deduct the full balance since it's a request for all.
    $ustmt = $conn->prepare("UPDATE wallets SET balance = 0, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
    $ustmt->bind_param("i", $user_id);
    $ustmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Payout request submitted successfully.']);

}
catch (Exception $e) {
    if (isset($conn))
        $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
