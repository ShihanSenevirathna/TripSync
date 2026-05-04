<?php
/**
 * TripSync Authentication Middleware
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the user is logged in
 * 
 * @param string|array|null $requiredRole Optional role(s) required to access the page
 * @return bool
 */
function checkAuth($requiredRole = null)
{
    // Check if user session exists
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }

    // Check if role requirements are met
    if ($requiredRole !== null) {
        $userRole = $_SESSION['user_role'];

        if (is_array($requiredRole)) {
            if (!in_array($userRole, $requiredRole)) {
                handleUnauthorized();
            }
        }
        else {
            if ($userRole !== $requiredRole) {
                handleUnauthorized();
            }
        }

        // Additional Status Check for Gated Access
        require_once 'config.php';
        global $conn;
        $stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $userData = $stmt->get_result()->fetch_assoc();

        if ($userData) {
            $userStatus = $userData['status'];
            $_SESSION['user_status'] = $userStatus;

            // Prevent recursive redirect if already on status/logout pages
            $currentFile = basename($_SERVER['PHP_SELF']);
            $restrictedPages = ['pending_verification.php', 'logout.php', 'index.php'];

            if (!in_array($currentFile, $restrictedPages) && $userStatus === 'pending' && $userRole === 'partner') {
                header("Location: " . BASE_URL . "partner/pending_verification.php");
                exit();
            }
        }
    }

    return true;
}

/**
 * Handle unauthorized access attempts
 */
function handleUnauthorized()
{
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';

    switch ($role) {
        case 'admin':
            header("Location: " . BASE_URL . "admin/dashboard.php");
            break;
        case 'partner':
            header("Location: " . BASE_URL . "partner/dashboard.php");
            break;
        case 'customer':
            header("Location: " . BASE_URL . "customer/dashboard.php");
            break;
        default:
            header("Location: " . BASE_URL . "login.php");
            break;
    }
    exit();
}

/**
 * Redirect logged in users away from login/register pages
 */
function redirectIfLoggedIn()
{
    if (isset($_SESSION['user_id'])) {
        handleUnauthorized();
    }
}
