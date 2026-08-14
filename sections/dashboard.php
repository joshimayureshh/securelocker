<?php
// Dashboard section content
$formatted_name = ucwords(strtolower($user_name));
$name_parts = explode(' ', $formatted_name);
$first_name = !empty($name_parts[0]) ? $name_parts[0] : $formatted_name;
?>

<!-- =========================
     WELCOME HERO BANNER
========================= -->
<section class="sl-welcome-banner">
    <div class="sl-welcome-content">
        <div class="sl-welcome-badge">
            <span class="badge-dot"></span>
            <span>AES-256 Encrypted Cloud Vault</span>
        </div>
        <h2>
            <span class="welcome-hand">👋</span>
            Welcome back, <?php echo htmlspecialchars($first_name); ?>!
        </h2>
        <p>
            Your private vault is active and secured. Manage, upload, and protect your files with zero-knowledge encryption.
        </p>
        <div class="sl-welcome-actions">
            <a href="?section=upload" class="sl-hero-btn primary">
                <svg class="btn-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 17px; height: 17px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                <span>Upload New File</span>
            </a>
            <a href="?section=files" class="sl-hero-btn secondary">
                <svg class="btn-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 17px; height: 17px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <span>Browse All Files</span>
            </a>
        </div>
    </div>
    <div class="sl-welcome-art">
        <div class="sl-shield-container">
            <div class="sl-shield-glow"></div>
            <div class="sl-shield-card">
                <div class="sl-shield-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 38px; height: 38px; filter: drop-shadow(0 2px 8px rgba(56, 189, 248, 0.4));"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
                </div>
                <div class="sl-shield-label">Protected</div>
                <div class="sl-shield-sub">256-Bit SSL/AES</div>
            </div>
        </div>
    </div>
</section>

<!-- =========================
     STORAGE & STATS OVERVIEW
========================= -->
<section class="sl-section">
    <div class="sl-section-title">
        <h3>
            <span class="sl-title-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            </span>
            Vault Overview
        </h3>
        <span class="sl-section-subtitle">Real-time storage analytics</span>
    </div>

    <div class="sl-stat-grid">
        <!-- TOTAL FILES -->
        <a href="?section=files" class="sl-stat-card">
            <div class="sl-stat-icon-wrap blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <div class="sl-stat-info">
                <div class="sl-stat-number"><?php echo number_format($stats['file_count']); ?></div>
                <div class="sl-stat-label">Total Files</div>
                <div class="sl-stat-trend">Stored in cloud</div>
            </div>
        </a>

        <!-- STORAGE USED -->
        <div class="sl-stat-card">
            <div class="sl-stat-icon-wrap purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
            </div>
            <div class="sl-stat-info">
                <div class="sl-stat-number"><?php echo formatFileSize($stats['total_size']); ?></div>
                <div class="sl-stat-label">Storage Used</div>
                <div class="sl-stat-trend">Encrypted volume</div>
            </div>
        </div>

        <!-- FAVORITES -->
        <a href="?section=favorites" class="sl-stat-card">
            <div class="sl-stat-icon-wrap yellow">
                <svg viewBox="0 0 24 24" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </div>
            <div class="sl-stat-info">
                <div class="sl-stat-number"><?php echo number_format($stats['favorite_count']); ?></div>
                <div class="sl-stat-label">Starred Files</div>
                <div class="sl-stat-trend">Quick access</div>
            </div>
        </a>

        <!-- SECURITY STATUS -->
        <div class="sl-stat-card">
            <div class="sl-stat-icon-wrap green">
                <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            <div class="sl-stat-info">
                <div class="sl-stat-number" style="font-size: 20px; color: #10b981;">Active</div>
                <div class="sl-stat-label">Encryption</div>
                <div class="sl-stat-trend">AES-256 CBC</div>
            </div>
        </div>
    </div>
</section>

<!-- =========================
     RECENT FILES
========================= -->
<section class="sl-section recent-section">
    <div class="sl-section-title recent-title">
        <div>
            <h3>
                <span class="sl-title-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                Recent Files
            </h3>
            <span class="sl-section-subtitle">Quick access to your latest uploads</span>
        </div>
        <a href="?section=files" class="sl-view-files">
            <span>View All Files</span>
            <span class="arrow">→</span>
        </a>
    </div>

    <?php if (empty($recent_files)): ?>
        <!-- EMPTY STATE -->
        <div class="sl-empty-state">
            <div class="sl-empty-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="1.8" style="width: 44px; height: 44px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <h4>No Files Uploaded Yet</h4>
            <p>Your secure locker is ready. Upload your first document, photo, or archive to get started.</p>
            <a href="?section=upload" class="sl-hero-btn primary">
                <svg class="btn-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                <span>Upload Your First File</span>
            </a>
        </div>
    <?php else: ?>
        <!-- MODERN TABLE -->
        <div class="sl-file-table-wrapper">
            <div class="sl-file-table">
                <div class="sl-table-header">
                    <div class="th-file">FILE NAME</div>
                    <div class="th-size">SIZE</div>
                    <div class="th-date">UPLOADED ON</div>
                    <div class="th-actions">ACTIONS</div>
                </div>

                <?php foreach ($recent_files as $file): ?>
                    <div class="sl-file-row">
                        <!-- FILE NAME -->
                        <div class="sl-file-name">
                            <div class="sl-file-type-icon">
                                <?php echo getFileIcon($file['file_name']); ?>
                            </div>
                            <div class="sl-file-name-text" title="<?php echo htmlspecialchars($file['file_name']); ?>">
                                <span class="name-main"><?php echo htmlspecialchars($file['file_name']); ?></span>
                                <span class="name-sub"><?php echo htmlspecialchars($file['file_type'] ?? 'Binary'); ?></span>
                            </div>
                        </div>

                        <!-- SIZE -->
                        <div class="sl-file-size">
                            <span class="size-pill"><?php echo formatFileSize($file['file_size']); ?></span>
                        </div>

                        <!-- DATE -->
                        <div class="sl-file-date">
                            <span class="date-main"><?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?></span>
                            <span class="date-time"><?php echo date('h:i A', strtotime($file['uploaded_at'])); ?></span>
                        </div>

                        <!-- ACTIONS -->
                        <div class="sl-file-actions">
                            <button
                                type="button"
                                class="sl-action-btn view"
                                onclick="viewFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>', '<?php echo formatFileSize($file['file_size']); ?>')"
                                title="View in Browser">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>

                            <button
                                type="button"
                                class="sl-action-btn download"
                                onclick="downloadFile(<?php echo $file['id']; ?>)"
                                title="Download File">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            </button>

                            <button
                                type="button"
                                class="sl-action-btn delete"
                                onclick="deleteFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>')"
                                title="Delete File">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
function downloadFile(fileId) {
    window.location.href = 'api.php?action=download&id=' + fileId;
}

function deleteFile(fileId, fileName) {
    if (confirm('Move "' + fileName + '" to Recycle Bin? You can restore it later from Settings.')) {
        window.location.href = '?section=dashboard&action=delete&id=' + fileId + '&confirm=yes';
    }
}
</script>