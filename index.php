<?php
require_once 'includes/auth.php';

// Force logout if logout parameter is present
if (isset($_GET['logout'])) {
    session_start();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Redirect if already logged in
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
    <title>Kabel HR - Talent Acquisition System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
        }
        
        .hero-section {
            text-align: center;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .hero-tagline {
            font-size: 1rem;
            opacity: 0.8;
            font-style: italic;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 3rem 0;
        }
        
        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .feature-description {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            .login-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 2rem;">
                <div class="logo-icon" style="width: 60px; height: 60px; font-size: 2rem;">K</div>
                <div>
                    <h1 class="hero-title">Kabel HR System</h1>
                    <p class="hero-subtitle">Empowering Digital Transformation in Talent Acquisition</p>
                    <p class="hero-tagline">"Connecting Excellence, Powering Growth"</p>
                </div>
            </div>
            
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">🤖</div>
                    <div class="feature-title">AI-Powered Matching</div>
                    <div class="feature-description">Smart resume analysis with skill matching algorithms</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <div class="feature-title">Real-time Analytics</div>
                    <div class="feature-description">Track hiring progress and onboarding metrics</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🚀</div>
                    <div class="feature-title">Seamless Onboarding</div>
                    <div class="feature-description">Streamlined employee integration process</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <div class="feature-title">24/7 Support Bot</div>
                    <div class="feature-description">Instant assistance for employee queries</div>
                </div>
            </div>
        </div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">Sign in to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    Sign In
                </button>
            </form>

            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e9ecef; text-align: center;">
                <p style="color: #6c757d; font-size: 0.9rem;">
                    <strong>Demo Accounts:</strong><br>
                    HR: <code>hr_admin</code> / <code>password123</code><br>
                    New candidates can upload resumes directly
                </p>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>