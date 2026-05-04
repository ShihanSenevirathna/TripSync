<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['fullName']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $nic = clean($_POST['nic']);
    $address = clean($_POST['address']);
    $city = clean($_POST['city']);
    $password = $_POST['password'];

    $vehicleType = clean($_POST['vehicleType'] ?? 'sedan');
    $vehicleModel = clean($_POST['vehicleModel']);
    $vehicleYear = clean($_POST['vehicleYear']);
    $vehicleColor = clean($_POST['vehicleColor']);
    $registrationNumber = clean($_POST['registrationNumber']);
    $licenseNumber = clean($_POST['licenseNumber']);

    // Check if user exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "This email is already registered.";
    }
    else {
        $targetDir = "../assets/images/partners/";
        $licenseFile = "";
        $regFile = "";

        if (isset($_FILES['licenseDoc']) && $_FILES['licenseDoc']['error'] == 0) {
            $ext = pathinfo($_FILES['licenseDoc']['name'], PATHINFO_EXTENSION);
            $licenseFile = "license_" . time() . "_" . uniqid() . "." . $ext;
            if (!is_dir($targetDir))
                mkdir($targetDir, 0777, true);
            move_uploaded_file($_FILES['licenseDoc']['tmp_name'], $targetDir . $licenseFile);
        }

        if (isset($_FILES['regDoc']) && $_FILES['regDoc']['error'] == 0) {
            $ext = pathinfo($_FILES['regDoc']['name'], PATHINFO_EXTENSION);
            $regFile = "reg_" . time() . "_" . uniqid() . "." . $ext;
            if (!is_dir($targetDir))
                mkdir($targetDir, 0777, true);
            move_uploaded_file($_FILES['regDoc']['tmp_name'], $targetDir . $regFile);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $role = 'partner';
        $status = 'pending';

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, phone, address, nic_number, license_number, license_doc, registration_doc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", $name, $email, $hashedPassword, $role, $status, $phone, $address, $nic, $licenseNumber, $licenseFile, $regFile);
            $stmt->execute();
            $userId = $conn->insert_id;

            $capacity = 4;
            if ($vehicleType === 'suv')
                $capacity = 6;
            if ($vehicleType === 'van')
                $capacity = 12;
            if ($vehicleType === 'tuk-tuk')
                $capacity = 3;

            $vStmt = $conn->prepare("INSERT INTO vehicles (owner_id, type, model, year, color, reg_number, capacity, price_per_day, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $pricePerDay = 5000.00;
            $vStatus = 'available';
            $vStmt->bind_param("isssssids", $userId, $vehicleType, $vehicleModel, $vehicleYear, $vehicleColor, $registrationNumber, $capacity, $pricePerDay, $vStatus);
            $vStmt->execute();

            $conn->commit();
            $success = true;
        }
        catch (Exception $e) {
            $conn->rollback();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Partner - TripSync</title>
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .step-content { display: none; }
        .step-content.active { display: block; }
        .vehicle-type-btn.active { border-color: #0d9488; background-color: #f0fdfa; }
        .vehicle-type-btn.active i { color: #0d9488; }
    </style>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
</head>
<body class="bg-gray-50/50">
    <div id="root">
        <div class="min-h-screen">
            <nav id="navbar" class="fixed top-0 left-0 right-0 z-50 bg-white shadow-sm border-b border-gray-100">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <a class="flex items-center gap-3" href="../index.php">
                            <img alt="TripSync Logo" class="h-10 w-auto" src="../assets/images/logo.png">
                        </a>
                        <div class="hidden md:flex items-center gap-8">
                            <a class="nav-link text-sm font-medium transition-colors text-gray-600 hover:text-teal-600" href="../index.php">Home</a>
                            <a class="nav-link text-sm font-medium transition-colors text-gray-600 hover:text-teal-600" href="../login.php">Login</a>
                             <a class="nav-link text-sm font-medium bg-teal-600 text-white px-4 py-2 rounded-full hover:bg-teal-700 transition-colors" href="../register.php">Join as Traveler</a>
                        </div>
                    </div>
                </div>
            </nav>

            <section class="relative pt-16" id="registration-hero" <?php echo $success ? 'style="display:none"' : ''; ?>>
                <div class="relative h-64 overflow-hidden">
                    <img alt="Partner Registration" class="w-full h-full object-cover object-top" src="../assets/images/hero.jpg">
                    <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/40 to-black/60"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-center">
                        <div>
                            <div class="w-16 h-16 flex items-center justify-center bg-white/20 backdrop-blur-sm rounded-2xl mx-auto mb-4">
                                <i class="ri-shield-star-line text-3xl text-white"></i>
                            </div>
                            <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">Become a Verified Partner</h1>
                            <p class="text-white/80 text-base">Start your journey with Sri Lanka's leading travel network</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="max-w-3xl mx-auto px-4 <?php echo $success ? 'pt-24' : '-mt-8'; ?> relative z-10 pb-16">
                <!-- Stepper -->
                <div id="stepper" class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6" <?php echo $success ? 'style="display:none"' : ''; ?>>
                    <div class="flex items-center justify-between">
                        <?php
$steps = ['Personal Info', 'Vehicle Details', 'Documents', 'Review'];
foreach ($steps as $idx => $label):
    $stepNum = $idx + 1;
?>
                        <div class="flex items-center flex-1" id="step-indicator-<?php echo $stepNum; ?>">
                            <div class="flex flex-col items-center flex-1">
                                <div class="indicator-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all <?php echo $stepNum == 1 ? 'bg-teal-600 text-white ring-4 ring-teal-100' : 'bg-gray-100 text-gray-400'; ?>">
                                    <?php echo $stepNum; ?>
                                </div>
                                <span class="text-[10px] md:text-xs mt-2 font-medium whitespace-nowrap <?php echo $stepNum == 1 ? 'text-teal-700' : 'text-gray-400'; ?>"><?php echo $label; ?></span>
                            </div>
                            <?php if ($stepNum < 4): ?>
                                <div class="h-0.5 flex-1 mx-2 mt-[-20px] rounded-full bg-gray-200" id="line-<?php echo $stepNum; ?>"></div>
                            <?php
    endif; ?>
                        </div>
                        <?php
endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8" id="form-container">
                    <?php if ($error): ?>
                        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg flex items-center gap-3">
                            <i class="ri-error-warning-line text-xl"></i>
                            <p class="text-sm font-medium"><?php echo $error; ?></p>
                        </div>
                    <?php
endif; ?>

                    <form id="partnerForm" method="POST" enctype="multipart/form-data" <?php echo $success ? 'style="display:none"' : ''; ?>>
                        <input type="hidden" name="vehicleType" id="vehicleTypeInput" value="sedan">
                        
                        <!-- Step 1 -->
                        <div class="step-content active" id="step-1">
                            <h2 class="text-xl font-bold text-gray-900 mb-1">Personal Information</h2>
                            <p class="text-sm text-gray-500 mb-6">Tell us about yourself</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="fullName" type="text" placeholder="Full name as per NIC">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="phone" type="tel" placeholder="+94 7X XXX XXXX">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="email" type="email" placeholder="your@email.com">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="password" type="password" placeholder="Min 8 characters">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">NIC Number *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="nic" type="text" placeholder="NIC Number">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Address *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="address" type="text" placeholder="Your residential address">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">City *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="city" type="text" placeholder="Current city">
                                </div>
                            </div>
                            <div class="flex justify-end mt-8 pt-6 border-t border-gray-100">
                                <button type="button" onclick="nextStep(2)" class="px-8 py-3 bg-teal-600 text-white text-sm font-semibold rounded-full hover:bg-teal-700 transition-colors">Continue</button>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="step-content" id="step-2">
                            <h2 class="text-xl font-bold text-gray-900 mb-1">Vehicle Details</h2>
                            <p class="text-sm text-gray-500 mb-6">Details of the vehicle you will be using</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                                <?php
$vtips = [
    ['id' => 'sedan', 'label' => 'Sedan', 'cap' => '1-4', 'icon' => 'ri-taxi-line'],
    ['id' => 'suv', 'label' => 'SUV', 'cap' => '1-6', 'icon' => 'ri-car-line'],
    ['id' => 'van', 'label' => 'Van', 'cap' => '1-12', 'icon' => 'ri-bus-line'],
    ['id' => 'tuk-tuk', 'label' => 'Tuk-Tuk', 'cap' => '1-3', 'icon' => 'ri-motorbike-line']
];
foreach ($vtips as $vt): ?>
                                <button type="button" onclick="selectVehicle('<?php echo $vt['id']; ?>')" class="vehicle-type-btn p-3 rounded-xl border-2 transition-all <?php echo $vt['id'] == 'sedan' ? 'active' : 'border-gray-100'; ?>" id="vbtn-<?php echo $vt['id']; ?>">
                                    <i class="<?php echo $vt['icon']; ?> text-2xl text-gray-400 mb-1 block"></i>
                                    <p class="text-xs font-bold text-gray-700"><?php echo $vt['label']; ?></p>
                                    <p class="text-[9px] text-gray-400"><?php echo $vt['cap']; ?> People</p>
                                </button>
                                <?php
endforeach; ?>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Vehicle Model *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="vehicleModel" type="text" placeholder="e.g. Toyota KDH">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Manufacture Year *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="vehicleYear" type="number" placeholder="2020">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Registration Number *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="registrationNumber" type="text" placeholder="WP-CAB-1234">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">License Number *</label>
                                    <input required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="licenseNumber" type="text" placeholder="Driving License #">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Vehicle Color</label>
                                    <input class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm" name="vehicleColor" type="text" placeholder="White, Silver, etc.">
                                </div>
                            </div>
                            <div class="flex justify-between mt-8 pt-6 border-t border-gray-100">
                                <button type="button" onclick="prevStep(1)" class="px-6 py-3 border border-gray-200 text-gray-600 text-sm font-medium rounded-full hover:bg-gray-50">Back</button>
                                <button type="button" onclick="nextStep(3)" class="px-8 py-3 bg-teal-600 text-white text-sm font-semibold rounded-full hover:bg-teal-700 transition-colors">Continue</button>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="step-content" id="step-3">
                            <h2 class="text-xl font-bold text-gray-900 mb-1">Document Upload</h2>
                            <p class="text-sm text-gray-500 mb-6">Proof of identity and vehicle registration</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3"><i class="ri-file-text-line mr-1"></i>Driver's License *</label>
                                    <label class="block border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer border-gray-300 hover:border-teal-400 hover:bg-teal-50/30 transition-all">
                                        <input required accept=".jpg,.jpeg,.png,.pdf" class="hidden" type="file" name="licenseDoc" onchange="updateFileName(this, 'license-preview')">
                                        <i class="ri-upload-cloud-2-line text-3xl text-gray-400 mb-2 block"></i>
                                        <p class="text-sm font-medium text-gray-700" id="license-preview">Upload License</p>
                                    </label>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3"><i class="ri-car-line mr-1"></i>Vehicle Book *</label>
                                    <label class="block border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer border-gray-300 hover:border-teal-400 hover:bg-teal-50/30 transition-all">
                                        <input required accept=".jpg,.jpeg,.png,.pdf" class="hidden" type="file" name="regDoc" onchange="updateFileName(this, 'reg-preview')">
                                        <i class="ri-upload-cloud-2-line text-3xl text-gray-400 mb-2 block"></i>
                                        <p class="text-sm font-medium text-gray-700" id="reg-preview">Upload RC Book</p>
                                    </label>
                                </div>
                            </div>
                            <div class="flex justify-between mt-8 pt-6 border-t border-gray-100">
                                <button type="button" onclick="prevStep(2)" class="px-6 py-3 border border-gray-200 text-gray-600 text-sm font-medium rounded-full hover:bg-gray-50">Back</button>
                                <button type="button" onclick="nextStep(4)" class="px-8 py-3 bg-teal-600 text-white text-sm font-semibold rounded-full hover:bg-teal-700 transition-colors">Continue</button>
                            </div>
                        </div>

                        <!-- Step 4 -->
                        <div class="step-content" id="step-4">
                            <h2 class="text-xl font-bold text-gray-900 mb-1">Final Review</h2>
                            <p class="text-sm text-gray-500 mb-6">Please check your details before submitting</p>
                            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl mb-6">
                                <p class="text-xs text-amber-700">By clicking submit, you confirm that all information provided is accurate and you agree to our partner terms and conditions.</p>
                            </div>
                            <div class="flex justify-between mt-8 pt-6 border-t border-gray-100">
                                <button type="button" onclick="prevStep(3)" class="px-6 py-3 border border-gray-200 text-gray-600 text-sm font-medium rounded-full hover:bg-gray-50">Back</button>
                                <button type="submit" class="px-10 py-3 bg-emerald-600 text-white text-sm font-bold rounded-full hover:bg-emerald-700 transition-all shadow-lg hover:shadow-emerald-600/20">Submit Application</button>
                            </div>
                        </div>
                    </form>

                    <!-- Success Step 5 -->
                    <div class="step-content <?php echo $success ? 'active' : ''; ?>" id="step-5">
                        <div class="text-center py-10">
                            <div class="w-20 h-20 flex items-center justify-center bg-emerald-100 rounded-full mx-auto mb-6">
                                <i class="ri-check-double-fill text-4xl text-emerald-600"></i>
                            </div>
                            <h1 class="text-3xl font-bold text-gray-900 mb-3">Application Received</h1>
                            <p class="text-gray-600 mb-8 max-w-sm mx-auto text-sm">Thank you for applying. Our verification team will review your documents and contact you within 2 working days.</p>
                            <a href="../index.php" class="px-8 py-3 bg-teal-600 text-white font-bold rounded-full hover:bg-teal-700 transition-colors">Return Home</a>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="bg-gray-900 text-white py-12">
                <div class="max-w-7xl mx-auto px-4 text-center">
                    <img src="../assets/images/logo.png" alt="TripSync" class="h-10 mx-auto mb-4 opacity-50">
                    <p class="text-gray-500 text-sm">&copy; <?php echo date('Y'); ?> TripSync. All rights reserved.</p>
                </div>
            </footer>
        </div>
    </div>

    <script>
        let currentStep = 1;
        function updateStepper(step) {
            for (let i = 1; i <= 4; i++) {
                const indicator = document.getElementById(`step-indicator-${i}`);
                if (!indicator) continue;
                const circle = indicator.querySelector('.indicator-circle');
                const text = indicator.querySelector('span');
                const line = document.getElementById(`line-${i}`);

                if (i < step) {
                    circle.innerHTML = '<i class="ri-check-line text-lg"></i>';
                    circle.className = 'indicator-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold bg-emerald-500 text-white';
                    text.className = 'text-[10px] md:text-xs mt-2 font-medium text-teal-700';
                    if (line) line.className = 'h-0.5 flex-1 mx-2 mt-[-20px] rounded-full bg-emerald-500';
                } else if (i === step) {
                    circle.innerHTML = i;
                    circle.className = 'indicator-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold bg-teal-600 text-white ring-4 ring-teal-100';
                    text.className = 'text-[10px] md:text-xs mt-2 font-medium text-teal-700';
                    if (line) line.className = 'h-0.5 flex-1 mx-2 mt-[-20px] rounded-full bg-gray-200';
                } else {
                    circle.innerHTML = i;
                    circle.className = 'indicator-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold bg-gray-100 text-gray-400';
                    text.className = 'text-[10px] md:text-xs mt-2 font-medium text-gray-400';
                    if (line) line.className = 'h-0.5 flex-1 mx-2 mt-[-20px] rounded-full bg-gray-200';
                }
            }
        }

        function nextStep(step) {
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.getElementById(`step-${step}`).classList.add('active');
            updateStepper(step);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function prevStep(step) {
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.getElementById(`step-${step}`).classList.add('active');
            updateStepper(step);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function selectVehicle(type) {
            document.getElementById('vehicleTypeInput').value = type;
            document.querySelectorAll('.vehicle-type-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('border-gray-100');
            });
            document.getElementById('vbtn-' + type).classList.add('active');
            document.getElementById('vbtn-' + type).classList.remove('border-gray-100');
        }
        function updateFileName(input, targetId) {
            const fileName = input.files[0] ? input.files[0].name : (targetId === 'license-preview' ? 'Upload License' : 'Upload RC Book');
            const target = document.getElementById(targetId);
            target.textContent = fileName;
            target.classList.add('text-teal-600', 'font-bold');
        }
    </script>
</body>
</html>
