<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();
require_once 'config.php';
requireLogin();

$db = getDB();
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    /* ===== TOGGLE FAVORITE ===== */
    if ($action === 'toggle_favorite') {
        header('Content-Type: application/json');
        
        // Accept both POST and GET for flexibility
        $file_id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$file_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
            exit;
        }

        // PostgreSQL's RETURNING clause
        $stmt = $db->prepare("
            UPDATE files 
            SET is_favorite = NOT is_favorite 
            WHERE id = :id AND user_id = :user_id
            RETURNING is_favorite
        ");
        $stmt->execute([':id' => $file_id, ':user_id' => $user_id]);
        $result = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'is_favorite' => $result['is_favorite'] ?? false
        ]);
        exit;
    }

    /* ===== DOWNLOAD FILE ===== */
    if ($action === 'download') {
        $file_id = intval($_GET['id'] ?? 0);
        if (!$file_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No file ID']);
            exit;
        }

        // Get file data including encryption keys
        $stmt = $db->prepare("
            SELECT file_name, file_type, file_path, encryption_key, iv 
            FROM files 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $file_id, ':user_id' => $user_id]);
        $file = $stmt->fetch();

        if (!$file || !file_exists($file['file_path'])) {
            // For download, we should show error page, not JSON
            header('Content-Type: text/html');
            die("File not found.");
        }

        $encrypted_content = file_get_contents($file['file_path']);
        
        // If file is encrypted (has key and iv)
        if (!empty($file['encryption_key']) && !empty($file['iv'])) {
            $key = hex2bin($file['encryption_key']);
            $iv = hex2bin($file['iv']);
            
            $decrypted = openssl_decrypt(
                $encrypted_content,
                'aes-256-cbc',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                header('Content-Type: text/html');
                die("Decryption failed.");
            }
        } else {
            // File is not encrypted (for backward compatibility)
            $decrypted = $encrypted_content;
        }

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set proper headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
        header('Content-Length: ' . strlen($decrypted));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        
        echo $decrypted;
        exit;
    }
    
    /* ===== VIEW FILE ===== */
    if ($action === 'view') {
        $file_id = intval($_GET['id'] ?? 0);
        if (!$file_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No file ID']);
            exit;
        }

        // Get file data including encryption keys
        $stmt = $db->prepare("
            SELECT file_name, file_type, file_path, encryption_key, iv 
            FROM files 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $file_id, ':user_id' => $user_id]);
        $file = $stmt->fetch();

        if (!$file || !file_exists($file['file_path'])) {
            // Clear output buffer and show error
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: text/html; charset=utf-8');
            die("File not found.");
        }

        // Read encrypted file
        $encrypted_content = file_get_contents($file['file_path']);
        if ($encrypted_content === false) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: text/html; charset=utf-8');
            die("Failed to read file.");
        }
        
        // Decrypt if encrypted
        if (!empty($file['encryption_key']) && !empty($file['iv'])) {
            $key = hex2bin($file['encryption_key']);
            $iv = hex2bin($file['iv']);
            
            $decrypted = openssl_decrypt(
                $encrypted_content,
                'aes-256-cbc',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Content-Type: text/html; charset=utf-8');
                die("Decryption failed.");
            }
        } else {
            $decrypted = $encrypted_content;
        }

        // Clear ALL output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Comprehensive MIME type mapping
        $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
        $mime_map = [
            // Video
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            // Audio
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',
            'flac' => 'audio/flac',
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            // Documents & Code
            'pdf' => 'application/pdf',
            'txt' => 'text/plain; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'php' => 'text/plain; charset=utf-8',
            'py' => 'text/plain; charset=utf-8',
            'sql' => 'text/plain; charset=utf-8',
            'md' => 'text/plain; charset=utf-8',
            'csv' => 'text/plain; charset=utf-8'
        ];
        
        $content_type = $mime_map[$ext] ?? $file['file_type'] ?? 'application/octet-stream';
        $is_viewable = isset($mime_map[$ext]) || strpos($content_type, 'image/') === 0 || strpos($content_type, 'video/') === 0 || strpos($content_type, 'audio/') === 0 || $content_type === 'application/pdf' || strpos($content_type, 'text/') === 0;
        
        if ($is_viewable) {
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . addslashes($file['file_name']) . '"');
            header('Accept-Ranges: bytes');
            header('Content-Length: ' . strlen($decrypted));
            header('Cache-Control: private, max-age=3600');
        } else {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . addslashes($file['file_name']) . '"');
            header('Content-Length: ' . strlen($decrypted));
            header('Cache-Control: no-cache, must-revalidate');
        }
        
        header('Pragma: public');
        header('Expires: 0');
        
        // Output the decrypted file stream
        echo $decrypted;
        exit;
    }

    /* ===== GET RECYCLED FILES ===== */
    if ($action === 'get_recycled_files') {
        header('Content-Type: application/json');
        $stmt = $db->prepare("
            SELECT id, file_name, file_type, file_size, uploaded_at, deleted_at
            FROM files
            WHERE user_id = :user_id AND deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add formatted fields
        foreach ($files as &$f) {
            $f['formatted_size'] = formatFileSize($f['file_size']);
            $f['formatted_deleted'] = date('M d, Y h:i A', strtotime($f['deleted_at']));
            $f['file_icon'] = getFileIcon($f['file_name']);
        }
        
        echo json_encode([
            'success' => true,
            'count' => count($files),
            'files' => $files
        ]);
        exit;
    }

    /* ===== RESTORE FILE (RECOVER) ===== */
    if ($action === 'restore_file') {
        header('Content-Type: application/json');
        $file_id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$file_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE files 
            SET deleted_at = NULL 
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NOT NULL
        ");
        $stmt->execute([':id' => $file_id, ':user_id' => $user_id]);
        
        if (function_exists('logActivity')) {
            logActivity($user_id, 'restore', 'Restored file ID: ' . $file_id);
        }

        echo json_encode([
            'success' => true,
            'message' => 'File recovered successfully!',
            'id' => $file_id
        ]);
        exit;
    }

    /* ===== PERMANENT DELETE FILE ===== */
    if ($action === 'permanent_delete_file') {
        header('Content-Type: application/json');
        $file_id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$file_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
            exit;
        }

        $stmt = $db->prepare("SELECT file_path, file_name FROM files WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $file_id, ':user_id' => $user_id]);
        $file = $stmt->fetch();

        if ($file) {
            if (!empty($file['file_path']) && file_exists($file['file_path'])) {
                @unlink($file['file_path']);
            }
            $stmt = $db->prepare("DELETE FROM files WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $file_id, ':user_id' => $user_id]);
            
            if (function_exists('logActivity')) {
                logActivity($user_id, 'permanent_delete', 'Permanently deleted file: ' . $file['file_name']);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'File permanently deleted',
            'id' => $file_id
        ]);
        exit;
    }

    /* ===== RENAME FILE ===== */
    if ($action === 'rename_file') {
        header('Content-Type: application/json');
        $file_id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        $new_name = trim($_POST['new_name'] ?? $_GET['new_name'] ?? '');

        if (!$file_id || empty($new_name)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid file name']);
            exit;
        }

        // Sanitize filename
        $new_name = preg_replace('/[\\/\\\\:\*\?"<>\|]/', '', $new_name);
        $new_name = basename($new_name);

        if (empty($new_name)) {
            echo json_encode(['success' => false, 'message' => 'File name contains invalid characters']);
            exit;
        }

        // Fetch original file
        $stmt = $db->prepare("SELECT id, file_name FROM files WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL");
        $stmt->execute([':id' => $file_id, ':user_id' => $user_id]);
        $orig_file = $stmt->fetch();

        if (!$orig_file) {
            echo json_encode(['success' => false, 'message' => 'File not found or access denied']);
            exit;
        }

        $orig_ext = pathinfo($orig_file['file_name'], PATHINFO_EXTENSION);
        $new_ext = pathinfo($new_name, PATHINFO_EXTENSION);

        // Auto preserve extension if omitted
        if (empty($new_ext) && !empty($orig_ext)) {
            $new_name .= '.' . $orig_ext;
        }

        // Update database
        $stmt = $db->prepare("UPDATE files SET file_name = :new_name WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':new_name' => $new_name, ':id' => $file_id, ':user_id' => $user_id]);

        if (function_exists('logActivity')) {
            logActivity($user_id, 'rename_file', "Renamed file from '{$orig_file['file_name']}' to '{$new_name}'");
        }

        echo json_encode([
            'success' => true,
            'message' => 'File renamed successfully',
            'id' => $file_id,
            'file_name' => $new_name,
            'file_icon' => getFileIcon($new_name)
        ]);
        exit;
    }

    /* ===== EMPTY RECYCLE BIN ===== */
    if ($action === 'empty_recycle_bin') {
        header('Content-Type: application/json');
        $stmt = $db->prepare("SELECT file_path FROM files WHERE user_id = :user_id AND deleted_at IS NOT NULL");
        $stmt->execute([':user_id' => $user_id]);
        $deleted_files = $stmt->fetchAll();

        foreach ($deleted_files as $df) {
            if (!empty($df['file_path']) && file_exists($df['file_path'])) {
                @unlink($df['file_path']);
            }
        }

        $stmt = $db->prepare("DELETE FROM files WHERE user_id = :user_id AND deleted_at IS NOT NULL");
        $stmt->execute([':user_id' => $user_id]);

        if (function_exists('logActivity')) {
            logActivity($user_id, 'empty_bin', 'Emptied entire recycle bin');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Recycle bin emptied'
        ]);
        exit;
    }

    /* ===== CREATE EXPIRING SHARE LINK ===== */
    if ($action === 'create_share_link') {
        header('Content-Type: application/json');
        $file_id = intval($_POST['file_id'] ?? $_GET['file_id'] ?? 0);
        $duration = intval($_POST['duration'] ?? $_GET['duration'] ?? 15); // Duration in minutes (10, 15, 60, 1440, etc.)
        $burn_after_download = !empty($_POST['burn']) || !empty($_GET['burn']);
        $max_downloads = $burn_after_download ? 1 : null;

        if (!$file_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
            exit;
        }

        // Validate allowed durations (between 1 minute and 30 days)
        if ($duration < 1 || $duration > 43200) {
            $duration = 15;
        }

        // Verify file ownership
        $stmt = $db->prepare("SELECT id, file_name, file_size FROM files WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL");
        $stmt->execute([':id' => $file_id, ':user_id' => $user_id]);
        $file = $stmt->fetch();

        if (!$file) {
            echo json_encode(['success' => false, 'message' => 'File not found or access denied']);
            exit;
        }

        // Generate 48-char random token
        $token = bin2hex(random_bytes(24));

        // Insert into file_shares with dynamic expiration interval
        $stmt = $db->prepare("
            INSERT INTO file_shares (file_id, user_id, share_token, expires_at, max_downloads, download_count)
            VALUES (:file_id, :user_id, :token, CURRENT_TIMESTAMP + (:duration || ' minutes')::INTERVAL, :max_downloads, 0)
            RETURNING id, share_token, expires_at, max_downloads
        ");
        $stmt->execute([
            ':file_id' => $file_id,
            ':user_id' => $user_id,
            ':token' => $token,
            ':duration' => $duration,
            ':max_downloads' => $max_downloads
        ]);
        $share = $stmt->fetch();

        // Build full shareable URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $share_url = $protocol . $host . $script_dir . '/share.php?token=' . $token;

        if (function_exists('logActivity')) {
            logActivity($user_id, 'share_link_created', "Created {$duration}-minute share link for file '{$file['file_name']}'");
        }

        echo json_encode([
            'success' => true,
            'share_id' => $share['id'],
            'file_name' => $file['file_name'],
            'share_token' => $token,
            'share_url' => $share_url,
            'duration_minutes' => $duration,
            'expires_at' => $share['expires_at'],
            'burn_after_download' => $burn_after_download,
            'formatted_expires' => date('M d, Y h:i A', strtotime($share['expires_at']))
        ]);
        exit;
    }

    /* ===== GET ACTIVE FILE SHARES ===== */
    if ($action === 'get_file_shares') {
        header('Content-Type: application/json');
        $file_id = intval($_GET['file_id'] ?? 0);

        if (!$file_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, share_token, expires_at, max_downloads, download_count, created_at,
                   (expires_at > CURRENT_TIMESTAMP) as is_active,
                   ROUND(EXTRACT(EPOCH FROM (expires_at - CURRENT_TIMESTAMP))/60) as remaining_minutes
            FROM file_shares
            WHERE file_id = :file_id AND user_id = :user_id AND expires_at > CURRENT_TIMESTAMP
            ORDER BY created_at DESC
        ");
        $stmt->execute([':file_id' => $file_id, ':user_id' => $user_id]);
        $shares = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

        foreach ($shares as &$s) {
            $s['share_url'] = $protocol . $host . $script_dir . '/share.php?token=' . $s['share_token'];
            $s['formatted_expires'] = date('M d, Y h:i A', strtotime($s['expires_at']));
        }

        echo json_encode([
            'success' => true,
            'shares' => $shares
        ]);
        exit;
    }

    /* ===== REVOKE / DELETE SHARE LINK ===== */
    if ($action === 'revoke_share_link') {
        header('Content-Type: application/json');
        $share_id = intval($_POST['share_id'] ?? $_GET['share_id'] ?? 0);

        if (!$share_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid share ID']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM file_shares WHERE id = :share_id AND user_id = :user_id");
        $stmt->execute([':share_id' => $share_id, ':user_id' => $user_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Share link revoked immediately'
        ]);
        exit;
    }

    /* ===== SAVE THEME ===== */
    if ($action === 'save_theme') {
        header('Content-Type: application/json');
        $theme = sanitize($_POST['theme'] ?? $_GET['theme'] ?? 'light');
        
        if (in_array($theme, ['light', 'dark'])) {
            setcookie('theme', $theme, time() + (365*24*60*60), '/');
            setcookie('theme_mode', $theme, time() + (365*24*60*60), '/');
        }

        echo json_encode([
            'success' => true,
            'theme' => $theme
        ]);
        exit;
    }

    /* ===== SEARCH FILES ===== */
    if ($action === 'search_files') {
        header('Content-Type: application/json');
        $query = sanitize($_GET['q'] ?? '');
        
        $stmt = $db->prepare("
            SELECT id, file_name, file_type, file_size, is_favorite, uploaded_at
            FROM files
            WHERE user_id = :user_id AND deleted_at IS NULL AND file_name ILIKE :query
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([':user_id' => $user_id, ':query' => "%{$query}%"]);
        
        echo json_encode(['success' => true, 'files' => $stmt->fetchAll()]);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}