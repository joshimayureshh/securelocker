<?php
// share.php - Modern, Premium Public File Share & Streaming Page
require_once 'config.php';

$token = trim($_GET['token'] ?? '');
$is_download = isset($_GET['download']) && $_GET['download'] == '1';
$is_view = isset($_GET['view']) && $_GET['view'] == '1';

$db = getDB();

$share = null;
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
            $error_message = 'This share link does not exist or the file was removed by its owner.';
        } elseif (!$share['is_valid_time'] || $share['seconds_left'] <= 0) {
            $error_type = 'expired';
            $error_message = 'This temporary share link has expired. Secure Locker links automatically self-destruct for security.';
        } elseif ($share['max_downloads'] !== null && $share['download_count'] >= $share['max_downloads']) {
            $error_type = 'limit_reached';
            $error_message = 'This 1-time download link has already been downloaded and permanently expired.';
        }
    } catch (PDOException $e) {
        $error_type = 'server_error';
        $error_message = 'A server error occurred: ' . $e->getMessage();
    }
} else {
    $error_type = 'not_found';
    $error_message = 'No share token provided. Please verify the URL.';
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
    <title><?php echo $share ? htmlspecialchars($share['file_name']) . ' &bull; Secure Locker' : 'Link Expired &bull; Secure Locker'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #040d1e;
            --card-glass: rgba(10, 22, 48, 0.88);
            --card-border: rgba(59, 130, 246, 0.25);
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.45);
            --text-title: #ffffff;
            --text-sub: #94a3b8;
            --emerald: #10b981;
            --amber: #f59e0b;
            --rose: #f43f5e;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(at 15% 15%, rgba(37, 99, 235, 0.22) 0px, transparent 55%),
                radial-gradient(at 85% 85%, rgba(139, 92, 246, 0.18) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(16, 185, 129, 0.08) 0px, transparent 65%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            color: var(--text-title);
        }

        .share-wrapper {
            width: 100%;
            max-width: 680px;
            margin: 0 auto;
        }

        /* BRAND HEADER */
        .brand-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 24px;
            text-decoration: none;
        }

        .brand-nav-logo {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
        }

        .brand-nav-title {
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 30%, #93c5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* MAIN CARD */
        .share-card-container {
            background: var(--card-glass);
            border: 1.5px solid var(--card-border);
            border-radius: 26px;
            padding: 32px 30px;
            box-shadow: 
                0 30px 70px -15px rgba(0, 0, 0, 0.7),
                0 0 0 1px rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            position: relative;
            overflow: hidden;
            animation: modalFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(18px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* TOP STATUS BAR */
        .card-status-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 20px;
            margin-bottom: 22px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            flex-wrap: wrap;
        }

        .security-badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 650;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--emerald);
            box-shadow: 0 0 10px var(--emerald);
            animation: pulseGlow 2s infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.6; }
        }

        .timer-badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(245, 158, 11, 0.14);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fbbf24;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 750;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.2px;
        }

        .timer-badge-tag.urgent {
            background: rgba(244, 63, 94, 0.18);
            border-color: rgba(244, 63, 94, 0.45);
            color: #fb7185;
            animation: timerBlink 1s infinite alternate;
        }

        @keyframes timerBlink {
            from { opacity: 1; }
            to { opacity: 0.65; }
        }

        /* FILE INFO HEADER */
        .file-info-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
        }

        .file-type-avatar {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.25), rgba(139, 92, 246, 0.25));
            border: 1.5px solid rgba(96, 165, 250, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .file-info-text {
            flex: 1;
            overflow: hidden;
        }

        .file-info-title {
            font-size: 20px;
            font-weight: 750;
            color: #ffffff;
            line-height: 1.35;
            word-break: break-all;
            margin-bottom: 4px;
        }

        .file-info-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            color: var(--text-sub);
            flex-wrap: wrap;
        }

        .meta-chip {
            background: #112040;
            border: 1px solid #1f3768;
            padding: 2px 9px;
            border-radius: 6px;
            font-weight: 700;
            color: #93c5fd;
            font-size: 11.5px;
            letter-spacing: 0.3px;
        }

        /* MEDIA PREVIEW CANVAS */
        .media-showcase-canvas {
            margin: 18px 0 24px;
            border-radius: 18px;
            overflow: hidden;
            background: #030814;
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.5), 0 10px 30px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            max-height: 380px;
        }

        .media-showcase-canvas img {
            width: 100%;
            height: auto;
            max-height: 380px;
            object-fit: contain;
            display: block;
            border-radius: 16px;
        }

        .media-showcase-canvas video {
            width: 100%;
            max-height: 380px;
            display: block;
            border-radius: 16px;
            outline: none;
        }

        /* SELF DESTRUCT BANNER */
        .burn-banner {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(244, 63, 94, 0.12);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #fda4af;
            padding: 9px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        /* ACTIONS */
        .card-actions-layout {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-main-download {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px 24px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-size: 15px;
            font-weight: 750;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 10px 28px rgba(37, 99, 235, 0.4);
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .btn-main-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px rgba(37, 99, 235, 0.55);
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .secondary-actions-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn-sub-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            background: #112040;
            border: 1px solid #1f3768;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 650;
            border-radius: 12px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-sub-action:hover {
            background: #1a3060;
            color: #ffffff;
            border-color: #3b82f6;
        }

        /* ERROR / EXPIRED CARD */
        .expired-card-content {
            text-align: center;
            padding: 24px 10px;
        }

        .expired-icon {
            font-size: 58px;
            margin-bottom: 16px;
            display: inline-block;
        }

        .expired-title {
            font-size: 22px;
            font-weight: 800;
            color: #fda4af;
            margin-bottom: 10px;
        }

        .expired-desc {
            font-size: 14px;
            color: #cbd5e1;
            line-height: 1.6;
            margin-bottom: 26px;
            max-width: 440px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-return-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 26px;
            background: #172d5c;
            border: 1px solid #284c94;
            color: #ffffff;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 650;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-return-home:hover {
            background: #2563eb;
            border-color: #2563eb;
        }

        .page-footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 12.5px;
            color: var(--text-sub);
        }

        .page-footer-note a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 600;
        }

        .page-footer-note a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="share-wrapper">
    <!-- BRAND NAVIGATION -->
    <a href="login.php" class="brand-nav">
        <img src="assets/images/icon-192.png" alt="Secure Locker" class="brand-nav-logo">
        <span class="brand-nav-title">Secure Locker</span>
    </a>

    <?php if ($error_type): ?>
        <!-- ERROR / EXPIRED CARD -->
        <div class="share-card-container" style="border-color: rgba(244, 63, 94, 0.35);">
            <div class="expired-card-content">
                <span class="expired-icon">
                    <?php echo ($error_type === 'expired' || $error_type === 'limit_reached') ? '⏱️' : '🔒'; ?>
                </span>
                <h2 class="expired-title">
                    <?php 
                    if ($error_type === 'expired') echo "Temporary Link Expired";
                    elseif ($error_type === 'limit_reached') echo "Download Limit Reached";
                    else echo "Access Restricted";
                    ?>
                </h2>
                <p class="expired-desc"><?php echo htmlspecialchars($error_message); ?></p>

                <a href="login.php" class="btn-return-home">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    <span>Back to Secure Locker</span>
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- ACTIVE SHARE CARD -->
        <div class="share-card-container">
            <!-- TOP STATUS BAR (ENCRYPTION + LIVE TIMER) -->
            <div class="card-status-bar">
                <div class="security-badge-tag">
                    <span class="pulse-dot"></span>
                    <span>AES-256 Encrypted Stream</span>
                </div>

                <div class="timer-badge-tag" id="timerBadge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Expires in <strong id="timerCountdown">--:--</strong></span>
                </div>
            </div>

            <!-- FILE INFO HEADER -->
            <div class="file-info-header">
                <div class="file-type-avatar">
                    <?php 
                    if ($is_video) echo '🎬';
                    elseif ($is_image) echo '🖼️';
                    elseif ($is_pdf) echo '📄';
                    elseif ($is_audio) echo '🎵';
                    else echo '📁';
                    ?>
                </div>
                <div class="file-info-text">
                    <h1 class="file-info-title"><?php echo htmlspecialchars($share['file_name']); ?></h1>
                    <div class="file-info-meta">
                        <span class="meta-chip"><?php echo strtoupper($ext ?: 'FILE'); ?></span>
                        <span><?php echo formatFileSize($share['file_size']); ?></span>
                        <span>&bull;</span>
                        <span>Shared by <strong><?php echo htmlspecialchars($share['owner_name']); ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- MEDIA SHOWCASE (FOR IMAGES / VIDEOS) -->
            <?php if ($is_image): ?>
                <div class="media-showcase-canvas">
                    <img src="share.php?token=<?php echo urlencode($token); ?>&view=1" alt="<?php echo htmlspecialchars($share['file_name']); ?>">
                </div>
            <?php elseif ($is_video): ?>
                <div class="media-showcase-canvas">
                    <video controls autoplay playsinline preload="metadata">
                        <source src="share.php?token=<?php echo urlencode($token); ?>&view=1" type="video/mp4">
                        Your browser does not support HTML5 video.
                    </video>
                </div>
            <?php endif; ?>

            <!-- SELF DESTRUCT WARNING (IF 1-TIME ACCESS) -->
            <?php if ($share['max_downloads'] === 1): ?>
                <div class="burn-banner">
                    <span>🔥</span>
                    <span><strong>1-Time Download Link:</strong> This file link will permanently self-destruct once downloaded.</span>
                </div>
            <?php endif; ?>

            <!-- ACTION BUTTONS -->
            <div class="card-actions-layout">
                <a href="share.php?token=<?php echo urlencode($token); ?>&download=1" class="btn-main-download">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width: 18px; height: 18px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Download Decrypted File (<?php echo formatFileSize($share['file_size']); ?>)</span>
                </a>

                <div class="secondary-actions-row">
                    <?php if ($can_preview): ?>
                        <a href="share.php?token=<?php echo urlencode($token); ?>&view=1" target="_blank" class="btn-sub-action">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <span>Open Original View</span>
                        </a>
                    <?php endif; ?>

                    <button type="button" class="btn-sub-action" id="copySharePageBtn" onclick="copyCurrentShareUrl()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span>Copy Link</span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="page-footer-note">
        Protected by <a href="login.php">Secure Locker</a> &bull; Zero-Knowledge Cloud Storage
    </div>
</div>

<?php if ($share && !$error_type): ?>
<script>
    // Live Countdown Timer
    let secondsLeft = <?php echo intval($share['seconds_left']); ?>;
    const timerCountdown = document.getElementById('timerCountdown');
    const timerBadge = document.getElementById('timerBadge');

    function updateCountdown() {
        if (secondsLeft <= 0) {
            timerCountdown.textContent = "00:00";
            if (timerBadge) {
                timerBadge.classList.add('urgent');
                timerBadge.innerHTML = '<span>⚠️ Link Expired</span>';
            }
            setTimeout(() => { location.reload(); }, 1200);
            return;
        }

        const mins = Math.floor(secondsLeft / 60);
        const secs = secondsLeft % 60;
        const formatted = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        timerCountdown.textContent = formatted;

        if (secondsLeft <= 120 && timerBadge) {
            timerBadge.classList.add('urgent');
        }

        secondsLeft--;
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);

    function copyCurrentShareUrl() {
        navigator.clipboard.writeText(window.location.href).then(() => {
            const btn = document.getElementById('copySharePageBtn');
            if (btn) {
                btn.innerHTML = '<span>✅ Link Copied!</span>';
                setTimeout(() => {
                    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span>Copy Link</span>';
                }, 2200);
            }
        });
    }
</script>
<?php endif; ?>

</body>
</html>
