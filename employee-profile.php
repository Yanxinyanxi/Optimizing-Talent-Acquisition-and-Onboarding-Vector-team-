<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Check if the user is an employee
if ($_SESSION['role'] !== 'employee') {
    header('Location: index.php');
    exit;
}

// Check database connection
if (!isset($connection) || $connection->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

$success = false;
$error = '';
$user_info = [];
$job_position = [];

try {
    // Get user basic info
    $stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $connection->error);
    }
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_info = $result->fetch_assoc();
    } else {
        throw new Exception("User not found.");
    }

    // Fetch job position details
    $stmt = $connection->prepare("SELECT jp.title, jp.department FROM job_positions jp WHERE jp.id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $connection->error);
    }
    $stmt->bind_param("i", $user_info['job_position_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $job_position = $result->fetch_assoc();
    } else {
        throw new Exception("Job position not found.");
    }

} catch (Exception $e) {
    error_log("Error fetching profile data: " . $e->getMessage());
    $error = "Error loading profile data: " . $e->getMessage();
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate inputs
        if (empty($full_name) || empty($email)) {
            $error = "Full name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Update without password change
                if (empty($new_password) && empty($confirm_password)) {
                    $stmt = $connection->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception("Update prepare failed: " . $connection->error);
                    }
                    $stmt->bind_param("ssi", $full_name, $email, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        $success = true;
                        $user_info['full_name'] = $full_name;
                        $user_info['email'] = $email;
                        $_SESSION['full_name'] = $full_name;
                    } else {
                        $error = "Failed to update profile.";
                    }
                } else {
                    // Password update flow
                    $stmt = $connection->prepare("SELECT password FROM users WHERE id = ?");
                    if (!$stmt) {
                        throw new Exception("Password check prepare failed: " . $connection->error);
                    }
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();

                    if (!password_verify($current_password, $row['password'])) {
                        $error = "Current password is incorrect.";
                    } elseif ($new_password !== $confirm_password) {
                        $error = "New passwords do not match.";
                    } elseif (strlen($new_password) < 6) {
                        $error = "New password must be at least 6 characters.";
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $connection->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
                        if (!$stmt) {
                            throw new Exception("Password update prepare failed: " . $connection->error);
                        }
                        $stmt->bind_param("sssi", $full_name, $email, $hashed_password, $_SESSION['user_id']);
                        if ($stmt->execute()) {
                            $success = true;
                            $user_info['full_name'] = $full_name;
                            $user_info['email'] = $email;
                            $_SESSION['full_name'] = $full_name;
                        } else {
                            $error = "Failed to update profile with password.";
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error updating profile: " . $e->getMessage());
                $error = "An unexpected error occurred: " . $e->getMessage();
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
    <title>Employee Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #2B4C8C;
            --kabel-gradient: linear-gradient(135deg, #FF6B35 0%, #2B4C8C 100%);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #2B4C8C 0%, #1e3a75 100%);
            padding: 2rem 0;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            text-align: center;
            padding: 0 2rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 2rem;
            flex-shrink: 0;
        }

        .sidebar-brand .logo {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }

        .sidebar-brand h3 {
            color: white;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .sidebar-brand p {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0 1rem;
            flex: 1;
        }

        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            gap: 1rem;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,107,53,0.2);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-nav .icon {
            font-size: 1.2rem;
            width: 20px;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 2rem;
        }

        .user-profile {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }

        .user-profile .avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            margin: 0 auto 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .topbar {
            background: linear-gradient(135deg, rgba(255,107,53,0.1) 0%, rgba(43,76,140,0.1) 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .topbar-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .content-area {
            padding: 2rem;
        }

        /* Profile Card Styles */
        .profile-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            border-left: 4px solid var(--primary-color);
        }

        .profile-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, rgba(255,107,53,0.1) 0%, rgba(43,76,140,0.1) 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .profile-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .profile-body {
            padding: 2rem;
        }

        .resume-data {
    background: rgba(43, 76, 140, 0.05);
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 2rem;
    border-left: 3px solid var(--secondary-color);
}

/* Data Item Styling for Job Details */
.data-item {
    margin-bottom: 1rem;
    padding-top: 1rem;
}

.data-item strong {
    color: var(--secondary-color);
    display: block;
    margin-bottom: 0.25rem;
}

.data-item p {
    color: #555;
    line-height: 1.5;
}

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--secondary-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255,107,53,0.1);
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(255,107,53,0.2);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-toggle {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                border: none;
                padding: 0.75rem;
                border-radius: 8px;
                cursor: pointer;
                font-size: 1.2rem;
            }

            .content-area {
                padding: 1rem;
            }

            .profile-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        .mobile-toggle {
            display: none;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Button Styles for Update Profile */
.btn-primary {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    background: var(--kabel-gradient); /* Gradient background */
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(255, 107, 53, 0.3);
}

/* Disabled Button */
.btn-primary:disabled {
    background: #ddd;
    cursor: not-allowed;
    box-shadow: none;
}
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo">V</div>
            <h3>Vector HR System</h3>
            <p>Employee Portal</p>
        </div>

        <ul class="sidebar-nav">
            <li>
                <a href="employee-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'employee-dashboard.php' ? 'active' : ''; ?>">
                    <span class="icon">🏠</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="employee-profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'employee-profile.php' ? 'active' : ''; ?>">
                    <span class="icon">👤</span>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="onboarding-tasks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'onboarding-tasks.php' ? 'active' : ''; ?>">
                    <span class="icon">📋</span>
                    <span>Onboarding Tasks</span>
                </a>
            </li>
            <li>
                <a href="training-modules.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'training-modules.php' ? 'active' : ''; ?>">
                    <span class="icon">🎓</span>
                    <span>Training Modules</span>
                </a>
            </li>
            <li>
                <a href="required-documents.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'required-documents.php' ? 'active' : ''; ?>">
                    <span class="icon">📄</span>
                    <span>Required Documents</span>
                </a>
            </li>
            <li>
                <a href="get-help.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'get-help.php' ? 'active' : ''; ?>">
                    <span class="icon">❓</span>
                    <span>Get Help</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="avatar"><?php echo substr($user_info['full_name'], 0, 1); ?></div>
                <div style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($user_info['full_name']); ?>
                </div>
                <div style="color: rgba(255,255,255,0.7); font-size: 0.8rem; margin-bottom: 1rem;">
                    Employee
                </div>
                <a href="logout.php" style="color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.8rem;">
                    🚪 Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <h1 class="page-title">👤 My Profile</h1>
        </div>

        <div class="content-area">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ Profile updated successfully.
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="profile-card">
                <div class="profile-header">
                    <h2 class="profile-title">Personal Information</h2>
                </div>

                <div class="profile-body">
                    <form method="POST">
                        <div class="form-section">
                            <h3 class="form-section-title">Basic Information</h3>

                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                    value="<?php echo htmlspecialchars($user_info['full_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                    value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-title">Change Password</h3>
                            <p style="color: #6c757d; margin-bottom: 1rem;">Leave these fields blank if you don't want to change your password.</p>

                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control">
                            </div>

                                                        <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            💾 Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <div class="profile-card">
                <div class="profile-header">
                    <h2 class="profile-title">Job Information</h2>
                </div>

                <div class="profile-body">
                    <div class="resume-data">
                        <h3>Job Details</h3>
                        <div class="data-item">
                            <strong>Job Title</strong>
                            <p><?php echo htmlspecialchars($job_position['title'] ?? 'Not assigned'); ?></p>
                        </div>
                        <div class="data-item">
                            <strong>Department</strong>
                            <p><?php echo htmlspecialchars($job_position['department'] ?? 'Not assigned'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Sidebar visibility
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
</body>
</html>
