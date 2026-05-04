<?php
ob_start();
session_start();
require_once '../includes/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'partner') {
        throw new Exception('Unauthorized');
    }

    $user_id = $_SESSION['user_id'];
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['booking_id'])) {
        throw new Exception('Invalid booking ID');
    }

    $booking_id = (int)$data['booking_id'];

    $conn->begin_transaction();

    // 1. Check if the booking is still available (FOR UPDATE locks the row)
    $stmt = $conn->prepare("SELECT status, assigned_partner_id, reference_no, user_id FROM bookings WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        throw new Exception('Booking record not found');
    }

    if ($booking['status'] !== 'pending') {
        throw new Exception('Job is already ' . $booking['status'] . ' and no longer available.');
    }

    if ($booking['assigned_partner_id'] !== NULL && $booking['assigned_partner_id'] != 0) {
        throw new Exception('This job has already been claimed by another partner.');
    }

    // 2. Assign the job to the current partner and update status to confirmed
    $stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed', assigned_partner_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $user_id, $booking_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update booking assignment.');
    }

    // 3. Update Partner Status: Mark as Busy
    $ustmt = $conn->prepare("UPDATE users SET is_busy = 1 WHERE id = ?");
    $ustmt->bind_param("i", $user_id);
    $ustmt->execute();

    // 4. Notify Customer
    $ref_no = $booking['reference_no'];
    $cust_id = $booking['user_id'];
    $n_title = "Trip Update: #$ref_no";
    $n_msg = "A partner has accepted your job request and is now handling your booking.";

    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'trip_update')");
    $n_stmt->bind_param("iss", $cust_id, $n_title, $n_msg);
    $n_stmt->execute();

    $conn->commit();

    // Clear buffer and send JSON
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Job accepted! Drive safe.']);

}
catch (Throwable $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
