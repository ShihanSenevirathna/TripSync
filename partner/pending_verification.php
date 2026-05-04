<?php
require_once '../includes/config.php';

// Basic safety check: Only show this if user is really pending
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'partner') {
    header("Location: ../login.php");
    exit();
}

// Check real-time status in case they were just approved
require_once '../includes/config.php';
$stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$status = $stmt->get_result()->fetch_assoc()['status'];

if ($status === 'active') {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Pending - TripSync Partner</title>
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-gray-100 p-8 text-center relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute top-0 left-0 w-full h-2 bg-emerald-600"></div>
        
        <div class="w-24 h-24 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6 relative">
            <i class="ri-time-line text-5xl text-amber-500 animate-pulse"></i>
            <div class="absolute -right-1 -bottom-1 w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-sm">
                <i class="ri-shield-user-fill text-amber-600 text-xl"></i>
            </div>
        </div>

        <h1 class="text-3xl font-black text-gray-900 mb-2">Verification Pending</h1>
        <p class="text-gray-500 text-sm mb-8 leading-relaxed">
            Your application is currently under review by our safety team. We verify all documents to ensure the highest quality of service.
        </p>

        <div class="space-y-4 mb-8">
            <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100 text-left">
                <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 flex-shrink-0">
                    <i class="ri-check-line text-xl font-bold"></i>
                </div>
                <div>
                    <h4 class="text-xs font-black text-gray-900 uppercase">Step 1: Application</h4>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Completed Successfully</p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 bg-amber-50/50 rounded-2xl border border-amber-100 text-left">
                <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600 flex-shrink-0">
                    <i class="ri-loader-4-line text-xl animate-spin"></i>
                </div>
                <div>
                    <h4 class="text-xs font-black text-gray-900 uppercase">Step 2: Document Review</h4>
                    <p class="text-[10px] text-amber-600 uppercase font-bold tracking-widest">In Progress (ETA 24-48h)</p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 bg-gray-50/50 rounded-2xl border border-gray-100 text-left opacity-50">
                <div class="w-10 h-10 bg-gray-200 rounded-xl flex items-center justify-center text-gray-400 flex-shrink-0">
                    <i class="ri-lock-line text-xl"></i>
                </div>
                <div>
                    <h4 class="text-xs font-black text-gray-900 uppercase">Step 3: Dashboard Access</h4>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Waiting for Approval</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <button onclick="window.location.reload()" class="w-full py-3 bg-white border border-gray-200 text-gray-600 text-xs font-black rounded-xl hover:bg-gray-50 transition-all uppercase tracking-widest">
                Check Status
            </button>
            <a href="../logout.php" class="w-full py-3 bg-gray-900 text-white text-xs font-black rounded-xl hover:bg-black transition-all uppercase tracking-widest">
                Logout
            </a>
        </div>

        <p class="mt-8 text-[10px] text-gray-400 font-bold uppercase tracking-tighter">
            Need help? Contact <span class="text-teal-600">support@tripsync.lk</span>
        </p>
    </div>
</body>
</html>
