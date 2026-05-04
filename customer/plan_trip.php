<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
checkAuth('customer');

$userId = $_SESSION['user_id'];
$error = '';
$editPlan = null;

// Handle Edit Mode
if (isset($_GET['edit'])) {
  $editId = (int)$_GET['edit'];
  $stmt = $conn->prepare("SELECT * FROM travel_plans WHERE id = ? AND user_id = ?");
  $stmt->bind_param("ii", $editId, $userId);
  $stmt->execute();
  $editPlan = $stmt->get_result()->fetch_assoc();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tripName = clean($_POST['trip_name']);
  $startDate = $_POST['start_date'];
  $endDate = $_POST['end_date'];
  $travelers = (int)$_POST['travelers'];
  $budget = clean($_POST['budget_range']);

  if (empty($tripName) || empty($startDate) || empty($endDate)) {
    $error = "Please fill in all required fields.";
  }
  else {
    if (isset($_POST['plan_id']) && !empty($_POST['plan_id'])) {
      // Update existing plan
      $planId = (int)$_POST['plan_id'];
      $stmt = $conn->prepare("UPDATE travel_plans SET name = ?, start_date = ?, end_date = ?, travelers = ? WHERE id = ? AND user_id = ?");
      $stmt->bind_param("sssiii", $tripName, $startDate, $endDate, $travelers, $planId, $userId);
    }
    else {
      // Create new plan
      $stmt = $conn->prepare("INSERT INTO travel_plans (user_id, name, start_date, end_date, travelers, status) VALUES (?, ?, ?, ?, ?, 'planning')");
      $stmt->bind_param("isssi", $userId, $tripName, $startDate, $endDate, $travelers);
    }

    if ($stmt->execute()) {
      $planId = isset($planId) ? $planId : $conn->insert_id;
      header("Location: plan_trip_step2.php?plan_id=" . $planId);
      exit();
    }
    else {
      $error = "Failed to save trip. Please try again.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plan Trip - TripSync</title>
  <meta name="description" content="Plan your perfect trip.">
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
      <?php include 'includes/navbar.php'; ?>
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
                <div class="w-16 h-1 mx-2 bg-gray-200"></div>
              </div>
              <div class="flex items-center">
                <div
                  class="w-10 h-10 flex items-center justify-center rounded-full font-semibold transition-all bg-gray-200 text-gray-500">
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
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-lg text-sm border border-red-100">
                    <i class="ri-error-warning-line mr-2"></i><?php echo $error; ?>
                </div>
            <?php
endif; ?>

            <form action="plan_trip.php" method="POST" id="tripForm">
              <?php if ($editPlan): ?>
                <input type="hidden" name="plan_id" value="<?php echo $editPlan['id']; ?>">
              <?php
endif; ?>
              
              <h2 class="text-2xl font-bold text-gray-900 mb-6"><?php echo $editPlan ? 'Edit Trip Details' : 'Basic Information'; ?></h2>
              <div class="space-y-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Trip Name</label>
                  <input
                    name="trip_name"
                    required
                    placeholder="e.g., Southern Coast Adventure"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm"
                    type="text" 
                    value="<?php echo $editPlan ? clean($editPlan['name']) : ''; ?>">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input
                      name="start_date"
                      required
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm"
                      type="date" 
                      value="<?php echo $editPlan ? $editPlan['start_date'] : ''; ?>">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input
                      name="end_date"
                      required
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent text-sm"
                      type="date" 
                      value="<?php echo $editPlan ? $editPlan['end_date'] : ''; ?>">
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Number of Travelers</label>
                  <div class="flex items-center gap-4">
                    <button type="button" onclick="updateTravelers(-1)"
                      class="w-10 h-10 flex items-center justify-center bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors cursor-pointer">
                      <i class="ri-subtract-line"></i>
                    </button>
                    <span id="travelerCount" class="text-2xl font-bold text-gray-900 w-12 text-center">
                      <?php echo $editPlan ? $editPlan['travelers'] : '1'; ?>
                    </span>
                    <input type="hidden" name="travelers" id="travelersInput" value="<?php echo $editPlan ? $editPlan['travelers'] : '1'; ?>">
                    <button type="button" onclick="updateTravelers(1)"
                      class="w-10 h-10 flex items-center justify-center bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors cursor-pointer">
                      <i class="ri-add-line"></i>
                    </button>
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-3">Budget Range</label>
                  <input type="hidden" name="budget_range" id="budgetInput" value="medium">
                  <div class="grid grid-cols-3 gap-4">
                    <button type="button" onclick="setBudget('low')" id="btn-low"
                      class="budget-btn p-4 border-2 rounded-xl text-center transition-all cursor-pointer border-gray-200 hover:border-gray-300">
                      <p class="font-semibold text-gray-900 capitalize">low</p>
                      <p class="text-xs text-gray-600 mt-1">Budget-friendly</p>
                    </button>
                    <button type="button" onclick="setBudget('medium')" id="btn-medium"
                      class="budget-btn p-4 border-2 rounded-xl text-center transition-all cursor-pointer border-teal-600 bg-teal-50">
                      <p class="font-semibold text-gray-900 capitalize">medium</p>
                      <p class="text-xs text-gray-600 mt-1">Comfortable</p>
                    </button>
                    <button type="button" onclick="setBudget('high')" id="btn-high"
                      class="budget-btn p-4 border-2 rounded-xl text-center transition-all cursor-pointer border-gray-200 hover:border-gray-300">
                      <p class="font-semibold text-gray-900 capitalize">high</p>
                      <p class="text-xs text-gray-600 mt-1">Luxury</p>
                    </button>
                  </div>
                </div>
              </div>
              <div class="flex justify-end mt-8">
                <button type="submit"
                  class="px-8 py-3 bg-teal-600 text-white text-sm font-semibold rounded-lg hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer">
                  Next Step
                </button>
              </div>
            </form>

            <script>
                function updateTravelers(val) {
                    const span = document.getElementById('travelerCount');
                    const input = document.getElementById('travelersInput');
                    let current = parseInt(span.innerText);
                    current += val;
                    if (current < 1) current = 1;
                    span.innerText = current;
                    input.value = current;
                }

                function setBudget(type) {
                    document.getElementById('budgetInput').value = type;
                    // Reset all buttons
                    document.querySelectorAll('.budget-btn').forEach(btn => {
                        btn.classList.remove('border-teal-600', 'bg-teal-50');
                        btn.classList.add('border-gray-200');
                    });
                    // Highlight selected
                    const selected = document.getElementById('btn-' + type);
                    selected.classList.remove('border-gray-200');
                    selected.classList.add('border-teal-600', 'bg-teal-50');
                }
            </script>
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
