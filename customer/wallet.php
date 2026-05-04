<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
checkAuth('customer');

$userId = $_SESSION['user_id'];

// Fallback for Local Sandbox Testing: process the transaction on return since Ngrok webhook might be offline
if (isset($_GET['status']) && $_GET['status'] == 'success' && isset($_GET['order_id']) && PAYHERE_MODE === 'sandbox') {
    $order_id = $_GET['order_id'];
    $stmt = $conn->prepare("SELECT amount FROM transactions WHERE transaction_ref = ? AND status = 'pending'");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $pendingTx = $res->fetch_assoc();
        $payAmount = $pendingTx['amount'];
        
        $conn->begin_transaction();
        try {
            // Update transaction status
            $upTx = $conn->prepare("UPDATE transactions SET status = 'completed' WHERE transaction_ref = ?");
            $upTx->bind_param("s", $order_id);
            $upTx->execute();
            
            // Add balance to wallet
            $chkWal = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
            $chkWal->bind_param("i", $userId);
            $chkWal->execute();
            if ($chkWal->get_result()->num_rows > 0) {
                $upWal = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
                $upWal->bind_param("di", $payAmount, $userId);
                $upWal->execute();
            } else {
                $insWal = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
                $insWal->bind_param("id", $userId, $payAmount);
                $insWal->execute();
            }
            $conn->commit();
            // Redirect to clean URL
            header("Location: wallet.php?status=success");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
}

// Fetch wallet balance
$walletStmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$walletStmt->bind_param("i", $userId);
$walletStmt->execute();
$wallet = $walletStmt->get_result()->fetch_assoc();
$balance = $wallet ? $wallet['balance'] : 0.00;

// Fetch transactions
$transStmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$transStmt->bind_param("i", $userId);
$transStmt->execute();
$transactions = $transStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch stats
$spentStmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'debit' AND status = 'completed'");
$spentStmt->bind_param("i", $userId);
$spentStmt->execute();
$totalSpent = $spentStmt->get_result()->fetch_assoc()['total'] ?: 0.00;

$pendingStmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND status = 'pending'");
$pendingStmt->bind_param("i", $userId);
$pendingStmt->execute();
$totalPending = $pendingStmt->get_result()->fetch_assoc()['total'] ?: 0.00;

// Fetch Detailed Bookings for QR Passes & Receipts
$bookingStmt = $conn->prepare("
    SELECT b.*, 
           COALESCE(h.name, v.model) as service_name,
           COALESCE(h.location, b.pickup_location) as location,
           COALESCE(h.image_path, v.image_path) as image_path,
           v.model as vehicle_model,
           h.name as hotel_name
    FROM bookings b
    LEFT JOIN hotels h ON b.type = 'hotel' AND b.item_id = h.id
    LEFT JOIN vehicles v ON b.type = 'vehicle' AND b.item_id = v.id
    WHERE b.user_id = ? AND b.status IN ('confirmed', 'completed')
    ORDER BY b.start_date DESC
");
$bookingStmt->bind_param("i", $userId);
$bookingStmt->execute();
$allBookings = $bookingStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Google API details for bookings missing local names
$googleApiLoaded = false;
foreach ($allBookings as &$booking) {
  if ($booking['type'] === 'hotel' && (strpos($booking['item_id'], 'google_') === 0 || strlen($booking['item_id']) > 15)) {
    if (empty($booking['service_name']) || empty($booking['location'])) {
      if (!$googleApiLoaded) {
        require_once '../api/google_places_api.php';
        $googleApi = new GooglePlacesAPI();
        $googleApiLoaded = true;
      }
      $place_id = str_replace('google_', '', $booking['item_id']);
      $details = $googleApi->getDetailsByPlaceId($place_id);
      if ($details) {
        if (empty($booking['service_name']))
          $booking['service_name'] = $details['name'];
        if (empty($booking['location']))
          $booking['location'] = $details['address'];
        if (empty($booking['image_path']) && !empty($details['photos'])) {
          $booking['image_path'] = $details['photos'][0];
        }
      }
    }
    else {
      // Even if name and location are cached locally but image is missing for a Google booking, we should fetch it.
      if (empty($booking['image_path'])) {
        if (!$googleApiLoaded) {
          require_once '../api/google_places_api.php';
          $googleApi = new GooglePlacesAPI();
          $googleApiLoaded = true;
        }
        $place_id = str_replace('google_', '', $booking['item_id']);
        $booking['image_path'] = $googleApi->getPlacePhoto($booking['service_name']);
      }
    }
  }
}
unset($booking); // Break reference

$successMsg = isset($_GET['status']) && $_GET['status'] == 'success' ? "Payment completed successfully!" : null;
$errorMsg = isset($_GET['status']) && $_GET['status'] == 'cancel' ? "Payment was cancelled." : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Travel Wallet - TripSync</title>
  <meta name="description" content="Manage your travel funds, bookings, payments & QR passes.">
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
        <div class="max-w-6xl mx-auto">
          <?php if ($successMsg): ?>
          <div class="mb-6 bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-3 animate-in fade-in slide-in-from-top-2 duration-500">
            <i class="ri-checkbox-circle-line text-lg"></i>
            <?php echo $successMsg; ?>
          </div>
          <?php
endif; ?>
          <?php if ($errorMsg): ?>
          <div class="mb-6 bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm flex items-center gap-3 animate-in fade-in slide-in-from-top-2 duration-500">
            <i class="ri-error-warning-line text-lg"></i>
            <?php echo $errorMsg; ?>
          </div>
          <?php
endif; ?>
          <div class="flex items-center justify-between mb-8">
            <div>
              <h1 class="text-3xl font-bold text-gray-900 mb-1">Digital Travel Wallet</h1>
              <p class="text-gray-500 text-sm">Manage your bookings, payments &amp; QR passes</p>
            </div>
            <button onclick="openTopUpModal()"
              class="px-6 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-full hover:bg-teal-700 transition-colors whitespace-nowrap cursor-pointer flex items-center gap-2">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-add-line text-sm"></i></div>Top Up
              Wallet
            </button>
          </div>

          <div class="mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
              <div
                class="lg:col-span-1 bg-gradient-to-br from-teal-600 to-teal-700 rounded-2xl p-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-40 h-40 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2">
                </div>
                <div
                  class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2">
                </div>
                <div class="relative z-10">
                  <p class="text-teal-100 text-sm mb-1">Available Balance</p>
                  <h2 class="text-4xl font-bold mb-4">LKR <?php echo number_format($balance, 2); ?></h2>
                  <div class="flex items-center gap-2 text-teal-200 text-xs">
                    <div class="w-4 h-4 flex items-center justify-center"><i class="ri-shield-check-line text-sm"></i>
                    </div>Secured by TripSync
                  </div>
                </div>
              </div>
              <div class="bg-white rounded-2xl border border-gray-200 p-6 flex items-center gap-5">
                <div class="w-14 h-14 flex items-center justify-center bg-gray-50 rounded-2xl flex-shrink-0"><i
                    class="ri-time-line text-2xl text-amber-600"></i></div>
                <div>
                  <p class="text-sm text-gray-500 mb-0.5">Pending</p>
                  <p class="text-2xl font-bold text-gray-900">LKR <?php echo number_format($totalPending, 2); ?></p>
                </div>
              </div>
              <div class="bg-white rounded-2xl border border-gray-200 p-6 flex items-center gap-5">
                <div class="w-14 h-14 flex items-center justify-center bg-gray-50 rounded-2xl flex-shrink-0"><i
                    class="ri-shopping-bag-line text-2xl text-rose-600"></i></div>
                <div>
                  <p class="text-sm text-gray-500 mb-0.5">Total Spent</p>
                  <p class="text-2xl font-bold text-gray-900">LKR <?php echo number_format($totalSpent, 2); ?></p>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="flex items-center gap-1 bg-white rounded-full p-1 shadow-sm border border-gray-200 w-fit mb-8">
            <button id="transactions-tab" onclick="switchTab('transactions')"
              class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-teal-600 text-white shadow-sm">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-exchange-line text-sm"></i></div>
              Transactions
            </button>
            <button id="qr-passes-tab" onclick="switchTab('qr-passes')"
              class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap text-gray-500 hover:text-gray-700">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-qr-code-line text-sm"></i></div>QR
              Passes
            </button>
            <button id="receipts-tab" onclick="switchTab('receipts')"
              class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap text-gray-500 hover:text-gray-700">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-file-list-3-line text-sm"></i></div>
              Receipts
            </button>
          </div>

          <!-- Transactions Content -->
          <div id="transactions-content" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
              <h3 class="font-semibold text-gray-900 text-base">Payment History</h3><span
                class="text-xs text-gray-500"><?php echo count($transactions); ?> transactions</span>
            </div>
            <div class="divide-y divide-gray-50">
              <?php if (empty($transactions)): ?>
              <div class="px-6 py-8 text-center">
                <p class="text-gray-500 text-sm">No transactions yet.</p>
              </div>
              <?php
else: ?>
              <?php foreach ($transactions as $trans): ?>
              <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50 transition-colors">
                <div class="flex items-center gap-4">
                  <div class="w-11 h-11 flex items-center justify-center rounded-xl <?php echo $trans['type'] == 'credit' ? 'bg-emerald-100 text-emerald-600' : 'bg-teal-100 text-teal-600'; ?>">
                    <i class="ri-<?php echo $trans['type'] == 'credit' ? 'refund-2-line' : 'hotel-line'; ?> text-xl"></i>
                  </div>
                  <div>
                    <h4 class="font-medium text-gray-900 text-sm"><?php echo $trans['type'] == 'credit' ? 'Wallet Top-up' : 'Booking Payment'; ?></h4>
                    <p class="text-xs text-gray-500 mt-0.5"><?php echo $trans['transaction_ref']; ?> · <?php echo date('M d, Y', strtotime($trans['created_at'])); ?></p>
                  </div>
                </div>
                <div class="text-right">
                  <p class="font-bold text-sm <?php echo $trans['type'] == 'credit' ? 'text-emerald-600' : 'text-gray-900'; ?>">
                    <?php echo($trans['type'] == 'credit' ? '+' : '-') . 'LKR ' . number_format($trans['amount'], 2); ?>
                  </p>
                  <span class="text-xs text-gray-400 capitalize"><?php echo $trans['status']; ?></span>
                </div>
              </div>
              <?php
  endforeach; ?>
              <?php
endif; ?>
            </div>
          </div>

          <!-- QR Passes Content -->
          <div id="qr-passes-content" class="hidden">
            <div class="flex items-center gap-2 mb-6">
              <button onclick="filterQR('all')" id="qr-all" class="px-4 py-2 rounded-full text-xs font-medium transition-colors cursor-pointer whitespace-nowrap capitalize bg-teal-600 text-white">all</button>
              <button onclick="filterQR('upcoming')" id="qr-upcoming" class="px-4 py-2 rounded-full text-xs font-medium transition-colors cursor-pointer whitespace-nowrap capitalize bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">upcoming</button>
              <button onclick="filterQR('completed')" id="qr-completed" class="px-4 py-2 rounded-full text-xs font-medium transition-colors cursor-pointer whitespace-nowrap capitalize bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">completed</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <?php if (empty($allBookings)): ?>
                <div class="md:col-span-2 text-center py-12 bg-white rounded-2xl border border-gray-100 shadow-sm">
                  <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-qr-code-line text-3xl text-gray-300"></i>
                  </div>
                  <h3 class="text-gray-900 font-bold">No active passes</h3>
                  <p class="text-gray-500 text-sm">Your booking QR passes will appear here.</p>
                </div>
              <?php
else: ?>
                <?php foreach ($allBookings as $b):
    $isUpcoming = strtotime($b['start_date']) > time();
    $status = $isUpcoming ? 'upcoming' : 'completed';
    $statusClass = $isUpcoming ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-500';

    // Image Logic
    $img = '';
    if (!empty($b['image_path'])) {
      if (strpos($b['image_path'], 'http') === 0) {
        $img = $b['image_path'];
      }
      else {
        $full_path = __DIR__ . '/../assets/images/' . $b['image_path'];
        if (file_exists($full_path)) {
          $img = '../assets/images/' . $b['image_path'];
        }
      }
    }

    if (empty($img)) {
      // Fallback to placeholder if static map fails or wasn't generated
      $img = '../assets/images/placeholder.jpg';
    }
?>
                <div class="qr-item bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-all" data-status="<?php echo $status; ?>">
                  <div class="flex">
                    <div class="w-28 h-28 flex-shrink-0">
                      <img alt="<?php echo htmlspecialchars($b['service_name']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($img); ?>" onerror="this.onerror=null; this.src='../assets/images/placeholder.jpg';">
                    </div>
                    <div class="flex-1 p-4">
                      <div class="flex items-start justify-between mb-1">
                        <h3 class="font-semibold text-gray-900 text-sm leading-tight"><?php echo htmlspecialchars($b['service_name']); ?></h3>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full whitespace-nowrap <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                      </div>
                      <p class="text-xs text-gray-500 mb-2"><i class="ri-map-pin-line mr-1"></i><?php echo htmlspecialchars($b['location'] ?: 'Sri Lanka'); ?></p>
                      <div class="flex items-center gap-3 text-xs text-gray-600">
                        <span><?php echo date('Y-m-d', strtotime($b['start_date'])); ?></span>
                        <i class="ri-arrow-right-line text-gray-400"></i>
                        <span><?php echo date('Y-m-d', strtotime($b['end_date'])); ?></span>
                      </div>
                    </div>
                  </div>
                  <div class="border-t border-gray-100 px-4 py-3 flex items-center justify-between">
                    <div class="text-xs text-gray-500">
                      <span class="font-medium text-gray-700"><?php echo $b['reference_no']; ?></span> · <?php echo ucfirst($b['type']); ?>
                    </div>
                    <button onclick="showBookingQR('<?php echo $b['reference_no']; ?>', '<?php echo htmlspecialchars($b['service_name']); ?>')" class="flex items-center gap-1 text-teal-600 text-xs font-medium cursor-pointer whitespace-nowrap hover:text-teal-700">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-qr-code-line text-sm"></i></div>
                      Show QR
                    </button>
                  </div>
                </div>
                <?php
  endforeach; ?>
              <?php
endif; ?>
            </div>
          </div>

          <!-- Receipts Content -->
          <div id="receipts-content" class="hidden">
            <div class="space-y-4">
              <?php if (empty($allBookings)): ?>
                <div class="text-center py-12 bg-white rounded-2xl border border-gray-100 shadow-sm">
                  <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-file-list-line text-3xl text-gray-300"></i>
                  </div>
                  <h3 class="text-gray-900 font-bold">No receipts found</h3>
                  <p class="text-gray-500 text-sm">Your booking receipts will appear here.</p>
                </div>
              <?php
else: ?>
                <?php foreach ($allBookings as $b): ?>
                <div onclick="openReceipt('<?php echo addslashes($b['service_name']); ?>', '<?php echo $b['reference_no']; ?>', '<?php echo date('F d, Y', strtotime($b['created_at'])); ?>', '<?php echo number_format($b['total_price'], 2); ?>')"
                  class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-md transition-all cursor-pointer">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                      <div class="w-11 h-11 flex items-center justify-center rounded-xl <?php echo $b['type'] === 'hotel' ? 'bg-teal-100 text-teal-600' : 'bg-orange-100 text-orange-600'; ?>">
                        <i class="ri-<?php echo $b['type'] === 'hotel' ? 'hotel-line' : 'car-line'; ?> text-xl"></i>
                      </div>
                      <div>
                        <h3 class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($b['service_name']); ?></h3>
                        <p class="text-xs text-gray-500 mt-0.5">Ref: <?php echo $b['reference_no']; ?> · <?php echo date('Y-m-d', strtotime($b['created_at'])); ?></p>
                      </div>
                    </div>
                    <div class="text-right flex items-center gap-3">
                      <div>
                        <p class="font-bold text-gray-900 text-sm">LKR <?php echo number_format($b['total_price'], 2); ?></p>
                        <span class="text-xs text-emerald-600 font-medium capitalize">Paid</span>
                      </div>
                      <div class="w-5 h-5 flex items-center justify-center"><i class="ri-arrow-right-s-line text-gray-400"></i></div>
                    </div>
                  </div>
                </div>
                <?php
  endforeach; ?>
              <?php
endif; ?>
            </div>
          </div>

          <!-- JavaScript for Tabs and Receipt -->
          <script>
            function switchTab(tab) {
              const transTab = document.getElementById('transactions-tab');
              const qrTab = document.getElementById('qr-passes-tab');
              const recTab = document.getElementById('receipts-tab');

              const transContent = document.getElementById('transactions-content');
              const qrContent = document.getElementById('qr-passes-content');
              const recContent = document.getElementById('receipts-content');

              const tabs = [transTab, qrTab, recTab];
              const contents = [transContent, qrContent, recContent];

              tabs.forEach(t => {
                t.classList.remove('bg-teal-600', 'text-white', 'shadow-sm');
                t.classList.add('text-gray-500', 'hover:text-gray-700');
              });

              contents.forEach(c => {
                if(c) c.classList.add('hidden');
              });

              document.getElementById(tab + '-tab').classList.add('bg-teal-600', 'text-white', 'shadow-sm');
              document.getElementById(tab + '-tab').classList.remove('text-gray-500', 'hover:text-gray-700');
              document.getElementById(tab + '-content').classList.remove('hidden');
            }

            function filterQR(status) {
              const items = document.querySelectorAll('.qr-item');
              const buttons = ['all', 'upcoming', 'completed'];
              
              buttons.forEach(b => {
                const btn = document.getElementById('qr-' + b);
                btn.classList.remove('bg-teal-600', 'text-white');
                btn.classList.add('bg-white', 'border', 'border-gray-200', 'text-gray-600');
              });

              document.getElementById('qr-' + status).classList.remove('bg-white', 'border', 'border-gray-200', 'text-gray-600');
              document.getElementById('qr-' + status).classList.add('bg-teal-600', 'text-white');

              items.forEach(item => {
                if (status === 'all' || item.dataset.status === status) {
                  item.classList.remove('hidden');
                } else {
                  item.classList.add('hidden');
                }
              });
            }

            function showBookingQR(ref, name) {
              document.getElementById('qr-modal-ref').innerText = ref;
              document.getElementById('qr-modal-name').innerText = name;
              document.getElementById('qr-image').src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + ref;
              document.getElementById('qr-modal').classList.remove('hidden');
            }

            function closeQRModal() {
              document.getElementById('qr-modal').classList.add('hidden');
            }

            function openTopUpModal() {
              document.getElementById('topup-modal').classList.remove('hidden');
            }

            function closeTopUpModal() {
              document.getElementById('topup-modal').classList.add('hidden');
            }

            function openReceipt(name, ref, date, total) {
              document.getElementById('receipt-name').innerText = name;
              document.getElementById('receipt-ref').innerText = ref;
              document.getElementById('receipt-date').innerText = 'Date: ' + date;
              document.getElementById('receipt-total').innerText = 'LKR ' + total;
              document.getElementById('receipt-total-main').innerText = 'LKR ' + total;
              document.getElementById('receipt-modal').classList.remove('hidden');
            }

            function closeReceipt() {
              document.getElementById('receipt-modal').classList.add('hidden');
            }

            document.addEventListener('DOMContentLoaded', function() {
              const form = document.getElementById('payhere-form');
              if (form) {
                  form.addEventListener('submit', function(e) {
                      if (!document.getElementById('hash-field').value) {
                          e.preventDefault();
                          const amount = document.getElementById('topup-amount').value;
                          const amountFormatted = parseFloat(amount).toFixed(2);
                          
                          fetch('../api/generate-hash.php', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                              body: 'amount=' + amountFormatted + '&type=TOPUP'
                          })
                          .then(res => res.json())
                          .then(data => {
                              document.getElementById('hash-field').value = data.hash;
                              document.getElementById('order-id-field').value = data.order_id;
                              document.getElementById('topup-amount').value = amountFormatted;
                              form.submit();
                          });
                      }
                  });
              }
            });
          </script>

          <!-- QR Modal -->
          <div id="qr-modal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 hidden">
            <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center relative">
              <button onclick="closeQRModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="ri-close-line text-2xl"></i>
              </button>
              <div class="mb-6">
                <div class="w-16 h-16 bg-teal-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                  <i class="ri-qr-code-line text-3xl text-teal-600"></i>
                </div>
                <h3 id="qr-modal-name" class="text-xl font-bold text-gray-900 mb-1">Cinnamon Grand Colombo</h3>
                <p id="qr-modal-ref" class="text-gray-500 text-sm font-medium">BK-HTL-4821</p>
              </div>
              <div class="bg-gray-50 rounded-2xl p-6 mb-6 inline-block mx-auto border-2 border-dashed border-gray-200">
                <!-- Generating a QR code using a public API for demonstration -->
                <img id="qr-image" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=BK-HTL-4821" alt="Booking QR" class="w-48 h-48 mx-auto">
              </div>
              <p class="text-xs text-gray-400 leading-relaxed uppercase tracking-wider font-semibold">Scan at destination for check-in</p>
              <div class="mt-8 pt-6 border-t border-gray-100">
                <button onclick="window.print()" class="w-full py-3 border border-gray-200 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                  <i class="ri-printer-line"></i> Print Pass
                </button>
              </div>
            </div>
          </div>

          <!-- Top Up Modal -->
          <div id="topup-modal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 hidden">
            <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
              <h3 class="text-xl font-bold text-gray-900 mb-4">Top Up Wallet</h3>
              <form action="https://sandbox.payhere.lk/pay/checkout" method="post" id="payhere-form">
                <input type="hidden" name="merchant_id" value="<?php echo PAYHERE_MERCHANT_ID; ?>">
                <input type="hidden" name="return_url" value="<?php echo BASE_URL; ?>customer/wallet.php?status=success">
                <input type="hidden" name="cancel_url" value="<?php echo BASE_URL; ?>customer/wallet.php?status=cancel">
                <input type="hidden" name="notify_url" value="https://woozy-unmelodramatically-nancey.ngrok-free.dev/TripSync/api/payhere-notify.php">
                <input type="hidden" name="order_id" id="order-id-field" value="">
                <input type="hidden" name="items" value="Wallet Top-up">
                <input type="hidden" name="currency" value="LKR">
                <input type="hidden" name="first_name" value="<?php echo addslashes($_SESSION['user_name'] ?? ''); ?>">
                <input type="hidden" name="last_name" value="">
                <input type="hidden" name="email" value="<?php echo $_SESSION['user_email'] ?? ''; ?>">
                <input type="hidden" name="phone" value="0771234567">
                <input type="hidden" name="address" value="Sri Lanka">
                <input type="hidden" name="city" value="Colombo">
                <input type="hidden" name="country" value="Sri Lanka">
                <input type="hidden" name="hash" id="hash-field" value="">
                
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Amount (LKR)</label>
                  <input type="number" name="amount" id="topup-amount" required min="100" step="100" placeholder="e.g., 5000"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                
                <div class="flex gap-3">
                  <button type="button" onclick="closeTopUpModal()"
                    class="flex-1 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                  <button type="submit"
                    class="flex-1 py-2.5 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 transition-colors shadow-sm">Pay Now</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Receipt Modal -->
          <div id="receipt-modal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 hidden">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative overflow-hidden">
              <div class="bg-gradient-to-r from-teal-600 to-teal-700 px-6 py-5 text-white">
                <button onclick="closeReceipt()"
                  class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center text-white/70 hover:text-white cursor-pointer"><i
                    class="ri-close-line text-xl"></i></button>
                <div class="flex items-center gap-3 mb-2">
                  <div class="w-10 h-10 flex items-center justify-center bg-white/20 rounded-xl"><i
                      class="ri-file-list-3-line text-xl"></i></div>
                  <div>
                    <h3 id="receipt-name" class="font-bold text-base">Cinnamon Grand Colombo</h3>
                    <p id="receipt-ref" class="text-teal-100 text-xs">BK-HTL-4821</p>
                  </div>
                </div>
              </div>
              <div class="px-6 py-5">
                <div class="flex items-center justify-between text-xs text-gray-500 mb-4">
                  <span id="receipt-date">Date: January 28, 2025</span>
                  <span
                    class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-medium capitalize">paid</span>
                </div>
                <div class="space-y-3 mb-5">
                  <div class="flex items-center justify-between"><span class="text-sm text-gray-700">Booking
                      Amount</span><span id="receipt-total" class="text-sm font-medium text-gray-900">LKR 25,000</span>
                  </div>
                  <div class="flex items-center justify-between"><span class="text-sm text-gray-700">Service charge
                      (Included)</span><span class="text-sm font-medium text-gray-900">LKR 0</span></div>
                </div>
                <div class="border-t border-dashed border-gray-200 pt-4 mb-5">
                  <div class="flex items-center justify-between"><span class="font-bold text-gray-900">Total</span><span
                      id="receipt-total-main" class="font-bold text-lg text-gray-900">LKR 25,000</span></div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 mb-5">
                  <div class="flex items-center justify-between text-sm"><span class="text-gray-500">Payment
                      Method</span><span class="font-medium text-gray-900 flex items-center gap-1.5">
                      <div class="w-4 h-4 flex items-center justify-center"><i
                          class="ri-wallet-3-line text-sm text-teal-600"></i></div>TripSync Wallet
                    </span></div>
                </div>
                <button onclick="closeReceipt()"
                  class="w-full py-3 bg-gray-100 text-gray-700 font-medium rounded-full hover:bg-gray-200 transition-colors whitespace-nowrap cursor-pointer text-sm">Close
                  Receipt</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white font-outfit">
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
</body>

</html>
