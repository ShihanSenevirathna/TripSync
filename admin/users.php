<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
checkAuth('admin');

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $status = ($action === 'activate') ? 'active' : 'inactive';
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    if ($stmt->execute()) {
        $msg = "User status updated successfully.";
    } else {
        $error = "Error updating user status.";
    }
}

// Fetch stats
$customer_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
$partner_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'partner'")->fetch_assoc()['count'];
$admin_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];

$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$query = "SELECT * FROM users WHERE 1=1";
if ($role_filter !== 'all') {
    $query .= " AND role = '$role_filter'";
}
$query .= " ORDER BY created_at DESC";
$users = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - TripSync Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="assets/files/index-BjODfbg0.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        .bg-slate-900 { background-color: #0f172a !important; }
        .text-teal-600 { color: #0d9488 !important; }
        .bg-teal-600 { background-color: #0d9488 !important; }
        .bg-teal-50 { background-color: #f0fdfa !important; }
    </style>
</head>
<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen">
            <!-- Navigation -->
            <?php include 'includes/navbar.php'; ?>

            <div class="pt-24 pb-16 px-4 max-w-7xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
                        <p class="text-sm text-gray-500 mt-1">Manage platform access and user roles</p>
                    </div>
                    <div class="flex gap-3">
                        <div class="bg-white px-4 py-2 rounded-2xl border border-gray-100 flex items-center gap-3">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Users</span>
                            <span class="text-xl font-bold text-gray-900"><?php echo $users->num_rows; ?></span>
                        </div>
                    </div>
                </div>

                <?php if (isset($msg)): ?>
                    <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 rounded-2xl border border-emerald-100 flex items-center gap-2">
                        <i class="ri-checkbox-circle-line"></i> <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center text-2xl"><i class="ri-user-smile-line"></i></div>
                            <span class="text-xs font-bold text-sky-600 bg-sky-50 px-3 py-1 rounded-full uppercase tracking-tighter">Customers</span>
                        </div>
                        <h3 class="text-3xl font-black text-gray-900"><?php echo $customer_count; ?></h3>
                        <p class="text-xs text-gray-400 mt-1">Registered Travelers</p>
                    </div>
                    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl"><i class="ri-steering-2-line"></i></div>
                            <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full uppercase tracking-tighter">Partners</span>
                        </div>
                        <h3 class="text-3xl font-black text-gray-900"><?php echo $partner_count; ?></h3>
                        <p class="text-xs text-gray-400 mt-1">Verified Drivers & Agencies</p>
                    </div>
                    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl"><i class="ri-admin-line"></i></div>
                            <span class="text-xs font-bold text-rose-600 bg-rose-50 px-3 py-1 rounded-full uppercase tracking-tighter">Admins</span>
                        </div>
                        <h3 class="text-3xl font-black text-gray-900"><?php echo $admin_count; ?></h3>
                        <p class="text-xs text-gray-400 mt-1">System Overseers</p>
                    </div>
                </div>

                <!-- Filters & Search -->
                <div class="bg-white p-4 rounded-[2rem] border border-gray-100 mb-8 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-1 bg-gray-50 rounded-full p-1.5">
                        <a href="?role=all" class="px-6 py-2 rounded-full text-xs font-bold transition-all <?php echo $role_filter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-400 hover:text-gray-600'; ?>">All</a>
                        <a href="?role=customer" class="px-6 py-2 rounded-full text-xs font-bold transition-all <?php echo $role_filter === 'customer' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-400 hover:text-gray-600'; ?>">Customers</a>
                        <a href="?role=partner" class="px-6 py-2 rounded-full text-xs font-bold transition-all <?php echo $role_filter === 'partner' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-400 hover:text-gray-600'; ?>">Partners</a>
                        <a href="?role=admin" class="px-6 py-2 rounded-full text-xs font-bold transition-all <?php echo $role_filter === 'admin' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-400 hover:text-gray-600'; ?>">Admins</a>
                    </div>
                    <div class="relative w-full md:w-96">
                        <i class="ri-search-2-line absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="user-search" onkeyup="searchUsers()" placeholder="Search by name, email or phone..." class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm focus:ring-2 focus:ring-teal-500 focus:outline-none transition-all">
                    </div>
                </div>

                <!-- Users Table -->
                <div class="bg-white rounded-[2.5rem] border border-gray-100 overflow-hidden shadow-xl shadow-gray-100/50">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50 border-b border-gray-100">
                                <tr>
                                    <th class="px-8 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">User Details</th>
                                    <th class="px-8 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Role</th>
                                    <th class="px-8 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                    <th class="px-8 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Joined On</th>
                                    <th class="px-8 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50" id="users-table-body">
                                <?php while ($u = $users->fetch_assoc()): ?>
                                    <tr class="user-row hover:bg-gray-50/50 transition-colors">
                                        <td class="px-8 py-5">
                                            <div class="flex items-center gap-4">
                                                <div class="w-11 h-11 rounded-2xl overflow-hidden flex-shrink-0 bg-teal-50">
                                                    <img src="../assets/images/<?php echo $u['profile_pic'] ?: 'default-avatar.jpg'; ?>" class="w-full h-full object-cover">
                                                </div>
                                                <div>
                                                    <p class="font-bold text-gray-900 user-name"><?php echo htmlspecialchars($u['name']); ?></p>
                                                    <p class="text-[11px] text-gray-400 font-medium user-email"><?php echo htmlspecialchars($u['email']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-5">
                                            <span class="px-3 py-1 text-[10px] font-black uppercase rounded-lg <?php 
                                                echo $u['role'] === 'admin' ? 'bg-rose-50 text-rose-600' : 
                                                    ($u['role'] === 'partner' ? 'bg-emerald-50 text-emerald-600' : 'bg-sky-50 text-sky-600'); 
                                            ?> tracking-tighter"><?php echo $u['role']; ?></span>
                                        </td>
                                        <td class="px-8 py-5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-1.5 h-1.5 rounded-full <?php echo $u['status'] === 'active' ? 'bg-emerald-500' : 'bg-rose-500'; ?>"></div>
                                                <span class="text-xs font-bold text-gray-700 capitalize"><?php echo $u['status']; ?></span>
                                            </div>
                                        </td>
                                        <td class="px-8 py-5">
                                            <p class="text-xs font-bold text-gray-700"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></p>
                                        </td>
                                        <td class="px-8 py-5 text-right">
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                                <?php if ($u['status'] === 'active'): ?>
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" onclick="return confirm('Suspend this user?')" class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all"><i class="ri-user-forbid-line text-lg"></i></button>
                                                <?php else: ?>
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="p-2 text-emerald-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all"><i class="ri-user-follow-line text-lg"></i></button>
                                                <?php endif; ?>
                                            </form>
                                            <button class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-all ml-1"><i class="ri-more-2-fill text-lg"></i></button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function searchUsers() {
            const input = document.getElementById('user-search');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');

            rows.forEach(row => {
                const name = row.querySelector('.user-name').innerText.toLowerCase();
                const email = row.querySelector('.user-email').innerText.toLowerCase();
                if (name.includes(filter) || email.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>
