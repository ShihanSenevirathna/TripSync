<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$vehicle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : null;
// Pre-fill dates and passengers from itinerary builder context
$prefill_checkin = isset($_GET['checkin']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['checkin']) ? $_GET['checkin'] : date('Y-m-d');
$prefill_checkout = isset($_GET['checkout']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['checkout']) ? $_GET['checkout'] : date('Y-m-d', strtotime('+3 days'));
$prefill_guests = isset($_GET['guests']) && is_numeric($_GET['guests']) ? (int)$_GET['guests'] : 1;

if ($vehicle_id <= 0) {
    header('Location: marketplace.php');
    exit;
}

$stmt = $conn->prepare("SELECT v.*, u.name as owner_name, u.profile_pic as owner_pic FROM vehicles v LEFT JOIN users u ON v.owner_id = u.id WHERE v.id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result->fetch_assoc();

if (!$vehicle) {
    header('Location: marketplace.php');
    exit;
}

$features = array_map('trim', explode(',', $vehicle['features']));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vehicle['model']); ?> - TripSync</title>
    <meta name="description"
        content="Rent a Toyota KDH Van for your Sri Lanka trip. Spacious, reliable, and professional driver included.">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places"></script>
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
                <span class="capitalize">vehicles</span>
                <i class="ri-arrow-right-s-line text-gray-400"></i>
                <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($vehicle['model']); ?></span>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-4 grid-rows-2 gap-2 h-[420px] rounded-2xl overflow-hidden shadow-lg">
                <div class="col-span-2 row-span-2 relative group overflow-hidden">
                    <img alt="<?php echo htmlspecialchars($vehicle['model']); ?> main"
                        class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-700"
                        src="../assets/images/<?php echo htmlspecialchars($vehicle['image_path']); ?>">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                </div>
                <div class="relative group overflow-hidden">
                    <img alt="Toyota KDH Van photo 2"
                        class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500"
                        src="../assets/images/vehicle_kdh_van.jpg">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                </div>
                <div class="relative group overflow-hidden">
                    <img alt="Toyota KDH Van photo 3"
                        class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500"
                        src="../assets/images/toyota_kdh.jpg">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                </div>
                <div class="relative group overflow-hidden">
                    <img alt="Toyota KDH Van photo 4"
                        class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500"
                        src="../assets/images/vehicle_nissan_caravan.jpg">
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                </div>
                <div class="relative group overflow-hidden">
                    <img alt="Toyota KDH Van photo 5"
                        class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500"
                        src="../assets/images/vehicle_prius.jpg">
                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                        <span class="text-white text-sm font-medium whitespace-nowrap">+2 more</span>
                    </div>
                </div>
            </div>
            <div class="flex justify-end mt-3 pr-2">
                <button
                    class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer whitespace-nowrap shadow-sm">
                    <div class="w-4 h-4 flex items-center justify-center"><i class="ri-image-line text-sm"></i></div>
                    View All Photos
                </button>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="flex flex-col lg:flex-row gap-8 pb-16">
                <div class="flex-1 min-w-0">
                    <div>
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-2">
                                <span
                                    class="bg-orange-100 text-orange-700 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo ucfirst(htmlspecialchars($vehicle['type'])); ?></span>
                                <span class="text-xs text-gray-500"><?php echo htmlspecialchars($vehicle['color']); ?></span>
                            </div>
                            <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($vehicle['model']); ?></h1>
                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <span class="flex items-center gap-1.5">
                                    <div class="w-4 h-4 flex items-center justify-center"><i
                                            class="ri-user-line text-sm text-gray-400"></i></div><?php echo htmlspecialchars($vehicle['capacity']); ?> passengers
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <div class="w-4 h-4 flex items-center justify-center"><i
                                            class="ri-luggage-cart-line text-sm text-gray-400"></i></div><?php echo rand(2, 8); ?> luggage
                                </span>
                                <span class="flex items-center gap-1"><i
                                        class="ri-star-fill text-amber-500 text-sm"></i><strong
                                        class="text-gray-900"><?php echo htmlspecialchars($vehicle['rating']); ?></strong><span class="text-gray-400">(<?php echo htmlspecialchars($vehicle['reviews_count']); ?>
                                        reviews)</span></span>
                            </div>
                        </div>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">About This Vehicle</h2>
                            <p class="text-sm text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($vehicle['description'])); ?></p>
                            <button
                                class="text-teal-600 text-sm font-medium mt-2 cursor-pointer hover:text-teal-700 flex items-center gap-1">Read
                                More<div class="w-4 h-4 flex items-center justify-center"><i
                                        class="ri-arrow-down-s-line text-sm"></i></div></button>
                        </section>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Included Features</h2>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($features as $feature): ?>
                                <span
                                    class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 rounded-xl text-sm text-gray-700 font-medium">
                                    <div class="w-4 h-4 flex items-center justify-center"><i
                                            class="ri-check-line text-sm text-teal-600"></i></div><?php echo htmlspecialchars($feature); ?>
                                </span>
                                <?php
endforeach; ?>
                            </div>
                        </section>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Specifications</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div class="p-4 bg-gray-50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-5 h-5 flex items-center justify-center"><i
                                                class="ri-settings-3-line text-sm text-gray-400"></i></div><span
                                            class="text-xs text-gray-500">Reg No</span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 pl-7"><?php echo htmlspecialchars($vehicle['reg_number']); ?></p>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-5 h-5 flex items-center justify-center"><i
                                                class="ri-gas-station-line text-sm text-gray-400"></i></div><span
                                            class="text-xs text-gray-500">Color</span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 pl-7"><?php echo htmlspecialchars($vehicle['color']); ?></p>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-5 h-5 flex items-center justify-center"><i
                                                class="ri-calendar-line text-sm text-gray-400"></i></div><span
                                            class="text-xs text-gray-500">Year</span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 pl-7"><?php echo htmlspecialchars($vehicle['year']); ?></p>
                                </div>
                            </div>
                        </section>

                        <section class="mb-10">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">Your Driver</h2>
                            <div
                                class="bg-gradient-to-r from-teal-50 to-emerald-50 rounded-2xl p-6 border border-teal-100">
                                <div class="flex items-start gap-4">
                                    <div
                                        class="w-16 h-16 rounded-full overflow-hidden flex-shrink-0 border-2 border-white shadow-md">
                                        <img alt="<?php echo htmlspecialchars($vehicle['owner_name']); ?>" class="w-full h-full object-cover"
                                            src="<?php echo getProfilePic($vehicle['owner_pic'], '../'); ?>">
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($vehicle['owner_name']); ?></h3>
                                        <div class="flex items-center gap-1 mt-0.5 mb-2">
                                            <i class="ri-star-fill text-amber-500 text-sm"></i><span
                                                class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($vehicle['rating']); ?></span><span
                                                class="text-xs text-gray-500">· <?php echo htmlspecialchars($vehicle['reviews_count']); ?> trips</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs text-gray-600">
                                            <span><i class="ri-time-line mr-1"></i>12 years experience</span>
                                            <span><i class="ri-translate-2 mr-1"></i>English, Sinhala, Tamil</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-bold text-gray-900">Guest Reviews</h2>
                                <div class="flex items-center gap-1 bg-amber-50 px-3 py-1.5 rounded-full">
                                    <i class="ri-star-fill text-amber-500 text-sm"></i><span
                                        class="text-sm font-bold text-amber-700">4.7</span><span
                                        class="text-xs text-amber-600">/ 5</span>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-xl p-5">
                                    <div class="flex items-start gap-3 mb-3">
                                        <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0"><img
                                                alt="Mark Johnson" class="w-full h-full object-cover"
                                                src="../assets/images/hotel_details/user1.jpg"></div>
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <h4 class="font-semibold text-gray-900 text-sm">Mark Johnson</h4>
                                                <div class="flex items-center gap-0.5"><i
                                                        class="text-xs ri-star-fill text-amber-400"></i><i
                                                        class="text-xs ri-star-fill text-amber-400"></i><i
                                                        class="text-xs ri-star-fill text-amber-400"></i><i
                                                        class="text-xs ri-star-fill text-amber-400"></i><i
                                                        class="text-xs ri-star-fill text-amber-400"></i></div>
                                            </div>
                                            <p class="text-xs text-gray-500">January 20, 2025</p>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-700 leading-relaxed">Chaminda was an incredible driver
                                        and guide. He knew all the best spots and was always on time. The van was
                                        spotless and very comfortable for our 5-day tour.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="w-full lg:w-96 flex-shrink-0">
                    <div class="sticky top-28">
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-lg p-6">
                            <div class="flex items-center justify-between mb-5">
                                <div><span class="text-xs text-gray-500" id="priceDetailText">From</span>
                                    <p class="text-2xl font-bold text-gray-900" id="totalPriceDisplay">LKR <?php echo number_format($vehicle['price_per_day']); ?><span
                                            class="text-sm font-normal text-gray-500">/day</span></p>
                                </div>
                                <div class="flex items-center gap-1 bg-amber-50 px-2.5 py-1 rounded-full"><i
                                        class="ri-star-fill text-amber-500 text-xs"></i><span
                                        class="text-xs font-bold text-amber-700"><?php echo htmlspecialchars($vehicle['rating']); ?></span><span
                                        class="text-xs text-amber-500">(<?php echo htmlspecialchars($vehicle['reviews_count']); ?>)</span></div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div><label class="text-xs font-medium text-gray-600 mb-1.5 block">Pick-up</label><input
                                        id="pickupDate"
                                        class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                        type="date" value="<?php echo $prefill_checkin; ?>" onchange="updateVehicleBooking()"></div>
                                <div><label class="text-xs font-medium text-gray-600 mb-1.5 block">Return</label><input
                                        id="returnDate"
                                        class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                        type="date" value="<?php echo $prefill_checkout; ?>" onchange="updateVehicleBooking()"></div>
                            </div>
                            <?php if (!$plan_id): ?>
                            <div class="space-y-4 mb-5">
                                <div>
                                    <div class="flex justify-between items-center mb-1.5">
                                        <label class="text-xs font-semibold text-gray-700">Pick-up Location</label>
                                        <button type="button" onclick="detectLocation('pickupLocation')" class="text-[10px] text-teal-600 hover:text-teal-700 font-bold flex items-center gap-1">
                                            <i class="ri-navigation-line"></i> Use Current Location
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                                            <i class="ri-map-pin-line text-xs"></i>
                                        </div>
                                        <input id="pickupLocation" type="text" placeholder="Enter starting point" onchange="updateVehicleBooking()"
                                            class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all">
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1.5">
                                        <label class="text-xs font-semibold text-gray-700">Drop-off Location</label>
                                        <button type="button" onclick="detectLocation('dropoffLocation')" class="text-[10px] text-teal-600 hover:text-teal-700 font-bold flex items-center gap-1">
                                            <i class="ri-navigation-line"></i> Use Current Location
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                                            <i class="ri-flag-line text-xs"></i>
                                        </div>
                                        <input id="dropoffLocation" type="text" placeholder="Enter destination" onchange="updateVehicleBooking()"
                                            class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all">
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="mb-5">
                                <label class="text-xs font-medium text-gray-600 mb-1.5 block">Passengers</label>
                                <select id="passengerCount" onchange="updateVehicleBooking()"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent cursor-pointer">
                                    <?php foreach ([1, 2, 4, 8] as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo($prefill_guests >= $p && ($prefill_guests < ($p == 1 ? 2 : ($p == 2 ? 4 : ($p == 4 ? 8 : 99))))) ? 'selected' : ''; ?>>
                                        <?php echo $p; ?> Passenger<?php echo $p > 1 ? 's' : ''; ?>
                                    </option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>
                            <a id="rentNowBtn" href="booking_confirmation.php?type=vehicle&id=<?php echo $vehicle['id']; ?>&total=<?php echo $vehicle['price_per_day']; ?><?php echo $plan_id ? '&plan_id=' . $plan_id : ''; ?>"
                                class="w-full py-3.5 bg-teal-600 text-white font-semibold rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer text-sm flex items-center justify-center">Rent
                                Now</a>
                            <div class="mt-4 flex items-center gap-2 justify-center text-xs text-gray-500">
                                <div class="w-4 h-4 flex items-center justify-center"><i
                                        class="ri-shield-check-line text-sm text-emerald-500"></i></div>Free
                                cancellation up to 24 hours
                            </div>
                        </div>

                        <div class="mt-4 bg-white rounded-2xl border border-gray-200 p-5">
                            <h4 class="font-semibold text-gray-900 text-sm mb-3">Need Help?</h4>
                            <div class="space-y-2">
                                <a href="tel:+94112345678"
                                    class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer">
                                    <div class="w-8 h-8 flex items-center justify-center bg-teal-100 rounded-lg"><i
                                            class="ri-phone-line text-base text-teal-600"></i></div>
                                    <span class="text-sm text-gray-700">Call Support</span>
                                </a>
                                <a href="mailto:info@tripsync.lk"
                                    class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer">
                                    <div class="w-8 h-8 flex items-center justify-center bg-teal-100 rounded-lg"><i
                                            class="ri-mail-line text-base text-teal-600"></i></div>
                                    <span class="text-sm text-gray-700">Send Inquiry</span>
                                </a>
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
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-base mb-4">Support</h3>
                    <ul class="space-y-2">
                        <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                href="help.php">Help Center</a></li>
                        <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                href="#">Terms of Service</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-base mb-4">Connect With Us</h3>
                    <div class="flex gap-3 mb-4">
                        <a href="#"
                            class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                class="ri-facebook-fill text-lg"></i></a>
                        <a href="#"
                            class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                class="ri-instagram-line text-lg"></i></a>
                    </div>
                    <p class="text-teal-50 text-sm"><i class="ri-mail-line mr-2"></i>info@tripsync.lk</p>
                </div>
            </div>
            <div class="border-t border-teal-500/30 mt-8 pt-6 text-center text-teal-50 text-sm">
                <p>&copy; 2026 TripSync. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        let cachedDistance = null;

        async function updateVehicleBooking() {
            const pickupDateInput = document.getElementById('pickupDate');
            const returnDateInput = document.getElementById('returnDate');
            const pickupDate = new Date(pickupDateInput.value);
            const returnDate = new Date(returnDateInput.value);
            const pricePerDay = <?php echo $vehicle['price_per_day']; ?>;
            const baseFare = <?php echo $vehicle['base_fare'] ?? 500; ?>;
            const pricePerKm = <?php echo $vehicle['price_per_km'] ?? 80; ?>;
            const passengerCount = document.getElementById('passengerCount').value;
            const hasPlan = <?php echo $plan_id ? 'true' : 'false'; ?>;
            const planId = <?php echo $plan_id ?? 0; ?>;
            
            // Validate Dates
            if (returnDate <= pickupDate) {
                const tomorrow = new Date(pickupDate);
                tomorrow.setDate(tomorrow.getDate() + 1);
                returnDateInput.value = tomorrow.toISOString().split('T')[0];
                updateVehicleBooking();
                return;
            }

            // Calculate Days (minimum 1 day)
            const diffTime = Math.abs(returnDate - pickupDate);
            const diffDays = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
            
            let totalCost = 0;
            let displayDetails = "Calculating distance...";

            if (hasPlan) {
                if (cachedDistance === null) {
                    try {
                        const res = await fetch(`../api/get_itinerary_distance.php?plan_id=${planId}`);
                        const data = await res.json();
                        if (data.success && data.distance > 0) {
                            cachedDistance = data.distance;
                        } else {
                            cachedDistance = diffDays * 120; // Fallback estimate
                        }
                    } catch (e) {
                        cachedDistance = diffDays * 120; // Fallback estimate
                    }
                }
                
                totalCost = baseFare + (cachedDistance * pricePerKm);
                displayDetails = `Total Itinerary: ${cachedDistance} KM`;
            } else {
                totalCost = pricePerDay * diffDays;
                displayDetails = `${diffDays} Day Rental`;
            }
            
            // Update UI
            document.getElementById('totalPriceDisplay').innerText = `LKR ${Math.round(totalCost).toLocaleString()}`;
            document.getElementById('priceDetailText').innerText = displayDetails;

            // Capture Locations if no plan
            const pickupLocInput = document.getElementById('pickupLocation');
            const dropoffLocInput = document.getElementById('dropoffLocation');
            
            // Update Reserve Button Link
            const rentBtn = document.getElementById('rentNowBtn');
            const baseUrl = rentBtn.getAttribute('href').split('?')[0];
            const params = new URLSearchParams({
                type: 'vehicle',
                id: '<?php echo $vehicle['id']; ?>',
                checkin: pickupDateInput.value,
                checkout: returnDateInput.value,
                guests: passengerCount,
                total: totalCost.toFixed(2)
            });

            if (pickupLocInput && pickupLocInput.value) params.append('pickup', pickupLocInput.value);
            if (dropoffLocInput && dropoffLocInput.value) params.append('dropoff', dropoffLocInput.value);

            <?php if ($plan_id): ?>
            params.append('plan_id', '<?php echo $plan_id; ?>');
            <?php
endif; ?>
            
            rentBtn.setAttribute('href', `${baseUrl}?${params.toString()}`);
        }

        // Initialize on Load
        document.addEventListener('DOMContentLoaded', () => {
            updateVehicleBooking();
            initAutocomplete();
        });

        function initAutocomplete() {
            const pickupInput = document.getElementById('pickupLocation');
            const dropoffInput = document.getElementById('dropoffLocation');

            if (pickupInput && typeof google !== 'undefined') {
                new google.maps.places.Autocomplete(pickupInput, {
                    componentRestrictions: { country: 'lk' },
                    fields: ['formatted_address', 'geometry', 'name']
                }).addListener('place_changed', updateVehicleBooking);
            }

            if (dropoffInput && typeof google !== 'undefined') {
                new google.maps.places.Autocomplete(dropoffInput, {
                    componentRestrictions: { country: 'lk' },
                    fields: ['formatted_address', 'geometry', 'name']
                }).addListener('place_changed', updateVehicleBooking);
            }
        }

        function detectLocation(targetId) {
            const input = document.getElementById(targetId);
            if (!navigator.geolocation) {
                alert("Geolocation is not supported by your browser");
                return;
            }

            input.placeholder = "Detecting location...";
            input.disabled = true;

            navigator.geolocation.getCurrentPosition((position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                if (typeof google !== 'undefined') {
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                        input.disabled = false;
                        if (status === "OK" && results[0]) {
                            input.value = results[0].formatted_address;
                            updateVehicleBooking();
                        } else {
                            input.value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                        }
                    });
                } else {
                    input.disabled = false;
                    input.value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                }
            }, (error) => {
                input.disabled = false;
                input.placeholder = "Enter location";
                alert("Error getting location: " + error.message);
            });
        }
    </script>
</body>

</html>
