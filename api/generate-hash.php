<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
checkAuth('customer');

$userId = $_SESSION['user_id'];
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$type = isset($_POST['type']) ? $_POST['type'] : 'TOPUP';
$order_id = "";

if ($type === 'TOPUP') {
    $order_id = "TOPUP_" . $userId . "_" . time();
    $amount_val = number_format($amount, 2, '.', '');
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, method, status, transaction_ref) VALUES (?, ?, 'credit', 'payhere', 'pending', ?)");
    $stmt->bind_param("ids", $userId, $amount_val, $order_id);
    $stmt->execute();
}

$merchant_id = PAYHERE_MERCHANT_ID;
$amount_formatted = number_format($amount, 2, '.', '');
$currency = "LKR";
$merchant_secret = PAYHERE_SECRET;

$hash = strtoupper(md5($merchant_id . $order_id . $amount_formatted . $currency . strtoupper(md5($merchant_secret))));

echo json_encode([
    'hash' => $hash,
    'order_id' => $order_id
]);
?>