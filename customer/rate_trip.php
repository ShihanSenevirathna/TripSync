<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

checkAuth('customer');

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
  header("Location: trip_history.php");
  exit();
}

// Fetch booking details
$sql = "SELECT b.*, tp.name as trip_name, tp.start_date as plan_start, tp.end_date as plan_end,
               u.name as driver_name, u.id as driver_id, v.model as vehicle_model, v.reg_number as vehicle_reg, v.image_path as vehicle_image
        FROM bookings b
        LEFT JOIN travel_plans tp ON b.plan_id = tp.id
        LEFT JOIN users u ON b.assigned_partner_id = u.id
        LEFT JOIN vehicles v ON v.owner_id = u.id
        WHERE b.id = ? AND b.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
  header("Location: trip_history.php");
  exit();
}

// Handle form submission
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rating = intval($_POST['rating'] ?? 0);
  $comment = clean($_POST['comment'] ?? '');
  $cat_cleanliness = intval($_POST['cat_cleanliness'] ?? 0);
  $cat_punctuality = intval($_POST['cat_punctuality'] ?? 0);
  $cat_driving = intval($_POST['cat_driving'] ?? 0);
  $cat_communication = intval($_POST['cat_communication'] ?? 0);
  $tags = clean($_POST['tags'] ?? '');

  if ($rating < 1 || $rating > 5) {
    $message = "Please select a rating.";
  }
  else {
    // Check if review already exists
    $check_stmt = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
      $message = "You have already reviewed this trip.";
    }
    else {
      $insert_sql = "INSERT INTO reviews (booking_id, reviewer_id, target_id, rating, comment, cat_cleanliness, cat_punctuality, cat_driving, cat_communication, tags) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
      $insert_stmt = $conn->prepare($insert_sql);
      $insert_stmt->bind_param("iiiisiiiis", $booking_id, $user_id, $booking['driver_id'], $rating, $comment, $cat_cleanliness, $cat_punctuality, $cat_driving, $cat_communication, $tags);

      if ($insert_stmt->execute()) {
        header("Location: trip_history.php?success=Review submitted successfully");
        exit();
      }
      else {
        $message = "Error submitting review. Please try again.";
      }
    }
  }
}

// Fetch destinations for display
$dest_stmt = $conn->prepare("SELECT location_name FROM destinations WHERE plan_id = ? ORDER BY day_number ASC");
$dest_stmt->bind_param("i", $booking['plan_id']);
$dest_stmt->execute();
$dest_res = $dest_stmt->get_result();
$route = [];
while ($row = $dest_res->fetch_assoc()) {
  $route[] = $row['location_name'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rate Trip - TripSync</title>
  <meta name="description" content="Rate your trip experience and provide feedback.">
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
        <div class="max-w-2xl mx-auto">
          <div class="text-center mb-8">
            <div class="w-16 h-16 flex items-center justify-center bg-emerald-100 rounded-full mx-auto mb-4"><i
                class="ri-check-line text-3xl text-emerald-600"></i></div>
            <h1 class="text-2xl font-bold text-gray-900 mb-1">Trip Completed!</h1>
            <p class="text-sm text-gray-500">Rate your experience and help us improve</p>
            <?php if ($message): ?>
              <p class="mt-4 text-sm font-medium text-rose-600 bg-rose-50 py-2 px-4 rounded-lg inline-block"><?php echo $message; ?></p>
            <?php
endif; ?>
          </div>

          <form action="rate_trip.php?booking_id=<?php echo $booking_id; ?>" method="POST" id="rateForm">
            <input type="hidden" name="rating" id="ratingInput" value="0">
            <input type="hidden" name="tags" id="tagsInput" value="">
            
            <div class="bg-white rounded-2xl shadow-xl shadow-teal-900/10 border border-gray-100 overflow-hidden mb-8 transform transition-all hover:scale-[1.01]">
              <div class="relative w-full h-56">
                <img alt="<?php echo clean($booking['trip_name']); ?>" class="w-full h-full object-cover"
                  src="../assets/images/review_trip_hero.jpg">
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
                <div class="absolute bottom-0 left-0 right-0 p-6">
                  <div class="flex items-center gap-3 mb-3">
                    <span class="px-2.5 py-1 bg-teal-500 text-white text-[10px] font-black rounded shadow-lg uppercase tracking-wider">Completed Trip</span>
                    <span class="text-teal-50 text-xs font-bold bg-white/10 px-2 py-1 rounded backdrop-blur-sm"><?php echo date('M d, Y', strtotime($booking['plan_start'])); ?></span>
                  </div>
                  <h2 class="text-2xl font-black text-white leading-tight drop-shadow-md">
                    <?php echo implode(' <i class="ri-arrow-right-line mx-2 text-teal-400"></i> ', array_map('clean', $route)); ?>
                  </h2>
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 px-6 py-6 bg-white border-t border-gray-50">
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] text-gray-400 font-black uppercase tracking-[0.2em]">Date Range</span>
                  <p class="text-sm font-bold text-gray-800"><?php echo date('M j', strtotime($booking['plan_start'])); ?> - <?php echo date('M j, Y', strtotime($booking['plan_end'])); ?></p>
                </div>
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] text-gray-400 font-black uppercase tracking-[0.2em]">Total Fare</span>
                  <p class="text-sm font-bold text-teal-600"><?php echo formatCurrency($booking['total_price']); ?></p>
                </div>
                <div class="flex flex-col gap-1 md:items-end">
                  <span class="text-[10px] text-gray-400 font-black uppercase tracking-[0.2em]">Reference No</span>
                  <p class="text-xs font-mono font-bold text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-100"><?php echo clean($booking['reference_no']); ?></p>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
              <div class="flex items-center gap-4 mb-6">
                <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-white shadow-lg flex-shrink-0">
                  <img alt="<?php echo clean($booking['driver_name']); ?>" class="w-full h-full object-cover object-top"
                    src="../assets/images/<?php echo !empty($booking['vehicle_image']) ? $booking['vehicle_image'] : 'driver_nuwan.jpg'; ?>">
                </div>
                <div>
                  <h3 class="text-base font-bold text-gray-900"><?php echo clean($booking['driver_name'] ?? 'Driver Name'); ?></h3>
                  <p class="text-xs text-gray-500"><?php echo clean($booking['vehicle_model'] ?? 'Vehicle Model'); ?> · <?php echo clean($booking['vehicle_reg'] ?? 'Reg Number'); ?></p>
                  <div class="flex items-center gap-1 mt-1">
                    <i class="ri-star-fill text-xs text-amber-400"></i>
                    <i class="ri-star-fill text-xs text-amber-400"></i>
                    <i class="ri-star-fill text-xs text-amber-400"></i>
                    <i class="ri-star-fill text-xs text-amber-400"></i>
                    <i class="ri-star-half-fill text-xs text-amber-400"></i>
                    <span class="text-xs text-gray-500 ml-1">4.6 · 218 trips</span>
                  </div>
                </div>
              </div>
              
              <div class="text-center mb-8 pb-8 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-700 mb-3">How was your ride with <?php echo clean($booking['driver_name'] ?? 'your driver'); ?>?</p>
                <div class="flex items-center justify-center gap-3 main-stars">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                  <button type="button" data-val="<?php echo $i; ?>" class="star-btn cursor-pointer transition-transform hover:scale-125">
                    <i class="text-4xl ri-star-line text-gray-300"></i>
                  </button>
                  <?php
endfor; ?>
                </div>
              </div>

              <!-- Category Ratings -->
              <div class="space-y-4 mb-8">
                <?php
$categories = [
  'cleanliness' => 'Cleanliness',
  'punctuality' => 'Punctuality',
  'driving' => 'Driving Skill',
  'communication' => 'Communication'
];
foreach ($categories as $key => $label):
?>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600"><?php echo $label; ?></span>
                  <div class="flex items-center gap-2 category-stars" data-category="<?php echo $key; ?>">
                    <input type="hidden" name="cat_<?php echo $key; ?>" id="input_<?php echo $key; ?>" value="0">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" data-val="<?php echo $i; ?>" class="cat-star-btn cursor-pointer transition-colors">
                      <i class="ri-star-line text-xl text-gray-200"></i>
                    </button>
                    <?php
  endfor; ?>
                  </div>
                </div>
                <?php
endforeach; ?>
              </div>

              <!-- "What went well?" Tags -->
              <div class="mb-8">
                <p class="text-sm font-medium text-gray-700 mb-3">What went well?</p>
                <div class="flex flex-wrap gap-2">
                  <?php
$tags = ['Punctual', 'Clean Vehicle', 'Safe Driving', 'Good Music', 'Knowledgeable', 'Friendly', 'Professional'];
foreach ($tags as $tag):
?>
                  <button type="button" class="tag-btn px-4 py-2 rounded-full border border-gray-200 text-xs font-medium text-gray-600 hover:border-teal-500 hover:text-teal-600 transition-all cursor-pointer">
                    <?php echo $tag; ?>
                  </button>
                  <?php
endforeach; ?>
                </div>
              </div>

              <div class="mb-6">
                <p class="text-sm font-medium text-gray-700 mb-3">Share your experience (optional)</p>
                <textarea rows="4" name="comment" id="reviewComment"
                  placeholder="Tell us about your trip experience..."
                  class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"></textarea>
                <p class="text-xs text-gray-400 mt-1 text-right" id="charCount">0/500</p>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <a class="flex-1 py-3 text-center border border-gray-300 text-gray-700 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors cursor-pointer whitespace-nowrap"
                href="trip_history.php" data-discover="true">Skip</a>
              <button type="submit" id="submitBtn" disabled
                class="flex-[2] py-3 bg-teal-600 text-white text-sm font-semibold rounded-full hover:bg-teal-700 transition-colors cursor-pointer whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
                Submit Review
              </button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Footer logic... -->
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
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="#"
                    data-discover="true">Become a Partner</a></li>
              </ul>
            </div>
            <div>
              <h3 class="font-semibold text-base mb-4">Support</h3>
              <ul class="space-y-2">
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="help.php"
                    data-discover="true">Help Center</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer" href="#"
                    data-discover="true">Contact Us</a></li>
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

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const stars = document.querySelectorAll('.star-btn');
      const ratingInput = document.getElementById('ratingInput');
      const submitBtn = document.getElementById('submitBtn');
      const textarea = document.getElementById('reviewComment');
      const charCount = document.getElementById('charCount');
      const tagsInput = document.getElementById('tagsInput');
      const tagButtons = document.querySelectorAll('.tag-btn');
      const selectedTags = new Set();

      // Main Star Rating
      stars.forEach(star => {
        star.addEventListener('click', function() {
          const val = parseInt(this.getAttribute('data-val'));
          ratingInput.value = val;
          
          stars.forEach((s, idx) => {
            const icon = s.querySelector('i');
            if (idx < val) {
              icon.classList.replace('ri-star-line', 'ri-star-fill');
              icon.classList.replace('text-gray-300', 'text-amber-400');
            } else {
              icon.classList.replace('ri-star-fill', 'ri-star-line');
              icon.classList.replace('text-amber-400', 'text-gray-300');
            }
          });
          
          submitBtn.disabled = false;
        });
      });

      // Category Star Rating
      const catStarBtns = document.querySelectorAll('.cat-star-btn');
      catStarBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          const val = parseInt(this.getAttribute('data-val'));
          const parent = this.closest('.category-stars');
          const category = parent.getAttribute('data-category');
          const input = document.getElementById(`input_${category}`);
          const siblings = parent.querySelectorAll('.cat-star-btn');
          
          input.value = val;
          
          siblings.forEach((s, idx) => {
            const icon = s.querySelector('i');
            if (idx < val) {
              icon.classList.replace('ri-star-line', 'ri-star-fill');
              icon.classList.replace('text-gray-200', 'text-amber-400');
            } else {
              icon.classList.replace('ri-star-fill', 'ri-star-line');
              icon.classList.replace('text-amber-400', 'text-gray-200');
            }
          });
        });
      });

      // Tags selection
      tagButtons.forEach(btn => {
        btn.addEventListener('click', function() {
          this.classList.toggle('active');
          if (this.classList.contains('active')) {
            this.classList.add('bg-teal-600', 'text-white', 'border-teal-600', 'shadow-md', 'scale-105');
            this.classList.remove('text-gray-600', 'border-gray-200', 'hover:bg-gray-50');
          } else {
            this.classList.remove('bg-teal-600', 'text-white', 'border-teal-600', 'shadow-md', 'scale-105');
            this.classList.add('text-gray-600', 'border-gray-200', 'hover:bg-gray-50');
          }
        });
      });

      // Form Submit Logic
      const rateForm = document.getElementById('rateForm');
      rateForm.addEventListener('submit', (e) => {
          const activeTags = Array.from(document.querySelectorAll('.tag-btn.active'))
              .map(btn => btn.textContent.trim());
          document.getElementById('tagsInput').value = activeTags.join(',');

          if (document.getElementById('ratingInput').value === '0') {
              e.preventDefault();
              alert('Please select a rating before submitting.');
          }
      });

      // Character count
      textarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = `${length}/500`;
        const percent = (length / 500) * 100;
        charCount.style.color = percent > 90 ? '#ef4444' : (percent > 70 ? '#f59e0b' : '#9ca3af');
      });
    });
  </script>
</body>

</html>
