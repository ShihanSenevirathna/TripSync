<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? $pageTitle . " - TripSync" : "TripSync - Smart Trip Planning Platform for Sri Lanka Travel"; ?></title>
  <meta name="description"
    content="TripSync is your trusted trip planning platform connecting travelers with hotels, drivers, and travel agencies in Sri Lanka.">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/fonts.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
  <script type="module" src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
  
  <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/logo.png">
</head>

<body>
  <div id="root">
    <div class="min-h-screen bg-white">
      <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-transparent">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center h-20"><a class="flex items-center gap-3" href="<?php echo BASE_URL; ?>index.php"
              data-discover="true"><img alt="TripSync Logo" class="h-12 w-auto" src="<?php echo BASE_URL; ?>assets/images/logo.png"></a>
            <div class="hidden md:flex items-center gap-6">
              <a class="text-sm font-medium transition-colors whitespace-nowrap <?php echo strpos($_SERVER['PHP_SELF'], 'index.php') !== false ? 'text-white' : 'text-white hover:text-teal-300'; ?>" href="<?php echo BASE_URL; ?>index.php">Home</a>
              
              <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['user_role'] === 'customer'): ?>
                  <a class="text-sm font-medium transition-colors whitespace-nowrap text-white hover:text-teal-300" href="<?php echo BASE_URL; ?>customer/dashboard.php">Dashboard</a>
                  <a class="text-sm font-medium transition-colors whitespace-nowrap text-white hover:text-teal-300" href="<?php echo BASE_URL; ?>customer/plan_trip.php">Plan Trip</a>
                  <a class="text-sm font-medium transition-colors whitespace-nowrap text-white hover:text-teal-300" href="<?php echo BASE_URL; ?>customer/marketplace.php">Marketplace</a>
                <?php
  elseif ($_SESSION['user_role'] === 'partner'): ?>
                  <a class="text-sm font-medium transition-colors whitespace-nowrap text-white hover:text-teal-300" href="<?php echo BASE_URL; ?>partner/dashboard.php">Partner Dashboard</a>
                <?php
  elseif ($_SESSION['user_role'] === 'admin'): ?>
                  <a class="text-sm font-medium transition-colors whitespace-nowrap text-white hover:text-teal-300" href="<?php echo BASE_URL; ?>admin/dashboard.php">Admin Panel</a>
                <?php
  endif; ?>
                
                <div class="flex items-center gap-4 ml-4 pl-4 border-l border-white/20">
                  <a class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors cursor-pointer text-white hover:bg-white/10" href="<?php echo BASE_URL; ?><?php echo $_SESSION['user_role']; ?>/notifications.php">
                    <i class="ri-notification-3-line text-lg"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-rose-500 rounded-full"></span>
                  </a>
                  <div class="group relative">
                    <button class="flex items-center gap-2 py-2 text-white hover:text-teal-300 transition-all outline-none">
                      <div class="w-8 h-8 rounded-full bg-teal-500 flex items-center justify-center text-xs font-bold ring-2 ring-white/20">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                      </div>
                      <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl py-2 invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all transform origin-top-right scale-95 group-hover:scale-100 z-50">
                      <div class="px-4 py-3 border-b border-gray-100 mb-1 text-left">
                        <p class="text-xs text-gray-400 font-medium">Signed in as</p>
                        <p class="text-sm font-bold text-gray-900 truncate"><?php echo clean($_SESSION['user_name']); ?></p>
                      </div>
                      <a href="<?php echo BASE_URL; ?><?php echo $_SESSION['user_role']; ?>/profile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-teal-50 hover:text-teal-600 transition-colors">
                        <i class="ri-user-settings-line"></i> Profile
                      </a>
                      <a href="<?php echo BASE_URL; ?>logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <i class="ri-logout-box-line"></i> Logout
                      </a>
                    </div>
                  </div>
                </div>
              <?php
else: ?>
                <a class="text-sm font-medium transition-colors whitespace-nowrap text-white hover:text-teal-300" href="<?php echo BASE_URL; ?>partner/register.php">Become a Partner</a>
                <div class="flex items-center gap-3 ml-4">
                  <a class="text-sm font-bold text-white px-5 py-2 hover:text-teal-300 transition-all" href="<?php echo BASE_URL; ?>login.php">Login</a>
                  <a class="text-sm font-bold bg-teal-600 text-white px-6 py-2.5 rounded-full hover:bg-teal-700 transition-all shadow-lg shadow-teal-600/20" href="<?php echo BASE_URL; ?>register.php">Join Now</a>
                </div>
              <?php
endif; ?>
            </div>
<button class="md:hidden w-8 h-8 flex items-center justify-center cursor-pointer text-white"><i
                class="ri-menu-line text-2xl"></i></button>
          </div>
        </div>
      </nav>
