<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('admin');

$admin_id = $_SESSION['user_id'];
$admin = $conn->query("SELECT * FROM users WHERE id = $admin_id")->fetch_assoc();

// Handle Profile Update
if (isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $profile_pic = $admin['profile_pic'];

    // Handle Profile Pic Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $targetDir = "../assets/images/profiles/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $fileName = "admin_" . $admin_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetDir . $fileName)) {
            $profile_pic = "profiles/" . $fileName; // Path relative to assets/images/
        }
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, profile_pic = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $phone, $profile_pic, $admin_id);
    if ($stmt->execute()) {
        $msg = "Profile updated successfully.";
        // Refresh data
        $admin = $conn->query("SELECT * FROM users WHERE id = $admin_id")->fetch_assoc();
    }
    else {
        $error = "Error updating profile.";
    }
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (password_verify($current_pass, $admin['password'])) {
        if ($new_pass === $confirm_pass) {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_pass, $admin_id);
            if ($stmt->execute()) {
                $msg = "Password updated successfully.";
            }
            else {
                $error = "Error updating password.";
            }
        }
        else {
            $error = "New passwords do not match.";
        }
    }
    else {
        $error = "Incorrect current password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - TripSync</title>
    <meta name="description"
        content="TripSync Admin Profile - Manage system settings and administrator account information.">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="assets/files/index-BjODfbg0.css">
    
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        .bg-rose-600 { background-color: #e11d48 !important; }
        .bg-rose-500 { background-color: #f43f5e !important; }
        .bg-rose-50 { background-color: #fff1f2 !important; }
        .bg-emerald-50 { background-color: #ecfdf5 !important; }
        .bg-emerald-500\/20 { background-color: rgba(16, 185, 129, 0.2) !important; }
        .bg-rose-500\/20 { background-color: rgba(244, 63, 94, 0.2) !important; }
        .bg-white\/10 { background-color: rgba(255, 255, 255, 0.1) !important; }
        .bg-white\/5 { background-color: rgba(255, 255, 255, 0.05) !important; }
        
        .text-rose-700 { color: #be123c !important; }
        .text-rose-600 { color: #e11d48 !important; }
        .text-rose-400 { color: #fb7185 !important; }
        .text-emerald-700 { color: #047857 !important; }
        .text-emerald-400 { color: #34d399 !important; }
    </style>
</head>

<body class="bg-gray-50">
    <div id="root">
        <div class="min-h-screen">
            <!-- Navigation -->
            <?php include 'includes/navbar.php'; ?>

            <main class="pt-24 pb-16 px-4">
                <div class="max-w-5xl mx-auto">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Profile Settings</h1>
                            <p class="text-sm text-gray-500 mt-1">Manage your personal information and preferences</p>
                        </div>
                        <a class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors cursor-pointer whitespace-nowrap"
                            href="dashboard.php">
                            <i class="ri-arrow-left-line text-base"></i>Back to Dashboard
                        </a>
                    </div>

                    <?php if (isset($msg)): ?>
                        <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-100 flex items-center gap-2">
                            <i class="ri-checkbox-circle-line"></i> <?php echo $msg; ?>
                        </div>
                    <?php
endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="mb-6 p-4 bg-rose-50 text-rose-700 rounded-xl border border-rose-100 flex items-center gap-2">
                            <i class="ri-error-warning-line"></i> <?php echo $error; ?>
                        </div>
                    <?php
endif; ?>


                    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Sidebar Profile Card -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center sticky top-24">
                                <div class="relative w-28 h-28 mx-auto mb-4 group">
                                    <img id="profile-preview" alt="Admin Avatar"
                                        class="w-full h-full rounded-full object-cover object-top border-4 border-white shadow-lg transition-all"
                                        src="../assets/images/<?php echo $admin['profile_pic'] ?: 'default-avatar.jpg'; ?>">
                                    <label for="profile_pic" class="absolute inset-0 flex items-center justify-center bg-black/40 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                        <div class="text-center">
                                            <i class="ri-camera-switch-line text-2xl"></i>
                                            <p class="text-[8px] font-black uppercase tracking-widest mt-1">Change</p>
                                        </div>
                                    </label>
                                    <input type="file" name="profile_pic" id="profile_pic" class="hidden" accept="image/*" onchange="previewImage(this)">
                                </div>
                                <h3 class="text-lg font-bold text-gray-900"><?php echo $admin['name']; ?></h3>
                                <p class="text-sm text-gray-500 mb-2">Platform Administrator</p>
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-50 text-rose-700 text-xs font-medium rounded-full">
                                    <i class="ri-shield-user-fill text-sm"></i><?php echo ucfirst($admin['role']); ?>
                                </span>
                                <div class="mt-5 pt-5 border-t border-gray-100 text-left space-y-3">
                                    <div class="flex items-center gap-3 text-sm">
                                        <div class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg">
                                            <i class="ri-mail-line text-gray-500 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-gray-400 text-xs">Email Address</p>
                                            <p class="text-gray-700 font-medium"><?php echo $admin['email']; ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 text-sm">
                                        <div class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg">
                                            <i class="ri-calendar-line text-gray-500 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-gray-400 text-xs">Member Since</p>
                                            <p class="text-gray-700 font-medium"><?php echo date('F Y', strtotime($admin['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-5 pt-5 border-t border-gray-100">
                                    <a href="../logout.php" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-50 text-rose-600 rounded-xl text-sm font-bold hover:bg-rose-100 transition-colors cursor-pointer">
                                        <i class="ri-logout-box-r-line text-lg"></i> Log Out
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Form Sections -->
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                    <div class="w-6 h-6 flex items-center justify-center">
                                        <i class="ri-user-line text-rose-500 text-base"></i>
                                    </div>Admin Information
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Full Name</label>
                                        <input name="name"
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 focus:border-transparent outline-none"
                                            type="text" value="<?php echo $admin['name']; ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Email (Read-only)</label>
                                        <input readonly
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 text-gray-500 cursor-not-allowed outline-none"
                                            type="email" value="<?php echo $admin['email']; ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Phone</label>
                                        <input name="phone"
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 focus:border-transparent outline-none"
                                            type="tel" value="<?php echo $admin['phone']; ?>">
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end">
                                    <button type="submit" name="update_profile"
                                        class="px-8 py-2.5 bg-rose-600 text-white text-sm font-medium rounded-full hover:bg-rose-700 transition-colors cursor-pointer">
                                        Update Profile
                                    </button>
                                </div>
                            </div>
                    </form>


                            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                    <div class="w-6 h-6 flex items-center justify-center">
                                        <i class="ri-shield-keyhole-line text-rose-500 text-base"></i>
                                    </div>Security & Preferences
                                </h3>
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Two-Factor Authentication</p>
                                            <p class="text-xs text-gray-500">Add an extra layer of security to your
                                                admin account</p>
                                        </div>
                                        <button
                                            class="relative w-12 h-6 rounded-full transition-all cursor-pointer bg-rose-500">
                                            <div
                                                class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all left-6">
                                            </div>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Email Notifications</p>
                                            <p class="text-xs text-gray-500">Receive alerts for new registrations and
                                                system events</p>
                                        </div>
                                        <button
                                            class="relative w-12 h-6 rounded-full transition-all cursor-pointer bg-rose-500">
                                            <div
                                                class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all left-6">
                                            </div>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">SMS Alerts</p>
                                            <p class="text-xs text-gray-500">Get text messages for critical system
                                                alerts</p>
                                        </div>
                                        <button
                                            class="relative w-12 h-6 rounded-full transition-all cursor-pointer bg-gray-300">
                                            <div
                                                class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all left-0.5">
                                            </div>
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Push Notifications</p>
                                            <p class="text-xs text-gray-500">Browser notifications for real-time admin
                                                alerts</p>
                                        </div>
                                        <button
                                            class="relative w-12 h-6 rounded-full transition-all cursor-pointer bg-rose-500">
                                            <div
                                                class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all left-6">
                                            </div>
                                        </button>
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Language</label>
                                        <select
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 focus:border-transparent cursor-pointer">
                                            <option>English</option>
                                            <option>Sinhala</option>
                                            <option>Tamil</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <form method="POST" class="bg-white rounded-2xl border border-gray-200 p-6">
                                 <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                     <div class="w-6 h-6 flex items-center justify-center">
                                         <i class="ri-lock-line text-rose-500 text-base"></i>
                                     </div>Security Settings
                                 </h3>
                                 <div class="space-y-4">
                                     <div>
                                         <label class="block text-xs font-medium text-gray-500 mb-1.5">Current
                                             Password</label>
                                         <input name="current_password" required
                                             class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 focus:border-transparent outline-none"
                                             type="password" placeholder="••••••••">
                                     </div>
                                     <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                         <div>
                                             <label class="block text-xs font-medium text-gray-500 mb-1.5">New
                                                 Password</label>
                                             <input name="new_password" required
                                                 class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 focus:border-transparent outline-none"
                                                 type="password" placeholder="••••••••">
                                         </div>
                                         <div>
                                             <label class="block text-xs font-medium text-gray-500 mb-1.5">Confirm New
                                                 Password</label>
                                             <input name="confirm_password" required
                                                 class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 focus:border-transparent outline-none"
                                                 type="password" placeholder="••••••••">
                                         </div>
                                     </div>
                                 </div>
                                 <div class="mt-6 flex justify-end">
                                     <button type="submit" name="change_password"
                                         class="px-8 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-slate-800 transition-colors cursor-pointer">
                                         Change Password
                                     </button>
                                 </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>

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
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>
