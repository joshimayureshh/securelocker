<?php
// share.php - Public Secure Expiring File Share Landing & Download Page
require_once 'config.php';

$token = trim($_GET['token'] ?? '');
$is_download = isset($_GET['download']) && $_GET['download'] == '1';
$is_view = isset($_GET['view']) && $_GET['view'] == '1';

$db = getDB();

$share = null;
$file = null;
$error_type = null; // 'not_found', 'expired', 'limit_reached'
$error_message = '';

if (!empty($token)) {
    try {
        $stmt = $db->prepare("
            SELECT s.id as share_id, s.file_id, s.user_id, s.share_token, s.expires_at, 
                   s.max_downloads, s.download_count, s.created_at,
                   f.file_name, f.file_type, f.file_size, f.file_path, f.encryption_key, f.iv,
                   u.name as owner_name,
                   (s.expires_at > CURRENT_TIMESTAMP) as is_valid_time,
                   ROUND(EXTRACT(EPOCH FROM (s.expires_at - CURRENT_TIMESTAMP))) as seconds_left
            FROM file_shares s
            JOIN files f ON s.file_id = f.id
            JOIN users u ON s.user_id = u.id
            WHERE s.share_token = :token AND f.deleted_at IS NULL
        ");
        $stmt->execute([':token' => $token]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$share) {
            $error_type = 'not_found';
            $error_message = 'This share link does not exist or the file was deleted by the owner.';
        } elseif (!$share['is_valid_time'] || $share['seconds_left'] <= 0) {
            $error_type = 'expired';
            $error_message = 'This temporary share link has expired. For security, files are inaccessible once their expiration period ends.';
        } elseif ($share['max_downloads'] !== null && $share['download_count'] >= $share['max_downloads']) {
            $error_type = 'limit_reached';
            $error_message = 'This 1-time download share link has already been downloaded and self-destructed.';
        }
    } catch (PDOException $e) {
        $error_type = 'server_error';
        $error_message = 'A database error occurred: ' . $e->getMessage();
    }
} else {
    $error_type = 'not_found';
    $error_message = 'No share token provided. Please check your URL link.';
}

// Handle Direct Download or Inline Stream
if ($share && !$error_type && ($is_download || $is_view)) {
    if (!file_exists($share['file_path'])) {
        die("Physical file not found on server.");
    }

    $encrypted_content = file_get_contents($share['file_path']);
    if ($encrypted_content === false) {
        die("Failed to read file storage.");
    }

    // Decrypt file contents
    if (!empty($share['encryption_key']) && !empty($share['iv'])) {
        $key = hex2bin($share['encryption_key']);
        $iv = hex2bin($share['iv']);
        $decrypted = openssl_decrypt(
            $encrypted_content,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($decrypted === false) {
            die("Decryption failed.");
        }
    } else {
        $decrypted = $encrypted_content;
    }

    // If downloading, increment download count
    if ($is_download) {
        try {
            $stmt = $db->prepare("UPDATE file_shares SET download_count = download_count + 1 WHERE id = :id");
            $stmt->execute([':id' => $share['share_id']]);
        } catch (Exception $e) {}
    }

    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $ext = strtolower(pathinfo($share['file_name'], PATHINFO_EXTENSION));
    $mime_map = [
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'aac' => 'audio/aac', 'm4a' => 'audio/mp4',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8', 'json' => 'application/json; charset=utf-8'
    ];
    $content_type = $mime_map[$ext] ?? $share['file_type'] ?? 'application/octet-stream';

    if ($is_view) {
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: inline; filename="' . addslashes($share['file_name']) . '"');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . strlen($decrypted));
        header('Cache-Control: private, max-age=60');
    } else {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($share['file_name']) . '"');
        header('Content-Length: ' . strlen($decrypted));
        header('Cache-Control: no-cache, must-revalidate');
    }

    header('Pragma: public');
    header('Expires: 0');
    echo $decrypted;
    exit;
}

$ext = $share ? strtolower(pathinfo($share['file_name'], PATHINFO_EXTENSION)) : '';
$is_video = in_array($ext, ['mp4', 'webm', 'mov', 'mkv']);
$is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
$is_pdf = ($ext === 'pdf');
$is_audio = in_array($ext, ['mp3', 'wav', 'ogg', 'm4a']);
$can_preview = ($is_video || $is_image || $is_pdf || $is_audio || in_array($ext, ['txt', 'md', 'json', 'js', 'html', 'css', 'php']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $share ? htmlspecialchars($share['file_name']) . ' - Secure Locker' : 'Secure Link Expired - Secure Locker'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #061126;
            --card-bg: #0b1a3a;
            --border-color: #1e3360;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: var(--bg-base);
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.18) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.15) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text-main);
        }

        .share-container {
            width: 100%;
            max-width: 580px;
        }

        /* HEADER BRANDING */
        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-main);
        }

        .brand-logo-img {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: contain;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
        }

        .brand-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 0%, #93c5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* MAIN CARD */
        .share-card {
            background: var(--card-bg);
            border: 1.5px solid var(--border-color);
            border-radius: 24px;
            padding: 36px 32px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(16px);
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: cardSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* COUNTDOWN TIMER BANNER */
        .countdown-banner {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fbbf24;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .countdown-banner.urgent {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(239, 68, 68, 0.4);
            color: #f87171;
            animation: pulseBanner 1.5s infinite;
        }

        @keyframes pulseBanner {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* FILE ICON & DETAILS */
        .file-icon-wrap {
            width: 80px;
            height: 80px;
            background: rgba(59, 130, 246, 0.12);
            border: 1.5px solid rgba(59, 130, 246, 0.25);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 38px;
        }

        .file-name {
            font-size: 20px;
            font-weight: 750;
            color: #ffffff;
            margin-bottom: 8px;
            word-break: break-all;
            line-height: 1.35;
        }

        .file-meta-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .meta-pill {
            background: #132448;
            padding: 4px 10px;
            border-radius: 8px;
            border: 1px solid #1e3360;
            font-weight: 600;
        }

        /* SECURITY BADGE */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 28px;
        }

        /* ACTION BUTTONS */
        .actions-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-download-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px 24px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
            transition: all 0.2s ease;
        }

        .btn-download-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.5);
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .btn-preview-secondary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 24px;
            background: #17284d;
            color: #93c5fd;
            font-size: 14px;
            font-weight: 650;
            border: 1px solid #294073;
            border-radius: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-preview-secondary:hover {
            background: #1e3566;
            color: #ffffff;
            border-color: #3b82f6;
        }

        /* MEDIA PREVIEW EMBED */
        .media-preview-box {
            margin: 20px 0;
            border-radius: 16px;
            overflow: hidden;
            background: #060d1d;
            border: 1.5px solid #1e3360;
        }

        .media-preview-box video {
            width: 100%;
            max-height: 280px;
            display: block;
            outline: none;
        }

        .media-preview-box img {
            width: 100%;
            max-height: 280px;
            object-fit: contain;
            display: block;
        }

        /* ERROR / EXPIRED STATES */
        .error-card {
            border-color: rgba(239, 68, 68, 0.35);
            background: #1a0f1d;
        }

        .error-icon {
            font-size: 56px;
            margin-bottom: 16px;
        }

        .error-title {
            font-size: 22px;
            font-weight: 750;
            color: #f87171;
            margin-bottom: 12px;
        }

        .error-desc {
            font-size: 14px;
            color: #cbd5e1;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #26385e;
            color: #ffffff;
            border-radius: 12px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-home:hover {
            background: #3b82f6;
        }

        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .footer-note a {
            color: #60a5fa;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="share-container">
    <!-- BRAND HEADER -->
    <div class="brand-header">
        <a href="login.php" class="brand-logo">
            <img src="assets/images/icon-192.png" alt="Secure Locker" class="brand-logo-img">
            <span class="brand-title">Secure Locker</span>
        </a>
    </div>

    <?php if ($error_type): ?>
        <!-- ERROR / EXPIRED CARD -->
        <div class="share-card error-card">
            <div class="error-icon">
                <?php echo ($error_type === 'expired' || $error_type === 'limit_reached') ? '⏱️' : '🔒'; ?>
            </div>
            <h2 class="error-title">
                <?php 
                if ($error_type === 'expired') echo "Link Expired";
                elseif ($error_type === 'limit_reached') echo "Download Limit Reached";
                else echo "Access Denied";
                ?>
            </h2>
            <p class="error-desc"><?php echo htmlspecialchars($error_message); ?></p>

            <a href="login.php" class="btn-home">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Go to Secure Locker</span>
            </a>
        </div>
    <?php else: ?>
        <!-- ACTIVE SHARE CARD -->
        <div class="share-card">
            <!-- EXPIRATION COUNTDOWN TIMER -->
            <div class="countdown-banner" id="countdownBanner">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Link Expires In: <strong id="countdownText">--:--</strong></span>
            </div>

            <!-- FILE DETAILS -->
            <div class="file-icon-wrap">
                <?php 
                if ($is_video) echo '🎬';
                elseif ($is_image) echo '🖼️';
                elseif ($is_pdf) echo '📄';
                elseif ($is_audio) echo '🎵';
                else echo '📁';
                ?>
            </div>

            <h1 class="file-name"><?php echo htmlspecialchars($share['file_name']); ?></h1>

            <div class="file-meta-row">
                <span class="meta-pill"><?php echo strtoupper($ext ?: 'FILE'); ?></span>
                <span class="meta-pill"><?php echo formatFileSize($share['file_size']); ?></span>
                <span>Shared by <strong><?php echo htmlspecialchars($share['owner_name']); ?></strong></span>
            </div>

            <!-- INLINE MEDIA PREVIEW IF VIDEO OR IMAGE -->
            <?php if ($is_video): ?>
                <div class="media-preview-box">
                    <video controls autoplay playsinline preload="metadata">
                        <source src="share.php?token=<?php echo urlencode($token); ?>&view=1" type="video/mp4">
                        Your browser does not support HTML5 video.
                    </video>
                </div>
            <?php elseif ($is_image): ?>
                <div class="media-preview-box">
                    <img src="share.php?token=<?php echo urlencode($token); ?>&view=1" alt="<?php echo htmlspecialchars($share['file_name']); ?>">
                </div>
            <?php endif; ?>

            <!-- SECURITY BADGE -->
            <div class="security-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <span>Encrypted with AES-256 &bull; Decrypted on-the-fly</span>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="actions-group">
                <a href="share.php?token=<?php echo urlencode($token); ?>&download=1" class="btn-download-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width: 18px; height: 18px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Download Decrypted File (<?php echo formatFileSize($share['file_size']); ?>)</span>
                </a>

                <?php if ($can_preview && !$is_video && !$is_image): ?>
                    <a href="share.php?token=<?php echo urlencode($token); ?>&view=1" target="_blank" class="btn-preview-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <span>Preview in Browser</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="footer-note">
        Protected by <a href="login.php">Secure Locker</a> &bull; Zero-Knowledge Cloud Storage
    </div>
</div>

<?php if ($share && !$error_type): ?>
<script>
    // Live Countdown Timer
    let secondsLeft = <?php echo intval($share['seconds_left']); ?>;
    const countdownText = document.getElementById('countdownText');
    const countdownBanner = document.getElementById('countdownBanner');

    function updateCountdown() {
        if (secondsLeft <= 0) {
            countdownText.textContent = "Expired";
            if (countdownBanner) {
                countdownBanner.classList.add('urgent');
                countdownBanner.innerHTML = '<span>⚠️ Link has expired. Refreshing...</span>';
            }
            setTimeout(() => { location.reload(); }, 1500);
            return;
        }

        const mins = Math.floor(secondsLeft / 60);
        const secs = secondsLeft % 60;
        const formatted = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        countdownText.textContent = formatted;

        if (secondsLeft <= 120 && countdownBanner) {
            countdownBanner.classList.add('urgent');
        }

        secondsLeft--;
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
</script>
<?php endif; ?>

</body>
</html>
