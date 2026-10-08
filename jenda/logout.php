<?php
/**
 * Logout Handler for T. A. Bajaba Ministries
 * This script securely clears all session data and redirects the user.
 */

require_once 'config.php';

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will completely destroy the session, not just the data within it.
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

// Finally, destroy the session.
session_destroy();

// Redirect to the login page
header("Location: /");
exit();
?>