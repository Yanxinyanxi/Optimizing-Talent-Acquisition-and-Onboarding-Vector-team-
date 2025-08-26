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

// Handle role transitions and redirect if needed
handleRoleRedirect($connection, 'employee-dashboard.php');

// Ensure Employee access only (after checking for role transitions)
requireRole('employee');

$user_id = $_SESSION['user_id'];

// Get employee info with job position - refresh from database to ensure latest data
$stmt = $connection->prepare("
    SELECT u.*, jp.title as job_title, jp.department as job_department, jp.description as job_description
    FROM users u 
    LEFT JOIN job_positions jp ON u.job_position_id = jp.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Update session if data has changed
if ($employee) {
    $_SESSION['full_name'] = $employee['full_name'];
    $_SESSION['email'] = $employee['email'];
    $_SESSION['department'] = $employee['department'];
    $_SESSION['job_position_id'] = $employee['job_position_id'];
}

// Determine department for queries (prefer job department over user department)
$user_department = $employee['job_department'] ?: $employee['department'] ?: 'General';

// Get overall progress stats
try {
    // Onboarding tasks progress
    $stmt = $connection->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM onboarding_tasks ot
        LEFT JOIN employee_onboarding eo ON ot.id = eo.task_id AND eo.employee_id = ?
        WHERE ot.is_mandatory = 1 AND (ot.department = 'ALL' OR ot.department = ?)
    ");
    $stmt->bind_param("is", $user_id, $user_department);
    $stmt->execute();
    $task_progress = $stmt->get_result()->fetch_assoc();
    $task_completion = $task_progress['total_tasks'] > 0 ? round(($task_progress['completed_tasks'] / $task_progress['total_tasks']) * 100) : 0;
    
    // Training modules progress
    $stmt = $connection->prepare("
        SELECT 
            COUNT(*) as total_modules,
            SUM(CASE WHEN et.status = 'completed' THEN 1 ELSE 0 END) as completed_modules,
            AVG(CASE WHEN et.progress_percentage IS NOT NULL THEN et.progress_percentage ELSE 0 END) as avg_progress
        FROM training_modules tm
        LEFT JOIN employee_training et ON tm.id = et.module_id AND et.employee_id = ?
        WHERE tm.is_mandatory = 1 AND (tm.department = 'ALL' OR tm.department = ?)
    ");
    $stmt->bind_param("is", $user_id, $user_department);
    $stmt->execute();
    $training_progress = $stmt->get_result()->fetch_assoc();
    $training_completion = $training_progress['total_modules'] > 0 ? round(($training_progress['completed_modules'] / $training_progress['total_modules']) * 100) : 0;
    $training_avg_progress = round($training_progress['avg_progress'] ?: 0);
    
    // Documents progress
    $stmt = $connection->prepare("
        SELECT 
            COUNT(*) as total_docs,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_docs,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_docs
        FROM employee_documents 
        WHERE employee_id = ? AND is_required = 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $doc_progress = $stmt->get_result()->fetch_assoc();
    $doc_completion = $doc_progress['total_docs'] > 0 ? round(($doc_progress['approved_docs'] / $doc_progress['total_docs']) * 100) : 0;
    
    // Overall completion - use training average instead of just completion
    $overall_completion = round(($task_completion + $training_avg_progress + $doc_completion) / 3);
    
    // Recent activity (last 5 completed items)
    $stmt = $connection->prepare("
        (SELECT 'task' as type, ot.task_name as name, eo.completed_at as date 
         FROM employee_onboarding eo 
         JOIN onboarding_tasks ot ON eo.task_id = ot.id 
         WHERE eo.employee_id = ? AND eo.status = 'completed')
        UNION ALL
        (SELECT 'training' as type, tm.module_name as name, et.completed_at as date 
         FROM employee_training et 
         JOIN training_modules tm ON et.module_id = tm.id 
         WHERE et.employee_id = ? AND et.status = 'completed')
        UNION ALL
        (SELECT 'document' as type, document_name as name, 
         CASE WHEN status = 'approved' THEN reviewed_at ELSE uploaded_at END as date 
         FROM employee_documents 
         WHERE employee_id = ? AND (status = 'approved' OR status = 'submitted'))
        ORDER BY date DESC 
        LIMIT 5
    ");
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get pending tasks count
    $stmt = $connection->prepare("
        SELECT COUNT(*) as pending_count
        FROM employee_onboarding eo
        JOIN onboarding_tasks ot ON eo.task_id = ot.id
        WHERE eo.employee_id = ? AND eo.status = 'pending'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending_tasks = $stmt->get_result()->fetch_assoc()['pending_count'];
    
    // Get support tickets count
    $stmt = $connection->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets
        FROM support_tickets 
        WHERE employee_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $ticket_stats = $stmt->get_result()->fetch_assoc();
    
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $task_completion = $training_completion = $doc_completion = $overall_completion = 0;
    $recent_activity = [];
    $pending_tasks = 0;
    $ticket_stats = ['total_tickets' => 0, 'open_tickets' => 0];
}

// Team information - try to get from database, fallback to defaults
$team_info = [
    'reporting_manager' => [
        'name' => 'Sarah Johnson',
        'email' => 'sarah.johnson@haircare2u.my',
        'phone' => '+60 12-345 6789',
        'position' => 'Department Head - ' . $user_department
    ],
    'hr_contact' => [
        'name' => 'Lisa Wong',
        'email' => 'hr@haircare2u.my',
        'phone' => '+60 12-345 6790',
        'position' => 'HR Manager'
    ],
    'department' => $user_department,
    'start_date' => $employee['updated_at'] ?: $employee['created_at'], // Use updated_at as it reflects when they became employee
    'employee_id' => 'HC2U-' . str_pad($employee['id'], 4, '0', STR_PAD_LEFT)
];

// Welcome message based on how recently they became an employee
$days_since_hired = 0;
if ($employee['updated_at']) {
    $days_since_hired = floor((time() - strtotime($employee['updated_at'])) / 86400);
}

$welcome_message = "Welcome to HairCare2U! 🎉";
$welcome_subtitle = "You're doing great! Here's your onboarding progress overview.";

if ($days_since_hired <= 1) {
    $welcome_message = "Welcome to the team! 🎉";
    $welcome_subtitle = "Congratulations on being hired! Let's get you started with your onboarding journey.";
} elseif ($days_since_hired <= 7) {
    $welcome_message = "Welcome to your first week! 🚀";
    $welcome_subtitle = "You're off to a great start! Keep up the momentum with your onboarding tasks.";
}

// Check if user just transitioned from candidate to employee
$show_transition_message = false;
if (isset($_GET['transitioned']) && $_GET['transitioned'] === 'true') {
    $show_transition_message = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Vector HR System</title>
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
        
        /* Welcome Section */
        .welcome-section {
            background: var(--kabel-gradient);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }
        
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 1rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        /* Transition Welcome Message */
        .transition-welcome {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .transition-welcome::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%);
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .transition-welcome h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .transition-welcome p {
            font-size: 1.2rem;
            opacity: 0.95;
            margin-bottom: 1.5rem;
        }
        
        .transition-welcome .celebration {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: bounce 2s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
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
        
        /* Progress Cards Grid */
        .progress-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .progress-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .progress-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .progress-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--kabel-gradient);
        }
        
        .progress-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .progress-card .percentage {
            font-size: 2rem;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .progress-card .label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .progress-card .sub-label {
            color: #9ca3af;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border-left: 4px solid;
            position: relative;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }
        
        .action-card.tasks {
            border-left-color: var(--primary-color);
        }
        
        .action-card.training {
            border-left-color: var(--info-color);
        }
        
        .action-card.documents {
            border-left-color: var(--warning-color);
        }
        
        .action-card.help {
            border-left-color: var(--success-color);
        }
        
        .action-card .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .action-description {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Team Info Grid */
        .team-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-card .title {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-card .content {
            color: #6c757d;
            line-height: 1.5;
        }
        
        .info-card .contact-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .info-card .contact-link:hover {
            text-decoration: underline;
        }
        
        /* Recent Activity */
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .activity-icon.task {
            background: rgba(255, 107, 53, 0.1);
        }
        
        .activity-icon.training {
            background: rgba(23, 162, 184, 0.1);
        }
        
        .activity-icon.document {
            background: rgba(255, 193, 7, 0.1);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            color: var(--secondary-color);
            margin-bottom: 0.25rem;
        }
        
        .activity-date {
            font-size: 0.85rem;
            color: #6c757d;
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
        
        .alert-info {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border-color: #17a2b8;
        }

        /* Chatbot Styles */
        #chatbot-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
            font-family: 'Inter', sans-serif;
        }

        /* Floating Button */
        #chatbot-toggle {
            width: 60px;
            height: 60px;
            background: var(--kabel-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            position: relative;
            animation: pulse 2s infinite;
        }

        #chatbot-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        #chatbot-icon {
            font-size: 1.5rem;
            color: white;
        }

        #notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: #ff4444;
            border-radius: 50%;
            border: 2px solid white;
        }

        /* Chat Window */
        #chatbot-window {
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            position: absolute;
            bottom: 80px;
            right: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        #chatbot-window.active {
            transform: translateY(0);
            opacity: 1;
        }

        /* Chat Header */
        #chatbot-header {
            background: var(--kabel-gradient);
            color: white;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chatbot-header-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chatbot-avatar {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .chatbot-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .chatbot-status {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .chatbot-controls {
            display: flex;
            gap: 0.5rem;
        }

        .chatbot-control-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .chatbot-control-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Chat Messages */
        #chatbot-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .chatbot-message {
            display: flex;
            gap: 0.75rem;
            max-width: 85%;
        }

        .bot-message {
            align-self: flex-start;
        }

        .user-message {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .bot-message .message-avatar {
            background: linear-gradient(135deg, #FF6B35, #2B4C8C);
            color: white;
        }

        .user-message .message-avatar {
            background: #e9ecef;
        }

        .message-content {
            flex: 1;
        }

        .message-text {
    background: #f8f9fa;
    padding: 0.75rem 1rem;
    border-radius: 16px;
    font-size: 0.9rem;
    line-height: 1.6; /* Increased for better readability */
    white-space: pre-line; /* Preserves line breaks */
}

.message-text strong,
.message-text b {
    font-weight: 600;
    color: #2B4C8C;
}

.message-text ol,
.message-text ul {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.message-text li {
    margin: 0.3rem 0;
    line-height: 1.4;
}

.message-text p {
    margin: 0.5rem 0;
}

.message-text h4 {
    margin: 0.7rem 0 0.3rem 0;
    color: #2B4C8C;
    font-size: 0.95rem;
}

        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.5rem;
            text-align: left;
        }

        .user-message .message-time {
            text-align: right;
        }

        /* Typing Indicator */
        #typing-indicator {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0 1rem 0.5rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .typing-dots {
            display: flex;
            gap: 0.25rem;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            background: #6c757d;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typing {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        /* Chat Input */
        #chatbot-input-area {
            padding: 1rem;
            border-top: 1px solid #e9ecef;
            background: white;
        }

        .input-container {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        #chatbot-input {
            flex: 1;
            border: 1px solid #e9ecef;
            border-radius: 20px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        #chatbot-input:focus {
            border-color: var(--primary-color);
        }

        #send-button {
            width: 40px;
            height: 40px;
            background: var(--kabel-gradient);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        #send-button:hover {
            transform: scale(1.05);
        }

        #send-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Error state */
        .message-error {
            background: #fee;
            border-left: 3px solid #dc3545;
        }

        /* Success indicators */
        .api-indicator {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .faq-match {
            color: #28a745;
        }

        .ai-response {
            color: #17a2b8;
        }

        /* Custom scrollbar for messages */
        #chatbot-messages::-webkit-scrollbar {
            width: 4px;
        }

        #chatbot-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        #chatbot-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }

        #chatbot-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
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
            
            .progress-grid,
            .quick-actions,
            .team-info-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-section {
                text-align: center;
                padding: 1.5rem;
            }
            
            .welcome-section h2 {
                font-size: 1.5rem;
            }
            
            .progress-circle {
                width: 100px;
                height: 100px;
                font-size: 1.5rem;
            }
            
            .topbar {
                padding-left: 4rem;
            }
            
            .page-title {
                font-size: 1.25rem;
            }

            /* Chatbot mobile styles */
            #chatbot-container {
                bottom: 90px;
                right: 15px;
            }
            
            #chatbot-window {
                width: calc(100vw - 30px);
                height: 70vh;
                bottom: 75px;
                right: -10px;
            }
            
            #chatbot-toggle {
                width: 55px;
                height: 55px;
            }
            
            #chatbot-icon {
                font-size: 1.3rem;
            }
        }
        
        .mobile-toggle {
            display: none;
        }
        
        /* Animations */
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 107, 53, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 107, 53, 0); }
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
                <a href="employee-dashboard.php" class="active">
                    <span class="icon">🏠</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="employee-profile.php">
                    <span class="icon">👤</span>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="onboarding-tasks.php">
                    <span class="icon">📋</span>
                    <span>Onboarding Tasks</span>
                </a>
            </li>
            <li>
                <a href="training-modules.php">
                    <span class="icon">🎓</span>
                    <span>Training Modules</span>
                </a>
            </li>
            <li>
                <a href="required-documents.php">
                    <span class="icon">📄</span>
                    <span>Required Documents</span>
                </a>
            </li>
            <li>
                <a href="get-help.php">
                    <span class="icon">❓</span>
                    <span>Get Help</span>
                    \
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
                    <?php echo htmlspecialchars($user_department); ?> • Employee
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
            <h1 class="page-title">🏠 My Dashboard</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Welcome back, <?php echo htmlspecialchars($employee['full_name']); ?>!
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

            <?php if ($show_transition_message): ?>
                <!-- Special welcome message for newly transitioned employees -->
                <div class="transition-welcome">
                    <div class="celebration">🎊🎉🎊</div>
                    <h2>Congratulations! You're Now Part of the Team!</h2>
                    <p>You've been successfully hired and your account has been converted to employee status. Welcome to HairCare2U!</p>
                    <div style="background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                        <strong>What's Next?</strong><br>
                        Complete your onboarding tasks, training modules, and document submissions to get fully integrated into the team.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 300px;">
                        <h2 style="font-size: 2rem; margin-bottom: 1rem;"><?php echo $welcome_message; ?></h2>
                        <p style="font-size: 1.1rem; opacity: 0.9; margin-bottom: 1.5rem;">
                            <?php echo $welcome_subtitle; ?>
                        </p>
                        <?php if ($overall_completion == 100): ?>
                            <div style="background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 8px;">
                                <strong>🎊 Congratulations!</strong> You've completed your onboarding journey!
                            </div>
                        <?php elseif ($overall_completion >= 75): ?>
                            <div style="background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 8px;">
                                <strong>🚀 Almost there!</strong> You're nearly done with your onboarding.
                            </div>
                        <?php elseif ($days_since_hired <= 1): ?>
                            <div style="background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 8px;">
                                <strong>🌟 Getting Started!</strong> Take your time to explore and complete your initial tasks.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: center; min-width: 150px;">
                        <div class="progress-circle">
                            <?php echo $overall_completion; ?>%
                        </div>
                        <p style="margin: 0; opacity: 0.9; font-weight: 500;">Overall Progress</p>
                    </div>
                </div>
            </div>

            <!-- Progress Overview -->
            <div class="progress-grid">
                <div class="progress-card">
                    <div class="icon">📋</div>
                    <div class="percentage"><?php echo $task_completion; ?>%</div>
                    <div class="label">Onboarding Tasks</div>
                </div>
                <div class="progress-card">
                    <div class="icon">🎓</div>
                    <div class="percentage"><?php echo $training_avg_progress; ?>%</div>
                    <div class="label">Training Progress</div>
                    <div class="sub-label">Average across all modules</div>
                </div>
                <div class="progress-card">
                    <div class="icon">📄</div>
                    <div class="percentage"><?php echo $doc_completion; ?>%</div>
                    <div class="label">Required Documents</div>
                    <?php if ($doc_progress['submitted_docs'] > 0 && $doc_progress['approved_docs'] < $doc_progress['total_docs']): ?>
                        <div class="sub-label">Some documents under review</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="onboarding-tasks.php" class="action-card tasks">
                    <div class="action-icon">📋</div>
                    <div class="action-title">My Tasks</div>
                    <div class="action-description">View and complete onboarding tasks</div>
                </a>
                <a href="training-modules.php" class="action-card training">
                    <div class="action-icon">🎓</div>
                    <div class="action-title">Training</div>
                    <div class="action-description">Access training modules and courses</div>
                </a>
                <a href="required-documents.php" class="action-card documents">
                    <div class="action-icon">📄</div>
                    <div class="action-title">Documents</div>
                    <div class="action-description">Upload and manage documents</div>
                </a>
                <a href="get-help.php" class="action-card help">
                    <?php if ($ticket_stats['open_tickets'] > 0): ?>
                        <span class="notification-badge"><?php echo $ticket_stats['open_tickets']; ?></span>
                    <?php endif; ?>
                    <div class="action-icon">❓</div>
                    <div class="action-title">Get Help</div>
                    <div class="action-description">Need assistance? We're here to help</div>
                </a>
            </div>

            <!-- Team Information -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">👥</span>
                    <div>
                        <h3>Team Information</h3>
                        <p style="margin: 0; opacity: 0.9;">Your key contacts and team details</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="team-info-grid">
                        <div class="info-card">
                            <div class="title">
                                <span>👤</span>
                                Reporting Manager
                            </div>
                            <div class="content">
                                <strong><?php echo $team_info['reporting_manager']['name']; ?></strong><br>
                                <span><?php echo $team_info['reporting_manager']['position']; ?></span><br>
                                <a href="mailto:<?php echo $team_info['reporting_manager']['email']; ?>" class="contact-link">
                                    <?php echo $team_info['reporting_manager']['email']; ?>
                                </a><br>
                                <a href="tel:<?php echo $team_info['reporting_manager']['phone']; ?>" class="contact-link">
                                    <?php echo $team_info['reporting_manager']['phone']; ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="title">
                                <span>🏢</span>
                                Department & Role
                            </div>
                            <div class="content">
                                <strong>Department:</strong> <?php echo htmlspecialchars($team_info['department']); ?><br>
                                <strong>Position:</strong> <?php echo htmlspecialchars($employee['job_title'] ?: 'Employee'); ?><br>
                                <strong>Employee ID:</strong> <?php echo $team_info['employee_id']; ?><br>
                                <strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($team_info['start_date'])); ?>
                                <?php if ($days_since_hired <= 7): ?>
                                    <br><small style="color: var(--success-color); font-weight: 500;">
                                        <?php echo $days_since_hired == 0 ? 'Joined today!' : "Day $days_since_hired"; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="title">
                                <span>💼</span>
                                HR Contact
                            </div>
                            <div class="content">
                                <strong><?php echo $team_info['hr_contact']['name']; ?></strong><br>
                                <span><?php echo $team_info['hr_contact']['position']; ?></span><br>
                                <a href="mailto:<?php echo $team_info['hr_contact']['email']; ?>" class="contact-link">
                                    <?php echo $team_info['hr_contact']['email']; ?>
                                </a><br>
                                <a href="tel:<?php echo $team_info['hr_contact']['phone']; ?>" class="contact-link">
                                    <?php echo $team_info['hr_contact']['phone']; ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="title">
                                <span>🕒</span>
                                Working Hours
                            </div>
                            <div class="content">
                                <strong>Standard Hours:</strong> 9:00 AM - 6:00 PM<br>
                                <strong>Working Days:</strong> Monday - Friday<br>
                                <strong>Break Time:</strong> 12:00 PM - 1:00 PM<br>
                                <strong>Flexible:</strong> Available after probation
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <?php if (!empty($recent_activity)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="icon">📈</span>
                    <div>
                        <h3>Recent Activity</h3>
                        <p style="margin: 0; opacity: 0.9;">Your latest completed items</p>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon <?php echo $activity['type']; ?>">
                                    <?php
                                    switch($activity['type']) {
                                        case 'task': echo '✅'; break;
                                        case 'training': echo '🎓'; break;
                                        case 'document': echo '📄'; break;
                                        default: echo '✨';
                                    }
                                    ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        Completed: <?php echo htmlspecialchars($activity['name']); ?>
                                    </div>
                                    <div class="activity-date">
                                        <?php echo $activity['date'] ? date('M j, Y - g:i A', strtotime($activity['date'])) : 'Recently'; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php elseif ($days_since_hired <= 1): ?>
            <div class="alert alert-info">
                <span>🌟</span>
                <div>
                    <strong>Welcome!</strong> Once you start completing tasks and training, your recent activity will appear here.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chatbot Floating Button -->
    <div id="chatbot-container">
        <!-- Floating Button -->
        <div id="chatbot-toggle" onclick="toggleChatbot()">
            <div id="chatbot-icon">💬</div>
            <div id="notification-dot" style="display: none;"></div>
        </div>
        
        <!-- Chat Window -->
        <div id="chatbot-window" style="display: none;">
            <!-- Chat Header -->
            <div id="chatbot-header">
                <div class="chatbot-header-info">
                    <div class="chatbot-avatar">🤖</div>
                    <div class="chatbot-details">
                        <div class="chatbot-name">HR Assistant</div>
                        <div class="chatbot-status">Online • Ready to help</div>
                    </div>
                </div>
                <div class="chatbot-controls">
                    <button onclick="minimizeChatbot()" class="chatbot-control-btn">−</button>
                    <button onclick="closeChatbot()" class="chatbot-control-btn">×</button>
                </div>
            </div>
            
            <!-- Chat Messages -->
            <div id="chatbot-messages">
                <div class="chatbot-message bot-message">
                    <div class="message-avatar">🤖</div>
                    <div class="message-content">
                        <div class="message-text">
                            Hello! I'm your HR assistant. I can help you with:
                            <ul>
                                <li>Onboarding questions</li>
                                <li>Company policies</li>
                                <li>Training information</li>
                                <li>Document requirements</li>
                                <li>General HR inquiries</li>
                            </ul>
                            How can I assist you today?
                        </div>
                        <div class="message-time"><?php echo date('g:i A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Typing Indicator -->
            <div id="typing-indicator" style="display: none;">
                <div class="typing-dots">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
                <span>HR Assistant is typing...</span>
            </div>
            
            <!-- Chat Input -->
            <div id="chatbot-input-area">
                <div class="input-container">
                    <input type="text" id="chatbot-input" placeholder="Type your message..." onkeypress="handleKeyPress(event)">
                    <button id="send-button" onclick="sendMessage()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Chatbot JavaScript Functions
        let chatbotOpen = false;

        function toggleChatbot() {
            const chatbotWindow = document.getElementById('chatbot-window');
            const chatbotToggle = document.getElementById('chatbot-toggle');
            
            if (chatbotOpen) {
                closeChatbot();
            } else {
                openChatbot();
            }
        }

        function openChatbot() {
            const chatbotWindow = document.getElementById('chatbot-window');
            const chatbotIcon = document.getElementById('chatbot-icon');
            
            chatbotWindow.style.display = 'flex';
            setTimeout(() => {
                chatbotWindow.classList.add('active');
            }, 10);
            
            chatbotIcon.innerHTML = '×';
            chatbotOpen = true;
            
            // Focus input
            document.getElementById('chatbot-input').focus();
            
            // Hide notification dot
            document.getElementById('notification-dot').style.display = 'none';
        }

        function closeChatbot() {
            const chatbotWindow = document.getElementById('chatbot-window');
            const chatbotIcon = document.getElementById('chatbot-icon');
            
            chatbotWindow.classList.remove('active');
            setTimeout(() => {
                chatbotWindow.style.display = 'none';
            }, 300);
            
            chatbotIcon.innerHTML = '💬';
            chatbotOpen = false;
        }

        function minimizeChatbot() {
            closeChatbot();
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        function sendMessage() {
            const input = document.getElementById('chatbot-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Clear input and disable send button
            input.value = '';
            setSendButtonState(false);
            
            // Add user message to chat
            addMessage(message, 'user');
            
            // Show typing indicator
            showTypingIndicator();
            
            // Send to backend
            fetch('chatbot_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                hideTypingIndicator();
                setSendButtonState(true);
                
                if (data.success) {
                    addMessage(data.response, 'bot', data.api_used ? 'ai-response' : 'faq-match');
                } else {
                    addMessage(data.response || 'Sorry, I encountered an error. Please try again.', 'bot', 'error');
                }
            })
            .catch(error => {
                console.error('Chatbot error:', error);
                hideTypingIndicator();
                setSendButtonState(true);
                addMessage('I apologize, but I\'m experiencing technical difficulties. Please contact HR directly at hr@haircare2u.my for immediate assistance.', 'bot', 'error');
            });
        }

        function addMessage(text, sender, type = '') {
            const messagesContainer = document.getElementById('chatbot-messages');
            const messageDiv = document.createElement('div');
            const currentTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            messageDiv.className = `chatbot-message ${sender}-message`;
            
            const avatar = sender === 'user' 
                ? '<?php echo substr($_SESSION["full_name"] ?? "U", 0, 1); ?>' 
                : '🤖';
            
            let typeIndicator = '';
            if (type === 'faq-match') {
                typeIndicator = '<div class="api-indicator faq-match">📚 From FAQ</div>';
            } else if (type === 'ai-response') {
                typeIndicator = '<div class="api-indicator ai-response">🤖 AI Response</div>';
            }
            
            messageDiv.innerHTML = `
                <div class="message-avatar">${avatar}</div>
                <div class="message-content">
                    <div class="message-text ${type === 'error' ? 'message-error' : ''}">${text}</div>
                    <div class="message-time">${currentTime}</div>
                    ${typeIndicator}
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function showTypingIndicator() {
            document.getElementById('typing-indicator').style.display = 'flex';
            const messagesContainer = document.getElementById('chatbot-messages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function hideTypingIndicator() {
            document.getElementById('typing-indicator').style.display = 'none';
        }

        function setSendButtonState(enabled) {
            const sendButton = document.getElementById('send-button');
            sendButton.disabled = !enabled;
        }

        // Show notification dot for new features (optional)
        function showChatbotNotification() {
            if (!chatbotOpen) {
                document.getElementById('notification-dot').style.display = 'block';
            }
        }
        
        // Auto-hide alerts after 5 seconds (except for transition welcome)
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (!alert.classList.contains('transition-welcome')) {
                    setTimeout(function() {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(function() {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 300);
                    }, 5000);
                }
            });
            
            // Hide transition welcome message after 10 seconds
            const transitionWelcome = document.querySelector('.transition-welcome');
            if (transitionWelcome) {
                setTimeout(function() {
                    transitionWelcome.style.opacity = '0';
                    transitionWelcome.style.transform = 'translateY(-20px)';
                    setTimeout(function() {
                        transitionWelcome.remove();
                    }, 500);
                }, 10000);
            }
            
            // Auto-focus input when chatbot opens
            document.getElementById('chatbot-input').addEventListener('focus', function() {
                setTimeout(() => {
                    const messagesContainer = document.getElementById('chatbot-messages');
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }, 100);
            });
        });
        
        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.progress-card, .action-card');
            cards.forEach(function(card) {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });

        // Check for role transitions every 5 minutes
        setInterval(function() {
            fetch('check-role-transition.php')
                .then(response => response.json())
                .then(data => {
                    if (data.role_changed && data.new_role !== 'employee') {
                        // Role has changed, redirect
                        window.location.href = data.redirect_url;
                    }
                })
                .catch(error => {
                    console.log('Role check failed:', error);
                });
        }, 300000); // 5 minutes

    </script>
</body>
</html>