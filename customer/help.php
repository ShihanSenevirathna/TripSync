<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('customer');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Help Center - TripSync</title>
  <meta name="description" content="TripSync Help Center.">
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
      <section class="relative pt-20">
        <div class="absolute inset-0 h-80"><img alt="" class="w-full h-full object-cover"
            src="../assets/images/help_hero.jpg">
          <div class="absolute inset-0 bg-gradient-to-b from-teal-900/60 via-teal-800/50 to-teal-700/40"></div>
        </div>
        <div class="relative z-10 max-w-3xl mx-auto px-4 pt-16 pb-24 text-center">
          <h1 class="text-4xl font-bold text-white mb-3">How can we help you?</h1>
          <p class="text-teal-50 text-base mb-8">Find answers about bookings, payments, trip planning, and more</p>
          <div class="relative max-w-xl mx-auto">
            <div
              class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center text-gray-400"><i
                class="ri-search-line text-lg"></i></div><input placeholder="Search for help topics..."
              class="w-full pl-12 pr-4 py-4 rounded-2xl bg-white shadow-xl text-sm focus:ring-2 focus:ring-teal-500 focus:outline-none border-0"
              type="text" value="">
          </div>
        </div>
      </section>
      <div class="max-w-6xl mx-auto px-4 -mt-8 relative z-20 pb-16">
        <section class="mb-12">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div
                  class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-rocket-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">
                    Getting Started with TripSync</h3>
                  <p class="text-xs text-gray-400">2,840 views</p>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div
                  class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-qr-code-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">How
                    to Use Your QR Pass at Hotels</h3>
                  <p class="text-xs text-gray-400">1,920 views</p>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div
                  class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-map-pin-star-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">Top
                    10 Sri Lanka Destinations for 2025</h3>
                  <p class="text-xs text-gray-400">3,150 views</p>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div
                  class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-wallet-3-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">
                    Understanding Your Travel Wallet</h3>
                  <p class="text-xs text-gray-400">1,680 views</p>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div
                  class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-shield-check-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">
                    Safety Tips for Solo Travelers</h3>
                  <p class="text-xs text-gray-400">2,210 views</p>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div
                  class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-car-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">
                    Booking Your First Vehicle Rental</h3>
                  <p class="text-xs text-gray-400">1,450 views</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Dynamic Support Tickets Section -->
        <section class="mb-12">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-bold text-gray-900">My Support Tickets</h2>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Active Conversations</p>
          </div>
          <div class="grid grid-cols-1 gap-3">
            <?php
            $t_stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $t_stmt->bind_param("i", $_SESSION['user_id']);
            $t_stmt->execute();
            $user_tickets = $t_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if (empty($user_tickets)):
            ?>
              <div class="bg-white border border-dashed border-gray-200 rounded-2xl p-8 text-center">
                <i class="ri-chat-history-line text-3xl text-gray-200 mb-2 block"></i>
                <p class="text-xs text-gray-400 font-medium italic">No active support tickets found.</p>
              </div>
            <?php else: ?>
              <?php foreach ($user_tickets as $ut): ?>
                <a href="view_ticket.php?id=<?php echo $ut['id']; ?>" class="block bg-white rounded-xl border border-gray-100 p-5 hover:shadow-lg hover:border-teal-200 transition-all group">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                      <div class="w-10 h-10 flex items-center justify-center rounded-xl <?php echo $ut['priority'] == 'emergency' ? 'bg-rose-50 text-rose-500' : 'bg-teal-50 text-teal-600'; ?>">
                        <i class="<?php echo $ut['priority'] == 'emergency' ? 'ri-alarm-warning-line' : 'ri-question-line'; ?> text-lg"></i>
                      </div>
                      <div class="flex-1 pr-4">
                        <h4 class="text-sm font-bold text-gray-900 group-hover:text-teal-700 transition-colors uppercase truncate max-w-md"><?php echo htmlspecialchars($ut['subject']); ?></h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-0.5">Ticket #ST-<?php echo str_pad($ut['id'], 4, '0', STR_PAD_LEFT); ?> • <?php echo date('M j, Y', strtotime($ut['created_at'])); ?></p>
                      </div>
                    </div>
                    <div class="flex items-center gap-3">
                      <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php 
                        echo $ut['status'] == 'open' ? 'bg-emerald-100 text-emerald-700' : ($ut['status'] == 'in_progress' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700');
                      ?>"><?php echo ucfirst($ut['status']); ?></span>
                      <i class="ri-arrow-right-s-line text-gray-300 group-hover:text-teal-500 transition-colors"></i>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <section class="mb-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-5"><a href="help.php"
              class="hover:text-teal-700 transition-colors">Frequently Asked Questions</a></h2>
          <div class="flex flex-wrap gap-2 mb-6"><button
              class="px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-teal-600 text-white shadow-sm">All
              Topics</button><button
              class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-teal-300 hover:text-teal-600">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-calendar-check-line text-sm"></i></div>
              Bookings
            </button><button
              class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-teal-300 hover:text-teal-600">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-bank-card-line text-sm"></i></div>
              Payments &amp; Refunds
            </button><button
              class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-teal-300 hover:text-teal-600">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-route-line text-sm"></i></div>Trip
              Planning
            </button><button
              class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-teal-300 hover:text-teal-600">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-car-line text-sm"></i></div>Vehicles
              &amp; Drivers
            </button><button
              class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-teal-300 hover:text-teal-600">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-hotel-line text-sm"></i></div>Hotels
              &amp; Stays
            </button><button
              class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all cursor-pointer whitespace-nowrap bg-white text-gray-600 border border-gray-200 hover:border-teal-300 hover:text-teal-600">
              <div class="w-4 h-4 flex items-center justify-center"><i class="ri-shield-user-line text-sm"></i></div>
              Account &amp; Security
            </button></div>
        </section>
        <section class="mb-16">
          <div class="space-y-3">
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">How
                    do I book a hotel or vehicle on TripSync?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Navigate to the Marketplace from the top menu, browse
                    available hotels or vehicles, select your preferred option, choose your dates and guests, then click
                    "Book Now". You can pay directly through your TripSync Wallet or use a card. A confirmation with a
                    QR pass will be sent to your wallet instantly.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Can I
                    modify or cancel a booking after confirmation?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Yes! Go to your Dashboard or Wallet, find the booking
                    you want to change, and tap "Modify" or "Cancel". Free cancellation is available up to 48 hours
                    before check-in for hotels and 24 hours before pickup for vehicles. Late cancellations may incur a
                    fee of up to 50% of the booking value.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">What
                    happens if my driver does not show up?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">If your assigned driver fails to arrive within 15
                    minutes of the scheduled pickup, you can tap the SOS button on the tracking page. Our system will
                    automatically reassign a nearby available driver. If no replacement is found within 30 minutes, you
                    will receive a full refund to your TripSync Wallet.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">How
                    far in advance should I book?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">We recommend booking hotels at least 2 weeks in
                    advance during peak season (December-March) and vehicles at least 3 days ahead. Last‑minute bookings
                    are possible but availability may be limited, especially for popular destinations like Ella,
                    Sigiriya, and the Southern Coast.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">What
                    payment methods does TripSync accept?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">TripSync accepts Visa, Mastercard, and American
                    Express credit/debit cards, bank transfers, and TripSync Wallet balance. You can top up your wallet
                    anytime from the Wallet page. All transactions are secured with 256‑bit SSL encryption.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">How
                    long does a refund take to process?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Refunds to your TripSync Wallet are instant. Refunds
                    to credit/debit cards take 5‑7 business days. Bank transfer refunds may take up to 10 business days
                    depending on your bank. You can track refund status from your Wallet transaction history.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Is
                    there a service fee on bookings?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">TripSync charges a small service fee of 5‑10%
                    depending on the booking type. Hotels typically have a 10% service charge while vehicle rentals have
                    a 5% fee. All fees are clearly displayed before you confirm your booking so there are no surprises.
                  </p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Can I
                    get an invoice or receipt for my bookings?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Absolutely! Every completed booking generates a
                    detailed receipt available in your Wallet under the Receipts tab. You can view, download as PDF, or
                    share receipts via email. Receipts include itemized charges, taxes, and payment method details.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">How
                    does the smart itinerary builder work?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Our drag-and-drop itinerary builder lets you add
                    destinations, hotels, and activities to each day of your trip. The AI‑powered route optimizer
                    automatically suggests the most efficient travel order, estimates driving times, and recommends
                    nearby attractions you might enjoy.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Can I
                    share my trip plan with travel companions?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Yes! Once your itinerary is created, use the Share
                    button to generate a link. Your companions can view the full plan including maps, bookings, and
                    schedules. They can also suggest changes which you can approve or decline from your dashboard.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">What
                    destinations are available on TripSync?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">TripSync covers all 25 districts of Sri Lanka with
                    curated experiences in popular destinations like Colombo, Kandy, Galle, Ella, Sigiriya, Trincomalee,
                    Jaffna, Nuwara Eliya, Yala, and Mirissa. We are constantly adding new locations and hidden gems
                    recommended by local partners.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Does
                    TripSync work offline?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Your confirmed bookings and QR passes are cached
                    locally so you can access them without internet. However, real‑time features like trip tracking,
                    messaging, and new bookings require an active connection. We recommend downloading your itinerary
                    before heading to remote areas.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Are
                    all drivers on TripSync verified?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Yes, every driver undergoes a thorough verification
                    process including valid driving license check, vehicle registration verification, background
                    screening, and a minimum 4.0 star rating requirement. Our admin team manually reviews all documents
                    before approving any partner.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">What
                    vehicle types are available?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">We offer sedans (Toyota Prius, Honda Civic), SUVs
                    (Toyota Land Cruiser, Mitsubishi Montero), vans (Toyota KDH, Nissan Caravan), economy cars (Suzuki
                    Alto, Toyota Aqua), and tuk‑tuks for short city rides. All vehicles come with AC, insurance, and a
                    professional driver.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Can I
                    request a specific driver for my trip?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Yes! If you have traveled with a driver before and
                    rated them, you can find them in your trip history and request them directly for future bookings.
                    Subject to their availability, the system will prioritize your preferred driver assignment.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">How
                    are hotel ratings determined?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Hotel ratings on TripSync combine the official star
                    classification with verified guest reviews. We display both the property star rating and the average
                    traveler rating so you get a complete picture. Only guests who completed a stay can leave reviews.
                  </p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Do
                    hotels offer free cancellation?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Most hotels on TripSync offer free cancellation up to
                    48 hours before check-in. Some properties during peak season may have stricter policies. The
                    cancellation terms are always clearly shown on the booking page before you confirm.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">How
                    do I reset my password?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Go to the login page and tap "Forgot Password". Enter
                    your registered email and we will send a reset link valid for 30 minutes. If you do not receive the
                    email, check your spam folder or contact our support team for assistance.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">Is my
                    personal data safe with TripSync?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">We take data security very seriously. All personal
                    data is encrypted at rest and in transit. We never share your information with third parties without
                    consent. You can request a full data export or account deletion anytime from your Profile Settings
                    page.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
            <div
              class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-teal-200 transition-all">
              <button class="w-full flex items-center justify-between px-6 py-5 text-left cursor-pointer">
                <div class="flex items-center gap-3 flex-1 pr-4"><span class="text-sm font-semibold text-gray-900">How
                    do I become a TripSync partner driver?</span></div>
                <div
                  class="w-6 h-6 flex items-center justify-center flex-shrink-0 text-gray-400 transition-transform duration-300 ">
                  <i class="ri-add-line text-lg"></i>
                </div>
              </button>
              <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
                <div class="px-6 pb-5 border-t border-gray-100 pt-4">
                  <p class="text-sm text-gray-600 leading-relaxed">Visit the Partner Registration page from the footer
                    or navigate to /partner/register. Complete the multi‑step form with your personal details, vehicle
                    information, and upload your driving license and vehicle registration. Our team reviews applications
                    within 2‑3 business days.</p>
                  <div class="mt-4 flex items-center gap-4"><span class="text-xs text-gray-400">Was this
                      helpful?</span><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-teal-600 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-up-line text-xs"></i>
                      </div>Yes
                    </button><button
                      class="flex items-center gap-1 text-xs text-gray-500 hover:text-rose-500 transition-colors cursor-pointer">
                      <div class="w-4 h-4 flex items-center justify-center"><i class="ri-thumb-down-line text-xs"></i>
                      </div>No
                    </button></div>
                </div>
              </div>
            </div>
          </div>
        </section>
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-16">
          <div class="bg-white rounded-2xl border border-gray-200 p-8">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Still need help?</h3>
            <p class="text-sm text-gray-500 mb-6">Send us a message and our team will get back to you within 24 hours.
            </p>
            <form id="help-contact-form" >
              <div class="space-y-4">
                <div><label class="block text-xs font-medium text-gray-600 mb-1.5">Your Name</label><input required=""
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                    placeholder="Enter your name" type="text" name="name"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1.5">Email Address</label><input
                    required=""
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                    placeholder="your@email.com" type="email" name="email"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1.5">Topic</label><select name="topic"
                    required=""
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white cursor-pointer">
                    <option value="">Select a topic</option>
                    <option value="Booking Issue">Booking Issue</option>
                    <option value="Payment &amp; Refund">Payment &amp; Refund</option>
                    <option value="Trip Planning">Trip Planning</option>
                    <option value="Driver Complaint">Driver Complaint</option>
                    <option value="Account Issue">Account Issue</option>
                    <option value="Other">Other</option>
                  </select></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1.5">Message</label><textarea
                    name="message" required="" maxlength="500" rows="4"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"
                    placeholder="Describe your issue..."></textarea>
                  <p class="text-xs text-gray-400 mt-1">Maximum 500 characters</p>
                </div><button type="submit" class="w-full py-3 bg-teal-600 text-white font-semibold text-sm rounded-full hover:bg-teal-7
00 transition-colors whitespace-nowrap cursor-pointer">Send Message</button>
              </div>
            </form>
          </div>
          <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-md transition-all">
              <div class="flex items-start gap-4">
                <div class="w-12 h-12 flex items-center justify-center bg-teal-50 rounded-xl flex-shrink-0"><i
                    class="ri-mail-send-line text-2xl text-teal-600"></i></div>
                <div>
                  <h4 class="font-semibold text-gray-900 text-sm mb-1"><a href="mailto:support@tripsync.lk"
                      class="hover:text-teal-600 transition-colors">Email Support</a></h4>
                  <p class="text-xs text-gray-500 mb-2">Get a response within 24 hours</p><a
                    href="mailto:support@tripsync.lk" rel="nofollow"
                    class="text-sm text-teal-600 font-medium hover:text-teal-700 cursor-pointer">support@tripsync.lk</a>
                </div>
              </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-md transition-all">
              <div class="flex items-start gap-4">
                <div class="w-12 h-12 flex items-center justify-center bg-orange-50 rounded-xl flex-shrink-0"><i
                    class="ri-phone-line text-2xl text-orange-600"></i></div>
                <div>
                  <h4 class="font-semibold text-gray-900 text-sm mb-1"><a href="tel:+94112345678"
                      class="hover:text-orange-600 transition-colors">Phone Support</a></h4>
                  <p class="text-xs text-gray-500 mb-2">Available Mon-Sat, 8 AM - 8 PM</p><a href="tel:+94112345678"
                    rel="nofollow" class="text-sm text-orange-600 font-medium hover:text-orange-700 cursor-pointer">+94
                    11 234 5678</a>
                </div>
              </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-md transition-all">
              <div class="flex items-start gap-4">
                <div class="w-12 h-12 flex items-center justify-center bg-emerald-50 rounded-xl flex-shrink-0"><i
                    class="ri-whatsapp-line text-2xl text-emerald-600"></i></div>
                <div>
                  <h4 class="font-semibold text-gray-900 text-sm mb-1">WhatsApp</h4>
                  <p class="text-xs text-gray-500 mb-2">Quick replies during business hours</p><a
                    href="https://wa.me/94112345678" target="_blank" rel="noopener noreferrer nofollow"
                    class="text-sm text-emerald-600 font-medium hover:text-emerald-700 cursor-pointer">Chat on
                    WhatsApp</a>
                </div>
              </div>
            </div>
            <div class="bg-gradient-to-br from-teal-600 to-teal-700 rounded-2xl p-6 text-white">
              <div class="flex items-start gap-4">
                <div class="w-12 h-12 flex items-center justify-center bg-white/15 rounded-xl flex-shrink-0"><i
                    class="ri-customer-service-2-line text-2xl"></i></div>
                <div>
                  <h4 class="font-semibold text-sm mb-1">Emergency Assistance</h4>
                  <p class="text-xs text-teal-100 mb-2">For urgent safety concerns during trips</p><a
                    href="tel:+94112345679" rel="nofollow"
                    class="text-sm font-bold hover:text-teal-200 cursor-pointer">+94 11 234 5679</a>
                </div>
              </div>
            </div>
          </div>
        </section>
        <section class="text-center">
          <p class="text-sm text-gray-500 mb-4">Explore more</p>
          <div class="flex flex-wrap justify-center gap-3"><a
              class="px-5 py-2.5 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-700 hover:border-teal-300 hover:text-teal-600 transition-all cursor-pointer whitespace-nowrap"
              href="marketplace.php" data-discover="true"><i class="ri-store-2-line mr-1.5"></i>Marketplace</a><a
              class="px-5 py-2.5 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-700 hover:border-teal-300 hover:text-teal-600 transition-all cursor-pointer whitespace-nowrap"
              href="wallet.php" data-discover="true"><i class="ri-wallet-3-line mr-1.5"></i>My Wallet</a><a
              class="px-5 py-2.5 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-700 hover:border-teal-300 hover:text-teal-600 transition-all cursor-pointer whitespace-nowrap"
              href="trip_history.php" data-discover="true"><i class="ri-history-line mr-1.5"></i>Trip History</a><a
              class="px-5 py-2.5 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-700 hover:border-teal-300 hover:text-teal-600 transition-all cursor-pointer whitespace-nowrap"
              href="#" data-discover="true"><i class="ri-message-3-line mr-1.5"></i>Messages</a></div>
        </section>
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


</body>

</html>
