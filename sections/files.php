<?php
// sections/files.php - Enhanced Modern UI

// Get all active files for the user (excluding recycled files)
try {
    $stmt = $db->prepare("SELECT id, file_name, file_type, file_size, is_favorite, uploaded_at FROM files WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY uploaded_at DESC");
    $stmt->execute([':user_id' => $user_id]);
    $all_files = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_files = [];
}
$file_count = count($all_files);

// Check count of recycled files
$recycled_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM files WHERE user_id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$user_id]);
    $recycled_count = $stmt->fetch()['count'];
} catch (PDOException $e) {}
?>

<div class="sl-page-container">
    <!-- PAGE HEADER -->
    <div class="sl-page-header">
        <div class="sl-page-title-group">
            <div class="sl-page-icon-badge blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <div>
                <h2>My Files</h2>
                <p>Manage, preview, and download your encrypted files</p>
            </div>
        </div>

        <div class="sl-page-actions">
            <?php if ($recycled_count > 0): ?>
                <button type="button" class="sl-hero-btn secondary" onclick="openRecycleBinDrawer()" title="View recently deleted files">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    <span>Recycle Bin (<span class="recycled-badge-num"><?php echo $recycled_count; ?></span>)</span>
                </button>
            <?php endif; ?>
            <a href="?section=upload" class="sl-hero-btn primary">
                <svg class="btn-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                <span>Upload File</span>
            </a>
        </div>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="sl-toolbar-card">
        <div class="sl-search-wrap">
            <span class="sl-search-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 17px; height: 17px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </span>
            <input type="text" id="searchInput" class="sl-search-field" placeholder="Search files by name..." autocomplete="off">
            <button type="button" id="searchClear" class="sl-search-clear" style="display: none;" title="Clear search">&times;</button>
        </div>

        <div class="sl-filter-group">
            <button type="button" class="sl-filter-btn active" data-filter="all">All (<?php echo $file_count; ?>)</button>
            <button type="button" class="sl-filter-btn" data-filter="image">
                <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg> Images
            </button>
            <button type="button" class="sl-filter-btn" data-filter="pdf">
                <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> Documents
            </button>
            <button type="button" class="sl-filter-btn" data-filter="media">
                <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg> Media
            </button>
            <button type="button" class="sl-filter-btn" data-filter="archive">
                <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect></svg> Archives
            </button>
        </div>

        <div class="sl-view-toggle">
            <button type="button" class="sl-toggle-btn active" id="viewGridBtn" title="Grid View">▦</button>
            <button type="button" class="sl-toggle-btn" id="viewListBtn" title="List View">☰</button>
        </div>
    </div>

    <?php if (empty($all_files)): ?>
        <!-- EMPTY STATE -->
        <div class="sl-empty-state">
            <div class="sl-empty-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="1.8" style="width: 44px; height: 44px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <h4>No Files in Your Locker</h4>
            <p>Your secure locker is currently empty. Start uploading your documents, photos, or data to keep them safe.</p>
            <a href="?section=upload" class="sl-hero-btn primary">
                <svg class="btn-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                <span>Upload Files Now</span>
            </a>
        </div>
    <?php else: ?>
        <!-- FILES GRID VIEW -->
        <div class="sl-files-grid" id="filesGrid">
            <?php foreach ($all_files as $file): 
                $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                $category = 'other';
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) $category = 'image';
                elseif (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'csv', 'xlsx', 'xls', 'ppt', 'pptx'])) $category = 'pdf';
                elseif (in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'mp3', 'wav', 'ogg'])) $category = 'media';
                elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) $category = 'archive';
            ?>
                <div class="sl-card-item file-card <?php echo $file['is_favorite'] ? 'is-starred' : ''; ?>" 
                     data-name="<?php echo htmlspecialchars(strtolower($file['file_name'])); ?>"
                     data-cat="<?php echo $category; ?>">
                    
                    <div class="sl-card-top-row">
                        <span class="sl-type-badge"><?php echo strtoupper($ext ?: 'FILE'); ?></span>
                        <button type="button" 
                                class="sl-star-btn <?php echo $file['is_favorite'] ? 'active' : ''; ?>" 
                                onclick="toggleFavorite(<?php echo $file['id']; ?>)" 
                                title="<?php echo $file['is_favorite'] ? 'Remove from favorites' : 'Add to favorites'; ?>">
                            <span><?php echo $file['is_favorite'] ? '★' : '☆'; ?></span>
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
                                class="sl-btn-action view" 
                                title="View in Browser">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> View
                        </button>
                        <button type="button" 
                                onclick="openShareModal(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>', '<?php echo formatFileSize($file['file_size']); ?>')" 
                                class="sl-btn-action share" 
                                title="Share Expiring Link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; margin-right: 4px; display: inline-block; vertical-align: middle;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg> Share
                        </button>
                        <button type="button" 
                                onclick="openRenameModal(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>')" 
                                class="sl-btn-action rename" 
                                title="Rename File">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button type="button" 
                                onclick="downloadFile(<?php echo $file['id']; ?>)" 
                                class="sl-btn-action download" 
                                title="Download File">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        </button>
                        <button type="button" 
                                onclick="deleteFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>')" 
                                class="sl-btn-action delete" 
                                title="Delete File">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- NO MATCH SEARCH STATE (HIDDEN BY DEFAULT) -->
        <div id="noMatchState" class="sl-empty-state" style="display: none; padding: 40px 20px;">
            <div class="sl-empty-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2" style="width: 36px; height: 36px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
            <h4>No Matching Files Found</h4>
            <p>Try searching for a different keyword or reset the category filter.</p>
        </div>
    <?php endif; ?>
</div>

<!-- RENAME FILE MODAL -->
<div id="renameFileModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="title-icon-badge blue" style="width: 38px; height: 38px; border-radius: 10px; background: #dbeafe; display: flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="width: 18px; height: 18px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                </div>
                <h2 style="font-size: 19px; font-weight: 750; margin: 0;">Rename File</h2>
            </div>
            <button type="button" class="modal-close" onclick="closeRenameModal()">&times;</button>
        </div>
        
        <form id="renameFileForm" onsubmit="submitRenameFile(event)" style="margin-top: 16px;">
            <input type="hidden" id="renameFileId" name="id" value="">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; color: #334155;">New File Name <span style="color: #dc2626;">*</span></label>
                <input type="text" id="renameFileNameInput" name="new_name" required placeholder="Enter new file name" class="sl-search-field" style="width: 100%; height: 44px; font-size: 14px; padding: 0 14px; border-radius: 12px; border: 1.5px solid #cbd5e1; outline: none;">
                <small style="display: block; margin-top: 6px; font-size: 11.5px; color: #64748b;">The original file extension is kept automatically if not specified.</small>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="closeRenameModal()">Cancel</button>
                <button type="submit" class="sl-hero-btn primary" id="renameSubmitBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline></svg>
                    <span>Save Name</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchClear = document.getElementById('searchClear');
    const filterBtns = document.querySelectorAll('.sl-filter-btn');
    const fileCards = document.querySelectorAll('.file-card');
    const noMatchState = document.getElementById('noMatchState');
    const viewGridBtn = document.getElementById('viewGridBtn');
    const viewListBtn = document.getElementById('viewListBtn');
    const filesGrid = document.getElementById('filesGrid');

    let currentFilter = 'all';
    let currentSearch = '';

    function applyFilterAndSearch() {
        let visibleCount = 0;
        fileCards.forEach(card => {
            const name = card.dataset.name || '';
            const cat = card.dataset.cat || '';

            const matchesFilter = (currentFilter === 'all') || (cat === currentFilter);
            const matchesSearch = !currentSearch || name.includes(currentSearch);

            if (matchesFilter && matchesSearch) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noMatchState) {
            noMatchState.style.display = (visibleCount === 0 && fileCards.length > 0) ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            currentSearch = this.value.trim().toLowerCase();
            if (searchClear) {
                searchClear.style.display = currentSearch.length > 0 ? 'block' : 'none';
            }
            applyFilterAndSearch();
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                currentSearch = '';
                searchClear.style.display = 'none';
                searchInput.focus();
                applyFilterAndSearch();
            }
        });
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter || 'all';
            applyFilterAndSearch();
        });
    });

    if (viewGridBtn && viewListBtn && filesGrid) {
        viewGridBtn.addEventListener('click', function() {
            viewGridBtn.classList.add('active');
            viewListBtn.classList.remove('active');
            filesGrid.classList.remove('list-view');
        });

        viewListBtn.addEventListener('click', function() {
            viewListBtn.classList.add('active');
            viewGridBtn.classList.remove('active');
            filesGrid.classList.add('list-view');
        });
    }
});

function downloadFile(fileId) {
    window.location.href = 'api.php?action=download&id=' + fileId;
}

function toggleFavorite(fileId) {
    window.location.href = '?section=files&action=toggle_favorite&id=' + fileId;
}

function deleteFile(fileId, fileName) {
    if (confirm('Move "' + fileName + '" to Recycle Bin? You can restore it later from Settings.')) {
        window.location.href = '?section=files&action=delete&id=' + fileId + '&confirm=yes';
    }
}

/* ===== RENAME FILE MODAL CONTROLLER ===== */
function openRenameModal(fileId, currentName) {
    document.getElementById('renameFileId').value = fileId;
    document.getElementById('renameFileNameInput').value = currentName;
    const modal = document.getElementById('renameFileModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    setTimeout(() => {
        const input = document.getElementById('renameFileNameInput');
        input.focus();
        const dotIndex = currentName.lastIndexOf('.');
        if (dotIndex > 0) {
            input.setSelectionRange(0, dotIndex);
        } else {
            input.select();
        }
    }, 100);
}

function closeRenameModal() {
    const modal = document.getElementById('renameFileModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

async function submitRenameFile(e) {
    e.preventDefault();
    const fileId = document.getElementById('renameFileId').value;
    const newName = document.getElementById('renameFileNameInput').value.trim();
    const submitBtn = document.getElementById('renameSubmitBtn');

    if (!newName) return;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Saving...</span>';

    try {
        const formData = new FormData();
        formData.append('action', 'rename_file');
        formData.append('id', fileId);
        formData.append('new_name', newName);

        const res = await fetch('api.php?action=rename_file', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            const renameBtn = document.querySelector(`button[onclick*="openRenameModal(${fileId}"]`);
            if (renameBtn) {
                const card = renameBtn.closest('.file-card');
                if (card) {
                    const nameEl = card.querySelector('.sl-card-filename');
                    if (nameEl) {
                        nameEl.textContent = data.file_name;
                        nameEl.title = data.file_name;
                    }
                    card.dataset.name = data.file_name.toLowerCase();

                    renameBtn.setAttribute('onclick', `openRenameModal(${fileId}, ${JSON.stringify(data.file_name)})`);
                    const viewBtn = card.querySelector('button[onclick*="viewFile"]');
                    if (viewBtn) {
                        viewBtn.setAttribute('onclick', `viewFile(${fileId}, ${JSON.stringify(data.file_name)})`);
                    }
                    const deleteBtn = card.querySelector('button[onclick*="deleteFile"]');
                    if (deleteBtn) {
                        deleteBtn.setAttribute('onclick', `deleteFile(${fileId}, ${JSON.stringify(data.file_name)})`);
                    }

                    if (data.file_icon) {
                        const iconEl = card.querySelector('.sl-card-file-icon');
                        if (iconEl) iconEl.innerHTML = data.file_icon;
                    }

                    card.style.transition = 'all 0.3s ease';
                    card.style.boxShadow = '0 0 0 3px #10b981';
                    setTimeout(() => {
                        card.style.boxShadow = '';
                    }, 1500);
                }
            } else {
                location.reload();
            }

            closeRenameModal();
        } else {
            alert(data.message || 'Failed to rename file');
        }
    } catch (err) {
        alert('Network error while renaming file: ' + err.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline></svg><span>Save Name</span>';
    }
}

window.addEventListener('click', function(event) {
    const modal = document.getElementById('renameFileModal');
    if (event.target === modal) {
        closeRenameModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('renameFileModal');
        if (modal && modal.style.display === 'block') {
            closeRenameModal();
        }
    }
});
</script>