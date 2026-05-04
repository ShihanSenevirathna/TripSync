<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
checkAuth('customer');

$userId = $_SESSION['user_id'];
$planId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

if (!$planId) {
  header("Location: plan_trip.php");
  exit();
}

// Verify plan ownership
$stmt = $conn->prepare("SELECT * FROM travel_plans WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $planId, $userId);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
  header("Location: dashboard.php");
  exit();
}

// Calculate number of days
$start = new DateTime($plan['start_date']);
$end = new DateTime($plan['end_date']);
$interval = $start->diff($end);
$totalDays = $interval->days + 1;

$currentDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
if ($currentDay < 1)
  $currentDay = 1;

// Fetch destinations for current day
$destStmt = $conn->prepare("SELECT * FROM destinations WHERE plan_id = ? AND day_number = ? ORDER BY arrival_time ASC");
$destStmt->bind_param("ii", $planId, $currentDay);
$destStmt->execute();
$destinations = $destStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$lastStop = !empty($destinations) ? end($destinations) : null;
if (!$lastStop && $currentDay > 1) {
  $prevStmt = $conn->prepare("SELECT * FROM destinations WHERE plan_id = ? AND day_number = ? ORDER BY arrival_time DESC LIMIT 1");
  $prevDay = $currentDay - 1;
  $prevStmt->bind_param("ii", $planId, $prevDay);
  $prevStmt->execute();
  $lastStop = $prevStmt->get_result()->fetch_assoc();
}

// Fetch all destinations for summary
$summaryStmt = $conn->prepare("SELECT COUNT(*) as total_dest FROM destinations WHERE plan_id = ?");
$summaryStmt->bind_param("i", $planId);
$summaryStmt->execute();
$summaryData = $summaryStmt->get_result()->fetch_assoc();
$totalDestinations = $summaryData['total_dest'];

// Calculate the actual calendar date of the current day being viewed
$currentDayDate = (clone $start)->modify('+' . ($currentDay - 1) . ' days');
$currentDayDateStr = $currentDayDate->format('Y-m-d');
$nextDayDateStr = (clone $currentDayDate)->modify('+1 day')->format('Y-m-d');
$tripEndDateStr = $end->format('Y-m-d');
$travelers = (int)($plan['travelers'] ?? 1);

// Fetch ALL bookings linked to this plan (hotel & vehicle)
$planBookingsStmt = $conn->prepare("
    SELECT b.*,
           h.name as hotel_name, h.location as hotel_location, h.image_path as hotel_img, h.stars as hotel_stars,
           v.model as vehicle_model, v.type as vehicle_type, v.image_path as vehicle_img, v.capacity as vehicle_capacity
    FROM bookings b
    LEFT JOIN hotels h ON b.type = 'hotel' AND b.item_id = h.id
    LEFT JOIN vehicles v ON b.type = 'vehicle' AND b.item_id = v.id
    WHERE b.plan_id = ? AND b.status IN ('pending','confirmed','completed')
    ORDER BY b.start_date ASC, b.created_at DESC
");
$planBookingsStmt->bind_param("i", $planId);
$planBookingsStmt->execute();
$planBookings = $planBookingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Find the hotel and vehicle relevant to the CURRENT DAY being viewed.
// Hotel: check-in date <= current day AND check-out date > current day (covers this night).
// Vehicle: rental start <= current day AND rental end >= current day (covers this day of travel).
$bookedHotel = null;
$bookedVehicle = null;
$fallbackHotel = null; // Most recent hotel if no date match
$fallbackVehicle = null;

foreach ($planBookings as $pb) {
  $bookStart = date('Y-m-d', strtotime($pb['start_date']));
  $bookEnd = date('Y-m-d', strtotime($pb['end_date']));

  if ($pb['type'] === 'hotel') {
    // Save the first hotel as fallback (closest to trip start)
    if (!$fallbackHotel)
      $fallbackHotel = $pb;

    // Perfect match: check-in on or before current day AND check-out after current day
    if ($bookStart <= $currentDayDateStr && $bookEnd > $currentDayDateStr) {
      if (!$bookedHotel) {
        // Resolve Google hotel name if needed
        if (empty($pb['hotel_name']) && strpos($pb['item_id'], 'google_') === 0) {
          if (!class_exists('GooglePlacesAPI'))
            require_once '../api/google_places_api.php';
          $googleApi = new GooglePlacesAPI();
          $place_id = str_replace('google_', '', $pb['item_id']);
          $details = $googleApi->getDetailsByPlaceId($place_id);
          if ($details) {
            $pb['hotel_name'] = $details['name'];
            $pb['hotel_location'] = $details['address'];
            $pb['hotel_img'] = !empty($details['photos']) ? $details['photos'][0] : '';
          }
        }
        $bookedHotel = $pb;
      }
    }
  }

  if ($pb['type'] === 'vehicle') {
    // For vehicles, we show the same booking for all days (trip-level)
    if (!$bookedVehicle) {
      $bookedVehicle = $pb;
    }
  }
}

// If no exact day-match found, show the nearest upcoming or most recent booking
if (!$bookedHotel && $fallbackHotel) {
  $fb = $fallbackHotel;
  if (empty($fb['hotel_name']) && strpos($fb['item_id'], 'google_') === 0) {
    if (!class_exists('GooglePlacesAPI'))
      require_once '../api/google_places_api.php';
    $googleApi = new GooglePlacesAPI();
    $place_id = str_replace('google_', '', $fb['item_id']);
    $details = $googleApi->getDetailsByPlaceId($place_id);
    if ($details) {
      $fb['hotel_name'] = $details['name'];
      $fb['hotel_location'] = $details['address'];
      $fb['hotel_img'] = !empty($details['photos']) ? $details['photos'][0] : '';
    }
    $fallbackHotel = $fb;
  }
  // Only show fallback if it's the ONLY hotel (single hotel for whole trip)
  // If there are multiple hotel bookings for different days, don't show wrong one
  $hotelCount = count(array_filter($planBookings, fn($b) => $b['type'] === 'hotel'));
  if ($hotelCount === 1) {
    $bookedHotel = $fallbackHotel;
  }
}
if (!$bookedVehicle && $fallbackVehicle) {
  $vehicleCount = count(array_filter($planBookings, fn($b) => $b['type'] === 'vehicle'));
  if ($vehicleCount === 1) {
    $bookedVehicle = $fallbackVehicle;
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Itinerary Builder - TripSync</title>
  <meta name="description" content="Build your custom itinerary for Sri Lanka.">
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <script type="module" src="../assets/js/main.js"></script>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>

<body>
  <div id="root">
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-teal-50">
      <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center h-20"><a class="flex items-center gap-3" href="../index.php"
              data-discover="true"><img alt="TripSync Logo" class="h-12 w-auto" src="../assets/images/logo.png"></a>
            <div class="hidden md:flex items-center gap-8"><a
                class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                href="../index.php" data-discover="true">Home</a><a
                class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                href="dashboard.php" data-discover="true">Dashboard</a><a
                class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                href="plan_trip.php" data-discover="true">Plan Trip</a><a
                class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                href="marketplace.php" data-discover="true">Marketplace</a><a
                class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                href="wallet.php" data-discover="true">Wallet</a><a
                class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                href="reviews.php" data-discover="true">Reviews</a><a
                class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                href="trip_history.php" data-discover="true">Trip History</a><a
                class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                href="help.php" data-discover="true">Help</a>
              <div class="flex items-center gap-2"><a
                  class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-gray-600 hover:bg-gray-100"
                  href="notifications.php" data-discover="true"><i class="ri-notification-3-line text-lg"></i><span
                    class="absolute top-1 right-1 w-2 h-2 bg-rose-500 rounded-full"></span></a><a
                  class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-gray-600 hover:bg-gray-100"
                  href="profile.php" data-discover="true"><i class="ri-user-line text-lg"></i></a></div>
            </div><button class="md:hidden w-8 h-8 flex items-center justify-center cursor-pointer text-gray-700"><i
                class="ri-menu-line text-2xl"></i></button>
          </div>
        </div>
      </nav>
      <div class="pt-24 pb-16 px-4">
        <div class="max-w-7xl mx-auto">
          <div class="flex items-center justify-center mb-12">
            <div class="flex items-center gap-4">
              <div class="flex items-center">
                <div
                  class="w-10 h-10 flex items-center justify-center rounded-full font-semibold transition-all bg-teal-600 text-white">
                  1</div>
                <div class="w-16 h-1 mx-2 bg-teal-600"></div>
              </div>
              <div class="flex items-center">
                <div
                  class="w-10 h-10 flex items-center justify-center rounded-full font-semibold transition-all bg-teal-600 text-white">
                  2</div>
                <div class="w-16 h-1 mx-2 bg-teal-600"></div>
              </div>
              <div class="flex items-center">
                <div
                  class="w-10 h-10 flex items-center justify-center rounded-full font-semibold transition-all bg-teal-600 text-white">
                  3</div>
              </div>
            </div>
          </div>
          <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo clean($plan['name']); ?></h1>
                <p class="text-gray-600"><i class="ri-calendar-line mr-2"></i><?php echo $plan['start_date']; ?> to <?php echo $plan['end_date']; ?></p>
              </div>
              <div class="flex gap-3">
                <a href="plan_trip_step2.php?plan_id=<?php echo $planId; ?>"
                  class="px-6 py-3 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap cursor-pointer flex items-center gap-2">Back</a>
                <button
                  class="px-6 py-3 bg-orange-500 text-white text-sm font-medium rounded-lg hover:bg-orange-600 transition-colors whitespace-nowrap cursor-pointer flex items-center gap-2"><i
                    class="ri-route-line"></i>Smart Route Optimizer</button><button onclick="saveItinerary()"
                  class="px-6 py-3 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer">Save
                  Itinerary</button>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
              <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
                  <?php for ($i = 1; $i <= $totalDays; $i++): ?>
                  <a href="?plan_id=<?php echo $planId; ?>&day=<?php echo $i; ?>"
                    class="px-6 py-3 rounded-full text-sm font-medium whitespace-nowrap transition-all cursor-pointer <?php echo($currentDay == $i) ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    Day <?php echo $i; ?>
                  </a>
                  <?php
endfor; ?>
                </div>
                <div class="space-y-4" id="destinations-list">
                  <?php if (empty($destinations)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                      <?php if ($currentDay == 1): ?>
                        <div class="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                          <i class="ri-map-pin-user-line text-3xl text-teal-600"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Set Your Starting Point</h3>
                        <p class="text-gray-500 mb-6 max-w-sm mx-auto">Where are you beginning your journey? This will be used to calculate travel times for all next stops.</p>
                        <button onclick="openAddModal(0)" class="px-6 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-all font-medium">
                          Set Start Location
                        </button>
                      <?php
  else: ?>
                        <p class="text-gray-500">No destinations added for Day <?php echo $currentDay; ?> yet.</p>
                      <?php
  endif; ?>
                    </div>
                  <?php
else: ?>
                    <?php foreach ($destinations as $index => $dest): ?>
                    <div class="relative">
                      <?php if ($index < count($destinations) - 1): ?>
                      <div class="absolute left-6 top-14 w-0.5 h-16 bg-gray-300"></div>
                      <?php
    endif; ?>
                      <div class="flex gap-4 items-start group">
                        <div class="w-12 h-12 flex items-center justify-center bg-teal-500 rounded-full flex-shrink-0 shadow-md">
                          <i class="ri-map-pin-user-fill text-xl text-white"></i>
                        </div>
                        <div class="flex-1 bg-gray-50 p-4 rounded-xl border border-gray-200 group-hover:border-teal-500 group-hover:shadow-md transition-all">
                          <div class="flex items-start justify-between mb-2">
                            <div>
                              <h3 class="font-semibold text-gray-900 mb-1"><?php echo clean($dest['location_name']); ?></h3>
                              <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                                <?php if ($index == 0 && $currentDay == 1): ?>
                                  <p class="text-sm text-gray-600">
                                    <i class="ri-play-line mr-1 text-teal-600"></i>
                                    <span class="font-medium text-gray-900">Trip Start Time:</span> <?php echo date('h:i A', strtotime($dest['departure_time'] ?: $dest['arrival_time'])); ?>
                                  </p>
                                <?php
    else: ?>
                                  <p class="text-sm text-gray-600">
                                    <i class="ri-share-forward-line mr-1 text-teal-600"></i>
                                    <span class="font-medium text-gray-900">Journey Start:</span> <?php echo $dest['departure_time'] ? date('h:i A', strtotime($dest['departure_time'])) : '--:--'; ?>
                                  </p>
                                  <p class="text-sm text-gray-600">
                                    <i class="ri-map-pin-line mr-1 text-teal-600"></i>
                                    <span class="font-medium text-gray-900">Reach Dest:</span> <?php echo date('h:i A', strtotime($dest['arrival_time'])); ?>
                                  </p>
                                <?php
    endif; ?>
                              </div>
                              <?php if ($dest['notes']): ?>
                                <p class="text-xs text-gray-500 mt-2 italic flex items-center gap-1">
                                  <i class="ri-sticky-note-line"></i> <?php echo clean($dest['notes']); ?>
                                </p>
                              <?php
    endif; ?>
                            </div>
                            <div class="flex gap-2">
                              <button onclick="editDestination(<?php echo htmlspecialchars(json_encode($dest)); ?>)"
                                class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors cursor-pointer">
                                <i class="ri-edit-line"></i>
                              </button>
                              <button onclick="deleteDestination(<?php echo $dest['id']; ?>)"
                                class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer">
                                <i class="ri-delete-bin-line"></i>
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php
  endforeach; ?>
                  <?php
endif; ?>
                </div>
                <button onclick="openAddModal(<?php echo count($destinations); ?>)"
                  data-last-lat="<?php echo $lastStop ? $lastStop['latitude'] : ''; ?>"
                  data-last-lng="<?php echo $lastStop ? $lastStop['longitude'] : ''; ?>"
                  data-last-time="<?php echo $lastStop ? ($lastStop['arrival_time'] ?: $lastStop['departure_time']) : '08:00'; ?>"
                  id="addDestBtn"
                  class="w-full mt-6 px-6 py-3 border-2 border-dashed border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:border-teal-500 hover:text-teal-600 transition-colors whitespace-nowrap cursor-pointer">
                  <i class="ri-add-circle-line mr-2"></i>Add Destination
                </button>
              </div>
              <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Trip Summary</h2>
                <div class="grid grid-cols-3 gap-4 mb-5">
                  <div class="text-center p-4 bg-teal-50 rounded-xl">
                    <div class="w-12 h-12 flex items-center justify-center bg-teal-600 rounded-full mx-auto mb-2"><i
                        class="ri-map-pin-line text-2xl text-white"></i></div>
                    <p class="text-2xl font-bold text-teal-700"><?php echo $totalDestinations; ?></p>
                    <p class="text-xs text-gray-600">Destinations</p>
                  </div>
                  <div class="text-center p-4 bg-orange-50 rounded-xl">
                    <div class="w-12 h-12 flex items-center justify-center bg-orange-600 rounded-full mx-auto mb-2"><i
                        class="ri-time-line text-2xl text-white"></i></div>
                    <p class="text-2xl font-bold text-orange-700"><?php echo $totalDays; ?></p>
                    <p class="text-xs text-gray-600">Days</p>
                  </div>
                  <div class="text-center p-4 bg-indigo-50 rounded-xl">
                    <div class="w-12 h-12 flex items-center justify-center bg-indigo-600 rounded-full mx-auto mb-2"><i
                        class="ri-road-map-line text-2xl text-white"></i></div>
                    <p class="text-2xl font-bold text-indigo-700">245</p>
                    <p class="text-xs text-gray-600">Total KM</p>
                  </div>
                </div>

                <!-- Booked Hotel Card -->
                <?php
// Compute last destination of current day for marketplace near-search
$lastDayDest = !empty($destinations) ? end($destinations)['location_name'] : ($lastStop['location_name'] ?? '');
?>
                <div class="mb-3">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-1.5"><i class="ri-hotel-bed-line text-teal-500"></i> Accommodation</p>
                  <?php if ($bookedHotel): ?>
                  <a href="booking_confirmation.php?id=<?php echo $bookedHotel['id']; ?>" class="flex items-center gap-3 p-3 bg-teal-50 border border-teal-100 rounded-xl hover:border-teal-400 transition-all group">
                    <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0 bg-gray-100 border border-teal-100">
                      <?php
  $hImg = $bookedHotel['hotel_img'] ?? '';
  if (!empty($hImg) && strpos($hImg, 'http') === false) {
    $hImg = '../assets/images/' . $hImg;
  }
  if (empty($hImg))
    $hImg = '../assets/images/hotel_details/main.jpg';
?>
                      <img src="<?php echo htmlspecialchars($hImg); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" onerror="this.src='../assets/images/hotel_details/main.jpg'">
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($bookedHotel['hotel_name'] ?? 'Hotel Booking'); ?></p>
                      <?php if (!empty($bookedHotel['hotel_location'])): ?>
                      <p class="text-xs text-gray-500 truncate flex items-center gap-1"><i class="ri-map-pin-line text-teal-400"></i><?php echo htmlspecialchars($bookedHotel['hotel_location']); ?></p>
                      <?php
  endif; ?>
                      <p class="text-xs text-gray-500 mt-0.5"><?php echo date('M d', strtotime($bookedHotel['start_date'])); ?> &rarr; <?php echo date('M d', strtotime($bookedHotel['end_date'])); ?></p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                      <?php
  $hStatusClasses = ['pending' => 'bg-amber-50 text-amber-700 border-amber-200', 'confirmed' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'completed' => 'bg-gray-100 text-gray-600 border-gray-200'];
  $hStatusClass = $hStatusClasses[$bookedHotel['status']] ?? 'bg-gray-100 text-gray-600 border-gray-200';
?>
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border <?php echo $hStatusClass; ?>"><?php echo ucfirst($bookedHotel['status']); ?></span>
                    </div>
                  </a>
                  <?php
else: ?>
                  <a href="marketplace.php?tab=hotels&plan_id=<?php echo $planId; ?>&checkin=<?php echo $currentDayDateStr; ?>&checkout=<?php echo $nextDayDateStr; ?>&guests=<?php echo $travelers; ?>&near=<?php echo urlencode($lastDayDest); ?>" class="flex items-center gap-3 p-3 bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl hover:border-teal-400 hover:bg-teal-50/50 transition-all group">
                    <div class="w-14 h-14 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                      <i class="ri-hotel-bed-line text-2xl text-gray-300 group-hover:text-teal-500 transition-colors"></i>
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-gray-400 group-hover:text-teal-600 transition-colors">No Hotel for Day <?php echo $currentDay; ?></p>
                      <p class="text-xs text-gray-400">Tap to browse &amp; book for this night</p>
                    </div>
                    <i class="ri-arrow-right-line ml-auto text-gray-300 group-hover:text-teal-500 transition-colors"></i>
                  </a>
                  <?php
endif; ?>
                </div>

                <!-- Booked Vehicle Card -->
                <div>
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-1.5"><i class="ri-car-line text-orange-500"></i> Vehicle</p>
                  <?php if ($bookedVehicle): ?>
                  <a href="booking_confirmation.php?id=<?php echo $bookedVehicle['id']; ?>" class="flex items-center gap-3 p-3 bg-orange-50 border border-orange-100 rounded-xl hover:border-orange-400 transition-all group">
                    <div class="w-14 h-14 rounded-lg overflow-hidden flex-shrink-0 bg-gray-100 border border-orange-100">
                      <?php
  $vImg = $bookedVehicle['vehicle_img'] ?? '';
  if (!empty($vImg)) {
    $vImg = '../assets/images/' . $vImg;
  }
  else {
    $vImg = '../assets/images/vehicle_kdh_van.jpg';
  }
?>
                      <img src="<?php echo htmlspecialchars($vImg); ?>" class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-300" onerror="this.src='../assets/images/vehicle_kdh_van.jpg'">
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($bookedVehicle['vehicle_model'] ?? 'Vehicle Booking'); ?></p>
                      <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($bookedVehicle['vehicle_type'] ?? ''); ?> &bull; <?php echo htmlspecialchars($bookedVehicle['vehicle_capacity'] ?? ''); ?> passengers</p>
                      <p class="text-xs text-gray-500 mt-0.5"><?php echo date('M d', strtotime($bookedVehicle['start_date'])); ?> &rarr; <?php echo date('M d', strtotime($bookedVehicle['end_date'])); ?></p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                      <?php
  $vStatusClasses = ['pending' => 'bg-amber-50 text-amber-700 border-amber-200', 'confirmed' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'completed' => 'bg-gray-100 text-gray-600 border-gray-200'];
  $vStatusClass = $vStatusClasses[$bookedVehicle['status']] ?? 'bg-gray-100 text-gray-600 border-gray-200';
?>
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border <?php echo $vStatusClass; ?>"><?php echo ucfirst($bookedVehicle['status']); ?></span>
                    </div>
                  </a>
                  <?php
else: ?>
                  <a href="marketplace.php?tab=vehicles&plan_id=<?php echo $planId; ?>&checkin=<?php echo $plan['start_date']; ?>&checkout=<?php echo $plan['end_date']; ?>" class="flex items-center gap-3 p-3 bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl hover:border-orange-400 hover:bg-orange-50/50 transition-all group">
                    <div class="w-14 h-14 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                      <i class="ri-car-line text-2xl text-gray-300 group-hover:text-orange-500 transition-colors"></i>
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-gray-400 group-hover:text-orange-600 transition-colors">No Vehicle for Trip</p>
                      <p class="text-xs text-gray-400">Book one vehicle for your entire journey</p>
                    </div>
                    <i class="ri-arrow-right-line ml-auto text-gray-300 group-hover:text-orange-500 transition-colors"></i>
                  </a>
                  <?php
endif; ?>
                </div>

              </div>
            </div>
            <div class="space-y-6">
              <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200">
                <div class="p-4 border-b border-gray-200">
                  <h2 class="text-lg font-bold text-gray-900">Route Preview</h2>
                  <p class="text-sm text-gray-600">Real-time map visualization</p>
                </div>
                <div class="relative w-full h-96"><iframe src="../assets/maps/itinerary_map.php?plan_id=<?php echo $planId; ?>&day=<?php echo $currentDay; ?>" width="100%"
                    height="100%" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    title="Route Map" style="border: 0px;"></iframe></div>
                <div class="p-4 bg-gray-50">
                  <div class="flex items-center justify-between text-sm">
                  <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="p-3 bg-white rounded-xl border border-gray-100">
                      <p class="text-gray-500 mb-1">Day Distance</p>
                      <p class="font-bold text-gray-900" id="total-distance">Calculating...</p>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-teal-100">
                      <p class="text-teal-600 mb-1">Full Trip Dist.</p>
                      <p class="font-bold text-teal-700" id="full-trip-distance">Calculating...</p>
                    </div>
                    <div class="col-span-2 p-3 bg-white rounded-xl border border-gray-100 flex items-center justify-between">
                      <p class="text-gray-500">Day Travel Time</p>
                      <p class="font-bold text-gray-900" id="travel-time">Calculating...</p>
                    </div>
                  </div>
                  </div>
                </div>
              </div>
              <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                <h2 class="text-lg font-bold text-gray-900 mb-4 tracking-tight flex items-center gap-2">
                    <i class="ri-hotel-bed-line text-teal-600"></i> Contextual Bookings
                </h2>
                <?php
$targetLocation = !empty($destinations) ? end($destinations)['location_name'] : '';
?>
                <p class="text-xs text-gray-500 mb-5 leading-relaxed" id="booking-context-text">
                    Best places to stay near your final stop <b><?php echo !empty($targetLocation) ? clean($targetLocation) : 'of the day'; ?></b>.
                </p>
                <div class="space-y-3" id="hotel-list">
                  <!-- Skeleton Loader -->
                  <div class="animate-pulse space-y-3">
                    <div class="h-24 bg-gray-50 rounded-xl border border-gray-100 flex gap-3 p-4">
                        <div class="w-16 h-16 bg-gray-200 rounded-lg"></div>
                        <div class="flex-1 space-y-2">
                          <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                          <div class="h-2 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                  </div>
                </div>
                <a href="marketplace.php?plan_id=<?php echo $planId; ?>"
                  class="block w-full text-center mt-6 px-4 py-3 bg-teal-50 text-teal-700 text-xs font-bold rounded-xl hover:bg-teal-100 transition-all cursor-pointer border border-teal-100/50">
                  Explore More in Marketplace
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Add/Edit Destination Modal -->
      <div id="destModal" class="fixed inset-0 z-[100] hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center">
          <div class="fixed inset-0 transition-opacity bg-black/60 backdrop-blur-sm" onclick="closeModal()"></div>
          
          <div class="relative inline-block w-full max-w-lg p-0 overflow-hidden text-left align-middle transition-all transform bg-white rounded-2xl shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
              <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Add Destination</h3>
              <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ri-close-line text-2xl"></i>
              </button>
            </div>
            <form id="destForm" class="p-6 space-y-4">
              <input type="hidden" name="action" id="formAction" value="add">
              <input type="hidden" name="day_number" value="<?php echo $currentDay; ?>">
              <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">
              <input type="hidden" name="id" id="destId">
              
              <div>
                <div class="flex justify-between items-center mb-1">
                  <label class="block text-sm font-medium text-gray-700">Location Name</label>
                  <button type="button" onclick="useCurrentLocation()" class="text-xs text-teal-600 hover:text-teal-700 font-medium">
                    <i class="ri-gps-line mr-1"></i>Use Current Location
                  </button>
                </div>
                <input type="text" name="location_name" required id="locInput"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none transition-all">
                <input type="hidden" name="latitude" id="latInput">
                <input type="hidden" name="longitude" id="lngInput">
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div id="departureField">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Journey Start (Leave previous)</label>
                  <input type="time" name="departure_time" id="departureTimeInput"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none transition-all">
                </div>
                <div id="arrivalField">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Arrival Time (Reach destination)</label>
                  <input type="time" name="arrival_time" required id="timeInput"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none transition-all">
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="notesInput" rows="3"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none transition-all"
                  placeholder="e.g., Sightseeing, lunch break..."></textarea>
              </div>
              
              <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeModal()"
                  class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                  Cancel
                </button>
                <button type="submit"
                  class="flex-1 px-4 py-2 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 transition-colors shadow-sm">
                  Save Destination
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <script>
        let autocomplete;
        let distanceService;

        function initAutocomplete() {
          const input = document.getElementById('locInput');
          if (!input || typeof google === 'undefined' || !google.maps || !google.maps.places) return;

          distanceService = new google.maps.DistanceMatrixService();
          autocomplete = new google.maps.places.Autocomplete(input, {
              componentRestrictions: { country: 'lk' },
              fields: ['geometry', 'name', 'formatted_address']
          });

          autocomplete.addListener('place_changed', function() {
              const place = autocomplete.getPlace();
              if (!place || !place.geometry || !place.geometry.location) {
                  console.warn("Selected place has no geometry/location data");
                  return;
              }
              
              const lat = place.geometry.location.lat();
              const lng = place.geometry.location.lng();
              document.getElementById('latInput').value = lat;
              document.getElementById('lngInput').value = lng;
              
              console.log("Place selected:", place.name, {lat, lng});
              handleTimeAutoCalc(lat, lng);
          });
        }

        function handleTimeAutoCalc(lat, lng) {
            const departInput = document.getElementById('departureTimeInput');
            const arriveInput = document.getElementById('timeInput');
            
            if (!lat || !lng || !departInput.value || document.getElementById('formAction').value !== 'add') return;

            const btn = document.getElementById('addDestBtn');
            const lastLat = parseFloat(btn.dataset.lastLat);
            const lastLng = parseFloat(btn.dataset.lastLng);

            // If no previous stop, just set Arrival = Departure for now
            if (isNaN(lastLat) || isNaN(lastLng)) {
                arriveInput.value = departInput.value;
                return;
            }

            arriveInput.style.border = '2px solid #0d9488'; // Teal border to show calculating
            
            if (!distanceService && typeof google !== 'undefined') distanceService = new google.maps.DistanceMatrixService();
            if (!distanceService) return;

            console.log("Requesting travel duration...", {from: {lastLat, lastLng}, to: {lat, lng}});

            distanceService.getDistanceMatrix({
                origins: [new google.maps.LatLng(lastLat, lastLng)],
                destinations: [new google.maps.LatLng(parseFloat(lat), parseFloat(lng))],
                travelMode: google.maps.TravelMode.DRIVING,
            }, (response, status) => {
                arriveInput.style.border = '';
                if (status === 'OK' && response.rows[0].elements[0].status === 'OK') {
                    const durationSec = response.rows[0].elements[0].duration.value;
                    const timeParts = departInput.value.split(':');
                    const baseTime = new Date();
                    baseTime.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]), 0);
                    baseTime.setSeconds(baseTime.getSeconds() + durationSec);
                    
                    const pad = (n) => n.toString().padStart(2, '0');
                    const newArrival = `${pad(baseTime.getHours())}:${pad(baseTime.getMinutes())}`;
                    arriveInput.value = newArrival;
                    console.log("Arrival updated:", newArrival);
                } else {
                    console.warn("Distance Matrix calculation failed. Status:", status, response);
                    // Fallback: set arrival to departure if calc fails
                    if (!arriveInput.value) arriveInput.value = departInput.value;
                }
            });
        }

        function editDestination(dest) {
            document.getElementById('modalTitle').innerText = 'Edit Destination';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('destId').value = dest.id;
            document.getElementById('locInput').value = dest.location_name;
            document.getElementById('latInput').value = dest.latitude;
            document.getElementById('lngInput').value = dest.longitude;
            document.getElementById('notesInput').value = dest.notes || '';
            
            // Format times (HH:MM)
            if (dest.arrival_time) document.getElementById('timeInput').value = dest.arrival_time.substring(0, 5);
            if (dest.departure_time) document.getElementById('departureTimeInput').value = dest.departure_time.substring(0, 5);
            
            // Show all fields during edit (even if it was a start point)
            document.getElementById('arrivalField').classList.remove('hidden');
            document.getElementById('departureField').classList.remove('col-span-2');
            document.getElementById('timeInput').required = true;

            document.getElementById('destModal').classList.remove('hidden');
            if (!autocomplete) initAutocomplete();
        }

        function useCurrentLocation() {
            if (!navigator.geolocation) {
                alert("Geolocation is not supported by your browser");
                return;
            }
            
            const locInput = document.getElementById('locInput');
            locInput.value = "Detecting location...";
            locInput.disabled = true;

            navigator.geolocation.getCurrentPosition((position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                document.getElementById('latInput').value = lat;
                document.getElementById('lngInput').value = lng;
                
                // Get address using Geocoder
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                    locInput.disabled = false;
                    if (status === "OK" && results[0]) {
                        locInput.value = results[0].formatted_address;
                        handleTimeAutoCalc(lat, lng);
                    } else {
                        locInput.value = `Location (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                    }
                });
            }, (error) => {
                locInput.disabled = false;
                locInput.value = "";
                alert("Error getting location: " + error.message);
            });
        }

        function openAddModal(index) {
          const btn = document.getElementById('addDestBtn');
          const isStart = index === 0 && <?php echo $currentDay; ?> == 1;
          
          document.getElementById('modalTitle').innerText = isStart ? 'Set Starting Point' : 'Add Destination';
          document.getElementById('formAction').value = 'add';
          document.getElementById('destForm').reset();
          document.getElementById('latInput').value = '';
          document.getElementById('lngInput').value = '';
          document.getElementById('destModal').classList.remove('hidden');
          
          // Show/Hide Fields for Start Point
          const arrivalField = document.getElementById('arrivalField');
          const departureField = document.getElementById('departureField');
          
          if (isStart) {
              arrivalField.classList.add('hidden');
              departureField.classList.remove('grid-cols-2');
              departureField.classList.add('col-span-2');
              document.getElementById('timeInput').required = false;
          } else {
              arrivalField.classList.remove('hidden');
              departureField.classList.remove('col-span-2');
              document.getElementById('timeInput').required = true;
          }

          // Pre-fill Departure Time (Start of this journey)
          if (index === 0) {
              // First stop of ANY day should start fresh in the morning
              document.getElementById('departureTimeInput').value = "08:00";
          } else if (btn.dataset.lastTime) {
              const lastTime = btn.dataset.lastTime.substring(0, 5);
              const parts = lastTime.split(':');
              const baseTime = new Date();
              baseTime.setHours(parseInt(parts[0]), parseInt(parts[1]), 0);
              
              // Add 1h stay duration from previous destination
              baseTime.setHours(baseTime.getHours() + 1);
              
              const pad = (n) => n.toString().padStart(2, '0');
              document.getElementById('departureTimeInput').value = `${pad(baseTime.getHours())}:${pad(baseTime.getMinutes())}`;
          } else {
              document.getElementById('departureTimeInput').value = "08:00";
          }
          
          if (!autocomplete) initAutocomplete();
        }

        function closeModal() {
          document.getElementById('destModal').classList.add('hidden');
        }

        document.getElementById('destForm').addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          const data = Object.fromEntries(formData.entries());
          
          fetch('../api/itinerary.php?action=' + document.getElementById('formAction').value, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          })
          .then(res => res.json())
          .then(res => {
            if(res.success) {
                // If it was a start point, ensure arrival_time = departure_time
                location.reload();
            }
            else alert(res.message || 'Something went wrong');
          })
          .catch(err => alert('Error: ' + err.message));
        });

        function deleteDestination(id) {
          if(!confirm('Are you sure you want to remove this destination?')) return;
          fetch('../api/itinerary.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
          })
          .then(res => res.json())
          .then(res => res.success ? location.reload() : alert(res.message))
          .catch(err => alert('Error: ' + err.message));
        }
        
        document.getElementById('departureTimeInput').addEventListener('change', function() {
            const lat = document.getElementById('latInput').value;
            const lng = document.getElementById('lngInput').value;
            console.log("Departure changed, recalculating Arrival...", {lat, lng, depart: this.value});
            if (lat && lng && this.value) {
                handleTimeAutoCalc(parseFloat(lat), parseFloat(lng));
            }
        });

        document.getElementById('timeInput').addEventListener('change', function() {
            // No longer auto-chaining Departure inside the same modal
            // User manually sets when they reached this destination.
        });

        <?php if (!empty($destinations)):
  $lastDest = end($destinations);
?>
        const targetLocation = "<?php echo addslashes($lastDest['location_name']); ?>";
        fetch(`../api/hotel-search.php?location=${encodeURIComponent(targetLocation)}`)
          .then(res => res.json())
          .then(data => {
            const list = document.getElementById('hotel-list');
            list.innerHTML = '';
            // Limit to Top 3 results for a cleaner layout
            if (data.success && data.hotels.length > 0) {
              data.hotels.slice(0, 3).forEach(hotel => {
                const priceFormatted = hotel.price_per_night ? `LKR ${hotel.price_per_night.toLocaleString()}` : 'Check Price';
                const hotelImg = hotel.image_path || '../assets/images/hotel_details/main.jpg';
                list.innerHTML += `
                  <a href="hotel_details.php?id=${hotel.id}&plan_id=<?php echo $planId; ?>&checkin=<?php echo $currentDayDateStr; ?>&checkout=<?php echo $nextDayDateStr; ?>&guests=<?php echo $travelers; ?>" class="block group relative overflow-hidden bg-white border border-gray-100 rounded-2xl hover:border-teal-500 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-center gap-4 p-3">
                      <div class="w-20 h-20 bg-gray-50 flex-shrink-0 rounded-xl overflow-hidden relative border border-gray-100/50">
                        <img src="${hotelImg}" 
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" 
                             onerror="this.src='../assets/images/hotel_details/main.jpg'">
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="font-bold text-sm text-gray-900 truncate pr-2">${hotel.name}</h3>
                            <div class="flex items-center gap-0.5 bg-amber-50 px-1.5 py-0.5 rounded-lg">
                                <i class="ri-star-fill text-amber-500 text-[10px]"></i>
                                <span class="text-[10px] font-bold text-amber-700">${hotel.stars}</span>
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-500 mb-2 flex items-center gap-1 truncate">
                            <i class="ri-map-pin-line text-teal-500"></i> ${hotel.location}
                        </p>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xs font-bold text-teal-600">${priceFormatted}</span>
                            <span class="text-[10px] text-gray-400 font-normal">/night</span>
                        </div>
                      </div>
                    </div>
                  </a>
                `;
              });
            } else {
              list.innerHTML = '<div class="text-center py-8 bg-gray-50 rounded-2xl border border-dashed border-gray-200"><p class="text-xs text-gray-500">No premium hotels found in this area.</p></div>';
            }
          })
          .catch(() => {});
        <?php
else: ?>
        document.getElementById('hotel-list').innerHTML = '<p class="text-xs text-gray-500 text-center py-4">Add a destination to see hotel suggestions.</p>';
        <?php
endif; ?>
      </script>
      <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places&callback=initAutocomplete" async defer></script>
      <script>
        // Save Itinerary (Confirm Status)
        function saveItinerary() {
          if (!confirm('Are you sure you want to finalize and save this itinerary?')) return;
          
          fetch('../api/itinerary.php?action=confirm', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: <?php echo $planId; ?> })
          })
          .then(res => res.json())
          .then(res => {
            if(res.success) {
              alert('Itinerary saved and confirmed successfully!');
              window.location.href = 'dashboard.php';
            } else {
              alert(res.message || 'Failed to save itinerary');
            }
          })
          .catch(err => alert('Error saving itinerary: ' + err.message));
        }

        // Listen for updates from the map iframe (Day route)
        window.addEventListener('message', function(event) {
          if (event.data.type === 'route_update') {
            document.getElementById('total-distance').innerText = event.data.distance;
            document.getElementById('travel-time').innerText = event.data.duration;
          }
        });

        // Fetch Full Trip Distance (Integrated Billing)
        fetch(`../api/get_itinerary_distance.php?plan_id=<?php echo $planId; ?>`)
          .then(res => res.json())
          .then(data => {
            if(data.success) {
              document.getElementById('full-trip-distance').innerText = data.distance + ' km';
            } else {
              document.getElementById('full-trip-distance').innerText = '0 km';
            }
          })
          .catch(() => {
            document.getElementById('full-trip-distance').innerText = '0 km';
          });
      </script>

      <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div><img alt="TripSync Logo" class="h-12 w-auto mb-4" src="../assets/images/logo.png">
              <p class="text-teal-50 text-sm leading-relaxed">Your trusted partner for seamless travel planning across
                Sri Lanka.</p>
            </div>
            <div>
              <h3 class="font-semibold text-base mb-4">Quick Links</h3>
              <ul class="space-y-2">
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="dashboard.php" data-discover="true">Dashboard</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="plan_trip.php" data-discover="true">Plan Trip</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="marketplace.php" data-discover="true">Marketplace</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="wallet.php"
                    data-discover="true">Wallet</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="trip_history.php" data-discover="true">Trip History</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="messages.php" data-discover="true">Messages</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="booking_confirmation.php" data-discover="true">Booking Confirmation</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="../partner/register.php" data-discover="true">Become a Partner</a></li>
              </ul>
            </div>
            <div>
              <h3 class="font-semibold text-base mb-4">Support</h3>
              <ul class="space-y-2">
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="help.php"
                    data-discover="true">Help Center</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="../index.php#contact" data-discover="true">Contact Us</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="#"
                    data-discover="true">Terms of Service</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="#"
                    data-discover="true">Privacy Policy</a></li>
              </ul>
            </div>
            <div>
              <h3 class="font-semibold text-base mb-4">Connect With Us</h3>
              <div class="flex gap-3 mb-4"><a href="https://facebook.com/" target="_blank" rel="noopener noreferrer"
                  class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                    class="ri-facebook-fill text-lg"></i></a><a href="https://instagram.com/" target="_blank"
                  rel="noopener noreferrer"
                  class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                    class="ri-instagram-line text-lg"></i></a><a href="https://twitter.com/" target="_blank"
                  rel="noopener noreferrer"
                  class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                    class="ri-twitter-x-line text-lg"></i></a></div>
              <p class="text-teal-50 text-sm"><i class="ri-mail-line mr-2"></i>info@tripsync.lk</p>
            </div>
          </div>
          <div class="border-t border-teal-500/30 mt-8 pt-6 text-center text-teal-50 text-sm">
            <p>© 2026 TripSync. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  </div>
</body>

</html>
