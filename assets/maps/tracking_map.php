<?php
require_once('../../includes/config.php');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// 1. Fetch live tracking data
$sql = "SELECT latitude, longitude FROM trip_tracking WHERE booking_id = ? ORDER BY updated_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$tracking = $stmt->get_result()->fetch_assoc();

// 2. Fetch booking for route (pickup/dropoff)
$b_sql = "SELECT pickup_location, dropoff_location, plan_id, status FROM bookings WHERE id = ?";
$b_stmt = $conn->prepare($b_sql);
$b_stmt->bind_param("i", $booking_id);
$b_stmt->execute();
$booking = $b_stmt->get_result()->fetch_assoc();

// 3. Fetch intermediate destinations with status
$destinations = [];
if ($booking && $booking['plan_id']) {
    $d_sql = "SELECT location_name, latitude, longitude, status FROM destinations WHERE plan_id = ? ORDER BY day_number ASC";
    $d_stmt = $conn->prepare($d_sql);
    $d_stmt->bind_param("i", $booking['plan_id']);
    $d_stmt->execute();
    $destinations = $d_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$lat = $tracking['latitude'] ?? null;
$lng = $tracking['longitude'] ?? null;
$apiKey = GOOGLE_MAPS_API_KEY;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Live Tracking & Route Map</title>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $apiKey; ?>&libraries=geometry"></script>
    <style>
        #map { height: 100vh; width: 100%; }
        body { margin: 0; padding: 0; font-family: sans-serif; }
        .info-box { position: absolute; top: 10px; left: 10px; background: white; padding: 12px 16px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 1000; font-size: 13px; border: 1px solid #e5e7eb; }
        .status-dot { display: inline-block; width: 8px; height: 8px; background: #10b981; margin-right: 6px; border-radius: 50%; animation: pulse 2s infinite; }
        .status-dot.inactive { background: #ef4444; animation: none; }
        .status-dot.completed { background: #6b7280; animation: none; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
    </style>
</head>
<body>
    <div class="info-box">
        <?php if ($booking['status'] === 'cancelled'): ?>
            <div style="display: flex; align-items: center;"><span class="status-dot inactive"></span> <b>TripSync</b> &bull; Trip Cancelled</div>
            <div id="distance-info" style="color: #ef4444; font-weight: 600; margin-top: 4px;">Service discontinued</div>
        <?php elseif ($booking['status'] === 'completed'): ?>
            <div style="display: flex; align-items: center;"><span class="status-dot completed"></span> <b>TripSync</b> &bull; Trip Completed</div>
            <div id="distance-info" style="color: #6b7280; font-weight: 600; margin-top: 4px;">Safe arrival confirmed</div>
        <?php else: ?>
            <div style="display: flex; align-items: center;"><span class="status-dot"></span> <b>TripSync Live</b> &bull; Tracking Active</div>
            <div id="distance-info" style="color: #6b7280; font-weight: 600; margin-top: 4px;">Calculating route...</div>
        <?php endif; ?>
    </div>
    <div id="map"></div>
    <script>
        let map, marker, directionsService, directionsRenderer;
        let routePath = [];
        const bookingId = <?php echo $booking_id; ?>;
        let lastPos = { 
            lat: <?php echo $lat ?: '6.9271'; ?>, 
            lng: <?php echo $lng ?: '79.8612'; ?> 
        };
        const bookingStatus = "<?php echo $booking['status']; ?>";
        const pickupLoc = "<?php echo addslashes($booking['pickup_location'] ?? ''); ?>";
        const dropoffLoc = "<?php echo addslashes($booking['dropoff_location'] ?? ''); ?>";
        let destinations = <?php echo json_encode($destinations); ?>;

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 15,
                center: lastPos,
                mapTypeControl: false,
                streetViewControl: false,
                styles: [
                    {"featureType": "poi", "stylers": [{"visibility": "off"}]},
                    {"featureType": "transit", "stylers": [{"visibility": "off"}]}
                ]
            });

            marker = new google.maps.Marker({
                position: lastPos,
                map: map,
                icon: {
                    url: 'https://cdn-icons-png.flaticon.com/512/3202/3202926.png',
                    scaledSize: new google.maps.Size(42, 42),
                    anchor: new google.maps.Point(21, 21)
                }
            });

            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: false,
                polylineOptions: { strokeColor: "#10b981", strokeOpacity: 0.7, strokeWeight: 6 }
            });

            updateMapUI(); 
            startLiveTracking();
        }

        async function updateMapUI(newCoords = null) {
            const currentOrigin = newCoords || lastPos;
            
            // 1. Determine Next Logical Target
            let target = dropoffLoc;
            let targetLabel = "Final Destination";

            if (bookingStatus === 'confirmed' || bookingStatus === 'arrived') {
                target = pickupLoc;
                targetLabel = "Pickup Location";
            } else if (destinations.length > 0) {
                const next = destinations.find(d => d.status !== 'completed');
                if (next) {
                    target = next.location_name;
                    targetLabel = next.location_name;
                }
            }

            // 2. Fetch Directions (Driver's Current Pos -> Next Stop)
            directionsService.route({
                origin: currentOrigin,
                destination: target,
                travelMode: google.maps.TravelMode.DRIVING
            }, (response, status) => {
                if (status === "OK") {
                    directionsRenderer.setDirections(response);
                    const leg = response.routes[0].legs[0];
                    document.getElementById('distance-info').innerText = `${targetLabel}: ${leg.distance.text} (${leg.duration.text})`;
                }
            });
        }

        function startLiveTracking() {
            setInterval(async () => {
                try {
                    const response = await fetch(`../../api/get_tracking_data.php?booking_id=${bookingId}`);
                    const data = await response.json();
                    
                    if (data.success && data.latitude && data.longitude) {
                        let newPos = { lat: parseFloat(data.latitude), lng: parseFloat(data.longitude) };
                        
                        // If position moved, animate and update route
                        if (newPos.lat !== lastPos.lat || newPos.lng !== lastPos.lng) {
                            animateMarker(marker, lastPos, newPos, 2000);
                            lastPos = newPos;
                            map.panTo(newPos);
                            
                            // Re-calculate route from new position
                            destinations = data.destinations;
                            updateMapUI(newPos);
                        }
                    }
                } catch (e) { console.error(e); }
            }, 5000);
        }

        function animateMarker(marker, fromPos, toPos, duration) {
            const start = performance.now();
            function step(timestamp) {
                const elapsed = timestamp - start;
                const t = Math.min(elapsed / duration, 1);
                const lat = fromPos.lat + (toPos.lat - fromPos.lat) * t;
                const lng = fromPos.lng + (toPos.lng - fromPos.lng) * t;
                marker.setPosition(new google.maps.LatLng(lat, lng));
                if (t < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }

        window.onload = initMap;
    </script>
</body>
</html>
