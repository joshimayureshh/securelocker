<?php
// forgot_password.php - Secure Password Recovery with Gmail OTP for Secure Locker
require_once 'config.php';
require_once 'mailer.php';

// Helper function to mask email address for privacy
function maskUserEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) < 2) return $email;
    $name = $parts[0];
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } else {
        $maskedName = substr($name, 0, 2) . str_repeat('*', max(1, $len - 3)) . substr($name, -1);
    }
    return $maskedName . '@' . $parts[1];
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$step = 1; // 1: Enter Email, 2: Enter Code & New Password, 3: Success
$email = '';
$demo_code = '';
$email_sent_real = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // STEP 1: REQUEST RECOVERY CODE
        if ($action === 'request_code') {
            $email = sanitize($_POST['email'] ?? '');

            if (empty($email) || !isValidEmail($email)) {
                $error = 'Please enter a valid registered email address.';
            } else {
                $db = getDB();
                try {
                    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE LOWER(email) = LOWER(?)");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if ($user) {
                        // Generate a secure 6-digit numeric verification code
                        $code = strval(random_int(100000, 999999));
                        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                        // Save reset token and expiration to database
                        $update = $db->prepare("UPDATE users SET reset_token = :token, reset_expires_at = :expires WHERE id = :id");
                        $update->execute([
                            ':token' => $code,
                            ':expires' => $expires,
                            ':id' => $user['id']
                        ]);

                        if (function_exists('logActivity')) {
                            logActivity($user['id'], 'password_reset_request', 'Requested password reset verification code');
                        }

                        // Attempt sending OTP directly to user's registered Gmail
                        $email_sent = false;
                        if (function_exists('sendOTPEmail') && defined('SMTP_ENABLED') && SMTP_ENABLED) {
                            $email_sent = sendOTPEmail($user['email'], $user['name'], $code);
                        }

                        $step = 2;
                        $masked = maskUserEmail($user['email']);

                        if ($email_sent) {
                            $success = "A 6-digit OTP has been sent directly to your registered Gmail ($masked). Please check your inbox or spam folder.";
                        } else {
                            if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
                                $error = "Could not send OTP to $masked. Please check your Gmail SMTP credentials in config.php.";
                            } else {
                                $success = "A 6-digit recovery OTP has been sent to $masked.";
                            }
                        }
                        $demo_code = ''; // Never show OTP on screen
                    } else {
                        $error = 'No account found with this email address. Please check and try again.';
                    }
                } catch (PDOException $e) {
                    $error = 'A database error occurred. Please try again.';
                }
            }
        }

        // STEP 2: VERIFY CODE & RESET PASSWORD
        elseif ($action === 'reset_password') {
            $email = sanitize($_POST['email'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($email) || empty($code) || empty($password) || empty($confirm_password)) {
                $error = 'Please fill in all fields.';
                $step = 2;
            } elseif (strlen($password) < 6) {
                $error = 'New password must be at least 6 characters long.';
                $step = 2;
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match. Please verify and try again.';
                $step = 2;
            } else {
                $db = getDB();
                try {
                    $stmt = $db->prepare("
                        SELECT id, name, email, reset_token, reset_expires_at 
                        FROM users 
                        WHERE LOWER(email) = LOWER(?)
                    ");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if (!$user || empty($user['reset_token'])) {
                        $error = 'Invalid reset session. Please request a new recovery code.';
                        $step = 1;
                    } elseif ($user['reset_token'] !== $code) {
                        $error = 'Invalid recovery code. Please check the 6-digit code and try again.';
                        $step = 2;
                    } elseif (strtotime($user['reset_expires_at']) < time()) {
                        $error = 'This recovery code has expired. Please request a new code.';
                        $step = 1;
                    } else {
                        // Password is valid and code is correct -> Update password
                        $new_hash = password_hash($password, PASSWORD_BCRYPT);
                        $update = $db->prepare("
                            UPDATE users 
                            SET password_hash = :hash, reset_token = NULL, reset_expires_at = NULL 
                            WHERE id = :id
                        ");
                        $update->execute([
                            ':hash' => $new_hash,
                            ':id' => $user['id']
                        ]);

                        if (function_exists('logActivity')) {
                            logActivity($user['id'], 'password_reset_success', 'Password successfully reset via forgot password');
                        }

                        $step = 3;
                        $success = 'Your password has been reset successfully!';
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to reset password. Please try again.';
                    $step = 2;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Recovery | Secure Locker</title>
    <!-- PWA Manifest & Mobile App Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#061d48">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SecureLocker">
    <link rel="apple-touch-icon" href="assets/images/icon-192.png">
    <style>
        /* ===== MODERN GOOGLE-STYLE LIGHT CARD STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f0f4f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #1f1f1f;
        }

        .recovery-wrapper {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .recovery-card {
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.04);
            width: 100%;
            padding: 40px 36px 36px;
            animation: cardFadeIn 0.35s cubic-bezier(0.2, 0, 0, 1);
        }

        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .brand-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-logo-img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.15));
            margin-bottom: 12px;
        }

        .brand-header h1 {
            font-size: 23px;
            font-weight: 600;
            color: #1f1f1f;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .brand-header p {
            font-size: 13.5px;
            color: #5e5e5e;
            line-height: 1.45;
        }

        /* ===== ALERTS ===== */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* DEMO CODE CALLOUT */
        .demo-code-box {
            background: #eff6ff;
            border: 1.5px dashed #93c5fd;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 20px;
            text-align: center;
        }

        .demo-code-box .code-label {
            font-size: 12px;
            color: #1e40af;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .demo-code-box .code-digits {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 6px;
            color: #1d4ed8;
            margin: 6px 0 2px 0;
            font-family: monospace;
        }

        .demo-code-box .code-hint {
            font-size: 11.5px;
            color: #3b82f6;
        }

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #444746;
            font-weight: 600;
            font-size: 13.5px;
        }

        input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid #747775;
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #1f1f1f;
        }

        input:focus {
            outline: none;
            border-color: #0b57d0;
            box-shadow: 0 0 0 3px rgba(11, 87, 208, 0.18);
        }

        .code-input {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 4px;
            text-align: center;
            font-family: monospace;
        }

        .password-field {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #747775;
            cursor: pointer;
            font-size: 16px;
            padding: 6px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .toggle-password:hover {
            background: rgba(0, 0, 0, 0.06);
            color: #1f1f1f;
        }

        /* STRENGTH BAR */
        .strength-meter-wrap {
            margin-top: 6px;
        }

        .strength-bar-bg {
            height: 4px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 4px;
        }

        .strength-text {
            font-size: 11.5px;
            font-weight: 600;
            margin-top: 4px;
            display: block;
        }

        /* BUTTONS */
        .btn-primary {
            width: 100%;
            padding: 13px 24px;
            border: none;
            border-radius: 100px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            background: #0b57d0;
            color: white;
            transition: all 0.25s ease;
            box-shadow: 0 2px 6px rgba(11, 87, 208, 0.25);
            margin-top: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary:hover {
            background: #0842a0;
            box-shadow: 0 4px 12px rgba(11, 87, 208, 0.35);
            transform: translateY(-1px);
        }

        /* SUCCESS STATE */
        .success-box {
            text-align: center;
            padding: 12px 0;
        }

        .success-icon-badge {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #dcfce7;
            color: #16a34a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .success-box h2 {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }

        .success-box p {
            font-size: 14px;
            color: #4b5563;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        /* CARD ACTIONS */
        .card-actions-row {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-text-action {
            background: transparent;
            border: none;
            color: #0b57d0;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 100px;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-text-action:hover {
            background: rgba(11, 87, 208, 0.08);
        }

        .recovery-footer {
            margin-top: 24px;
            font-size: 12px;
            color: #5e5e5e;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="recovery-wrapper">
        <div class="recovery-card">
            <!-- BRAND LOGO & TITLE -->
            <div class="brand-header">
                <img src="assets/images/logo.png" alt="Secure Locker Logo" class="brand-logo-img">
                <h1>Account Recovery</h1>
                <p>
                    <?php if ($step === 1): ?>
                        Enter your registered email address to recover your account
                    <?php elseif ($step === 2): ?>
                        Enter the 6-digit recovery code and choose a new password
                    <?php else: ?>
                        Password reset completed successfully
                    <?php endif; ?>
                </p>
            </div>

            <!-- ALERTS -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px; flex-shrink: 0;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success) && $step !== 3): ?>
                <div class="alert alert-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px; flex-shrink: 0;">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- STEP 1: ENTER EMAIL -->
            <?php if ($step === 1): ?>
                <form method="POST" action="forgot_password.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="request_code">

                    <div class="form-group">
                        <label for="recoveryEmail">Email address</label>
                        <input type="email" id="recoveryEmail" name="email" required placeholder="name@example.com" value="<?php echo htmlspecialchars($email); ?>" autofocus autocomplete="email">
                    </div>

                    <button type="submit" class="btn-primary">
                        Send Recovery Code
                    </button>
                </form>

            <!-- STEP 2: ENTER 6-DIGIT CODE & NEW PASSWORD -->
            <?php elseif ($step === 2): ?>
                <form method="POST" action="forgot_password.php" onsubmit="return validateResetForm();">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                    <div class="form-group">
                        <label for="recoveryCode">Enter 6-Digit OTP Code</label>
                        <input type="text" id="recoveryCode" name="code" required placeholder="• • • • • •" maxlength="6" class="code-input" value="" autofocus autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="newPassword">New Password</label>
                        <div class="password-field">
                            <input type="password" id="newPassword" name="password" required placeholder="Enter new password" oninput="checkStrength(this.value)">
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('newPassword', this)">👁️</button>
                        </div>
                        <div class="strength-meter-wrap">
                            <div class="strength-bar-bg">
                                <div class="strength-bar-fill" id="strengthBarFill"></div>
                            </div>
                            <span class="strength-text" id="strengthText"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirmNewPassword">Confirm New Password</label>
                        <div class="password-field">
                            <input type="password" id="confirmNewPassword" name="confirm_password" required placeholder="Re-enter new password">
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirmNewPassword', this)">👁️</button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">
                        Reset Password &amp; Sign In
                    </button>
                </form>

            <!-- STEP 3: SUCCESS CONFIRMATION -->
            <?php elseif ($step === 3): ?>
                <div class="success-box">
                    <div class="success-icon-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 32px; height: 32px;">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <h2>Password Reset Complete!</h2>
                    <p>Your password has been securely updated. You can now log into your Secure Locker account with your new password.</p>
                    <a href="login.php" class="btn-primary">
                        Sign In Now
                    </a>
                </div>
            <?php endif; ?>

            <!-- CARD FOOTER ACTION -->
            <?php if ($step !== 3): ?>
                <div class="card-actions-row">
                    <a href="login.php" class="btn-text-action">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        <span>Back to Sign in</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="recovery-footer">
            <span>Secure Locker</span>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '👁️‍🗨️';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }

        function checkStrength(pass) {
            const bar = document.getElementById('strengthBarFill');
            const txt = document.getElementById('strengthText');
            if (!bar || !txt) return;

            if (!pass) {
                bar.style.width = '0%';
                txt.textContent = '';
                return;
            }

            let score = 0;
            if (pass.length >= 6) score++;
            if (pass.length >= 10) score++;
            if (/[A-Z]/.test(pass) && /[a-z]/.test(pass)) score++;
            if (/[0-9]/.test(pass)) score++;
            if (/[^A-Za-z0-9]/.test(pass)) score++;

            if (score <= 2) {
                bar.style.width = '33%';
                bar.style.backgroundColor = '#ef4444';
                txt.textContent = 'Weak password';
                txt.style.color = '#dc2626';
            } else if (score <= 4) {
                bar.style.width = '66%';
                bar.style.backgroundColor = '#f59e0b';
                txt.textContent = 'Moderate password';
                txt.style.color = '#d97706';
            } else {
                bar.style.width = '100%';
                bar.style.backgroundColor = '#10b981';
                txt.textContent = 'Strong password';
                txt.style.color = '#059669';
            }
        }

        function validateResetForm() {
            const p1 = document.getElementById('newPassword').value;
            const p2 = document.getElementById('confirmNewPassword').value;
            if (p1 !== p2) {
                alert('Passwords do not match! Please re-type your confirm password.');
                return false;
            }
            if (p1.length < 6) {
                alert('Password must be at least 6 characters.');
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.remove('dark-theme');
        });
    </script>
    <script src="js/pwa.js"></script>
</body>
</html>
