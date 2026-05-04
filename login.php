<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth_check.php';

// Redirect if already logged in
redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email']);
    $password = $_POST['password']; // Don't clean passwords
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf_token)) {
        $error = "Invalid security token. Please try again.";
    }
    elseif (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    }
    else {
        $stmt = $conn->prepare("SELECT id, name, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'active' || ($user['role'] === 'partner' && $user['status'] === 'pending')) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_status'] = $user['status'];

                    // Fleet Mapping: Link partner to their vehicle in session
                    if ($user['role'] === 'partner') {
                        $vstmt = $conn->prepare("SELECT id FROM vehicles WHERE owner_id = ?");
                        $vstmt->bind_param("i", $user['id']);
                        $vstmt->execute();
                        $vresult = $vstmt->get_result();
                        if ($vehicle = $vresult->fetch_assoc()) {
                            $_SESSION['vehicle_id'] = $vehicle['id'];
                        }
                        $vstmt->close();
                    }

                    // Redirect based on role
                    handleUnauthorized();
                }
                else {
                    $error = "Your account is currently " . $user['status'] . ". Please contact support.";
                }
            }
            else {
                $error = "Invalid email or password.";
            }
        }
        else {
            $error = "Invalid email or password.";
        }
        $stmt->close();
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TripSync</title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .login-bg {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.6)), url('assets/images/hero.jpg');
            background-size: cover;
            background-position: center;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="min-h-screen flex items-center justify-center login-bg px-4 py-12">
        <div class="max-w-md w-full glass-card rounded-3xl shadow-2xl overflow-hidden border border-white/20">
            <div class="px-8 pt-10 pb-4 text-center">
                <a href="index.php" class="inline-block mb-6">
                    <img src="assets/images/logo.png" alt="TripSync Logo" class="h-12 w-auto mx-auto">
                </a>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h2>
                <p class="text-gray-500">Log in to manage your Sri Lankan adventure</p>
            </div>

            <div class="px-8 py-6">
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-lg">
                        <div class="flex items-center">
                            <i class="ri-error-warning-line text-lg mr-2"></i>
                            <p><?php echo $error; ?></p>
                        </div>
                    </div>
                <?php
endif; ?>

                <?php displayFlash(); ?>

                <form action="login.php" method="POST" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-mail-line text-gray-400"></i>
                            </div>
                            <input type="email" id="email" name="email" required
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all outline-none text-sm"
                                placeholder="name@example.com" value="<?php echo isset($email) ? clean($email) : ''; ?>">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-semibold text-gray-700">Password</label>
                            <a href="#" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-lock-2-line text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all outline-none text-sm"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox"
                            class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded cursor-pointer">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-600 cursor-pointer selezion-none">
                            Remember me
                        </label>
                    </div>

                    <button type="submit"
                        class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-teal-600/20 transform active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                        <span>Log In</span>
                        <i class="ri-arrow-right-line"></i>
                    </button>
                </form>

                <div class="mt-8 text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <a href="register.php" class="font-bold text-teal-600 hover:text-teal-700">Create Account</a>
                    </p>
                </div>
            </div>

            <div class="px-8 py-6 bg-gray-50 border-t border-gray-100 text-center">
                <p class="text-xs text-gray-400">
                    Are you a partner? <a href="partner/register.php" class="text-teal-500 hover:underline">Apply here</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
