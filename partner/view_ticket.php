<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('partner');

$user_id = $_SESSION['user_id'];
$tid = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$tid) {
    header("Location: dashboard.php");
    exit();
}

// Fetch Ticket
$stmt = $conn->prepare("SELECT t.*, u.name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.user_id = ?");
$stmt->bind_param("ii", $tid, $user_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header("Location: dashboard.php");
    exit();
}

// Handle Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO support_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $tid, $user_id, $msg);
        $stmt->execute();
        
        $conn->query("UPDATE support_tickets SET status = 'open' WHERE id = $tid");
        $success_msg = "Reply sent successfully.";
    }
}

// Fetch Replies
$replies_stmt = $conn->prepare("SELECT r.*, u.name as sender_name, u.role as sender_role, u.profile_pic FROM support_replies r JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
$replies_stmt->bind_param("i", $tid);
$replies_stmt->execute();
$replies = $replies_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOS Response #<?php echo $tid; ?> - Partner Portal</title>
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50/50">
    <?php include 'includes/navbar.php'; ?>

    <main class="pt-32 pb-16 px-4">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-4 mb-8">
                <a href="dashboard.php" class="p-2.5 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all shadow-sm">
                    <i class="ri-arrow-left-line"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Case ID: #ST-<?php echo str_pad($tid, 4, '0', STR_PAD_LEFT); ?></span>
                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php 
                            echo $ticket['status'] == 'open' ? 'bg-emerald-100 text-emerald-700' : ($ticket['status'] == 'in_progress' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700');
                        ?>"><?php echo ucfirst($ticket['status']); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($ticket['priority'] == 'emergency'): ?>
                <div class="bg-rose-50 border border-rose-100 rounded-2xl p-5 mb-8 flex items-center gap-4">
                    <div class="w-12 h-12 flex items-center justify-center bg-rose-500 text-white rounded-xl shadow-lg ring-4 ring-rose-100">
                        <i class="ri-alarm-warning-fill text-2xl animate-pulse"></i>
                    </div>
                    <div>
                        <h4 class="text-rose-900 font-bold text-sm">Emergency SOS Case</h4>
                        <p class="text-rose-600 text-xs mt-0.5">Our 24/7 priority safety team is dedicated to your case. Stay connected.</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="space-y-6">
                <!-- Original SOS Report -->
                <div class="bg-white rounded-[2rem] border border-gray-100 p-8 shadow-sm">
                    <div class="flex items-start gap-4 mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold text-xl">
                            <?php echo strtoupper(substr($ticket['user_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-gray-900"><?php echo htmlspecialchars($ticket['user_name']); ?> (You)</h4>
                            <p class="text-[11px] text-gray-400 font-bold uppercase tracking-widest mt-0.5"><?php echo date('M j, Y h:i A', strtotime($ticket['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-50/50 rounded-2xl p-6 border border-gray-100 text-sm text-gray-700 leading-relaxed italic">
                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                    </div>
                </div>

                <!-- Discussion Thread -->
                <?php foreach ($replies as $reply): ?>
                    <div class="bg-white rounded-[2rem] border border-gray-100 p-8 shadow-sm <?php echo $reply['sender_role'] == 'admin' ? 'border-l-4 border-l-teal-500' : ''; ?>">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-2xl overflow-hidden border border-gray-100 shadow-sm">
                                <img src="<?php echo getProfilePic($reply['profile_pic'], '../'); ?>" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-gray-900"><?php echo htmlspecialchars($reply['sender_name']); ?></h4>
                                <p class="text-[11px] text-gray-400 font-bold uppercase tracking-widest mt-0.5">
                                    <?php echo $reply['sender_role'] == 'admin' ? '<span class="text-teal-600">Admin Support</span>' : 'You'; ?> • 
                                    <?php echo date('M j, Y h:i A', strtotime($reply['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-sm text-gray-700 leading-relaxed pl-1">
                            <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Reply Form -->
                <?php if ($ticket['status'] !== 'closed'): ?>
                    <div class="bg-white rounded-[2rem] border border-gray-200 p-8 shadow-xl shadow-gray-100 mt-12 overflow-hidden relative">
                        <div class="absolute top-0 right-0 p-8 opacity-5">
                            <i class="ri-chat-voice-line text-6xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Send a Reply</h3>
                        <form method="POST" class="space-y-5">
                            <textarea name="message" rows="5" required class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl text-sm font-medium focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 outline-none transition-all resize-none shadow-inner" placeholder="Provide more details, location updates, or ask a question..."></textarea>
                            <button type="submit" class="w-full sm:w-auto px-10 py-4 bg-teal-600 text-white font-bold text-sm rounded-2xl hover:bg-teal-700 transition-all shadow-xl shadow-teal-100 flex items-center justify-center gap-2">
                                <i class="ri-send-plane-2-fill text-lg"></i> Send Feedback to Support
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-gray-100 rounded-[2rem] p-10 text-center text-gray-500 text-sm font-bold uppercase tracking-widest">
                        Support Interaction Locked (Closed)
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="bg-teal-900 text-white py-16 mt-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-teal-400 text-[10px] font-black uppercase tracking-widest mb-2">TripSync Safety Protocol</p>
            <p class="text-teal-100 text-sm font-medium">All support interactions are logged for safety and quality assurance. Emergency hotlines: 119 / 1919</p>
        </div>
    </footer>
</body>
</html>
