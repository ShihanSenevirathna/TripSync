<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('admin');

// Handle Actions via POST (AJAX preferred, but fallback to GET for now or implement below)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $reason = isset($_POST['reason']) ? $conn->real_escape_string($_POST['reason']) : '';

    if ($action == 'approve') {
        $conn->query("UPDATE users SET status = 'active', rejection_reason = NULL WHERE id = $id");
        
        // Notify Partner
        $title = "Congratulations! You're Approved";
        $message = "Your partner account has been verified. You can now start accepting trip requests.";
        $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($id, '$title', '$message', 'success')");
        
        $msg = "Partner approved successfully.";
    }
    elseif ($action == 'reject') {
        $conn->query("UPDATE users SET status = 'rejected', rejection_reason = '$reason' WHERE id = $id");
        
        // Notify Partner
        $title = "Application Update";
        $message = "Your partner application was not approved. Reason: $reason. Please update your documents and try again.";
        $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($id, '$title', '$message', 'warning')");
        
        $msg = "Partner application rejected.";
    }
}

// Fetch Pending
$pending = $conn->query("SELECT u.*, v.type as v_type, v.model as v_model, v.reg_number as v_reg 
    FROM users u 
    LEFT JOIN vehicles v ON u.id = v.owner_id 
    WHERE u.role = 'partner' AND u.status = 'pending' 
    ORDER BY u.created_at DESC");

// Fetch Approved
$approved = $conn->query("SELECT u.*, v.type as v_type, v.model as v_model, v.reg_number as v_reg 
    FROM users u 
    LEFT JOIN vehicles v ON u.id = v.owner_id 
    WHERE u.role = 'partner' AND u.status = 'active' 
    ORDER BY u.created_at DESC");

// Fetch Rejected
$rejected = $conn->query("SELECT u.*, v.type as v_type, v.model as v_model, v.reg_number as v_reg 
    FROM users u 
    LEFT JOIN vehicles v ON u.id = v.owner_id 
    WHERE u.role = 'partner' AND u.status = 'rejected' 
    ORDER BY u.created_at DESC");

// Counts for UI
$pending_count = $pending->num_rows;

function getExpiryStatus($date) {
    if (!$date) return ['label' => 'No date set', 'class' => 'text-gray-400'];
    $expiry = new DateTime($date);
    $now = new DateTime('today');
    $diff = $now->diff($expiry);
    $days = $diff->days * ($diff->invert ? -1 : 1);

    if ($days < 0) return ['label' => 'EXPIRED', 'class' => 'text-rose-600 font-black animate-pulse'];
    if ($days < 30) return ['label' => "EXPIRES IN $days DAYS", 'class' => 'text-amber-600 font-black'];
    return ['label' => 'VALID (Expires ' . $expiry->format('M j, Y') . ')', 'class' => 'text-emerald-600 font-bold'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripSync - Verification Portal</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="assets/files/index-BjODfbg0.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }

        /* Color Utility Fixes */
        .bg-teal-50 { background-color: #f0fdfa !important; }
        .bg-emerald-50 { background-color: #ecfdf5 !important; }
        .bg-emerald-100 { background-color: #d1fae5 !important; }
        .bg-amber-100 { background-color: #fef3c7 !important; }
        .bg-amber-50 { background-color: #fffbeb !important; }
        .bg-rose-100 { background-color: #ffe4e6 !important; }
        .bg-rose-50 { background-color: #fff1f2 !important; }
        .bg-rose-500\/20 { background-color: rgba(244, 63, 94, 0.2) !important; }
        .bg-emerald-500\/20 { background-color: rgba(16, 185, 129, 0.2) !important; }
        .bg-white\/10 { background-color: rgba(255, 255, 255, 0.1) !important; }
        
        .text-teal-700 { color: #0f766e !important; }
        .text-emerald-700 { color: #047857 !important; }
        .text-amber-700 { color: #b45309 !important; }
        .text-rose-700 { color: #be123c !important; }
        .text-rose-400 { color: #fb7185 !important; }
        .text-emerald-400 { color: #34d399 !important; }
    </style>
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen bg-gray-50/50">
            <!-- Navigation -->
            <?php include 'includes/navbar.php'; ?>

            <div class="pt-20 pb-16 px-4">
                <div class="max-w-7xl mx-auto">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Verification Portal</h1>
                            <p class="text-sm text-gray-500 mt-1">Review and approve partner applications</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="px-3 py-1 bg-amber-100 text-amber-700 text-sm font-semibold rounded-full whitespace-nowrap"><?php echo $pending_count; ?> Pending</span>
                        </div>
                    </div>

                    <?php if (isset($msg)): ?>
                        <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-100 flex items-center gap-2">
                            <i class="ri-checkbox-circle-line"></i> <?php echo $msg; ?>
                        </div>
                    <?php
endif; ?>

                    <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-6">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <div class="flex items-center gap-2 bg-gray-100 rounded-full p-1">
                                <button onclick="showTab('all')" id="btn-all"
                                    class="tab-btn px-4 py-2 text-sm font-medium rounded-full transition-colors cursor-pointer whitespace-nowrap text-gray-500 hover:text-gray-700">All</button>
                                <button onclick="showTab('pending')" id="btn-pending"
                                    class="tab-btn px-4 py-2 text-sm font-medium rounded-full transition-colors cursor-pointer whitespace-nowrap bg-white text-gray-900 shadow-sm">Pending</button>
                                <button onclick="showTab('approved')" id="btn-approved"
                                    class="tab-btn px-4 py-2 text-sm font-medium rounded-full transition-colors cursor-pointer whitespace-nowrap text-gray-500 hover:text-gray-700">Approved</button>
                                <button onclick="showTab('rejected')" id="btn-rejected"
                                    class="tab-btn px-4 py-2 text-sm font-medium rounded-full transition-colors cursor-pointer whitespace-nowrap text-gray-500 hover:text-gray-700">Rejected</button>
                            </div>
                            <div class="relative w-full md:w-72">
                                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input placeholder="Search by name..."
                                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                                    type="text" id="searchInput" onkeyup="filterNames()">
                            </div>
                        </div>
                    </div>

                    <!-- Pending Tab -->
                    <div id="tab-pending" class="verification-tab space-y-4">
                        <?php if ($pending->num_rows == 0): ?>
                            <div class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
                                <div class="w-16 h-16 flex items-center justify-center bg-gray-100 rounded-full mx-auto mb-4">
                                    <i class="ri-check-double-fill text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 mb-1">No Pending Applications</h3>
                                <p class="text-sm text-gray-500">Wait for new partners to register.</p>
                            </div>
                        <?php
else: ?>
                            <?php while ($p = $pending->fetch_assoc()): ?>
                                <div class="bg-white rounded-2xl border transition-all border-amber-200">
                                    <div class="p-5">
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center gap-4">
                                                <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-gray-100 flex-shrink-0">
                                                    <img src="../assets/images/<?php echo $p['profile_pic'] ?: 'default-avatar.jpg'; ?>" class="w-full h-full object-cover">
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <h3 class="name-text text-base font-bold text-gray-900"><?php echo $p['name']; ?></h3>
                                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize bg-amber-100 text-amber-700">pending</span>
                                                    </div>
                                                    <p class="text-sm text-gray-500 mt-0.5">PTR-<?php echo $p['id']; ?> · <?php echo $p['v_type']; ?> · <?php echo $p['v_model']; ?></p>
                                                    <p class="text-xs text-gray-400 mt-0.5">Submitted <?php echo date('M j, Y h:i A', strtotime($p['created_at'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-4 space-y-5">
                                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                                                <div>
                                                    <p class="text-[10px] uppercase font-black text-gray-400 mb-1">Phone</p>
                                                    <p class="text-sm font-bold text-gray-900"><?php echo $p['phone']; ?></p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] uppercase font-black text-gray-400 mb-1">Email</p>
                                                    <p class="text-sm font-bold text-gray-900 truncate" title="<?php echo $p['email']; ?>"><?php echo $p['email']; ?></p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] uppercase font-black text-gray-400 mb-1">Vehicle</p>
                                                    <p class="text-xs font-bold text-gray-900"><?php echo $p['v_model']; ?> (<?php echo $p['v_reg']; ?>)</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] uppercase font-black text-gray-400 mb-1">License Expiry</p>
                                                    <?php $ls = getExpiryStatus($p['license_expiry']); ?>
                                                    <p class="text-[10px] <?php echo $ls['class']; ?>"><?php echo $ls['label']; ?></p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] uppercase font-black text-gray-400 mb-1">Vehicle RC Expiry</p>
                                                    <?php $rs = getExpiryStatus($p['registration_expiry']); ?>
                                                    <p class="text-[10px] <?php echo $rs['class']; ?>"><?php echo $rs['label']; ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3 pt-2">
                                                <button onclick="openDocModal('../assets/images/partners/<?php echo $p['license_doc']; ?>', 'License - <?php echo addslashes($p['name']); ?>')"
                                                    class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-200 transition-colors">
                                                    <i class="ri-file-user-line mr-1"></i>View License
                                                </button>
                                                <button onclick="openDocModal('../assets/images/partners/<?php echo $p['registration_doc']; ?>', 'Vehicle RC - <?php echo addslashes($p['v_model']); ?>')"
                                                    class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-200 transition-colors">
                                                    <i class="ri-file-list-3-line mr-1"></i>View Vehicle RC
                                                </button>
                                                <div class="flex-1"></div>
                                                <button onclick="openRejectModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>')"
                                                    class="px-5 py-2.5 border border-rose-200 text-rose-600 text-sm font-medium rounded-xl hover:bg-rose-50 transition-colors cursor-pointer whitespace-nowrap"><i
                                                        class="ri-close-line mr-1"></i>Reject</button>
                                                <form method="POST" style="display:inline">
                                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit"
                                                        class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition-colors cursor-pointer whitespace-nowrap"><i
                                                            class="ri-check-line mr-1"></i>Approve</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
    endwhile; ?>
                        <?php
endif; ?>
                    </div>

                    <!-- Approved Tab -->
                    <div id="tab-approved" class="verification-tab hidden space-y-4">
                        <?php if ($approved->num_rows == 0): ?>
                            <div class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
                                <div class="w-16 h-16 flex items-center justify-center bg-gray-100 rounded-full mx-auto mb-4">
                                    <i class="ri-history-line text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 mb-1">No Approved Partners</h3>
                                <p class="text-sm text-gray-500">Applications you approve will appear here.</p>
                            </div>
                        <?php
else: ?>
                            <?php while ($p = $approved->fetch_assoc()): ?>
                                <div class="bg-white rounded-2xl border transition-all border-emerald-200 p-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-14 h-14 rounded-full bg-teal-50 flex items-center justify-center flex-shrink-0">
                                            <img src="../assets/images/<?php echo $p['profile_pic'] ?: 'default-avatar.jpg'; ?>" class="w-full h-full rounded-full object-cover">
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="name-text text-base font-bold text-gray-900"><?php echo $p['name']; ?></h3>
                                                <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize bg-emerald-100 text-emerald-700">approved</span>
                                            </div>
                                            <p class="text-sm text-gray-500 mt-0.5">PTR-<?php echo $p['id']; ?> · <?php echo $p['v_type']; ?> · <?php echo $p['v_model']; ?></p>
                                            
                                            <div class="mt-4 flex flex-wrap items-center gap-6">
                                                <div>
                                                    <p class="text-[9px] uppercase font-black text-gray-400 mb-1">License Expiry</p>
                                                    <?php $ls = getExpiryStatus($p['license_expiry']); ?>
                                                    <p class="text-[10px] <?php echo $ls['class']; ?>"><?php echo $ls['label']; ?></p>
                                                </div>
                                                <div>
                                                    <p class="text-[9px] uppercase font-black text-gray-400 mb-1">Vehicle RC Expiry</p>
                                                    <?php $rs = getExpiryStatus($p['registration_expiry']); ?>
                                                    <p class="text-[10px] <?php echo $rs['class']; ?>"><?php echo $rs['label']; ?></p>
                                                </div>
                                                <div class="flex gap-2 ml-auto">
                                                    <button onclick="openDocModal('../assets/images/partners/<?php echo $p['license_doc']; ?>', 'License - <?php echo addslashes($p['name']); ?>')"
                                                        class="px-3 py-1.5 bg-gray-50 text-gray-600 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-100 transition-colors border border-gray-100">
                                                        <i class="ri-file-user-line mr-1"></i>License
                                                    </button>
                                                    <button onclick="openDocModal('../assets/images/partners/<?php echo $p['registration_doc']; ?>', 'Vehicle RC - <?php echo addslashes($p['v_model']); ?>')"
                                                        class="px-3 py-1.5 bg-gray-50 text-gray-600 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-100 transition-colors border border-gray-100">
                                                        <i class="ri-file-list-3-line mr-1"></i>Vehicle RC
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
    endwhile; ?>
                        <?php
endif; ?>
                    </div>

                    <!-- Rejected Tab -->
                    <div id="tab-rejected" class="verification-tab hidden space-y-4">
                        <?php if ($rejected->num_rows == 0): ?>
                             <div class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
                                <div class="w-16 h-16 flex items-center justify-center bg-gray-100 rounded-full mx-auto mb-4">
                                    <i class="ri-close-circle-line text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 mb-1">No Rejected Applications</h3>
                            </div>
                        <?php
else: ?>
                            <?php while ($p = $rejected->fetch_assoc()): ?>
                                <div class="bg-white rounded-2xl border transition-all border-rose-200 p-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-14 h-14 rounded-full bg-rose-50 flex items-center justify-center flex-shrink-0">
                                            <img src="../assets/images/<?php echo $p['profile_pic'] ?: 'default-avatar.jpg'; ?>" class="w-full h-full rounded-full object-cover">
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="name-text text-base font-bold text-gray-900"><?php echo $p['name']; ?></h3>
                                                <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize bg-rose-100 text-rose-700">rejected</span>
                                            </div>
                                            <p class="text-sm text-gray-500 mt-0.5">PTR-<?php echo $p['id']; ?> · <?php echo $p['v_type']; ?> · <?php echo $p['v_model']; ?></p>
                                            
                                            <div class="mt-4 flex flex-wrap items-center gap-6">
                                                <div class="flex gap-2 ml-auto">
                                                    <button onclick="openDocModal('../assets/images/partners/<?php echo $p['license_doc']; ?>', 'License - <?php echo addslashes($p['name']); ?>')"
                                                        class="px-3 py-1.5 bg-gray-50 text-gray-600 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-100 transition-colors border border-gray-100">
                                                        <i class="ri-file-user-line mr-1"></i>License
                                                    </button>
                                                    <button onclick="openDocModal('../assets/images/partners/<?php echo $p['registration_doc']; ?>', 'Vehicle RC - <?php echo addslashes($p['v_model']); ?>')"
                                                        class="px-3 py-1.5 bg-gray-50 text-gray-600 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-100 transition-colors border border-gray-100">
                                                        <i class="ri-file-list-3-line mr-1"></i>Vehicle RC
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
    endwhile; ?>
                        <?php
endif; ?>
                    </div>
                    <!-- All Tab -->
                    <div id="tab-all" class="verification-tab hidden space-y-4">
                        <div class="text-[10px] text-gray-400 font-black uppercase tracking-[0.2em] mb-4 flex items-center gap-3">
                            <span class="w-12 h-[1px] bg-gray-200"></span>
                            Partner Directory
                            <span class="w-12 h-[1px] bg-gray-200"></span>
                        </div>
                        <?php
                        // Simplified combined view
                        $all = $conn->query("SELECT u.*, v.type as v_type, v.model as v_model, v.reg_number as v_reg 
                            FROM users u 
                            LEFT JOIN vehicles v ON u.id = v.owner_id 
                            WHERE u.role = 'partner' 
                            ORDER BY u.created_at DESC");
                        while ($p = $all->fetch_assoc()): ?>
                                                    <div class="bg-white rounded-2xl border border-gray-100 p-5">
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center gap-4">
                                                                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden">
                                                                    <img src="../assets/images/<?php echo $p['profile_pic'] ?: 'default-avatar.jpg'; ?>" class="w-full h-full object-cover">
                                                                </div>
                                                                <div>
                                                                    <div class="flex items-center gap-2">
                                                                        <p class="name-text text-sm font-bold text-gray-900"><?php echo htmlspecialchars($p['name']); ?></p>
                                                                        <span class="px-2 py-0.5 text-[8px] font-black rounded-full <?php
                                                                            echo $p['status'] == 'active' ? 'bg-emerald-100 text-emerald-700' :
                                                                                ($p['status'] == 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
                                                                        ?> uppercase tracking-widest"><?php echo $p['status']; ?></span>
                                                                    </div>
                                                                    <p class="text-xs text-gray-500 mt-0.5">Joined <?php echo date('M Y', strtotime($p['created_at'])); ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <button onclick="openDocModal('../assets/images/partners/<?php echo $p['license_doc']; ?>', 'License - <?php echo addslashes($p['name']); ?>')"
                                                                    class="p-2 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors tooltip" title="View License">
                                                                    <i class="ri-file-user-line"></i>
                                                                </button>
                                                                <button onclick="openDocModal('../assets/images/partners/<?php echo $p['registration_doc']; ?>', 'Vehicle RC - <?php echo addslashes($p['name']); ?>')"
                                                                    class="p-2 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors tooltip" title="View RC">
                                                                    <i class="ri-file-list-3-line"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                        endwhile; ?>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="bg-slate-900 text-slate-400 border-t border-slate-800">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-2">
                            <img alt="TripSync Logo" class="h-8 w-auto opacity-50" src="../assets/images/logo.png">
                            <p class="text-sm">© 2026 TripSync Admin. All rights reserved.</p>
                        </div>
                        <div class="flex gap-6 text-sm">
                            <a href="#" class="hover:text-white transition-colors">Privacy Policy</a>
                            <a href="#" class="hover:text-white transition-colors">Terms of Service</a>
                            <a href="support.php" class="hover:text-white transition-colors">Support</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/files/index-Dammfq5V.js.download"></script>
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.verification-tab').forEach(tab => {
                tab.classList.add('hidden');
            });
            document.getElementById('tab-' + tabId).classList.remove('hidden');

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
                btn.classList.add('text-gray-500', 'hover:text-gray-700');
            });
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
            activeBtn.classList.remove('text-gray-500', 'hover:text-gray-700');
        }

        function filterNames() {
            let input = document.getElementById('searchInput');
            let filter = input.value.toLowerCase();
            let cards = document.querySelectorAll('.verification-tab > div:not(.bg-gray-100)'); // Simple filter logic

            cards.forEach(card => {
                let name = card.querySelector('.name-text');
                if (name) {
                    let txtValue = name.textContent || name.innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        card.style.display = "";
                    } else {
                        card.style.display = "none";
                    }
                }
            });
        }
    </script>

    <!-- Document Modal -->
    <div id="docModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm animate-in fade-in duration-300">
        <div class="bg-white rounded-3xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
                <h3 class="text-xl font-bold text-gray-900" id="docModalTitle">Document Preview</h3>
                <button onclick="closeDocModal()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-all">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <div class="flex-1 overflow-auto p-4 bg-gray-50 flex items-center justify-center">
                <img id="docPreviewImg" src="" class="max-w-full h-auto shadow-lg rounded-lg border border-gray-200 hidden">
                <div id="docPreviewIframe" class="w-full h-[70vh] rounded-lg border border-gray-200 hidden"></div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 bg-white flex justify-end">
                <a id="docDownloadBtn" href="" download class="px-6 py-2.5 bg-teal-600 text-white font-bold rounded-xl hover:bg-teal-700 transition-all flex items-center gap-2">
                    <i class="ri-download-2-line"></i> Download Document
                </a>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm animate-in fade-in duration-300">
        <div class="bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-900">Reject Application</h3>
                <button onclick="closeRejectModal()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-all">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="id" id="rejectUserId">
                <input type="hidden" name="action" value="reject">
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Rejection Reason</label>
                    <textarea name="reason" required rows="4" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 focus:outline-none text-sm" placeholder="Explain why the application is being rejected..."></textarea>
                    <p class="text-[10px] text-gray-400 mt-2">The partner will be able to see this reason upon login.</p>
                </div>
                <div class="flex gap-3 mt-8">
                    <button type="button" onclick="closeRejectModal()" class="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-xl hover:bg-gray-200 transition-all">Cancel</button>
                    <button type="submit" class="flex-1 py-3 bg-rose-600 text-white font-bold rounded-xl hover:bg-rose-700 shadow-lg shadow-rose-100 transition-all">Reject Partner</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openDocModal(src, title) {
            const modal = document.getElementById('docModal');
            const img = document.getElementById('docPreviewImg');
            const iframe = document.getElementById('docPreviewIframe');
            const downloadBtn = document.getElementById('docDownloadBtn');
            const modalTitle = document.getElementById('docModalTitle');

            modalTitle.textContent = title;
            downloadBtn.href = src;
            
            const ext = src.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                img.src = src;
                img.classList.remove('hidden');
                iframe.classList.add('hidden');
            } else {
                iframe.innerHTML = `<iframe src="${src}" class="w-full h-full rounded-lg" frameborder="0"></iframe>`;
                iframe.classList.remove('hidden');
                img.classList.add('hidden');
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDocModal() {
            const modal = document.getElementById('docModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openRejectModal(id, name) {
            document.getElementById('rejectUserId').value = id;
            const modal = document.getElementById('rejectModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeRejectModal() {
            const modal = document.getElementById('rejectModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>

</html>
