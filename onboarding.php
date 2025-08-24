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

// Ensure HR access only
requireRole('hr');

// Get onboarding statistics with proper error handling
try {
    // Total employees
    $stmt = $connection->query("SELECT COUNT(*) as total FROM users WHERE role = 'employee'");
    if ($stmt === false) {
        throw new Exception("Failed to count employees: " . $connection->error);
    }
    $total_employees = $stmt->fetch_assoc()['total'];
    
    // Check if department column exists, if not, treat all users as 'ALL' department
    $column_check = $connection->query("SHOW COLUMNS FROM users LIKE 'department'");
    $has_department_column = $column_check && $column_check->num_rows > 0;
    
    if ($has_department_column) {
        // Use department filtering if column exists
        $stmt = $connection->query("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.username,
                COALESCE(u.department, 'ALL') as department,
                u.created_at as hire_date,
                COUNT(ot.id) as total_tasks,
                SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                MAX(eo.completed_at) as last_updated,
                CASE 
                    WHEN COUNT(ot.id) = 0 THEN 'not_started'
                    WHEN SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) = COUNT(ot.id) THEN 'completed'
                    WHEN SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'in_progress'
                    ELSE 'not_started'
                END as status,
                CASE 
                    WHEN COUNT(ot.id) = 0 THEN 0
                    ELSE ROUND((SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) / COUNT(ot.id)) * 100)
                END as progress_percentage
            FROM users u
            LEFT JOIN onboarding_tasks ot ON (ot.department = 'ALL' OR ot.department = COALESCE(u.department, 'ALL')) AND ot.is_mandatory = 1
            LEFT JOIN employee_onboarding eo ON u.id = eo.employee_id AND ot.id = eo.task_id
            WHERE u.role = 'employee'
            GROUP BY u.id, u.full_name, u.email, u.username, u.department, u.created_at
            ORDER BY progress_percentage DESC, u.created_at DESC
        ");
    } else {
        // Fallback: No department filtering (show all mandatory tasks to all employees)
        $stmt = $connection->query("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.username,
                'ALL' as department,
                u.created_at as hire_date,
                COUNT(ot.id) as total_tasks,
                SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                MAX(eo.completed_at) as last_updated,
                CASE 
                    WHEN COUNT(ot.id) = 0 THEN 'not_started'
                    WHEN SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) = COUNT(ot.id) THEN 'completed'
                    WHEN SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'in_progress'
                    ELSE 'not_started'
                END as status,
                CASE 
                    WHEN COUNT(ot.id) = 0 THEN 0
                    ELSE ROUND((SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) / COUNT(ot.id)) * 100)
                END as progress_percentage
            FROM users u
            LEFT JOIN onboarding_tasks ot ON ot.is_mandatory = 1
            LEFT JOIN employee_onboarding eo ON u.id = eo.employee_id AND ot.id = eo.task_id
            WHERE u.role = 'employee'
            GROUP BY u.id, u.full_name, u.email, u.username, u.created_at
            ORDER BY progress_percentage DESC, u.created_at DESC
        ");
    }
    
    if ($stmt === false) {
        throw new Exception("Failed to fetch employee onboarding data: " . $connection->error);
    }
    
    $employees = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Calculate statistics
    $completed_onboarding = 0;
    $in_progress_onboarding = 0;
    $not_started_onboarding = 0;
    
    foreach ($employees as $employee) {
        switch ($employee['status']) {
            case 'completed':
                $completed_onboarding++;
                break;
            case 'in_progress':
                $in_progress_onboarding++;
                break;
            case 'not_started':
                $not_started_onboarding++;
                break;
        }
    }
    
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $employees = [];
    $total_employees = 0;
    $completed_onboarding = 0;
    $in_progress_onboarding = 0;
    $not_started_onboarding = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Onboarding - Vector HR System</title>
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
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            border-left: 4px solid var(--primary-color);
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
        
        /* Dashboard Statistics Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-left: 4px solid;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card:nth-child(1) {
            border-left-color: var(--info-color);
        }
        
        .stat-card:nth-child(2) {
            border-left-color: var(--success-color);
        }
        
        .stat-card:nth-child(3) {
            border-left-color: var(--warning-color);
        }
        
        .stat-card:nth-child(4) {
            border-left-color: var(--danger-color);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            background: var(--kabel-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 1rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table th {
            background: var(--kabel-gradient);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
            position: relative;
        }
        
        .table th.sortable {
            cursor: pointer;
            user-select: none;
        }
        
        .table th.sortable:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .table th.sorted::after {
            
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }
        
        .table tbody tr:hover {
            background: rgba(255, 107, 53, 0.05);
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-not-started {
            background: rgba(108, 117, 125, 0.1);
            color: #495057;
            border: 1px solid rgba(108, 117, 125, 0.2);
        }
        
        .status-in-progress {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }
        
        .status-completed {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        /* Progress Bar */
        .progress {
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
        
        .btn-outline {
            background: transparent;
            color: var(--secondary-color);
            border: 2px solid var(--secondary-color);
        }
        
        .btn-outline:hover {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            margin: 0.5rem;
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
        
        .sort-indicator {
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
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
            
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem 1rem;
            }
            
            .stat-number {
                font-size: 2rem;
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
        
        .task-icons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .task-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: white;
            font-weight: bold;
        }
        
        .task-completed {
            background: var(--success-color);
        }
        
        .task-pending {
            background: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
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
            <h3>HairCare2U</h3>
            <p>HR Management System</p>
        </div>
        
        <ul class="sidebar-nav">
            <li>
                <a href="hr-dashboard.php">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="manage-applications.php">
                    <span class="icon">📋</span>
                    <span>Applications</span>
                </a>
            </li>
            <li>
                <a href="manage-jobs.php">
                    <span class="icon">💼</span>
                    <span>Job Positions</span>
                </a>
            </li>
            <li>
                <a href="onboarding.php" class="active">
                    <span class="icon">🚀</span>
                    <span>Onboarding</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <span class="icon">📈</span>
                    <span>Analytics</span>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <span class="icon">⚙️</span>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="avatar"><?php echo substr($_SESSION['full_name'], 0, 1); ?></div>
                <div style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
                <div style="color: rgba(255,255,255,0.7); font-size: 0.8rem; margin-bottom: 1rem;">
                    HR Manager
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
            <h1 class="page-title">🚀 Employee Onboarding Progress</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
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

            <!-- Dashboard Statistics -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_employees; ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $completed_onboarding; ?></div>
                    <div class="stat-label">Completed Onboarding</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $in_progress_onboarding; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $not_started_onboarding; ?></div>
                    <div class="stat-label">Not Started</div>
                </div>
            </div>

            <!-- Employee Onboarding Progress -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">👥</span>
                    <div>
                        <h3>Employee Onboarding Status</h3>
                        <p style="margin: 0; opacity: 0.9;">Monitor and track onboarding progress for all employees</p>
                        
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($employees)): ?>
                        <div class="empty-state">
                            <div class="icon">👥</div>
                            <h3>No employees found</h3>
                            <p>No employees are currently in the system.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Hire Date</th>
                                        <th class="sorted">Progress</th>
                                        <th>Status</th>
                                        <th>Task Overview</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
<tbody>
    <?php foreach ($employees as $employee): ?>
        <?php
        // Safely get department-specific tasks for accurate task icons
        try {
            $emp_dept = $employee['department'] ?? 'ALL';
            
            if ($has_department_column && $emp_dept != 'ALL') {
                $dept_tasks_query = $connection->prepare("
                    SELECT ot.id, ot.order_sequence, COALESCE(eo.status, 'pending') as status
                    FROM onboarding_tasks ot
                    LEFT JOIN employee_onboarding eo ON ot.id = eo.task_id AND eo.employee_id = ?
                    WHERE (ot.department = 'ALL' OR ot.department = ?) AND ot.is_mandatory = 1
                    ORDER BY ot.order_sequence
                ");
                $dept_tasks_query->bind_param("is", $employee['id'], $emp_dept);
            } else {
                $dept_tasks_query = $connection->prepare("
                    SELECT ot.id, ot.order_sequence, COALESCE(eo.status, 'pending') as status
                    FROM onboarding_tasks ot
                    LEFT JOIN employee_onboarding eo ON ot.id = eo.task_id AND eo.employee_id = ?
                    WHERE ot.is_mandatory = 1
                    ORDER BY ot.order_sequence
                ");
                $dept_tasks_query->bind_param("i", $employee['id']);
            }
            
            $dept_tasks_query->execute();
            $dept_result = $dept_tasks_query->get_result();
            $dept_tasks = $dept_result ? $dept_result->fetch_all(MYSQLI_ASSOC) : [];
            
        } catch (Exception $e) {
            $dept_tasks = [];
        }
        
        $total_dept_tasks = count($dept_tasks);
        $completed_dept_tasks = 0;
        foreach ($dept_tasks as $task) {
            if ($task['status'] == 'completed') {
                $completed_dept_tasks++;
            }
        }
        $dept_progress_percentage = $total_dept_tasks > 0 ? round(($completed_dept_tasks / $total_dept_tasks) * 100) : 0;
        ?>
        <tr>
            <td>
                <div>
                    <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($employee['full_name']); ?></strong><br>
                    <small style="color: #6c757d;"><?php echo htmlspecialchars($employee['email']); ?></small><br>
                    <small style="color: #6c757d; font-weight: 500;">
                        Dept: <?php echo htmlspecialchars($employee['department'] ?: 'Not Set'); ?>
                    </small>
                </div>
            </td>
            <td>
                <div>
                    <strong><?php echo date('M j, Y', strtotime($employee['hire_date'])); ?></strong><br>
                    <small style="color: #6c757d;"><?php echo date('g:i A', strtotime($employee['hire_date'])); ?></small>
                </div>
            </td>
            <td style="width: 200px;">
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $dept_progress_percentage; ?>%">
                        <?php echo $dept_progress_percentage; ?>%
                    </div>
                </div>
                <small style="color: #6c757d;">
                    <?php echo $completed_dept_tasks; ?> of <?php echo $total_dept_tasks; ?> tasks completed
                </small>
            </td>
            <td>
                <span class="status-badge status-<?php echo str_replace('_', '-', $employee['status']); ?>">
                    <?php echo ucwords(str_replace('_', ' ', $employee['status'])); ?>
                </span>
            </td>
            <td>
                <div class="task-icons">
                    <?php if (!empty($dept_tasks)): ?>
                        <?php foreach ($dept_tasks as $index => $task): ?>
                            <div class="task-icon <?php echo ($task['status'] == 'completed') ? 'task-completed' : 'task-pending'; ?>" 
                                 title="Task <?php echo $task['order_sequence']; ?> - <?php echo ucfirst($task['status']); ?>">
                                <?php echo ($task['status'] == 'completed') ? '✓' : $task['order_sequence']; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small style="color: #6c757d;">No tasks assigned</small>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <?php echo $employee['last_updated'] ? date('M j, Y', strtotime($employee['last_updated'])) : 'Never'; ?>
            </td>
            <td>
                <div style="min-width: 120px;">
                    <button onclick="viewDetails(<?php echo $employee['id']; ?>)" 
                            class="btn btn-primary btn-sm" 
                            style="width: 100%;">
                        👁️ View Details
                    </button>
                    <button onclick="sendReminder(<?php echo $employee['id']; ?>)" 
                            class="btn btn-primary btn-sm" 
                            style="width: 100%;">
                        📧 Send Reminder
                    </button>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        function viewDetails(employeeId) {
            if (confirm('View details of this employee?')) {
                // In a real implementation, this would make an AJAX call
                alert('View details functionality would be implemented here');
            }
        }

        function sendReminder(employeeId) {
            if (confirm('Send onboarding reminder to this employee?')) {
                // In a real implementation, this would make an AJAX call
                alert('Reminder functionality would be implemented here');
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>