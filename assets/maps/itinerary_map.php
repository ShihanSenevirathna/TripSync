<?php
require_once '../../includes/config.php';

$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$day = isset($_GET['day']) ? (int)$_GET['day'] : 1;

// Fetch destinations for the day
$stmt = $conn->prepare("SELECT location_name, arrival_time, departure_time, latitude, longitude FROM destinations WHERE plan_id = ? AND day_number = ? ORDER BY arrival_time ASC");
$stmt->bind_param("ii", $plan_id, $day);
$stmt->execute();
$destinations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// LOGIC: If day > 1, fetch the last location of the previous day to use as a starting point
if ($day > 1) {
    $prevDay = $day - 1;
    $stmtPrev = $conn->prepare("SELECT location_name, arrival_time, departure_time, latitude, longitude FROM destinations WHERE plan_id = ? AND day_number = ? ORDER BY arrival_time DESC LIMIT 1");
    $stmtPrev->bind_param("ii", $plan_id, $prevDay);
    $stmtPrev->execute();
    $lastLocationPrevDay = $stmtPrev->get_result()->fetch_assoc();

    if ($lastLocationPrevDay) {
        // Prepend the last location of the previous day to the current destinations
        // We mark it as 'is_start_node' so we can handle it differently in JS if needed
        $lastLocationPrevDay['is_prev_day_end'] = true;
        array_unshift($destinations, $lastLocationPrevDay);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Itinerary Map</title>
    <style>
        html, body, #map { height: 100%; margin: 0; padding: 0; }
    </style>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=geometry&callback=initMap" async defer></script>
    <script>
        function initMap() {
            const destinations = <?php echo json_encode($destinations); ?>;
            const validDestinations = destinations.filter(d => d.latitude !== null && d.longitude !== null);
            
            if (validDestinations.length === 0) {
                const map = new google.maps.Map(document.getElementById('map'), {
                    zoom: 7,
                    center: { lat: 7.8731, lng: 80.7718 } // Sri Lanka center
                });
                return;
            }

            const map = new google.maps.Map(document.getElementById('map'));
            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: false
            });

            if (validDestinations.length === 1) {
                const pos = { 
                    lat: parseFloat(validDestinations[0].latitude), 
                    lng: parseFloat(validDestinations[0].longitude) 
                };
                map.setCenter(pos);
                map.setZoom(15);
                new google.maps.Marker({ position: pos, map: map, title: validDestinations[0].location_name });
            } else {
                const origin = { lat: parseFloat(validDestinations[0].latitude), lng: parseFloat(validDestinations[0].longitude) };
                const destination = { lat: parseFloat(validDestinations[validDestinations.length - 1].latitude), lng: parseFloat(validDestinations[validDestinations.length - 1].longitude) };
                
                const waypoints = [];
                for (let i = 1; i < validDestinations.length - 1; i++) {
                    waypoints.push({
                        location: { lat: parseFloat(validDestinations[i].latitude), lng: parseFloat(validDestinations[i].longitude) },
                        stopover: true
                    });
                }

                directionsService.route({
                    origin: origin,
                    destination: destination,
                    waypoints: waypoints,
                    travelMode: google.maps.TravelMode.DRIVING
                }, (response, status) => {
                    if (status === 'OK') {
                        directionsRenderer.setDirections(response);
                        const route = response.routes[0];
                        let totalDist = 0;
                        let totalTime = 0;
                        route.legs.forEach(leg => {
                            totalDist += leg.distance.value;
                            totalTime += leg.duration.value;
                        });
                        
                        window.parent.postMessage({
                            type: 'route_update',
                            distance: (totalDist / 1000).toFixed(1) + ' km',
                            duration: Math.floor(totalTime / 3600) + 'h ' + Math.ceil((totalTime % 3600) / 60) + 'min'
                        }, '*');
                    } else {
                        console.error('Directions request failed due to ' + status);
                    }
                });
            }
        }
    </script>
</head>
<body>
    <div id="map"></div>
</body>
</html>
