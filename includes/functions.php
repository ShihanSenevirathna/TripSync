<?php
/**
 * TripSync Utility Functions
 */

/**
 * Sanitize user input for HTML output
 * 
 * @param string $data
 * @return string
 */
function clean($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a random CSRF token and store it in session
 * 
 * @return string
 */
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token)
{
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

/**
 * Set a flash message for the next request
 * 
 * @param string $type success, error, info, warning
 * @param string $message
 */
function setFlash($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display and clear the flash message if it exists
 */
function displayFlash()
{
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];

        $bgColor = 'bg-blue-100 border-blue-400 text-blue-700';
        if ($type === 'success')
            $bgColor = 'bg-green-100 border-green-400 text-green-700';
        if ($type === 'error')
            $bgColor = 'bg-red-100 border-red-400 text-red-700';
        if ($type === 'warning')
            $bgColor = 'bg-yellow-100 border-yellow-400 text-yellow-700';

        echo "<div class='p-4 mb-4 text-sm rounded-lg {$bgColor} border' role='alert'>
                {$message}
              </div>";

        unset($_SESSION['flash']);
    }
}

/**
 * Format currency to Sri Lankan Rupees (LKR)
 * 
 * @param float $amount
 * @return string
 */
function formatCurrency($amount)
{
    return "LKR " . number_format($amount, 0);
}

/**
 * Get the profile picture path with fallback logic
 * 
 * @param string|null $filename
 * @param string $prefix Prefix for relative paths (e.g., '../' or '')
 * @return string
 */
function getProfilePic($filename, $prefix = '')
{
    $default = 'default_profile_pic.png';
    // Use realpath to ensure we have the correct base directory
    $basePath = realpath(dirname(__DIR__) . '/assets/images');

    if (!$basePath) {
        // Fallback if assets folder moves or isn't found
        return $prefix . 'assets/images/' . $default;
    }

    if (empty($filename) || $filename === 'default-avatar.jpg') {
        return $prefix . 'assets/images/' . $default;
    }

    // Get only the filename in case the DB has paths
    $pureName = basename($filename);

    // 1. Check root assets/images/
    if (file_exists($basePath . DIRECTORY_SEPARATOR . $pureName)) {
        return $prefix . 'assets/images/' . $pureName;
    }

    // 2. Check assets/images/partners/
    if (file_exists($basePath . DIRECTORY_SEPARATOR . 'partners' . DIRECTORY_SEPARATOR . $pureName)) {
        return $prefix . 'assets/images/partners/' . $pureName;
    }

    // 3. Fallback to default
    return $prefix . 'assets/images/' . $default;
}

require_once __DIR__ . '/../api/google_places_api.php';

/**
 * Fetch live hotels from Google Places API
 * 
 * @param string $location
 * @param int $limit
 * @return array
 */
function getLiveHotels($location = 'Colombo', $limit = 6)
{
    try {
        $api = new GooglePlacesAPI();
        return array_slice($api->searchHotels($location), 0, $limit);
    }
    catch (Exception $e) {
        error_log("Google Live Hotels Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Send a push notification using Firebase Cloud Messaging (FCM)
 * 
 * @param int $user_id
 * @param string $title
 * @param string $body
 * @param array $data Additional data payload
 * @return bool
 */
function sendFCMNotification($user_id, $title, $body, $data = [])
{
    global $conn;

    // 1. Fetch user's FCM token
    $stmt = $conn->prepare("SELECT fcm_token FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || empty($user['fcm_token'])) {
        return false; // No token, cannot send push
    }

    $token = $user['fcm_token'];

    // 2. Prepare FCM payload (HTTP v1 format)
    // NOTE: This requires a Google Service Account JSON and an OAuth2 token.
    // For this implementation, we'll log the attempt and provide the structure.

    $payload = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'data' => array_merge($data, [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'status' => 'done'
            ])
        ]
    ];

    // Log the notification for debugging/audit
    $log_msg = "[" . date('Y-m-d H:i:s') . "] FCM to User #$user_id: $title - $body\n";
    file_put_contents(__DIR__ . '/../logs/fcm_notifications.log', $log_msg, FILE_APPEND);

    /* 
     REAL IMPLEMENTATION STEPS:
     1. Get Access Token using Service Account JSON (using Google Auth Library)
     2. POST to https://fcm.googleapis.com/v1/projects/{YOUR_PROJECT_ID}/messages:send
     3. Use Bearer token in Header
     */

    return true;

}
/**
 * Convert a timestamp to a human-readable "time ago" string
 * 
 * @param string $datetime
 * @param bool $full
 * @return string
 */
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        $val = $diff->$k ?? null;
        if ($k == 'w') $val = $weeks;
        if ($k == 'd') $val = $days;

        if ($val) {
            $v = $val . ' ' . $v . ($val > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full)
        $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
