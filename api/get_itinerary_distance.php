<?php
header("Content-Type: application/json");
require_once "../includes/config.php";

$plan_id = isset($_GET["plan_id"]) ? (int)$_GET["plan_id"] : 0;

if (!$plan_id) {
    echo json_encode(["success" => false, "message" => "Plan ID required"]);
    exit;
}

// Fetch all destinations with coordinates
$stmt = $conn->prepare("SELECT latitude, longitude FROM destinations WHERE plan_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY day_number ASC, arrival_time ASC");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$result = $stmt->get_result();
$coords = $result->fetch_all(MYSQLI_ASSOC);

if (count($coords) < 2) {
    echo json_encode(["success" => true, "distance" => 0, "message" => "Not enough destinations for distance calculation"]);
    exit;
}

// Calculate total distance using Google Distance Matrix API
// We'll process them in pairs or as waypoints
$origin = $coords[0]['latitude'] . ',' . $coords[0]['longitude'];
$destination = end($coords)['latitude'] . ',' . end($coords)['longitude'];
$waypoints = [];
for ($i = 1; $i < count($coords) - 1; $i++) {
    $waypoints[] = $coords[$i]['latitude'] . ',' . $coords[$i]['longitude'];
}

$url = "https://maps.googleapis.com/maps/api/directions/json?origin=" . urlencode($origin) .
    "&destination=" . urlencode($destination) .
    "&waypoints=" . urlencode(implode('|', $waypoints)) .
    "&key=" . GOOGLE_MAPS_API_KEY;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($data['status'] === 'OK') {
    $totalDistance = 0;
    foreach ($data['routes'][0]['legs'] as $leg) {
        $totalDistance += $leg['distance']['value'];
    }

    // Convert to KM
    $totalDistanceKm = $totalDistance / 1000;

    echo json_encode([
        "success" => true,
        "distance" => round($totalDistanceKm, 2),
        "unit" => "km"
    ]);
}
else {
    echo json_encode([
        "success" => false,
        "message" => "Google API error: " . ($data['error_message'] ?? $data['status'])
    ]);
}
?>
