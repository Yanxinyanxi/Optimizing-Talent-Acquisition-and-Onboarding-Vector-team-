<?php
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

// Ensure HR access only
requireRole('hr');

// Check database connection
if (!isset($connection) || $connection->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

// Get filter parameters
$filter_job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
$match_filter = isset($_GET['match_filter']) ? $_GET['match_filter'] : 'all';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'match_desc';

// Get job positions for filtering
try {
    $stmt = $connection->query("SELECT id, title, department FROM job_positions ORDER BY title");
    if ($stmt) {
        $job_positions = $stmt->fetch_all(MYSQLI_ASSOC);
    } else {
        $job_positions = [];
    }
} catch(Exception $e) {
    $job_positions = [];
}

// Build query for applications with parsed data
try {
    $query = "
        SELECT a.id, a.candidate_name, a.candidate_email, a.resume_filename, 
               a.match_percentage, a.applied_at, a.status, a.parsed_data,
               j.title as job_title, j.department, j.required_skills, j.experience_level
        FROM applications a
        JOIN job_positions j ON a.job_id = j.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filter by job position
    if ($filter_job_id) {
        $query .= " AND a.job_id = ?";
        $params[] = $filter_job_id;
    }
    
    // Filter by match percentage
    switch ($match_filter) {
        case 'excellent':
            $query .= " AND a.match_percentage >= 90";
            break;
        case 'good':
            $query .= " AND a.match_percentage >= 70 AND a.match_percentage < 90";
            break;
        case 'fair':
            $query .= " AND a.match_percentage >= 50 AND a.match_percentage < 70";
            break;
        case 'poor':
            $query .= " AND a.match_percentage < 50";
            break;
    }
    
    // Sort applications
    switch ($sort_by) {
        case 'match_desc':
            $query .= " ORDER BY a.match_percentage DESC";
            break;
        case 'match_asc':
            $query .= " ORDER BY a.match_percentage ASC";
            break;
        case 'date_desc':
            $query .= " ORDER BY a.applied_at DESC";
            break;
        case 'date_asc':
            $query .= " ORDER BY a.applied_at ASC";
            break;
        case 'name':
            $query .= " ORDER BY a.candidate_name ASC";
            break;
    }
    
    if ($params) {
        $stmt = $connection->prepare($query);
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($params)), ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $applications = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                $applications = [];
            }
        } else {
            $applications = [];
        }
    } else {
        $stmt = $connection->query($query);
        if ($stmt) {
            $applications = $stmt->fetch_all(MYSQLI_ASSOC);
        } else {
            $applications = [];
        }
    }
    
} catch(Exception $e) {
    $applications = [];
    $error = "Database error: " . $e->getMessage();
}

// Function to safely decode JSON
function safeJsonDecode($json_string) {
    if (empty($json_string)) return [];
    $decoded = json_decode($json_string, true);
    return is_array($decoded) ? $decoded : [];
}

// Function to get skill match details
function getSkillMatchDetails($candidate_skills, $required_skills) {
    $required = array_map('trim', explode(',', strtolower($required_skills)));
    $candidate = array_map('strtolower', $candidate_skills);
    
    $matched = array_intersect($candidate, $required);
    $missing = array_diff($required, $candidate);
    
    return [
        'matched' => array_values($matched),
        'missing' => array_values($missing),
        'additional' => array_values(array_diff($candidate, $required))
    ];
}

// Handle status updates
if ($_POST && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'];
    $new_status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    if (updateApplicationStatus($application_id, $new_status, $notes)) {
        $success = "Application status updated successfully!";
        // Refresh page to show updated data
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
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
    <title>Smart Matching - Vector HR System</title>
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
        
        /* Filter Form */
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 0;
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
        
        /* Candidate Grid */
        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }
        
        .candidate-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            border-left: 4px solid;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .candidate-card.match-excellent {
            border-left-color: #28a745;
        }
        
        .candidate-card.match-good {
            border-left-color: #17a2b8;
        }
        
        .candidate-card.match-fair {
            border-left-color: #ffc107;
        }
        
        .candidate-card.match-poor {
            border-left-color: #dc3545;
        }
        
        .candidate-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(255,107,53,0.1) 0%, rgba(43,76,140,0.1) 100%);
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .candidate-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .candidate-details h3 {
            color: var(--secondary-color);
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        
        .candidate-details p {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .match-score {
            text-align: center;
            padding: 0.75rem;
            border-radius: 12px;
            min-width: 80px;
        }
        
        .match-score.excellent {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 2px solid rgba(40, 167, 69, 0.2);
        }
        
        .match-score.good {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border: 2px solid rgba(23, 162, 184, 0.2);
        }
        
        .match-score.fair {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 2px solid rgba(255, 193, 7, 0.2);
        }
        
        .match-score.poor {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 2px solid rgba(220, 53, 69, 0.2);
        }
        
        .match-percentage {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .match-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            opacity: 0.8;
        }
        
        .job-info {
            background: rgba(43,76,140,0.05);
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .candidate-body {
            padding: 1.5rem;
        }
        
        .section {
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .skill-tag {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .skill-matched {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .skill-missing {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .skill-additional {
            background: rgba(108, 117, 125, 0.1);
            color: #495057;
            border: 1px solid rgba(108, 117, 125, 0.2);
        }
        
        .experience-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .experience-fill {
            background: var(--kabel-gradient);
            height: 100%;
            border-radius: 10px;
            transition: width 0.6s ease;
        }
        
        .contact-info {
            background: rgba(255,107,53,0.05);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .contact-info p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: #212529;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        /* Status Badge */
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
        
        .status-hired {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: var(--kabel-gradient);
            color: white;
            padding: 1.5rem;
            border-radius: 16px 16px 0 0;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .close {
            color: white;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            opacity: 0.8;
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
            
            .candidates-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .topbar {
                padding-left: 4rem;
            }
        }
        
        .mobile-toggle {
            display: none;
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
            <p>Talent Control Center</p>
        </div>
        
        <ul class="sidebar-nav">
            <li>
                <a href="hr-dashboard.php">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="smart-matching.php" class="active">
                    <span class="icon">🎯</span>
                    <span>Smart Matching</span>
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
            <h1 class="page-title">🎯 Smart Matching - AI-Powered Candidate Analysis</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    <?php echo count($applications); ?> candidates analyzed
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

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🔍</span>
                    <div>
                        <h3>Smart Filters & Search</h3>
                        <p style="margin: 0; opacity: 0.9;">Find the perfect candidates using AI-powered matching</p>
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
                                            <?php echo ($filter_job_id == $job['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="match_filter" class="form-label">Match Quality</label>
                            <select name="match_filter" id="match_filter" class="form-control form-select">
                                <option value="all" <?php echo ($match_filter == 'all') ? 'selected' : ''; ?>>All Matches</option>
                                <option value="excellent" <?php echo ($match_filter == 'excellent') ? 'selected' : ''; ?>>Excellent (90-100%)</option>
                                <option value="good" <?php echo ($match_filter == 'good') ? 'selected' : ''; ?>>Good (70-89%)</option>
                                <option value="fair" <?php echo ($match_filter == 'fair') ? 'selected' : ''; ?>>Fair (50-69%)</option>
                                <option value="poor" <?php echo ($match_filter == 'poor') ? 'selected' : ''; ?>>Poor (Below 50%)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sort_by" class="form-label">Sort By</label>
                            <select name="sort_by" id="sort_by" class="form-control form-select">
                                <option value="match_desc" <?php echo ($sort_by == 'match_desc') ? 'selected' : ''; ?>>Match % (High to Low)</option>
                                <option value="match_asc" <?php echo ($sort_by == 'match_asc') ? 'selected' : ''; ?>>Match % (Low to High)</option>
                                <option value="date_desc" <?php echo ($sort_by == 'date_desc') ? 'selected' : ''; ?>>Application Date (Newest)</option>
                                <option value="date_asc" <?php echo ($sort_by == 'date_asc') ? 'selected' : ''; ?>>Application Date (Oldest)</option>
                                <option value="name" <?php echo ($sort_by == 'name') ? 'selected' : ''; ?>>Candidate Name</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">🔍 Apply Filters</button>
                        </div>
                        <div class="form-group">
                            <a href="smart-matching.php" class="btn btn-outline" style="width: 100%;">Clear All</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Candidates Grid -->
            <?php if (empty($applications)): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="icon">🎯</div>
                            <h3>No candidates found</h3>
                            <p>Try adjusting your filters or check back later for new applications.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="candidates-grid">
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $parsed_data = safeJsonDecode($app['parsed_data']);
                        $match_class = '';
                        $match_category = '';
                        
                        if ($app['match_percentage'] >= 90) {
                            $match_class = 'match-excellent';
                            $match_category = 'excellent';
                        } elseif ($app['match_percentage'] >= 70) {
                            $match_class = 'match-good';
                            $match_category = 'good';
                        } elseif ($app['match_percentage'] >= 50) {
                            $match_class = 'match-fair';
                            $match_category = 'fair';
                        } else {
                            $match_class = 'match-poor';
                            $match_category = 'poor';
                        }
                        
                        $candidate_skills = isset($parsed_data['skills']) ? $parsed_data['skills'] : [];
                        $skill_match = getSkillMatchDetails($candidate_skills, $app['required_skills']);
                        $experience_years = isset($parsed_data['total_experience']) ? intval($parsed_data['total_experience']) : 0;
                        ?>
                        <div class="candidate-card <?php echo $match_class; ?>">
                            <div class="candidate-header">
                                <div class="candidate-info">
                                    <div class="candidate-details">
                                        <h3><?php echo htmlspecialchars($app['candidate_name']); ?></h3>
                                        <p>📧 <?php echo htmlspecialchars($app['candidate_email']); ?></p>
                                        <p>🗓️ Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?></p>
                                        <p>📊 Status: 
                                            <span class="status-badge status-<?php echo str_replace('_', '-', $app['status']); ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="match-score <?php echo $match_category; ?>">
                                        <div class="match-percentage"><?php echo number_format($app['match_percentage'], 1); ?>%</div>
                                        <div class="match-label">Match</div>
                                    </div>
                                </div>
                                <div class="job-info">
                                    <strong>📋 <?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                    <small>🏢 <?php echo htmlspecialchars($app['department']); ?> | 📈 <?php echo ucfirst($app['experience_level']); ?> Level</small>
                                </div>
                            </div>
                            
                            <div class="candidate-body">
                                <!-- Experience Section -->
                                <div class="section">
                                    <div class="section-title">
                                        <span>💼</span>
                                        <span>Experience</span>
                                    </div>
                                    <p><strong><?php echo $experience_years; ?> years</strong> of professional experience</p>
                                    <?php if ($experience_years > 0): ?>
                                        <div class="experience-bar">
                                            <div class="experience-fill" style="width: <?php echo min(100, ($experience_years / 10) * 100); ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($parsed_data['current_position'])): ?>
                                        <p><small>Current: <?php echo htmlspecialchars($parsed_data['current_position']); ?></small></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Skills Analysis -->
                                <div class="section">
                                    <div class="section-title">
                                        <span>🎯</span>
                                        <span>Skills Analysis</span>
                                    </div>
                                    
                                    <?php if (!empty($skill_match['matched'])): ?>
                                        <p><strong>✅ Matched Skills (<?php echo count($skill_match['matched']); ?>):</strong></p>
                                        <div class="skills-container">
                                            <?php foreach ($skill_match['matched'] as $skill): ?>
                                                <span class="skill-tag skill-matched"><?php echo htmlspecialchars(ucfirst($skill)); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($skill_match['missing'])): ?>
                                        <p><strong>❌ Missing Skills (<?php echo count($skill_match['missing']); ?>):</strong></p>
                                        <div class="skills-container">
                                            <?php foreach ($skill_match['missing'] as $skill): ?>
                                                <span class="skill-tag skill-missing"><?php echo htmlspecialchars(ucfirst($skill)); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($skill_match['additional'])): ?>
                                        <p><strong>➕ Additional Skills (<?php echo count($skill_match['additional']); ?>):</strong></p>
                                        <div class="skills-container">
                                            <?php foreach (array_slice($skill_match['additional'], 0, 5) as $skill): ?>
                                                <span class="skill-tag skill-additional"><?php echo htmlspecialchars(ucfirst($skill)); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($skill_match['additional']) > 5): ?>
                                                <span class="skill-tag skill-additional">+<?php echo count($skill_match['additional']) - 5; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Education -->
                                <?php if (isset($parsed_data['education']) && !empty($parsed_data['education'])): ?>
                                <div class="section">
                                    <div class="section-title">
                                        <span>🎓</span>
                                        <span>Education</span>
                                    </div>
                                    <?php foreach (array_slice($parsed_data['education'], 0, 2) as $edu): ?>
                                        <div style="margin-bottom: 0.5rem; padding: 0.5rem; background: rgba(43,76,140,0.05); border-radius: 8px;">
                                            <p style="margin: 0; font-weight: 600;"><?php echo htmlspecialchars($edu['degree'] ?? 'Degree'); ?></p>
                                            <p style="margin: 0; color: #6c757d; font-size: 0.9rem;"><?php echo htmlspecialchars($edu['institution'] ?? 'Institution'); ?></p>
                                            <?php if (isset($edu['year'])): ?>
                                                <p style="margin: 0; color: #6c757d; font-size: 0.8rem;">Graduated: <?php echo htmlspecialchars($edu['year']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Contact & Actions -->
                                <div class="section">
                                    <div class="contact-info">
                                        <?php if (isset($parsed_data['phone'])): ?>
                                            <p>📱 <strong>Phone:</strong> <?php echo htmlspecialchars($parsed_data['phone']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($parsed_data['location'])): ?>
                                            <p>📍 <strong>Location:</strong> <?php echo htmlspecialchars($parsed_data['location']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($parsed_data['linkedin'])): ?>
                                            <p>🔗 <strong>LinkedIn:</strong> <a href="<?php echo htmlspecialchars($parsed_data['linkedin']); ?>" target="_blank">Profile</a></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <?php if (!empty($app['resume_filename'])): ?>
                                            <a href="uploads/resumes/<?php echo htmlspecialchars($app['resume_filename']); ?>" 
                                               target="_blank" class="btn btn-secondary btn-sm">
                                                📄 View Resume
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button onclick="updateStatus(<?php echo $app['id']; ?>, 'selected')" class="btn btn-success btn-sm">
                                            ✅ Select
                                        </button>
                                        <button onclick="updateStatus(<?php echo $app['id']; ?>, 'waiting_interview')" class="btn btn-warning btn-sm">
                                            📞 Interview
                                        </button>
                                        <button onclick="updateStatus(<?php echo $app['id']; ?>, 'rejected')" class="btn btn-danger btn-sm">
                                            ❌ Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Application Status</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="statusForm">
                    <input type="hidden" name="application_id" id="modalApplicationId">
                    <input type="hidden" name="status" id="modalStatus">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="form-group">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" 
                                  placeholder="Add any notes about this decision..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" onclick="closeModal()" class="btn btn-outline">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        function updateStatus(applicationId, status) {
            document.getElementById('modalApplicationId').value = applicationId;
            document.getElementById('modalStatus').value = status;
            document.getElementById('statusModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target == modal) {
                modal.style.display = 'none';
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

        // Add animation delays for cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.candidate-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>