<?php
ob_start();
session_start();
require_once '../includes/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }

    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) throw new Exception('Invalid JSON input');

    $booking_id = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
    $new_status = isset($data['status']) ? $data['status'] : '';
    $lat = isset($data['latitude']) ? (float)$data['latitude'] : null;
    $lng = isset($data['longitude']) ? (float)$data['longitude'] : null;

    if (!$booking_id || !$new_status) {
        throw new Exception('Missing booking_id or status');
    }

    // Role-based validation and fetching booking details
    if ($user_role === 'partner') {
        $stmt = $conn->prepare("SELECT b.*, u.name as customer_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.assigned_partner_id = ?");
        $stmt->bind_param("ii", $booking_id, $user_id);
    } else {
        // Customer can only cancel their own bookings
        if ($new_status !== 'cancelled') {
            throw new Exception('Customers can only cancel bookings.');
        }
        $stmt = $conn->prepare("SELECT b.*, u.name as customer_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.user_id = ?");
        $stmt->bind_param("ii", $booking_id, $user_id);
    }
    
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        throw new Exception('Booking not found or access denied.');
    }

    $current_status = $booking['status'];
    $ref_no = $booking['reference_no'];
    $assigned_partner_id = $booking['assigned_partner_id'];
    $customer_id = $booking['user_id'];

    if ($current_status === 'completed' || $current_status === 'cancelled') {
        throw new Exception("Cannot update a $current_status booking.");
    }

    $conn->begin_transaction();

    // 1. Handle Cancellations & Refunds (If customer cancels a confirmed booking)
    if ($new_status === 'cancelled') {
        if ($user_role === 'customer' && $current_status === 'confirmed') {
            // Check if payment was via wallet to issue refund
            $t_stmt = $conn->prepare("SELECT id FROM transactions WHERE booking_id = ? AND user_id = ? AND method = 'wallet' AND status = 'completed' AND type = 'debit'");
            $t_stmt->bind_param("ii", $booking_id, $customer_id);
            $t_stmt->execute();
            if ($t_stmt->get_result()->num_rows > 0) {
                // Issue Refund
                $refund_amount = $booking['total_price'];
                $r_stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
                $r_stmt->bind_param("di", $refund_amount, $customer_id);
                $r_stmt->execute();

                $tr_ref = "REFUND-" . $ref_no;
                $lt_stmt = $conn->prepare("INSERT INTO transactions (user_id, booking_id, amount, type, method, status, transaction_ref) VALUES (?, ?, ?, 'credit', 'wallet', 'completed', ?)");
                $lt_stmt->bind_param("iids", $customer_id, $booking_id, $refund_amount, $tr_ref);
                $lt_stmt->execute();
            }
        }
        
        // If partner cancels/declines, reset their busy status
        if ($assigned_partner_id) {
            $conn->query("UPDATE users SET is_busy = 0 WHERE id = $assigned_partner_id");
        }
    }

    // 2. Update Booking Status
    $up_stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $up_stmt->bind_param("si", $new_status, $booking_id);
    $up_stmt->execute();

    // 3. Log event (For timeline history)
    $log_stmt = $conn->prepare("INSERT INTO booking_status_logs (booking_id, status, latitude, longitude) VALUES (?, ?, ?, ?)");
    $log_stmt->bind_param("isdd", $booking_id, $new_status, $lat, $lng);
    $log_stmt->execute();

    // 4. Update Live Tracking Table (Latest position for real-time ETA calc)
    if ($lat && $lng) {
        $track_stmt = $conn->prepare("INSERT INTO trip_tracking (booking_id, latitude, longitude, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE latitude = ?, longitude = ?, updated_at = NOW()");
        $track_stmt->bind_param("idddd", $booking_id, $lat, $lng, $lat, $lng);
        $track_stmt->execute();
    }

    // 5. Notifications
    $n_title = "Booking Update: #$ref_no";
    $n_msg = "";
    $notify_user_id = 0;

    if ($user_role === 'partner') {
        $notify_user_id = $customer_id;
        $status_map = [
            'confirmed' => 'Your trip has been confirmed by the partner!',
            'cancelled' => 'The partner has declined your request.',
            'arrived' => 'Your driver has arrived at the pickup location.',
            'in_progress' => 'Your trip has started.',
            'completed' => 'Your trip has been completed successfully.'
        ];
        $n_msg = $status_map[$new_status] ?? "Status updated to $new_status";
    } else {
        // Customer cancelled, notify partner if assigned
        if ($assigned_partner_id) {
            $notify_user_id = $assigned_partner_id;
            $n_msg = "The customer has cancelled the booking #$ref_no.";
        }
    }

    if ($notify_user_id && $n_msg) {
        $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'trip_update')");
        $n_stmt->bind_param("iss", $notify_user_id, $n_title, $n_msg);
        $n_stmt->execute();
    }

    // 5. Financial Settlement (If partner completes trip)
    if ($user_role === 'partner' && $new_status === 'completed') {
        $partner_share = $booking['total_price'] * 0.80;
        $platform_share = $booking['total_price'] * 0.20;
        $trans_ref = "EARN-" . $ref_no;
        
        $conn->query("UPDATE wallets SET balance = balance + $partner_share WHERE user_id = $assigned_partner_id");
        $conn->query("INSERT INTO transactions (user_id, booking_id, amount, type, method, status, transaction_ref) VALUES ($assigned_partner_id, $booking_id, $partner_share, 'credit', 'bank_transfer', 'completed', '$trans_ref')");
        $conn->query("INSERT INTO platform_ledger (booking_id, amount, description) VALUES ($booking_id, $platform_share, 'Commission from #$ref_no')");
        
        if ($booking['plan_id']) {
            $conn->query("UPDATE travel_plans SET status = 'completed' WHERE id = " . $booking['plan_id']);
        }
        $conn->query("UPDATE users SET is_busy = 0 WHERE id = $user_id");
    }

    $conn->commit();
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);

} catch (Throwable $e) {
    if (isset($conn) && $conn->ping()) $conn->rollback();
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
