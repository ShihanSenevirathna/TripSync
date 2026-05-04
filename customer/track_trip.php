<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

checkAuth('customer');

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
  // Only track the latest ACTIVE booking
  $latest_sql = "SELECT id FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'arrived', 'in_progress') ORDER BY created_at DESC LIMIT 1";
  $latest_stmt = $conn->prepare($latest_sql);
  $latest_stmt->bind_param("i", $user_id);
  $latest_stmt->execute();
  $booking_id = $latest_stmt->get_result()->fetch_assoc()['id'] ?? 0;
}

if (!$booking_id) {
  header("Location: dashboard.php?msg=noactive");
  exit();
}

$sql = "SELECT b.*, tp.name as trip_name, u.name as partner_name, u.profile_pic as partner_pic
        FROM bookings b 
        LEFT JOIN travel_plans tp ON b.plan_id = tp.id 
        LEFT JOIN users u ON b.assigned_partner_id = u.id
        WHERE b.id = ? AND b.user_id = ? AND b.status IN ('confirmed', 'arrived', 'in_progress')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
  header("Location: dashboard.php?msg=noactive");
  exit();
}

// Fetch destinations for route
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
  <title>Track Trip - TripSync</title>
  <meta name="description" content="Track your trip in real-time.">
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <script type="module" src="../assets/js/main.js"></script>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <style>
    .chat-bubble-container {
      position: fixed;
      bottom: 1.5rem;
      right: 1.5rem;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 1rem;
    }

    .chat-toggle-btn {
      width: 3.5rem;
      height: 3.5rem;
      background: #0d9488;
      color: white;
      border-radius: 1.25rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      cursor: pointer;
      box-shadow: 0 10px 15px -3px rgba(13, 148, 136, 0.4);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
    }

    .chat-toggle-btn:hover {
      transform: translateY(-2px);
      background: #0f766e;
    }

    .chat-badge {
      position: absolute;
      top: -0.25rem;
      right: -0.25rem;
      background: #ef4444;
      color: white;
      font-size: 0.65rem;
      font-weight: 800;
      width: 1.25rem;
      height: 1.25rem;
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid white;
      display: none;
    }

    .chat-window {
      width: 320px;
      height: 450px;
      background: white;
      border-radius: 2rem;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      display: none;
      flex-direction: column;
      overflow: hidden;
      border: 1px solid rgba(0, 0, 0, 0.05);
      animation: slideInUp 0.3s ease-out;
    }

    @keyframes slideInUp {
      from {
        transform: translateY(20px);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .chat-messages {
      flex: 1;
      padding: 1.25rem;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      background: #f8fafc;
    }

    .message-row {
      display: flex;
      flex-direction: column;
      max-width: 80%;
    }

    .message-row.sent {
      align-self: flex-end;
      align-items: flex-end;
    }

    .message-row.received {
      align-self: flex-start;
      align-items: flex-start;
    }

    .message-bubble {
      padding: 0.75rem 1rem;
      border-radius: 1rem;
      font-size: 0.85rem;
      font-weight: 500;
      line-height: 1.4;
    }

    .sent .message-bubble {
      background: #0d9488;
      color: white;
      border-bottom-right-radius: 0.25rem;
    }

    .received .message-bubble {
      background: white;
      color: #1e293b;
      border-bottom-left-radius: 0.25rem;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .message-time {
      font-size: 0.6rem;
      color: #94a3b8;
      margin-top: 0.25rem;
      font-weight: 600;
    }
  </style>
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
        <div class="max-w-7xl mx-auto">
          <div class="flex items-center justify-between mb-6">
            <div>
              <div class="flex items-center gap-3 mb-1">
                <h1 class="text-2xl font-bold text-gray-900">Trip Tracking</h1>
                <?php
                $status_colors = [
                  'pending' => 'bg-amber-100 text-amber-700',
                  'confirmed' => 'bg-blue-100 text-blue-700',
                  'arrived' => 'bg-indigo-100 text-indigo-700',
                  'in_progress' => 'bg-emerald-100 text-emerald-700',
                  'completed' => 'bg-gray-100 text-gray-700',
                  'cancelled' => 'bg-rose-100 text-rose-700'
                ];
                $color_class = $status_colors[$booking['status']] ?? 'bg-gray-100 text-gray-700';
                ?>
                <span
                  class="flex items-center gap-1.5 px-3 py-1 <?php echo $color_class; ?> text-xs font-semibold rounded-full whitespace-nowrap">
                  <?php if ($booking['status'] == 'in_progress'): ?>
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <?php
                  endif; ?>
                  <?php echo ucfirst($booking['status']); ?>
                </span>
              </div>
              <p class="text-sm text-gray-500">Track your driver in real-time · Trip
                <?php echo clean($booking['reference_no']); ?> &mdash; <?php echo clean($booking['trip_name']); ?></p>
            </div>
            <div class="flex items-center gap-3"><a
                class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors cursor-pointer whitespace-nowrap"
                href="dashboard.php" data-discover="true"><i class="ri-arrow-left-line text-base"></i>Dashboard</a>
            </div>
          </div>
          <?php
          // Fetch driver and vehicle info
          $driver_sql = "SELECT u.name as driver_name, u.phone as driver_phone, v.model as vehicle_model, v.reg_number as vehicle_reg, v.image_path as vehicle_image
                FROM users u
                LEFT JOIN vehicles v ON v.owner_id = u.id
                WHERE u.id = ?";
          $driver_stmt = $conn->prepare($driver_sql);
          $driver_stmt->bind_param("i", $booking['assigned_partner_id']);
          $driver_stmt->execute();
          $driver = $driver_stmt->get_result()->fetch_assoc();

          // Fetch status logs for timeline
          $log_stmt = $conn->prepare("SELECT * FROM booking_status_logs WHERE booking_id = ? ORDER BY created_at ASC");
          $log_stmt->bind_param("i", $booking_id);
          $log_stmt->execute();
          $status_logs = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

          // Calculate progress
          $progress_map = ['pending' => 5, 'confirmed' => 15, 'arrived' => 35, 'in_progress' => 65, 'completed' => 100, 'cancelled' => 0];
          $progress_pct = $progress_map[$booking['status']] ?? 0;

          $eta = ($booking['status'] == 'in_progress') ? 'Calculating...' : (($booking['status'] == 'arrived') ? 'Arrived' : (($booking['status'] == 'cancelled') ? 'Cancelled' : 'Calculating...'));
          $dist = ($booking['status'] == 'in_progress') ? 'TBD' : (($booking['status'] == 'arrived') ? '0 km' : (($booking['status'] == 'cancelled') ? 'N/A' : 'TBD'));
          ?>
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
              <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="p-4 flex items-center justify-between">
                  <div>
                    <h3 class="text-base font-bold text-gray-900">Live Location</h3>
                    <p class="text-xs text-gray-500 mt-0.5"><i class="ri-map-pin-line mr-1"></i>Currently at: Tracking
                      Active</p>
                  </div>
                </div>
                <div class="w-full h-96">
                  <iframe src="../assets/maps/tracking_map.php?booking_id=<?php echo $booking_id; ?>"
                    class="w-full h-full border-0" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" title="Live Trip Tracking Map"></iframe>
                </div>
                <div id="route-breadcrumbs"
                  class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center gap-3 overflow-x-auto no-scrollbar">
                  <!-- Dynamic Stops will be injected here via JS -->
                  <div class="animate-pulse flex space-x-4">
                    <div class="h-2 bg-gray-200 rounded w-24"></div>
                    <div class="h-2 bg-gray-200 rounded w-24"></div>
                    <div class="h-2 bg-gray-200 rounded w-24"></div>
                  </div>
                </div>
              </div>
              <div class="bg-gradient-to-r from-teal-50 to-emerald-50 border border-teal-200 rounded-2xl p-5">
                <div class="flex items-center gap-6 flex-wrap">
                  <div class="flex items-center gap-3">
                    <div class="w-12 h-12 flex items-center justify-center bg-white rounded-xl shadow-sm"><i
                        class="ri-time-line text-xl text-teal-600"></i></div>
                    <div>
                      <p class="text-xs text-gray-500">ETA</p>
                      <p id="eta-main" class="text-xl font-bold text-teal-700"><?php echo $eta; ?></p>
                    </div>
                  </div>
                  <div class="w-px h-10 bg-teal-200 hidden sm:block"></div>
                  <div class="flex items-center gap-3">
                    <div class="w-12 h-12 flex items-center justify-center bg-white rounded-xl shadow-sm"><i
                        class="ri-route-line text-xl text-teal-600"></i></div>
                    <div>
                      <p class="text-xs text-gray-500">Distance</p>
                      <p id="dist-main" class="text-xl font-bold text-teal-700"><?php echo $dist; ?></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="lg:col-span-1 space-y-6">
              <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center gap-4 mb-4">
                  <div class="relative">
                    <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-white shadow-lg">
                      <img alt="<?php echo clean($driver['driver_name'] ?? 'Driver'); ?>"
                        class="w-full h-full object-cover object-top"
                        src="../assets/images/<?php echo !empty($driver['vehicle_image']) ? $driver['vehicle_image'] : 'driver_kasun.jpg'; ?>">
                    </div>
                    <div
                      class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-emerald-500 border-2 border-white">
                    </div>
                  </div>
                  <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-gray-900">
                      <?php echo clean($driver['driver_name'] ?? 'Partner Not Assigned'); ?></h3>
                    <div class="flex items-center gap-1 mb-1">
                      <i class="ri-star-fill text-xs text-amber-400"></i>
                      <i class="ri-star-fill text-xs text-amber-400"></i>
                      <i class="ri-star-fill text-xs text-amber-400"></i>
                      <i class="ri-star-fill text-xs text-amber-400"></i>
                      <i class="ri-star-fill text-xs text-amber-400"></i>
                      <span class="text-xs font-medium text-gray-600 ml-1">4.8 (342 trips)</span>
                    </div>
                    <p class="text-xs text-gray-500">
                      <?php echo clean($driver['vehicle_model'] ?? 'Standard Vehicle'); ?></p>
                  </div>
                </div>
                <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl mb-4">
                  <div class="w-8 h-8 flex items-center justify-center bg-gray-200 rounded-lg"><i
                      class="ri-car-line text-gray-600 text-sm"></i></div>
                  <div class="flex-1">
                    <p class="text-xs text-gray-400">License Plate</p>
                    <p class="text-sm font-bold text-gray-900 tracking-wider">
                      <?php echo clean($driver['vehicle_reg'] ?? 'N/A'); ?></p>
                  </div>
                </div>
                <div class="flex gap-2">
                  <a href="tel:<?php echo $driver['driver_phone'] ?: '#'; ?>"
                    class="flex-1 flex items-center justify-center gap-2 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition-colors cursor-pointer whitespace-nowrap"><i
                      class="ri-phone-fill text-base"></i>Call Driver</a>
                  <a href="messages.php"
                    class="flex-1 flex items-center justify-center gap-2 py-2.5 border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors cursor-pointer whitespace-nowrap"><i
                      class="ri-message-3-line text-base"></i>Message</a>
                </div>
              </div>
              <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-base font-bold text-gray-900">Trip Progress</h3>
                  <span
                    class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-full whitespace-nowrap capitalize"><?php echo $booking['status']; ?></span>
                </div>
                <div class="flex items-center gap-4 mb-5">
                  <div class="relative w-20 h-20 flex-shrink-0">
                    <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36">
                      <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        fill="none" stroke="#E5E7EB" stroke-width="3"></path>
                      <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        fill="none" stroke="#10B981" stroke-width="3"
                        stroke-dasharray="<?php echo $progress_pct; ?>, 100" stroke-linecap="round"></path>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                      <span class="text-lg font-bold text-gray-900"><?php echo $progress_pct; ?>%</span>
                    </div>
                  </div>
                  <div class="flex-1 space-y-2">
                    <div class="flex items-center justify-between"><span class="text-xs text-gray-500">Distance
                        Remaining</span><span id="dist-remaining" class="text-sm font-semibold text-gray-900"><?php echo $dist; ?></span>
                    </div>
                    <div class="flex items-center justify-between"><span class="text-xs text-gray-500">ETA</span><span
                        id="eta-side" class="text-sm font-bold text-teal-600"><?php echo $eta; ?></span></div>
                  </div>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2 mb-1">
                  <div class="bg-gradient-to-r from-teal-500 to-emerald-500 h-2 rounded-full transition-all"
                    style="width: <?php echo $progress_pct; ?>%;"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-gray-400 mt-1">
                  <span>Pickup</span><span>Destination</span>
                </div>

                <?php if ($booking['status'] === 'completed'): ?>
                  <div
                    class="mt-6 p-4 bg-gray-900 rounded-2xl text-center space-y-3 shadow-xl animate-in fade-in zoom-in duration-500">
                    <div class="flex items-center justify-center gap-2 text-emerald-400 mb-1">
                      <i class="ri-checkbox-circle-fill text-xl"></i>
                      <span class="text-xs font-black uppercase tracking-widest">Safe Arrival</span>
                    </div>
                    <p class="text-white text-xs font-medium">Your trip has ended. Would you like to rate your driver?</p>
                    <a href="rate_trip.php?booking_id=<?php echo $booking_id; ?>"
                      class="block w-full py-2.5 bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-900/40">
                      Rate Trip Now
                    </a>
                  </div>
                  <?php
                endif; ?>
              </div>
              <div class="bg-white rounded-2xl border border-gray-200 p-5 overflow-hidden">
                <div class="flex items-center gap-2 mb-6 p-1 bg-gray-100 rounded-xl">
                  <button id="tab-timeline" onclick="switchTripTab('timeline')"
                    class="flex-1 px-4 py-2 rounded-lg text-sm font-bold transition-all cursor-pointer whitespace-nowrap bg-white text-gray-900 shadow-sm">
                    Timeline
                  </button>
                  <button id="tab-stops" onclick="switchTripTab('stops')"
                    class="flex-1 px-4 py-2 rounded-lg text-sm font-bold transition-all cursor-pointer whitespace-nowrap text-gray-500 hover:text-gray-700">
                    Route Stops
                  </button>
                </div>

                <!-- Timeline Tab -->
                <div id="panel-timeline" class="space-y-0 transition-opacity duration-300">
                  <?php
                  $unique_logs = [];
                  $last_status = '';
                  foreach ($status_logs as $log) {
                    if ($log['status'] !== $last_status) {
                      $unique_logs[] = $log;
                      $last_status = $log['status'];
                    }
                  }
                  if (empty($unique_logs)):
                    ?>
                    <p class="text-xs text-gray-400 italic py-4 text-center">Waiting for driver updates...</p>
                    <?php
                  else: ?>
                    <?php foreach (array_reverse($unique_logs) as $index => $log): ?>
                      <div class="flex gap-4 group">
                        <div class="flex flex-col items-center">
                          <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-emerald-500 ring-4 ring-emerald-50"></div>
                          <?php if ($index < count($unique_logs) - 1): ?>
                            <div class="w-0.5 h-10 bg-emerald-100"></div>
                            <?php
                          endif; ?>
                        </div>
                        <div class="pb-6">
                          <p class="text-sm font-bold text-gray-900 -mt-1 capitalize">
                            <?php echo str_replace('_', ' ', $log['status']); ?></p>
                          <p class="text-[10px] text-gray-400 mt-0.5 font-medium">
                            <?php echo date('h:i A', strtotime($log['created_at'])); ?></p>
                        </div>
                      </div>
                      <?php
                    endforeach; ?>
                    <?php
                  endif; ?>
                </div>

                <!-- Route Stops Tab -->
                <div id="panel-stops" class="hidden transition-opacity duration-300">
                  <div id="stops-list-container" class="space-y-4">
                    <!-- Populated via JS -->
                    <div class="animate-pulse space-y-4 py-2">
                      <div class="h-10 bg-gray-50 rounded-xl w-full"></div>
                      <div class="h-10 bg-gray-50 rounded-xl w-full"></div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="text-base font-bold text-gray-900 mb-4">Trip Details</h3>
                <div class="space-y-3 mb-5">
                  <div class="flex items-start gap-4">
                    <div class="flex flex-col items-center mt-1">
                      <div class="w-3 h-3 rounded-full bg-emerald-500 border-2 border-emerald-200"></div>
                      <div class="w-0.5 h-14 bg-gradient-to-b from-emerald-300 to-rose-300"></div>
                      <div class="w-3 h-3 rounded-full bg-rose-500 border-2 border-rose-200"></div>
                    </div>
                    <div class="flex-1">
                      <div class="mb-3">
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider">Pickup</p>
                        <p id="pickup-name" class="text-sm font-medium text-gray-900 font-black">
                          <?php echo clean($booking['pickup_location'] ?: 'Location Pending'); ?></p>
                        <p class="text-xs text-gray-500">
                          <?php echo date('M d, Y', strtotime($booking['start_date'])); ?> ·
                          <?php echo date('h:i A', strtotime($booking['start_date'])); ?></p>
                      </div>
                      <div>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider">Drop-off</p>
                        <p id="dropoff-name" class="text-sm font-medium text-gray-900 font-black">
                          <?php echo clean($booking['dropoff_location'] ?: 'Location Pending'); ?></p>
                        <p class="text-xs text-gray-500">Estimated Arrival · <span
                            id="eta-detail"><?php echo $eta; ?></span></p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-5">
                  <div class="bg-gray-50 rounded-xl p-3">
                    <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-tighter font-bold">Reference</p>
                    <p class="text-sm font-bold text-gray-900"><?php echo clean($booking['reference_no']); ?></p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-3">
                    <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-tighter font-bold">Fare</p>
                    <p id="fare-display" class="text-sm font-bold text-teal-600 italic">LKR
                      <?php echo number_format($booking['total_price']); ?></p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-3">
                    <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-tighter font-bold">Trip Type</p>
                    <p class="text-sm font-bold text-gray-900 capitalize"><?php echo $booking['type']; ?></p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-3">
                    <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-tighter font-bold">Payment Status</p>
                    <p class="text-sm font-bold text-gray-900 capitalize">Paid (Card)</p>
                  </div>
                </div>
                <div class="flex gap-2"><button
                    class="flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-medium rounded-xl transition-colors cursor-pointer whitespace-nowrap border border-gray-200 text-gray-700 hover:bg-gray-50"><i
                      class="ri-share-forward-line text-base"></i>Share Trip</button><button
                    class="w-12 h-10 flex items-center justify-center border border-rose-200 text-rose-600 rounded-xl hover:bg-rose-50 transition-colors cursor-pointer"
                    onclick="triggerSOS()"><i class="ri-alarm-warning-line text-lg"></i></button></div>
              </div>
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
                    href="messages.php">Messages</a></li>
                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                    href="booking_confirmation.php">Booking Confirmation</a></li>
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
    // Unified Real-Time Tracking Foundation (Step 0)
    async function startRealTimeUpdates() {
      const bookingId = <?php echo $booking_id; ?>;

      setInterval(async () => {
        try {
          const response = await fetch(`../api/get_tracking_data.php?booking_id=${bookingId}`);
          const data = await response.json();
          updateTripUI(data);

          // Update map if position changed
          if (window.updateMapMarker && data.latitude && data.longitude) {
            window.updateMapMarker(data.latitude, data.longitude);
          }
        } catch (e) {
          console.error("Polling error:", e);
        }
      }, 5000); // Poll every 5 seconds
    }

    startRealTimeUpdates();

    function updateTripUI(data) {
      if (!data.success) return;

      // 1. Update status badge and text
      const statusBadge = document.querySelector('span.px-3.py-1');
      if (statusBadge) {
        statusBadge.className = `flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full whitespace-nowrap ${getStatusColor(data.status)}`;
        statusBadge.innerHTML = (data.status === 'in_progress' ? '<div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>' : '') + data.status.charAt(0).toUpperCase() + data.status.slice(1);
      }

      // 2. Update Progress Bar & Percentage
      const progressBar = document.querySelector('.bg-gradient-to-r.from-teal-500');
      if (progressBar) progressBar.style.width = data.progress + '%';

      const progressPct = document.querySelector('.text-lg.font-bold.text-gray-900');
      if (progressPct) progressPct.textContent = data.progress + '%';

      const svgPath = document.querySelector('path[stroke="#10B981"]');
      if (svgPath) svgPath.setAttribute('stroke-dasharray', `${data.progress}, 100`);

      // 3. Update ETA and Distance
      const etaEls = document.querySelectorAll('#eta-main, #eta-side, .text-sm.font-bold.text-teal-600, #eta-detail');
      etaEls.forEach(el => el.textContent = data.eta);

      const distEls = document.querySelectorAll('#dist-main, #dist-remaining');
      distEls.forEach(el => el.textContent = data.distance);

      const fareDisplay = document.getElementById('fare-display');
      if (fareDisplay) fareDisplay.textContent = 'LKR ' + Number(data.total_price).toLocaleString();

      const pickupName = document.getElementById('pickup-name');
      if (pickupName) pickupName.textContent = data.pickup_location;

      const dropoffName = document.getElementById('dropoff-name');
      if (dropoffName) dropoffName.textContent = data.dropoff_location;



      // 4. Update Itinerary Breadcrumbs (Task 02)
      const breadcrumbContainer = document.getElementById('route-breadcrumbs');
      if (breadcrumbContainer && data.destinations) {
        if (data.destinations.length > 0) {
          let html = '';
          data.destinations.forEach((dest, i) => {
            const isCompleted = dest.status === 'completed';
            const isArrived = dest.status === 'arrived';
            html += `
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <div class="flex items-center gap-1.5 text-[10px] font-bold ${isCompleted ? 'text-emerald-600' : (isArrived ? 'text-amber-500' : 'text-gray-400')}">
                                <i class="${isCompleted ? 'ri-checkbox-circle-fill' : (isArrived ? 'ri-record-circle-fill animate-pulse' : 'ri-checkbox-blank-circle-line')}"></i>
                                <span>${dest.location_name}</span>
                            </div>
                            ${i < data.destinations.length - 1 ? '<i class="ri-arrow-right-s-line text-gray-300"></i>' : ''}
                        </div>
                    `;
          });
          breadcrumbContainer.innerHTML = html;
        } else {
          breadcrumbContainer.innerHTML = `
                    <div class="flex items-center gap-2 whitespace-nowrap">
                        <div class="flex items-center gap-1.5 text-[10px] font-bold ${data.status === 'confirmed' ? 'text-gray-400' : 'text-emerald-600'}">
                            <i class="${data.status === 'confirmed' ? 'ri-checkbox-blank-circle-line' : 'ri-checkbox-circle-fill'}"></i>
                            <span>${data.pickup_location}</span>
                        </div>
                        <i class="ri-arrow-right-s-line text-gray-300"></i>
                    </div>
                    <div class="flex items-center gap-2 whitespace-nowrap">
                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400">
                            <i class="ri-map-pin-2-fill text-rose-500"></i>
                            <span>${data.dropoff_location}</span>
                        </div>
                    </div>
                `;
        }
        updateStopsTab(data.destinations, data.pickup_location, data.dropoff_location, data.status); // Update the detailed tab as well
      }

      // 5. Reload if terminal status reached or changed significantly
      if ((data.status === 'completed' || data.status === 'cancelled') && "<?php echo $booking['status']; ?>" !== data.status) {
        window.location.reload();
      }
    }

    function triggerSOS() {
      if (!confirm('🚨 EMERGENCY SOS\n\nAre you sure you want to trigger an emergency alert? This will broadcast your current location to our 24/7 safety team.')) return;

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(async (position) => {
          const lat = position.coords.latitude;
          const lng = position.coords.longitude;
          const bookingId = <?php echo $booking_id; ?>;

          try {
            const response = await fetch('../api/trigger_sos.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                booking_id: bookingId,
                latitude: lat,
                longitude: lng
              })
            });
            const data = await response.json();
            if (data.success) {
              alert('🚨 SOS SENT: Help is on the way. Our safety team has been notified and is tracking your location.');
            } else {
              alert('SOS Error: ' + data.message);
            }
          } catch (e) {
            console.error("SOS Trigger Error:", e);
            alert("Failed to send SOS alert. Please try again or call emergency services directly.");
          }
        }, (error) => {
          console.error("Geolocation error:", error);
          alert("Could not get your location. Please check your browser's location permissions and try again.");
        });
      } else {
        alert("Geolocation is not supported by your browser. Please call 119 directly in an emergency.");
      }
    }

    function getStatusColor(status) {
      const colors = {
        'pending': 'bg-amber-100 text-amber-700',
        'confirmed': 'bg-blue-100 text-blue-700',
        'arrived': 'bg-indigo-100 text-indigo-700',
        'in_progress': 'bg-emerald-100 text-emerald-700',
        'completed': 'bg-gray-100 text-gray-700',
        'cancelled': 'bg-rose-100 text-rose-700'
      };
      return colors[status] || 'bg-gray-100 text-gray-700';
    }

    function switchTripTab(tab) {
      const panels = ['timeline', 'stops'];
      panels.forEach(p => {
        const panel = document.getElementById(`panel-${p}`);
        const btn = document.getElementById(`tab-${p}`);
        if (p === tab) {
          panel.classList.remove('hidden');
          btn.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
          btn.classList.remove('text-gray-500');
        } else {
          panel.classList.add('hidden');
          btn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
          btn.classList.add('text-gray-500');
        }
      });
    }

    // New: Update the detailed stops list in the tab
    function updateStopsTab(destinations, pickup, dropoff, status) {
      const container = document.getElementById('stops-list-container');
      if (!container) return;

      let list = [];
      if (destinations && destinations.length > 0) {
        list = destinations.map(d => ({ name: d.location_name, status: d.status, type: 'stop' }));
      } else {
        // Solo booking stops
        list = [
          { name: pickup, status: (status === 'confirmed' ? 'pending' : 'completed'), type: 'pickup' },
          { name: dropoff, status: 'pending', type: 'dropoff' }
        ];
      }

      let html = '';
      list.forEach((stop, i) => {
        const isCompleted = stop.status === 'completed';
        const isArrived = stop.status === 'arrived';
        const isLast = i === list.length - 1;

        html += `
                <div class="flex items-start gap-4 p-4 rounded-xl border ${isCompleted ? 'bg-emerald-50/50 border-emerald-100' : (isArrived ? 'bg-amber-50/50 border-amber-100' : 'bg-gray-50/50 border-gray-100')} transition-all">
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 flex items-center justify-center rounded-lg ${isCompleted ? 'bg-emerald-100 text-emerald-600' : (isArrived ? 'bg-amber-100 text-amber-600 animate-pulse' : 'bg-white text-gray-400')}">
                            <i class="${isCompleted ? 'ri-checkbox-circle-fill' : (stop.type === 'dropoff' ? 'ri-flag-2-fill text-rose-500' : 'ri-map-pin-2-fill')} text-lg"></i>
                        </div>
                        ${!isLast ? '<div class="w-0.5 h-8 bg-gray-100"></div>' : ''}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-900 capitalize">${stop.name}</p>
                        <p class="text-[10px] ${isCompleted ? 'text-emerald-600' : 'text-gray-400'} font-black uppercase tracking-widest mt-1">
                            ${isCompleted ? 'Completed' : (isArrived ? 'Currently Here' : 'Upcoming')}
                        </p>
                    </div>
                </div>
            `;
      });
      container.innerHTML = html;
    }
  </script>
  <!-- Chat UI -->
  <div class="chat-bubble-container">
    <div id="chat-window" class="chat-window shadow-2xl">
      <div class="p-4 bg-teal-600 text-white flex items-center justify-between">
        <div class="flex items-center gap-3">
          <img src="<?php echo getProfilePic($booking['partner_pic'] ?? '', '../'); ?>"
            class="w-8 h-8 rounded-full border-2 border-white/20">
          <div>
            <h4 class="text-xs font-bold">
              <?php echo htmlspecialchars($booking['partner_name'] ?? 'TripSync Partner'); ?></h4>
            <p class="text-[9px] text-teal-100 font-bold uppercase tracking-widest">Driver / Partner</p>
          </div>
        </div>
        <button onclick="toggleChat()" class="text-white/60 hover:text-white"><i
            class="ri-close-line text-lg"></i></button>
      </div>
      <div id="chat-messages" class="chat-messages no-scrollbar">
        <div class="flex items-center justify-center h-full opacity-40">
          <p class="text-[10px] font-black uppercase tracking-widest">No messages yet</p>
        </div>
      </div>
      <div class="p-4 bg-white border-t border-gray-100 flex gap-2">
        <input type="text" id="chat-input" placeholder="Type a message..."
          class="flex-1 bg-gray-50 border-0 rounded-xl px-4 py-2 text-xs font-medium focus:ring-2 focus:ring-teal-500/20 outline-none">
        <button onclick="sendMessage()"
          class="w-10 h-10 bg-teal-600 text-white rounded-xl flex items-center justify-center hover:bg-teal-700 transition-all">
          <i class="ri-send-plane-2-fill"></i>
        </button>
      </div>
    </div>
    <button onclick="toggleChat()" class="chat-toggle-btn shadow-2xl">
      <i class="ri-message-3-fill"></i>
      <span id="chat-unread-badge" class="chat-badge">0</span>
    </button>
  </div>

  <script>
    const chatBookingId = <?php echo (int) $booking_id; ?>;
    const chatReceiverId = <?php echo (int) $booking['assigned_partner_id']; ?>;
    let lastMessageCount = 0;

    function toggleChat() {
      const win = document.getElementById('chat-window');
      if (win.style.display === 'flex') {
        win.style.display = 'none';
      } else {
        win.style.display = 'flex';
        loadMessages();
        document.getElementById('chat-unread-badge').style.display = 'none';
      }
    }

    async function loadMessages() {
      try {
        const response = await fetch(`../api/get_messages.php?booking_id=${chatBookingId}`);
        const data = await response.json();
        if (data.success) {
          const container = document.getElementById('chat-messages');
          const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;

          let html = '';
          data.messages.forEach(msg => {
            const isSent = msg.sender_id == <?php echo $user_id; ?>;
            html += `
                          <div class="message-row ${isSent ? 'sent' : 'received'}">
                              <div class="message-bubble font-medium">${msg.message}</div>
                              <span class="message-time">${new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                          </div>
                      `;
          });

          if (data.messages.length > 0) {
            container.innerHTML = html;
          }

          if (data.messages.length > lastMessageCount) {
            const chatWin = document.getElementById('chat-window');
            if (chatWin.style.display !== 'flex') {
              const badge = document.getElementById('chat-unread-badge');
              const unreadCount = data.messages.filter(m => !m.is_read && m.sender_id != <?php echo $user_id; ?>).length;
              badge.textContent = unreadCount;
              if (unreadCount > 0) badge.style.display = 'flex';
            }

            if (isAtBottom || lastMessageCount === 0) {
              container.scrollTop = container.scrollHeight;
            }
            lastMessageCount = data.messages.length;
          }
        }
      } catch (error) {
        console.error('Chat poll error:', error);
      }
    }

    async function sendMessage() {
      const input = document.getElementById('chat-input');
      const message = input.value.trim();
      if (!message) return;

      try {
        const response = await fetch('../api/send_message.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            booking_id: chatBookingId,
            receiver_id: chatReceiverId,
            message: message
          })
        });
        const data = await response.json();
        if (data.success) {
          input.value = '';
          loadMessages();
        }
      } catch (error) {
        console.error('Send message error:', error);
      }
    }

    setInterval(loadMessages, 3000);

    document.getElementById('chat-input').addEventListener('keypress', (e) => {
      if (e.key === 'Enter') sendMessage();
    });
  </script>
</body>

</html>