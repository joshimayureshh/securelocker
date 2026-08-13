<?php
// delete_account.php - Permanent user profile & data deletion
require_once 'config.php';
requireLogin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?section=profile');
    exit();
}

// Validate CSRF token
if (!validateCSRFToken()) {
    $_SESSION['error'] = "Security token expired. Please try again.";
    header('Location: dashboard.php?section=profile');
    exit();
}

// Check if this is a deletion request
if (!isset($_POST['delete_account'])) {
    header('Location: dashboard.php?section=profile');
    exit();
}

$user_id = $_SESSION['user_id'];
$password = $_POST['confirm_delete_password'] ?? '';
$db = getDB();

try {
    // Verify password
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    
    if (!$user_data) {
        $_SESSION['error'] = "User not found.";
        header('Location: dashboard.php?section=profile');
        exit();
    }
    
    if (!password_verify($password, $user_data['password_hash'])) {
        $_SESSION['error'] = "Incorrect password. Profile deletion cancelled.";
        header('Location: dashboard.php?section=profile');
        exit();
    }
    
    // 1. Get and delete all physical encrypted files from disk
    $stmt = $db->prepare("SELECT file_path FROM files WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $files = $stmt->fetchAll();
    
    $file_count = 0;
    foreach ($files as $file) {
        if (!empty($file['file_path']) && file_exists($file['file_path'])) {
            if (@unlink($file['file_path'])) {
                $file_count++;
            }
        }
    }
    
    // 2. Explicitly remove all records from database tables
    $db->prepare("DELETE FROM files WHERE user_id = ?")->execute([$user_id]);
    $db->prepare("DELETE FROM activities WHERE user_id = ?")->execute([$user_id]);
    $result = $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    
    if (!$result) {
        throw new Exception("Failed to delete user profile from database");
    }
    
    // 3. Clear and destroy session
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => 'Lax'
        ]);
    }
    
    session_destroy();
    
    // 4. Redirect to goodbye confirmation page
    header('Location: goodbye.php');
    exit();
    
} catch (PDOException $e) {
    error_log("Profile deletion PDO error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred while deleting profile.";
    header('Location: dashboard.php?section=profile');
    exit();
} catch (Exception $e) {
    error_log("Profile deletion error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to delete profile: " . $e->getMessage();
    header('Location: dashboard.php?section=profile');
    exit();
}
?>