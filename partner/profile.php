<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkAuth('partner');

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $address = clean($_POST['address'] ?? '');
    $dob = !empty($_POST['dob']) ? clean($_POST['dob']) : null;
    $license_number = clean($_POST['license_number'] ?? '');
    $nic_number = clean($_POST['nic_number'] ?? '');
    $emergency_contact_name = clean($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = clean($_POST['emergency_contact_phone'] ?? '');

    // New Expiry Dates
    $license_expiry = !empty($_POST['license_expiry']) ? clean($_POST['license_expiry']) : null;
    $registration_expiry = !empty($_POST['registration_expiry']) ? clean($_POST['registration_expiry']) : null;
    $insurance_expiry_user = !empty($_POST['insurance_expiry']) ? clean($_POST['insurance_expiry']) : null;
    $background_expiry = !empty($_POST['background_check_expiry']) ? clean($_POST['background_check_expiry']) : null;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    try {
        // 1. Update Personal Info
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, address = ?, dob = ?, license_number = ?, nic_number = ?, emergency_contact_name = ?, emergency_contact_phone = ?, license_expiry = ?, registration_expiry = ?, insurance_expiry = ?, background_check_expiry = ? WHERE id = ?");
        $stmt->bind_param("ssssssssssssi", $name, $phone, $address, $dob, $license_number, $nic_number, $emergency_contact_name, $emergency_contact_phone, $license_expiry, $registration_expiry, $insurance_expiry_user, $background_expiry, $user_id);
        $stmt->execute();

        // 2. Handle File Uploads (Profile Pic, License, Registration)
        $target_dir = "../assets/images/partners/";
        if (!is_dir($target_dir))
            mkdir($target_dir, 0777, true);

        // Profile Picture
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $new_name = "profile_" . $user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $new_name)) {
                $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $user_id);
                $stmt->execute();
            }
        }

        // License Document
        if (isset($_FILES['license_doc']) && $_FILES['license_doc']['error'] === 0) {
            $ext = pathinfo($_FILES['license_doc']['name'], PATHINFO_EXTENSION);
            $new_name = "lic_" . $user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['license_doc']['tmp_name'], $target_dir . $new_name)) {
                $stmt = $conn->prepare("UPDATE users SET license_doc = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $user_id);
                $stmt->execute();
            }
        }

        // Registration Document
        if (isset($_FILES['reg_doc']) && $_FILES['reg_doc']['error'] === 0) {
            $ext = pathinfo($_FILES['reg_doc']['name'], PATHINFO_EXTENSION);
            $new_name = "reg_" . $user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['reg_doc']['tmp_name'], $target_dir . $new_name)) {
                $stmt = $conn->prepare("UPDATE users SET registration_doc = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $user_id);
                $stmt->execute();
            }
        }

        // Insurance Document
        if (isset($_FILES['insurance_doc']) && $_FILES['insurance_doc']['error'] === 0) {
            $ext = pathinfo($_FILES['insurance_doc']['name'], PATHINFO_EXTENSION);
            $new_name = "ins_" . $user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['insurance_doc']['tmp_name'], $target_dir . $new_name)) {
                $stmt = $conn->prepare("UPDATE users SET insurance_doc = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $user_id);
                $stmt->execute();
            }
        }

        // Background Check Document
        if (isset($_FILES['background_doc']) && $_FILES['background_doc']['error'] === 0) {
            $ext = pathinfo($_FILES['background_doc']['name'], PATHINFO_EXTENSION);
            $new_name = "bgc_" . $user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['background_doc']['tmp_name'], $target_dir . $new_name)) {
                $stmt = $conn->prepare("UPDATE users SET background_check_doc = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $user_id);
                $stmt->execute();
            }
        }

        // 3. Update Vehicle Info
        if (isset($_POST['vehicle_model'])) {
            $v_model = clean($_POST['vehicle_model']);
            $v_maker = clean($_POST['maker']);
            $v_reg = clean($_POST['reg_number']);
            $v_type = clean($_POST['vehicle_type']);
            $v_year = (int)$_POST['vehicle_year'];
            $v_color = clean($_POST['vehicle_color']);
            $v_capacity = (int)$_POST['capacity'];
            $v_fuel = clean($_POST['fuel_type']);
            $v_trans = clean($_POST['transmission']);
            $v_ins_exp = !empty($_POST['vehicle_insurance_expiry']) ? clean($_POST['vehicle_insurance_expiry']) : null;
            $v_status = clean($_POST['vehicle_status'] ?? 'available');
            $v_features = isset($_POST['features']) ? implode(',', $_POST['features']) : '';

            // Using INSERT ... ON DUPLICATE KEY UPDATE to handle missing vehicle rows
            $stmt = $conn->prepare("INSERT INTO vehicles (owner_id, model, maker, reg_number, type, year, color, capacity, fuel_type, transmission, insurance_expiry, features, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE model = VALUES(model), maker = VALUES(maker), reg_number = VALUES(reg_number), type = VALUES(type), year = VALUES(year), color = VALUES(color), capacity = VALUES(capacity), fuel_type = VALUES(fuel_type), transmission = VALUES(transmission), insurance_expiry = VALUES(insurance_expiry), features = VALUES(features), status = VALUES(status)");
            $stmt->bind_param("issssssisssss", $user_id, $v_model, $v_maker, $v_reg, $v_type, $v_year, $v_color, $v_capacity, $v_fuel, $v_trans, $v_ins_exp, $v_features, $v_status);
            $stmt->execute();
        }

        $conn->commit();
        $success_msg = "Profile updated successfully!";

        // Refresh all data after update
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("SELECT * FROM vehicles WHERE owner_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $vehicle = $stmt->get_result()->fetch_assoc();
    }
    catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Failed to update profile: " . $e->getMessage();
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch vehicle data
$stmt = $conn->prepare("SELECT * FROM vehicles WHERE owner_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc() ?? ['model' => '', 'maker' => '', 'rating' => 0];

$partner_name = $user['name'] ?? 'Partner';
$vehicle_display_name = trim(($vehicle['maker'] ?? '') . ' ' . ($vehicle['model'] ?? '')) ?: 'No vehicle';

$profile_pic_path = getProfilePic($user['profile_pic'] ?? '', '../');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Profile - TripSync</title>
    <meta name="description"
        content="TripSync is your trusted trip planning platform connecting travelers with hotels, drivers, and travel agencies in Sri Lanka.">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="module" src="../assets/js/main.js"></script>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        .sidebar-btn.active {
            background-color: #f0fdfa;
            color: #0d9488;
        }

        .section-content {
            display: none;
        }

        .section-content.active {
            display: block;
        }

        .feature-tag {
            cursor: pointer;
            transition: all 0.2s;
        }

        .feature-tag input:checked+span {
            background-color: #0d9488;
            color: white;
            border-color: #0d9488;
        }

        .feature-tag input:checked+span i {
            display: inline-block;
        }

        .feature-tag i {
            display: none;
        }
    </style>
</head>

<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen bg-gray-50">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>

            <div class="pt-24 pb-16 px-4">
                <div class="max-w-5xl mx-auto">
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

                    <?php if ($success_msg): ?>
                        <div class="mb-6 p-4 bg-emerald-100 text-emerald-700 rounded-xl flex items-center gap-3">
                            <i class="ri-checkbox-circle-line text-xl"></i>
                            <span class="text-sm font-medium"><?php echo $success_msg; ?></span>
                        </div>
                    <?php
endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="mb-6 p-4 bg-rose-100 text-rose-700 rounded-xl flex items-center gap-3">
                            <i class="ri-error-warning-line text-xl"></i>
                            <span class="text-sm font-medium"><?php echo $error_msg; ?></span>
                        </div>
                    <?php
endif; ?>



                    <form action="profile.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center sticky top-24">
                                <div class="relative w-28 h-28 mx-auto mb-4">
                                    <img alt="<?php echo htmlspecialchars($partner_name); ?>"
                                        class="w-full h-full rounded-full object-cover object-top border-4 border-white shadow-lg"
                                        src="<?php echo htmlspecialchars($profile_pic_path); ?>">
                                    <button type="button" onclick="document.getElementById('profile_pic_input').click()"
                                        class="absolute bottom-0 right-0 w-9 h-9 flex items-center justify-center bg-emerald-600 text-white rounded-full shadow-md hover:bg-emerald-700 transition-colors cursor-pointer">
                                        <i class="ri-camera-line text-sm"></i>
                                    </button>
                                    <input type="file" name="profile_pic" id="profile_pic_input" class="hidden" accept="image/*">
                                </div>
                                <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($partner_name); ?></h3>
                                <p class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($vehicle_display_name); ?></p>
                                <div class="flex items-center justify-center gap-1 mb-3">
                                    <i class="ri-star-fill text-sm text-amber-400"></i>
                                    <i class="ri-star-fill text-sm text-amber-400"></i>
                                    <i class="ri-star-fill text-sm text-amber-400"></i>
                                    <i class="ri-star-fill text-sm text-amber-400"></i>
                                    <i class="ri-star-fill text-sm text-amber-400"></i>
                                    <span class="text-sm font-semibold text-gray-700 ml-1"><?php echo number_format($vehicle['rating'] ?? 0, 1); ?></span>
                                </div>
                                <?php if (($user['status'] ?? 'pending') === 'active'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-emerald-600 text-white text-xs font-bold rounded-full shadow-sm">
                                        <i class="ri-verified-badge-fill text-sm"></i>Verified Partner
                                    </span>
                                <?php
elseif (($user['status'] ?? 'pending') === 'pending'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-amber-600 text-white text-xs font-bold rounded-full shadow-sm">
                                        <i class="ri-history-line text-sm"></i>Verification Pending
                                    </span>
                                <?php
else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-rose-600 text-white text-xs font-bold rounded-full shadow-sm">
                                        <i class="ri-error-warning-fill text-sm"></i>Access Restricted
                                    </span>
                                <?php
endif; ?>
                                <div class="mt-5 pt-5 border-t border-gray-100 space-y-1">
                                    <button type="button" onclick="showSection('personal-info', this)"
                                        class="sidebar-btn w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-sm font-medium transition-all cursor-pointer bg-emerald-50 text-emerald-700 active">
                                        <div class="flex items-center gap-3">
                                            <div class="w-5 h-5 flex items-center justify-center"><i class="ri-user-line text-base"></i></div>
                                            Personal Info
                                        </div>
                                    </button>
                                    <button type="button" onclick="showSection('vehicle-info', this)"
                                        class="sidebar-btn w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-sm font-medium transition-all cursor-pointer text-gray-600 hover:bg-gray-50">
                                        <div class="flex items-center gap-3">
                                            <div class="w-5 h-5 flex items-center justify-center"><i class="ri-car-line text-base"></i></div>
                                            Vehicle Details
                                        </div>
                                    </button>
                                    <button type="button" onclick="showSection('support-info', this)"
                                        class="sidebar-btn w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-sm font-medium transition-all cursor-pointer text-gray-600 hover:bg-gray-50">
                                        <div class="flex items-center gap-3">
                                            <div class="w-5 h-5 flex items-center justify-center"><i class="ri-customer-service-2-line text-base"></i></div>
                                            Help & Support
                                        </div>
                                    </button>
                                </div>
                                <div class="mt-5 pt-5 border-t border-gray-100">
                                    <a href="../logout.php" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-50 text-rose-600 rounded-xl text-sm font-bold hover:bg-rose-100 transition-colors cursor-pointer">
                                        <i class="ri-logout-box-r-line text-lg"></i> Log Out
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-2 space-y-6">
                            <div id="personal-info" class="section-content active bg-white rounded-2xl border border-gray-200 p-6">
                                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                    <div class="w-6 h-6 flex items-center justify-center"><i
                                            class="ri-user-line text-emerald-600 text-base"></i></div>Personal
                                    Information
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Full Name</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="name" type="text" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Email</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 cursor-not-allowed"
                                            type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Phone</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="phone" type="tel" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Date of
                                            Birth</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="dob" type="date" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Address</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="address" type="text" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">License
                                            Number</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="license_number" type="text" value="<?php echo htmlspecialchars($user['license_number'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">NIC Number</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="nic_number" type="text" value="<?php echo htmlspecialchars($user['nic_number'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Emergency Contact
                                            Name</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="emergency_contact_name" type="text" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Emergency Contact
                                            Number</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="emergency_contact_phone" type="tel" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div id="vehicle-info" class="section-content bg-white rounded-2xl border border-gray-200 p-6">
                                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                    <div class="w-6 h-6 flex items-center justify-center"><i
                                            class="ri-car-line text-emerald-600 text-base"></i></div>Vehicle
                                    Information
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Vehicle Type</label>
                                        <select name="vehicle_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                                            <option value="sedan" <?php echo($vehicle['type'] ?? '') == 'sedan' ? 'selected' : ''; ?>>Sedan</option>
                                            <option value="suv" <?php echo($vehicle['type'] ?? '') == 'suv' ? 'selected' : ''; ?>>SUV</option>
                                            <option value="van" <?php echo($vehicle['type'] ?? '') == 'van' ? 'selected' : ''; ?>>Van</option>
                                            <option value="tuk-tuk" <?php echo($vehicle['type'] ?? '') == 'tuk-tuk' ? 'selected' : ''; ?>>Tuk-Tuk</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Maker</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="maker" type="text" placeholder="e.g. Toyota" value="<?php echo htmlspecialchars($vehicle['maker'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Model</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="vehicle_model" type="text" placeholder="e.g. KDH 201" value="<?php echo htmlspecialchars($vehicle['model'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Year</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="vehicle_year" type="number" placeholder="2021" value="<?php echo htmlspecialchars($vehicle['year'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Color</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="vehicle_color" type="text" placeholder="White" value="<?php echo htmlspecialchars($vehicle['color'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">License Plate</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="reg_number" type="text" placeholder="WP CAB-4521" value="<?php echo htmlspecialchars($vehicle['reg_number'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Seating Capacity</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="capacity" type="number" placeholder="8" value="<?php echo htmlspecialchars($vehicle['capacity'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Fuel Type</label>
                                        <select name="fuel_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                                            <option value="petrol" <?php echo($vehicle['fuel_type'] ?? '') == 'petrol' ? 'selected' : ''; ?>>Petrol</option>
                                            <option value="diesel" <?php echo($vehicle['fuel_type'] ?? '') == 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                            <option value="hybrid" <?php echo($vehicle['fuel_type'] ?? '') == 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                            <option value="electric" <?php echo($vehicle['fuel_type'] ?? '') == 'electric' ? 'selected' : ''; ?>>Electric</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Transmission</label>
                                        <select name="transmission" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                                            <option value="manual" <?php echo($vehicle['transmission'] ?? '') == 'manual' ? 'selected' : ''; ?>>Manual</option>
                                            <option value="automatic" <?php echo($vehicle['transmission'] ?? '') == 'automatic' ? 'selected' : ''; ?>>Automatic</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Insurance Expiry</label>
                                        <input
                                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                            name="vehicle_insurance_expiry" type="date" value="<?php echo htmlspecialchars($vehicle['insurance_expiry'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Fleet Status</label>
                                        <select name="vehicle_status" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                                            <option value="available" <?php echo($vehicle['status'] ?? '') == 'available' ? 'selected' : ''; ?>>Online & Accepting Jobs</option>
                                            <option value="booked" <?php echo($vehicle['status'] ?? '') == 'booked' ? 'selected' : ''; ?>>On Active Trip</option>
                                            <option value="maintenance" <?php echo($vehicle['status'] ?? '') == 'maintenance' ? 'selected' : ''; ?>>In Maintenance</option>
                                            <option value="inactive" <?php echo($vehicle['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Offline / Personal Use</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-8">
                                    <h4 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                        <i class="ri-list-check text-emerald-600"></i> Features & Amenities
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        <?php
$features_list = ['Air Conditioning', 'GPS Navigation', 'USB Charging', 'First Aid Kit', 'WiFi Hotspot', 'Luggage Rack', 'Child Seat', 'Pet Friendly', 'Dashcam', 'Bluetooth Audio'];
$current_features = explode(',', $vehicle['features'] ?? '');
foreach ($features_list as $f):
    $checked = in_array($f, $current_features);
?>
                                            <label class="feature-tag relative">
                                                <input type="checkbox" name="features[]" value="<?php echo $f; ?>" class="hidden" <?php echo $checked ? 'checked' : ''; ?>>
                                                <span class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-medium rounded-full hover:border-emerald-500 transition-all">
                                                    <i class="ri-check-line text-xs"></i> <?php echo $f; ?>
                                                </span>
                                            </label>
                                        <?php
endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div id="documents-info" class="section-content bg-white rounded-2xl border border-gray-200 p-6">
                                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                    <div class="w-6 h-6 flex items-center justify-center"><i
                                            class="ri-file-shield-2-line text-emerald-600 text-base"></i></div>Documents
                                </h3>
                                <div class="space-y-4 mb-8">
                                    <?php
$docs = [
    ['key' => 'license_doc', 'label' => "Driver's License", 'expiry_key' => 'license_expiry', 'icon' => 'ri-file-user-line', 'color' => 'bg-emerald-50 text-emerald-600'],
    ['key' => 'registration_doc', 'label' => 'Vehicle Registration', 'expiry_key' => 'registration_expiry', 'icon' => 'ri-file-list-3-line', 'color' => 'bg-blue-50 text-blue-600'],
    ['key' => 'insurance_doc', 'label' => 'Insurance Certificate', 'expiry_key' => 'insurance_expiry', 'icon' => 'ri-file-shield-line', 'color' => 'bg-amber-50 text-amber-600'],
    ['key' => 'background_check_doc', 'label' => 'Background Check', 'expiry_key' => 'background_check_expiry', 'icon' => 'ri-file-shield-2-line', 'color' => 'bg-purple-50 text-purple-600']
];

foreach ($docs as $doc):
    $file = $user[$doc['key']] ?? '';
    $expiry = $user[$doc['expiry_key']] ?? '';
    $is_expiring = false;
    if ($expiry && strtotime($expiry) < strtotime('+30 days'))
        $is_expiring = true;
    if ($expiry && strtotime($expiry) < time())
        $status = 'Expired';
    else if ($is_expiring)
        $status = 'Expiring Soon';
    else if ($file)
        $status = 'Verified';
    else
        $status = 'Not Uploaded';

    $badge_class = 'bg-emerald-100 text-emerald-700';
    if ($status == 'Expiring Soon')
        $badge_class = 'bg-amber-100 text-amber-700';
    if ($status == 'Expired' || $status == 'Not Uploaded')
        $badge_class = 'bg-gray-100 text-gray-700';
?>
                                        <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-emerald-200 transition-all bg-gray-50/30 group">
                                            <div class="flex items-center gap-4">
                                                <div class="w-12 h-12 <?php echo $doc['color']; ?> rounded-xl flex items-center justify-center">
                                                    <i class="<?php echo $doc['icon']; ?> text-2xl"></i>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-bold text-gray-900"><?php echo $doc['label']; ?></h4>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo $expiry ? 'Expires: ' . date('M d, Y', strtotime($expiry)) : 'No expiry set'; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <span class="px-3 py-1 text-[10px] font-bold rounded-full uppercase <?php echo $badge_class; ?>">
                                                    <?php echo $status; ?>
                                                </span>
                                                <div class="flex items-center gap-2">
                                                    <?php if ($file): ?>
                                                        <a href="../assets/images/partners/<?php echo htmlspecialchars($file); ?>" target="_blank" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-emerald-600 transition-colors">
                                                            <i class="ri-eye-line text-lg"></i>
                                                        </a>
                                                    <?php
    endif; ?>
                                                    <label class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-teal-600 cursor-pointer transition-colors">
                                                        <input type="file" name="<?php echo $doc['key']; ?>" class="hidden">
                                                        <i class="ri-upload-2-line text-lg"></i>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-2 ml-16 flex items-center gap-4 group-hover:flex">
                                            <div class="flex flex-col">
                                                <label class="text-[10px] text-gray-400 font-medium">Set Expiry Date (Current: <?php echo $expiry ?: 'None'; ?>)</label>
                                                <input type="date" name="<?php echo $doc['expiry_key']; ?>" value="<?php echo $expiry; ?>" class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:ring-1 focus:ring-emerald-500 outline-none">
                                            </div>
                                        </div>
                                    <?php
endforeach; ?>
                                </div>

                                <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center bg-gray-50/50">
                                    <div class="w-12 h-12 bg-white rounded-full shadow-sm flex items-center justify-center mx-auto mb-3 text-gray-400 font-bold">
                                        <i class="ri-upload-cloud-2-line text-2xl"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-900">Upload New Document</h4>
                                    <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG up to 5MB</p>
                                </div>
                            </div>

                            <div id="support-info" class="section-content bg-white rounded-2xl border border-gray-200 p-6">
                                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                    <div class="w-6 h-6 flex items-center justify-center"><i
                                            class="ri-customer-service-2-line text-emerald-600 text-base"></i></div>Help &
                                    Support
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                    <div class="p-5 border border-emerald-100 bg-emerald-50/50 rounded-2xl">
                                        <h4 class="text-sm font-bold text-gray-900 mb-2">Need immediate help?</h4>
                                        <p class="text-xs text-gray-500 mb-4">Our partner support team is available 24/7 for urgent trip issues.</p>
                                        <a href="tel:+94112345678" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition-all">
                                            <i class="ri-phone-line"></i> Call Support
                                        </a>
                                    </div>
                                    <div class="p-5 border border-gray-100 bg-gray-50/50 rounded-2xl">
                                        <h4 class="text-sm font-bold text-gray-900 mb-2">Technical Support</h4>
                                        <p class="text-xs text-gray-500 mb-4">Found a bug or need help with your account? Raise a ticket.</p>
                                        <button type="button" onclick="document.getElementById('ticket-form-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 text-white text-xs font-bold rounded-xl hover:bg-gray-800 transition-all">
                                            <i class="ri-add-line"></i> New Ticket
                                        </button>
                                    </div>
                                </div>

                                <h4 class="text-sm font-bold text-gray-900 mb-4">Your Recent Tickets</h4>
                                <div class="space-y-3">
                                    <?php
$stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($tickets)): ?>
                                        <div class="text-center py-8 text-gray-400">
                                            <i class="ri-ticket-2-line text-3xl mb-2"></i>
                                            <p class="text-xs font-medium">No support tickets found.</p>
                                        </div>
                                    <?php
else:
    foreach ($tickets as $t):
        $status_class = $t['status'] === 'open' ? 'bg-amber-100 text-amber-700' : ($t['status'] === 'resolved' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700');
?>
                                        <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 bg-white">
                                            <div>
                                                <h5 class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($t['subject']); ?></h5>
                                                <p class="text-[10px] text-gray-500 mt-1">Ref: #TK-<?php echo $t['id']; ?> · <?php echo date('M d, Y', strtotime($t['created_at'])); ?></p>
                                            </div>
                                            <span class="px-2.5 py-1 text-[9px] font-black uppercase rounded-full <?php echo $status_class; ?>">
                                                <?php echo $t['status']; ?>
                                            </span>
                                        </div>
                                    <?php
    endforeach;
endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                                <button type="button" onclick="window.location.href='dashboard.php'"
                                    class="px-6 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors cursor-pointer whitespace-nowrap">Cancel</button>
                                <button type="submit"
                                    class="px-8 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-full hover:bg-emerald-700 transition-colors cursor-pointer whitespace-nowrap">Save
                                    Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Support Ticket Modal -->
            <div id="ticket-form-modal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm hidden">
                <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200">
                    <div class="bg-emerald-600 px-6 py-4 flex items-center justify-between">
                        <h4 class="text-lg font-bold text-white">Raise Support Ticket</h4>
                        <button type="button" onclick="document.getElementById('ticket-form-modal').classList.add('hidden')" class="text-white/80 hover:text-white transition-colors">
                            <i class="ri-close-line text-2xl"></i>
                        </button>
                    </div>
                    <form id="ticket-form" class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Subject</label>
                            <input type="text" id="ticket-subject" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none" placeholder="e.g. App crash on dashboard">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Priority</label>
                            <select id="ticket-priority" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Description</label>
                            <textarea id="ticket-description" required rows="4" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none" placeholder="Provide details about the issue..."></textarea>
                        </div>
                        <button type="submit" class="w-full py-3 bg-emerald-600 text-white text-sm font-black uppercase tracking-widest rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                            Submit Ticket
                        </button>
                    </form>
                </div>
            </div>

            <script>
                function showSection(sectionId, btn) {
                    // Hide all sections
                    document.querySelectorAll('.section-content').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Show target section
                    document.getElementById(sectionId).classList.add('active');
                    
                    // Update sidebar buttons
                    document.querySelectorAll('.sidebar-btn').forEach(b => {
                        b.classList.remove('active', 'bg-emerald-50', 'text-emerald-700');
                        b.classList.add('text-gray-600');
                    });
                    
                    btn.classList.add('active', 'bg-emerald-50', 'text-emerald-700');
                    btn.classList.remove('text-gray-600');
                }

                // Handle Ticket Submission
                document.getElementById('ticket-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const btn = e.target.querySelector('button');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Submitting...';

                    const data = {
                        subject: document.getElementById('ticket-subject').value,
                        priority: document.getElementById('ticket-priority').value,
                        description: document.getElementById('ticket-description').value
                    };

                    try {
                        const response = await fetch('../api/create_ticket.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data)
                        });

                        const result = await response.json();
                        if (result.success) {
                            alert('Support ticket raised successfully! Our team will get back to you soon.');
                            window.location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An unexpected error occurred.');
                    } finally {
                        btn.disabled = false;
                        btn.textContent = 'Submit Ticket';
                    }
                });
            </script>

            <footer class="bg-teal-800 text-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div>
                            <img alt="TripSync Logo" class="h-12 w-auto mb-4" src="../assets/images/logo.png">
                            <p class="text-teal-50 text-sm leading-relaxed">Your trusted partner for seamless travel
                                planning across Sri Lanka.</p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Quick Links</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="dashboard.php">Dashboard</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="active-trip.php">Active Trip</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="earnings.php">Earnings</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="messages.php">Messages</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Support</h3>
                            <ul class="space-y-2">
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Help Center</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Terms of Service</a></li>
                                <li><a class="text-teal-50 text-sm hover:text-white transition-colors cursor-pointer"
                                        href="#">Privacy Policy</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base mb-4">Connect With Us</h3>
                            <div class="flex gap-3 mb-4">
                                <a href="#"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-facebook-fill text-lg"></i></a>
                                <a href="#"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-instagram-line text-lg"></i></a>
                                <a href="#"
                                    class="w-9 h-9 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-colors cursor-pointer"><i
                                        class="ri-twitter-x-line text-lg"></i></a>
                            </div>
                            <p class="text-teal-50 text-sm">info@tripsync.lk</p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</body>

</html>

