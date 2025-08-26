<?php
// require_once 'includes/auth.php';
// require_once 'includes/functions.php';

session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/extracta_api.php';
require_once 'includes/functions.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$query = "
    SELECT 
        a.id,
        u.full_name as candidate_name,
        u.email as candidate_email,
        a.match_percentage,
        a.applied_at,
        a.status,
        a.hr_notes,
        a.resume_filename,    -- Make sure this is included
        a.resume_path,        -- Make sure this is included  
        j.title as job_title,
        j.department
    FROM applications a
    JOIN job_positions j ON a.job_position_id = j.id
    JOIN users u ON a.candidate_id = u.id
    WHERE 1=1
";

// Check database connection
if (!isset($connection) || $connection->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

// Ensure HR access only
requireRole('hr');

// Get dashboard statistics
try {
    // Total applications
    $stmt = $connection->query("SELECT COUNT(*) as total FROM applications");
    $total_applications = $stmt->fetch_assoc()['total'];
    
    // Pending applications
    $stmt = $connection->query("SELECT COUNT(*) as pending FROM applications WHERE status = 'pending'");
    $pending_applications = $stmt->fetch_assoc()['pending'];
    
    // Hired candidates
    $stmt = $connection->query("SELECT COUNT(*) as hired FROM applications WHERE status = 'hired'");
    $hired_candidates = $stmt->fetch_assoc()['hired'];
    
    // Active employees
    $stmt = $connection->query("SELECT COUNT(*) as employees FROM users WHERE role = 'employee' AND status = 'active'");
    $active_employees = $stmt->fetch_assoc()['employees'];
    
    // Get all applications with details
    $filter_job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
    $applications = getApplicationsForHR($filter_job_id);
    
    // Get job positions for filtering
    $job_positions = getJobPositions();
    
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle status updates
if ($_POST && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'];
    $new_status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    if (updateApplicationStatus($application_id, $new_status, $notes)) {
        $success = "Application status updated successfully!";
        // Refresh applications data
        $applications = getApplicationsForHR($filter_job_id);
    } else {
        $error = "Failed to update application status.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - Vector HR System</title>
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
            border-left-color: var(--warning-color);
        }
        
        .stat-card:nth-child(3) {
            border-left-color: var(--success-color);
        }
        
        .stat-card:nth-child(4) {
            border-left-color: var(--primary-color);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
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
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }
        
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: #212529;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
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
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }
        
        .status-selected {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .status-waiting-interview {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(255, 107, 53, 0.2);
        }
        
        .status-interview-completed {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border: 1px solid rgba(23, 162, 184, 0.2);
        }
        
        .status-offer-sent {
            background: rgba(102, 16, 242, 0.1);
            color: #4c1d95;
            border: 1px solid rgba(102, 16, 242, 0.2);
        }
        
        .status-offer-accepted {
            background: rgba(34, 197, 94, 0.1);
            color: #166534;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .status-offer-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-hired {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-completed {
            background: rgba(34, 197, 94, 0.1);
            color: #166534;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .status-in-progress {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        /* Match Percentage */
        .match-percentage {
            font-weight: 600;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
        }
        
        .match-high {
            background: rgba(34, 197, 94, 0.1);
            color: #166534;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .match-medium {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .match-low {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
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
        
        /* Quick Actions Grid */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .quick-action-btn {
            padding: 2rem;
            text-align: center;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            display: block;
            position: relative;
            overflow: hidden;
        }
        
        .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .quick-action-btn:hover::before {
            left: 100%;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        /* Filter Form */
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-form .form-group {
            flex: 1;
            margin-bottom: 0;
            min-width: 200px;
        }
        
        /* Empty State */
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
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-form .form-group {
                min-width: auto;
            }
            
            .table-responsive {
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
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
            <h3>HairCare2U</h3>
            <p>HR Management System</p>
        </div>
        
        <ul class="sidebar-nav">
            <li>
                <a href="hr-dashboard.php" class="active">
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
                <a href="onboarding.php">
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
            <h1 class="page-title">📊 HR Dashboard - Talent Acquisition Control Center</h1>
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

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div>
                        <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dashboard Statistics -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_applications; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pending_applications; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $hired_candidates; ?></div>
                    <div class="stat-label">Successful Hires</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_employees; ?></div>
                    <div class="stat-label">Active Employees</div>
                </div>
            </div>

            <!-- Job Position Filter -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🎯</span>
                    <div>
                        <h3>Filter Applications by Job Position</h3>
                        <p style="margin: 0; opacity: 0.9;">Narrow down applications by specific roles</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="job_filter" class="form-label">Job Position</label>
                            <select name="job_id" id="job_filter" class="form-control form-select">
                                <option value="">All Positions</option>
                                <?php foreach ($job_positions as $job): ?>
                                    <option value="<?php echo $job['id']; ?>" 
                                            <?php echo (isset($_GET['job_id']) && $_GET['job_id'] == $job['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filter Applications</button>
                        <a href="hr-dashboard.php" class="btn btn-outline">Clear Filter</a>
                    </form>
                </div>
            </div>

            <!-- Applications Management -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">📋</span>
                    <div>
                        <h3>Candidate Applications Management</h3>
                        <p style="margin: 0; opacity: 0.9;">Review and manage candidate applications</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($applications)): ?>
                        <div class="empty-state">
                            <div class="icon">📭</div>
                            <h3>No applications found</h3>
                            <p>There are currently no applications to review.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Job Position</th>
                                        <th>Match Score</th>
                                        <th>Applied Date</th>
                                        <th>Current Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($app['candidate_name']); ?></strong><br>
                                                    <small style="color: #6c757d;"><?php echo htmlspecialchars($app['candidate_email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                                    <small style="color: #6c757d;"><?php echo htmlspecialchars($app['department']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $match_class = '';
                                                if ($app['match_percentage'] >= 80) $match_class = 'match-high';
                                                elseif ($app['match_percentage'] >= 60) $match_class = 'match-medium';
                                                else $match_class = 'match-low';
                                                ?>
                                                <span class="match-percentage <?php echo $match_class; ?>">
                                                    <?php echo number_format($app['match_percentage'], 1); ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo date('M j, Y', strtotime($app['applied_at'])); ?></strong><br>
                                                    <small style="color: #6c757d;"><?php echo date('g:i A', strtotime($app['applied_at'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo str_replace('_', '-', $app['status']); ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="min-width: 180px;">
                                                    <form method="POST" style="margin-bottom: 0.75rem;">
                                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                        <select name="status" class="form-control form-select" style="margin-bottom: 0.5rem; font-size: 0.875rem;" onchange="this.form.submit()">
                                                            <option value="pending" <?php echo ($app['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="selected" <?php echo ($app['status'] == 'selected') ? 'selected' : ''; ?>>Selected</option>
                                                            <option value="rejected" <?php echo ($app['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                            <option value="waiting_interview" <?php echo ($app['status'] == 'waiting_interview') ? 'selected' : ''; ?>>Waiting Interview</option>
                                                            <option value="interview_completed" <?php echo ($app['status'] == 'interview_completed') ? 'selected' : ''; ?>>Interview Completed</option>
                                                            <option value="offer_sent" <?php echo ($app['status'] == 'offer_sent') ? 'selected' : ''; ?>>Offer Sent</option>
                                                            <option value="offer_accepted" <?php echo ($app['status'] == 'offer_accepted') ? 'selected' : ''; ?>>Offer Accepted</option>
                                                            <option value="offer_rejected" <?php echo ($app['status'] == 'offer_rejected') ? 'selected' : ''; ?>>Offer Rejected</option>
                                                            <option value="hired" <?php echo ($app['status'] == 'hired') ? 'selected' : ''; ?>>Hired</option>
                                                        </select>
                                                        <input type="hidden" name="update_status" value="1">
                                                    </form>
                                                    
<?php if (!empty($app['resume_filename'])): ?>
    <?php 
    // Try both possible paths
    $resumePath1 = 'uploads/resumes/' . $app['resume_filename']; // Original filename path
    $resumePath2 = !empty($app['resume_path']) ? $app['resume_path'] : null; // Stored path
    
    // Check which file actually exists
    $workingPath = null;
    if (!empty($resumePath2) && file_exists($resumePath2)) {
        $workingPath = $resumePath2;
    } elseif (file_exists($resumePath1)) {
        $workingPath = $resumePath1;
    }
    
    // Also try looking for the renamed file in the uploads directory
    if (!$workingPath) {
        $uploadDir = 'uploads/resumes/';
        if (is_dir($uploadDir)) {
            $files = scandir($uploadDir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && is_file($uploadDir . $file)) {
                    // Check if this might be our file (you can add more logic here)
                    $workingPath = $uploadDir . $file;
                    break; // For now, just take the first file found
                }
            }
        }
    }
    ?>
    
    <?php if ($workingPath): ?>
    <a href="<?php echo htmlspecialchars($workingPath); ?>"
       target="_blank" 
       class="btn btn-outline" 
       style="font-size: 0.8rem; padding: 0.5rem 0.75rem; width: 100%; margin-bottom: 0.5rem;">
        📄 View Resume
    </a>
    <?php else: ?>
    <span class="btn btn-outline" 
          style="font-size: 0.8rem; padding: 0.5rem 0.75rem; width: 100%; margin-bottom: 0.5rem; opacity: 0.5; cursor: not-allowed;">
        📄 Resume Not Found
    </span>
    <?php endif; ?>
<?php endif; ?>
                                                    
                                                    <?php if (!empty($app['hr_notes'])): ?>
                                                        <div style="margin-top: 0.75rem; padding: 0.5rem; background: rgba(43, 76, 140, 0.05); border-radius: 8px; border-left: 3px solid var(--secondary-color);">
                                                            <small style="color: #6c757d;">
                                                                <strong>Notes:</strong> <?php echo htmlspecialchars($app['hr_notes']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
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

            <!-- Employee Onboarding Progress -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🚀</span>
                    <div>
                        <h3>Employee Onboarding Progress</h3>
                        <p style="margin: 0; opacity: 0.9;">Track new employee onboarding status</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    // Get all employees and their onboarding progress
                    try {
                        $stmt = $connection->query("
                            SELECT u.id, u.full_name, u.email,
                                   COUNT(eo.id) as total_tasks,
                                   SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                            FROM users u
                            LEFT JOIN employee_onboarding eo ON u.id = eo.employee_id
                            WHERE u.role = 'employee' AND u.status = 'active'
                            GROUP BY u.id, u.full_name, u.email
                            ORDER BY u.full_name
                        ");
                        $employees = $stmt->fetch_all(MYSQLI_ASSOC);
                    } catch(Exception $e) {
                        $employees = [];
                    }
                    ?>

                    <?php if (empty($employees)): ?>
                        <div class="empty-state">
                            <div class="icon">👥</div>
                            <h3>No employees found</h3>
                            <p>No employees are currently in the onboarding process.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Email</th>
                                        <th>Onboarding Progress</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $employee): ?>
                                        <?php
                                        $total = max(1, $employee['total_tasks']); // Avoid division by zero
                                        $completed = $employee['completed_tasks'];
                                        $progress = round(($completed / $total) * 100);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($employee['full_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($employee['email']); ?>
                                            </td>
                                            <td style="width: 300px;">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%">
                                                        <?php echo $progress; ?>%
                                                    </div>
                                                </div>
                                                <small style="color: #6c757d;">
                                                    <?php echo $completed; ?> of <?php echo $total; ?> tasks completed
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($progress == 100): ?>
                                                    <span class="status-badge status-completed">Completed</span>
                                                <?php elseif ($progress > 0): ?>
                                                    <span class="status-badge status-in-progress">In Progress</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-pending">Not Started</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">⚡</span>
                    <div>
                        <h3>Quick Actions</h3>
                        <p style="margin: 0; opacity: 0.9;">Access commonly used HR functions</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="quick-actions-grid">
                        <button onclick="exportApplications()" class="quick-action-btn btn-secondary">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                            <div style="font-size: 1.1rem; margin-bottom: 0.25rem;">Export Data</div>
                            <small style="opacity: 0.8;">Download applications</small>
                        </button>
                        <button onclick="generateReport()" class="quick-action-btn btn-warning">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📈</div>
                            <div style="font-size: 1.1rem; margin-bottom: 0.25rem;">Generate Report</div>
                            <small style="opacity: 0.8;">Hiring analytics</small>
                        </button>
                        <button onclick="manageJobs()" class="quick-action-btn btn-success">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">💼</div>
                            <div style="font-size: 1.1rem; margin-bottom: 0.25rem;">Manage Jobs</div>
                            <small style="opacity: 0.8;">Add/edit positions</small>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        function exportApplications() {
    // Create CSV export of applications data
    const applications = <?php echo json_encode($applications); ?>;
    
    if (!applications || applications.length === 0) {
        alert('No application data available to export.');
        return;
    }
    
    // CSV headers
    const headers = ['Candidate Name', 'Email', 'Job Title', 'Department', 'Match Percentage', 'Applied Date', 'Status', 'HR Notes'];
    
    // Build CSV content
    let csvContent = headers.join(',') + '\n';
    
    applications.forEach(app => {
        const row = [
            `"${app.candidate_name || ''}"`,
            `"${app.candidate_email || ''}"`,
            `"${app.job_title || ''}"`,
            `"${app.department || ''}"`,
            `"${app.match_percentage || '0'}%"`,
            `"${new Date(app.applied_at).toLocaleDateString()}"`,
            `"${app.status ? app.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : ''}"`,
            `"${(app.hr_notes || '').replace(/"/g, '""')}"`
        ];
        csvContent += row.join(',') + '\n';
    });
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `applications_export_${new Date().toISOString().slice(0, 10)}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function generateReport() {
    // Generate comprehensive HTML report
    const currentDate = new Date().toLocaleDateString();
    const applications = <?php echo json_encode($applications); ?>;
    const totalApps = <?php echo $total_applications; ?>;
    const pendingApps = <?php echo $pending_applications; ?>;
    const hiredCandidates = <?php echo $hired_candidates; ?>;
    const activeEmployees = <?php echo $active_employees; ?>;
    
    // Calculate statistics
    const statusCounts = {};
    const departmentCounts = {};
    let totalMatch = 0;
    let matchCount = 0;
    
    applications.forEach(app => {
        // Count by status
        const status = app.status || 'unknown';
        statusCounts[status] = (statusCounts[status] || 0) + 1;
        
        // Count by department
        const dept = app.department || 'unknown';
        departmentCounts[dept] = (departmentCounts[dept] || 0) + 1;
        
        // Calculate average match
        if (app.match_percentage && app.match_percentage > 0) {
            totalMatch += parseFloat(app.match_percentage);
            matchCount++;
        }
    });
    
    const avgMatch = matchCount > 0 ? (totalMatch / matchCount).toFixed(1) : 0;
    const hireRate = totalApps > 0 ? ((hiredCandidates / totalApps) * 100).toFixed(1) : 0;
    
    const reportHTML = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HairCare2U HR Analytics Report - ${currentDate}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #FF6B35;
        }
        .header h1 {
            color: #2B4C8C;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        .header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .metric-card {
            background: linear-gradient(135deg, #FF6B35, #2B4C8C);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .metric-number {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .metric-label {
            font-size: 1rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section {
            margin-bottom: 40px;
        }
        .section h2 {
            color: #2B4C8C;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #FF6B35;
            font-size: 1.8rem;
        }
        .chart-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        th {
            background: linear-gradient(135deg, #2B4C8C, #1e3a75);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f3f4;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        tr:hover {
            background: rgba(255, 107, 53, 0.1);
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: rgba(255, 193, 7, 0.2); color: #856404; }
        .status-selected { background: rgba(40, 167, 69, 0.2); color: #155724; }
        .status-rejected { background: rgba(220, 53, 69, 0.2); color: #721c24; }
        .status-hired { background: rgba(102, 16, 242, 0.2); color: #5a1a6b; }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
        }
        @media print {
            body { background: white; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 HairCare2U HR Analytics Report</h1>
            <p>Comprehensive Hiring and Employee Management Analysis</p>
            <p><strong>Report Generated:</strong> ${currentDate}</p>
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-number">${totalApps}</div>
                <div class="metric-label">Total Applications</div>
            </div>
            <div class="metric-card">
                <div class="metric-number">${pendingApps}</div>
                <div class="metric-label">Pending Review</div>
            </div>
            <div class="metric-card">
                <div class="metric-number">${hiredCandidates}</div>
                <div class="metric-label">Successful Hires</div>
            </div>
            <div class="metric-card">
                <div class="metric-number">${activeEmployees}</div>
                <div class="metric-label">Active Employees</div>
            </div>
        </div>

        <div class="section">
            <h2>📈 Key Performance Indicators</h2>
            <div class="chart-container">
                <p><strong>Average Match Score:</strong> ${avgMatch}%</p>
                <p><strong>Overall Hire Rate:</strong> ${hireRate}%</p>
                <p><strong>Applications This Month:</strong> ${applications.filter(app => new Date(app.applied_at).getMonth() === new Date().getMonth()).length}</p>
            </div>
        </div>

        <div class="section">
            <h2>📊 Application Status Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    ${Object.entries(statusCounts).map(([status, count]) => `
                        <tr>
                            <td><span class="status-badge status-${status}">${status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span></td>
                            <td>${count}</td>
                            <td>${totalApps > 0 ? ((count / totalApps) * 100).toFixed(1) : 0}%</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>🏢 Department Analysis</h2>
            <table>
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Applications</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    ${Object.entries(departmentCounts).map(([dept, count]) => `
                        <tr>
                            <td>${dept}</td>
                            <td>${count}</td>
                            <td>${totalApps > 0 ? ((count / totalApps) * 100).toFixed(1) : 0}%</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>🎯 Recent Applications</h2>
            <table>
                <thead>
                    <tr>
                        <th>Candidate</th>
                        <th>Position</th>
                        <th>Match Score</th>
                        <th>Applied Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${applications.slice(0, 10).map(app => `
                        <tr>
                            <td>${app.candidate_name || 'N/A'}</td>
                            <td>${app.job_title || 'N/A'}</td>
                            <td>${app.match_percentage || 0}%</td>
                            <td>${new Date(app.applied_at).toLocaleDateString()}</td>
                            <td><span class="status-badge status-${app.status || 'unknown'}">${(app.status || 'unknown').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>© ${new Date().getFullYear()} HairCare2U HR Management System</p>
            <p>This report was automatically generated by the Vector HR System</p>
        </div>
    </div>
</body>
</html>`;

    // Create and download the report
    const blob = new Blob([reportHTML], { type: 'text/html;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `HR_Analytics_Report_${new Date().toISOString().slice(0, 10)}.html`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function manageJobs() {
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Loading...';
    btn.disabled = true;
    
    // Redirect to job management page
    window.location.href = 'manage-jobs.php';
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
        
        // Add smooth scrolling for page navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add loading states to form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '⏳ Processing...';
                    submitBtn.disabled = true;
                    
                    // Re-enable after a delay (in case of errors)
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 5000);
                }
            });
        });
    </script>
</body>
</html>