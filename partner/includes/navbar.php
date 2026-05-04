<nav class="fixed top-0 left-0 right-0 z-50 bg-white shadow-sm border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <a class="flex items-center gap-3" href="dashboard.php">
                <img alt="TripSync Logo" class="h-10 w-auto" src="../assets/images/logo.png">
                <span
                    class="hidden sm:inline-block px-2 py-0.5 bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-md whitespace-nowrap">PARTNER</span>
            </a>
            <div class="hidden md:flex items-center gap-1">
                <a class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap cursor-pointer text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                    id="nav-dashboard" href="dashboard.php">
                    <i class="ri-dashboard-line text-base"></i>Dashboard
                </a>
                <a class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap cursor-pointer text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                    id="nav-active-trip" href="active-trip.php">
                    <i class="ri-steering-2-line text-base"></i>Active Trip
                </a>
                <a class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap cursor-pointer text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                    id="nav-earnings" href="earnings.php">
                    <i class="ri-money-dollar-circle-line text-base"></i>Earnings
                </a>
                <a class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap cursor-pointer text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                    id="nav-messages" href="messages.php">
                    <i class="ri-message-3-line text-base"></i>Messages
                </a>
                <a class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap cursor-pointer text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                    id="nav-help" href="help.php">
                    <i class="ri-question-line text-base"></i>Help Hub
                </a>
            </div>
            <div class="hidden md:flex items-center gap-2">
                <a class="relative w-9 h-9 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-full transition-colors cursor-pointer"
                    id="nav-notifications" href="notifications.php">
                    <i class="ri-notification-3-line text-lg"></i>
                    <?php
                    $nav_uid = $_SESSION['user_id'];
                    $unread_res = $conn->query("SELECT id FROM notifications WHERE user_id = $nav_uid AND is_read = 0 LIMIT 1");
                    if ($unread_res && $unread_res->num_rows > 0): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-rose-500 rounded-full"></span>
                    <?php endif; ?>
                </a>
                <a class="w-9 h-9 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-full transition-colors cursor-pointer"
                    id="nav-profile" href="profile.php">
                    <i class="ri-user-settings-line text-lg"></i>
                </a>
                <a class="text-sm text-gray-500 hover:text-gray-700 transition-colors cursor-pointer whitespace-nowrap ml-1"
                    href="../index.php">
                    <i class="ri-arrow-left-line mr-1"></i>Traveler View
                </a>
            </div>
            <button class="md:hidden w-8 h-8 flex items-center justify-center text-gray-700 cursor-pointer">
                <i class="ri-menu-line text-2xl"></i>
            </button>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const path = window.location.pathname;
        const page = path.split("/").pop();
        
        const navItems = {
            'dashboard.php': 'nav-dashboard',
            'active-trip.php': 'nav-active-trip',
            'earnings.php': 'nav-earnings',
            'messages.php': 'nav-messages',
            'notifications.php': 'nav-notifications',
            'profile.php': 'nav-profile',
            'help.php': 'nav-help'
        };

        if (navItems[page]) {
            const el = document.getElementById(navItems[page]);
            if (el) {
                el.classList.add('bg-emerald-50', 'text-emerald-700');
                el.classList.remove('text-gray-600', 'hover:bg-gray-50', 'hover:text-gray-900');
            }
        }
    });
</script>
