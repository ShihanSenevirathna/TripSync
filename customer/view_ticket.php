<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('customer');

$user_id = $_SESSION['user_id'];
$tid = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$tid) {
    header("Location: help.php");
    exit();
}

// Fetch Ticket
$stmt = $conn->prepare("SELECT t.*, u.name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.user_id = ?");
$stmt->bind_param("ii", $tid, $user_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header("Location: help.php");
    exit();
}

// Handle Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO support_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $tid, $user_id, $msg);
        $stmt->execute();
        
        // Update ticket status to open if it was in_progress (showing user replied)
        // Or just keep it as is. Usually user reply means it's 'open' again for admin.
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
    <title>Support Ticket #<?php echo $tid; ?> - TripSync</title>
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
                <a href="help.php" class="p-2.5 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                    <i class="ri-arrow-left-line"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Ticket #ST-<?php echo str_pad($tid, 4, '0', STR_PAD_LEFT); ?></span>
                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php 
                            echo $ticket['status'] == 'open' ? 'bg-emerald-100 text-emerald-700' : ($ticket['status'] == 'in_progress' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700');
                        ?>"><?php echo ucfirst($ticket['status']); ?></span>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Original Ticket -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center font-bold">
                            <?php echo strtoupper(substr($ticket['user_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($ticket['user_name']); ?> (You)</h4>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest"><?php echo date('M j, Y h:i A', strtotime($ticket['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-50/50 rounded-xl p-4 border border-gray-100 text-sm text-gray-700 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                    </div>
                </div>

                <!-- Discussion Thread -->
                <?php foreach ($replies as $reply): ?>
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm <?php echo $reply['sender_role'] == 'admin' ? 'border-l-4 border-l-teal-500' : ''; ?>">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full overflow-hidden border border-gray-100">
                                    <img src="<?php echo getProfilePic($reply['profile_pic'], '../'); ?>" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($reply['sender_name']); ?></h4>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                        <?php echo $reply['sender_role'] == 'admin' ? '<span class="text-teal-600">Administrator</span>' : 'You'; ?> • 
                                        <?php echo date('M j, Y h:i A', strtotime($reply['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="text-sm text-gray-700 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Reply Form -->
                <?php if ($ticket['status'] !== 'closed'): ?>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm mt-8">
                        <h3 class="text-base font-bold text-gray-900 mb-4">Send a Reply</h3>
                        <form method="POST" class="space-y-4">
                            <textarea name="message" rows="4" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all resize-none" placeholder="Type your message here..."></textarea>
                            <button type="submit" class="px-8 py-3 bg-teal-600 text-white font-bold text-sm rounded-xl hover:bg-teal-700 transition-all shadow-lg shadow-teal-100 flex items-center gap-2">
                                <i class="ri-send-plane-2-line"></i> Post Reply
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-gray-100 rounded-2xl p-6 text-center text-gray-500 text-sm font-medium">
                        This ticket has been closed. If you still need help, please open a new ticket.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="bg-teal-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-teal-100 text-sm">© 2026 TripSync Safety & Support. Available 24/7 for SOS cases.</p>
        </div>
    </footer>
</body>
</html>
