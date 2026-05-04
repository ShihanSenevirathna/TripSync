<?php
$current_page = basename($_SERVER['PHP_SELF']);

function navLink($url, $icon, $label, $current_page) {
    // Only exact match for the page name
    $active = $current_page == $url ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white';
    return <<<HTML
    <a class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap cursor-pointer $active"
        href="$url">
        <i class="$icon text-base"></i>$label
    </a>
HTML;
}
?>
<nav class="fixed top-0 left-0 right-0 z-50 bg-slate-900 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <a class="flex items-center gap-3" href="dashboard.php">
                <img alt="TripSync Logo" class="h-10 w-auto" src="../assets/images/logo.png">
                <span class="hidden sm:inline-block px-2.5 py-1 bg-rose-500/20 text-rose-400 text-xs font-bold rounded-md tracking-wider whitespace-nowrap">ADMIN</span>
            </a>
            <div class="hidden md:flex items-center gap-1">
                <?php echo navLink('dashboard.php', 'ri-dashboard-3-line', 'Command Center', $current_page); ?>
                <?php echo navLink('verification.php', 'ri-shield-check-line', 'Verification', $current_page); ?>
                <?php echo navLink('users.php', 'ri-group-line', 'Users', $current_page); ?>
                <?php echo navLink('finance.php', 'ri-bank-line', 'Finance', $current_page); ?>
                <?php echo navLink('support.php', 'ri-customer-service-2-line', 'Support', $current_page); ?>
            </div>
            <div class="hidden md:flex items-center gap-2">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-emerald-500/20 rounded-full">
                    <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                    <span class="text-xs font-medium text-emerald-400 whitespace-nowrap">LIVE</span>
                </div>
                <!-- Notifications Link -->
                <a href="notifications.php" class="relative w-9 h-9 flex items-center justify-center <?php echo $current_page == 'notifications.php' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'; ?> rounded-full transition-colors cursor-pointer">
                    <i class="ri-notification-3-line text-lg"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-rose-500 rounded-full"></span>
                </a>
                <!-- Profile Link -->
                <a href="profile.php" class="w-9 h-9 flex items-center justify-center <?php echo $current_page == 'profile.php' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'; ?> rounded-full transition-colors cursor-pointer">
                    <i class="ri-user-settings-line text-lg"></i>
                </a>
                <!-- Main Site Link -->
                <a class="text-sm text-slate-400 hover:text-white transition-colors cursor-pointer whitespace-nowrap ml-1" href="../index.php">
                    <i class="ri-arrow-left-line mr-1"></i>Main Site
                </a>
                <!-- Logout Button -->
                <a href="../logout.php" class="ml-2 w-9 h-9 flex items-center justify-center text-rose-400 hover:text-rose-600 hover:bg-rose-500/10 rounded-full transition-colors" title="Logout">
                    <i class="ri-logout-box-r-line text-lg"></i>
                </a>
            </div>
            <button class="md:hidden w-8 h-8 flex items-center justify-center text-white cursor-pointer">
                <i class="ri-menu-line text-2xl"></i>
            </button>
        </div>
    </div>
</nav>
