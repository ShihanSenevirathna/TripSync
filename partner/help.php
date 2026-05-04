<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('partner');

$user_id = $_SESSION['user_id'];

// Handle Actions (like marking read via redirect)
if (isset($_GET['action']) && $_GET['action'] == 'mark_read' && isset($_GET['id'])) {
    $nid = intval($_GET['id']);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $nid AND user_id = $user_id");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Partner Help Center - TripSync</title>
  <meta name="description" content="TripSync Partner Help Center.">
  <link rel="stylesheet" href="../assets/css/fonts.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>

<body class="bg-gray-50/50">
  <div id="root">
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-teal-50/30">
      <?php include 'includes/navbar.php'; ?>
      
      <section class="relative pt-20">
        <div class="absolute inset-0 h-64"><img alt="" class="w-full h-full object-cover"
            src="../assets/images/help_hero.jpg">
          <div class="absolute inset-0 bg-gradient-to-b from-teal-900/60 via-teal-800/50 to-teal-700/40"></div>
        </div>
        <div class="relative z-10 max-w-3xl mx-auto px-4 pt-12 pb-16 text-center">
          <h1 class="text-3xl font-bold text-white mb-2">Partner Support Hub</h1>
          <p class="text-teal-50 text-sm mb-6">Manage your account, view earnings issues, and get safety assistance</p>
          <div class="relative max-w-lg mx-auto">
            <div
              class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center text-gray-400"><i
                class="ri-search-line text-lg"></i></div><input placeholder="Search help articles..."
              class="w-full pl-12 pr-4 py-3 rounded-xl bg-white shadow-xl text-sm focus:ring-2 focus:ring-teal-500 focus:outline-none border-0"
              type="text" value="">
          </div>
        </div>
      </section>

      <div class="max-w-6xl mx-auto px-4 -mt-8 relative z-20 pb-16">
        <!-- Notification / Status Section -->
        <section class="mb-12">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-bold text-gray-900">My Support Discussions</h2>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Active Tickets</p>
          </div>
          <div class="grid grid-cols-1 gap-3">
            <?php
            $t_stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $t_stmt->bind_param("i", $user_id);
            $t_stmt->execute();
            $user_tickets = $t_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if (empty($user_tickets)):
            ?>
              <div class="bg-white border border-dashed border-gray-200 rounded-2xl p-8 text-center shadow-sm">
                <i class="ri-history-line text-3xl text-gray-200 mb-3 block"></i>
                <p class="text-sm text-gray-400 font-medium font-bold uppercase tracking-tighter">No active support cases found.</p>
              </div>
            <?php else: ?>
              <?php foreach ($user_tickets as $ut): ?>
                <a href="view_ticket.php?id=<?php echo $ut['id']; ?>" class="block bg-white rounded-xl border border-gray-100 p-5 hover:shadow-lg hover:border-teal-200 transition-all group">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                      <div class="w-10 h-10 flex items-center justify-center rounded-xl <?php echo $ut['priority'] == 'emergency' ? 'bg-rose-50 text-rose-500' : 'bg-teal-50 text-teal-600'; ?>">
                        <i class="<?php echo $ut['priority'] == 'emergency' ? 'ri-alarm-warning-line' : 'ri-question-line'; ?> text-xl"></i>
                      </div>
                      <div>
                        <h4 class="text-sm font-bold text-gray-900 group-hover:text-teal-700 transition-colors uppercase pr-4"><?php echo htmlspecialchars($ut['subject']); ?></h4>
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

        <section class="mb-12">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-wallet-3-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">Earnings & Payouts</h3>
                  <p class="text-xs text-gray-400">Learn how and when you get paid.</p>
                </div>
              </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-shield-check-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">Partner Safety</h3>
                  <p class="text-xs text-gray-400">Safety protocols for drivers.</p>
                </div>
              </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg hover:border-teal-200 transition-all cursor-pointer group">
              <div class="flex items-start gap-4">
                <div class="w-10 h-10 flex items-center justify-center bg-teal-50 rounded-xl group-hover:bg-teal-100 transition-colors flex-shrink-0">
                  <i class="ri-car-line text-xl text-teal-600"></i>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 mb-1 group-hover:text-teal-700 transition-colors">Vehicle Maintenance</h3>
                  <p class="text-xs text-gray-400">Keep your vehicle in top condition.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Speak to Partner Support</h3>
            <p class="text-sm text-gray-500 mb-6">Describe your issue below and our expert team will assist you shortly.</p>
            <form id="partner-help-form" onsubmit="event.preventDefault(); submitTicket();">
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Issue Topic</label>
                  <select id="ticket-subject" required class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white cursor-pointer">
                    <option value="">Select an area</option>
                    <option value="Payment Issue">Earnings & Payment Error</option>
                    <option value="Trip Dispute">Trip/Job Dispute</option>
                    <option value="Account Access">Account & Security</option>
                    <option value="Technical Bug">App/Web Technical Issue</option>
                    <option value="Other">General Inquiry</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Details</label>
                  <textarea id="ticket-desc" required maxlength="500" rows="4"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent resize-none"
                    placeholder="Provide as much detail as possible..."></textarea>
                </div>
                <button type="submit" class="w-full py-4 bg-teal-600 text-white font-bold text-sm rounded-xl hover:bg-teal-700 transition-all shadow-lg shadow-teal-100">
                  Submit Support Ticket
                </button>
              </div>
            </form>
          </div>
          <div class="space-y-4">
            <div class="bg-emerald-600 rounded-2xl p-6 text-white shadow-xl">
              <div class="flex items-start gap-4">
                <div class="w-12 h-12 flex items-center justify-center bg-white/20 rounded-xl flex-shrink-0">
                  <i class="ri-phone-fill text-2xl"></i>
                </div>
                <div>
                  <h4 class="font-bold text-base mb-1">Emergency Hotline</h4>
                  <p class="text-xs text-emerald-50 mb-3 line-clamp-2">Direct line for critical safety issues or accidents during a trip.</p>
                  <a href="tel:+94112345679" class="text-xl font-black hover:text-emerald-200">+94 11 234 5679</a>
                </div>
              </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
              <h4 class="font-bold text-gray-900 mb-3 text-sm">Common Partner Questions</h4>
              <div class="space-y-3">
                <details class="group">
                  <summary class="list-none flex justify-between items-center cursor-pointer text-xs font-bold text-gray-600 hover:text-teal-600">
                    How long do payouts take?
                    <i class="ri-add-line group-open:rotate-45 transition-transform"></i>
                  </summary>
                  <p class="text-xs text-gray-400 mt-2 leading-relaxed">Weekly payouts are processed every Monday. Funds usually arrive in your bank account within 24-48 hours depending on your bank.</p>
                </details>
                <div class="h-px bg-gray-50"></div>
                <details class="group">
                  <summary class="list-none flex justify-between items-center cursor-pointer text-xs font-bold text-gray-600 hover:text-teal-600">
                    I missed a trip notification
                    <i class="ri-add-line group-open:rotate-45 transition-transform"></i>
                  </summary>
                  <p class="text-xs text-gray-400 mt-2 leading-relaxed">Ensure 'Push Notifications' are enabled in your device settings. Missed jobs will appear in your 'Recent Alerts' for 15 minutes.</p>
                </details>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>

  <script>
    async function submitTicket() {
        const subject = document.getElementById('ticket-subject').value;
        const desc = document.getElementById('ticket-desc').value;

        try {
            const response = await fetch('../api/create_ticket.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subject: subject,
                    description: desc,
                    priority: 'medium'
                })
            });
            const data = await response.json();
            if (data.success) {
                alert('Support Case Created Successfully! Our team will reply soon.');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            console.error(e);
            alert('Failed to connect to support server.');
        }
    }
  </script>
</body>
</html>
