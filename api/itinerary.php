<?php
header("Content-Type: application/json");
require_once "../includes/config.php";
require_once "../includes/auth_check.php";

$uid = $_SESSION["user_id"] ?? 0;
if (!$uid) {
    echo json_encode(["success" => false, "message" => "Auth failed"]);
    exit;
}

$action = $_GET["action"] ?? "";
// Handle both JSON and traditional POST data
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!$data) {
    $data = $_POST;
}

try {
    if ($action === "add" || $action === "edit") {
        $planId = (int)($data["plan_id"] ?? 0);
        $dayNum = (int)($data["day_number"] ?? 1);
        $locName = trim($data["location_name"] ?? "");
        $arrival = $data["arrival_time"] ?? "";
        $departure = $data["departure_time"] ?? "";
        $notes = trim($data["notes"] ?? "");
        $lat = ($data["latitude"] ?? "") !== "" ? (float)$data["latitude"] : null;
        $lng = ($data["longitude"] ?? "") !== "" ? (float)$data["longitude"] : null;

        // Validation
        if (!$planId || !$locName) {
            echo json_encode(["success" => false, "message" => "Plan ID and Location are required."]);
            exit;
        }
        
        // If Arrival is missing but Departure exists (Starting Point), sync them
        if (!$arrival && $departure) {
            $arrival = $departure;
        }

        // Final check for time
        if (!$arrival) {
            echo json_encode(["success" => false, "message" => "Arrival Time is required."]);
            exit;
        }

        // Verify plan ownership
        $stmt = $conn->prepare("SELECT id FROM travel_plans WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $planId, $uid);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            echo json_encode(["success" => false, "message" => "Unauthorized access to this plan"]);
            exit;
        }

        if ($action === "add") {
            $stmt = $conn->prepare("INSERT INTO destinations (plan_id, day_number, location_name, arrival_time, departure_time, notes, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssss", $planId, $dayNum, $locName, $arrival, $departure, $notes, $lat, $lng);
        } else {
            $did = (int)($data["id"] ?? 0);
            $stmt = $conn->prepare("UPDATE destinations SET location_name=?, arrival_time=?, departure_time=?, notes=?, latitude=?, longitude=? WHERE id=? AND plan_id=?");
            $stmt->bind_param("ssssssii", $locName, $arrival, $departure, $notes, $lat, $lng, $did, $planId);
        }
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
        }
    } elseif ($action === "delete") {
        $did = (int)($data["id"] ?? 0);
        $stmt = $conn->prepare("DELETE d FROM destinations d JOIN travel_plans p ON d.plan_id = p.id WHERE d.id = ? AND p.user_id = ?");
        $stmt->bind_param("ii", $did, $uid);
        echo json_encode(["success" => $stmt->execute()]);
    } elseif ($action === 'confirm') {
        $pid = (int)($data["plan_id"] ?? 0);
        $stmt = $conn->prepare("UPDATE travel_plans SET status = 'confirmed' WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $pid, $uid);
        echo json_encode(["success" => $stmt->execute()]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action specified."]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
?>