<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

checkAuth('customer');

$user_id = $_SESSION['user_id'];

// Handle Form Submission
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = clean($_POST['name'] ?? '');
  $phone = !empty($_POST['phone']) ? clean($_POST['phone']) : null;
  $dob = !empty($_POST['dob']) ? clean($_POST['dob']) : null;
  $address = !empty($_POST['address']) ? clean($_POST['address']) : null;
  $emergency_name = !empty($_POST['emergency_contact_name']) ? clean($_POST['emergency_contact_name']) : null;
  $emergency_phone = !empty($_POST['emergency_contact_phone']) ? clean($_POST['emergency_contact_phone']) : null;

  if (empty($name)) {
    $error_msg = "Name cannot be empty.";
  }
  else {
    $conn->begin_transaction();
    try {
      // Update Personal Info
      $update_sql = "UPDATE users SET name = ?, phone = ?, dob = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE id = ?";
      $update_stmt = $conn->prepare($update_sql);
      $update_stmt->bind_param("ssssssi", $name, $phone, $dob, $address, $emergency_name, $emergency_phone, $user_id);
      $update_stmt->execute();

      // Handle Profile Picture Upload
      if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $target_dir = "../assets/images/";
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_name = "customer_" . $user_id . "_" . time() . "." . $ext;

        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $new_name)) {
          $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
          $stmt->bind_param("si", $new_name, $user_id);
          $stmt->execute();
        }
      }

      $conn->commit();
      $success_msg = "Profile updated successfully!";
    }
    catch (Exception $e) {
      $conn->rollback();
      $error_msg = "Error updating profile: " . $e->getMessage();
    }
  }
}

// Fetch user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  die("User not found");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile Settings - TripSync</title>
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
          <div class="flex items-center justify-between mb-6">
            <div>
              <h1 class="text-2xl font-bold text-gray-900">Profile Settings</h1>
              <p class="text-sm text-gray-500 mt-1">Manage your personal information and preferences</p>
            </div>
            <a class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors cursor-pointer whitespace-nowrap"
              href="dashboard.php">
              <i class="ri-arrow-left-line text-base"></i>Back to Dashboard
            </a>
          </div>

          <?php if ($success_msg): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-2xl flex items-center gap-3">
              <i class="ri-checkbox-circle-line text-xl"></i>
              <span class="text-sm font-medium"><?php echo $success_msg; ?></span>
            </div>
          <?php
endif; ?>

          <?php if ($error_msg): ?>
            <div class="mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-700 rounded-2xl flex items-center gap-3">
              <i class="ri-error-warning-line text-xl"></i>
              <span class="text-sm font-medium"><?php echo $error_msg; ?></span>
            </div>
          <?php
endif; ?>

          <form action="profile.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
              <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center sticky top-24">
                <div class="relative w-28 h-28 mx-auto mb-4">
                  <img alt="<?php echo clean($user['name']); ?>"
                    class="w-full h-full rounded-full object-cover object-top border-4 border-white shadow-lg"
                    src="<?php echo getProfilePic($user['profile_pic'], '../'); ?>">
                  <button type="button" onclick="document.getElementById('profile_pic_input').click()"
                    class="absolute bottom-0 right-0 w-9 h-9 flex items-center justify-center bg-teal-600 text-white rounded-full shadow-md hover:bg-teal-700 transition-colors cursor-pointer">
                    <i class="ri-camera-line text-sm"></i>
                  </button>
                  <input type="file" name="profile_pic" id="profile_pic_input" class="hidden" accept="image/*" onchange="this.form.submit()">
                </div>
                <h3 class="text-lg font-bold text-gray-900"><?php echo clean($user['name']); ?></h3>
                <p class="text-sm text-gray-500 mb-3"><?php echo clean($user['email']); ?></p>
                <span
                  class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-full">
                  <i class="ri-verified-badge-fill text-sm"></i>Verified Account
                </span>
                <div class="mt-5 pt-5 border-t border-gray-100 text-left space-y-3">
                  <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg">
                      <i class="ri-calendar-line text-gray-500 text-sm"></i>
                    </div>
                    <div>
                      <p class="text-gray-400 text-xs">Member Since</p>
                      <p class="text-gray-700 font-medium"><?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    </div>
                  </div>
                  <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg">
                      <i class="ri-bank-card-line text-gray-500 text-sm"></i>
                    </div>
                    <div>
                      <p class="text-gray-400 text-xs">Payment</p>
                      <p class="text-gray-700 font-medium">Visa ending in 4521</p>
                    </div>
                  </div>
                </div>
                <div class="mt-5 pt-5 border-t border-gray-100">
                  <a href="../logout.php" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-50 text-rose-600 rounded-xl text-sm font-bold hover:bg-rose-100 transition-colors cursor-pointer">
                    <i class="ri-logout-box-r-line text-lg"></i> Log Out
                  </a>
                </div>
              </div>
            </div>
            <div class="lg:col-span-2 space-y-6">
              <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                  <div class="w-6 h-6 flex items-center justify-center"><i
                      class="ri-user-line text-teal-600 text-base"></i></div>Personal Information
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Full Name</label>
                    <input name="name"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="text" value="<?php echo clean($user['name']); ?>">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Email Address</label>
                    <input
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="email" value="<?php echo clean($user['email']); ?>" readonly>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Phone Number</label>
                    <input name="phone"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="tel" value="<?php echo clean($user['phone'] ?? ''); ?>">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Date of Birth</label>
                    <input name="dob"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="date" value="<?php echo clean($user['dob'] ?? ''); ?>">
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Address</label>
                    <input name="address"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="text" value="<?php echo clean($user['address'] ?? ''); ?>">
                  </div>
                </div>
              </div>
              <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                  <div class="w-6 h-6 flex items-center justify-center"><i
                      class="ri-alarm-warning-line text-rose-500 text-base"></i></div>Emergency Contact
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Contact Name</label>
                    <input name="emergency_contact_name"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="text" value="<?php echo clean($user['emergency_contact_name'] ?? ''); ?>">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Contact Number</label>
                    <input name="emergency_contact_phone"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                      type="tel" value="<?php echo clean($user['emergency_contact_phone'] ?? ''); ?>">
                  </div>
                </div>
              </div>
              <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                  <div class="w-6 h-6 flex items-center justify-center"><i
                      class="ri-settings-3-line text-gray-500 text-base"></i></div>Preferences
                </h3>
                <div class="space-y-4">
                  <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                    <div>
                      <p class="text-sm font-medium text-gray-900">Email Notifications</p>
                      <p class="text-xs text-gray-500">Receive trip updates and booking confirmations via email</p>
                    </div>
                    <button class="relative w-12 h-6 rounded-full transition-all cursor-pointer bg-teal-500">
                      <div class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all left-6"></div>
                    </button>
                  </div>
                  <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                    <div>
                      <p class="text-sm font-medium text-gray-900">SMS Alerts</p>
                      <p class="text-xs text-gray-500">Get text messages for important trip reminders</p>
                    </div>
                    <button class="relative w-12 h-6 rounded-full transition-all cursor-pointer bg-teal-500">
                      <div class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all left-6"></div>
                    </button>
                  </div>
                  <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                    <div>
                      <p class="text-sm font-medium text-gray-900">Push Notifications</p>
                      <p class="text-xs text-gray-500">Browser notifications for real-time updates</p>
                    </div>
                    <button class="relative w-12 h-6 rounded-full transition-all cursor-pointer bg-teal-500">
                      <div class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all left-6"></div>
                    </button>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                      <label class="block text-xs font-medium text-gray-500 mb-1.5">Language</label>
                      <select
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent cursor-pointer">
                        <option>English</option>
                        <option>Sinhala</option>
                        <option>Tamil</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-500 mb-1.5">Currency</label>
                      <select
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent cursor-pointer">
                        <option>LKR</option>
                        <option>USD</option>
                        <option>EUR</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex items-center justify-end gap-3">
                <button type="button" onclick="window.location.reload();"
                  class="px-6 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors cursor-pointer whitespace-nowrap">Cancel</button>
                <button type="submit"
                  class="px-8 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-full hover:bg-teal-700 transition-colors cursor-pointer whitespace-nowrap">Save
                  Changes</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white">
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
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="messages.php">Messages</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="booking_confirmation.php">Booking Confirmation</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="../partner/register.php">Become a Partner</a></li>
              </ul>
            </div>
            <div>
              <h3 class="font-semibold text-base mb-4">Support</h3>
              <ul class="space-y-2">
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="help.php">Help Center</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="../index.php#contact">Contact Us</a></li>
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
            <p>© 2026 TripSync. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  </div>
</body>

</html>
