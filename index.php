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

// Handle login form submission
if ($_POST) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kabel Talent Hub - Login</title>
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
        
        .login-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(25px);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 
                0 25px 50px rgba(43, 76, 140, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--kabel-gradient);
            border-radius: 24px 24px 0 0;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .brand-section {
            text-align: center;
            margin-bottom: 2.5rem;
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
            font-weight: 400;
        }
        
        .form-section {
            margin-bottom: 2rem;
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
        
        .form-group input {
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
        
        .form-group input:focus {
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
        
        .form-group input:focus + .input-icon {
            color: var(--primary-color);
        }
        
        .login-btn {
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
        
        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(255, 107, 53, 0.4);
        }
        
        .login-btn:hover::before {
            left: 100%;
        }
        
        .login-btn:active {
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
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
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
        
        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            color: #9ca3af;
            font-size: 0.9rem;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
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
            
            .login-container {
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
        
        /* Loading state */
        .login-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .login-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }
        
        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="login-container">
        <div class="brand-section">
            <div class="logo">K</div>
            <h1 class="brand-title">Kabel Talent Hub</h1>
            <p class="brand-subtitle">Welcome back! Please sign in to your account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <span>⚠️</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="form-section" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input type="text" name="username" id="username" required autocomplete="username">
                    <div class="input-icon">👤</div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" required autocomplete="current-password">
                    <div class="input-icon">🔒</div>
                </div>
            </div>
            
            <button type="submit" class="login-btn" id="loginBtn">
                Sign In
            </button>
        </form>
        
        <div class="divider">Demo Accounts</div>
        
        <div class="demo-section">
            <div class="demo-title">
                <span>🎭</span>
                Quick Access
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
    </div>

    <script>
        // Fill credentials when demo account is clicked
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Add a subtle animation to indicate the fields were filled
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.style.background = '#f0f9ff';
                setTimeout(() => {
                    input.style.background = '';
                }, 1000);
            });
        }
        
        // Add loading state to login button
        document.getElementById('loginForm').addEventListener('submit', function() {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.classList.add('loading');
            loginBtn.textContent = 'Signing In...';
        });
        
        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Add enter key support for demo accounts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.closest('.demo-account')) {
                e.target.closest('.demo-account').click();
            }
        });
        
        // Enhanced form validation with better UX
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.style.borderColor = '#fca5a5';
                } else {
                    this.style.borderColor = '#10b981';
                }
            });
            
            input.addEventListener('focus', function() {
                this.style.borderColor = '#FF6B35';
            });
        });
    </script>
</body>
</html>