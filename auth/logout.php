<?php
/**
 * Authentication - Logout Handler
 */

require_once __DIR__ . '/../includes/functions.php';

// Unset all session variables
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start a fresh session to hold the flash message
session_name(SESSION_NAME);
session_start();

flash('info', 'You have been logged out successfully.');
redirect('auth/login.php');
