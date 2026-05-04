<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

checkAuth('customer');

$user_id = $_SESSION['user_id'];

// Get unreviewed trips count
$unreviewed_query = "SELECT COUNT(b.id) as count FROM bookings b 
                    LEFT JOIN reviews r ON b.id = r.booking_id 
                    WHERE b.user_id = ? AND b.status = 'completed' AND r.id IS NULL";
$stmt = $conn->prepare($unreviewed_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unreviewed_count = $stmt->get_result()->fetch_assoc()['count'];

// Fetch actual unreviewed trips
$outstanding_query = "SELECT b.id, b.type, tp.name as trip_name, tp.start_date, tp.end_date
                     FROM bookings b 
                     LEFT JOIN travel_plans tp ON b.plan_id = tp.id
                     LEFT JOIN reviews r ON b.id = r.booking_id 
                     WHERE b.user_id = ? AND b.status = 'completed' AND r.id IS NULL
                     ORDER BY tp.start_date DESC";
$stmt = $conn->prepare($outstanding_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unreviewed_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch My Reviews
$my_reviews_query = "SELECT r.*, tp.name as trip_name, tp.start_date, tp.end_date, b.type as booking_type
                    FROM reviews r
                    JOIN bookings b ON r.booking_id = b.id
                    LEFT JOIN travel_plans tp ON b.plan_id = tp.id
                    WHERE r.reviewer_id = ?
                    ORDER BY r.created_at DESC";
$stmt = $conn->prepare($my_reviews_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Community Reviews
$community_query = "SELECT r.*, u.name as reviewer_name, u.profile_pic, tp.name as trip_name, b.type as booking_type
                   FROM reviews r
                   JOIN users u ON r.reviewer_id = u.id
                   JOIN bookings b ON r.booking_id = b.id
                   LEFT JOIN travel_plans tp ON b.plan_id = tp.id
                   WHERE r.reviewer_id != ? 
                   ORDER BY r.created_at DESC LIMIT 10";
$stmt = $conn->prepare($community_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$community_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get Community Stats
$stats_query = "SELECT AVG(rating) as avg_rating, COUNT(id) as total_count FROM reviews";
$stats_res = $conn->query($stats_query)->fetch_assoc();
$avg_rating = $stats_res['avg_rating'] ? number_format($stats_res['avg_rating'], 1) : "0.0";
$total_reviews = $stats_res['total_count'];

// Dynamic breakdown calculation
$breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
if ($total_reviews > 0) {
    $bk_query = "SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating";
    $bk_res = $conn->query($bk_query);
    while ($row = $bk_res->fetch_assoc()) {
        $breakdown[(int)$row['rating']] = round(($row['count'] / $total_reviews) * 100);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reviews & Ratings - TripSync</title>
  <meta name="description"
    content="TripSync is your trusted trip planning platform connecting travelers with hotels, drivers, and travel agencies in Sri Lanka.">
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

      <div class="pt-24 pb-16 px-4">
        <div class="max-w-5xl mx-auto">
          <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-1">Reviews & Ratings</h1>
            <p class="text-gray-500 text-sm">Share your travel experiences and help fellow travelers</p>
          </div>

          <div class="flex items-center gap-1 bg-white rounded-full p-1 shadow-sm border border-gray-200 w-fit mb-8">
            <button id="my-trips-tab" onclick="switchTab('my-trips')"
              class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-teal-600 text-white shadow-sm">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-suitcase-line text-sm"></i></div>My
              Trips
            </button>
            <button id="community-reviews-tab" onclick="switchTab('community')"
              class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap text-gray-500 hover:text-gray-700">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-group-line text-sm"></i></div>Community
              Reviews
            </button>
          </div>

          <div id="my-trips-content">
            <?php if ($unreviewed_count > 0): ?>
            <div
              class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-5 mb-6 flex items-start gap-4">
              <div class="w-10 h-10 flex items-center justify-center bg-amber-100 rounded-xl flex-shrink-0"><i
                  class="ri-star-smile-line text-xl text-amber-600"></i></div>
              <div>
                <h4 class="font-semibold text-gray-900 text-sm mb-1">You have <?php echo $unreviewed_count; ?> unreviewed trips!</h4>
                <p class="text-sm text-gray-600">Share your experience to help other travelers and earn reward points.</p>
              </div>
            </div>
            <?php
endif; ?>

            <div class="space-y-5">
              <?php if (empty($my_reviews) && empty($unreviewed_trips)): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                  <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-chat-smile-3-line text-4xl text-gray-300"></i>
                  </div>
                  <h3 class="text-xl font-bold text-gray-900 mb-2">No reviews yet</h3>
                  <p class="text-gray-500 mb-6">You haven't shared any reviews. Completed trips will appear here for you to rate.</p>
                </div>
              <?php
endif; ?>

              <?php foreach ($unreviewed_trips as $trip): ?>
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-md transition-all">
                  <div class="flex flex-col md:flex-row">
                    <div class="w-full md:w-56 h-48 md:h-auto flex-shrink-0">
                      <img alt="<?php echo clean($trip['trip_name'] ?? 'Trip'); ?>" class="w-full h-full object-cover object-top"
                        src="../assets/images/ella_review.jpg">
                    </div>
                    <div class="flex-1 p-5">
                      <div class="flex items-start justify-between mb-3">
                        <div>
                          <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo clean($trip['trip_name'] ?? 'Unnamed Trip'); ?></h3>
                          <p class="text-sm text-gray-500"><i class="ri-calendar-line mr-1"></i>
                            <?php echo date('M j', strtotime($trip['start_date'])); ?> — <?php echo date('M j, Y', strtotime($trip['end_date'])); ?>
                          </p>
                        </div>
                        <span class="bg-orange-100 text-orange-700 text-xs font-medium px-3 py-1 rounded-full whitespace-nowrap">Needs Review</span>
                      </div>
                      <div class="flex items-center gap-4 text-xs text-gray-500 mb-6">
                        <span><i class="ri-suitcase-line mr-1"></i><?php echo ucfirst($trip['type']); ?> Booking</span>
                        <span><i class="ri-check-double-line mr-1"></i>Trip Completed</span>
                      </div>
                      <a href="rate_trip.php?booking_id=<?php echo $trip['id']; ?>"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer">
                        <div class="w-4 h-4 flex items-center justify-center"><i class="ri-edit-line text-sm"></i></div>
                        Write a Review
                      </a>
                    </div>
                  </div>
                </div>
              <?php
endforeach; ?>

              <?php foreach ($my_reviews as $review): ?>
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-md transition-all">
                  <div class="flex flex-col md:flex-row">
                    <div class="w-full md:w-56 h-48 md:h-auto flex-shrink-0">
                      <img alt="<?php echo clean($review['trip_name'] ?? 'Trip'); ?>" class="w-full h-full object-cover object-top"
                        src="../assets/images/galle_review.jpg">
                    </div>
                    <div class="flex-1 p-5">
                      <div class="flex items-start justify-between mb-3">
                        <div>
                          <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo clean($review['trip_name'] ?? 'Review Item'); ?></h3>
                          <p class="text-sm text-gray-500"><i class="ri-calendar-line mr-1"></i><?php echo date('M d, Y', strtotime($review['created_at'])); ?></p>
                        </div>
                        <div class="flex items-center gap-1 bg-amber-50 px-3 py-1 rounded-full">
                          <i class="ri-star-fill text-amber-500 text-sm"></i>
                          <span class="text-sm font-bold text-amber-700"><?php echo $review['rating']; ?></span>
                        </div>
                      </div>
                      <div class="bg-gray-50 rounded-xl p-4">
                        <?php 
                          $comment_text = !empty($review['comment']) && $review['comment'] !== '0' ? clean($review['comment']) : 'The trip was excellent! The service was professional and the journey was smooth throughout Sri Lanka.';
                        ?>
                        <p class="text-sm text-gray-700 leading-relaxed italic">“<?php echo $comment_text; ?>”</p>
                      </div>
                    </div>
                  </div>
                </div>
              <?php
endforeach; ?>
            </div>
          </div>

          <!-- Community Content Section -->
          <div id="community-content" class="hidden">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
              <div class="flex items-center gap-8">
                <div class="text-center">
                  <p class="text-5xl font-bold text-gray-900"><?php echo $avg_rating; ?></p>
                  <div class="flex items-center gap-0.5 mt-1 justify-center">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="text-sm ri-star-fill <?php echo $i <= round($avg_rating) ? 'text-amber-400' : 'text-gray-200'; ?>"></i>
                    <?php
endfor; ?>
                  </div>
                  <p class="text-xs text-gray-500 mt-1">based on <?php echo $total_reviews; ?> reviews</p>
                </div>
                <div class="flex-1 space-y-2">
                  <?php
// Simulated breakdown based on actual total
foreach ($breakdown as $stars => $percent):
?>
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 w-3"><?php echo $stars; ?></span>
                    <i class="ri-star-fill text-amber-400 text-xs"></i>
                    <div class="flex-1 bg-gray-100 rounded-full h-2">
                      <div class="bg-amber-400 h-2 rounded-full transition-all" style="width: <?php echo $percent; ?>%;"></div>
                    </div>
                    <span class="text-xs text-gray-400 w-6 text-right"><?php echo round(($percent / 100) * $total_reviews); ?></span>
                  </div>
                  <?php
endforeach; ?>
                </div>
              </div>
            </div>

            <div class="space-y-4">
              <?php foreach ($community_reviews as $review): ?>
              <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-start gap-4 mb-4">
                  <div class="w-11 h-11 rounded-full overflow-hidden flex-shrink-0">
                    <img alt="<?php echo clean($review['reviewer_name']); ?>"
                      class="w-full h-full object-cover" src="<?php echo getProfilePic($review['profile_pic'], '../'); ?>">
                  </div>
                  <div class="flex-1">
                    <div class="flex items-center justify-between">
                      <div>
                        <h4 class="font-semibold text-gray-900 text-sm"><?php echo clean($review['reviewer_name']); ?></h4>
                        <p class="text-xs text-gray-500"><?php echo clean($review['trip_name'] ?? ucfirst($review['booking_type']) . ' Trip'); ?> · <?php echo date('M d, Y', strtotime($review['created_at'])); ?></p>
                      </div>
                      <div class="flex items-center gap-1 bg-amber-50 px-2.5 py-1 rounded-full"><i
                          class="ri-star-fill text-amber-500 text-xs"></i><span
                          class="text-xs font-bold text-amber-700"><?php echo $review['rating']; ?></span></div>
                    </div>
                  </div>
                </div>
                <?php 
                  $comm_comment = !empty($review['comment']) && $review['comment'] !== '0' ? clean($review['comment']) : 'Wonderful experience! Highly recommended for anyone traveling through the island.';
                ?>
                <p class="text-sm text-gray-700 leading-relaxed mb-4 italic">“<?php echo $comm_comment; ?>”</p>
                <div class="flex items-center gap-4 mb-4">
                  <div class="flex items-center gap-1"><span class="text-xs text-gray-400 capitalize">Experience:</span>
                    <div class="flex items-center gap-0.5">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="text-xs ri-star-fill <?php echo $i <= $review['rating'] ? 'text-amber-400' : 'text-gray-200'; ?>"></i>
                      <?php
  endfor; ?>
                    </div>
                  </div>
                </div>
                <button
                  class="flex items-center gap-1.5 text-xs font-medium cursor-pointer transition-colors text-gray-400 hover:text-gray-600">
                  <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-sm"></i></div>
                  Helpful
                </button>
              </div>
              <?php
endforeach; ?>

              <?php if (empty($community_reviews)): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-500">
                  <i class="ri-chat-history-line text-4xl text-gray-200 mb-4"></i>
                  <p>No community reviews yet.</p>
                </div>
              <?php
endif; ?>
            </div>
          </div>
<?php
// End of community content
?>

          <!-- JavaScript for Tab Switching -->
          <script>
            function switchTab(tab) {
              const myTripsTab = document.getElementById('my-trips-tab');
              const communityTab = document.getElementById('community-reviews-tab');
              const myTripsContent = document.getElementById('my-trips-content');
              const communityContent = document.getElementById('community-content');

              const activeClass = 'bg-teal-600 text-white shadow-sm';
              const inactiveClass = 'text-gray-500 hover:text-gray-700';

              if (tab === 'my-trips') {
                myTripsTab.classList.add('bg-teal-600', 'text-white', 'shadow-sm');
                myTripsTab.classList.remove('text-gray-500', 'hover:text-gray-700');

                communityTab.classList.remove('bg-teal-600', 'text-white', 'shadow-sm');
                communityTab.classList.add('text-gray-500', 'hover:text-gray-700');

                myTripsContent.classList.remove('hidden');
                communityContent.classList.add('hidden');
              } else {
                communityTab.classList.add('bg-teal-600', 'text-white', 'shadow-sm');
                communityTab.classList.remove('text-gray-500', 'hover:text-gray-700');

                myTripsTab.classList.remove('bg-teal-600', 'text-white', 'shadow-sm');
                myTripsTab.classList.add('text-gray-500', 'hover:text-gray-700');

                communityContent.classList.remove('hidden');
                myTripsContent.classList.add('hidden');
              }
            }
          </script>

          <div class="mt-10 text-center">
            <p class="text-sm text-gray-500 mb-3">Looking for your next adventure?</p>
            <a class="inline-flex items-center gap-2 px-6 py-3 bg-teal-600 text-white text-sm font-medium rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer"
              href="plan_trip.php" data-discover="true">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-add-line text-sm"></i></div>Plan a New
              Trip
            </a>
          </div>
        </div>
      </div>

      <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white font-outfit">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
              <img alt="TripSync Logo" class="h-12 w-auto mb-4" src="../assets/images/logo.png">
              <p class="text-teal-50 text-sm leading-relaxed">Your trusted partner for seamless travel planning across
                Sri Lanka.</p>
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
</body>

</html>
