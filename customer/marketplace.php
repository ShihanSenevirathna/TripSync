<?php
require_once '../includes/config.php';
require_once '../api/google_places_api.php';

// Filter Logic
$location = isset($_GET['location']) ? $_GET['location'] : 'All Locations';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 100000;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recommended';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'hotels';
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : null;
$plan_param = $plan_id ? '&plan_id=' . $plan_id : '';
// Trip-context: near destination, travel dates, guests
$near = isset($_GET['near']) ? trim($_GET['near']) : '';
$qs_checkin = isset($_GET['checkin']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['checkin']) ? $_GET['checkin'] : '';
$qs_checkout = isset($_GET['checkout']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['checkout']) ? $_GET['checkout'] : '';
$qs_guests = isset($_GET['guests']) && is_numeric($_GET['guests']) ? (int)$_GET['guests'] : 0;

// Build date/guest query suffix for hotel & vehicle links
$date_param = $qs_checkin ? '&checkin=' . $qs_checkin : '';
$date_param .= $qs_checkout ? '&checkout=' . $qs_checkout : '';
$date_param .= $qs_guests ? '&guests=' . $qs_guests : '';
$near_param = $near ? '&near=' . urlencode($near) : '';

$googleApi = new GooglePlacesAPI();

// Determine the search query for Google
if (!empty($search)) {
  // If specific hotel/name search, use it directly
  $search_query = $search;
}
elseif (!empty($near)) {
  // Trip context: search for top hotels near the destination
  $search_query = 'top rated hotels near ' . $near;
}
else {
  // If browsing by location, search for "top hotels" in that area
  $search_query = ($location === 'All Locations' ? 'best top rated hotels in Sri Lanka' : 'top rated hotels in ' . $location);
}

// Fetch Real Hotels
$hotels = $googleApi->searchHotels($search_query);

// Apply "Top Hotels Only" Filter if not a specific search
// If searching for a name, we show the results regardless of rating to find the exact match
if (empty($search)) {
  $hotels = array_filter($hotels, function ($h) {
    return ($h['stars'] ?? 0) >= 4.0;
  });
}

// Apply Price Filter
$hotels = array_filter($hotels, function ($h) use ($min_price, $max_price) {
  return $h['price_per_night'] >= $min_price && $h['price_per_night'] <= $max_price;
});

// Sort Logic
if ($sort === 'price-low') {
  usort($hotels, fn($a, $b) => $a['price_per_night'] <=> $b['price_per_night']);
}
elseif ($sort === 'price-high') {
  usort($hotels, fn($a, $b) => $b['price_per_night'] <=> $a['price_per_night']);
}
elseif ($sort === 'rating' || $sort === 'recommended') {
  usort($hotels, fn($a, $b) => $b['stars'] <=> $a['stars']);
}

// Re-index array
$hotels = array_values($hotels);

// Fetch Vehicles
$vehicle_query = "SELECT v.* FROM vehicles v WHERE 1=1";

// Date-Range Availability Filter
if (!empty($qs_checkin) && !empty($qs_checkout)) {
  $vehicle_query .= " AND v.id NOT IN (
        SELECT item_id FROM bookings 
        WHERE type = 'vehicle' 
        AND status IN ('confirmed', 'arrived', 'in_progress', 'pending')
        AND (
            (start_date <= '" . $conn->real_escape_string($qs_checkout) . " 23:59:59') AND 
            (end_date >= '" . $conn->real_escape_string($qs_checkin) . " 00:00:00')
        )
    )";
}

if ($search) {
  $vehicle_query .= " AND (v.model LIKE '%" . $conn->real_escape_string($search) . "%' OR v.type LIKE '%" . $conn->real_escape_string($search) . "%')";
}
$vehicle_query .= " AND v.price_per_day BETWEEN $min_price AND $max_price";
if ($sort === 'price-low') {
  $vehicle_query .= " ORDER BY v.price_per_day ASC";
}
elseif ($sort === 'price-high') {
  $vehicle_query .= " ORDER BY v.price_per_day DESC";
}
elseif ($sort === 'rating') {
  $vehicle_query .= " ORDER BY v.rating DESC";
}
$vehicles_result = $conn->query($vehicle_query);
$vehicles = [];
if ($vehicles_result && $vehicles_result->num_rows > 0) {
  while ($row = $vehicles_result->fetch_assoc()) {
    $vehicles[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Marketplace - TripSync</title>
  <meta name="description" content="Browse and book the best hotels and vehicles across Sri Lanka.">
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <script type="module" src="../assets/js/main.js"></script>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>

<body>
  <div id="root">
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-teal-50/30">
      <?php include 'includes/navbar.php'; ?>
      
      <?php if (isset($_GET['error'])): ?>
      <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[100] w-full max-w-md px-4">
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 animate-in fade-in slide-in-from-top-4 duration-300">
          <i class="ri-error-warning-line text-xl"></i>
          <div>
            <p class="text-sm font-bold">Booking Conflict</p>
            <p class="text-xs">
              <?php 
                if ($_GET['error'] === 'already_booked') echo "You already have an active or pending booking for these dates.";
                elseif ($_GET['error'] === 'vehicle_busy') echo "This vehicle was just booked by another user for these dates.";
                else echo "An error occurred with your booking. Please try again.";
              ?>
            </p>
          </div>
          <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-rose-400 hover:text-rose-600"><i class="ri-close-line"></i></button>
        </div>
      </div>
      <?php endif; ?>

      <section class="relative pt-20">
        <div class="relative w-full h-72 overflow-hidden">
          <img alt="Marketplace Hero" class="w-full h-full object-cover object-top"
            src="../assets/images/marketplace_hero.jpg">
          <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/30 to-black/50"></div>
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-4 w-full">
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-3">Marketplace</h1>
            <p class="text-lg text-white/90 max-w-xl">Browse and book the best hotels and vehicles across Sri Lanka</p>
          </div>
        </div>
      </section>

      <section class="max-w-7xl mx-auto px-4 -mt-6 relative z-10 mb-6">
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide">
          <button
            class="px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-600 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition-colors whitespace-nowrap cursor-pointer shadow-sm">Free
            Cancellation</button>
          <button
            class="px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-600 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition-colors whitespace-nowrap cursor-pointer shadow-sm">Breakfast
            Included</button>
          <button
            class="px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-600 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition-colors whitespace-nowrap cursor-pointer shadow-sm">Pool
            Access</button>
          <button
            class="px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-600 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition-colors whitespace-nowrap cursor-pointer shadow-sm">Beach
            Nearby</button>
          <button
            class="px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-600 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition-colors whitespace-nowrap cursor-pointer shadow-sm">Driver
            Included</button>
          <button
            class="px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-600 hover:bg-teal-50 hover:border-teal-200 hover:text-teal-700 transition-colors whitespace-nowrap cursor-pointer shadow-sm">Top
            Rated</button>
        </div>
      </section>

      <section class="max-w-7xl mx-auto px-4 pb-16">
        <div class="flex gap-6">
          <!-- Sidebar Filters -->
          <div class="hidden lg:block w-72 flex-shrink-0">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sticky top-28">
              <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 flex items-center justify-center"><i
                      class="ri-filter-3-line text-lg text-gray-700"></i></div>
                  <span class="font-semibold text-gray-900 text-base">Filters</span>
                </div>
                <button onclick="clearFilters()"
                  class="text-sm text-teal-600 hover:text-teal-700 font-medium cursor-pointer whitespace-nowrap">Clear
                  All</button>
              </div>

              <div class="border-t border-gray-100 pt-4 mb-4">
                <button class="flex items-center justify-between w-full mb-3 cursor-pointer">
                  <span class="text-sm font-semibold text-gray-800">Location</span>
                  <div class="w-5 h-5 flex items-center justify-center"><i class="ri-arrow-up-s-line text-gray-500"></i>
                  </div>
                </button>
                <div class="space-y-1 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                  <?php
$locations = [
  'All Locations', 'Colombo', 'Kandy', 'Galle', 'Nuwara Eliya', 'Ella',
  'Sigiriya', 'Dambulla', 'Anuradhapura', 'Polonnaruwa', 'Habarana',
  'Bentota', 'Negombo', 'Mirissa', 'Unawatuna', 'Hikkaduwa', 'Arugam Bay', 'Tangalle', 'Trincomalee',
  'Bandarawela', 'Badulla', 'Hatton', 'Haputale',
  'Jaffna', 'Matara', 'Ratnapura', 'Balangoda', 'Kurunegala', 'Matale', 'Batticaloa', 'Ampara', 'Kegalle', 'Chilaw', 'Kalutara', 'Hambantota', 'Monaragala'
];
foreach ($locations as $loc): ?>
                  <button onclick="switchLocation('<?php echo $loc; ?>', this)"
                    class="block w-full text-left px-3 py-1.5 rounded-lg text-xs transition-colors cursor-pointer <?php echo $location === $loc ? 'bg-teal-50 text-teal-700 font-medium' : 'text-gray-600 hover:bg-gray-50'; ?>"><?php echo $loc; ?></button>
                  <?php
endforeach; ?>
                </div>
              </div>
              
              <div class="border-t border-gray-100 pt-4 mb-4">
                <button class="flex items-center justify-between w-full mb-3 cursor-pointer">
                  <span class="text-sm font-semibold text-gray-800">Property Type</span>
                  <div class="w-5 h-5 flex items-center justify-center"><i class="ri-arrow-up-s-line text-gray-500"></i></div>
                </button>
                <div class="flex flex-wrap gap-2">
                  <?php
$types = ['All', 'Luxury', 'Resort', 'Boutique', 'Heritage', 'Eco Lodge', 'Beach Hotel'];
foreach ($types as $type): ?>
                  <button class="px-3 py-1 bg-gray-50 hover:bg-teal-50 hover:text-teal-600 text-[11px] rounded-full text-gray-600 transition-colors border border-gray-100"><?php echo $type; ?></button>
                  <?php
endforeach; ?>
                </div>
              </div>

              <div class="border-t border-gray-100 pt-4 mb-4">
                <button class="flex items-center justify-between w-full mb-3 cursor-pointer">
                  <span class="text-sm font-semibold text-gray-800">Price Range</span>
                  <div class="w-5 h-5 flex items-center justify-center"><i class="ri-arrow-up-s-line text-gray-500"></i>
                  </div>
                </button>
                <div class="flex items-center gap-2 mb-4">
                  <div class="flex-1">
                    <label class="text-[10px] text-gray-400 uppercase font-bold mb-1 block">Min</label>
                    <input name="min_price" id="minPriceInput"
                      class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="number" value="<?php echo $min_price; ?>" onchange="updateFilters()">
                  </div>
                  <span class="text-gray-300 mt-5">—</span>
                  <div class="flex-1">
                    <label class="text-[10px] text-gray-400 uppercase font-bold mb-1 block">Max</label>
                    <input name="max_price" id="maxPriceInput"
                      class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="number" value="<?php echo $max_price; ?>" onchange="updateFilters()">
                  </div>
                </div>
                <input name="max_price_range" min="0" max="100000" step="500" class="w-full accent-teal-600 cursor-pointer" type="range" value="<?php echo $max_price; ?>" oninput="document.getElementById('maxPriceInput').value = this.value" onchange="updateFilters()">
              </div>

              <div class="border-t border-gray-100 pt-4 mb-4">
                <button class="flex items-center justify-between w-full mb-3 cursor-pointer">
                  <span class="text-sm font-semibold text-gray-800">Minimum Rating</span>
                </button>
                <div class="flex items-center justify-between gap-1">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                  <button class="flex-1 py-1.5 border border-gray-100 rounded-lg text-xs hover:bg-teal-50 flex items-center justify-center gap-1 transition-colors">
                    <?php echo $i; ?> <i class="ri-star-fill text-amber-500 text-[10px]"></i>
                  </button>
                  <?php
endfor; ?>
                </div>
              </div>

              <div class="border-t border-gray-100 pt-4">
                <button class="flex items-center justify-between w-full mb-3 cursor-pointer">
                  <span class="text-sm font-semibold text-gray-800">Amenities</span>
                </button>
                <div class="grid grid-cols-2 gap-2">
                  <?php
$amenity_items = ['Wifi', 'Pool', 'Parking', 'Restaurant', 'Spa', 'Beach', 'Nature'];
foreach ($amenity_items as $item): ?>
                  <button class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-100 rounded-lg text-[10px] text-gray-600 hover:bg-teal-50 hover:text-teal-600 transition-colors">
                    <div class="w-3 h-3 flex items-center justify-center border border-gray-300 rounded-sm bg-white"></div>
                    <?php echo $item; ?>
                  </button>
                  <?php
endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Main Content -->
          <div class="flex-1 min-w-0">
            <div class="mb-6">
              <div class="relative mb-5">
                <div onclick="updateFilters()" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center cursor-pointer hover:text-teal-600 transition-colors"><i
                    class="ri-search-line text-lg text-gray-400 hover:text-teal-600"></i></div>
                <input placeholder="Search hotels or vehicles by name or location..." id="searchInput"
                  class="w-full pl-12 pr-4 py-3 bg-white border border-gray-100 rounded-xl text-xs focus:ring-1 focus:ring-teal-500 focus:border-transparent shadow-sm"
                  type="text" value="<?php echo htmlspecialchars($search); ?>" onkeyup="handleSearchInput(event)" autocomplete="off">
                <!-- Suggestions Dropdown -->
                <div id="suggestionsBox" class="absolute top-full left-0 right-0 bg-white border border-gray-100 rounded-xl mt-1 shadow-lg z-50 hidden overflow-hidden">
                </div>
              </div>
              <div class="flex items-center justify-between flex-wrap gap-4">
                <!-- Toggle Tabs -->
                <div class="flex items-center gap-1 bg-gray-100 rounded-full p-1">
                  <button id="tab-hotels" onclick="switchTab('hotels')"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap <?php echo $active_tab === 'hotels' ? 'bg-white text-teal-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'; ?>">
                    <div class="w-4 h-4 flex items-center justify-center"><i class="ri-hotel-line text-sm"></i></div>
                    Hotels
                  </button>
                  <button id="tab-vehicles" onclick="switchTab('vehicles')"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap <?php echo $active_tab === 'vehicles' ? 'bg-white text-teal-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'; ?>">
                    <div class="w-4 h-4 flex items-center justify-center"><i class="ri-car-line text-sm"></i></div>
                    Vehicles
                  </button>
                </div>
                <div class="flex items-center gap-4">
                   <span id="results-count" class="text-sm text-gray-500">
                    <?php if ($active_tab === 'hotels'): ?>
                      <strong class="text-gray-900"><?php echo count($hotels); ?></strong> properties found
                    <?php
else: ?>
                      <strong class="text-gray-900"><?php echo count($vehicles); ?></strong> vehicles found
                    <?php
endif; ?>
                  </span>
                  <select id="sortSelect" onchange="updateFilters()"
                    class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-teal-500 focus:border-transparent cursor-pointer">
                    <option value="recommended" <?php echo $sort === 'recommended' ? 'selected' : ''; ?>>Recommended</option>
                    <option value="price-low" <?php echo $sort === 'price-low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price-high" <?php echo $sort === 'price-high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                  </select>
                </div>
              </div>
            </div>

            <?php if ($plan_id && !empty($near)): ?>
            <!-- Trip Context Banner -->
            <div class="mb-5 flex items-start gap-3 p-4 bg-gradient-to-r from-teal-50 to-indigo-50 border border-teal-100 rounded-2xl">
              <div class="w-9 h-9 flex-shrink-0 flex items-center justify-center bg-teal-600 rounded-xl">
                <i class="ri-map-pin-2-line text-white text-lg"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-teal-800">
                  Hotels near <span class="font-extrabold"><?php echo htmlspecialchars($near); ?></span>
                </p>
                <p class="text-xs text-teal-600 mt-0.5">
                  <?php if ($qs_checkin && $qs_checkout): ?>
                    Check-in <?php echo date('M d', strtotime($qs_checkin)); ?> &rarr; Check-out <?php echo date('M d', strtotime($qs_checkout)); ?>
                    <?php if ($qs_guests > 0): ?>&nbsp;&bull;&nbsp;<?php echo $qs_guests; ?> guest<?php echo $qs_guests > 1 ? 's' : ''; ?><?php
    endif; ?>
                  <?php
  else: ?>
                    Based on your trip itinerary
                  <?php
  endif; ?>
                </p>
              </div>
              <a href="marketplace.php?tab=hotels<?php echo $plan_param; ?>"
                 class="flex-shrink-0 text-xs text-teal-500 hover:text-teal-700 font-medium flex items-center gap-1 transition-colors whitespace-nowrap">
                Clear <i class="ri-close-line"></i>
              </a>
            </div>
            <?php
endif; ?>

            <!-- Hotels Section -->
            <div id="hotels-section" class="<?php echo $active_tab === 'hotels' ? '' : 'hidden'; ?>">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php foreach ($hotels as $hotel):
  // Optimized: use reference from search results first
  if (!empty($hotel['photo_reference'])) {
    $display_image = $googleApi->getPhotoUrlFromReference($hotel['photo_reference']);
  }
  else {
    $display_image = $googleApi->getPlacePhoto($hotel['name'] . " " . ($hotel['location'] ?? ''));
  }
?>
                <!-- Hotel Card -->
                <div
                  class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 group">
                  <a class="block relative w-full h-52" href="hotel_details.php?id=<?php echo $hotel['id']; ?><?php echo $plan_param . $date_param; ?>">
                    <img alt="<?php echo $hotel['name']; ?>"
                      class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
                      src="<?php echo $display_image; ?>">
                    <?php if (isset($hotel['is_live']) && $hotel['is_live']): ?>
                    <span
                      class="absolute top-3 left-3 bg-teal-600 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase shadow-sm">Live Deal</span>
                    <?php
  elseif (($hotel['stars'] ?? 0) >= 4.7): ?>
                    <span
                      class="absolute top-3 left-3 bg-amber-500 text-white text-xs font-semibold px-3 py-1 rounded-full">Featured</span>
                    <?php
  endif; ?>
                    <button
                      class="absolute top-3 right-3 w-9 h-9 flex items-center justify-center bg-white/80 backdrop-blur-sm rounded-full hover:bg-white transition-colors cursor-pointer"><i
                        class="ri-heart-line text-gray-600 text-lg"></i></button>
                    <div class="absolute bottom-3 left-3 bg-white/90 backdrop-blur-sm px-2.5 py-1 rounded-full"><span
                        class="text-xs font-medium text-gray-700"><?php echo $hotel['category'] ?? 'Premium Stay'; ?></span></div>
                  </a>
                  <div class="p-5">
                    <div class="flex items-start justify-between mb-2">
                       <a class="font-semibold text-gray-900 text-base leading-tight pr-2 hover:text-teal-700 transition-colors cursor-pointer"
                        href="hotel_details.php?id=<?php echo $hotel['id']; ?><?php echo $plan_param . $date_param; ?>"><?php echo $hotel['name']; ?></a>
                      <div class="flex items-center gap-1 flex-shrink-0"><i
                          class="ri-star-fill text-amber-500 text-sm"></i><span
                          class="text-sm font-semibold text-gray-900"><?php echo $hotel['stars'] ?? '4.0'; ?></span></div>
                    </div>
                    <div class="flex items-center gap-1 mb-2">
                      <div class="w-4 h-4 flex items-center justify-center"><i
                          class="ri-map-pin-line text-sm text-gray-400"></i></div>
                      <span class="text-[11px] text-gray-500"><?php echo $hotel['location'] ?? 'Sri Lanka'; ?> <span class="text-gray-300 mx-1">·</span> (<?php echo $hotel['reviews_count'] ?? rand(50, 200); ?> reviews)</span>
                    </div>
                    <div class="flex items-center gap-0.5 mb-3">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="ri-star-fill <?php echo $i <= ($hotel['stars'] ?? 0) ? 'text-amber-400' : 'text-gray-200'; ?> text-xs"></i>
                      <?php
  endfor; ?>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mb-4">
                      <?php
  $amenities_raw = $hotel['amenities'] ?? 'WiFi, AC, Parking';
  $amenities = explode(',', $amenities_raw);
  foreach (array_slice($amenities, 0, 4) as $amenity):
    $amenity = trim($amenity);
    $icon = 'ri-checkbox-circle-line';
    if (stripos($amenity, 'wifi') !== false)
      $icon = 'ri-wifi-line';
    if (stripos($amenity, 'pool') !== false)
      $icon = 'ri-water-flash-line';
    if (stripos($amenity, 'restaurant') !== false)
      $icon = 'ri-restaurant-line';
    if (stripos($amenity, 'beach') !== false)
      $icon = 'ri-sun-line';
    if (stripos($amenity, 'spa') !== false)
      $icon = 'ri-magic-line';
?>
                      <div class="w-8 h-8 flex items-center justify-center bg-gray-50 rounded-lg" title="<?php echo $amenity; ?>"><i
                          class="<?php echo $icon; ?> text-sm text-gray-500"></i></div>
                      <?php
  endforeach; ?>
                    </div>
                    <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                      <div><span class="text-xs text-gray-500">From</span>
                        <p class="text-lg font-bold text-teal-700">LKR <?php echo number_format($hotel['price_per_night']); ?><span
                            class="text-xs font-normal text-gray-500">/night</span></p>
                      </div>
                      <a class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer flex items-center gap-1.5"
                        href="hotel_details.php?id=<?php echo $hotel['id']; ?><?php echo $plan_param . $date_param; ?>">View Details<div
                          class="w-4 h-4 flex items-center justify-center"><i class="ri-arrow-right-line text-sm"></i>
                        </div></a>
                    </div>
                  </div>
                </div>
                <?php
endforeach; ?>
              </div>
            </div>

            <!-- Vehicles Section -->
            <div id="vehicles-section" class="<?php echo $active_tab === 'vehicles' ? '' : 'hidden'; ?>">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <?php foreach ($vehicles as $vehicle): ?>
                <!-- Vehicle Card -->
                <div
                  class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 group">
                  <a class="block relative w-full h-52" href="vehicle_details.php?id=<?php echo $vehicle['id']; ?><?php echo $plan_param . $date_param; ?>">
                    <img alt="<?php echo $vehicle['model']; ?>"
                      class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-500"
                      src="<?php echo getVehicleImage($vehicle['image_path'], '../'); ?>">
                    <?php if ($vehicle['rating'] >= 4.7): ?>
                    <span
                      class="absolute top-3 left-3 bg-amber-500 text-white text-xs font-semibold px-3 py-1 rounded-full">Popular</span>
                    <?php
  endif; ?>
                    <div class="absolute bottom-3 left-3 bg-white/90 backdrop-blur-sm px-2.5 py-1 rounded-full"><span
                        class="text-xs font-medium text-gray-700"><?php echo ucfirst($vehicle['type']); ?></span></div>
                  </a>
                  <div class="p-5">
                    <div class="flex items-start justify-between mb-2">
                      <a class="font-semibold text-gray-900 text-base leading-tight pr-2 hover:text-teal-700 transition-colors cursor-pointer"
                        href="vehicle_details.php?id=<?php echo $vehicle['id']; ?><?php echo $plan_param . $date_param; ?>"><?php echo $vehicle['model']; ?></a>
                      <div class="flex items-center gap-1 flex-shrink-0"><i
                          class="ri-star-fill text-amber-500 text-sm"></i><span
                          class="text-sm font-semibold text-gray-900"><?php echo number_format($vehicle['rating'], 1); ?></span></div>
                    </div>
                    <p class="text-xs text-gray-400 mb-3">(<?php echo $vehicle['reviews_count']; ?> reviews)</p>
                    <div class="flex items-center gap-4 mb-4">
                      <div class="flex items-center gap-1.5 text-sm text-gray-600">
                        <div class="w-4 h-4 flex items-center justify-center"><i
                            class="ri-user-line text-sm text-gray-400"></i></div><span><?php echo $vehicle['capacity']; ?></span>
                      </div>
                      <div class="flex items-center gap-1.5 text-sm text-gray-600">
                        <div class="w-4 h-4 flex items-center justify-center"><i
                            class="ri-luggage-cart-line text-sm text-gray-400"></i></div><span><?php echo floor($vehicle['capacity'] / 1.5); ?></span>
                      </div>
                      <div class="flex items-center gap-1.5 text-sm text-gray-600">
                        <div class="w-4 h-4 flex items-center justify-center"><i
                            class="ri-settings-3-line text-sm text-gray-400"></i></div><span><?php echo stripos($vehicle['features'], 'Automatic') !== false ? 'Automatic' : 'Manual'; ?></span>
                      </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mb-4">
                      <?php
  $features = explode(',', $vehicle['features']);
  foreach ($features as $feature):
?>
                      <span class="px-2.5 py-1 bg-gray-50 text-gray-600 text-xs rounded-full"><?php echo trim($feature); ?></span>
                      <?php
  endforeach; ?>
                    </div>
                    <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                      <div><span class="text-xs text-gray-500">From</span>
                        <p class="text-lg font-bold text-teal-700">LKR <?php echo number_format($vehicle['price_per_day']); ?><span
                            class="text-xs font-normal text-gray-500">/day</span></p>
                      </div>
                      <a class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer flex items-center gap-1.5"
                        href="vehicle_details.php?id=<?php echo $vehicle['id']; ?><?php echo $plan_param . $date_param; ?>">View Details<div
                          class="w-4 h-4 flex items-center justify-center"><i class="ri-arrow-right-line text-sm"></i>
                        </div></a>
                    </div>
                  </div>
                </div>
                <?php
endforeach; ?>

              </div>
            </div>

            <!-- Footer Section -->
            <div class="mt-8 bg-gradient-to-r from-teal-50 to-emerald-50 border border-teal-100 rounded-2xl p-6">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 flex items-center justify-center bg-teal-100 rounded-xl flex-shrink-0"><i
                    class="ri-lightbulb-line text-xl text-teal-600"></i></div>
                <div>
                  <h4 class="font-semibold text-gray-900 text-sm mb-1"><a href="plan_trip.php"
                      class="hover:text-teal-700 transition-colors">Contextual Booking Tip</a></h4>
                  <p class="text-sm text-gray-600 leading-relaxed">Plan your itinerary first, then come back here! We'll
                    automatically suggest hotels and vehicles available at your destinations on the exact dates you need
                    them.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
              <img alt="TripSync Logo" class="h-12 w-auto mb-4" src="../assets/images/logo.png">
              <p class="text-teal-50 text-sm leading-relaxed">Your trusted partner for seamless travel planning
                across Sri Lanka.</p>
            </div>
            <div>
              <h3 class="font-semibold text-base mb-4">Quick Links</h3>
              <ul class="space-y-2">
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="dashboard.php">Dashboard</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="plan_trip.php">Plan Trip</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="marketplace.php">Marketplace</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="wallet.php">Wallet</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="trip_history.php">Trip History</a></li>
              </ul>
            </div>
            <div>
              <h3 class="font-semibold text-base mb-4">Support</h3>
              <ul class="space-y-2">
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="help.php">Help Center</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="help.php">Contact Us</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="#">Terms of
                    Service</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="#">Privacy
                    Policy</a></li>
              </ul>
            </div>
            <div>
              <h3 class="font-semibold text-base mb-4">Connect With Us</h3>
              <div class="flex gap-3 mb-4">
                <a href="https://facebook.com/" target="_blank" rel="noopener noreferrer"
                  class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                    class="ri-facebook-fill text-lg"></i></a>
                <a href="https://instagram.com/" target="_blank" rel="noopener noreferrer"
                  class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                    class="ri-instagram-line text-lg"></i></a>
                <a href="https://twitter.com/" target="_blank" rel="noopener noreferrer"
                  class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                    class="ri-twitter-x-line text-lg"></i></a>
              </div>
              <p class="text-teal-50 text-sm"><i class="ri-mail-line mr-2"></i>info@tripsync.lk</p>
            </div>
          </div>
          <div class="border-t border-teal-500/30 mt-8 pt-6 text-center text-teal-50 text-sm">
            <p>&copy; 2026 TripSync. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script>
    function updateFilters() {
      const search = document.getElementById('searchInput').value;
      const sort = document.getElementById('sortSelect').value;
      const minPrice = document.getElementById('minPriceInput').value;
      const maxPrice = document.getElementById('maxPriceInput').value;
      const activeLocBtn = document.querySelector('.bg-teal-50.text-teal-700');
      const location = activeLocBtn ? activeLocBtn.innerText.trim() : 'All Locations';
      const activeTab = document.getElementById('tab-hotels').classList.contains('text-teal-700') ? 'hotels' : 'vehicles';
      
      const planId = <?php echo $plan_id ? $plan_id : 'null'; ?>;
      const nearDest = <?php echo $near ? json_encode($near) : 'null'; ?>;
      const qsCheckin  = <?php echo $qs_checkin ? json_encode($qs_checkin) : 'null'; ?>;
      const qsCheckout = <?php echo $qs_checkout ? json_encode($qs_checkout) : 'null'; ?>;
      const qsGuests   = <?php echo $qs_guests ? $qs_guests : 'null'; ?>;
      let url = `marketplace.php?search=${encodeURIComponent(search)}&sort=${sort}&min_price=${minPrice}&max_price=${maxPrice}&location=${encodeURIComponent(location)}&tab=${activeTab}`;
      if (planId) url += `&plan_id=${planId}`;
      if (nearDest)   url += `&near=${encodeURIComponent(nearDest)}`;
      if (qsCheckin)  url += `&checkin=${qsCheckin}`;
      if (qsCheckout) url += `&checkout=${qsCheckout}`;
      if (qsGuests)   url += `&guests=${qsGuests}`;
      window.location.href = url;
    }

    function clearFilters() {
      const planId = <?php echo $plan_id ? $plan_id : 'null'; ?>;
      window.location.href = planId ? `marketplace.php?plan_id=${planId}` : 'marketplace.php';
    }

    function switchLocation(loc, btn) {
      // Update UI
      document.querySelectorAll('.space-y-1 button').forEach(b => {
        b.classList.remove('bg-teal-50', 'text-teal-700', 'font-medium');
        b.classList.add('text-gray-600', 'hover:bg-gray-50');
      });
      btn.classList.add('bg-teal-50', 'text-teal-700', 'font-medium');
      btn.classList.remove('text-gray-600', 'hover:bg-gray-50');
      
      updateFilters();
    }

    function switchTab(tab) {
      const hotelTab = document.getElementById('tab-hotels');
      const vehicleTab = document.getElementById('tab-vehicles');
      const hotelSection = document.getElementById('hotels-section');
      const vehicleSection = document.getElementById('vehicles-section');
      const resultsCount = document.getElementById('results-count');

      if (tab === 'hotels') {
        hotelTab.classList.add('bg-white', 'text-teal-700', 'shadow-sm');
        hotelTab.classList.remove('text-gray-500', 'hover:text-gray-700');
        vehicleTab.classList.remove('bg-white', 'text-teal-700', 'shadow-sm');
        vehicleTab.classList.add('text-gray-500', 'hover:text-gray-700');

        hotelSection.classList.remove('hidden');
        vehicleSection.classList.add('hidden');
        resultsCount.innerHTML = '<strong class="text-gray-900"><?php echo count($hotels); ?></strong> properties found';
      } else {
        vehicleTab.classList.add('bg-white', 'text-teal-700', 'shadow-sm');
        vehicleTab.classList.remove('text-gray-500', 'hover:text-gray-700');
        hotelTab.classList.remove('bg-white', 'text-teal-700', 'shadow-sm');
        hotelTab.classList.add('text-gray-500', 'hover:text-gray-700');

        vehicleSection.classList.remove('hidden');
        hotelSection.classList.add('hidden');
        resultsCount.innerHTML = '<strong class="text-gray-900"><?php echo count($vehicles); ?></strong> vehicles found';
      }
    }
    let searchTimeout;
    const suggestionsBox = document.getElementById('suggestionsBox');
    const searchInput = document.getElementById('searchInput');

    function handleSearchInput(event) {
      if (event.key === 'Enter') {
        updateFilters();
        return;
      }

      clearTimeout(searchTimeout);
      const query = event.target.value.trim();

      if (query.length < 3) {
        suggestionsBox.classList.add('hidden');
        return;
      }

      searchTimeout = setTimeout(() => {
        fetchSuggestions(query);
      }, 400);
    }

    async function fetchSuggestions(query) {
      try {
        const response = await fetch(`../api_endpoints/hotel_suggestions.php?query=${encodeURIComponent(query)}`);
        const data = await response.json();

        if (data && data.length > 0) {
          suggestionsBox.innerHTML = '';
          data.forEach(item => {
            const div = document.createElement('div');
            div.className = 'px-4 py-3 hover:bg-teal-50 cursor-pointer border-b last:border-0 border-gray-50 flex items-center gap-3 transition-colors';
            div.innerHTML = `
              <div class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center flex-shrink-0">
                <i class="ri-hotel-line text-teal-600"></i>
              </div>
              <div class="flex-1 overflow-hidden">
                <p class="text-sm font-semibold text-gray-800 truncate">${item.main_text}</p>
                <p class="text-[10px] text-gray-500 truncate">${item.secondary_text}</p>
              </div>
            `;
            div.onclick = () => {
              searchInput.value = item.main_text;
              suggestionsBox.classList.add('hidden');
              updateFilters();
            };
            suggestionsBox.appendChild(div);
          });
          suggestionsBox.classList.remove('hidden');
        } else {
          suggestionsBox.classList.add('hidden');
        }
      } catch (error) {
        console.error('Error fetching suggestions:', error);
      }
    }

    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.classList.add('hidden');
      }
    });
  </script>
</body>

</html>
