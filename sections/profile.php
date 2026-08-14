<?php
// sections/profile.php - Structured, Modern User Profile & Security Center
$user_name_display = ucwords(strtolower($user_name));

// Get current user data
try {
    $stmt = $db->prepare("SELECT name, email, phone, country, bio, created_at, avatar_path FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();
    
    if (!$current_user) {
        $current_user = [
            'name' => $_SESSION['user_name'] ?? 'User',
            'email' => $_SESSION['user_email'] ?? '',
            'phone' => '',
            'country' => '',
            'bio' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'avatar_path' => null
        ];
    }
} catch (PDOException $e) {
    $current_user = [
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => '',
        'country' => '',
        'bio' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'avatar_path' => null
    ];
}

// Handle profile update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $country = sanitize($_POST['country'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    
    try {
        $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, country = ?, bio = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $country, $bio, $user_id]);
        
        $_SESSION['user_name'] = $name;
        if (function_exists('logActivity')) {
            logActivity($user_id, 'profile_update', 'Updated profile information');
        }
        
        echo '<meta http-equiv="refresh" content="0;url=dashboard.php?section=profile&success=profile_updated">';
        exit();
    } catch (PDOException $e) {
        $error = "Failed to update profile: " . $e->getMessage();
    }
}

// Handle avatar selection
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['select_avatar'])) {
    $selected_avatar = $_POST['avatar_option'] ?? '';
    
    // Pre-defined avatar options
    $valid_avatars = [
        '👤', '👨', '👩', '🧑', '👦', '👧', '👶', '👴', '👵',
        '👨‍💼', '👩‍💼', '👨‍💻', '👩‍💻', '👨‍🎓', '👩‍🎓', '👨‍⚕️', '👩‍⚕️',
        '🦸', '🦸‍♀️', '🦹', '🦹‍♀️', '🧙', '🧙‍♀️',
        '🐼', '🐨', '🦊', '🐧', '🐱', '🐶', '🦁', '🐯',
        '🌟', '⭐', '☀️', '🌙', '🌈', '🌸', '🌻',
        '🎨', '🎯', '🎲', '🎮', '🎸', '🎭', '📚', '💻'
    ];
    
    if (in_array($selected_avatar, $valid_avatars)) {
        try {
            $stmt = $db->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            $stmt->execute([$selected_avatar, $user_id]);
            
            if (function_exists('logActivity')) {
                logActivity($user_id, 'avatar_select', 'Selected new profile avatar');
            }
            $avatar_success = "Profile avatar updated successfully!";
            $current_user['avatar_path'] = $selected_avatar;
        } catch (PDOException $e) {
            $avatar_error = "Failed to select avatar: " . $e->getMessage();
        }
    } else {
        $avatar_error = "Invalid avatar selection";
    }
}

// Handle avatar reset
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['reset_avatar'])) {
    try {
        $stmt = $db->prepare("UPDATE users SET avatar_path = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        
        if (function_exists('logActivity')) {
            logActivity($user_id, 'avatar_reset', 'Reset to default avatar');
        }
        $avatar_success = "Avatar reset to default!";
        $current_user['avatar_path'] = null;
    } catch (PDOException $e) {
        $avatar_error = "Failed to reset avatar: " . $e->getMessage();
    }
}

// Handle password change
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if ($user_data && password_verify($current_password, $user_data['password_hash'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $user_id]);
                    
                    if (function_exists('logActivity')) {
                        logActivity($user_id, 'password_change', 'Changed account password');
                    }
                    
                    echo '<meta http-equiv="refresh" content="0;url=dashboard.php?section=profile&success=password_changed">';
                    exit();
                } else {
                    $error = "New password must be at least 6 characters";
                }
            } else {
                $error = "New passwords do not match";
            }
        } else {
            $error = "Current password is incorrect";
        }
    } catch (PDOException $e) {
        $error = "Failed to change password: " . $e->getMessage();
    }
}

// Session errors & successes
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'profile_updated') {
        $success = "Profile updated successfully!";
    } elseif ($_GET['success'] == 'password_changed') {
        $success = "Password changed successfully!";
    }
}

// Stats overview
$stats = ['file_count' => 0, 'total_size' => 0];
try {
    $stmt = $db->prepare("SELECT COUNT(*) as file_count, COALESCE(SUM(file_size), 0) as total_size FROM files WHERE user_id = ? AND deleted_at IS NULL");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {}

// Avatar categories
$avatar_collections = [
    'Professional' => ['👨‍💼', '👩‍💼', '👨‍💻', '👩‍💻', '👨‍🎓', '👩‍🎓', '👨‍⚕️', '👩‍⚕️', '👨‍🏫', '👩‍🏫', '👨‍🔧', '👩‍🔧'],
    'People' => ['👤', '👨', '👩', '🧑', '👦', '👧', '👶', '👴', '👵'],
    'Creative' => ['🎨', '🎯', '🎲', '🎮', '🎸', '🎭', '📚', '💻', '📱', '📷'],
    'Nature' => ['🌟', '⭐', '☀️', '🌙', '🌈', '🌸', '🌻', '🌺', '🌍', '🌎']
];
?>

<div class="sl-page-container sl-profile-layout">
    <!-- PAGE HEADER -->
    <div class="sl-page-header">
        <div class="sl-page-title-group">
            <div class="sl-page-icon-badge blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 26px; height: 26px;">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <div>
                <h2>User Profile</h2>
                <p>Manage your identity, personal details, credentials, and vault account</p>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION ALERTS -->
    <?php if (isset($error) || isset($avatar_error)): ?>
        <div class="sl-alert error">
            <span class="alert-icon">⚠️</span>
            <span><?php echo htmlspecialchars($error ?? $avatar_error); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success) || isset($avatar_success)): ?>
        <div class="sl-alert success">
            <span class="alert-icon">✅</span>
            <span><?php echo htmlspecialchars($success ?? $avatar_success); ?></span>
        </div>
    <?php endif; ?>

    <!-- MAIN PROFILE GRID -->
    <div class="sl-profile-grid">
        <!-- LEFT COLUMN: USER OVERVIEW & IDENTITY -->
        <div class="sl-profile-left-col">
            <!-- IDENTITY CARD -->
            <div class="sl-profile-card identity-card">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar-ring">
                        <?php if (!empty($current_user['avatar_path'])): ?>
                            <div class="profile-avatar-face"><?php echo $current_user['avatar_path']; ?></div>
                        <?php else: ?>
                            <div class="profile-avatar-initials">
                                <?php 
                                $initials = '';
                                $names = explode(' ', $current_user['name']);
                                foreach ($names as $n) {
                                    if (!empty($n)) {
                                        $initials .= strtoupper($n[0]);
                                        if (strlen($initials) >= 2) break;
                                    }
                                }
                                echo !empty($initials) ? $initials : 'U';
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="avatar-camera-btn" onclick="toggleAvatarModal()" title="Change profile avatar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" style="width: 15px; height: 15px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                    </button>
                </div>

                <div class="profile-meta-header">
                    <h3>Hi, <?php echo htmlspecialchars($user_name_display); ?>!</h3>
                    <span class="profile-email-badge"><?php echo htmlspecialchars($current_user['email']); ?></span>
                </div>

                <button type="button" class="btn-change-avatar" onclick="toggleAvatarModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    <span>Change Avatar</span>
                </button>

                <!-- STATS TILES -->
                <div class="profile-stats-tiles">
                    <div class="stat-tile">
                        <div class="stat-tile-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="width: 20px; height: 20px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        </div>
                        <div class="stat-tile-val"><?php echo number_format($stats['file_count']); ?></div>
                        <div class="stat-tile-lbl">Stored Files</div>
                    </div>

                    <div class="stat-tile">
                        <div class="stat-tile-icon purple">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" style="width: 20px; height: 20px;"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                        </div>
                        <div class="stat-tile-val"><?php echo formatFileSize($stats['total_size']); ?></div>
                        <div class="stat-tile-lbl">Vault Storage</div>
                    </div>
                </div>

                <div class="profile-member-footer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px; opacity: 0.8;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span>Member since <?php echo date('F Y', strtotime($current_user['created_at'])); ?></span>
                </div>
            </div>

            <!-- ACCOUNT INFO DETAILS -->
            <div class="sl-profile-card">
                <div class="card-section-header">
                    <h4>Account Summary</h4>
                </div>

                <div class="account-details-list">
                    <div class="detail-item">
                        <div class="detail-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="width: 16px; height: 16px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </div>
                        <div class="detail-content">
                            <span class="detail-label">Email</span>
                            <span class="detail-val"><?php echo htmlspecialchars($current_user['email']); ?></span>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="width: 16px; height: 16px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </div>
                        <div class="detail-content">
                            <span class="detail-label">Phone</span>
                            <span class="detail-val"><?php echo !empty($current_user['phone']) ? htmlspecialchars($current_user['phone']) : 'Not set'; ?></span>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="width: 16px; height: 16px;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                        </div>
                        <div class="detail-content">
                            <span class="detail-label">Country</span>
                            <span class="detail-val"><?php echo !empty($current_user['country']) ? htmlspecialchars($current_user['country']) : 'Not set'; ?></span>
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="width: 16px; height: 16px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
                        </div>
                        <div class="detail-content">
                            <span class="detail-label">Encryption Status</span>
                            <span class="detail-val" style="color: #10b981; font-weight: 700;">AES-256-CBC Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: STRUCTURED FORMS -->
        <div class="sl-profile-right-col">
            <!-- 1. PERSONAL INFORMATION CARD -->
            <div class="sl-profile-card">
                <div class="card-title-bar">
                    <div class="title-icon-badge blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="width: 18px; height: 18px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </div>
                    <div>
                        <h3>Personal Information</h3>
                        <p>Update your public display name, contact numbers, and profile bio</p>
                    </div>
                </div>

                <form method="POST" class="sl-structured-form">
                    <div class="form-row-single">
                        <div class="form-field">
                            <label>Full Name <span class="req">*</span></label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user_name_display); ?>" required placeholder="Enter full name" autocomplete="name">
                        </div>
                    </div>

                    <div class="form-row-double">
                        <div class="form-field">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" placeholder="+1 (555) 000-0000" autocomplete="tel">
                        </div>

                        <div class="form-field">
                            <label>Country</label>
                            <select name="country">
                                <option value="">Select Country</option>
                                <option value="US" <?php echo ($current_user['country'] ?? '') == 'US' ? 'selected' : ''; ?>>United States</option>
                                <option value="UK" <?php echo ($current_user['country'] ?? '') == 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                                <option value="CA" <?php echo ($current_user['country'] ?? '') == 'CA' ? 'selected' : ''; ?>>Canada</option>
                                <option value="AU" <?php echo ($current_user['country'] ?? '') == 'AU' ? 'selected' : ''; ?>>Australia</option>
                                <option value="IN" <?php echo ($current_user['country'] ?? '') == 'IN' ? 'selected' : ''; ?>>India</option>
                                <option value="DE" <?php echo ($current_user['country'] ?? '') == 'DE' ? 'selected' : ''; ?>>Germany</option>
                                <option value="FR" <?php echo ($current_user['country'] ?? '') == 'FR' ? 'selected' : ''; ?>>France</option>
                                <option value="JP" <?php echo ($current_user['country'] ?? '') == 'JP' ? 'selected' : ''; ?>>Japan</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-single">
                        <div class="form-field">
                            <label>About / Bio</label>
                            <textarea name="bio" rows="3" placeholder="Tell us a little about yourself or your organization..."><?php echo htmlspecialchars($current_user['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions-row">
                        <button type="button" class="btn-cancel" onclick="location.reload()">Discard</button>
                        <button type="submit" name="update_profile" class="sl-hero-btn primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- 2. CHANGE PASSWORD CARD -->
            <div class="sl-profile-card">
                <div class="card-title-bar">
                    <div class="title-icon-badge purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" style="width: 18px; height: 18px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </div>
                    <div>
                        <h3>Change Password</h3>
                        <p>Keep your account secure with a strong and unique password</p>
                    </div>
                </div>

                <form method="POST" class="sl-structured-form">
                    <div class="form-row-single">
                        <div class="form-field">
                            <label>Current Password <span class="req">*</span></label>
                            <input type="password" name="current_password" required placeholder="Enter current password" autocomplete="current-password">
                        </div>
                    </div>

                    <div class="form-row-double">
                        <div class="form-field">
                            <label>New Password <span class="req">*</span></label>
                            <input type="password" name="new_password" id="newPasswordInput" required minlength="6" placeholder="Min. 6 characters" autocomplete="new-password">
                        </div>

                        <div class="form-field">
                            <label>Confirm New Password <span class="req">*</span></label>
                            <input type="password" name="confirm_password" required placeholder="Re-enter new password" autocomplete="new-password">
                        </div>
                    </div>

                    <div id="passwordStrengthBox" class="password-meter-wrap" style="display: none;">
                        <div class="meter-bar-track">
                            <div class="meter-bar-fill" id="meterFill"></div>
                        </div>
                        <span class="meter-text" id="meterText">Strength: Medium</span>
                    </div>

                    <div class="form-actions-row">
                        <button type="submit" name="change_password" class="sl-hero-btn primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <span>Update Password</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- 3. DELETE PROFILE FOREVER CARD -->
            <div class="sl-profile-card danger-zone-card">
                <div class="card-title-bar">
                    <div class="title-icon-badge red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="width: 18px; height: 18px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    </div>
                    <div>
                        <h3 style="color: #dc2626;">Delete Profile Forever</h3>
                        <p>Permanently delete your profile, credentials, and all uploaded files from the database</p>
                    </div>
                </div>

                <div class="danger-warning-banner">
                    <span class="warning-icon">⚠️</span>
                    <div class="warning-text">
                        <strong>Irreversible Action:</strong> Once confirmed, your account credentials, encryption keys, and all uploaded files will be permanently erased from both database and disk storage.
                    </div>
                </div>

                <form method="POST" action="delete_account.php" class="sl-structured-form" onsubmit="return confirmDeleteForever();">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="delete_account" value="1">

                    <div class="form-row-single">
                        <div class="form-field">
                            <label style="color: #dc2626; font-weight: 650;">Enter password to confirm deletion <span class="req">*</span></label>
                            <input type="password" name="confirm_delete_password" required placeholder="Enter your current password" autocomplete="current-password" class="danger-input">
                        </div>
                    </div>

                    <div class="form-actions-row">
                        <button type="submit" class="btn-danger-forever">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            <span>Delete Profile Forever</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- AVATAR SELECTION MODAL -->
<div id="avatarModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Choose Your Avatar</h2>
            <button class="modal-close" onclick="toggleAvatarModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="avatar-preview">
                <span>Current Avatar:</span>
                <?php if (!empty($current_user['avatar_path'])): ?>
                    <div class="preview-avatar"><?php echo $current_user['avatar_path']; ?></div>
                <?php else: ?>
                    <div class="preview-initials">
                        <?php echo !empty($initials) ? $initials : 'U'; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Category Tabs -->
            <div class="avatar-categories">
                <?php 
                $categories = array_keys($avatar_collections);
                foreach ($categories as $index => $category): 
                ?>
                    <button class="category-tab <?php echo $index === 0 ? 'active' : ''; ?>" 
                            onclick="switchCategory(this, 'cat-<?php echo $index; ?>')">
                        <?php echo $category; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Avatar Grids -->
            <?php foreach ($avatar_collections as $category => $avatars): ?>
                <?php $cat_id = 'cat-' . array_search($category, array_keys($avatar_collections)); ?>
                <div id="<?php echo $cat_id; ?>" class="avatar-grid" 
                     style="display: <?php echo $category === array_keys($avatar_collections)[0] ? 'grid' : 'none'; ?>;">
                    <?php foreach ($avatars as $avatar): ?>
                        <div class="avatar-item <?php echo ($current_user['avatar_path'] ?? '') == $avatar ? 'selected' : ''; ?>" 
                             onclick="selectAvatar('<?php echo $avatar; ?>', this)">
                            <?php echo $avatar; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <form method="POST" id="avatarForm" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <input type="hidden" name="select_avatar" value="1">
                <input type="hidden" name="avatar_option" id="selectedAvatar" value="">
                
                <?php if (!empty($current_user['avatar_path'])): ?>
                    <button type="submit" name="reset_avatar" class="sl-hero-btn secondary">
                        Reset to Default
                    </button>
                <?php endif; ?>
                
                <button type="submit" class="sl-hero-btn primary" id="saveAvatarBtn" disabled>
                    Save Avatar
                </button>
            </form>
        </div>
    </div>
</div>

<style>
/* =========================================================
   STRUCTURED USER PROFILE STYLING
========================================================= */
.sl-profile-layout {
    width: 100%;
    max-width: 100%;
    margin: 0;
}

.sl-profile-layout .sl-page-header {
    width: 100%;
    max-width: 100%;
    margin: 0 0 24px 0;
}

.sl-profile-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    width: 100%;
    max-width: 100%;
    gap: 28px;
    align-items: start;
    justify-content: stretch;
    margin: 0;
}

.sl-profile-left-col {
    width: 100%;
}

.sl-profile-right-col {
    width: 100%;
}

/* CARDS */
.sl-profile-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1.5px solid #e2e8f0;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
}

body.dark-theme .sl-profile-card {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

/* IDENTITY CARD */
.identity-card {
    text-align: center;
    background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
}

body.dark-theme .identity-card {
    background: linear-gradient(180deg, #0f172a 0%, #152038 100%);
}

.profile-avatar-wrap {
    position: relative;
    width: 100px;
    height: 100px;
    margin: 0 auto 16px;
}

.profile-avatar-ring {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    padding: 3.5px;
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-avatar-face,
.profile-avatar-initials {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 42px;
}

body.dark-theme .profile-avatar-face,
body.dark-theme .profile-avatar-initials {
    background: #1e293b;
    color: #f1f5f9;
}

.profile-avatar-initials {
    font-size: 32px;
    font-weight: 750;
    color: #2563eb;
}

.avatar-camera-btn {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #2563eb;
    border: 2.5px solid #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s ease;
}

.avatar-camera-btn:hover {
    transform: scale(1.1);
}

.profile-meta-header h3 {
    font-size: 20px;
    font-weight: 750;
    color: #0f172a;
    margin: 0 0 6px 0;
}

body.dark-theme .profile-meta-header h3 {
    color: #f1f5f9;
}

.profile-email-badge {
    display: inline-block;
    font-size: 13px;
    color: #64748b;
    background: #f1f5f9;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 500;
}

body.dark-theme .profile-email-badge {
    background: #1e293b;
    color: #94a3b8;
}

.btn-change-avatar {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    padding: 7px 16px;
    background: transparent;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
}

body.dark-theme .btn-change-avatar {
    border-color: #334155;
    color: #94a3b8;
}

.btn-change-avatar:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.profile-stats-tiles {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 20px 0 16px;
}

.stat-tile {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 12px;
    text-align: center;
}

body.dark-theme .stat-tile {
    background: #1e293b;
    border-color: #334155;
}

.stat-tile-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    margin: 0 auto 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-tile-icon.blue { background: #dbeafe; }
body.dark-theme .stat-tile-icon.blue { background: #1e3a8a; }

.stat-tile-icon.purple { background: #ede9fe; }
body.dark-theme .stat-tile-icon.purple { background: #3b2866; }

.stat-tile-val {
    font-size: 17px;
    font-weight: 750;
    color: #0f172a;
}

body.dark-theme .stat-tile-val {
    color: #f1f5f9;
}

.stat-tile-lbl {
    font-size: 11.5px;
    color: #64748b;
    margin-top: 2px;
}

body.dark-theme .stat-tile-lbl {
    color: #94a3b8;
}

.profile-member-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
    padding-top: 14px;
    border-top: 1px solid #e2e8f0;
}

body.dark-theme .profile-member-footer {
    border-top-color: #1e293b;
    color: #94a3b8;
}

/* ACCOUNT DETAILS LIST */
.card-section-header h4 {
    font-size: 16px;
    font-weight: 750;
    color: #0f172a;
    margin: 0 0 14px 0;
}

body.dark-theme .card-section-header h4 {
    color: #f1f5f9;
}

.account-details-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

body.dark-theme .detail-item {
    background: #1e293b;
    border-color: #334155;
}

.detail-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

body.dark-theme .detail-icon {
    background: #152549;
}

.detail-content {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.detail-label {
    font-size: 11.5px;
    color: #64748b;
}

body.dark-theme .detail-label {
    color: #94a3b8;
}

.detail-val {
    font-size: 13.5px;
    font-weight: 600;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

body.dark-theme .detail-val {
    color: #f1f5f9;
}

/* RIGHT COLUMN FORMS */
.card-title-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}

body.dark-theme .card-title-bar {
    border-bottom-color: #1e293b;
}

.title-icon-badge {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.title-icon-badge.blue { background: #dbeafe; }
body.dark-theme .title-icon-badge.blue { background: #1e3a8a; }

.title-icon-badge.purple { background: #ede9fe; }
body.dark-theme .title-icon-badge.purple { background: #3b2866; }

.title-icon-badge.red { background: #fee2e2; }
body.dark-theme .title-icon-badge.red { background: #3b1414; }

.card-title-bar h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 750;
    color: #0f172a;
}

body.dark-theme .card-title-bar h3 {
    color: #f1f5f9;
}

.card-title-bar p {
    margin: 3px 0 0 0;
    font-size: 13px;
    color: #64748b;
}

body.dark-theme .card-title-bar p {
    color: #94a3b8;
}

/* FORM FIELDS */
.sl-structured-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.form-row-single {
    width: 100%;
}

.form-row-double {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-field label {
    font-size: 13px;
    font-weight: 650;
    color: #334155;
}

body.dark-theme .form-field label {
    color: #e2e8f0;
}

.form-field label .req {
    color: #dc2626;
}

.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    font-size: 14px;
    color: #0f172a;
    font-family: inherit;
    transition: all 0.2s ease;
    outline: none;
}

body.dark-theme .form-field input,
body.dark-theme .form-field select,
body.dark-theme .form-field textarea {
    background: #1e293b;
    border-color: #334155;
    color: #f1f5f9;
}

.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.form-actions-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 10px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

body.dark-theme .form-actions-row {
    border-top-color: #1e293b;
}

.btn-cancel {
    padding: 10px 18px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    background: transparent;
    font-size: 13.5px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s ease;
}

body.dark-theme .btn-cancel {
    border-color: #334155;
    color: #94a3b8;
}

.btn-cancel:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* PASSWORD METER */
.password-meter-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: -6px;
}

.meter-bar-track {
    width: 100%;
    height: 6px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

body.dark-theme .meter-bar-track {
    background: #334155;
}

.meter-bar-fill {
    height: 100%;
    width: 0%;
    transition: width 0.3s ease, background 0.3s ease;
}

.meter-text {
    font-size: 12px;
    font-weight: 600;
}

/* DANGER ZONE */
.danger-zone-card {
    border-color: #fecaca;
    background: #fffafa;
}

body.dark-theme .danger-zone-card {
    background: #1c131d;
    border-color: #7f1d1d;
}

.danger-warning-banner {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    background: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: 12px;
    margin-bottom: 20px;
    color: #991b1b;
    font-size: 13px;
    line-height: 1.55;
}

body.dark-theme .danger-warning-banner {
    background: #2b1318;
    border-color: #991b1b;
    color: #fca5a5;
}

.btn-danger-forever {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 22px;
    background: #dc2626;
    color: #ffffff;
    border: none;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
}

.btn-danger-forever:hover {
    background: #b91c1c;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(220, 38, 38, 0.35);
}

/* MODAL */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    z-index: 9999;
    overflow-y: auto;
    padding: 20px;
}

.modal-content {
    background: #ffffff;
    border-radius: 24px;
    max-width: 580px;
    margin: 40px auto;
    padding: 28px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    border: 1px solid #e2e8f0;
}

body.dark-theme .modal-content {
    background: #0f172a;
    border-color: #1e293b;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #e2e8f0;
}

body.dark-theme .modal-header {
    border-bottom-color: #1e293b;
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 750;
    color: #0f172a;
    margin: 0;
}

body.dark-theme .modal-header h2 {
    color: #f1f5f9;
}

.modal-close {
    background: transparent;
    border: none;
    font-size: 26px;
    color: #64748b;
    cursor: pointer;
    line-height: 1;
}

.avatar-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 14px;
    margin-bottom: 20px;
    font-size: 13.5px;
    font-weight: 600;
}

body.dark-theme .avatar-preview {
    background: #1e293b;
}

.preview-avatar,
.preview-initials {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    background: #2563eb;
    color: #ffffff;
}

.avatar-categories {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
    overflow-x: auto;
    padding-bottom: 4px;
}

.category-tab {
    padding: 7px 16px;
    border-radius: 20px;
    border: 1px solid #cbd5e1;
    background: transparent;
    font-size: 12.5px;
    font-weight: 650;
    color: #64748b;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
}

body.dark-theme .category-tab {
    border-color: #334155;
    color: #94a3b8;
}

.category-tab.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.avatar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(54px, 1fr));
    gap: 10px;
    max-height: 260px;
    overflow-y: auto;
    padding: 4px;
}

.avatar-item {
    width: 54px;
    height: 54px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.18s ease;
}

body.dark-theme .avatar-item {
    background: #1e293b;
    border-color: #334155;
}

.avatar-item:hover {
    transform: scale(1.12);
    border-color: #3b82f6;
}

.avatar-item.selected {
    border-color: #2563eb;
    background: #eff6ff;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
}

body.dark-theme .avatar-item.selected {
    background: #1e3a8a;
}

/* RESPONSIVE PROFILE */
@media (max-width: 960px) {
    .sl-profile-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .form-row-double {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .form-actions-row {
        flex-direction: column-reverse;
    }

    .form-actions-row button {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
function confirmDeleteForever() {
    return confirm("⚠️ ARE YOU ABSOLUTELY SURE?\n\nThis will permanently delete your profile and erase all your files, encryption keys, and account data from the database forever.\n\nThis action CANNOT be undone. Click OK to proceed.");
}

let selectedAvatarValue = '';

function toggleAvatarModal() {
    const modal = document.getElementById('avatarModal');
    if (modal.style.display === 'none' || modal.style.display === '') {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    } else {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function switchCategory(tab, categoryId) {
    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    
    document.querySelectorAll('.avatar-grid').forEach(grid => {
        grid.style.display = 'none';
    });
    document.getElementById(categoryId).style.display = 'grid';
}

function selectAvatar(avatar, element) {
    document.querySelectorAll('.avatar-item').forEach(item => {
        item.classList.remove('selected');
    });
    element.classList.add('selected');
    selectedAvatarValue = avatar;
    document.getElementById('selectedAvatar').value = avatar;
    document.getElementById('saveAvatarBtn').disabled = false;
    
    const preview = document.querySelector('.avatar-preview .preview-avatar, .avatar-preview .preview-initials');
    if (preview) {
        preview.outerHTML = `<div class="preview-avatar">${avatar}</div>`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('newPasswordInput');
    const meterBox = document.getElementById('passwordStrengthBox');
    const meterFill = document.getElementById('meterFill');
    const meterText = document.getElementById('meterText');

    if (passwordInput && meterBox) {
        passwordInput.addEventListener('input', function() {
            const val = this.value;
            if (val.length === 0) {
                meterBox.style.display = 'none';
                return;
            }
            meterBox.style.display = 'flex';
            
            let score = 0;
            if (val.length >= 6) score += 25;
            if (val.length >= 10) score += 25;
            if (/[0-9]/.test(val)) score += 25;
            if (/[^A-Za-z0-9]/.test(val)) score += 25;

            meterFill.style.width = score + '%';
            if (score <= 25) {
                meterFill.style.background = '#ef4444';
                meterText.textContent = 'Strength: Weak';
                meterText.style.color = '#ef4444';
            } else if (score <= 50) {
                meterFill.style.background = '#f59e0b';
                meterText.textContent = 'Strength: Fair';
                meterText.style.color = '#f59e0b';
            } else if (score <= 75) {
                meterFill.style.background = '#3b82f6';
                meterText.textContent = 'Strength: Good';
                meterText.style.color = '#3b82f6';
            } else {
                meterFill.style.background = '#10b981';
                meterText.textContent = 'Strength: Strong';
                meterText.style.color = '#10b981';
            }
        });
    }
});

window.onclick = function(event) {
    const modal = document.getElementById('avatarModal');
    if (event.target === modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('avatarModal');
        if (modal && modal.style.display === 'block') {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
});
</script>