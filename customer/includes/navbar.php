<?php
$nav_uid = $_SESSION['user_id'];

// Get unread notification count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $nav_uid);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'];
?>
<nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <a class="flex items-center gap-3" href="../index.php">
                <img alt="TripSync Logo" class="h-12 w-auto" src="../assets/images/logo.png">
            </a>
            <div class="hidden md:flex items-center gap-6 lg:gap-8">
                <a class="nav-link text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                    id="nav-home" href="../index.php">Home</a>
                <a class="nav-link text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                    id="nav-dashboard" href="dashboard.php">Dashboard</a>
                <a class="nav-link text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                    id="nav-plan_trip" href="plan_trip.php">Plan Trip</a>
                <a class="nav-link text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                    id="nav-marketplace" href="marketplace.php">Marketplace</a>
                <a class="nav-link text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                    id="nav-wallet" href="wallet.php">Wallet</a>
                <a class="nav-link text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                    id="nav-reviews" href="reviews.php">Reviews</a>
                <a class="nav-link text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                    id="nav-trip_history" href="trip_history.php">Trip History</a>
                <a class="nav-link text-sm font-medium transition-colors whitespace-nowrap text-gray-700 hover:text-teal-600"
                    id="nav-help" href="help.php">Help</a>
                <div class="flex items-center gap-2">
                    <a class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-gray-600 hover:bg-gray-100"
                        id="nav-notifications" href="notifications.php">
                        <i class="ri-notification-3-line text-lg"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-rose-500 rounded-full"></span>
                        <?php endif; ?>
                    </a>
                    <a class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-gray-600 hover:bg-gray-100"
                        id="nav-profile" href="profile.php">
                        <i class="ri-user-line text-lg"></i>
                    </a>
                </div>
            </div>
            <button class="md:hidden w-8 h-8 flex items-center justify-center cursor-pointer text-gray-700">
                <i class="ri-menu-line text-2xl"></i>
            </button>
        </div>
    </div>
</nav>

<script>
    // Handle Active States
    document.addEventListener('DOMContentLoaded', () => {
        const path = window.location.pathname;
        const page = path.split("/").pop();
        
        const navMap = {
            'dashboard.php': 'nav-dashboard',
            'plan_trip.php': 'nav-plan_trip',
            'marketplace.php': 'nav-marketplace',
            'wallet.php': 'nav-wallet',
            'reviews.php': 'nav-reviews',
            'trip_history.php': 'nav-trip_history',
            'help.php': 'nav-help',
            'view_ticket.php': 'nav-help',
            'notifications.php': 'nav-notifications',
            'profile.php': 'nav-profile'
        };

        const activeId = navMap[page];
        if (activeId) {
            const el = document.getElementById(activeId);
            if (el) {
                if (el.classList.contains('nav-link')) {
                    el.classList.remove('text-gray-700', 'hover:text-teal-600');
                    el.classList.add('text-teal-600');
                } else {
                    el.classList.add('bg-teal-50', 'text-teal-600');
                }
            }
        }
    });
</script>
