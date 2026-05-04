<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('partner');

$user_id = $_SESSION['user_id'];

// Fetch all conversations (bookings assigned to this partner)
$stmt = $conn->prepare("
    SELECT b.id as booking_id, b.status, b.reference_no, b.pickup_location, b.dropoff_location,
           u.id as customer_id, u.name as customer_name, u.profile_pic as customer_pic,
           (SELECT message FROM messages WHERE booking_id = b.id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE booking_id = b.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
           (SELECT COUNT(*) FROM messages WHERE booking_id = b.id AND receiver_id = ? AND is_read = 0) as unread_count
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.assigned_partner_id = ?
    ORDER BY last_message_time DESC, b.created_at DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_unread = array_sum(array_column($conversations, 'unread_count'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - TripSync Partner</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen flex flex-col">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>

            <!-- Main Content -->
            <main class="pt-20 pb-16 px-4 flex-1 flex flex-col items-center">
                <div class="w-full max-w-6xl">
                    <!-- Header -->
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Messages</h1>
                            <p class="text-sm text-gray-500 mt-1">Communicate with your travelers</p>
                        </div>
                        <?php if ($total_unread > 0): ?>
                        <div class="flex items-center gap-2 px-4 py-2 bg-emerald-50 border border-emerald-200 rounded-xl">
                            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                            <span class="text-sm font-medium text-emerald-700"><?php echo $total_unread; ?> unread messages</span>
                        </div>
                        <?php
endif; ?>
                    </div>

                    <!-- Messenger Container -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-lg overflow-hidden flex"
                        style="height: calc(100vh - 240px); min-height: 520px;">

                        <!-- Sidebar: Conversations List -->
                        <div class="w-full md:w-96 border-r border-gray-200 flex flex-col bg-white">
                            <!-- Search & Filters -->
                            <div class="p-4 border-b border-gray-100">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Conversations</h3>
                                </div>
                                <div class="relative">
                                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" placeholder="Search travelers..."
                                        class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none transition-all">
                                </div>
                            </div>

                            <!-- Conversation List Items -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar">
                                <?php if (empty($conversations)): ?>
                                <div class="p-8 text-center text-gray-400">
                                    <i class="ri-message-line text-4xl mb-2"></i>
                                    <p class="text-sm">No conversations found.</p>
                                </div>
                                <?php
else: ?>
                                    <?php foreach ($conversations as $conv): ?>
                                    <button onclick="loadChat(<?php echo $conv['booking_id']; ?>, <?php echo $conv['customer_id']; ?>, '<?php echo addslashes($conv['customer_name']); ?>', '<?php echo $conv['reference_no']; ?>', '<?php echo getProfilePic($conv['customer_pic'], '../'); ?>')"
                                        class="conversation-btn w-full flex items-start gap-4 px-4 py-4 border-b border-gray-50 hover:bg-gray-50 transition-all text-left group"
                                        id="conv-<?php echo $conv['booking_id']; ?>">
                                        <div class="relative flex-shrink-0">
                                            <div class="w-12 h-12 rounded-full border-2 border-white shadow-sm overflow-hidden bg-gray-100">
                                                <img src="<?php echo getProfilePic($conv['customer_pic'], '../'); ?>" class="w-full h-full object-cover">
                                            </div>
                                            <?php if ($conv['status'] === 'in_progress'): ?>
                                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></span>
                                            <?php
        endif; ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start mb-1">
                                                <h4 class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($conv['customer_name']); ?></h4>
                                                <span class="text-[10px] font-bold text-gray-400">
                                                    <?php echo $conv['last_message_time'] ? date('h:i A', strtotime($conv['last_message_time'])) : ''; ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="px-1.5 py-0.5 text-[9px] font-black uppercase rounded <?php echo $conv['status'] === 'in_progress' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400'; ?> tracking-wider">
                                                    <?php echo $conv['status']; ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($conv['last_message'] ?: 'No messages yet'); ?></p>
                                        </div>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                        <div class="ml-2">
                                            <span class="w-5 h-5 flex items-center justify-center bg-emerald-600 text-white text-[10px] font-black rounded-full shadow-sm"><?php echo $conv['unread_count']; ?></span>
                                        </div>
                                        <?php
        endif; ?>
                                    </button>
                                    <?php
    endforeach; ?>
                                <?php
endif; ?>
                            </div>
                        </div>

                        <!-- Chat Area -->
                        <div id="chat-area" class="flex-1 flex flex-col bg-gray-50/30">
                            <!-- Empty State -->
                            <div id="chat-empty" class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                                <i class="ri-message-3-line text-6xl text-gray-200 mb-4 animate-bounce"></i>
                                <h3 class="text-lg font-bold text-gray-900">Your Inbox</h3>
                                <p class="text-sm text-gray-500 max-w-xs mt-2">Select a conversation from the sidebar to start chatting with your traveler.</p>
                            </div>

                            <!-- Chat Content (Hidden by default) -->
                            <div id="chat-content" class="hidden h-full flex flex-col">
                                <!-- Chat Header -->
                                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-white">
                                    <div class="relative">
                                        <div class="w-10 h-10 rounded-full border border-gray-100 shadow-sm flex items-center justify-center bg-emerald-50 overflow-hidden">
                                            <img src="" class="w-full h-full object-cover hidden" id="chat-user-pic-img">
                                            <i class="ri-user-3-fill text-xl text-emerald-600" id="chat-user-pic-icon"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-sm font-black text-gray-900" id="chat-user-name">Sarah Mitchell</h3>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Booking: <span class="text-emerald-600" id="chat-ref-no">#REF-12345</span></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button class="w-9 h-9 flex items-center justify-center rounded-full bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition-colors">
                                            <i class="ri-phone-line text-lg"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Messages Area -->
                                <div id="messages-container" class="flex-1 overflow-y-auto px-6 py-8 space-y-6 custom-scrollbar bg-white/50">
                                    <!-- Messages dynamic load here -->
                                </div>

                                <!-- Input Area -->
                                <div class="px-6 py-5 bg-white border-t border-gray-100">
                                    <form id="msg-form" class="flex items-center gap-4">
                                        <input type="hidden" id="current-booking-id">
                                        <input type="hidden" id="current-receiver-id">
                                        <div class="flex-1 relative group">
                                            <input type="text" id="msg-input" placeholder="Type your message here..."
                                                class="w-full px-5 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none focus:bg-white transition-all">
                                        </div>
                                        <button type="submit"
                                            class="w-12 h-12 flex items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-200 hover:bg-emerald-700 hover:-translate-y-0.5 transition-all">
                                            <i class="ri-send-plane-2-fill text-xl"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-gradient-to-br from-teal-600 to-teal-700 text-white mt-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-center">
                    <p class="text-teal-200 text-xs">© 2026 TripSync Partner Portal. All rights reserved.</p>
                </div>
            </footer>
        </div>
    </div>

    <script>
        let currentBookingId = null;
        let refreshInterval = null;

        async function loadChat(bookingId, customerId, customerName, refNo, customerPic) {
            currentBookingId = bookingId;
            document.getElementById('current-booking-id').value = bookingId;
            document.getElementById('current-receiver-id').value = customerId;
            document.getElementById('chat-empty').classList.add('hidden');
            document.getElementById('chat-content').classList.remove('hidden');
            document.getElementById('chat-user-name').innerText = customerName;
            document.getElementById('chat-ref-no').innerText = '#' + refNo;

            // Update customer profile pic in header
            const picImg = document.getElementById('chat-user-pic-img');
            const picIcon = document.getElementById('chat-user-pic-icon');
            if (customerPic) {
                picImg.src = customerPic;
                picImg.classList.remove('hidden');
                picIcon.classList.add('hidden');
            } else {
                picImg.classList.add('hidden');
                picIcon.classList.remove('hidden');
            }

            // Highlight active button
            document.querySelectorAll('.conversation-btn').forEach(btn => btn.classList.remove('bg-emerald-50', 'border-l-4', 'border-emerald-600'));
            document.getElementById('conv-' + bookingId).classList.add('bg-emerald-50', 'border-l-4', 'border-emerald-600');

            await fetchMessages();

            // Set up auto-refresh
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(fetchMessages, 5000);
        }

        async function fetchMessages() {
            if (!currentBookingId) return;
            try {
                const res = await fetch(`../api/get_messages.php?booking_id=${currentBookingId}`);
                const data = await res.json();
                if (data.success) {
                    renderMessages(data.messages);
                }
            } catch (err) {
                console.error('Fetch error:', err);
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            const currentUserId = <?php echo $user_id; ?>;
            
            // Remember scroll position
            const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;

            container.innerHTML = messages.map(m => `
                <div class="flex ${m.sender_id == currentUserId ? 'justify-end' : 'justify-start'}">
                    ${m.sender_id != currentUserId ? '<div class="w-8 h-8 rounded-full bg-gray-200 flex-shrink-0 mt-1 flex items-center justify-center text-gray-400 border-2 border-white shadow-sm mr-3"><i class="ri-user-fill text-sm"></i></div>' : ''}
                    <div class="max-w-[70%] group">
                        <div class="${m.sender_id == currentUserId ? 'bg-emerald-600 text-white rounded-tr-md' : 'bg-white border border-gray-100 text-gray-800 rounded-tl-md'} px-5 py-3 rounded-2xl shadow-sm">
                            <p class="text-sm leading-relaxed font-medium">${escapeHtml(m.message)}</p>
                        </div>
                        <div class="flex items-center ${m.sender_id == currentUserId ? 'justify-end' : 'justify-start'} gap-1.5 mt-1.5">
                            <span class="text-[9px] font-bold text-gray-300 uppercase">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                            ${m.sender_id == currentUserId ? (m.is_read ? '<i class="ri-check-double-line text-emerald-500 text-sm"></i>' : '<i class="ri-check-line text-gray-300 text-sm"></i>') : ''}
                        </div>
                    </div>
                </div>
            `).join('');

            if (isAtBottom) {
                container.scrollTop = container.scrollHeight;
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.getElementById('msg-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const bookingId = document.getElementById('current-booking-id').value;
            const receiverId = document.getElementById('current-receiver-id').value;
            const message = document.getElementById('msg-input').value.trim();

            if (!message) return;

            try {
                const res = await fetch('../api/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ booking_id: bookingId, receiver_id: receiverId, message: message })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('msg-input').value = '';
                    fetchMessages();
                }
            } catch (err) {
                alert('Failed to send message');
            }
        });
    </script>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #10b981; }
    </style>
</body>
</html>
