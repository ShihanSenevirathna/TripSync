<?php
require_once "../includes/config.php";

$logFile = __DIR__ . "/../logs/payment.log";
function logPayment($msg)
{
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

// 1. Get POST Data
$merchant_id = $_POST['merchant_id'] ?? '';
$order_id = $_POST['order_id'] ?? '';
$payhere_amount = $_POST['payhere_amount'] ?? '';
$payhere_currency = $_POST['payhere_currency'] ?? '';
$status_code = $_POST['status_code'] ?? '';
$md5sig = $_POST['md5sig'] ?? '';

logPayment("Notification received: Order $order_id, Status $status_code, Amount $payhere_amount");

// 2. Verify Signature
$merchant_secret = PAYHERE_SECRET;
$local_md5sig = strtoupper(
    md5($merchant_id . $order_id . $payhere_amount . $payhere_currency . $status_code . strtoupper(md5($merchant_secret)))
);

if ($local_md5sig !== $md5sig) {
    logPayment("INVALID SIGNATURE: Local expected $local_md5sig, got $md5sig");
    exit("Invalid signature");
}

// 3. Process Status (2 = Success)
if ($status_code == 2) {
    $parts = explode('_', $order_id);
    $type = $parts[0] ?? ''; // TOPUP or BOOKING

    $conn->begin_transaction();
    try {
        if ($type === 'BOOKING' && isset($parts[1]) && isset($parts[2])) {
            $bookingId = $parts[1];
            $userId = $parts[2];

            $stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $bookingId, $userId);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, booking_id, amount, type, method, status, transaction_ref) VALUES (?, ?, ?, 'debit', 'payhere', 'completed', ?)");
            $stmt->bind_param("iids", $userId, $bookingId, $payhere_amount, $order_id);
            $stmt->execute();

            logPayment("BOOKING SUCCESS: User #$userId, Booking #$bookingId");
        }
        else if ($type === 'TOPUP' && isset($parts[1])) {
            $userId = $parts[1];

            // Check if transaction is already completed (by the fallback in wallet.php)
            $chkTrans = $conn->prepare("SELECT id, status FROM transactions WHERE transaction_ref = ?");
            $chkTrans->bind_param("s", $order_id);
            $chkTrans->execute();
            $transRes = $chkTrans->get_result();

            if ($transRes->num_rows > 0) {
                $transaction = $transRes->fetch_assoc();
                if ($transaction['status'] !== 'completed') {
                    // Update Transaction to completed
                    $upTrans = $conn->prepare("UPDATE transactions SET status = 'completed', amount = ? WHERE transaction_ref = ?");
                    $upTrans->bind_param("ds", $payhere_amount, $order_id);
                    $upTrans->execute();

                    // Update Wallet
                    $checkStmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
                    $checkStmt->bind_param("i", $userId);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();

                    if ($checkResult->num_rows > 0) {
                        $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
                        $stmt->bind_param("di", $payhere_amount, $userId);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
                        $stmt->bind_param("id", $userId, $payhere_amount);
                    }
                    $stmt->execute();
                    logPayment("TOPUP SUCCESS: User #$userId, Added $payhere_amount");
                } else {
                    logPayment("TOPUP ALREADY PROCESSED: Order $order_id");
                }
            } else {
                logPayment("TOPUP PENDING TRANSACTION NOT FOUND: Order $order_id");
            }
        }
        else {
            logPayment("UNKNOWN ORDER TYPE: $type in $order_id");
        }

        $conn->commit();
        echo "OK";
    }
    catch (Exception $e) {
        $conn->rollback();
        logPayment("DATABASE ERROR: " . $e->getMessage());
    }
}
else {
    logPayment("PAYMENT FAILED/ABORTED: Status $status_code");
}
?>