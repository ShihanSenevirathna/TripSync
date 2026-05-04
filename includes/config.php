<?php
/**
 * TripSync Database Configuration
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '20051212');
define('DB_NAME', 'tripsync_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to utf8mb4
$conn->set_charset("utf8mb4");

// Define base URL for the project
define('BASE_URL', 'http://localhost/TripSync/');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// External API Keys
define('GOOGLE_MAPS_API_KEY', 'AIzaSyD8G_jalAMR1Q3SMI7jpUYW6_xnv3h2Gs0');
define('AMADEUS_API_KEY', 'CutBd41xVXxqAxx039wpU2N7RGpMfnFg');
define('AMADEUS_API_SECRET', 'ENUzBANvyEUwcU2n');
define('AMADEUS_MODE', 'test'); // 'test' or 'production'
define('PAYHERE_MERCHANT_ID', '1232290');
define('PAYHERE_SECRET', 'NTIwNTI5NDY0MTQ1MDIwNzg3MzI0NzQ3NzQ5NTExMzU0OTQ3NjY3');
define('PAYHERE_MODE', 'sandbox'); // Set to 'live' for production



// Include utility functions
require_once __DIR__ . '/functions.php';
