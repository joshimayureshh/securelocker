<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log activity if user was logged in
if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}

// Clear session data
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params["path"],
        'domain' => $params["domain"],
        'secure' => $params["secure"],
        'httponly' => $params["httponly"],
        'samesite' => $params['samesite'] ?? 'Lax'
    ]);
}

session_destroy();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Location: login.php");
exit();