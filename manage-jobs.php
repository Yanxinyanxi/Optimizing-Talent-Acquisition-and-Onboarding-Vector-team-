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

// Ensure HR access only
requireRole('hr');

// Handle form submissions
$success = '';
$error = '';

// Handle job creation - FIXED FOR YOUR DATABASE SCHEMA
if (isset($_POST['create_job'])) {
    $title = trim($_POST['title']);
    $department = trim($_POST['department']);
    $description = trim($_POST['description']);
    $required_skills = trim($_POST['required_skills']);
    $experience_level = trim($_POST['experience_level']);
    
    if (!empty($title) && !empty($department) && !empty($description) && !empty($required_skills)) {
        try {
            // FIXED: Only use columns that exist in your table
            $stmt = $connection->prepare("
                INSERT INTO job_positions (title, department, description, required_skills, experience_level, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sssssi", $title, $department, $description, $required_skills, $experience_level, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = "Job position created successfully!";
            } else {
                $error = "Failed to create job position: " . $connection->error;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Handle job update - FIXED FOR YOUR DATABASE SCHEMA
if (isset($_POST['update_job'])) {
    $job_id = (int)$_POST['job_id'];
    $title = trim($_POST['title']);
    $department = trim($_POST['department']);
    $description = trim($_POST['description']);
    $required_skills = trim($_POST['required_skills']);
    $experience_level = trim($_POST['experience_level']);
    $status = trim($_POST['status'] ?? 'active');
    
    if (!empty($title) && !empty($department) && !empty($description) && !empty($required_skills)) {
        try {
            // FIXED: Only use columns that exist in your table
            $stmt = $connection->prepare("
                UPDATE job_positions 
                SET title=?, department=?, description=?, required_skills=?, experience_level=?, status=?
                WHERE id=?
            ");
            $stmt->bind_param("ssssssi", $title, $department, $description, $required_skills, $experience_level, $status, $job_id);
            
            if ($stmt->execute()) {
                $success = "Job position updated successfully!";
            } else {
                $error = "Failed to update job position: " . $connection->error;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Handle job deletion
if (isset($_POST['delete_job'])) {
    $job_id = (int)$_POST['job_id'];
    
    try {
        // Check if job has applications first
        $check_stmt = $connection->prepare("SELECT COUNT(*) as app_count FROM applications WHERE job_position_id = ?");
        $check_stmt->bind_param("i", $job_id);
        $check_stmt->execute();
        $app_result = $check_stmt->get_result();
        $app_count = $app_result->fetch_assoc()['app_count'];
        
        if ($app_count > 0) {
            // Don't delete, just set status to closed
            $stmt = $connection->prepare("UPDATE job_positions SET status='closed' WHERE id=?");
            $stmt->bind_param("i", $job_id);
            if ($stmt->execute()) {
                $success = "Job position closed (has $app_count applications). Set to closed status instead of deleting.";
            }
        } else {
            // Safe to delete
            $stmt = $connection->prepare("DELETE FROM job_positions WHERE id=?");
            $stmt->bind_param("i", $job_id);
            if ($stmt->execute()) {
                $success = "Job position deleted successfully!";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$department_filter = isset($_GET['department']) ? $_GET['department'] : 'all';
$experience_filter = isset($_GET['experience']) ? $_GET['experience'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for jobs - FIXED FOR YOUR DATABASE SCHEMA
$query = "
    SELECT 
        jp.*,
        u.full_name as created_by_name,
        (SELECT COUNT(*) FROM applications WHERE job_position_id = jp.id) as application_count,
        (SELECT COUNT(*) FROM applications WHERE job_position_id = jp.id AND status = 'pending') as pending_count
    FROM job_positions jp
    LEFT JOIN users u ON jp.created_by = u.id
    WHERE 1=1
";

$params = [];
$types = '';

// Apply filters
if ($department_filter && $department_filter !== 'all') {
    $query .= " AND jp.department = ?";
    $params[] = $department_filter;
    $types .= 's';
}

if ($experience_filter && $experience_filter !== 'all') {
    $query .= " AND jp.experience_level = ?";
    $params[] = $experience_filter;
    $types .= 's';
}

if ($status_filter && $status_filter !== 'all') {
    $query .= " AND jp.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (jp.title LIKE ? OR jp.description LIKE ? OR jp.required_skills LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$query .= " ORDER BY jp.created_at DESC";

try {
    if ($params) {
        $stmt = $connection->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $jobs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } else {
            $jobs = [];
            $error = "Failed to prepare statement: " . $connection->error;
        }
    } else {
        $stmt = $connection->query($query);
        $jobs = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];
    }
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $jobs = [];
}

// Define departments and other options
$departments = [
    'Sales & Marketing',
    'Customer Service', 
    'Operations',
    'IT & Digital',
    'Management'
];

// FIXED: Only use experience levels that exist in your enum
$experience_levels = [
    'entry' => 'Entry Level',
    'mid' => 'Mid Level', 
    'senior' => 'Senior Level'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Management - Vector HR System</title>
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
        
        /* Main Content Styles */
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
        
        .content-area {
            padding: 2rem;
        }
        
        /* Card Styles */
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
            justify-content: space-between;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Form Styles */
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
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Button Styles */
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
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-xs {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 8px;
            font-weight: 500;
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
            min-width: 180px;
        }
        
        /* Job Cards */
        .job-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .job-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
            position: relative;
        }
        
        .job-body {
            padding: 2rem;
        }
        
        .job-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .job-department {
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .job-meta {
            display: flex;
            gap: 1rem;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .job-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .job-skills {
            margin-bottom: 1.5rem;
        }
        
        .skill-tag {
            display: inline-block;
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }
        
        .job-stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary-color);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .job-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .status-closed {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            background: var(--kabel-gradient);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            color: white;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s ease;
        }
        
        .close:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1rem 2rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        /* Form Grid */
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 16px;
            border: 2px dashed #e9ecef;
        }
        
        .empty-state .icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.4;
        }
        
        .empty-state h3 {
            color: var(--secondary-color);
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: #6c757d;
            font-size: 1rem;
            line-height: 1.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-form .form-group {
                min-width: auto;
            }
            
            .job-stats {
                justify-content: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
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
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo">H</div>
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
                <a href="manage-jobs.php" class="active">
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
            <h1 class="page-title">💼 Job Position Management</h1>
            <button onclick="openCreateModal()" class="btn btn-primary">
                ➕ Add New Job
            </button>
        </div>

        <div class="content-area">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span>⚠️</span>
                    <div><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div><strong>Success:</strong> <?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <div class="header-left">
                        <span class="icon" style="font-size: 1.5rem;">🔍</span>
                        <h3 style="margin: 0; font-size: 1.25rem;">Filter Jobs</h3>
                    </div>
                    <div class="header-right">
                        <span style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                            <?php echo count($jobs); ?> positions
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search by title, description, or skills..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-control form-select">
                                <option value="all">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>" 
                                            <?php echo ($department_filter == $dept) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Experience Level</label>
                            <select name="experience" class="form-control form-select">
                                <option value="all">All Levels</option>
                                <?php foreach ($experience_levels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo ($experience_filter == $key) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control form-select">
                                <option value="all">All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="closed" <?php echo ($status_filter == 'closed') ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="manage-jobs.php" class="btn btn-outline">Clear</a>
                    </form>
                </div>
            </div>

            <!-- Job Listings -->
            <div class="card">
                <div class="card-header">
                    <div class="header-left">
                        <span class="icon" style="font-size: 1.5rem;">💼</span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.25rem;">Job Positions</h3>
                            <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">Manage all HairCare2U career opportunities</p>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($jobs)): ?>
                        <div class="empty-state">
                            <div class="icon">💼</div>
                            <h3>No job positions found</h3>
                            <p>No job positions match your current filters. Try adjusting your search criteria or create a new job position.</p>
                            <button onclick="openCreateModal()" class="btn btn-primary" style="margin-top: 1rem;">
                                ➕ Create First Job Position
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($jobs as $job): ?>
                            <div class="job-card">
                                <div class="job-header">
                                    <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div class="job-department">🏢 <?php echo htmlspecialchars($job['department']); ?></div>
                                    <div class="job-meta">
                                        <span>📈 <?php echo ucfirst($job['experience_level']); ?> Level</span>
                                        <span>📅 <?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                                    </div>
                                    <span class="status-badge status-<?php echo $job['status'] ?? 'active'; ?>">
                                        <?php echo ucfirst($job['status'] ?? 'active'); ?>
                                    </span>
                                </div>
                                <div class="job-body">
                                    <div class="job-description">
                                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                    </div>
                                    
                                    <div class="job-skills">
                                        <strong>🎯 Required Skills:</strong><br>
                                        <?php 
                                        $skills = explode(',', $job['required_skills']);
                                        foreach ($skills as $skill): 
                                        ?>
                                            <span class="skill-tag"><?php echo trim(htmlspecialchars($skill)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="job-stats">
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $job['application_count']; ?></div>
                                            <div class="stat-label">Applications</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $job['pending_count']; ?></div>
                                            <div class="stat-label">Pending Review</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number">
                                                <?php 
                                                $days_posted = floor((time() - strtotime($job['created_at'])) / 86400);
                                                echo $days_posted;
                                                ?>
                                            </div>
                                            <div class="stat-label">Days Posted</div>
                                        </div>
                                    </div>
                                    
                                    <div class="job-actions">
                                        <?php if ($job['application_count'] > 0): ?>
                                            <a href="manage-applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-outline btn-sm">
                                                👥 View Applications
                                            </a>
                                        <?php endif; ?>
                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($job)); ?>)" class="btn btn-primary btn-sm">
                                            ✏️ Edit
                                        </button>
                                        <button onclick="confirmDelete(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['title']); ?>', <?php echo $job['application_count']; ?>)" class="btn btn-danger btn-sm">
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Job Modal -->
    <div id="jobModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Create New Job Position</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="jobForm">
                <div class="modal-body">
                    <input type="hidden" name="job_id" id="jobId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Job Title *</label>
                            <input type="text" name="title" id="jobTitle" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department *</label>
                            <select name="department" id="jobDepartment" class="form-control form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Job Description *</label>
                        <textarea name="description" id="jobDescription" class="form-control" rows="4" required 
                                  placeholder="Describe the role, responsibilities, and what makes this position great..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Required Skills *</label>
                        <input type="text" name="required_skills" id="jobSkills" class="form-control" required
                               placeholder="e.g., PHP,JavaScript,SQL,HTML,CSS (comma separated)">
                        <small style="color: #6c757d; font-size: 0.85rem;">Separate skills with commas</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Experience Level *</label>
                            <select name="experience_level" id="jobExperience" class="form-control form-select" required>
                                <option value="">Select Level</option>
                                <?php foreach ($experience_levels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="statusGroup" style="display: none;">
                            <label class="form-label">Status</label>
                            <select name="status" id="jobStatus" class="form-control form-select">
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="create_job" id="submitBtn" class="btn btn-primary">Create Job</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 1rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                    <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">Delete Job Position?</h4>
                    <p id="deleteMessage" style="color: #6c757d; line-height: 1.5;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="job_id" id="deleteJobId">
                    <button type="submit" name="delete_job" class="btn btn-danger">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Job Position';
            document.getElementById('jobForm').reset();
            document.getElementById('jobId').value = '';
            document.getElementById('statusGroup').style.display = 'none';
            document.getElementById('submitBtn').name = 'create_job';
            document.getElementById('submitBtn').textContent = 'Create Job';
            document.getElementById('jobModal').classList.add('show');
        }

        function openEditModal(job) {
            document.getElementById('modalTitle').textContent = 'Edit Job Position';
            document.getElementById('jobId').value = job.id;
            document.getElementById('jobTitle').value = job.title;
            document.getElementById('jobDepartment').value = job.department;
            document.getElementById('jobDescription').value = job.description;
            document.getElementById('jobSkills').value = job.required_skills;
            document.getElementById('jobExperience').value = job.experience_level;
            document.getElementById('jobStatus').value = job.status || 'active';
            document.getElementById('statusGroup').style.display = 'flex';
            document.getElementById('submitBtn').name = 'update_job';
            document.getElementById('submitBtn').textContent = 'Update Job';
            document.getElementById('jobModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('jobModal').classList.remove('show');
        }

        function confirmDelete(jobId, jobTitle, appCount) {
            document.getElementById('deleteJobId').value = jobId;
            const message = appCount > 0 
                ? `Are you sure you want to delete "${jobTitle}"?\n\nThis job has ${appCount} application(s). The job will be closed instead of deleted to preserve application data.`
                : `Are you sure you want to delete "${jobTitle}"?\n\nThis action cannot be undone.`;
            document.getElementById('deleteMessage').textContent = message;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const jobModal = document.getElementById('jobModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == jobModal) {
                closeModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // Auto-hide alerts
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