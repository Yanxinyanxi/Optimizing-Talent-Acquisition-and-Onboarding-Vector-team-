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

// Check if department column exists
$column_check = $connection->query("SHOW COLUMNS FROM users LIKE 'department'");
$has_department_column = $column_check && $column_check->num_rows > 0;

// Get employee's department
if ($has_department_column) {
    $employee_department = $employee['department'] ?? 'ALL';
} else {
    $employee_department = 'ALL'; // Fallback if no department column
}

// Get all onboarding tasks and progress - with department filtering if available
try {
    if ($has_department_column && $employee_department != 'ALL') {
        // Filter by department if we have department info
        $stmt = $connection->prepare("
            SELECT 
                ot.id,
                ot.task_name,
                ot.description,
                ot.order_sequence,
                ot.department,
                ot.is_mandatory,
                COALESCE(eo.status, 'pending') as status,
                eo.completed_at,
                eo.notes
            FROM onboarding_tasks ot
            LEFT JOIN employee_onboarding eo ON ot.id = eo.task_id AND eo.employee_id = ?
            WHERE (ot.department = 'ALL' OR ot.department = ?)
            ORDER BY ot.order_sequence
        ");
        $stmt->bind_param("is", $user_id, $employee_department);
    } else {
        // Show all tasks if no department filtering available
        $stmt = $connection->prepare("
            SELECT 
                ot.id,
                ot.task_name,
                ot.description,
                ot.order_sequence,
                ot.department,
                ot.is_mandatory,
                COALESCE(eo.status, 'pending') as status,
                eo.completed_at,
                eo.notes
            FROM onboarding_tasks ot
            LEFT JOIN employee_onboarding eo ON ot.id = eo.task_id AND eo.employee_id = ?
            ORDER BY ot.order_sequence
        ");
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result === false) {
        throw new Exception("Failed to fetch tasks: " . $connection->error);
    }
    
    $all_tasks = $result->fetch_all(MYSQLI_ASSOC);
    
    // Separate mandatory and optional tasks
    $mandatory_tasks = [];
    $optional_tasks = [];
    foreach ($all_tasks as $task) {
        if ($task['is_mandatory'] == 1) {
            $mandatory_tasks[] = $task;
        } else {
            $optional_tasks[] = $task;
        }
    }
    
    // Calculate progress
    $total_mandatory = count($mandatory_tasks);
    $completed_mandatory = 0;
    $in_progress_count = 0;
    
    foreach ($mandatory_tasks as $task) {
        if ($task['status'] == 'completed') {
            $completed_mandatory++;
        } elseif ($task['status'] == 'in_progress') {
            $in_progress_count++;
        }
    }
    
    $completion_percentage = $total_mandatory > 0 ? round(($completed_mandatory / $total_mandatory) * 100) : 0;
    
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $all_tasks = [];
    $mandatory_tasks = [];
    $optional_tasks = [];
    $completion_percentage = 0;
}

// Handle task actions
if ($_POST) {
    if (isset($_POST['start_task'])) {
        $task_id = $_POST['task_id'];
        
        try {
            $stmt = $connection->prepare("
                INSERT INTO employee_onboarding (employee_id, task_id, status) 
                VALUES (?, ?, 'in_progress') 
                ON DUPLICATE KEY UPDATE status = 'in_progress'
            ");
            $stmt->bind_param("ii", $user_id, $task_id);
            
            if ($stmt->execute()) {
                $success = "Task started successfully!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } catch(Exception $e) {
            $error = "Failed to start task: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['complete_task'])) {
        $task_id = $_POST['task_id'];
        $notes = $_POST['notes'] ?? '';
        
        try {
            $stmt = $connection->prepare("
                INSERT INTO employee_onboarding (employee_id, task_id, status, completed_at, notes) 
                VALUES (?, ?, 'completed', NOW(), ?) 
                ON DUPLICATE KEY UPDATE status = 'completed', completed_at = NOW(), notes = ?
            ");
            $stmt->bind_param("iiss", $user_id, $task_id, $notes, $notes);
            
            if ($stmt->execute()) {
                $success = "Task completed successfully!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } catch(Exception $e) {
            $error = "Failed to complete task: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding Tasks - Vector HR System</title>
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
        
        /* Task Timeline */
        .task-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .task-timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .task-item {
            position: relative;
            margin-bottom: 2rem;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .task-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .task-item.completed {
            border-left-color: var(--success-color);
            background: rgba(40, 167, 69, 0.02);
        }
        
        .task-item.in-progress {
            border-left-color: var(--warning-color);
            background: rgba(255, 193, 7, 0.02);
        }
        
        .task-item.pending {
            border-left-color: #6c757d;
        }
        
        .task-item::before {
            content: '';
            position: absolute;
            left: -2.25rem;
            top: 1.5rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 2px;
        }
        
        .task-item.completed::before {
            background: var(--success-color);
            box-shadow: 0 0 0 2px var(--success-color);
            content: '✓';
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .task-item.in-progress::before {
            background: var(--warning-color);
            box-shadow: 0 0 0 2px var(--warning-color);
        }
        
        .task-item.pending::before {
            background: #6c757d;
            box-shadow: 0 0 0 2px #6c757d;
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .task-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .task-meta {
            font-size: 0.9rem;
            color: #6c757d;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .task-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .task-status.completed {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .task-status.in-progress {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .task-status.pending {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .task-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .task-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .task-completion-info {
            font-size: 0.9rem;
            color: var(--success-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Progress Bar */
        .progress {
            background: #e9ecef;
            border-radius: 10px;
            height: 25px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .progress-bar {
            background: var(--kabel-gradient);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            transition: width 0.6s ease;
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
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1050;
            padding: 2rem;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
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
            
            .progress-overview {
                flex-direction: column;
                text-align: center;
            }
            
            .progress-stats {
                justify-content: center;
            }
            
            .task-timeline {
                padding-left: 1rem;
            }
            
            .task-timeline::before {
                left: 0.5rem;
            }
            
            .task-item::before {
                left: -1.75rem;
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
            <h1 class="page-title">📋 Onboarding Tasks</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Track your onboarding progress
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
                    <h2>Your Onboarding Progress</h2>
                    <p style="opacity: 0.9; margin-bottom: 0;">
                        Complete all mandatory tasks to finish your onboarding journey
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
                            <span class="stat-number"><?php echo $total_mandatory - $completed_mandatory - $in_progress_count; ?></span>
                            <span class="stat-label">Pending</span>
                        </div>
                    </div>
                </div>
                <div class="progress-circle">
                    <?php echo $completion_percentage; ?>%
                </div>
            </div>

            <!-- Overall Progress Bar -->
            <div class="card">
                <div class="card-body">
                    <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">Overall Progress</h4>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%">
                            <?php echo $completion_percentage; ?>% Complete
                        </div>
                    </div>
                    <p style="color: #6c757d; margin: 0.5rem 0 0 0;">
                        <?php echo $completed_mandatory; ?> of <?php echo $total_mandatory; ?> mandatory tasks completed
                    </p>
                </div>
            </div>

            <!-- Mandatory Tasks -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">⭐</span>
                    <div>
                        <h3>Mandatory Tasks</h3>
                        <p style="margin: 0; opacity: 0.9;">Required tasks to complete your onboarding</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="task-timeline">
                        <?php foreach ($mandatory_tasks as $index => $task): ?>
                            <div class="task-item <?php echo $task['status']; ?>">
                                <div class="task-header">
                                    <div>
                                        <div class="task-title">
                                            <?php echo ($index + 1) . '. ' . htmlspecialchars($task['task_name']); ?>
                                        </div>
                                        <div class="task-meta">
                                            <span>Step <?php echo $task['order_sequence']; ?></span>
                                            <?php if ($task['department'] != 'ALL'): ?>
                                                <span>• <?php echo htmlspecialchars($task['department']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="task-status <?php echo $task['status']; ?>">
                                        <?php echo str_replace('_', ' ', $task['status']); ?>
                                    </div>
                                </div>
                                
                                <div class="task-description">
                                    <?php echo htmlspecialchars($task['description']); ?>
                                </div>
                                
                                <div class="task-actions">
                                    <div>
                                        <?php if ($task['status'] == 'completed'): ?>
                                            <div class="task-completion-info">
                                                <span>✅</span>
                                                <span>Completed on <?php echo date('M j, Y', strtotime($task['completed_at'])); ?></span>
                                            </div>
                                        <?php elseif ($task['status'] == 'in_progress'): ?>
                                            <button class="btn btn-success btn-sm" onclick="completeTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['task_name']); ?>')">
                                                ✓ Complete Task
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="start_task" value="1">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    ▶️ Start Task
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($task['notes']): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(40, 167, 69, 0.05); border-radius: 8px; border-left: 3px solid var(--success-color);">
                                        <small style="color: #6c757d;">
                                            <strong>Your Notes:</strong> <?php echo htmlspecialchars($task['notes']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Optional Tasks -->
            <?php if (!empty($optional_tasks)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="icon">💡</span>
                    <div>
                        <h3>Optional Tasks</h3>
                        <p style="margin: 0; opacity: 0.9;">Additional tasks to enhance your onboarding experience</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="task-timeline">
                        <?php foreach ($optional_tasks as $task): ?>
                            <div class="task-item <?php echo $task['status']; ?>">
                                <div class="task-header">
                                    <div>
                                        <div class="task-title">
                                            <?php echo htmlspecialchars($task['task_name']); ?>
                                        </div>
                                        <div class="task-meta">
                                            <span>Optional</span>
                                            <?php if ($task['department'] != 'ALL'): ?>
                                                <span>• <?php echo htmlspecialchars($task['department']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="task-status <?php echo $task['status']; ?>">
                                        <?php echo str_replace('_', ' ', $task['status']); ?>
                                    </div>
                                </div>
                                
                                <div class="task-description">
                                    <?php echo htmlspecialchars($task['description']); ?>
                                </div>
                                
                                <div class="task-actions">
                                    <div>
                                        <?php if ($task['status'] == 'completed'): ?>
                                            <div class="task-completion-info">
                                                <span>✅</span>
                                                <span>Completed on <?php echo date('M j, Y', strtotime($task['completed_at'])); ?></span>
                                            </div>
                                        <?php elseif ($task['status'] == 'in_progress'): ?>
                                            <button class="btn btn-success btn-sm" onclick="completeTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['task_name']); ?>')">
                                                ✓ Complete Task
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="start_task" value="1">
                                                <button type="submit" class="btn btn-outline btn-sm">
                                                    ▶️ Start Task
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($task['notes']): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(40, 167, 69, 0.05); border-radius: 8px; border-left: 3px solid var(--success-color);">
                                        <small style="color: #6c757d;">
                                            <strong>Your Notes:</strong> <?php echo htmlspecialchars($task['notes']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Task Completion Modal -->
    <div class="modal" id="taskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Complete Task</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Task:</label>
                    <p id="taskNameDisplay" style="color: var(--secondary-color); font-weight: 600;"></p>
                </div>
                <div class="form-group">
                    <label for="notes" class="form-label">Notes (Optional):</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3" 
                              placeholder="Add any notes about completing this task..."></textarea>
                </div>
                <input type="hidden" name="task_id" id="taskId">
                <input type="hidden" name="complete_task" value="1">
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn" onclick="closeModal()" 
                            style="background: #6c757d; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success">✓ Mark Complete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        function completeTask(taskId, taskName) {
            document.getElementById('taskId').value = taskId;
            document.getElementById('taskNameDisplay').textContent = taskName;
            document.getElementById('taskModal').classList.add('show');
            document.getElementById('notes').value = '';
        }
        
        function closeModal() {
            document.getElementById('taskModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('taskModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal with escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
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
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '⏳ Processing...';
                    }
                });
            });
        });
    </script>
</body>
</html>