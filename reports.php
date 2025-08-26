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

// Get analytics data
try {
    // Application metrics
    $stmt = $connection->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) as selected,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired,
        AVG(match_percentage) as avg_match
        FROM applications");
    $app_stats = $stmt->fetch_assoc();
    
    // Monthly application trends (last 6 months)
    $stmt = $connection->query("SELECT 
        DATE_FORMAT(applied_at, '%Y-%m') as month,
        COUNT(*) as count
        FROM applications 
        WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(applied_at, '%Y-%m')
        ORDER BY month DESC");
    $monthly_trends = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Department-wise applications
    $stmt = $connection->query("SELECT 
        jp.department,
        COUNT(a.id) as application_count,
        AVG(a.match_percentage) as avg_match,
        SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired_count
        FROM job_positions jp
        LEFT JOIN applications a ON jp.id = a.job_position_id
        GROUP BY jp.department
        ORDER BY application_count DESC");
    $dept_stats = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Job position performance
    $stmt = $connection->query("SELECT 
        jp.title, jp.department,
        COUNT(a.id) as applications,
        AVG(a.match_percentage) as avg_match,
        SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired
        FROM job_positions jp
        LEFT JOIN applications a ON jp.id = a.job_position_id
        WHERE jp.status = 'active'
        GROUP BY jp.id, jp.title, jp.department
        HAVING applications > 0
        ORDER BY applications DESC
        LIMIT 10");
    $job_performance = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Employee onboarding progress
    $stmt = $connection->query("SELECT 
        u.department,
        COUNT(DISTINCT u.id) as total_employees,
        COUNT(DISTINCT CASE WHEN eo.status = 'completed' THEN eo.employee_id END) as completed_tasks,
        COUNT(DISTINCT eo.id) as total_tasks
        FROM users u
        LEFT JOIN employee_onboarding eo ON u.id = eo.employee_id
        WHERE u.role = 'employee' AND u.status = 'active'
        GROUP BY u.department");
    $onboarding_stats = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Training progress
    $stmt = $connection->query("SELECT 
        tm.module_name,
        tm.department,
        COUNT(et.id) as total_assignments,
        SUM(CASE WHEN et.status = 'completed' THEN 1 ELSE 0 END) as completed,
        AVG(et.progress_percentage) as avg_progress
        FROM training_modules tm
        LEFT JOIN employee_training et ON tm.id = et.module_id
        GROUP BY tm.id, tm.module_name, tm.department
        ORDER BY total_assignments DESC");
    $training_stats = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Support ticket analytics
    $stmt = $connection->query("SELECT 
        category,
        COUNT(*) as total_tickets,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
        AVG(CASE 
            WHEN resolved_at IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) 
            ELSE NULL 
        END) as avg_resolution_hours
        FROM support_tickets
        GROUP BY category
        ORDER BY total_tickets DESC");
    $support_stats = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Hiring funnel conversion rates
    $conversion_rates = [];
    if ($app_stats['total'] > 0) {
        $conversion_rates = [
            'applied_to_selected' => ($app_stats['selected'] / $app_stats['total']) * 100,
            'selected_to_hired' => $app_stats['selected'] > 0 ? ($app_stats['hired'] / $app_stats['selected']) * 100 : 0,
            'overall_hire_rate' => ($app_stats['hired'] / $app_stats['total']) * 100
        ];
    }
    
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Vector HR System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Chart containers */
        .chart-container {
            position: relative;
            height: 400px;
            margin: 1rem 0;
        }
        
        .chart-small {
            height: 250px;
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
        
        /* Progress bars */
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
        
        /* Export button */
        .export-btn {
            background: var(--kabel-gradient);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.3);
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
            
            .chart-container {
                height: 300px;
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
                <a href="onboarding.php">
                    <span class="icon">🚀</span>
                    <span>Onboarding</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="active">
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
            <h1 class="page-title">📈 Analytics & Reports - HR Intelligence Center</h1>
            <div class="topbar-actions">
                <button class="export-btn" onclick="exportReport()">📊 Export Report</button>
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

            <!-- Key Metrics Overview -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($app_stats['total']); ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($conversion_rates['overall_hire_rate'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">Overall Hire Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($app_stats['avg_match'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">Average Match Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($dept_stats); ?></div>
                    <div class="stat-label">Active Departments</div>
                </div>
            </div>

            <!-- Application Funnel Analysis -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🔄</span>
                    <div>
                        <h3>Hiring Funnel Analysis</h3>
                        <p style="margin: 0; opacity: 0.9;">Conversion rates through the hiring process</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container chart-small">
                        <canvas id="funnelChart"></canvas>
                    </div>
                    <div style="margin-top: 1rem;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div style="text-align: center; padding: 1rem; background: rgba(255,107,53,0.1); border-radius: 12px;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                                    <?php echo number_format($conversion_rates['applied_to_selected'] ?? 0, 1); ?>%
                                </div>
                                <div style="font-size: 0.9rem; color: #6c757d;">Applied → Selected</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: rgba(40,167,69,0.1); border-radius: 12px;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--success-color);">
                                    <?php echo number_format($conversion_rates['selected_to_hired'] ?? 0, 1); ?>%
                                </div>
                                <div style="font-size: 0.9rem; color: #6c757d;">Selected → Hired</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: rgba(23,162,184,0.1); border-radius: 12px;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--info-color);">
                                    <?php echo number_format($conversion_rates['overall_hire_rate'] ?? 0, 1); ?>%
                                </div>
                                <div style="font-size: 0.9rem; color: #6c757d;">Overall Hire Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Trends -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">📊</span>
                    <div>
                        <h3>Application Trends (Last 6 Months)</h3>
                        <p style="margin: 0; opacity: 0.9;">Monthly application volume analysis</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Department Performance -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🏢</span>
                    <div>
                        <h3>Department Performance Analysis</h3>
                        <p style="margin: 0; opacity: 0.9;">Applications and hiring success by department</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Applications</th>
                                    <th>Average Match</th>
                                    <th>Hired</th>
                                    <th>Success Rate</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dept_stats as $dept): ?>
                                    <?php 
                                    $success_rate = $dept['application_count'] > 0 ? ($dept['hired_count'] / $dept['application_count']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($dept['department']); ?></strong>
                                        </td>
                                        <td><?php echo number_format($dept['application_count']); ?></td>
                                        <td><?php echo number_format($dept['avg_match'] ?? 0, 1); ?>%</td>
                                        <td><?php echo number_format($dept['hired_count']); ?></td>
                                        <td><?php echo number_format($success_rate, 1); ?>%</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo min(100, $success_rate); ?>%">
                                                    <?php echo number_format($success_rate, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Performing Jobs -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🎯</span>
                    <div>
                        <h3>Top Performing Job Positions</h3>
                        <p style="margin: 0; opacity: 0.9;">Job positions with highest application volumes</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Applications</th>
                                    <th>Avg Match</th>
                                    <th>Hired</th>
                                    <th>Success Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($job_performance as $job): ?>
                                    <?php 
                                    $job_success_rate = $job['applications'] > 0 ? ($job['hired'] / $job['applications']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($job['title']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($job['department']); ?></td>
                                        <td><?php echo number_format($job['applications']); ?></td>
                                        <td><?php echo number_format($job['avg_match'], 1); ?>%</td>
                                        <td><?php echo number_format($job['hired']); ?></td>
                                        <td>
                                            <span style="color: <?php echo $job_success_rate >= 20 ? 'var(--success-color)' : ($job_success_rate >= 10 ? 'var(--warning-color)' : 'var(--danger-color)'); ?>">
                                                <?php echo number_format($job_success_rate, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Training Analytics -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🎓</span>
                    <div>
                        <h3>Training Module Performance</h3>
                        <p style="margin: 0; opacity: 0.9;">Employee training progress and completion rates</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module Name</th>
                                    <th>Department</th>
                                    <th>Assignments</th>
                                    <th>Completed</th>
                                    <th>Completion Rate</th>
                                    <th>Avg Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($training_stats as $training): ?>
                                    <?php 
                                    $completion_rate = $training['total_assignments'] > 0 ? ($training['completed'] / $training['total_assignments']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($training['module_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($training['department']); ?></td>
                                        <td><?php echo number_format($training['total_assignments']); ?></td>
                                        <td><?php echo number_format($training['completed']); ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo min(100, $completion_rate); ?>%">
                                                    <?php echo number_format($completion_rate, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($training['avg_progress'] ?? 0, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Support Ticket Analytics -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🎫</span>
                    <div>
                        <h3>Support Ticket Analysis</h3>
                        <p style="margin: 0; opacity: 0.9;">Support ticket categories and resolution performance</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total Tickets</th>
                                    <th>Resolved</th>
                                    <th>Open</th>
                                    <th>Resolution Rate</th>
                                    <th>Avg Resolution Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($support_stats as $support): ?>
                                    <?php 
                                    $resolution_rate = $support['total_tickets'] > 0 ? ($support['resolved'] / $support['total_tickets']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--primary-color);"><?php echo ucwords(str_replace('_', ' ', $support['category'])); ?></strong>
                                        </td>
                                        <td><?php echo number_format($support['total_tickets']); ?></td>
                                        <td><?php echo number_format($support['resolved']); ?></td>
                                        <td><?php echo number_format($support['open_tickets']); ?></td>
                                        <td>
                                            <span style="color: <?php echo $resolution_rate >= 80 ? 'var(--success-color)' : ($resolution_rate >= 60 ? 'var(--warning-color)' : 'var(--danger-color)'); ?>">
                                                <?php echo number_format($resolution_rate, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $support['avg_resolution_hours'] ? number_format($support['avg_resolution_hours'], 1) . 'h' : 'N/A'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart configurations
        Chart.defaults.font.family = 'Inter';
        Chart.defaults.color = '#6c757d';
        
        // Monthly Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse(array_column($monthly_trends, 'month'))); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_reverse(array_column($monthly_trends, 'count'))); ?>,
                    borderColor: '#FF6B35',
                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                }
            }
        });
        
        // Funnel Chart
        const funnelCtx = document.getElementById('funnelChart').getContext('2d');
        const funnelChart = new Chart(funnelCtx, {
            type: 'bar',
            data: {
                labels: ['Applied', 'Selected', 'Hired'],
                datasets: [{
                    label: 'Applications',
                    data: [
                        <?php echo $app_stats['total']; ?>,
                        <?php echo $app_stats['selected']; ?>,
                        <?php echo $app_stats['hired']; ?>
                    ],
                    backgroundColor: [
                        'rgba(23, 162, 184, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(40, 167, 69, 0.8)'
                    ],
                    borderColor: [
                        '#17a2b8',
                        '#ffc107',
                        '#28a745'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        function exportReport() {
            // Implementation for export functionality
            const currentDate = new Date().toISOString().split('T')[0];
            const filename = `HR_Analytics_Report_${currentDate}.html`;
            
            // Create a comprehensive report
            const reportContent = `
                <html>
                    <head>
                        <title>HR Analytics Report - ${currentDate}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .metric { display: inline-block; margin: 10px; padding: 15px; border: 1px solid #ddd; border-radius: 8px; text-align: center; }
                            .metric h3 { margin: 0; color: #FF6B35; }
                            .metric p { margin: 5px 0 0 0; color: #666; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                            th { background-color: #f2f2f2; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>HairCare2U HR Analytics Report</h1>
                            <p>Generated on: ${new Date().toLocaleDateString()}</p>
                        </div>
                        
                        <h2>Key Metrics</h2>
                        <div class="metric">
                            <h3><?php echo $app_stats['total']; ?></h3>
                            <p>Total Applications</p>
                        </div>
                        <div class="metric">
                            <h3><?php echo number_format($conversion_rates['overall_hire_rate'] ?? 0, 1); ?>%</h3>
                            <p>Overall Hire Rate</p>
                        </div>
                        <div class="metric">
                            <h3><?php echo number_format($app_stats['avg_match'] ?? 0, 1); ?>%</h3>
                            <p>Average Match Score</p>
                        </div>
                        
                        <h2>Department Performance</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Applications</th>
                                    <th>Average Match</th>
                                    <th>Hired</th>
                                    <th>Success Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dept_stats as $dept): ?>
                                    <?php $success_rate = $dept['application_count'] > 0 ? ($dept['hired_count'] / $dept['application_count']) * 100 : 0; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                        <td><?php echo $dept['application_count']; ?></td>
                                        <td><?php echo number_format($dept['avg_match'] ?? 0, 1); ?>%</td>
                                        <td><?php echo $dept['hired_count']; ?></td>
                                        <td><?php echo number_format($success_rate, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </body>
                </html>
            `;
            
            const blob = new Blob([reportContent], { type: 'text/html' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
    </script>
</body>
</html>