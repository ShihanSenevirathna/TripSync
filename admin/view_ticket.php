<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('admin');

$tid = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$tid) {
    header("Location: support.php");
    exit();
}

// Fetch Ticket
$stmt = $conn->prepare("SELECT t.*, u.name as user_name, u.email as user_email, u.role as user_role FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->bind_param("i", $tid);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header("Location: support.php");
    exit();
}

// Handle Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = $_POST['message'];
    $admin_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO support_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $tid, $admin_id, $msg);
    $stmt->execute();
    
    // Update Ticket Status to in_progress if open
    if ($ticket['status'] == 'open') {
        $conn->query("UPDATE support_tickets SET status = 'in_progress' WHERE id = $tid");
        $ticket['status'] = 'in_progress';
    }

    // Notify User
    $n_title = "Support Update: #ST-" . str_pad($tid, 4, '0', STR_PAD_LEFT);
    $n_msg = "An administrator has replied to your support ticket.";
    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'support_update')");
    $n_stmt->bind_param("iss", $ticket['user_id'], $n_title, $n_msg);
    $n_stmt->execute();

    $success_msg = "Reply sent successfully.";
}

// Fetch Replies
$replies_stmt = $conn->prepare("SELECT r.*, u.name as sender_name, u.role as sender_role FROM support_replies r JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
$replies_stmt->bind_param("i", $tid);
$replies_stmt->execute();
$replies = $replies_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Details - TripSync Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-50/50">
    <?php include 'includes/navbar.php'; ?>

    <main class="pt-24 pb-16 px-4">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-4 mb-6">
                <a href="support.php" class="p-2 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                    <i class="ri-arrow-left-line"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest">Case #ST-<?php echo str_pad($tid, 4, '0', STR_PAD_LEFT); ?> • <?php echo ucfirst($ticket['status']); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Discussion Area -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Original Message -->
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                        <div class="flex items-start gap-4 mb-4">
                            <div class="w-10 h-10 bg-teal-50 text-teal-600 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="ri-user-line"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($ticket['user_name']); ?></h4>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest"><?php echo $ticket['user_role'] === 'partner' ? 'Partner' : 'Traveler'; ?> • <?php echo date('M j, Y h:i A', strtotime($ticket['created_at'])); ?></p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 p-4 rounded-xl border border-gray-100"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                    </div>

                    <!-- Replies Thread -->
                    <?php foreach ($replies as $reply): ?>
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm <?php echo $reply['sender_role'] === 'admin' ? 'border-l-4 border-l-teal-500' : ''; ?>">
                        <div class="flex items-start gap-4 mb-4">
                            <div class="w-10 h-10 <?php echo $reply['sender_role'] === 'admin' ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-400'; ?> rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="ri-<?php echo $reply['sender_role'] === 'admin' ? 'star-fill' : 'user-line'; ?>"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($reply['sender_name']); ?> <?php echo $reply['sender_role'] === 'admin' ? '<span class="text-[9px] bg-teal-100 text-teal-600 px-1.5 py-0.5 rounded font-black uppercase tracking-tighter ml-1">Staff</span>' : ''; ?></h4>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest"><?php echo date('M j, Y h:i A', strtotime($reply['created_at'])); ?></p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                    </div>
                    <?php endforeach; ?>

                    <!-- Reply Editor -->
                    <?php if ($ticket['status'] !== 'resolved' && $ticket['status'] !== 'closed'): ?>
                    <div class="bg-white rounded-2xl border border-teal-100 p-6 shadow-md shadow-teal-50 overflow-hidden relative">
                        <div class="absolute top-0 right-0 p-3 opacity-5">
                            <i class="ri-chat-1-fill text-6xl text-teal-950"></i>
                        </div>
                        <h3 class="text-xs font-black uppercase tracking-widest text-teal-600 mb-4">Post Official Response</h3>
                        <form method="POST" class="space-y-4 relative z-10">
                            <textarea name="message" required rows="4" class="w-full px-4 py-3 bg-teal-50/30 border border-teal-100 rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:outline-none transition-all placeholder:text-teal-300" placeholder="Type your response here..."></textarea>
                            <div class="flex items-center justify-between">
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest"><i class="ri-information-line"></i> User will be notified via email/app</p>
                                <button type="submit" class="px-6 py-2.5 bg-teal-600 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-teal-700 transition-all flex items-center gap-2 shadow-lg shadow-teal-100">
                                    <i class="ri-send-plane-2-fill"></i> Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                    <form action="support.php" method="GET" class="mt-4">
                        <input type="hidden" name="action" value="resolve">
                        <input type="hidden" name="id" value="<?php echo $tid; ?>">
                        <button type="submit" class="w-full py-3 border-2 border-dashed border-gray-200 text-gray-400 text-xs font-black uppercase tracking-widest rounded-xl hover:border-emerald-500 hover:text-emerald-600 transition-all">
                            Mark as Resolved
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="p-8 text-center bg-gray-50 border-2 border-dashed border-gray-200 rounded-3xl">
                        <i class="ri-lock-2-line text-4xl text-gray-300 mb-3 block"></i>
                        <h4 class="text-sm font-bold text-gray-500">This conversation is closed</h4>
                        <p class="text-xs text-gray-400 mt-1 uppercase font-black tracking-widest leading-relaxed">The ticket was marked as resolved on <?php echo date('M j, Y', strtotime($ticket['updated_at'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Metadata -->
                <div class="space-y-6">
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 mb-4">Ticket Details</h3>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Priority Level</p>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase border <?php echo $ticket['priority'] === 'emergency' ? 'bg-rose-500 text-white' : 'border-rose-100 text-rose-600 bg-rose-50'; ?>">
                                    <?php echo $ticket['priority']; ?>
                                </span>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Created At</p>
                                <p class="text-xs font-bold text-gray-800"><?php echo date('F j, Y - g:i A', strtotime($ticket['created_at'])); ?></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">User Info</p>
                                <p class="text-xs font-bold text-gray-800"><?php echo htmlspecialchars($ticket['user_name']); ?></p>
                                <p class="text-xs text-gray-400 font-medium"><?php echo htmlspecialchars($ticket['user_email']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 rounded-2xl p-6 shadow-xl shadow-slate-900/10 text-white">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <button class="w-full py-2.5 bg-white/5 border border-white/10 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-white/10 transition-all">Ban User Account</button>
                            <button class="w-full py-2.5 bg-white/5 border border-white/10 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-white/10 transition-all">Transfer Ticket</button>
                            <button class="w-full py-2.5 bg-rose-600/20 border border-rose-500/30 text-rose-300 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-rose-600 hover:text-white transition-all">Escalate to Legal</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
