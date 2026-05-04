<?php
require_once '../includes/config.php';
require_once '../api/google_places_api.php';

$raw_id = isset($_GET['id']) ? $_GET['id'] : '';
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : null;
// Pre-fill dates and guests from itinerary builder context
$prefill_checkin = isset($_GET['checkin']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['checkin']) ? $_GET['checkin'] : date('Y-m-d');
$prefill_checkout = isset($_GET['checkout']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['checkout']) ? $_GET['checkout'] : date('Y-m-d', strtotime('+1 day'));
$prefill_guests = isset($_GET['guests']) && is_numeric($_GET['guests']) ? (int)$_GET['guests'] : 1;
$is_google = strpos($raw_id, 'google_') === 0;

$hotel = null;
$googleApi = new GooglePlacesAPI();

if ($is_google) {
    $place_id = substr($raw_id, 7);
    $googleDetails = $googleApi->getDetailsByPlaceId($place_id);

    if ($googleDetails) {
        $hotel = [
            'id' => $raw_id,
            'name' => $googleDetails['name'],
            'location' => $googleDetails['address'],
            'stars' => $googleDetails['rating'],
            'reviews_count' => $googleDetails['reviews_count'],
            'price_per_night' => GooglePlacesAPI::calcPrice($place_id, $googleDetails['rating'] ?? 4.0, $googleDetails['price_level'] ?? null),
            'image_path' => '',
            'description' => $googleDetails['description'],
            'amenities' => implode(', ', $googleDetails['amenities']),
            'google_photos' => $googleDetails['photos'],
            'google_reviews' => $googleDetails['reviews'],
            'google_amenities' => $googleDetails['amenities'],
            'map_id' => $googleDetails['place_id'],
            'category' => ($googleDetails['rating'] >= 4.5) ? 'Luxury' : 'Premium Stay',
            'website' => $googleDetails['website'],
            'phone' => $googleDetails['phone'],
            'room_types' => $googleDetails['room_types'],
            'policies' => $googleDetails['policies']
        ];
    }
}
else {
    // Fallback for local DB if needed, but user said real hotels/Google only
    $hotel_id = (int)$raw_id;
    if ($hotel_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM hotels WHERE id = ?");
        $stmt->bind_param("i", $hotel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $hotel = $result->fetch_assoc();
    }
}

if (!$hotel) {
    header('Location: marketplace.php');
    exit;
}

$main_image = !empty($hotel['google_photos']) ? $hotel['google_photos'][0] : "../assets/images/hotel_details/main.jpg";
$amenities = $hotel['google_amenities'] ?? explode(', ', $hotel['amenities']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hotel['name']); ?> - TripSync</title>
    <meta name="description" content="Experience the height of hospitality at <?php echo htmlspecialchars($hotel['name']); ?> in <?php echo htmlspecialchars($hotel['location']); ?>.">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script type="module" src="../assets/js/main.js"></script>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>

<body class="bg-gray-50">
    <!-- Nav -->
    <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a class="flex items-center gap-3" href="../index.php">
                    <img alt="TripSync Logo" class="h-12 w-auto" src="../assets/images/logo.png">
                </a>
                <div class="hidden md:flex items-center gap-8">
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="../index.php">Home</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="dashboard.php">Dashboard</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="plan_trip.php">Plan Trip</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-teal-600"
                        href="marketplace.php">Marketplace</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="wallet.php">Wallet</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="reviews.php">Reviews</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="trip_history.php">Trip History</a>
                    <a class="text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                        href="help.php">Help</a>
                    <div class="flex items-center gap-2">
                        <a class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-gray-600 hover:bg-gray-100"
                            href="notifications.php"><i class="ri-notification-3-line text-lg"></i><span
                                class="absolute top-1 right-1 w-2 h-2 bg-rose-500 rounded-full"></span></a>
                        <a class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-gray-600 hover:bg-gray-100"
                            href="profile.php"><i class="ri-user-line text-lg"></i></a>
                    </div>
                </div>
                <button class="md:hidden w-8 h-8 flex items-center justify-center cursor-pointer text-gray-700"><i
                        class="ri-menu-line text-2xl"></i></button>
            </div>
        </div>
    </nav>

    <div class="pt-20">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
                <a class="hover:text-teal-600 transition-colors cursor-pointer" href="marketplace.php">Marketplace</a>
                <i class="ri-arrow-right-s-line text-gray-400"></i>
                <span class="capitalize">hotels</span>
                <i class="ri-arrow-right-s-line text-gray-400"></i>
                <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($hotel['name']); ?></span>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4">
            <!-- Image Gallery -->
            <div class="grid grid-cols-4 grid-rows-2 gap-2 h-[420px] rounded-2xl overflow-hidden mb-8 shadow-sm">
                <div class="col-span-2 row-span-2 relative group overflow-hidden cursor-pointer" onclick="openPhotoModal(0)">
                    <img alt="<?php echo htmlspecialchars($hotel['name']); ?> main"
                        class="w-full h-full object-cover object-top hover:scale-105 transition-transform duration-700"
                        src="<?php echo $main_image; ?>">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                </div>
                <div class="relative group overflow-hidden cursor-pointer" onclick="openPhotoModal(1)">
                    <img alt="<?php echo htmlspecialchars($hotel['name']); ?> photo 2"
                        class="w-full h-full object-cover object-top hover:scale-110 transition-transform duration-500"
                        src="<?php echo $hotel['google_photos'][1] ?? '../assets/images/hotel_details/img1.jpg'; ?>">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                </div>
                <div class="relative group overflow-hidden cursor-pointer" onclick="openPhotoModal(2)">
                    <img alt="<?php echo htmlspecialchars($hotel['name']); ?> photo 3"
                        class="w-full h-full object-cover object-top hover:scale-110 transition-transform duration-500"
                        src="<?php echo $hotel['google_photos'][2] ?? '../assets/images/hotel_details/img2.jpg'; ?>">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                </div>
                <div class="relative group overflow-hidden cursor-pointer" onclick="openPhotoModal(3)">
                    <img alt="<?php echo htmlspecialchars($hotel['name']); ?> photo 4"
                        class="w-full h-full object-cover object-top hover:scale-110 transition-transform duration-500"
                        src="<?php echo $hotel['google_photos'][3] ?? '../assets/images/hotel_details/img3.jpg'; ?>">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                </div>
                <div class="relative group overflow-hidden cursor-pointer" onclick="openPhotoModal(4)">
                    <img alt="<?php echo htmlspecialchars($hotel['name']); ?> photo 5"
                        class="w-full h-full object-cover object-top hover:scale-110 transition-transform duration-500"
                        src="<?php echo $hotel['google_photos'][4] ?? '../assets/images/hotel_details/img4.jpg'; ?>">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                    <?php if (count($hotel['google_photos'] ?? []) > 5): ?>
                    <button onclick="openPhotoModal(4)"
                        class="absolute inset-0 bg-black/40 flex items-center justify-center cursor-pointer hover:bg-black/50 transition-colors">
                        <span class="text-white text-sm font-medium whitespace-nowrap">+<?php echo count($hotel['google_photos']) - 5; ?> more</span>
                    </button>
                    <?php
endif; ?>
                </div>
            </div>

            <style>
                .no-scrollbar::-webkit-scrollbar { display: none; }
                .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            </style>

            <div class="flex justify-end -mt-6 mb-8 relative z-10 pr-4">
                <button onclick="openPhotoModal(0)"
                    class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer whitespace-nowrap shadow-sm">
                    <div class="w-4 h-4 flex items-center justify-center"><i class="ri-image-line text-sm"></i></div>
                    View All Photos
                </button>
            </div>

            <div class="flex flex-col lg:flex-row gap-8 pb-16">
                <div class="flex-1 min-w-0">
                    <div>
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-2">
                                <span
                                    class="bg-teal-100 text-teal-700 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo htmlspecialchars($hotel['category']); ?></span>
                                <div class="flex items-center gap-0.5">
                                    <?php
$stars = floor($hotel['stars']);
for ($i = 0; $i < 5; $i++) {
    if ($i < $stars) {
        echo '<i class="ri-star-fill text-amber-400 text-xs"></i>';
    }
    else {
        echo '<i class="ri-star-line text-gray-300 text-xs"></i>';
    }
}
?>
                                </div>
                            </div>
                            <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($hotel['name']); ?></h1>
                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <span class="flex items-center gap-1">
                                    <div class="w-4 h-4 flex items-center justify-center"><i
                                            class="ri-map-pin-line text-sm text-teal-600"></i></div>
                                    <?php echo htmlspecialchars($hotel['location']); ?>, Sri Lanka
                                </span>
                                <span class="flex items-center gap-1"><i
                                        class="ri-star-fill text-amber-500 text-sm"></i><strong
                                        class="text-gray-900"><?php echo htmlspecialchars($hotel['stars']); ?></strong><span class="text-gray-400">(<?php echo rand(50, 500); ?>
                                        reviews)</span></span>
                            </div>
                        </div>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">About This Property</h2>
                            <div id="hotelDescription" class="text-sm text-gray-700 leading-relaxed max-h-24 overflow-hidden relative transition-all duration-500">
                                <?php echo nl2br(htmlspecialchars($hotel['description'])); ?>
                                <div id="descriptionGradient" class="absolute bottom-0 left-0 right-0 h-10 bg-gradient-to-t from-white to-transparent"></div>
                            </div>
                            <button onclick="toggleDescription()" id="readMoreBtn"
                                class="text-teal-600 text-sm font-medium mt-2 cursor-pointer hover:text-teal-700 flex items-center gap-1">
                                <span>Read More</span>
                                <div class="w-4 h-4 flex items-center justify-center transition-transform" id="readMoreIcon">
                                    <i class="ri-arrow-down-s-line text-sm"></i>
                                </div>
                            </button>
                        </section>

                        <script>
                            function toggleDescription() {
                                const desc = document.getElementById('hotelDescription');
                                const grad = document.getElementById('descriptionGradient');
                                const btnText = document.querySelector('#readMoreBtn span');
                                const icon = document.getElementById('readMoreIcon');
                                
                                if (desc.style.maxHeight === 'none' || desc.classList.contains('expanded')) {
                                    desc.style.maxHeight = '6rem';
                                    desc.classList.remove('expanded');
                                    grad.style.display = 'block';
                                    btnText.innerText = 'Read More';
                                    icon.style.transform = 'rotate(0deg)';
                                } else {
                                    desc.style.maxHeight = 'none';
                                    desc.classList.add('expanded');
                                    grad.style.display = 'none';
                                    btnText.innerText = 'Read Less';
                                    icon.style.transform = 'rotate(180deg)';
                                }
                            }
                        </script>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Amenities &amp; Services</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <?php foreach ($amenities as $amenity):
    $icon = 'ri-checkbox-circle-line';
    if (stripos($amenity, 'wifi') !== false)
        $icon = 'ri-wifi-line';
    if (stripos($amenity, 'pool') !== false)
        $icon = 'ri-water-flash-line';
    if (stripos($amenity, 'park') !== false)
        $icon = 'ri-parking-line';
    if (stripos($amenity, 'restau') !== false)
        $icon = 'ri-restaurant-line';
    if (stripos($amenity, 'spa') !== false)
        $icon = 'ri-leaf-line';
    if (stripos($amenity, 'gym') !== false || stripos($amenity, 'fit') !== false)
        $icon = 'ri-boxing-line';
?>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                                    <div class="w-9 h-9 flex items-center justify-center bg-white rounded-lg shadow-sm">
                                        <i class="<?php echo $icon; ?> text-lg text-teal-600"></i>
                                    </div><span class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($amenity); ?></span>
                                </div>
                                <?php
endforeach; ?>
                            </div>
                        </section>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Room Types</h2>
                            <div class="space-y-3">
                                <?php foreach ($hotel['room_types'] as $index => $room): ?>
                                <div
                                    class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100 hover:border-teal-200 transition-colors">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($room['name']); ?></h4>
                                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                            <span><i class="ri-ruler-line mr-1"></i><?php echo 25 + ($index * 15); ?> sqm</span>
                                            <span><i class="ri-hotel-bed-line mr-1"></i><?php echo($index > 1) ? '1 Super King' : '1 King'; ?></span>
                                            <span><i class="ri-user-line mr-1"></i>Max <?php echo 2 + ($index > 1 ? 1 : 0); ?></span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-teal-700">LKR <?php echo number_format($room['price']); ?></p>
                                        <span class="text-xs text-gray-500">/night</span>
                                    </div>
                                </div>
                                <?php
endforeach; ?>
                            </div>
                        </section>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Location</h2>
                            <div class="w-full h-80 rounded-2xl overflow-hidden border border-gray-200 mb-4">
                                <?php
$map_query = urlencode($hotel['name'] . ' ' . $hotel['location']);
?>
                                <iframe
                                    src="https://www.google.com/maps/embed/v1/place?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&q=<?php echo $map_query; ?>"
                                    width="100%" height="100%" allowfullscreen="" loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade" title="<?php echo htmlspecialchars($hotel['name']); ?> location"
                                    style="border: 0px;"></iframe>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg text-sm">
                                    <div class="w-4 h-4 flex items-center justify-center"><i
                                            class="ri-map-pin-2-line text-sm text-teal-600"></i></div><span
                                        class="text-gray-700">Galle Face Green</span><span
                                        class="text-gray-400 text-xs">0.8 km</span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg text-sm">
                                    <div class="w-4 h-4 flex items-center justify-center"><i
                                            class="ri-map-pin-2-line text-sm text-teal-600"></i></div><span
                                        class="text-gray-700">Gangaramaya Temple</span><span
                                        class="text-gray-400 text-xs">1.2 km</span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg text-sm">
                                    <div class="w-4 h-4 flex items-center justify-center"><i
                                            class="ri-map-pin-2-line text-sm text-teal-600"></i></div><span
                                        class="text-gray-700">National Museum</span><span
                                        class="text-gray-400 text-xs">2.1 km</span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg text-sm">
                                    <div class="w-4 h-4 flex items-center justify-center"><i
                                            class="ri-map-pin-2-line text-sm text-teal-600"></i></div><span
                                        class="text-gray-700">Pettah Market</span><span
                                        class="text-gray-400 text-xs">3.5 km</span>
                                </div>
                            </div>
                        </section>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Policies</h2>
                            <div class="space-y-2">
                                <div class="border border-gray-100 rounded-xl overflow-hidden bg-white">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 border-b border-gray-100">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm">
                                                <i class="ri-login-box-line text-base text-teal-600"></i>
                                            </div><span class="text-sm font-semibold text-gray-900">Check-in</span>
                                        </div>
                                        <span class="text-sm font-medium text-teal-700"><?php echo $hotel['policies']['check_in']; ?></span>
                                    </div>
                                    <div class="p-4 text-xs text-gray-500 bg-white">Standard check-in time. Early check-in subject to availability.</div>
                                </div>
                                <div class="border border-gray-100 rounded-xl overflow-hidden bg-white">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 border-b border-gray-100">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm">
                                                <i class="ri-logout-box-line text-base text-teal-600"></i>
                                            </div><span class="text-sm font-semibold text-gray-900">Check-out</span>
                                        </div>
                                        <span class="text-sm font-medium text-teal-700"><?php echo $hotel['policies']['check_out']; ?></span>
                                    </div>
                                    <div class="p-4 text-xs text-gray-500 bg-white">Please vacate the room by the specified time.</div>
                                </div>
                                <div class="border border-gray-100 rounded-xl overflow-hidden bg-white">
                                    <div class="flex items-center gap-3 p-4 bg-gray-50 border-b border-gray-100">
                                        <div class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm">
                                            <i class="ri-close-circle-line text-base text-teal-600"></i>
                                        </div><span class="text-sm font-semibold text-gray-900">Cancellation</span>
                                    </div>
                                    <div class="p-4 text-xs text-gray-600 bg-white leading-relaxed"><?php echo $hotel['policies']['cancellation']; ?></div>
                                </div>
                                <div class="border border-gray-100 rounded-xl overflow-hidden bg-white">
                                    <div class="flex items-center gap-3 p-4 bg-gray-50 border-b border-gray-100">
                                        <div class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm">
                                            <i class="ri-parent-line text-base text-teal-600"></i>
                                        </div><span class="text-sm font-semibold text-gray-900">Children</span>
                                    </div>
                                    <div class="p-4 text-xs text-gray-600 bg-white leading-relaxed"><?php echo $hotel['policies']['children']; ?></div>
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-bold text-gray-900">Guest Reviews</h2>
                                <div class="flex items-center gap-1 bg-amber-50 px-3 py-1.5 rounded-full">
                                    <i class="ri-star-fill text-amber-500 text-sm"></i><span
                                        class="text-sm font-bold text-amber-700"><?php echo $hotel['stars']; ?></span><span
                                        class="text-xs text-amber-600">/ 5</span>
                                    <span class="text-[10px] text-gray-400 ml-1">(<?php echo $hotel['reviews_count']; ?> reviews)</span>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <?php if (!empty($hotel['google_reviews'])): ?>
                                    <?php foreach ($hotel['google_reviews'] as $review): ?>
                                    <div class="bg-gray-50 rounded-xl p-5">
                                        <div class="flex items-start gap-3 mb-3">
                                            <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0">
                                                <img alt="<?php echo htmlspecialchars($review['author_name']); ?>" class="w-full h-full object-cover"
                                                    src="<?php echo $review['profile_photo_url'] ?? '../assets/images/hotel_details/user1.jpg'; ?>">
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between">
                                                    <h4 class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($review['author_name']); ?></h4>
                                                    <div class="flex items-center gap-0.5">
                                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                                            <i class="text-xs ri-star-fill <?php echo $i < $review['rating'] ? 'text-amber-400' : 'text-gray-300'; ?>"></i>
                                                        <?php
        endfor; ?>
                                                    </div>
                                                </div>
                                                <p class="text-xs text-gray-500"><?php echo date('F j, Y', $review['time']); ?></p>
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-700 leading-relaxed"><?php echo htmlspecialchars($review['text']); ?></p>
                                    </div>
                                    <?php
    endforeach; ?>
                                <?php
else: ?>
                                    <div class="bg-gray-50 rounded-xl p-5 text-center text-gray-500 text-sm">
                                        No recent reviews available.
                                    </div>
                                <?php
endif; ?>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="w-full lg:w-96 flex-shrink-0">
                    <div class="sticky top-28">
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-lg p-6">
                            <div class="flex items-center justify-between mb-5">
                                <div><span class="text-xs text-gray-500">From</span>
                                    <p class="text-2xl font-bold text-gray-900" id="basePriceDisplay">LKR <?php echo number_format($hotel['price_per_night']); ?><span
                                            class="text-sm font-normal text-gray-500">/night</span></p>
                                </div>
                                <div class="flex items-center gap-1 bg-amber-50 px-2.5 py-1 rounded-full">
                                    <i class="ri-star-fill text-amber-500 text-xs"></i>
                                    <span class="text-xs font-bold text-amber-700"><?php echo htmlspecialchars($hotel['stars']); ?></span>
                                    <span class="text-xs text-amber-500">(<?php echo $hotel['reviews_count']; ?>)</span>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="text-xs font-medium text-gray-600 mb-1.5 block">Room Type</label>
                                <select id="roomType" onchange="updateBookingData()"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent cursor-pointer">
                                    <?php foreach ($hotel['room_types'] as $room): ?>
                                    <option value="<?php echo htmlspecialchars($room['name']); ?>" data-price="<?php echo $room['price']; ?>">
                                        <?php echo htmlspecialchars($room['name']); ?> — LKR <?php echo number_format($room['price']); ?>
                                    </option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-600 mb-1.5 block">Check-in</label>
                                    <input id="checkIn" onchange="updateBookingData()"
                                        class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                        type="date" value="<?php echo $prefill_checkin; ?>">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-600 mb-1.5 block">Check-out</label>
                                    <input id="checkOut" onchange="updateBookingData()"
                                        class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                        type="date" value="<?php echo $prefill_checkout; ?>">
                                </div>
                            </div>
                            <div class="mb-5">
                                <label class="text-xs font-medium text-gray-600 mb-1.5 block">Guests</label>
                                <select id="guestCount" onchange="updateBookingData()"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent cursor-pointer">
                                    <?php foreach ([1, 2, 3, 4] as $g): ?>
                                    <option value="<?php echo $g; ?>" <?php echo($prefill_guests === $g) ? 'selected' : ''; ?>>
                                        <?php echo $g; ?> Guest<?php echo $g > 1 ? 's' : ''; ?>
                                    </option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>

                            <!-- Final Summary Area -->
                            <div id="bookingSummary" class="bg-teal-50/50 rounded-xl p-4 mb-5 border border-teal-100/50">
                                <div class="flex justify-between text-xs text-gray-600 mb-2">
                                    <span id="summaryRate">LKR <?php echo number_format($hotel['price_per_night']); ?> x 1 night</span>
                                    <span id="summarySubtotal">LKR <?php echo number_format($hotel['price_per_night']); ?></span>
                                </div>
                                <div class="flex justify-between font-bold text-gray-900 border-t border-teal-100 pt-2">
                                    <span>Total Price</span>
                                    <span id="summaryTotal">LKR <?php echo number_format($hotel['price_per_night']); ?></span>
                                </div>
                            </div>

                            <a id="reserveBtn" href="booking_confirmation.php?type=hotel&id=<?php echo $hotel['id']; ?>"
                                class="w-full py-3.5 bg-teal-600 text-white font-semibold rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer text-sm flex items-center justify-center shadow-md shadow-teal-200/50">Reserve
                                Now</a>
                            <div class="mt-4 flex items-center gap-2 justify-center text-xs text-gray-500">
                                <div class="w-4 h-4 flex items-center justify-center"><i
                                        class="ri-shield-check-line text-sm text-emerald-500"></i></div>
                                <span id="cancellationPolicy"><?php echo $hotel['policies']['cancellation']; ?></span>
                            </div>
                        </div>

                        <div class="mt-4 bg-white rounded-2xl border border-gray-200 p-5">
                            <h4 class="font-semibold text-gray-900 text-sm mb-3">Need Help?</h4>
                            <div class="space-y-3">
                                <?php if (!empty($hotel['phone'])): ?>
                                <a href="tel:<?php echo $hotel['phone']; ?>" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-teal-50 transition-colors group">
                                    <div class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm group-hover:bg-teal-100">
                                        <i class="ri-phone-line text-gray-600 group-hover:text-teal-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-500 uppercase font-bold">Call Property</p>
                                        <p class="text-xs font-semibold text-gray-800"><?php echo $hotel['phone']; ?></p>
                                    </div>
                                </a>
                                <?php
endif; ?>
                                <?php if (!empty($hotel['website'])): ?>
                                <a href="<?php echo $hotel['website']; ?>" target="_blank" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-teal-50 transition-colors group">
                                    <div class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm group-hover:bg-teal-100">
                                        <i class="ri-global-line text-gray-600 group-hover:text-teal-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-500 uppercase font-bold">Official Website</p>
                                        <p class="text-xs font-semibold text-gray-800 truncate max-w-[150px]">Visit Site</p>
                                    </div>
                                </a>
                                <?php
endif; ?>
                                <a href="#" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-teal-50 transition-colors group">
                                    <div class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm group-hover:bg-teal-100">
                                        <i class="ri-mail-line text-gray-600 group-hover:text-teal-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-500 uppercase font-bold">Send Inquiry</p>
                                        <p class="text-xs font-semibold text-gray-800">Support Team</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                        <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                href="#">Terms of Service</a></li>
                        <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                href="#">Privacy Policy</a></li>
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
    <!-- Photo Lightbox Modal -->
    <div id="photoModal" class="fixed inset-0 z-[100] hidden bg-black/95 backdrop-blur-sm">
        <div class="absolute top-6 right-6 z-[110]">
            <button onclick="closePhotoModal()" class="w-12 h-12 flex items-center justify-center bg-white/10 hover:bg-white/20 text-white rounded-full transition-all cursor-pointer">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>
        <?php if (!empty($hotel['google_photos'])): ?>
        <div class="h-full flex flex-col items-center justify-center px-4">
            <div class="relative w-full max-w-5xl h-[65vh] flex items-center justify-center">
                <button onclick="changeSlide(-1)" class="absolute left-0 lg:-left-24 w-12 h-12 flex items-center justify-center bg-white/10 hover:bg-white/20 text-white rounded-full transition-all cursor-pointer z-20">
                    <i class="ri-arrow-left-s-line text-2xl"></i>
                </button>
                
                <div class="w-full h-full flex items-center justify-center overflow-hidden rounded-3xl shadow-2xl bg-black/20">
                    <?php foreach ($hotel['google_photos'] as $index => $photo): ?>
                        <div class="photo-slide hidden w-full h-full relative">
                            <!-- Cinematic Blur Background for Portrait/Vertical Photos -->
                            <div class="absolute inset-0 z-0">
                                <img src="<?php echo $photo; ?>" class="w-full h-full object-cover blur-2xl opacity-40 scale-110">
                            </div>
                            <!-- Main Image -->
                            <img src="<?php echo $photo; ?>" class="relative z-10 w-full h-full object-contain" alt="Hotel photo <?php echo $index + 1; ?>">
                        </div>
                    <?php
    endforeach; ?>
                </div>

                <button onclick="changeSlide(1)" class="absolute right-0 lg:-right-24 w-12 h-12 flex items-center justify-center bg-white/10 hover:bg-white/20 text-white rounded-full transition-all cursor-pointer z-20">
                    <i class="ri-arrow-right-s-line text-2xl"></i>
                </button>
            </div>
            
            <div class="mt-8 flex gap-3 overflow-x-auto max-w-full py-2 px-4 no-scrollbar">
                <?php foreach ($hotel['google_photos'] as $index => $photo): ?>
                    <div class="w-20 h-14 flex-shrink-0 cursor-pointer rounded-lg overflow-hidden border-2 border-transparent transition-all thumbnail-btn" onclick="showSlide(<?php echo $index; ?>)">
                        <img src="<?php echo $photo; ?>" class="w-full h-full object-cover">
                    </div>
                <?php
    endforeach; ?>
            </div>
            
            <p class="text-white/60 text-sm mt-6 font-medium">
                Photo <span id="currentSlideNum">1</span> of <?php echo count($hotel['google_photos']); ?>
            </p>
        </div>
        <?php
else: ?>
        <div class="h-full flex items-center justify-center">
            <p class="text-white/50">No additional photos available.</p>
        </div>
        <?php
endif; ?>
    </div>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.photo-slide');
        const thumbnails = document.querySelectorAll('.thumbnail-btn');
        const currentSlideNum = document.getElementById('currentSlideNum');
        const modal = document.getElementById('photoModal');

        function openPhotoModal(index) {
            currentSlide = index;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            updateSlides();
        }

        function closePhotoModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function changeSlide(n) {
            currentSlide = (currentSlide + n + slides.length) % slides.length;
            updateSlides();
        }

        function showSlide(index) {
            currentSlide = index;
            updateSlides();
        }

        function updateSlides() {
            slides.forEach((slide, i) => {
                slide.classList.toggle('hidden', i !== currentSlide);
            });
            
            thumbnails.forEach((thumb, i) => {
                if (i === currentSlide) {
                    thumb.classList.add('border-teal-500', 'scale-110');
                    thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                } else {
                    thumb.classList.remove('border-teal-500', 'scale-110');
                }
            });
            
            currentSlideNum.textContent = currentSlide + 1;
        }

        // Close on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePhotoModal();
            if (e.key === 'ArrowLeft') changeSlide(-1);
            if (e.key === 'ArrowRight') changeSlide(1);
        });

        // Booking Calculation Logic
        function updateBookingData() {
            const checkIn = new Date(document.getElementById('checkIn').value);
            const checkOut = new Date(document.getElementById('checkOut').value);
            const roomSelect = document.getElementById('roomType');
            const guestCount = document.getElementById('guestCount').value;
            const selectedOption = roomSelect.options[roomSelect.selectedIndex];
            const pricePerNight = parseInt(selectedOption.getAttribute('data-price'));
            
            // Validate Dates
            if (checkOut <= checkIn) {
                const tomorrow = new Date(checkIn);
                tomorrow.setDate(tomorrow.getDate() + 1);
                document.getElementById('checkOut').value = tomorrow.toISOString().split('T')[0];
                updateBookingData();
                return;
            }

            // Calculate Nights
            const diffTime = Math.abs(checkOut - checkIn);
            const diffNights = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            // Calculate Totals
            const totalCost = pricePerNight * diffNights;
            
            // Update UI
            document.getElementById('basePriceDisplay').innerHTML = `LKR ${pricePerNight.toLocaleString()}<span class="text-sm font-normal text-gray-500">/night</span>`;
            document.getElementById('summaryRate').innerText = `LKR ${pricePerNight.toLocaleString()} x ${diffNights} night${diffNights > 1 ? 's' : ''}`;
            document.getElementById('summarySubtotal').innerText = `LKR ${totalCost.toLocaleString()}`;
            document.getElementById('summaryTotal').innerText = `LKR ${totalCost.toLocaleString()}`;
            
            // Update Reserve Button Link
            const baseUrl = document.getElementById('reserveBtn').getAttribute('href').split('?')[0];
            const params = new URLSearchParams({
                type: 'hotel',
                id: '<?php echo $hotel['id']; ?>',
                room: roomSelect.value,
                checkin: document.getElementById('checkIn').value,
                checkout: document.getElementById('checkOut').value,
                guests: guestCount,
                total: totalCost
            });
            <?php if ($plan_id): ?>
            params.append('plan_id', '<?php echo $plan_id; ?>');
            <?php
endif; ?>
            document.getElementById('reserveBtn').setAttribute('href', `${baseUrl}?${params.toString()}`);
        }

        // Initialize on Load
        document.addEventListener('DOMContentLoaded', updateBookingData);
    </script>
</body>

</html>
