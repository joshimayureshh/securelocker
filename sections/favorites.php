<?php
// sections/favorites.php - Enhanced Modern UI
try {
    $stmt = $db->prepare("SELECT id, file_name, file_type, file_size, uploaded_at FROM files WHERE user_id = ? AND is_favorite = true AND deleted_at IS NULL ORDER BY uploaded_at DESC");
    $stmt->execute([$user_id]);
    $favorite_files = $stmt->fetchAll();
} catch (PDOException $e) {
    $favorite_files = [];
}

$fav_count = count($favorite_files);
?>

<div class="sl-page-container">
    <!-- PAGE HEADER -->
    <div class="sl-page-header">
        <div class="sl-page-title-group">
            <div class="sl-page-icon-badge yellow">
                <svg viewBox="0 0 24 24" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </div>
            <div>
                <h2>Favorite Files</h2>
                <p>Quick access to your most important starred files (<?php echo $fav_count; ?>)</p>
            </div>
        </div>

        <div class="sl-page-actions">
            <a href="?section=files" class="sl-hero-btn secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <span>Browse All Files</span>
            </a>
        </div>
    </div>

    <?php if (empty($favorite_files)): ?>
        <!-- EMPTY STATE -->
        <div class="sl-empty-state">
            <div class="sl-empty-icon-wrap yellow">
                <svg viewBox="0 0 24 24" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5" style="width: 44px; height: 44px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </div>
            <h4>No Starred Files Yet</h4>
            <p>You can star important documents or photos in <strong>My Files</strong> to access them instantly from here.</p>
            <a href="?section=files" class="sl-hero-btn primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <span>Go to My Files</span>
            </a>
        </div>
    <?php else: ?>
        <!-- FAVORITES GRID -->
        <div class="sl-files-grid">
            <?php foreach ($favorite_files as $file): 
                $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
            ?>
                <div class="sl-card-item file-card is-starred">
                    <div class="sl-card-top-row">
                        <span class="sl-type-badge gold"><?php echo strtoupper($ext ?: 'FILE'); ?></span>
                        <button type="button" 
                                class="sl-star-btn active" 
                                onclick="toggleFavorite(<?php echo $file['id']; ?>)" 
                                title="Remove from favorites">
                            <svg viewBox="0 0 24 24" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5" style="width: 16px; height: 16px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        </button>
                    </div>

                    <div class="sl-card-icon-area">
                        <div class="sl-card-file-icon">
                            <?php echo getFileIcon($file['file_name']); ?>
                        </div>
                    </div>

                    <div class="sl-card-details">
                        <div class="sl-card-filename" title="<?php echo htmlspecialchars($file['file_name']); ?>">
                            <?php echo htmlspecialchars($file['file_name']); ?>
                        </div>
                        <div class="sl-card-meta">
                            <span class="meta-size">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 3px;"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                                <?php echo formatFileSize($file['file_size']); ?>
                            </span>
                            <span class="meta-date">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 3px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="sl-card-actions">
                        <button type="button" 
                                onclick="viewFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>', '<?php echo formatFileSize($file['file_size']); ?>')" 
                                class="sl-btn-card-view" 
                                title="View in Browser">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 15px; height: 15px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <span>View</span>
                        </button>

                        <div class="sl-card-menu-wrap">
                            <button type="button" 
                                    class="sl-btn-card-dots" 
                                    onclick="toggleCardMenu(event, this)" 
                                    title="More options"
                                    aria-label="More options">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px;"><circle cx="12" cy="5" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="12" cy="19" r="2"></circle></svg>
                            </button>
                            <div class="sl-card-dropdown-menu">
                                <button type="button" class="sl-dropdown-menu-item" onclick="openShareModal(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>', '<?php echo formatFileSize($file['file_size']); ?>')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                    <span>Share Link</span>
                                </button>
                                <button type="button" class="sl-dropdown-menu-item" onclick="downloadFile(<?php echo $file['id']; ?>)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    <span>Download</span>
                                </button>
                                <button type="button" class="sl-dropdown-menu-item" onclick="toggleFavorite(<?php echo $file['id']; ?>)">
                                    <svg viewBox="0 0 24 24" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    <span>Remove Favorite</span>
                                </button>
                                <div class="sl-dropdown-menu-divider"></div>
                                <button type="button" class="sl-dropdown-menu-item danger" onclick="deleteFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    <span>Move to Trash</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function downloadFile(fileId) {
    window.location.href = 'api.php?action=download&id=' + fileId;
}

function toggleFavorite(fileId) {
    window.location.href = '?section=favorites&action=toggle_favorite&id=' + fileId;
}
</script>