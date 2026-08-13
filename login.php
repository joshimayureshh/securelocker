<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'login';
        
        if ($action === 'login') {
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $error = 'Please fill in all fields';
            } elseif (!isValidEmail($email)) {
                $error = 'Please enter a valid email address';
            } else {
                $db = getDB();
                try {
                    $stmt = $db->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password_hash'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        
                        logActivity($user['id'], 'login', 'User logged in');
                        
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        $error = 'Invalid email or password';
                    }
                } catch (PDOException $e) {
                    $error = 'An error occurred. Please try again.';
                }
            }
        } elseif ($action === 'register') {
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
                $error = 'Please fill in all fields';
            } elseif (!isValidEmail($email)) {
                $error = 'Please enter a valid email address';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                $db = getDB();
                try {
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                    $stmt->execute([':email' => $email]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Email already registered';
                    } else {
                        $password_hash = password_hash($password, PASSWORD_BCRYPT);
                        
                        $stmt = $db->prepare("
                            INSERT INTO users (name, email, password_hash, created_at) 
                            VALUES (:name, :email, :password_hash, NOW())
                            RETURNING id
                        ");
                        $stmt->execute([
                            ':name' => $name,
                            ':email' => $email,
                            ':password_hash' => $password_hash
                        ]);
                        
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $user_id = $result['id'];
                        
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        
                        logActivity($user_id, 'register', 'New user registered');
                        
                        header('Location: dashboard.php');
                        exit();
                    }
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    $error = 'An error occurred during registration';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in | Secure Locker</title>
    <style>
        /* ===== GOOGLE / MODERN RESET & STYLES ===== */
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

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ===== MAIN GOOGLE-STYLE CARD ===== */
        .login-container {
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.04);
            width: 100%;
            padding: 40px 36px 36px;
            animation: gCardFadeIn 0.35s cubic-bezier(0.2, 0, 0, 1);
        }

        @keyframes gCardFadeIn {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* ===== LOGO & HEADERS ===== */
        .login-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 28px;
        }

        .login-brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .brand-logo-img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.15));
        }

        .login-brand h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1f1f1f;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .login-brand p {
            font-size: 14px;
            color: #5e5e5e;
        }

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 7px;
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

        /* Password field */
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

        /* ===== BUTTONS & ACTIONS ===== */
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
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: #0842a0;
            box-shadow: 0 4px 12px rgba(11, 87, 208, 0.35);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== SWITCHER & LINKS ===== */
        .card-actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-text-action {
            background: transparent;
            border: 1px solid transparent;
            color: #0b57d0;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 100px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-text-action:hover {
            background: rgba(11, 87, 208, 0.08);
        }

        .forgot-link {
            text-align: right;
            margin-bottom: 16px;
        }

        .forgot-link a {
            color: #0b57d0;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 550;
            transition: color 0.2s;
        }

        .forgot-link a:hover {
            text-decoration: underline;
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

        /* ===== HINT TEXT ===== */
        .hint-text {
            font-size: 12px;
            color: #747775;
            margin-top: 5px;
            display: block;
        }

        /* ===== FOOTER ===== */
        .login-footer {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 24px;
            padding: 0 12px;
            font-size: 12px;
            color: #5e5e5e;
            font-weight: 500;
        }

        .login-footer-links {
            display: flex;
            gap: 16px;
        }

        .login-footer a {
            color: #5e5e5e;
            text-decoration: none;
            transition: color 0.2s;
        }

        body.dark-theme .login-footer a {
            color: #94a3b8;
        }

        .login-footer a:hover {
            color: #0b57d0;
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px 24px;
                border-radius: 22px;
            }
            .login-footer {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Main Card -->
        <div class="login-container">
            <!-- Login Form (Image 2 Aesthetic) -->
            <div id="loginForm">
                <div class="login-brand">
                    <div class="login-brand-logo">
                        <img src="assets/images/logo.png" alt="Secure Locker Logo" class="brand-logo-img">
                    </div>
                    <h1>Sign in</h1>
                    <p>to continue to Secure Locker</p>
                </div>
                
                <div class="login-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <span>⚠️</span>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <span>✅</span>
                            <span><?= htmlspecialchars($success) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="form-group">
                            <label>Email address</label>
                            <input type="email" name="email" required placeholder="name@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" autocomplete="email">
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <div class="password-field">
                                <input type="password" name="password" id="loginPassword" required placeholder="Enter password" autocomplete="current-password">
                                <button type="button" class="toggle-password" onclick="togglePassword('loginPassword', this)">👁️</button>
                            </div>
                        </div>

                        <div class="forgot-link">
                            <a href="forgot_password.php">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="btn-primary">Sign in</button>
                        
                        <div class="card-actions-row">
                            <button type="button" class="btn-text-action" onclick="showSignup()">Create account</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Signup Form (Image 2 Aesthetic) -->
            <div id="signupForm" style="display: none;">
                <div class="login-brand">
                    <div class="login-brand-logo">
                        <img src="assets/images/logo.png" alt="Secure Locker Logo" class="brand-logo-img">
                    </div>
                    <h1>Create account</h1>
                    <p>to start using Secure Locker</p>
                </div>
                
                <div class="login-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" required placeholder="e.g. Rahul Sharma" autocomplete="name">
                        </div>
                        
                        <div class="form-group">
                            <label>Email address</label>
                            <input type="email" name="email" required placeholder="name@example.com" autocomplete="email">
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <div class="password-field">
                                <input type="password" name="password" id="signupPassword" required minlength="6" placeholder="At least 6 characters" autocomplete="new-password">
                                <button type="button" class="toggle-password" onclick="togglePassword('signupPassword', this)">👁️</button>
                            </div>
                            <small class="hint-text">Use 6 or more characters with letters & numbers</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <div class="password-field">
                                <input type="password" name="confirm_password" id="confirmPassword" required placeholder="Re-enter password" autocomplete="new-password">
                                <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword', this)">👁️</button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">Create account</button>
                        
                        <div class="card-actions-row">
                            <button type="button" class="btn-text-action" onclick="showLogin()">Sign in instead</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Footer with Secure Locker name in exact font and color -->
        <div class="login-footer">
            <span>Secure Locker</span>
        </div>
    </div>
    
    <script>
        function showSignup() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('signupForm').style.display = 'block';
            document.title = 'Create Account | Secure Locker';
        }
        
        function showLogin() {
            document.getElementById('signupForm').style.display = 'none';
            document.getElementById('loginForm').style.display = 'block';
            document.title = 'Sign in | Secure Locker';
        }
        
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.textContent = input.type === 'password' ? '👁️' : '👁️‍🗨️';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Keep login and registration strictly in clean light mode
            document.body.classList.remove('dark-theme');

            <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'register'): ?>
            showSignup();
            <?php endif; ?>
        });

        // Prevent double submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = 'Signing in...';
                }
            });
        });
    </script>
</body>
</html>