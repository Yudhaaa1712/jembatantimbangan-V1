<?php
// modules/auth/logout.php
require_once '../../config/database.php';

// Unset all session variables
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session with error handling
try {
    session_destroy();
} catch (Exception $e) {
    error_log("Session destroy failed: " . $e->getMessage());
}

// Clear session ID
session_id('');

// Redirect to login page
header('Location: ' . BASE_URL . 'modules/auth/login.php');
exit;
?>