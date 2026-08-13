<?php
// sections/settings.php - Settings with Tabbed Layout, Theme Mockup Cards, and In-Page Recycle Bin
$recycled_files = [];
try {
    $stmt = $db->prepare("SELECT id, file_name, file_type, file_size, uploaded_at, deleted_at FROM files WHERE user_id = ? AND deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $stmt->execute([$user_id]);
    $recycled_files = $stmt->fetchAll();
} catch (PDOException $e) {
    $recycled_files = [];
}
$recycled_count = count($recycled_files);

// Current active theme
$current_theme = $_COOKIE['theme_mode'] ?? $_COOKIE['theme'] ?? 'light';
if (!in_array($current_theme, ['light', 'dark'])) {
    $current_theme = 'light';
}
?>

<div class="sl-page-container sl-settings-page">
    <!-- PAGE HEADER -->
    <div class="sl-page-header">
        <div class="sl-page-title-group">
            <div class="sl-page-icon-badge purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </div>
            <div>
                <h2>Settings</h2>
                <p>Manage interface appearance, security preferences, and file recycle bin</p>
            </div>
        </div>
    </div>

    <!-- MAIN SETTINGS 2-COLUMN TABBED LAYOUT -->
    <div class="sl-settings-layout">
        <!-- LEFT COLUMN: NAVIGATION TABS -->
        <div class="sl-settings-tabs-sidebar">
            <button type="button" class="sl-settings-tab-btn active" id="tabBtn-appearance" onclick="switchSettingsTab('appearance')">
                <div class="tab-btn-content">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 17px; height: 17px;">
                        <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle>
                        <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle>
                        <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle>
                        <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle>
                        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.563C22 6.5 17.5 2 12 2z"></path>
                    </svg>
                    <span>Appearance</span>
                </div>
            </button>

            <button type="button" class="sl-settings-tab-btn" id="tabBtn-recycle" onclick="switchSettingsTab('recycle')">
                <div class="tab-btn-content">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 17px; height: 17px;">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                    <span>Recycle Bin</span>
                </div>
                <span class="sl-tab-count-badge" id="sideRecycleCountBadge" style="<?php echo $recycled_count > 0 ? '' : 'display:none;'; ?>">
                    <?php echo $recycled_count; ?>
                </span>
            </button>
        </div>

        <!-- RIGHT COLUMN: TAB CONTENT PANELS -->
        <div class="sl-settings-main-panel">
            <!-- 1. APPEARANCE PANEL -->
            <div class="sl-tab-content-panel active" id="panel-appearance">
                <div class="panel-section-header">
                    <h3>Interface Theme</h3>
                    <p>Customize the look and feel of Secure Locker across your browser sessions.</p>
                </div>

                <!-- THEME MOCKUP PREVIEW CARDS (EXACT VISUAL AS IN IMAGE) -->
                <div class="sl-theme-mockups-grid">
                    <!-- LIGHT MODE CARD -->
                    <div class="sl-theme-mockup-card <?php echo $current_theme === 'light' ? 'selected' : ''; ?>" 
                         id="mockup-light" 
                         onclick="selectSettingsTheme('light')">
                        
                        <!-- Mini Window Mockup -->
                        <div class="mockup-window light-window">
                            <div class="mockup-topbar light-topbar">
                                <div class="mockup-dots">
                                    <span></span><span></span><span></span>
                                </div>
                            </div>
                            <div class="mockup-body light-body">
                                <div class="mockup-sidebar light-sidebar"></div>
                                <div class="mockup-canvas light-canvas">
                                    <div class="mockup-lines">
                                        <div class="line w-60"></div>
                                        <div class="line w-40"></div>
                                        <div class="mockup-boxes">
                                            <div class="box"></div>
                                            <div class="box"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Label -->
                        <div class="mockup-info-row">
                            <div class="mockup-title">
                                <span class="theme-icon-svg sun-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 19px; height: 19px;">
                                        <circle cx="12" cy="12" r="5" fill="#fef3c7"></circle>
                                        <line x1="12" y1="1" x2="12" y2="3"></line>
                                        <line x1="12" y1="21" x2="12" y2="23"></line>
                                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                        <line x1="1" y1="12" x2="3" y2="12"></line>
                                        <line x1="21" y1="12" x2="23" y2="12"></line>
                                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                                    </svg>
                                </span>
                                <strong>Light Mode</strong>
                            </div>
                            <span class="mockup-check-icon">✓</span>
                        </div>
                    </div>

                    <!-- DARK MODE CARD -->
                    <div class="sl-theme-mockup-card <?php echo $current_theme === 'dark' ? 'selected' : ''; ?>" 
                         id="mockup-dark" 
                         onclick="selectSettingsTheme('dark')">
                        
                        <!-- Mini Window Mockup -->
                        <div class="mockup-window dark-window">
                            <div class="mockup-topbar dark-topbar">
                                <div class="mockup-dots">
                                    <span></span><span></span><span></span>
                                </div>
                            </div>
                            <div class="mockup-body dark-body">
                                <div class="mockup-sidebar dark-sidebar"></div>
                                <div class="mockup-canvas dark-canvas">
                                    <div class="mockup-lines">
                                        <div class="line w-60"></div>
                                        <div class="line w-40"></div>
                                        <div class="mockup-boxes">
                                            <div class="box"></div>
                                            <div class="box"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Label -->
                        <div class="mockup-info-row">
                            <div class="mockup-title">
                                <span class="theme-icon-svg moon-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" fill="#1e3a8a"></path>
                                    </svg>
                                </span>
                                <strong>Dark Mode</strong>
                            </div>
                            <span class="mockup-check-icon">✓</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. RECYCLE BIN PANEL -->
            <div class="sl-tab-content-panel" id="panel-recycle">
                <div class="panel-section-header with-actions">
                    <div>
                        <h3>Recycle Bin</h3>
                        <p>Recover recently deleted files back to your locker or permanently delete them forever.</p>
                    </div>
                    <?php if ($recycled_count > 0): ?>
                        <button type="button" class="btn-empty-recycle" id="emptyRecycleBtn" onclick="emptySettingsRecycleBin()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            <span>Empty Recycle Bin</span>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- EMPTY STATE (WHEN NO DELETED FILES) -->
                <div class="sl-empty-state" id="settingsEmptyBinState" style="<?php echo $recycled_count === 0 ? '' : 'display:none;'; ?> padding: 48px 20px;">
                    <div class="sl-empty-icon-wrap" style="background: #f1f5f9;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.8" style="width: 44px; height: 44px;">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </div>
                    <h4>Recycle Bin is Empty</h4>
                    <p>When you delete files from My Files, they will appear here safely for recovery before permanent deletion.</p>
                </div>

                <!-- DELETED FILES LIST -->
                <div class="sl-recycled-files-list" id="settingsRecycledList" style="<?php echo $recycled_count > 0 ? '' : 'display:none;'; ?>">
                    <?php foreach ($recycled_files as $file): ?>
                        <div class="sl-recycled-item" id="recycled-item-<?php echo $file['id']; ?>">
                            <div class="recycled-item-main">
                                <div class="recycled-item-icon">
                                    <?php echo getFileIcon($file['file_name']); ?>
                                </div>
                                <div class="recycled-item-info">
                                    <div class="recycled-item-name" title="<?php echo htmlspecialchars($file['file_name']); ?>">
                                        <?php echo htmlspecialchars($file['file_name']); ?>
                                    </div>
                                    <div class="recycled-item-meta">
                                        <span>💾 <?php echo formatFileSize($file['file_size']); ?></span>
                                        <span>•</span>
                                        <span>🗑️ Deleted <?php echo date('M d, Y', strtotime($file['deleted_at'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="recycled-item-actions">
                                <button type="button" class="btn-restore" onclick="restoreSettingFile(<?php echo $file['id']; ?>)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                        <polyline points="1 4 1 10 7 10"></polyline>
                                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                    </svg>
                                    <span>Restore</span>
                                </button>
                                <button type="button" class="btn-perm-delete" onclick="permanentDeleteSettingFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    <span>Delete Permanently</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* =========================================================
   SETTINGS TABBED LAYOUT & THEME MOCKUPS
========================================================= */
.sl-settings-page {
    max-width: 100%;
    margin: 0;
    width: 100%;
}

.sl-settings-page .sl-page-header {
    max-width: 1180px;
    margin: 0;
}

.sl-settings-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    max-width: 1180px;
    gap: 28px;
    align-items: start;
    justify-content: start;
    margin: 0;
}

/* SIDEBAR TABS */
.sl-settings-tabs-sidebar {
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 18px;
    padding: 12px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

body.dark-theme .sl-settings-tabs-sidebar {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.sl-settings-tab-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 650;
    color: #64748b;
    background: transparent;
    border: none;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.18s ease;
    text-align: left;
    width: 100%;
}

body.dark-theme .sl-settings-tab-btn {
    color: #94a3b8;
}

.tab-btn-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sl-settings-tab-btn:hover {
    background: #f8fafc;
    color: #0f172a;
}

body.dark-theme .sl-settings-tab-btn:hover {
    background: #1e293b;
    color: #f1f5f9;
}

.sl-settings-tab-btn.active {
    background: #eff6ff;
    color: #2563eb;
}

body.dark-theme .sl-settings-tab-btn.active {
    background: #172554;
    color: #60a5fa;
}

.sl-tab-count-badge {
    background: #ef4444;
    color: #ffffff;
    font-size: 11px;
    font-weight: 750;
    padding: 2px 7px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}

.sl-badge-pill-count {
    font-size: 12.5px;
    opacity: 0.85;
    margin-left: 2px;
}

/* MAIN PANEL */
.sl-settings-main-panel {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    min-height: 480px;
}

body.dark-theme .sl-settings-main-panel {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
}

.sl-tab-content-panel {
    display: none;
}

.sl-tab-content-panel.active {
    display: block;
    animation: fadeInTab 0.2s ease-in-out;
}

@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

.panel-section-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}

body.dark-theme .panel-section-header {
    border-bottom-color: #1e293b;
}

.panel-section-header.with-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.panel-section-header h3 {
    font-size: 19px;
    font-weight: 750;
    color: #0f172a;
    margin: 0 0 4px 0;
}

body.dark-theme .panel-section-header h3 {
    color: #f1f5f9;
}

.panel-section-header p {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

body.dark-theme .panel-section-header p {
    color: #94a3b8;
}

/* THEME MOCKUPS GRID */
.sl-theme-mockups-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(280px, 380px));
    gap: 22px;
    max-width: 820px;
    justify-content: start;
    margin: 0;
}

.sl-theme-mockup-card {
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
}

body.dark-theme .sl-theme-mockup-card {
    background: #111e38;
    border-color: #1e293b;
}

.sl-theme-mockup-card:hover {
    border-color: #93c5fd;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
}

body.dark-theme .sl-theme-mockup-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.4);
}

.sl-theme-mockup-card.selected {
    border-color: #2563eb;
    background: #f0f7ff;
    box-shadow: 0 4px 20px rgba(37, 99, 235, 0.15);
}

body.dark-theme .sl-theme-mockup-card.selected {
    border-color: #38bdf8;
    background: #152549;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5);
}

/* MINI WINDOW ILLUSTRATION */
.mockup-window {
    border-radius: 10px;
    overflow: hidden;
    height: 110px;
    display: flex;
    flex-direction: column;
    margin-bottom: 14px;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

/* Light Window Styles */
.light-window {
    background: #f8fafc;
}

.light-topbar {
    height: 16px;
    background: #2563eb;
    display: flex;
    align-items: center;
    padding: 0 8px;
}

.mockup-dots {
    display: flex;
    gap: 3px;
}

.mockup-dots span {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.7);
}

.light-body {
    flex: 1;
    display: flex;
}

.light-sidebar {
    width: 26px;
    background: #0f172a;
}

.light-canvas {
    flex: 1;
    background: #f8fafc;
    padding: 10px;
}

/* Dark Window Styles */
.dark-window {
    background: #0f172a;
    border-color: rgba(255, 255, 255, 0.08);
}

.dark-topbar {
    height: 16px;
    background: #1e293b;
    display: flex;
    align-items: center;
    padding: 0 8px;
}

.dark-body {
    flex: 1;
    display: flex;
}

.dark-sidebar {
    width: 26px;
    background: #091122;
}

.dark-canvas {
    flex: 1;
    background: #0f172a;
    padding: 10px;
}

.mockup-lines .line {
    height: 4px;
    border-radius: 2px;
    margin-bottom: 6px;
}

.light-canvas .line { background: #cbd5e1; }
.dark-canvas .line { background: #334155; }

.line.w-60 { width: 60%; }
.line.w-40 { width: 40%; }

.mockup-boxes {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}

.mockup-boxes .box {
    width: 24px;
    height: 20px;
    border-radius: 4px;
}

.light-canvas .box { background: #e2e8f0; }
.dark-canvas .box { background: #1e293b; }

/* Mockup Info */
.mockup-info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 2px;
}

.mockup-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
}

body.dark-theme .mockup-title {
    color: #f1f5f9;
}

.theme-icon-svg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
}

.theme-icon-svg.sun-icon {
    background: #fef3c7;
}

body.dark-theme .theme-icon-svg.sun-icon {
    background: #78350f;
}

.theme-icon-svg.moon-icon {
    background: #dbeafe;
}

body.dark-theme .theme-icon-svg.moon-icon {
    background: #1e3a8a;
}

.mockup-check-icon {
    font-size: 14px;
    font-weight: 800;
    color: transparent;
    transition: color 0.15s ease;
}

.sl-theme-mockup-card.selected .mockup-check-icon {
    color: #2563eb;
}

body.dark-theme .sl-theme-mockup-card.selected .mockup-check-icon {
    color: #38bdf8;
}

/* RECYCLE BIN PANEL STYLES */
.btn-empty-recycle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fca5a5;
    border-radius: 10px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.18s ease;
}

body.dark-theme .btn-empty-recycle {
    background: #2d1313;
    color: #fca5a5;
    border-color: #7f1d1d;
}

.btn-empty-recycle:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

.sl-recycled-files-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sl-recycled-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    transition: all 0.2s ease;
    gap: 16px;
}

body.dark-theme .sl-recycled-item {
    background: #111e38;
    border-color: #1e293b;
}

.sl-recycled-item:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

body.dark-theme .sl-recycled-item:hover {
    border-color: #334155;
}

.recycled-item-main {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.recycled-item-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

body.dark-theme .recycled-item-icon {
    background: #091122;
    border-color: #1e293b;
}

.recycled-item-info {
    flex: 1;
    min-width: 0;
}

.recycled-item-name {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

body.dark-theme .recycled-item-name {
    color: #f1f5f9;
}

.recycled-item-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

body.dark-theme .recycled-item-meta {
    color: #94a3b8;
}

.recycled-item-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.btn-restore {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    background: #10b981;
    color: #ffffff;
    border: none;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.18s ease;
}

.btn-restore:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-perm-delete {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    background: transparent;
    color: #dc2626;
    border: 1px solid #fca5a5;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.18s ease;
}

body.dark-theme .btn-perm-delete {
    color: #fca5a5;
    border-color: #7f1d1d;
}

.btn-perm-delete:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

/* RESPONSIVE */
@media (max-width: 860px) {
    .sl-settings-layout {
        grid-template-columns: 1fr;
    }

    .sl-settings-tabs-sidebar {
        flex-direction: row;
    }

    .sl-recycled-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .recycled-item-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<script>
function switchSettingsTab(tabName) {
    // Update Tab Buttons
    document.querySelectorAll('.sl-settings-tab-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById('tabBtn-' + tabName);
    if (activeBtn) activeBtn.classList.add('active');

    // Update Panels
    document.querySelectorAll('.sl-tab-content-panel').forEach(panel => panel.classList.remove('active'));
    const activePanel = document.getElementById('panel-' + tabName);
    if (activePanel) activePanel.classList.add('active');
}

function selectSettingsTheme(mode) {
    // Update mockups card selection
    document.querySelectorAll('.sl-theme-mockup-card').forEach(card => card.classList.remove('selected'));
    const activeCard = document.getElementById('mockup-' + mode);
    if (activeCard) activeCard.classList.add('selected');

    // Apply globally via dashboard theme controller
    if (typeof setThemeMode === 'function') {
        setThemeMode(mode);
    } else {
        localStorage.setItem('theme_mode', mode);
        document.cookie = `theme_mode=${mode};path=/;max-age=31536000;SameSite=Lax`;
        if (mode === 'dark') {
            document.body.classList.add('dark-theme');
        } else {
            document.body.classList.remove('dark-theme');
        }
        fetch(`api.php?action=save_theme&theme=${mode}`, { method: 'POST' }).catch(() => {});
    }
}

async function restoreSettingFile(fileId) {
    try {
        const formData = new FormData();
        formData.append('action', 'restore_file');
        formData.append('id', fileId);

        const res = await fetch('api.php?action=restore_file', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            const item = document.getElementById('recycled-item-' + fileId);
            if (item) {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    item.remove();
                    updateSettingsRecycleCounts();
                }, 300);
            }
        } else {
            alert(data.message || 'Failed to restore file');
        }
    } catch (err) {
        alert('Network error while restoring file: ' + err.message);
    }
}

async function permanentDeleteSettingFile(fileId, fileName) {
    if (!confirm(`Are you sure you want to PERMANENTLY delete "${fileName}"?\n\nThis file will be completely erased from disk storage and cannot be recovered.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'permanent_delete_file');
        formData.append('id', fileId);

        const res = await fetch('api.php?action=permanent_delete_file', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            const item = document.getElementById('recycled-item-' + fileId);
            if (item) {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    item.remove();
                    updateSettingsRecycleCounts();
                }, 300);
            }
        } else {
            alert(data.message || 'Failed to delete file');
        }
    } catch (err) {
        alert('Network error while deleting file: ' + err.message);
    }
}

async function emptySettingsRecycleBin() {
    if (!confirm("Are you sure you want to EMPTY the entire Recycle Bin?\n\nAll deleted files will be permanently erased forever.")) {
        return;
    }

    try {
        const res = await fetch('api.php?action=empty_recycle_bin', { method: 'POST' });
        const data = await res.json();

        if (data.success) {
            const list = document.getElementById('settingsRecycledList');
            if (list) list.innerHTML = '';
            updateSettingsRecycleCounts(0);
        } else {
            alert(data.message || 'Failed to empty recycle bin');
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
}

function updateSettingsRecycleCounts(forceCount) {
    let remaining = 0;
    if (typeof forceCount === 'number') {
        remaining = forceCount;
    } else {
        remaining = document.querySelectorAll('.sl-recycled-item').length;
    }

    // Update Header Pill
    const pill = document.getElementById('headerRecyclePill');
    if (pill) pill.textContent = `(${remaining})`;

    // Update Sidebar Tab Badge
    const sideBadge = document.getElementById('sideRecycleCountBadge');
    if (sideBadge) {
        sideBadge.textContent = remaining;
        sideBadge.style.display = remaining > 0 ? '' : 'none';
    }

    // Update Empty State & Empty Button
    const emptyState = document.getElementById('settingsEmptyBinState');
    const list = document.getElementById('settingsRecycledList');
    const emptyBtn = document.getElementById('emptyRecycleBtn');

    if (remaining === 0) {
        if (emptyState) emptyState.style.display = '';
        if (list) list.style.display = 'none';
        if (emptyBtn) emptyBtn.style.display = 'none';
    } else {
        if (emptyState) emptyState.style.display = 'none';
        if (list) list.style.display = '';
        if (emptyBtn) emptyBtn.style.display = '';
    }

    // Sync with slide drawer count if open
    const drawerBadge = document.getElementById('recycleDrawerBadge');
    if (drawerBadge) drawerBadge.textContent = `${remaining} File${remaining === 1 ? '' : 's'}`;
}

document.addEventListener('DOMContentLoaded', function() {
    // If URL has ?tab=recycle or opened via Recycle Bin trigger
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'recycle' || window.location.hash === '#recycle') {
        switchSettingsTab('recycle');
    }
});
</script>