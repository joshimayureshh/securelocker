<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'secure_locker');
define('DB_USER', 'postgres');
define('DB_PASS', '1234');

// File upload configuration
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
define('UPLOAD_DIR', __DIR__ . '/uploads');

// Gmail SMTP Configuration for Password Recovery OTP
define('SMTP_ENABLED', true); // Set to TRUE after adding your Gmail App Password
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'joshimayu62@gmail.com'); // Put your real Gmail address here
define('SMTP_PASS', 'liqb epsa zpty pqgc'); // Put your 16-character Google App Password here
define('SMTP_FROM_EMAIL', 'joshimayu62@gmail.com');
define('SMTP_FROM_NAME', 'Secure Locker');

// Linux-optimized settings
define('UPLOAD_DIR_PERMISSIONS', 0755); // Secure permissions for Linux
define('LOG_FILE', __DIR__ . '/logs/app.log');

// Create uploads directory if it doesn't exist with proper permissions
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, UPLOAD_DIR_PERMISSIONS, true);
    chmod(UPLOAD_DIR, UPLOAD_DIR_PERMISSIONS);
}

// Create logs directory
$log_dir = dirname(LOG_FILE);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Start session with long lifetime
session_name('SECURE_LOCKER');
session_set_cookie_params([
    'lifetime' => 86400 * 30, // 30 days
    'path' => '/',
    'secure' => false, // Set to true if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection with Linux-optimized settings
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // Better for PostgreSQL
                PDO::ATTR_PERSISTENT => true // Connection pooling for better performance
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    return $db;
}

// Simple login check
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    return true;
}

// Sanitize input
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Get file icon (Crisp Modern Vector SVG)
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $svgIcons = [
        'image' => '<svg class="file-svg-icon icon-image" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3" ry="3"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>',
        'pdf' => '<svg class="file-svg-icon icon-pdf" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
        'doc' => '<svg class="file-svg-icon icon-doc" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><line x1="10" y1="9" x2="8" y2="9"></line></svg>',
        'sheet' => '<svg class="file-svg-icon icon-sheet" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="12" x2="16" y2="12"></line><line x1="8" y1="16" x2="16" y2="16"></line><line x1="12" y1="12" x2="12" y2="18"></line></svg>',
        'archive' => '<svg class="file-svg-icon icon-archive" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>',
        'video' => '<svg class="file-svg-icon icon-video" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>',
        'audio' => '<svg class="file-svg-icon icon-audio" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>',
        'code' => '<svg class="file-svg-icon icon-code" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
        'default' => '<svg class="file-svg-icon icon-default" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>'
    ];

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'])) return $svgIcons['image'];
    if ($ext === 'pdf') return $svgIcons['pdf'];
    if (in_array($ext, ['doc', 'docx', 'txt', 'rtf', 'odt', 'pages', 'md'])) return $svgIcons['doc'];
    if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods', 'numbers'])) return $svgIcons['sheet'];
    if (in_array($ext, ['zip', 'rar', 'tar', 'gz', '7z', 'bz2', 'iso'])) return $svgIcons['archive'];
    if (in_array($ext, ['mp4', 'avi', 'mov', 'mkv', 'webm', 'wmv', 'flv'])) return $svgIcons['video'];
    if (in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a'])) return $svgIcons['audio'];
    if (in_array($ext, ['html', 'css', 'js', 'php', 'json', 'sql', 'py', 'java', 'c', 'cpp', 'sh'])) return $svgIcons['code'];
    
    return $svgIcons['default'];
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 B";
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Log activity
function logActivity($user_id, $type, $description) {
    $log_entry = date('Y-m-d H:i:s') . " | User: $user_id | Type: $type | $description" . PHP_EOL;
    error_log($log_entry, 3, LOG_FILE);
    
    // Also log to database if table exists
    $db = getDB();
    try {
        // Check if activities table exists
        $stmt = $db->prepare("SELECT to_regclass('activities')");
        $stmt->execute();
        $tableExists = $stmt->fetchColumn();
        
        if ($tableExists) {
            $stmt = $db->prepare("INSERT INTO activities (user_id, activity_type, description) VALUES (?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->execute([$user_id, $type, $description]);
        }
    } catch (Exception $e) {
        // Silent fail - don't break if logging fails
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Delete user account and all associated data
function deleteUserAccount($user_id) {
    $db = getDB();
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Get all files for the user to delete physical files
        $stmt = $db->prepare("SELECT file_path FROM files WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $files = $stmt->fetchAll();
        
        // Delete physical files from server
        $file_count = 0;
        foreach ($files as $file) {
            if (!empty($file['file_path']) && file_exists($file['file_path'])) {
                if (unlink($file['file_path'])) {
                    $file_count++;
                }
            }
        }
        
        // Delete user's activities first (optional, but safe)
        $stmt = $db->prepare("DELETE FROM activities WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        
        // Delete user's files records
        $stmt = $db->prepare("DELETE FROM files WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        
        // Finally delete the user
        $stmt = $db->prepare("DELETE FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        
        // Commit transaction
        $db->commit();
        
        return ['success' => true, 'message' => "Account deleted successfully. Removed $file_count files."];
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->rollBack();
        error_log("Account deletion error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete account: ' . $e->getMessage()];
    }
}

// Check if running on Linux
function isLinux() {
    return strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN';
}

// Get system info for Linux optimization
function getSystemInfo() {
    if (isLinux()) {
        return [
            'os' => php_uname(),
            'memory' => shell_exec('free -h'),
            'disk' => shell_exec('df -h ' . UPLOAD_DIR)
        ];
    }
    return null;
}