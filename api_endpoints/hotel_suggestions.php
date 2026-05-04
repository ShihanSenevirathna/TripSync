<?php
require_once '../includes/config.php';
require_once '../api/google_places_api.php';

header('Content-Type: application/json');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (strlen($query) < 3) {
    echo json_encode([]);
    exit;
}

$googleApi = new GooglePlacesAPI();
$suggestions = $googleApi->getHotelSuggestions($query);

echo json_encode($suggestions);
?>
