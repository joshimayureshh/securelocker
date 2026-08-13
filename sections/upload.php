<?php
// sections/upload.php - Enhanced Modern UI with AES-256 Encryption
$upload_error = '';
$upload_success = '';

// Check if uploads directory exists
$upload_dir = 'uploads';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        $upload_error = "Failed to create upload directory";
    }
}

// Handle file upload
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension'
        ];
        $upload_error = $upload_errors[$file['error']] ?? "Upload error: " . $file['error'];
    } 
    // Check file size (100MB max)
    elseif ($file['size'] > 100 * 1024 * 1024) {
        $upload_error = "File size exceeds maximum allowed limit of 100MB";
    }
    // Check file type
    else {
        $allowed_types = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo',
            'audio/mpeg', 'audio/wav', 'audio/ogg',
            'application/zip', 'application/x-rar-compressed', 'application/x-tar', 'application/x-7z-compressed',
            'text/plain', 'text/csv', 'text/html',
            'application/json'
        ];
        
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $upload_error = "File type not supported. Allowed formats: Images, PDF, Office Docs, Video, Audio, Archives, and Text.";
        } else {
            try {
                // Generate unique filename
                $original_name = basename($file['name']);
                $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                $unique_name = uniqid('file_', true) . '.' . $file_extension;
                $upload_path = $upload_dir . '/' . $unique_name;
                
                // Generate encryption key for this file (32 bytes for AES-256)
                $encryption_key = openssl_random_pseudo_bytes(32);
                
                // Generate IV (Initialization Vector) for AES (16 bytes)
                $iv = openssl_random_pseudo_bytes(16);
                
                // Read file content
                $file_content = file_get_contents($file['tmp_name']);
                
                if ($file_content === false) {
                    throw new Exception("Failed to read file content");
                }
                
                // Encrypt file content with AES-256-CBC
                $encrypted_content = openssl_encrypt(
                    $file_content,
                    'aes-256-cbc',
                    $encryption_key,
                    OPENSSL_RAW_DATA,
                    $iv
                );
                
                if ($encrypted_content === false) {
                    throw new Exception("Encryption failed");
                }
                
                // Save encrypted file
                if (file_put_contents($upload_path, $encrypted_content)) {
                    $file_size = strlen($encrypted_content);
                    
                    // Convert binary to hex for database storage
                    $encryption_key_hex = bin2hex($encryption_key);
                    $iv_hex = bin2hex($iv);
                    
                    // Store file info in database
                    $stmt = $db->prepare("INSERT INTO files (user_id, file_name, file_type, file_size, file_path, encryption_key, iv, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$user_id, $original_name, $file_type, $file_size, $upload_path, $encryption_key_hex, $iv_hex]);
                    
                    if (function_exists('logActivity')) {
                        logActivity($user_id, 'upload', 'Uploaded and encrypted file: ' . $original_name);
                    }
                    
                    $upload_success = "File \"<strong>" . htmlspecialchars($original_name) . "</strong>\" was uploaded and encrypted successfully with AES-256-CBC!";
                } else {
                    $upload_error = "Failed to write encrypted file to disk.";
                }
            } catch (Exception $e) {
                $upload_error = "Encryption Error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="sl-page-container">
    <!-- PAGE HEADER -->
    <div class="sl-page-header">
        <div class="sl-page-title-group">
            <div class="sl-page-icon-badge purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            </div>
            <div>
                <h2>Upload Files</h2>
                <p>Add and encrypt documents, photos, or media directly to your secure cloud locker</p>
            </div>
        </div>

        <div class="sl-page-actions">
            <a href="?section=files" class="sl-hero-btn secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <span>View All Files</span>
            </a>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($upload_error): ?>
        <div class="sl-alert error">
            <span class="alert-icon">⚠️</span>
            <span><?php echo $upload_error; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($upload_success): ?>
        <div class="sl-alert success">
            <span class="alert-icon">✅</span>
            <span><?php echo $upload_success; ?></span>
        </div>
    <?php endif; ?>

    <!-- MAIN UPLOAD CARD -->
    <div class="sl-upload-zone-card">
        <form id="uploadForm" method="POST" enctype="multipart/form-data" action="dashboard.php?section=upload">
            <input type="file" id="fileInput" name="file" hidden>

            <div class="sl-dropzone" id="dropZone">
                <div class="sl-dropzone-icon-ring">
                    <svg class="dropzone-cloud-svg" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width: 46px; height: 46px; filter: drop-shadow(0 4px 12px rgba(37, 99, 235, 0.25));">
                        <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                        <polyline points="16 16 12 12 8 16"></polyline>
                        <line x1="12" y1="12" x2="12" y2="21"></line>
                    </svg>
                </div>
                <h3>Drag &amp; drop your file here</h3>
                <p>or click anywhere in this area to browse from your device</p>

                <div class="sl-dropzone-badges">
                    <span class="dz-badge">Max 100MB</span>
                    <span class="dz-badge highlight">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle; margin-right: 3px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        AES-256 Encrypted
                    </span>
                    <span class="dz-badge">All Major Formats</span>
                </div>

                <button type="button" class="sl-hero-btn primary" id="browseBtn" style="margin-top: 20px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                    <span>Choose File</span>
                </button>
            </div>

            <!-- FILE SELECTED PREVIEW CARD -->
            <div id="filePreviewCard" class="sl-file-preview-card" style="display: none;">
                <div class="preview-info">
                    <div class="preview-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="width: 24px; height: 24px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    </div>
                    <div class="preview-details">
                        <div class="preview-name" id="previewFileName">filename.ext</div>
                        <div class="preview-size" id="previewFileSize">0 KB</div>
                    </div>
                </div>
                <div class="preview-actions">
                    <button type="button" class="preview-remove-btn" id="removeFileBtn" title="Remove file">&times;</button>
                </div>
            </div>

            <!-- UPLOAD PROGRESS (TRIGGERED ON SUBMIT) -->
            <div id="uploadProgressContainer" class="sl-upload-progress-box" style="display: none;">
                <div class="progress-header">
                    <span id="progressLabel">Encrypting and uploading file...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" id="progressBarFill" style="width: 0%;"></div>
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <div class="sl-upload-submit-wrap" id="submitWrap" style="display: none;">
                <button type="submit" class="sl-hero-btn primary large" id="uploadSubmitBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span>Upload &amp; Encrypt File</span>
                </button>
            </div>
        </form>
    </div>

    <!-- ENCRYPTION WORKFLOW & FEATURES -->
    <div class="sl-security-features-grid">
        <div class="sl-feature-card">
            <div class="feature-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
            </div>
            <h4>AES-256-CBC Security</h4>
            <p>Every file is encrypted on-the-fly with a unique 256-bit cryptographic key before hitting disk storage.</p>
        </div>

        <div class="sl-feature-card">
            <div class="feature-icon green">
                <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
            </div>
            <h4>Instant Access</h4>
            <p>Decryption happens on demand when you download or preview files—seamless and instantaneous.</p>
        </div>

        <div class="sl-feature-card">
            <div class="feature-icon purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            <h4>Zero-Knowledge Privacy</h4>
            <p>Only your authenticated session can request decryption tokens and view your stored files.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const dropZone = document.getElementById("dropZone");
    const fileInput = document.getElementById("fileInput");
    const browseBtn = document.getElementById("browseBtn");
    const previewCard = document.getElementById("filePreviewCard");
    const previewFileName = document.getElementById("previewFileName");
    const previewFileSize = document.getElementById("previewFileSize");
    const removeFileBtn = document.getElementById("removeFileBtn");
    const submitWrap = document.getElementById("submitWrap");
    const uploadForm = document.getElementById("uploadForm");
    const progressContainer = document.getElementById("uploadProgressContainer");
    const progressBarFill = document.getElementById("progressBarFill");
    const progressPercent = document.getElementById("progressPercent");
    const uploadSubmitBtn = document.getElementById("uploadSubmitBtn");

    function formatFileSize(bytes) {
        if (bytes === 0) return "0 Bytes";
        const sizes = ["Bytes", "KB", "MB", "GB"];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (Math.round(bytes / Math.pow(1024, i) * 100) / 100) + " " + sizes[i];
    }

    function showFile(file) {
        if (!file) return;
        previewFileName.textContent = file.name;
        previewFileSize.textContent = formatFileSize(file.size);
        previewCard.style.display = "flex";
        submitWrap.style.display = "flex";
        dropZone.classList.add("has-file");
    }

    function clearFile() {
        fileInput.value = "";
        previewCard.style.display = "none";
        submitWrap.style.display = "none";
        dropZone.classList.remove("has-file");
    }

    if (browseBtn) {
        browseBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            fileInput.click();
        });
    }

    if (dropZone) {
        dropZone.addEventListener("click", function () {
            fileInput.click();
        });

        dropZone.addEventListener("dragover", function (e) {
            e.preventDefault();
            dropZone.classList.add("dragover");
        });

        dropZone.addEventListener("dragleave", function () {
            dropZone.classList.remove("dragover");
        });

        dropZone.addEventListener("drop", function (e) {
            e.preventDefault();
            dropZone.classList.remove("dragover");
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                showFile(e.dataTransfer.files[0]);
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener("change", function () {
            if (this.files.length > 0) {
                showFile(this.files[0]);
            }
        });
    }

    if (removeFileBtn) {
        removeFileBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            clearFile();
        });
    }

    if (uploadForm) {
        uploadForm.addEventListener("submit", function () {
            if (uploadSubmitBtn) {
                uploadSubmitBtn.disabled = true;
                uploadSubmitBtn.innerHTML = '<span>🔒 Encrypting &amp; Uploading...</span>';
            }
            if (progressContainer) {
                progressContainer.style.display = "block";
                let progress = 10;
                const interval = setInterval(() => {
                    progress += Math.floor(Math.random() * 20) + 10;
                    if (progress > 90) {
                        progress = 90;
                        clearInterval(interval);
                    }
                    progressBarFill.style.width = progress + "%";
                    progressPercent.textContent = progress + "%";
                }, 150);
            }
        });
    }
});
</script>