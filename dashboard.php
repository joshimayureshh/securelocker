<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Retrieve current logged-in user dynamically from database
$user_name = 'User';
$user_email = '';
$user_avatar = null;

try {
    $stmt = $db->prepare("SELECT id, name, email, avatar_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();
    if ($current_user) {
        $user_name = $current_user['name'];
        $user_email = $current_user['email'];
        $user_avatar = $current_user['avatar_path'] ?? null;
        
        // Keep session in sync with DB
        $_SESSION['user_name'] = $current_user['name'];
        $_SESSION['user_email'] = $current_user['email'];
    } else {
        $user_name = $_SESSION['user_name'] ?? 'User';
        $user_email = $_SESSION['user_email'] ?? '';
    }
} catch (PDOException $e) {
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_email = $_SESSION['user_email'] ?? '';
}

$formatted_name = ucwords(strtolower(trim($user_name)));
$name_parts = explode(' ', $formatted_name);
$first_name = !empty($name_parts[0]) ? $name_parts[0] : $formatted_name;

// Compute 2-letter initials
$initials = '';
foreach ($name_parts as $n) {
    if (!empty($n)) {
        $initials .= strtoupper($n[0]);
        if (strlen($initials) >= 2) break;
    }
}
$user_initials = !empty($initials) ? $initials : 'U';

// Get user stats (only active files, not recycled)
$stats = ['file_count' => 0, 'total_size' => 0, 'favorite_count' => 0];
try {
    $stmt = $db->prepare("SELECT COUNT(*) as file_count, COALESCE(SUM(file_size), 0) as total_size, SUM(CASE WHEN is_favorite THEN 1 ELSE 0 END) as favorite_count FROM files WHERE user_id = ? AND deleted_at IS NULL");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    // Use default stats
}

// Get recent files (only active files)
$recent_files = [];
try {
    $stmt = $db->prepare("SELECT id, file_name, file_type, file_size, is_favorite, uploaded_at FROM files WHERE user_id = ? AND deleted_at IS NULL ORDER BY uploaded_at DESC LIMIT 8");
    $stmt->execute([$user_id]);
    $recent_files = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty array
}

// Handle file actions - Recycle Bin, Restore, Toggle Favorite, Permanent Delete
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $file_id = intval($_GET['id'] ?? 0);
    
    // Soft Delete (Move to Recycle Bin)
    if ($action === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes' && $file_id > 0) {
        try {
            $stmt = $db->prepare("UPDATE files SET deleted_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$file_id, $user_id]);
            
            if (function_exists('logActivity')) {
                logActivity($user_id, 'recycle', 'Moved file to recycle bin: ID ' . $file_id);
            }
            
            $redirect_section = isset($_GET['section']) ? $_GET['section'] : 'files';
            header('Location: dashboard.php?section=' . $redirect_section . '&msg=recycled');
            exit();
        } catch (PDOException $e) {
            $redirect_section = isset($_GET['section']) ? $_GET['section'] : 'files';
            header('Location: dashboard.php?section=' . $redirect_section . '&msg=error');
            exit();
        }
    }

    // Restore from Recycle Bin
    if ($action === 'restore' && $file_id > 0) {
        try {
            $stmt = $db->prepare("UPDATE files SET deleted_at = NULL WHERE id = ? AND user_id = ?");
            $stmt->execute([$file_id, $user_id]);
            
            if (function_exists('logActivity')) {
                logActivity($user_id, 'restore', 'Restored file: ID ' . $file_id);
            }
            
            header('Location: dashboard.php?section=settings&tab=recycle&msg=restored');
            exit();
        } catch (PDOException $e) {
            header('Location: dashboard.php?section=settings&tab=recycle&msg=error');
            exit();
        }
    }

    // Permanent Delete Single File
    if ($action === 'permanent_delete' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes' && $file_id > 0) {
        try {
            $stmt = $db->prepare("SELECT file_path, file_name FROM files WHERE id = ? AND user_id = ?");
            $stmt->execute([$file_id, $user_id]);
            $file = $stmt->fetch();
            
            if ($file) {
                if (!empty($file['file_path']) && file_exists($file['file_path'])) {
                    @unlink($file['file_path']);
                }
                $stmt = $db->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
                $stmt->execute([$file_id, $user_id]);
                
                if (function_exists('logActivity')) {
                    logActivity($user_id, 'permanent_delete', 'Permanently deleted file: ' . $file['file_name']);
                }
            }
            header('Location: dashboard.php?section=settings&tab=recycle&msg=permanently_deleted');
            exit();
        } catch (PDOException $e) {
            header('Location: dashboard.php?section=settings&tab=recycle&msg=error');
            exit();
        }
    }

    // Empty Entire Recycle Bin
    if ($action === 'empty_recycle_bin' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        try {
            $stmt = $db->prepare("SELECT file_path FROM files WHERE user_id = ? AND deleted_at IS NOT NULL");
            $stmt->execute([$user_id]);
            $deleted_files = $stmt->fetchAll();
            
            foreach ($deleted_files as $df) {
                if (!empty($df['file_path']) && file_exists($df['file_path'])) {
                    @unlink($df['file_path']);
                }
            }
            
            $stmt = $db->prepare("DELETE FROM files WHERE user_id = ? AND deleted_at IS NOT NULL");
            $stmt->execute([$user_id]);
            
            if (function_exists('logActivity')) {
                logActivity($user_id, 'empty_bin', 'Emptied entire recycle bin');
            }
            
            header('Location: dashboard.php?section=settings&tab=recycle&msg=bin_emptied');
            exit();
        } catch (PDOException $e) {
            header('Location: dashboard.php?section=settings&tab=recycle&msg=error');
            exit();
        }
    }
    
    // Handle toggle favorite
    if ($action === 'toggle_favorite') {
        try {
            $stmt = $db->prepare("
                UPDATE files 
                SET is_favorite = NOT is_favorite 
                WHERE id = ? AND user_id = ?
                RETURNING is_favorite
            ");
            $stmt->execute([$file_id, $user_id]);
            $result = $stmt->fetch();
            
            $redirect_section = isset($_GET['section']) ? $_GET['section'] : 'files';
            header('Location: dashboard.php?section=' . $redirect_section . '&msg=favorite_toggled');
            exit();
        } catch (PDOException $e) {
            $redirect_section = isset($_GET['section']) ? $_GET['section'] : 'files';
            header('Location: dashboard.php?section=' . $redirect_section . '&msg=error');
            exit();
        }
    }
}

// Determine current section
$section = isset($_GET['section']) ? sanitize($_GET['section']) : 'dashboard';
$valid_sections = ['dashboard', 'files', 'upload', 'favorites', 'profile', 'settings'];
if (!in_array($section, $valid_sections)) {
    $section = 'dashboard';
}

// Include section content
$section_file = "sections/{$section}.php";
if (!file_exists($section_file)) {
    $section = 'dashboard';
    $section_file = "sections/dashboard.php";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Locker - <?php echo htmlspecialchars(ucfirst($section)); ?></title>
    <!-- PWA Manifest & Mobile App Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#061d48">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SecureLocker">
    <link rel="apple-touch-icon" href="assets/images/icon-192.png">

    <link rel="stylesheet" href="css/dashboard-ui.css?v=<?php echo time(); ?>">
    <style>
    /* Essential inline fallback styles ensuring popup is hidden until triggered */
    .sl-google-card { display: none; }
    .sl-google-card.show { display: block !important; }
    </style>
</head>
<body>
   <header class="sl-header">

    <div class="sl-brand">
        <img src="assets/images/logo.png" class="sl-logo-img" alt="Secure Locker Logo">

        <div class="sl-brand-text">
            <h1>Secure Locker</h1>
            <p>PROTECTION | ACCESSIBILITY | TRUST</p>
        </div>
    </div>

    <div class="sl-header-right">
        <div class="sl-google-menu" id="slUserMenu">
            <!-- Trigger in Header -->
            <button type="button" class="sl-google-trigger" id="slUserTrigger" aria-expanded="false" aria-haspopup="true" title="Secure Locker Account: <?php echo htmlspecialchars($formatted_name); ?>">
                <div class="sl-trigger-avatar-ring">
                    <div class="sl-trigger-avatar">
                        <?php if (!empty($user_avatar)): ?>
                            <span class="sl-avatar-emoji"><?php echo $user_avatar; ?></span>
                        <?php else: ?>
                            <span class="sl-avatar-text"><?php echo htmlspecialchars($user_initials); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="sl-trigger-name"><?php echo htmlspecialchars($formatted_name); ?></span>
                <span class="sl-trigger-chevron">▼</span>
            </button>

            <!-- Google Account Style Popup Card (Matching Image 2) -->
            <div class="sl-google-card" id="slUserDropdown">
                <!-- Top Email & Close Button -->
                <div class="sl-gcard-top">
                    <span class="sl-gcard-email"><?php echo htmlspecialchars($user_email); ?></span>
                    <button type="button" class="sl-gcard-close" id="slCardClose" aria-label="Close menu">&times;</button>
                </div>

                <!-- Center Hero Area -->
                <div class="sl-gcard-hero">
                    <div class="sl-gcard-avatar-wrap">
                        <div class="sl-gcard-avatar">
                            <?php if (!empty($user_avatar)): ?>
                                <span class="sl-gcard-emoji"><?php echo $user_avatar; ?></span>
                            <?php else: ?>
                                <span class="sl-gcard-initials"><?php echo htmlspecialchars($user_initials); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="dashboard.php?section=profile" class="sl-gcard-camera-btn" title="Change profile avatar">
                            <span>📷</span>
                        </a>
                    </div>

                    <h3 class="sl-gcard-greeting">Hi, <?php echo htmlspecialchars($first_name); ?>!</h3>
                </div>

                <!-- Nested White Card with Accounts & Actions -->
                <div class="sl-gcard-inner-box">
                    <a href="dashboard.php?section=profile" class="sl-gcard-account-row">
                        <div class="sl-grow-avatar">
                            <?php if (!empty($user_avatar)): ?>
                                <span><?php echo $user_avatar; ?></span>
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($user_initials); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sl-grow-info">
                            <div class="sl-grow-name"><?php echo htmlspecialchars($formatted_name); ?></div>
                            <div class="sl-grow-email"><?php echo htmlspecialchars($user_email); ?></div>
                        </div>
                        <span class="sl-grow-check">✓</span>
                    </a>

                    <div class="sl-gcard-divider"></div>

                    <a href="dashboard.php?section=profile" class="sl-gcard-action-row">
                        <span class="sl-gaction-icon">👤</span>
                        <span class="sl-gaction-text">Profile &amp; Security</span>
                    </a>

                    <a href="logout.php" class="sl-gcard-action-row sl-gcard-signout-row">
                        <span class="sl-gaction-icon">⇥</span>
                        <span class="sl-gaction-text">Log Out</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

</header>
    
    <div class="container">
        <aside class="sl-sidebar">

    <nav class="sl-navigation">

        <a href="?section=dashboard"
           class="sl-nav-item <?php echo $section == 'dashboard' ? 'active' : ''; ?>">
            <span class="sl-nav-icon">
                <svg class="sl-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                </svg>
            </span>
            <span>Dashboard</span>
        </a>

        <a href="?section=files"
           class="sl-nav-item <?php echo $section == 'files' ? 'active' : ''; ?>">
            <span class="sl-nav-icon">
                <svg class="sl-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    <line x1="9" y1="14" x2="15" y2="14"></line>
                </svg>
            </span>
            <span>My Files</span>
        </a>

        <a href="?section=upload"
           class="sl-nav-item <?php echo $section == 'upload' ? 'active' : ''; ?>">
            <span class="sl-nav-icon">
                <svg class="sl-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
            </span>
            <span>Upload</span>
        </a>

        <a href="?section=favorites"
           class="sl-nav-item <?php echo $section == 'favorites' ? 'active' : ''; ?>">
            <span class="sl-nav-icon">
                <svg class="sl-nav-svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </span>
            <span>Favorites</span>
        </a>

        <a href="?section=settings"
           class="sl-nav-item <?php echo $section == 'settings' ? 'active' : ''; ?>">
            <span class="sl-nav-icon">
                <svg class="sl-nav-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </span>
            <span>Settings</span>
        </a>

    </nav>


    <!-- Decorative Locker
    <div class="sl-sidebar-decoration">

        <div class="sl-safe">
            <div class="sl-safe-door">
                <div class="sl-safe-lock">🔒</div>
            </div>
        </div>

        <div class="sl-shield">
            🔒
        </div>

    </div> -->

</aside>
        
        <main class="main-content section-<?php echo htmlspecialchars($section); ?>">
    <?php include $section_file; ?>
</main>
    </div>

<!-- =========================================================
     GLOBAL BACKDROP OVERLAY
========================================================= -->
<div id="slGlobalBackdrop" class="sl-drawer-backdrop" onclick="closeAllDrawers()"></div>

<!-- =========================================================
     GLOBAL RECYCLE BIN SLIDE-OVER DRAWER
========================================================= -->
<div id="slRecycleDrawer" class="sl-slide-drawer" role="dialog" aria-modal="true" aria-labelledby="recycleDrawerTitle">
    <div class="sl-drawer-header">
        <div class="sl-drawer-title-wrap">
            <span class="sl-drawer-icon-badge red">🗑️</span>
            <div>
                <h3 id="recycleDrawerTitle">Recycle Bin</h3>
                <p id="recycleDrawerSub">Restore or permanently erase deleted files</p>
            </div>
        </div>
        <button type="button" class="sl-drawer-close" onclick="closeAllDrawers()" aria-label="Close Recycle Bin">&times;</button>
    </div>

    <div class="sl-drawer-toolbar" id="recycleDrawerToolbar" style="display: none;">
        <span class="recycle-count-pill" id="recycleDrawerPill">0 files</span>
        <button type="button" class="sl-btn-empty-recycle" onclick="emptyRecycleBinAjax()">
            <span>🧹 Empty Recycle Bin</span>
        </button>
    </div>

    <div class="sl-drawer-body" id="recycleDrawerBody">
        <div class="sl-drawer-loading">
            <div class="sl-spinner"></div>
            <span>Loading deleted files...</span>
        </div>
    </div>
</div>

<!-- =========================================================
     GLOBAL APPEARANCE SLIDE-OVER DRAWER
========================================================= -->
<div id="slAppearanceDrawer" class="sl-slide-drawer" role="dialog" aria-modal="true" aria-labelledby="appearanceDrawerTitle">
    <div class="sl-drawer-header">
        <div class="sl-drawer-title-wrap">
            <span class="sl-drawer-icon-badge blue">🎨</span>
            <div>
                <h3 id="appearanceDrawerTitle">Appearance &amp; Theme</h3>
                <p>Customize your workspace theme</p>
            </div>
        </div>
        <button type="button" class="sl-drawer-close" onclick="closeAllDrawers()" aria-label="Close Appearance">&times;</button>
    </div>

    <div class="sl-drawer-body">
        <div class="sl-drawer-section">
            <div class="section-heading">Theme Mode</div>
            <div class="sl-theme-mode-list">
                <div class="sl-theme-mode-item" id="themeItem-light" onclick="setThemeMode('light')">
                    <div class="mode-info">
                        <span class="mode-icon">☀️</span>
                        <div>
                            <div class="mode-title">Light Mode</div>
                            <div class="mode-desc">High contrast bright workspace</div>
                        </div>
                    </div>
                    <span class="mode-radio-check">✓</span>
                </div>

                <div class="sl-theme-mode-item" id="themeItem-dark" onclick="setThemeMode('dark')">
                    <div class="mode-info">
                        <span class="mode-icon">🌙</span>
                        <div>
                            <div class="mode-title">Dark Mode</div>
                            <div class="mode-desc">Midnight blue theme for low light</div>
                        </div>
                    </div>
                    <span class="mode-radio-check">✓</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     IN-BROWSER FILE PREVIEW MODAL (SAME WINDOW)
========================================================= -->
<div id="slFilePreviewModal" class="sl-preview-modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="previewModalFileName">
    <div class="sl-preview-modal-box">
        <!-- Top Toolbar -->
        <div class="sl-preview-header">
            <div class="sl-preview-title-wrap">
                <span class="sl-preview-file-icon" id="previewModalFileIcon">📄</span>
                <div>
                    <h3 class="sl-preview-filename" id="previewModalFileName">File Name</h3>
                    <span class="sl-preview-meta" id="previewModalFileMeta">Type &bull; Size</span>
                </div>
            </div>
            <div class="sl-preview-header-actions">
                <button type="button" class="sl-preview-tool-btn" id="previewOpenTabBtn" title="Open in new browser tab">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    <span>New Tab</span>
                </button>
                <button type="button" class="sl-preview-tool-btn primary" id="previewDownloadBtn" title="Download Decrypted File">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Download</span>
                </button>
                <button type="button" class="sl-preview-close-btn" onclick="closeFilePreviewModal()" aria-label="Close preview">&times;</button>
            </div>
        </div>

        <!-- Main Preview Body -->
        <div class="sl-preview-body" id="previewModalBody">
            <div class="sl-preview-loading">
                <div class="sl-preview-spinner"></div>
                <p>Decrypting &amp; preparing preview...</p>
            </div>
        </div>
    </div>
</div>

<script>
// ==========================================
// GLOBAL DRAWERS (RECYCLE BIN & APPEARANCE)
// ==========================================

function openRecycleBinDrawer() {
    closeUserDropdown();
    closeAllDrawers();
    const backdrop = document.getElementById('slGlobalBackdrop');
    const drawer = document.getElementById('slRecycleDrawer');
    if (backdrop && drawer) {
        backdrop.classList.add('active');
        drawer.classList.add('open');
        document.body.classList.add('sl-drawer-active');
        loadRecycledFilesAjax();
    }
}

function openAppearanceDrawer() {
    closeUserDropdown();
    closeAllDrawers();
    const backdrop = document.getElementById('slGlobalBackdrop');
    const drawer = document.getElementById('slAppearanceDrawer');
    if (backdrop && drawer) {
        backdrop.classList.add('active');
        drawer.classList.add('open');
        document.body.classList.add('sl-drawer-active');
        updateAppearanceDrawerUI();
    }
}

function closeAllDrawers() {
    const backdrop = document.getElementById('slGlobalBackdrop');
    const recycleDrawer = document.getElementById('slRecycleDrawer');
    const appearanceDrawer = document.getElementById('slAppearanceDrawer');
    if (backdrop) backdrop.classList.remove('active');
    if (recycleDrawer) recycleDrawer.classList.remove('open');
    if (appearanceDrawer) appearanceDrawer.classList.remove('open');
    document.body.classList.remove('sl-drawer-active');
}

function closeUserDropdown() {
    const userDropdown = document.getElementById('slUserDropdown');
    const userTrigger = document.getElementById('slUserTrigger');
    if (userDropdown) userDropdown.classList.remove('show');
    if (userTrigger) {
        userTrigger.setAttribute('aria-expanded', 'false');
        userTrigger.classList.remove('active');
    }
}

// Load and Render Recycled Files via AJAX
function loadRecycledFilesAjax() {
    const body = document.getElementById('recycleDrawerBody');
    const toolbar = document.getElementById('recycleDrawerToolbar');
    const pill = document.getElementById('recycleDrawerPill');
    if (!body) return;

    body.innerHTML = `
        <div class="sl-drawer-loading">
            <div class="sl-spinner"></div>
            <span>Loading deleted files...</span>
        </div>
    `;

    fetch('api.php?action=get_recycled_files')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderRecycledFilesList(data.files, data.count);
            } else {
                body.innerHTML = `<div class="sl-drawer-error">⚠️ ${escapeHtml(data.message || 'Failed to load recycle bin')}</div>`;
            }
        })
        .catch(err => {
            body.innerHTML = `<div class="sl-drawer-error">⚠️ Error loading files: ${escapeHtml(err.message)}</div>`;
        });
}

function renderRecycledFilesList(files, count) {
    const body = document.getElementById('recycleDrawerBody');
    const toolbar = document.getElementById('recycleDrawerToolbar');
    const pill = document.getElementById('recycleDrawerPill');
    if (!body) return;

    // Update global badge numbers if any exist on the page
    document.querySelectorAll('.recycled-badge-num, #recycledCountBadge').forEach(el => {
        el.textContent = count;
    });
    const settingsRecycleText = document.getElementById('settingsRecycleCountText');
    if (settingsRecycleText) {
        settingsRecycleText.textContent = count + ' Deleted File' + (count === 1 ? '' : 's');
    }

    if (count === 0 || !files || files.length === 0) {
        if (toolbar) toolbar.style.display = 'none';
        body.innerHTML = `
            <div class="sl-recycle-empty-drawer">
                <div class="empty-bin-icon">🗑️</div>
                <h4>Recycle Bin is Empty</h4>
                <p>No recently deleted files. Deleted items appear here and can be recovered back to your vault anytime.</p>
            </div>
        `;
        return;
    }

    if (toolbar) toolbar.style.display = 'flex';
    if (pill) pill.textContent = count + ' file' + (count === 1 ? '' : 's') + ' in bin';

    let html = '<div class="sl-drawer-file-list">';
    files.forEach(f => {
        html += `
            <div class="sl-drawer-file-card" id="recycled-item-${f.id}">
                <div class="df-main">
                    <div class="df-icon">${f.file_icon || '📄'}</div>
                    <div class="df-details">
                        <div class="df-name" title="${escapeHtml(f.file_name)}">${escapeHtml(f.file_name)}</div>
                        <div class="df-meta">
                            <span class="df-size">💾 ${escapeHtml(f.formatted_size)}</span>
                            <span class="df-dot">•</span>
                            <span class="df-date">📅 ${escapeHtml(f.formatted_deleted)}</span>
                        </div>
                    </div>
                </div>
                <div class="df-actions">
                    <button type="button" class="sl-drawer-btn restore" onclick="restoreFileAjax(${f.id}, '${escapeQuotes(f.file_name)}')" title="Recover file to locker">
                        <span>♻️ Recover</span>
                    </button>
                    <button type="button" class="sl-drawer-btn delete" onclick="permanentDeleteAjax(${f.id}, '${escapeQuotes(f.file_name)}')" title="Delete forever">
                        <span>🗑️ Delete Forever</span>
                    </button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    body.innerHTML = html;
}

function restoreFileAjax(fileId, fileName) {
    if (!confirm('Recover "' + fileName + '" back to your locker? It will immediately reappear in Dashboard and My Files.')) return;

    const item = document.getElementById('recycled-item-' + fileId);
    if (item) item.style.opacity = '0.5';

    fetch('api.php?action=restore_file&id=' + fileId, { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (item) {
                    item.style.transition = 'all 0.25s ease';
                    item.style.transform = 'scale(0.9)';
                    item.style.opacity = '0';
                    setTimeout(() => {
                        loadRecycledFilesAjax();
                    }, 250);
                } else {
                    loadRecycledFilesAjax();
                }
            } else {
                alert('Error: ' + data.message);
                if (item) item.style.opacity = '1';
            }
        })
        .catch(err => {
            alert('Failed to recover file: ' + err.message);
            if (item) item.style.opacity = '1';
        });
}

function permanentDeleteAjax(fileId, fileName) {
    if (!confirm('Permanently delete "' + fileName + '"? This will erase the encrypted file forever and CANNOT be undone!')) return;

    const item = document.getElementById('recycled-item-' + fileId);
    if (item) item.style.opacity = '0.5';

    fetch('api.php?action=permanent_delete_file&id=' + fileId, { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (item) {
                    item.style.transition = 'all 0.25s ease';
                    item.style.transform = 'scale(0.9)';
                    item.style.opacity = '0';
                    setTimeout(() => {
                        loadRecycledFilesAjax();
                    }, 250);
                } else {
                    loadRecycledFilesAjax();
                }
            } else {
                alert('Error: ' + data.message);
                if (item) item.style.opacity = '1';
            }
        })
        .catch(err => {
            alert('Failed to delete file: ' + err.message);
            if (item) item.style.opacity = '1';
        });
}

function emptyRecycleBinAjax() {
    if (!confirm('Are you sure you want to EMPTY the entire recycle bin? All deleted files will be permanently erased!')) return;

    fetch('api.php?action=empty_recycle_bin', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadRecycledFilesAjax();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => alert('Failed to empty recycle bin: ' + err.message));
}

// Appearance Theme and Accent System
function setThemeMode(mode) {
    localStorage.setItem('theme_mode', mode);
    document.cookie = `theme_mode=${mode};path=/;max-age=31536000;SameSite=Lax`;

    applyThemeSetting(mode);
    updateAppearanceDrawerUI();

    fetch(`api.php?action=save_theme&theme=${mode}`, { method: 'POST' }).catch(() => {});
}

function applyThemeSetting(mode) {
    if (mode === 'dark') {
        document.body.classList.add('dark-theme');
    } else {
        document.body.classList.remove('dark-theme');
    }
}

function updateAppearanceDrawerUI() {
    const currentMode = localStorage.getItem('theme_mode') || (document.cookie.match(/theme_mode=([^;]+)/)?.[1]) || 'light';

    document.querySelectorAll('.sl-theme-mode-item').forEach(el => el.classList.remove('selected'));
    const activeItem = document.getElementById('themeItem-' + currentMode);
    if (activeItem) activeItem.classList.add('selected');
}

function escapeHtml(str) {
    return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escapeQuotes(str) {
    return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Download function
function downloadFile(fileId) {
    window.location.href = 'api.php?action=download&id=' + fileId;
}

// Delete function (Soft Delete to Recycle Bin)
function deleteFile(fileId, fileName) {
    if (confirm('Move "' + fileName + '" to Recycle Bin? You can recover it anytime from the Recycle Bin.')) {
        window.location.href = '?section=' + encodeURIComponent(new URLSearchParams(window.location.search).get('section') || 'files') + '&action=delete&id=' + fileId + '&confirm=yes';
    }
}

// =========================================================
// IN-BROWSER FILE PREVIEW CONTROLLER (SAME WINDOW)
// =========================================================
function viewFile(fileId, fileName, fileSize) {
    const modal = document.getElementById('slFilePreviewModal');
    if (!modal) {
        window.open('api.php?action=view&id=' + fileId, '_blank');
        return;
    }

    const titleEl = document.getElementById('previewModalFileName');
    const metaEl = document.getElementById('previewModalFileMeta');
    const iconEl = document.getElementById('previewModalFileIcon');
    const bodyEl = document.getElementById('previewModalBody');
    const newTabBtn = document.getElementById('previewOpenTabBtn');
    const downloadBtn = document.getElementById('previewDownloadBtn');

    const ext = (fileName.split('.').pop() || '').toLowerCase();
    const viewUrl = 'api.php?action=view&id=' + fileId;
    const downloadUrl = 'api.php?action=download&id=' + fileId;

    if (titleEl) titleEl.textContent = fileName;
    if (metaEl) metaEl.textContent = (ext ? ext.toUpperCase() + ' File' : 'Vault File') + (fileSize ? ' • ' + fileSize : '');

    if (newTabBtn) {
        newTabBtn.onclick = () => window.open(viewUrl, '_blank');
    }
    if (downloadBtn) {
        downloadBtn.onclick = () => window.location.href = downloadUrl;
    }

    // Supported Extension Categories
    const videoExts = ['mp4', 'webm', 'ogg', 'mov', 'mkv'];
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
    const audioExts = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'flac'];
    const textExts = ['txt', 'md', 'json', 'js', 'html', 'css', 'php', 'py', 'sql', 'csv', 'xml', 'sh'];

    bodyEl.innerHTML = `
        <div class="sl-preview-loading">
            <div class="sl-preview-spinner"></div>
            <p>Decrypting &amp; preparing preview...</p>
        </div>
    `;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // 1. VIDEO (MP4, WEBM, OGG, MOV, MKV)
    if (videoExts.includes(ext)) {
        if (iconEl) iconEl.textContent = '🎬';
        const videoMime = ext === 'mov' ? 'video/quicktime' : (ext === 'mkv' ? 'video/x-matroska' : 'video/' + ext);
        bodyEl.innerHTML = `
            <div class="sl-video-player-wrap">
                <video id="slEmbeddedVideo" class="sl-embedded-video" controls controlsList="nodownload" autoplay playsinline preload="auto">
                    <source src="${viewUrl}" type="${videoMime}">
                    Your browser does not support HTML5 video playback.
                </video>
                <div class="sl-player-bar-label">
                    <span>🔒 AES-256 Decrypted Video Stream</span>
                    <span>HD Media Player</span>
                </div>
            </div>
        `;
    } 
    // 2. IMAGES
    else if (imageExts.includes(ext)) {
        if (iconEl) iconEl.textContent = '🖼️';
        bodyEl.innerHTML = `
            <div class="sl-image-preview-wrap">
                <img src="${viewUrl}" alt="${escapeHtml(fileName)}" class="sl-embedded-image">
            </div>
        `;
    } 
    // 3. PDF DOCUMENTS
    else if (ext === 'pdf') {
        if (iconEl) iconEl.textContent = '📄';
        bodyEl.innerHTML = `
            <div class="sl-pdf-preview-wrap">
                <iframe src="${viewUrl}#toolbar=1" class="sl-embedded-pdf" title="${escapeHtml(fileName)}"></iframe>
            </div>
        `;
    } 
    // 4. AUDIO FILES
    else if (audioExts.includes(ext)) {
        if (iconEl) iconEl.textContent = '🎵';
        bodyEl.innerHTML = `
            <div class="sl-audio-player-wrap">
                <div class="audio-disc-icon">🎧</div>
                <h4>${escapeHtml(fileName)}</h4>
                <p>Decrypted Audio Stream</p>
                <audio controls autoplay style="width: 100%; max-width: 440px; margin-top: 14px;">
                    <source src="${viewUrl}" type="audio/${ext === 'mp3' ? 'mpeg' : ext}">
                    Your browser does not support audio playback.
                </audio>
            </div>
        `;
    } 
    // 5. TEXT & SOURCE CODE FILES
    else if (textExts.includes(ext)) {
        if (iconEl) iconEl.textContent = '📝';
        fetch(viewUrl)
            .then(res => res.text())
            .then(text => {
                const lineCount = text.split('\n').length;
                bodyEl.innerHTML = `
                    <div class="sl-text-preview-wrap">
                        <div class="sl-text-preview-toolbar">
                            <span>📄 ${lineCount} lines &bull; UTF-8</span>
                            <button type="button" class="btn-copy-code" onclick="copyPreviewText()">📋 Copy Content</button>
                        </div>
                        <pre class="sl-code-block"><code id="previewTextContent">${escapeHtml(text)}</code></pre>
                    </div>
                `;
            })
            .catch(err => {
                bodyEl.innerHTML = `<div class="sl-preview-loading" style="color: #ef4444;"><p>Failed to load text preview: ${escapeHtml(err.message)}</p></div>`;
            });
    } 
    // 6. OTHER BINARY / ARCHIVES
    else {
        if (iconEl) iconEl.textContent = '📦';
        bodyEl.innerHTML = `
            <div class="sl-unsupported-preview">
                <div class="unsupported-icon">📁</div>
                <h3>${escapeHtml(fileName)}</h3>
                <p>In-browser preview is not supported for <strong>.${ext.toUpperCase()}</strong> files.</p>
                <button type="button" class="sl-hero-btn primary" onclick="window.location.href='${downloadUrl}'" style="margin: 0 auto;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Download Decrypted File</span>
                </button>
            </div>
        `;
    }
}

function closeFilePreviewModal() {
    const modal = document.getElementById('slFilePreviewModal');
    if (modal) {
        // Pause any video or audio playing inside modal
        const video = modal.querySelector('video');
        if (video) video.pause();
        const audio = modal.querySelector('audio');
        if (audio) audio.pause();

        const bodyEl = document.getElementById('previewModalBody');
        if (bodyEl) bodyEl.innerHTML = '';

        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function copyPreviewText() {
    const codeEl = document.getElementById('previewTextContent');
    if (!codeEl) return;
    navigator.clipboard.writeText(codeEl.textContent).then(() => {
        const btn = document.querySelector('.btn-copy-code');
        if (btn) {
            btn.textContent = '✅ Copied!';
            setTimeout(() => { btn.textContent = '📋 Copy Content'; }, 2000);
        }
    }).catch(() => {
        alert('Failed to copy text.');
    });
}

// Favorite toggle function
function toggleFavorite(fileId) {
    fetch('api.php?action=toggle_favorite&id=' + fileId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('section') === 'favorites' && !data.is_favorite) {
                    location.reload();
                } else {
                    const btn = document.querySelector(`[onclick*="toggleFavorite(${fileId})"]`);
                    if (btn) {
                        btn.classList.toggle('active', data.is_favorite);
                        const span = btn.querySelector('span');
                        if (span) span.textContent = data.is_favorite ? '★' : '☆';
                    }
                }
            }
        })
        .catch(error => console.error('Error toggling favorite:', error));
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Apply saved theme mode
    const savedMode = localStorage.getItem('theme_mode') || (document.cookie.match(/theme_mode=([^;]+)/)?.[1]) || 'light';
    applyThemeSetting(savedMode);

    // Auto-open drawer if requested via URL param ?open=recycle or ?open=appearance or ?tab=recycle
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open') === 'recycle' || urlParams.get('tab') === 'recycle') {
        openRecycleBinDrawer();
    } else if (urlParams.get('open') === 'appearance') {
        openAppearanceDrawer();
    }

    // User Google-style Account dropdown interaction
    const userMenu = document.getElementById('slUserMenu');
    const userTrigger = document.getElementById('slUserTrigger');
    const userDropdown = document.getElementById('slUserDropdown');
    const cardClose = document.getElementById('slCardClose');

    if (userTrigger && userDropdown) {
        userTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = userDropdown.classList.toggle('show');
            userTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            userTrigger.classList.toggle('active', isOpen);
        });

        if (cardClose) {
            cardClose.addEventListener('click', function(e) {
                e.stopPropagation();
                closeUserDropdown();
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (userMenu && !userMenu.contains(e.target)) {
                closeUserDropdown();
            }
        });
    }

    // Close preview modal on clicking backdrop
    const previewModal = document.getElementById('slFilePreviewModal');
    if (previewModal) {
        previewModal.addEventListener('click', function(e) {
            if (e.target === previewModal) {
                closeFilePreviewModal();
            }
        });
    }

    // Close drawers / dropdown / preview modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilePreviewModal();
            closeAllDrawers();
            closeUserDropdown();
        }
    });
});
</script>
<script src="js/pwa.js"></script>
</body>
</html>