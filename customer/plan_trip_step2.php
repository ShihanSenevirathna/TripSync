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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recommended Destinations - TripSync</title>
  <meta name="description" content="Based on your preferences, we suggest these amazing places.">
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
        <div class="max-w-5xl mx-auto">
          <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Create Your Perfect Trip</h1>
            <p class="text-lg text-gray-600">Let's plan an unforgettable journey through Sri Lanka</p>
          </div>
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
                <div class="w-16 h-1 mx-2 bg-gray-200"></div>
              </div>
              <div class="flex items-center">
                <div
                  class="w-10 h-10 flex items-center justify-center rounded-full font-semibold transition-all bg-gray-200 text-gray-500">
                  3</div>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-200">
            <div>
              <h2 class="text-2xl font-bold text-gray-900 mb-6">Recommended Destinations</h2>
              <p class="text-gray-600 mb-6">Based on your preferences, we suggest these amazing places</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition-shadow">
                  <div class="w-full h-48"><img alt="Sigiriya" class="w-full h-full object-cover"
                      src="../assets/images/sigiriya_step3.jpg"></div>
                  <div class="p-4">
                    <div class="flex items-start justify-between mb-2">
                      <div>
                        <h3 class="font-semibold text-gray-900 mb-1">Sigiriya</h3>
                        <p class="text-xs text-gray-600"><i class="ri-map-pin-line mr-1"></i>Matale</p>
                      </div><span
                        class="bg-teal-100 text-teal-700 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap">Historical</span>
                    </div>
                    <p class="text-sm text-gray-600">Ancient rock fortress with stunning frescoes</p>
                  </div>
                </div>
                <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition-shadow">
                  <div class="w-full h-48"><img alt="Ella" class="w-full h-full object-cover"
                      src="../assets/images/ella_step3.jpg"></div>
                  <div class="p-4">
                    <div class="flex items-start justify-between mb-2">
                      <div>
                        <h3 class="font-semibold text-gray-900 mb-1">Ella</h3>
                        <p class="text-xs text-gray-600"><i class="ri-map-pin-line mr-1"></i>Badulla</p>
                      </div><span
                        class="bg-teal-100 text-teal-700 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap">Nature</span>
                    </div>
                    <p class="text-sm text-gray-600">Scenic hill country with tea plantations</p>
                  </div>
                </div>
                <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition-shadow">
                  <div class="w-full h-48"><img alt="Mirissa" class="w-full h-full object-cover"
                      src="../assets/images/mirissa_step3.jpg"></div>
                  <div class="p-4">
                    <div class="flex items-start justify-between mb-2">
                      <div>
                        <h3 class="font-semibold text-gray-900 mb-1">Mirissa</h3>
                        <p class="text-xs text-gray-600"><i class="ri-map-pin-line mr-1"></i>Matara</p>
                      </div><span
                        class="bg-teal-100 text-teal-700 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap">Beach</span>
                    </div>
                    <p class="text-sm text-gray-600">Beautiful beaches and whale watching</p>
                  </div>
                </div>
                <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition-shadow">
                  <div class="w-full h-48"><img alt="Yala National Park" class="w-full h-full object-cover"
                      src="../assets/images/yala_step3.jpg"></div>
                  <div class="p-4">
                    <div class="flex items-start justify-between mb-2">
                      <div>
                        <h3 class="font-semibold text-gray-900 mb-1">Yala National Park</h3>
                        <p class="text-xs text-gray-600"><i class="ri-map-pin-line mr-1"></i>Hambantota</p>
                      </div><span
                        class="bg-teal-100 text-teal-700 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap">Wildlife</span>
                    </div>
                    <p class="text-sm text-gray-600">Wildlife safari and leopard spotting</p>
                  </div>
                </div>
              </div>
              <div class="flex justify-between"><a href="plan_trip.php?edit=<?php echo $planId; ?>"
                  class="px-8 py-3 border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap cursor-pointer">Back</a><a
                  href="itinerary-builder.php?plan_id=<?php echo $planId; ?>"
                  class="px-8 py-3 bg-teal-600 text-white text-sm font-semibold rounded-lg hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer">Create
                  Trip & Start Planning</a></div>
            </div>
          </div>
        </div>
      </div>
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
