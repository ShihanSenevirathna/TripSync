<?php
require_once '../includes/config.php';
require_once '../api/google_places_api.php';

header('Content-Type: application/json');

$location = isset($_GET['location']) ? $_GET['location'] : '';

if (!$location) {
    echo json_encode(['success' => false, 'message' => 'Location required']);
    exit();
}

$googleApi = new GooglePlacesAPI();
$hotels = $googleApi->searchHotels($location);

if (!empty($hotels)) {
    // Format to match expected search response if needed, 
    // though GooglePlacesAPI già formats it beautifully.
    echo json_encode(['success' => true, 'hotels' => $hotels]);
}
else {
    echo json_encode(['success' => false, 'message' => 'No hotels found for this location']);
}
?>
