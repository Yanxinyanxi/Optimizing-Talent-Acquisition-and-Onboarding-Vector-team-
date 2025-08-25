<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Check database connection
if (!isset($connection) || $connection->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

// Ensure Employee access only
requireRole('employee');

$user_id = $_SESSION['user_id'];

// Get employee info
$stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Get employee's department from their job position
$employee_department = 'ALL';
if ($employee['job_position_id']) {
    $stmt = $connection->prepare("SELECT department FROM job_positions WHERE id = ?");
    $stmt->bind_param("i", $employee['job_position_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($dept_row = $result->fetch_assoc()) {
        $employee_department = $dept_row['department'];
    }
}

// Handle training actions BEFORE fetching data
if ($_POST) {
    if (isset($_POST['start_training'])) {
        $module_id = (int)$_POST['module_id'];
        
        try {
            // Check if module exists and user has access
            $stmt = $connection->prepare("
                SELECT id FROM training_modules 
                WHERE id = ? AND (department = 'ALL' OR department = ?)
            ");
            $stmt->bind_param("is", $module_id, $employee_department);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $stmt = $connection->prepare("
                    INSERT INTO employee_training (employee_id, module_id, status, progress_percentage, started_at) 
                    VALUES (?, ?, 'in_progress', 0, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        status = 'in_progress', 
                        started_at = COALESCE(started_at, NOW()),
                        progress_percentage = COALESCE(progress_percentage, 0)
                ");
                $stmt->bind_param("ii", $user_id, $module_id);
                
                if ($stmt->execute()) {
                    $success = "Training module started successfully!";
                } else {
                    $error = "Failed to start training module.";
                }
            } else {
                $error = "Invalid training module.";
            }
        } catch(Exception $e) {
            $error = "Failed to start training: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_progress'])) {
        $module_id = (int)$_POST['module_id'];
        $progress = (int)$_POST['progress_percentage'];
        
        // Validate progress
        if ($progress < 0) $progress = 0;
        if ($progress > 100) $progress = 100;
        
        // Determine status based on progress
        $status = ($progress >= 100) ? 'completed' : 'in_progress';
        
        try {
            // Check if the training record exists
            $stmt = $connection->prepare("
                SELECT id, progress_percentage FROM employee_training 
                WHERE employee_id = ? AND module_id = ?
            ");
            $stmt->bind_param("ii", $user_id, $module_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            
            if ($existing) {
                // Update existing record
                if ($status == 'completed') {
                    $stmt = $connection->prepare("
                        UPDATE employee_training 
                        SET status = ?, progress_percentage = ?, completed_at = NOW()
                        WHERE employee_id = ? AND module_id = ?
                    ");
                    $stmt->bind_param("siii", $status, $progress, $user_id, $module_id);
                } else {
                    $stmt = $connection->prepare("
                        UPDATE employee_training 
                        SET status = ?, progress_percentage = ?, completed_at = NULL
                        WHERE employee_id = ? AND module_id = ?
                    ");
                    $stmt->bind_param("siii", $status, $progress, $user_id, $module_id);
                }
                
                if ($stmt->execute()) {
                    $success = $progress >= 100 ? "Training module completed successfully!" : "Progress updated successfully!";
                } else {
                    $error = "Failed to update progress.";
                }
            } else {
                $error = "Please start the training module first.";
            }
        } catch(Exception $e) {
            $error = "Failed to update progress: " . $e->getMessage();
        }
    }
    
    // Redirect to prevent form resubmission
    if (isset($success) || isset($error)) {
        $redirect_url = $_SERVER['PHP_SELF'];
        if (isset($success)) {
            $redirect_url .= '?success=' . urlencode($success);
        } else {
            $redirect_url .= '?error=' . urlencode($error);
        }
        header("Location: $redirect_url");
        exit;
    }
}

// Handle URL parameters for messages
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Get training modules and progress
try {
    $stmt = $connection->prepare("
        SELECT 
            tm.id,
            tm.module_name,
            tm.description,
            tm.content_url,
            tm.department,
            tm.duration_hours,
            tm.is_mandatory,
            COALESCE(et.status, 'not_started') as status,
            COALESCE(et.progress_percentage, 0) as progress_percentage,
            et.started_at,
            et.completed_at
        FROM training_modules tm
        LEFT JOIN employee_training et ON tm.id = et.module_id AND et.employee_id = ?
        WHERE tm.department = 'ALL' OR tm.department = ?
        ORDER BY tm.is_mandatory DESC, tm.id
    ");
    $stmt->bind_param("is", $user_id, $employee_department);
    $stmt->execute();
    $all_modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Separate mandatory and optional modules
    $mandatory_modules = [];
    $optional_modules = [];
    foreach ($all_modules as $module) {
        if ($module['is_mandatory'] == 1) {
            $mandatory_modules[] = $module;
        } else {
            $optional_modules[] = $module;
        }
    }
    
    // Calculate progress - IMPROVED CALCULATION
    $total_mandatory = count($mandatory_modules);
    $completed_mandatory = 0;
    $in_progress_count = 0;
    $total_hours = 0;
    $completed_hours = 0;
    $total_progress_points = 0;
    $earned_progress_points = 0;
    
    foreach ($mandatory_modules as $module) {
        $total_hours += $module['duration_hours'];
        $total_progress_points += 100; // Each module contributes 100 points when fully complete
        
        $progress_percent = (int)$module['progress_percentage'];
        
        if ($module['status'] == 'completed' || $progress_percent >= 100) {
            $completed_mandatory++;
            $completed_hours += $module['duration_hours'];
            $earned_progress_points += 100;
        } elseif ($module['status'] == 'in_progress' && $progress_percent > 0) {
            $in_progress_count++;
            $completed_hours += ($module['duration_hours'] * $progress_percent / 100);
            $earned_progress_points += $progress_percent;
        }
        // not_started modules contribute 0 to both completed_hours and earned_progress_points
    }
    
    // Overall completion percentage based on weighted progress
    $completion_percentage = $total_progress_points > 0 ? round(($earned_progress_points / $total_progress_points) * 100) : 0;
    $hours_percentage = $total_hours > 0 ? round(($completed_hours / $total_hours) * 100) : 0;
    
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $all_modules = [];
    $mandatory_modules = [];
    $optional_modules = [];
    $completion_percentage = 0;
    $completed_mandatory = 0;
    $in_progress_count = 0;
    $total_hours = 0;
    $completed_hours = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Modules - Vector HR System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #2B4C8C;
            --kabel-gradient: linear-gradient(135deg, #FF6B35 0%, #2B4C8C 100%);
            --border-radius: 12px;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
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
            padding: 2rem 0 0 0;
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
            background: white;
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
        
        /* Progress Overview */
        .progress-overview {
            background: var(--kabel-gradient);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 2rem;
        }
        
        .progress-info h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .progress-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            position: relative;
            overflow: hidden;
        }
        
        .progress-circle::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 50%;
            background: conic-gradient(
                rgba(255,255,255,0.8) 0deg,
                rgba(255,255,255,0.8) calc(var(--progress) * 3.6deg),
                rgba(255,255,255,0.2) calc(var(--progress) * 3.6deg),
                rgba(255,255,255,0.2) 360deg
            );
            animation: progressSpin 2s ease-in-out;
        }
        
        @keyframes progressSpin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--kabel-gradient);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .card-header .icon {
            font-size: 1.5rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        /* Training Module Cards Grid */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .module-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .module-card.completed {
            border-left-color: var(--success-color);
            background: rgba(40, 167, 69, 0.02);
        }
        
        .module-card.in-progress {
            border-left-color: var(--warning-color);
            background: rgba(255, 193, 7, 0.02);
        }
        
        .module-card.not-started {
            border-left-color: #6c757d;
        }
        
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .module-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .module-meta {
            font-size: 0.9rem;
            color: #6c757d;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .module-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .module-status.completed {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .module-status.in-progress {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .module-status.not-started {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .module-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .module-progress {
            margin-bottom: 1.5rem;
        }
        
        .progress-bar-container {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .progress-bar {
            background: var(--kabel-gradient);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            transition: width 0.6s ease;
        }
        
        .progress-text {
            font-size: 0.9rem;
            color: #6c757d;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .module-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .progress-controls {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.05) 0%, rgba(43, 76, 140, 0.05) 100%);
            border: 2px solid rgba(255, 107, 53, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .progress-controls h5 {
            color: var(--secondary-color);
            margin-bottom: 1rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .progress-update-form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .progress-input {
            width: 90px;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .progress-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
            outline: none;
        }
        
        .progress-step-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .step-btn {
            padding: 0.75rem 1rem;
            border: 2px solid rgba(255, 107, 53, 0.2);
            background: white;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: var(--secondary-color);
            position: relative;
            overflow: hidden;
        }
        
        .step-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--kabel-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .step-btn span {
            position: relative;
            z-index: 1;
        }
        
        .step-btn:hover:before {
            opacity: 1;
        }
        
        .step-btn:hover {
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }
        
        .update-btn {
            background: var(--kabel-gradient);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--kabel-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.3);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: #856404;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-color: #dc3545;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-color: #28a745;
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
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .modules-grid {
                grid-template-columns: 1fr;
            }
            
            .progress-overview {
                flex-direction: column;
                text-align: center;
            }
            
            .progress-stats {
                justify-content: center;
            }
            
            .topbar {
                padding-left: 4rem;
            }
            
            .page-title {
                font-size: 1.25rem;
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
                <div class="avatar"><?php echo substr($employee['full_name'], 0, 1); ?></div>
                <div style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($employee['full_name']); ?>
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
            <h1 class="page-title">🎓 Training Modules</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Build your skills with our training programs
                </span>
            </div>
        </div>
        
        <div class="content-area">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <span>⚠️</span>
                    <div>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div>
                        <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Progress Overview -->
            <div class="progress-overview">
                <div class="progress-info">
                    <h2>Your Training Progress</h2>
                    <p style="opacity: 0.9; margin-bottom: 0;">
                        Complete your required training modules to enhance your skills
                    </p>
                    <div class="progress-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $completed_mandatory; ?></span>
                            <span class="stat-label">Completed</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $in_progress_count; ?></span>
                            <span class="stat-label">In Progress</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo round($completed_hours, 1); ?>h</span>
                            <span class="stat-label">Hours Done</span>
                        </div>
                    </div>
                </div>
                <div class="progress-circle" style="--progress: <?php echo $completion_percentage; ?>">
                    <?php echo $completion_percentage; ?>%
                </div>
            </div>

            <!-- Overall Progress -->
            <div class="card">
                <div class="card-body">
                    <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">Overall Training Progress</h4>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%">
                            <?php echo $completion_percentage; ?>%
                        </div>
                    </div>
                    <div class="progress-text">
                        <span><?php echo $completed_mandatory; ?> of <?php echo $total_mandatory; ?> modules completed</span>
                        <span><?php echo round($completed_hours, 1); ?> / <?php echo $total_hours; ?> hours</span>
                    </div>
                </div>
            </div>

            <!-- Mandatory Training Modules -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">⭐</span>
                    <div>
                        <h3>Required Training Modules</h3>
                        <p style="margin: 0; opacity: 0.9;">Mandatory training for your role</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="modules-grid">
                        <?php foreach ($mandatory_modules as $module): ?>
                            <div class="module-card <?php echo str_replace('_', '-', $module['status']); ?>">
                                <div class="module-status <?php echo str_replace('_', '-', $module['status']); ?>">
                                    <?php echo str_replace('_', ' ', $module['status']); ?>
                                </div>
                                
                                <div class="module-header">
                                    <div>
                                        <div class="module-title">
                                            <?php echo htmlspecialchars($module['module_name']); ?>
                                        </div>
                                        <div class="module-meta">
                                            <span>⏱️ <?php echo $module['duration_hours']; ?> hours</span>
                                            <?php if ($module['department'] != 'ALL'): ?>
                                                <span>• <?php echo htmlspecialchars($module['department']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="module-description">
                                    <?php echo htmlspecialchars($module['description']); ?>
                                </div>
                                
                                <?php if ($module['status'] != 'not_started'): ?>
                                    <div class="module-progress">
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo $module['progress_percentage']; ?>%">
                                                <?php echo $module['progress_percentage']; ?>%
                                            </div>
                                        </div>
                                        <div class="progress-text">
                                            <span>Progress: <?php echo $module['progress_percentage']; ?>%</span>
                                            <?php if ($module['started_at']): ?>
                                                <span>Started: <?php echo date('M j, Y', strtotime($module['started_at'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="module-actions">
                                    <?php if ($module['status'] == 'completed'): ?>
                                        <div style="color: var(--success-color); display: flex; align-items: center; gap: 0.5rem;">
                                            <span>✅</span>
                                            <span>Completed on <?php echo date('M j, Y', strtotime($module['completed_at'])); ?></span>
                                        </div>
                                    <?php elseif ($module['status'] == 'in_progress'): ?>
                                        <div style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
                                            <?php if ($module['content_url']): ?>
                                                <a href="<?php echo htmlspecialchars($module['content_url']); ?>" target="_blank" class="btn btn-primary btn-sm">
                                                    📖 Continue Training
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm" disabled>
                                                    📖 Content Not Available
                                                </button>
                                            <?php endif; ?>
                                            
                                            <div class="progress-controls">
                                                <h5>📊 Update Training Progress</h5>
                                                
                                                <div class="progress-step-buttons">
                                                    <button type="button" class="step-btn" onclick="setProgress(<?php echo $module['id']; ?>, 25)">
                                                        <span>25%</span>
                                                    </button>
                                                    <button type="button" class="step-btn" onclick="setProgress(<?php echo $module['id']; ?>, 50)">
                                                        <span>50%</span>
                                                    </button>
                                                    <button type="button" class="step-btn" onclick="setProgress(<?php echo $module['id']; ?>, 75)">
                                                        <span>75%</span>
                                                    </button>
                                                    <button type="button" class="step-btn" onclick="setProgress(<?php echo $module['id']; ?>, 100)">
                                                        <span>✅</span>
                                                    </button>
                                                </div>
                                                
                                                <div style="text-align: center; margin-bottom: 1rem; color: #6c757d; font-size: 0.9rem;">
                                                    Or set a custom progress:
                                                </div>
                                                
                                                <form method="POST" class="progress-update-form">
                                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                    <input type="hidden" name="update_progress" value="1">
                                                    
                                                    <input type="number" name="progress_percentage" class="progress-input" 
                                                           min="0" max="100" value="<?php echo $module['progress_percentage']; ?>" 
                                                           placeholder="0-100" required>
                                                    <span style="font-size: 1rem; color: #6c757d; font-weight: 600;">%</span>
                                                    <button type="submit" class="update-btn">
                                                        <span>🔄</span>
                                                        <span>Update</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" style="width: 100%;">
                                            <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                            <input type="hidden" name="start_training" value="1">
                                            <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
                                                🚀 Start Training
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($mandatory_modules)): ?>
                            <div style="text-align: center; padding: 2rem; color: #6c757d;">
                                <span style="font-size: 3rem;">📚</span>
                                <p>No mandatory training modules assigned to you at this time.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Optional Training Modules -->
            <?php if (!empty($optional_modules)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="icon">💡</span>
                    <div>
                        <h3>Optional Training Modules</h3>
                        <p style="margin: 0; opacity: 0.9;">Additional training to enhance your skills</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="modules-grid">
                        <?php foreach ($optional_modules as $module): ?>
                            <div class="module-card <?php echo str_replace('_', '-', $module['status']); ?>">
                                <div class="module-status <?php echo str_replace('_', '-', $module['status']); ?>">
                                    <?php echo str_replace('_', ' ', $module['status']); ?>
                                </div>
                                
                                <div class="module-header">
                                    <div>
                                        <div class="module-title">
                                            <?php echo htmlspecialchars($module['module_name']); ?>
                                        </div>
                                        <div class="module-meta">
                                            <span>⏱️ <?php echo $module['duration_hours']; ?> hours</span>
                                            <span>• Optional</span>
                                            <?php if ($module['department'] != 'ALL'): ?>
                                                <span>• <?php echo htmlspecialchars($module['department']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="module-description">
                                    <?php echo htmlspecialchars($module['description']); ?>
                                </div>
                                
                                <?php if ($module['status'] != 'not_started'): ?>
                                    <div class="module-progress">
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo $module['progress_percentage']; ?>%">
                                                <?php echo $module['progress_percentage']; ?>%
                                            </div>
                                        </div>
                                        <div class="progress-text">
                                            <span>Progress: <?php echo $module['progress_percentage']; ?>%</span>
                                            <?php if ($module['started_at']): ?>
                                                <span>Started: <?php echo date('M j, Y', strtotime($module['started_at'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="module-actions">
                                    <?php if ($module['status'] == 'completed'): ?>
                                        <div style="color: var(--success-color); display: flex; align-items: center; gap: 0.5rem;">
                                            <span>✅</span>
                                            <span>Completed on <?php echo date('M j, Y', strtotime($module['completed_at'])); ?></span>
                                        </div>
                                    <?php elseif ($module['status'] == 'in_progress'): ?>
                                        <div style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
                                            <?php if ($module['content_url']): ?>
                                                <a href="<?php echo htmlspecialchars($module['content_url']); ?>" target="_blank" class="btn btn-primary btn-sm">
                                                    📖 Continue Training
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm" disabled>
                                                    📖 Content Not Available
                                                </button>
                                            <?php endif; ?>
                                            
                                            <div class="progress-controls">
                                                <h5>📊 Update Training Progress</h5>
                                                
                                                <div class="progress-step-buttons">
                                                    <button type="button" class="step-btn" onclick="setProgress(<?php echo $module['id']; ?>, 25)">
                                                        <span>25%</span>
                                                    </button>
                                                    <button type="button" class="step-btn" onclick="setProgress(<?php echo $module['id']; ?>, 50)">
                                                        <span>50%</span>
                                                    </button>
                                                    <button type="button" class="step-btn" onclick="setProgress(<?php echo $module['id']; ?>, 75)">
                                                        <span>75%</span>
                                                    </button>
                                                    <button type="button" class="step-btn" onclick="setProgress(<?php echo $module['id']; ?>, 100)">
                                                        <span>✅ Complete</span>
                                                    </button>
                                                </div>
                                                
                                                <div style="text-align: center; margin-bottom: 1rem; color: #6c757d; font-size: 0.9rem;">
                                                    Or set a custom progress:
                                                </div>
                                                
                                                <form method="POST" class="progress-update-form">
                                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                    <input type="hidden" name="update_progress" value="1">
                                                    
                                                    <input type="number" name="progress_percentage" class="progress-input" 
                                                           min="0" max="100" value="<?php echo $module['progress_percentage']; ?>" 
                                                           placeholder="0-100" required>
                                                    <span style="font-size: 1rem; color: #6c757d; font-weight: 600;">%</span>
                                                    <button type="submit" class="update-btn">
                                                        <span>🔄</span>
                                                        <span>Update</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" style="width: 100%;">
                                            <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                            <input type="hidden" name="start_training" value="1">
                                            <button type="submit" class="btn btn-outline btn-sm" style="width: 100%;">
                                                🚀 Start Training
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        function setProgress(moduleId, percentage) {
            // Find the form for this module
            const moduleCard = document.querySelector(`input[name="module_id"][value="${moduleId}"]`).closest('.module-card');
            const progressInput = moduleCard.querySelector('input[name="progress_percentage"]');
            const form = progressInput.closest('form');
            
            // Set the progress value
            progressInput.value = percentage;
            
            // Add visual feedback
            const submitBtn = form.querySelector('.update-btn');
            const originalContent = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span>⏳</span><span>Updating...</span>';
            submitBtn.disabled = true;
            
            // Submit the form
            form.submit();
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
        
        // Add loading state to buttons when forms are submitted
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        
                        if (submitBtn.classList.contains('update-btn')) {
                            submitBtn.innerHTML = '<span>⏳</span><span>Updating...</span>';
                        } else {
                            submitBtn.innerHTML = '⏳ Processing...';
                        }
                        
                        // Re-enable after 5 seconds if form doesn't redirect
                        setTimeout(function() {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 5000);
                    }
                });
            });
        });
        
        // Validate progress input with real-time feedback
        document.addEventListener('DOMContentLoaded', function() {
            const progressInputs = document.querySelectorAll('.progress-input');
            progressInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    let value = parseInt(this.value);
                    if (isNaN(value)) value = 0;
                    if (value > 100) {
                        this.value = 100;
                        this.style.borderColor = '#ffc107';
                    } else if (value < 0) {
                        this.value = 0;
                        this.style.borderColor = '#ffc107';
                    } else {
                        this.style.borderColor = '#e9ecef';
                    }
                });
                
                input.addEventListener('focus', function() {
                    this.style.borderColor = 'var(--primary-color)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.borderColor = '#e9ecef';
                });
            });
        });
    </script>
</body>
</html>