<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if already logged in
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'hr':
            header('Location: hr-dashboard.php');
            break;
        case 'employee':
            header('Location: employee-dashboard.php');
            break;
        case 'candidate':
            header('Location: candidate-upload.php');
            break;
        default:
            header('Location: candidate-upload.php');
    }
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // login, register, forgot

// Add this near the top of your file after the database connection
$stmt = $pdo->query("SELECT NOW() as db_time");
$db_time = $stmt->fetch()['db_time'];
error_log("Database time: " . $db_time . ", PHP time: " . date('Y-m-d H:i:s'));

// Handle login form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (login($username, $password)) {
            // Redirect based on role
            switch ($_SESSION['role']) {
                case 'hr':
                    header('Location: hr-dashboard.php');
                    break;
                case 'employee':
                    header('Location: employee-dashboard.php');
                    break;
                case 'candidate':
                    header('Location: candidate-upload.php');
                    break;
                default:
                    header('Location: candidate-upload.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Handle register form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'candidate';
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$username, $email, $hashed_password, $full_name, $role])) {
                    $success = 'Registration successful! You can now log in.';
                    $mode = 'login';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}

// Handle forgot password form submission
// Handle forgot password form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'forgot') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Update user with reset token
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
                
                if ($stmt->execute([$token, $expires, $email])) {
                    // For demo purposes, show the reset link instead of sending email
                    $success = 'Password reset link: <a href="?mode=reset&token=' . $token . '" style="color: #FF6B35; text-decoration: underline;">Click here to reset password</a>';
                } else {
                    $error = 'Failed to generate reset token. Please try again.';
                }
            } else {
                $error = 'Email address not found in our system.';
            }
        } catch (PDOException $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

// Handle reset password form submission FIRST (before checking reset mode)
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reset') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in both password fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // First get the user and check expiration with PHP instead of SQL
            $stmt = $pdo->prepare("SELECT id, username, reset_expires FROM users WHERE reset_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user && strtotime($user['reset_expires']) > time()) {
                // Token is valid and not expired, update password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
                
                if ($stmt->execute([$hashed_password, $token])) {
                    $success = 'Password updated successfully! You can now log in.';
                    $mode = 'login';
                    // Don't redirect immediately, let the success message show
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } else {
                if (!$user) {
                    $error = 'Invalid reset token. Please request a new reset link.';
                } else {
                    $error = 'Reset token has expired. Please request a new reset link.';
                }
                $mode = 'forgot'; // Redirect to forgot password instead of login
            }
        } catch (PDOException $e) {
            error_log("Reset password error: " . $e->getMessage());
            $error = 'An error occurred while resetting password.';
        }
    }
}

// Check for reset mode and validate token (ONLY if not processing form submission)
if ($mode === 'reset' && isset($_GET['token']) && !isset($_POST['action'])) {
    $token = $_GET['token'];
    try {
        // Use the same approach - get user and check expiration with PHP
        $stmt = $pdo->prepare("SELECT id, username, reset_expires FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Invalid reset token. Please request a new password reset.';
            $mode = 'forgot';
        } elseif (strtotime($user['reset_expires']) <= time()) {
            $error = 'Reset token has expired. Please request a new password reset.';
            $mode = 'forgot';
        }
        // If we get here, token is valid - continue with reset form
        
    } catch (PDOException $e) {
        error_log("Token verification error: " . $e->getMessage());
        $error = 'An error occurred while verifying the reset token.';
        $mode = 'forgot';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vector</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #2B4C8C;
            --kabel-gradient: linear-gradient(135deg, #FF6B35 0%, #2B4C8C 100%);
            --shadow-soft: 0 10px 40px rgba(43, 76, 140, 0.1);
            --shadow-hover: 0 20px 60px rgba(43, 76, 140, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            height: 100%;
            overflow-x: hidden;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: 
                linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%),
                url('https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2074&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        .main-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* Fixed animated background elements */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(circle at 25% 25%, rgba(255, 107, 53, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 75% 25%, rgba(43, 76, 140, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 25% 75%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            animation: float 25s ease-in-out infinite;
            z-index: 0;
            pointer-events: none;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(20px, -20px) rotate(120deg); }
            66% { transform: translate(-15px, 15px) rotate(240deg); }
        }
        
        .auth-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(25px);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 
                0 25px 50px rgba(43, 76, 140, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .auth-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--kabel-gradient);
            border-radius: 24px 24px 0 0;
        }
        
        .auth-container:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .brand-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: var(--kabel-gradient);
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.3);
            position: relative;
        }
        
        .logo::after {
            content: '';
            position: absolute;
            inset: -3px;
            background: var(--kabel-gradient);
            border-radius: 50%;
            z-index: -1;
            opacity: 0.3;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.1; }
        }
        
        .brand-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }
        
        .brand-subtitle {
            color: #6b7280;
            font-size: 1rem;
            font-weight: 500;
            font-style: italic;
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            background: #f8fafc;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 2rem;
            position: relative;
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .tab-btn.active {
            color: white;
            background: var(--kabel-gradient);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .tab-btn:not(.active):hover {
            color: var(--secondary-color);
            background: #e2e8f0;
        }
        
        .form-section {
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            background: #f9fafb;
            transition: all 0.3s ease;
            color: var(--secondary-color);
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
            transform: translateY(-1px);
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            color: #9ca3af;
            transition: color 0.3s ease;
        }
        
        .form-group input:focus + .input-icon,
        .form-group select:focus + .input-icon {
            color: var(--primary-color);
        }
        
        .submit-btn {
            width: 100%;
            background: var(--kabel-gradient);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(255, 107, 53, 0.4);
        }
        
        .submit-btn:hover::before {
            left: 100%;
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 1px solid #fca5a5;
            color: #dc2626;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: shake 0.5s ease-in-out;
        }

        .success-message {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border: 1px solid #86efac;
            color: #15803d;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideDown 0.5s ease-in-out;
        }

        @keyframes slideDown {
            0% { transform: translateY(-10px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .link-btn {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .link-btn:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        .text-center {
            text-align: center;
        }
        
        .demo-section {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #bae6fd;
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .demo-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .demo-accounts {
            display: grid;
            gap: 0.75rem;
        }
        
        .demo-account {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1px solid #e0f2fe;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .demo-account:hover {
            background: #f8fafc;
            border-color: var(--primary-color);
            transform: translateX(5px);
        }
        
        .account-role {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 0.9rem;
        }
        
        .account-credentials {
            color: #6b7280;
            font-size: 0.85rem;
            margin-top: 0.25rem;
            font-family: 'Monaco', 'Menlo', monospace;
        }

        .hidden {
            display: none;
        }
        
        /* Responsive fixes */
        @media (max-height: 700px) {
            .main-wrapper {
                align-items: flex-start;
                padding-top: 20px;
                padding-bottom: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .main-wrapper {
                align-items: flex-start;
                padding: 1rem;
            }
            
            .auth-container {
                padding: 2rem 1.5rem;
                margin: 1rem 0;
                width: 100%;
            }
            
            .brand-title {
                font-size: 1.5rem;
            }
            
            .logo {
                width: 70px;
                height: 70px;
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="auth-container">
            <div class="brand-section">
                <div class="logo">V</div>
                <h1 class="brand-title">Vector</h1>
                <p class="brand-subtitle">Powered by Youth. Built for Speed.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <span>⚠️</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <span>✅</span>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($mode !== 'forgot' && $mode !== 'reset'): ?>
            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button type="button" class="tab-btn <?php echo $mode === 'login' ? 'active' : ''; ?>" onclick="switchTab('login')">
                    Login
                </button>
                <button type="button" class="tab-btn <?php echo $mode === 'register' ? 'active' : ''; ?>" onclick="switchTab('register')">
                    Register
                </button>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" class="form-section <?php echo $mode !== 'login' ? 'hidden' : ''; ?>" id="loginForm">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="login_username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" name="username" id="login_username" required autocomplete="username">
                        <div class="input-icon">👤</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="login_password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="login_password" required autocomplete="current-password">
                        <div class="input-icon">🔒</div>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    Sign In
                </button>

                <div class="text-center">
                    <a href="?mode=forgot" class="link-btn">Forgot Password?</a>
                </div>
            </form>

            <!-- Register Form -->
            <form method="POST" class="form-section <?php echo $mode !== 'register' ? 'hidden' : ''; ?>" id="registerForm">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="reg_full_name">Full Name</label>
                    <div class="input-wrapper">
                        <input type="text" name="full_name" id="reg_full_name" required>
                        <div class="input-icon">👤</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" name="username" id="reg_username" required>
                        <div class="input-icon">🔑</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reg_email">Email</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" id="reg_email" required>
                        <div class="input-icon">📧</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_role">Role</label>
                    <div class="input-wrapper">
                        <select name="role" id="reg_role" required>
                            <option value="hr">HR</option>
                            <option value="candidate">Candidate</option>
                            <!-- <option value="employee">Employee</option> -->
                        </select>
                        <div class="input-icon">🎭</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reg_password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="reg_password" required minlength="6">
                        <div class="input-icon">🔒</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_confirm_password">Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="confirm_password" id="reg_confirm_password" required minlength="6">
                        <div class="input-icon">🔐</div>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    Create Account
                </button>
            </form>

            <!-- Forgot Password Form -->
            <?php if ($mode === 'forgot'): ?>
            <form method="POST" class="form-section" id="forgotForm">
                <input type="hidden" name="action" value="forgot">
                
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h2 style="color: var(--secondary-color); margin-bottom: 0.5rem;">Forgot Password</h2>
                    <p style="color: #6b7280;">Enter your email to receive a reset link</p>
                </div>

                <div class="form-group">
                    <label for="forgot_email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" id="forgot_email" required>
                        <div class="input-icon">📧</div>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    Send Reset Link
                </button>

                <div class="text-center">
                    <a href="?mode=login" class="link-btn">Back to Login</a>
                </div>
            </form>
            <?php endif; ?>

            <!-- Reset Password Form -->
<?php if ($mode === 'reset' && isset($_GET['token'])): ?>
<form method="POST" class="form-section" id="resetForm">
    <input type="hidden" name="action" value="reset">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
    
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="color: var(--secondary-color); margin-bottom: 0.5rem;">Reset Password</h2>
        <p style="color: #6b7280;">Enter your new password</p>
    </div>

    <div class="form-group">
        <label for="reset_password">New Password</label>
        <div class="input-wrapper">
            <input type="password" name="password" id="reset_password" required minlength="6">
            <div class="input-icon">🔒</div>
        </div>
    </div>

    <div class="form-group">
        <label for="reset_confirm_password">Confirm New Password</label>
        <div class="input-wrapper">
            <input type="password" name="confirm_password" id="reset_confirm_password" required minlength="6">
            <div class="input-icon">🔐</div>
        </div>
    </div>
    
    <button type="submit" class="submit-btn">
        Update Password
    </button>

    <div class="text-center">
        <a href="?mode=login" class="link-btn">Back to Login</a>
    </div>
</form>
            <?php endif; ?>

            <!-- Demo Accounts (only show for login) -->
            <?php if ($mode === 'login'): ?>
            <div class="demo-section">
                <div class="demo-title">
                    <span>🎭</span>
                    Demo Accounts
                </div>
                <div class="demo-accounts">
                    <div class="demo-account" onclick="fillCredentials('hr_admin', 'password123')">
                        <div class="account-role">🏢 HR Manager</div>
                        <div class="account-credentials">hr_admin / password123</div>
                    </div>
                    <div class="demo-account" onclick="fillCredentials('alice123', 'password123')">
                        <div class="account-role">👤 Candidate</div>
                        <div class="account-credentials">alice123 / password123</div>
                    </div>
                    <div class="demo-account" onclick="fillCredentials('john_d', 'password123')">
                       <div class="account-role">👨‍💼 Employee</div>
                       <div class="account-credentials">john_d / password123</div>
                   </div>
               </div>
           </div>
           <?php endif; ?>
       </div>
   </div>

   <script>
       // Tab switching functionality
       function switchTab(tab) {
           // Update URL without page reload
           const url = new URL(window.location);
           url.searchParams.set('mode', tab);
           window.history.pushState({}, '', url);
           
           // Hide all forms
           document.getElementById('loginForm').classList.add('hidden');
           document.getElementById('registerForm').classList.add('hidden');
           
           // Show selected form
           if (tab === 'login') {
               document.getElementById('loginForm').classList.remove('hidden');
           } else if (tab === 'register') {
               document.getElementById('registerForm').classList.remove('hidden');
           }
           
           // Update tab buttons
           document.querySelectorAll('.tab-btn').forEach(btn => {
               btn.classList.remove('active');
           });
           event.target.classList.add('active');
           
           // Clear any error messages
           const errorMsg = document.querySelector('.error-message');
           if (errorMsg) errorMsg.remove();
           
           const successMsg = document.querySelector('.success-message');
           if (successMsg) successMsg.remove();
       }

       // Fill credentials for demo accounts
       function fillCredentials(username, password) {
           // Make sure we're on login tab
           if (document.getElementById('loginForm').classList.contains('hidden')) {
               switchTab('login');
               // Update the active tab button
               document.querySelectorAll('.tab-btn').forEach(btn => {
                   btn.classList.remove('active');
                   if (btn.textContent.trim() === 'Login') {
                       btn.classList.add('active');
                   }
               });
           }
           
           document.getElementById('login_username').value = username;
           document.getElementById('login_password').value = password;
           
           // Add visual feedback
           const inputs = document.querySelectorAll('#loginForm input');
           inputs.forEach(input => {
               input.style.background = '#f0f9ff';
               input.style.borderColor = '#10b981';
               setTimeout(() => {
                   input.style.background = '';
                   input.style.borderColor = '';
               }, 1500);
           });
       }
       
       // Form validation and UX enhancements
       document.addEventListener('DOMContentLoaded', function() {
           // Auto-focus first input based on current mode
           const mode = '<?php echo $mode; ?>';
           if (mode === 'login') {
               document.getElementById('login_username')?.focus();
           } else if (mode === 'register') {
               document.getElementById('reg_full_name')?.focus();
           } else if (mode === 'forgot') {
               document.getElementById('forgot_email')?.focus();
           } else if (mode === 'reset') {
               document.getElementById('reset_password')?.focus();
           }

           // Password confirmation validation for register form
           const regPassword = document.getElementById('reg_password');
           const regConfirmPassword = document.getElementById('reg_confirm_password');
           
           if (regConfirmPassword) {
               regConfirmPassword.addEventListener('input', function() {
                   if (regPassword.value !== regConfirmPassword.value) {
                       regConfirmPassword.setCustomValidity('Passwords do not match');
                       regConfirmPassword.style.borderColor = '#fca5a5';
                   } else {
                       regConfirmPassword.setCustomValidity('');
                       regConfirmPassword.style.borderColor = '#10b981';
                   }
               });
           }

           // Password confirmation validation for reset form
           const resetPassword = document.getElementById('reset_password');
           const resetConfirmPassword = document.getElementById('reset_confirm_password');
           
           if (resetConfirmPassword) {
               resetConfirmPassword.addEventListener('input', function() {
                   if (resetPassword.value !== resetConfirmPassword.value) {
                       resetConfirmPassword.setCustomValidity('Passwords do not match');
                       resetConfirmPassword.style.borderColor = '#fca5a5';
                   } else {
                       resetConfirmPassword.setCustomValidity('');
                       resetConfirmPassword.style.borderColor = '#10b981';
                   }
               });
           }
           
           // Enhanced input validation
           const inputs = document.querySelectorAll('input');
           inputs.forEach(input => {
               input.addEventListener('blur', function() {
                   if (this.value.trim() === '' && this.required) {
                       this.style.borderColor = '#fca5a5';
                   } else if (this.checkValidity()) {
                       this.style.borderColor = '#10b981';
                   }
               });
               
               input.addEventListener('focus', function() {
                   this.style.borderColor = '#FF6B35';
               });

               input.addEventListener('input', function() {
                   if (this.checkValidity()) {
                       this.style.borderColor = '#10b981';
                   }
               });
           });

           // Username validation (no spaces, special chars)
           const usernameInputs = document.querySelectorAll('input[name="username"]');
           usernameInputs.forEach(input => {
               input.addEventListener('input', function() {
                   // Remove invalid characters
                   this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
                   
                   if (this.value.length >= 3) {
                       this.style.borderColor = '#10b981';
                   } else if (this.value.length > 0) {
                       this.style.borderColor = '#fbbf24';
                   }
               });
           });

           // Email validation
           const emailInputs = document.querySelectorAll('input[type="email"]');
           emailInputs.forEach(input => {
               input.addEventListener('input', function() {
                   const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                   if (emailRegex.test(this.value)) {
                       this.style.borderColor = '#10b981';
                   } else if (this.value.length > 0) {
                       this.style.borderColor = '#fbbf24';
                   }
               });
           });

           // Password strength indicator
           const passwordInputs = document.querySelectorAll('input[type="password"]');
           passwordInputs.forEach(input => {
               if (input.name === 'password') { // Only for main password fields, not confirm
                   input.addEventListener('input', function() {
                       const password = this.value;
                       let strength = 0;
                       
                       if (password.length >= 6) strength++;
                       if (password.match(/[a-z]/)) strength++;
                       if (password.match(/[A-Z]/)) strength++;
                       if (password.match(/[0-9]/)) strength++;
                       if (password.match(/[^a-zA-Z0-9]/)) strength++;
                       
                       if (strength < 2) {
                           this.style.borderColor = '#fca5a5';
                       } else if (strength < 4) {
                           this.style.borderColor = '#fbbf24';
                       } else {
                           this.style.borderColor = '#10b981';
                       }
                   });
               }
           });
       });

       // Form submission with loading states
       document.querySelectorAll('form').forEach(form => {
           form.addEventListener('submit', function() {
               const submitBtn = this.querySelector('.submit-btn');
               if (submitBtn) {
                   submitBtn.style.opacity = '0.8';
                   submitBtn.style.pointerEvents = 'none';
                   
                   const originalText = submitBtn.textContent;
                   submitBtn.textContent = 'Processing...';
                   
                   // Reset after 3 seconds if still on page (in case of error)
                   setTimeout(() => {
                       submitBtn.style.opacity = '';
                       submitBtn.style.pointerEvents = '';
                       submitBtn.textContent = originalText;
                   }, 3000);
               }
           });
       });

       // Handle browser back/forward buttons
       window.addEventListener('popstate', function() {
           const urlParams = new URLSearchParams(window.location.search);
           const mode = urlParams.get('mode') || 'login';
           
           // Hide all forms
           document.getElementById('loginForm').classList.add('hidden');
           document.getElementById('registerForm').classList.add('hidden');
           
           // Show appropriate form
           if (mode === 'login') {
               document.getElementById('loginForm').classList.remove('hidden');
           } else if (mode === 'register') {
               document.getElementById('registerForm').classList.remove('hidden');
           }
           
           // Update tab buttons
           document.querySelectorAll('.tab-btn').forEach(btn => {
               btn.classList.remove('active');
               if ((mode === 'login' && btn.textContent.trim() === 'Login') ||
                   (mode === 'register' && btn.textContent.trim() === 'Register')) {
                   btn.classList.add('active');
               }
           });
       });
   </script>
</body>
</html>