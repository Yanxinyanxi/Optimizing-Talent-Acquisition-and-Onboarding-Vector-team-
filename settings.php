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

$success = '';
$error = '';

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_user':
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $full_name = $_POST['full_name'];
                    $role = $_POST['role'];
                    $department = $_POST['department'];
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    
                    $stmt = $connection->prepare("INSERT INTO users (username, email, password, full_name, role, department) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $username, $email, $password, $full_name, $role, $department);
                    $stmt->execute();
                    $success = "User added successfully!";
                    break;
                    
                case 'update_user':
                    $user_id = $_POST['user_id'];
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $full_name = $_POST['full_name'];
                    $role = $_POST['role'];
                    $department = $_POST['department'];
                    $status = $_POST['status'];
                    
                    $stmt = $connection->prepare("UPDATE users SET username=?, email=?, full_name=?, role=?, department=?, status=? WHERE id=?");
                    $stmt->bind_param("ssssssi", $username, $email, $full_name, $role, $department, $status, $user_id);
                    $stmt->execute();
                    $success = "User updated successfully!";
                    break;
                    
                case 'add_job_position':
                    $title = $_POST['title'];
                    $department = $_POST['department'];
                    $description = $_POST['description'];
                    $required_skills = $_POST['required_skills'];
                    $experience_level = $_POST['experience_level'];
                    $created_by = $_SESSION['user_id'];
                    
                    $stmt = $connection->prepare("INSERT INTO job_positions (title, department, description, required_skills, experience_level, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssi", $title, $department, $description, $required_skills, $experience_level, $created_by);
                    $stmt->execute();
                    $success = "Job position added successfully!";
                    break;
                    
                case 'update_job_position':
                    $job_id = $_POST['job_id'];
                    $title = $_POST['title'];
                    $department = $_POST['department'];
                    $description = $_POST['description'];
                    $required_skills = $_POST['required_skills'];
                    $experience_level = $_POST['experience_level'];
                    $status = $_POST['status'];
                    
                    $stmt = $connection->prepare("UPDATE job_positions SET title=?, department=?, description=?, required_skills=?, experience_level=?, status=? WHERE id=?");
                    $stmt->bind_param("ssssssi", $title, $department, $description, $required_skills, $experience_level, $status, $job_id);
                    $stmt->execute();
                    $success = "Job position updated successfully!";
                    break;
                    
                case 'add_training_module':
                    $module_name = $_POST['module_name'];
                    $description = $_POST['description'];
                    $content_url = $_POST['content_url'];
                    $department = $_POST['department'];
                    $duration_hours = $_POST['duration_hours'];
                    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
                    
                    $stmt = $connection->prepare("INSERT INTO training_modules (module_name, description, content_url, department, duration_hours, is_mandatory) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssii", $module_name, $description, $content_url, $department, $duration_hours, $is_mandatory);
                    $stmt->execute();
                    $success = "Training module added successfully!";
                    break;
                    
                case 'update_training_module':
                    $module_id = $_POST['module_id'];
                    $module_name = $_POST['module_name'];
                    $description = $_POST['description'];
                    $content_url = $_POST['content_url'];
                    $department = $_POST['department'];
                    $duration_hours = $_POST['duration_hours'];
                    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
                    
                    $stmt = $connection->prepare("UPDATE training_modules SET module_name=?, description=?, content_url=?, department=?, duration_hours=?, is_mandatory=? WHERE id=?");
                    $stmt->bind_param("ssssiii", $module_name, $description, $content_url, $department, $duration_hours, $is_mandatory, $module_id);
                    $stmt->execute();
                    $success = "Training module updated successfully!";
                    break;
                    
                case 'add_onboarding_task':
                    $task_name = $_POST['task_name'];
                    $description = $_POST['description'];
                    $department = $_POST['department'];
                    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
                    $order_sequence = $_POST['order_sequence'];
                    
                    $stmt = $connection->prepare("INSERT INTO onboarding_tasks (task_name, description, department, is_mandatory, order_sequence) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssii", $task_name, $description, $department, $is_mandatory, $order_sequence);
                    $stmt->execute();
                    $success = "Onboarding task added successfully!";
                    break;
                    
                case 'update_onboarding_task':
                    $task_id = $_POST['task_id'];
                    $task_name = $_POST['task_name'];
                    $description = $_POST['description'];
                    $department = $_POST['department'];
                    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
                    $order_sequence = $_POST['order_sequence'];
                    
                    $stmt = $connection->prepare("UPDATE onboarding_tasks SET task_name=?, description=?, department=?, is_mandatory=?, order_sequence=? WHERE id=?");
                    $stmt->bind_param("sssiii", $task_name, $description, $department, $is_mandatory, $order_sequence, $task_id);
                    $stmt->execute();
                    $success = "Onboarding task updated successfully!";
                    break;
                    
                case 'update_chatbot_settings':
                    $chatbot_enabled = isset($_POST['chatbot_enabled']) ? 1 : 0;
                    $api_provider = $_POST['api_provider'];
                    $api_key = $_POST['api_key'];
                    $default_response = $_POST['default_response'];
                    $greeting_message = $_POST['greeting_message'];
                    
                    // Update settings
                    $settings = [
                        'chatbot_enabled' => $chatbot_enabled,
                        'api_provider' => $api_provider,
                        'api_key' => $api_key,
                        'default_response' => $default_response,
                        'greeting_message' => $greeting_message
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $stmt = $connection->prepare("UPDATE chatbot_settings SET setting_value = ? WHERE setting_key = ?");
                        $stmt->bind_param("ss", $value, $key);
                        $stmt->execute();
                    }
                    
                    $success = "Chatbot settings updated successfully!";
                    break;
                    
                case 'add_faq':
                    $question = $_POST['question'];
                    $answer = $_POST['answer'];
                    $category = $_POST['category'];
                    $keywords = $_POST['keywords'];
                    
                    $stmt = $connection->prepare("INSERT INTO chatbot_faq (question, answer, category, keywords) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $question, $answer, $category, $keywords);
                    $stmt->execute();
                    $success = "FAQ added successfully!";
                    break;
                    
                case 'delete_user':
                    $user_id = $_POST['user_id'];
                    $stmt = $connection->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND id != ?");
                    $stmt->bind_param("ii", $user_id, $_SESSION['user_id']); // Prevent self-deletion
                    $stmt->execute();
                    $success = "User deactivated successfully!";
                    break;
                    
                case 'delete_job_position':
                    $job_id = $_POST['job_id'];
                    $stmt = $connection->prepare("UPDATE job_positions SET status = 'closed' WHERE id = ?");
                    $stmt->bind_param("i", $job_id);
                    $stmt->execute();
                    $success = "Job position closed successfully!";
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Operation failed: " . $e->getMessage();
    }
}

// Fetch data for display
try {
    // Get all users
    $users_stmt = $connection->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $users_stmt->fetch_all(MYSQLI_ASSOC);
    
    // Get all job positions
    $jobs_stmt = $connection->query("SELECT * FROM job_positions ORDER BY created_at DESC");
    $job_positions = $jobs_stmt->fetch_all(MYSQLI_ASSOC);
    
    // Get all training modules
    $training_stmt = $connection->query("SELECT * FROM training_modules ORDER BY created_at DESC");
    $training_modules = $training_stmt->fetch_all(MYSQLI_ASSOC);
    
    // Get all onboarding tasks
    $tasks_stmt = $connection->query("SELECT * FROM onboarding_tasks ORDER BY order_sequence");
    $onboarding_tasks = $tasks_stmt->fetch_all(MYSQLI_ASSOC);
    
    // Get chatbot settings
    $settings_stmt = $connection->query("SELECT setting_key, setting_value FROM chatbot_settings");
    $chatbot_settings = [];
    while ($row = $settings_stmt->fetch_assoc()) {
        $chatbot_settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Get FAQ items
    $faq_stmt = $connection->query("SELECT * FROM chatbot_faq ORDER BY created_at DESC LIMIT 10");
    $faq_items = $faq_stmt->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "Failed to load data: " . $e->getMessage();
}

$departments = ['ALL', 'IT', 'Sales & Marketing', 'Customer Service', 'Operations', 'Management'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Vector HR System</title>
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
        
        /* Tabs */
        .tabs {
            display: flex;
            background: white;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 0;
            overflow-x: auto;
        }
        
        .tab-button {
            background: none;
            border: none;
            padding: 1.5rem 2rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab-button:hover,
        .tab-button.active {
            color: var(--primary-color);
            background: rgba(255,107,53,0.05);
        }
        
        .tab-button.active {
            border-bottom: 3px solid var(--primary-color);
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .form-check input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
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
        
        .btn-secondary {
            background: #6c757d;
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
        
        /* Status badges */
        .badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }
        
        .badge-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
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
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
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
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
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
        
        .modal-body {
            padding: 2rem;
        }
        
        .close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }
        
        .close:hover {
            background-color: rgba(255,255,255,0.2);
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
            
            .topbar {
                padding-left: 4rem;
            }
            
            .page-title {
                font-size: 1.25rem;
            }
            
            .tabs {
                overflow-x: scroll;
            }
            
            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
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
                <a href="reports.php">
                    <span class="icon">📈</span>
                    <span>Analytics</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="active">
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
            <h1 class="page-title">⚙️ System Settings & Configuration</h1>
        </div>
        
        <div class="content-area">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span>⚠️</span>
                    <div>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div>
                        <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="tabs">
                <button class="tab-button active" onclick="openTab(event, 'users')">
                    👥 User Management
                </button>
                <button class="tab-button" onclick="openTab(event, 'jobs')">
                    💼 Job Positions
                </button>
                <button class="tab-button" onclick="openTab(event, 'training')">
                    🎓 Training Modules
                </button>
                <button class="tab-button" onclick="openTab(event, 'onboarding')">
                    🚀 Onboarding Tasks
                </button>
                <button class="tab-button" onclick="openTab(event, 'chatbot')">
                    🤖 Chatbot Settings
                </button>
            </div>

            <!-- User Management Tab -->
            <div id="users" class="tab-content active">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 2rem;">
                    <h2>User Management</h2>
                    <button class="btn btn-primary" onclick="openModal('addUserModal')">Add New User</button>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] === 'hr' ? 'badge-success' : ($user['role'] === 'employee' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['department']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">Edit</button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Job Positions Tab -->
            <div id="jobs" class="tab-content">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 2rem;">
                    <h2>Job Positions Management</h2>
                    <button class="btn btn-primary" onclick="openModal('addJobModal')">Add New Job Position</button>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Department</th>
                                <th>Experience Level</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($job_positions as $job): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($job['title']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($job['department']); ?></td>
                                    <td><?php echo ucfirst($job['experience_level']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $job['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="editJob(<?php echo htmlspecialchars(json_encode($job)); ?>)">Edit</button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteJob(<?php echo $job['id']; ?>)">Close</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Training Modules Tab -->
            <div id="training" class="tab-content">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 2rem;">
                    <h2>Training Modules Management</h2>
                    <button class="btn btn-primary" onclick="openModal('addTrainingModal')">Add New Training Module</button>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Module Name</th>
                                <th>Department</th>
                                <th>Duration (Hours)</th>
                                <th>Mandatory</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($training_modules as $module): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($module['module_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($module['department']); ?></td>
                                    <td><?php echo $module['duration_hours']; ?>h</td>
                                    <td>
                                        <span class="badge <?php echo $module['is_mandatory'] ? 'badge-danger' : 'badge-warning'; ?>">
                                            <?php echo $module['is_mandatory'] ? 'Required' : 'Optional'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="editTraining(<?php echo htmlspecialchars(json_encode($module)); ?>)">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Onboarding Tasks Tab -->
            <div id="onboarding" class="tab-content">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 2rem;">
                    <h2>Onboarding Tasks Management</h2>
                    <button class="btn btn-primary" onclick="openModal('addTaskModal')">Add New Task</button>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Task Name</th>
                                <th>Department</th>
                                <th>Mandatory</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($onboarding_tasks as $task): ?>
                                <tr>
                                    <td>
                                        <span style="background: var(--primary-color); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: bold;">
                                            <?php echo $task['order_sequence']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($task['task_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['department']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $task['is_mandatory'] ? 'badge-danger' : 'badge-warning'; ?>">
                                            <?php echo $task['is_mandatory'] ? 'Required' : 'Optional'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="editTask(<?php echo htmlspecialchars(json_encode($task)); ?>)">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Chatbot Settings Tab -->
            <div id="chatbot" class="tab-content">
                <h2>Chatbot Settings</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_chatbot_settings">
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="chatbot_enabled" id="chatbot_enabled" <?php echo ($chatbot_settings['chatbot_enabled'] ?? 0) ? 'checked' : ''; ?>>
                            <label for="chatbot_enabled" class="form-label">Enable Chatbot</label>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="api_provider" class="form-label">API Provider</label>
                            <select name="api_provider" id="api_provider" class="form-control form-select">
                                <option value="static" <?php echo ($chatbot_settings['api_provider'] ?? '') === 'static' ? 'selected' : ''; ?>>Static Responses</option>
                                <option value="openai" <?php echo ($chatbot_settings['api_provider'] ?? '') === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                                <option value="dialogflow" <?php echo ($chatbot_settings['api_provider'] ?? '') === 'dialogflow' ? 'selected' : ''; ?>>Dialogflow</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="api_key" class="form-label">API Key</label>
                            <input type="password" name="api_key" id="api_key" class="form-control" 
                                   value="<?php echo htmlspecialchars($chatbot_settings['api_key'] ?? ''); ?>" 
                                   placeholder="Enter API key if using external service">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="greeting_message" class="form-label">Greeting Message</label>
                        <textarea name="greeting_message" id="greeting_message" class="form-control" rows="3"><?php echo htmlspecialchars($chatbot_settings['greeting_message'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_response" class="form-label">Default Response (when no match found)</label>
                        <textarea name="default_response" id="default_response" class="form-control" rows="3"><?php echo htmlspecialchars($chatbot_settings['default_response'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Chatbot Settings</button>
                </form>
                
                <hr style="margin: 2rem 0;">
                
                <!-- FAQ Management -->
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 2rem;">
                    <h3>FAQ Management</h3>
                    <button class="btn btn-primary" onclick="openModal('addFaqModal')">Add FAQ</button>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Category</th>
                                <th>Keywords</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faq_items as $faq): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars(substr($faq['question'], 0, 50)) . '...'; ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($faq['category']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($faq['keywords'], 0, 30)) . '...'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $faq['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $faq['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="close" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role" class="form-label">Role</label>
                            <select name="role" id="role" class="form-control form-select" required>
                                <option value="">Select Role</option>
                                <option value="hr">HR</option>
                                <option value="employee">Employee</option>
                                <option value="candidate">Candidate</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="department" class="form-label">Department</label>
                            <select name="department" id="department" class="form-control form-select" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_full_name" class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="edit_role" class="form-label">Role</label>
                            <select name="role" id="edit_role" class="form-control form-select" required>
                                <option value="hr">HR</option>
                                <option value="employee">Employee</option>
                                <option value="candidate">Candidate</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_department" class="form-label">Department</label>
                            <select name="department" id="edit_department" class="form-control form-select" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_status" class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-control form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Job Position Modal -->
    <div id="addJobModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Job Position</h3>
                <button class="close" onclick="closeModal('addJobModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_job_position">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="job_title" class="form-label">Job Title</label>
                            <input type="text" name="title" id="job_title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="job_department" class="form-label">Department</label>
                            <select name="department" id="job_department" class="form-control form-select" required>
                                <?php foreach (array_slice($departments, 1) as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="job_description" class="form-label">Description</label>
                        <textarea name="description" id="job_description" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="job_skills" class="form-label">Required Skills (comma-separated)</label>
                        <textarea name="required_skills" id="job_skills" class="form-control" rows="2" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="job_experience" class="form-label">Experience Level</label>
                        <select name="experience_level" id="job_experience" class="form-control form-select" required>
                            <option value="entry">Entry Level</option>
                            <option value="mid">Mid Level</option>
                            <option value="senior">Senior Level</option>
                        </select>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addJobModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Job Position</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Training Module Modal -->
    <div id="addTrainingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Training Module</h3>
                <button class="close" onclick="closeModal('addTrainingModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_training_module">
                    
                    <div class="form-group">
                        <label for="training_name" class="form-label">Module Name</label>
                        <input type="text" name="module_name" id="training_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="training_description" class="form-label">Description</label>
                        <textarea name="description" id="training_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="training_url" class="form-label">Content URL</label>
                            <input type="url" name="content_url" id="training_url" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="training_duration" class="form-label">Duration (Hours)</label>
                            <input type="number" name="duration_hours" id="training_duration" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="training_dept" class="form-label">Department</label>
                            <select name="department" id="training_dept" class="form-control form-select" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check" style="margin-top: 2rem;">
                                <input type="checkbox" name="is_mandatory" id="training_mandatory" checked>
                                <label for="training_mandatory" class="form-label">Mandatory Training</label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addTrainingModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Module</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Onboarding Task Modal -->
    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Onboarding Task</h3>
                <button class="close" onclick="closeModal('addTaskModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_onboarding_task">
                    
                    <div class="form-group">
                        <label for="task_name" class="form-label">Task Name</label>
                        <input type="text" name="task_name" id="task_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="task_description" class="form-label">Description</label>
                        <textarea name="description" id="task_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="task_dept" class="form-label">Department</label>
                            <select name="department" id="task_dept" class="form-control form-select" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="task_order" class="form-label">Order Sequence</label>
                            <input type="number" name="order_sequence" id="task_order" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_mandatory" id="task_mandatory" checked>
                            <label for="task_mandatory" class="form-label">Mandatory Task</label>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Job Position Modal -->
    <div id="editJobModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Job Position</h3>
                <button class="close" onclick="closeModal('editJobModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_job_position">
                    <input type="hidden" name="job_id" id="edit_job_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_job_title" class="form-label">Job Title</label>
                            <input type="text" name="title" id="edit_job_title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_job_department" class="form-label">Department</label>
                            <select name="department" id="edit_job_department" class="form-control form-select" required>
                                <?php foreach (array_slice($departments, 1) as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_job_description" class="form-label">Description</label>
                        <textarea name="description" id="edit_job_description" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_job_skills" class="form-label">Required Skills (comma-separated)</label>
                        <textarea name="required_skills" id="edit_job_skills" class="form-control" rows="2" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_job_experience" class="form-label">Experience Level</label>
                            <select name="experience_level" id="edit_job_experience" class="form-control form-select" required>
                                <option value="entry">Entry Level</option>
                                <option value="mid">Mid Level</option>
                                <option value="senior">Senior Level</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_job_status" class="form-label">Status</label>
                            <select name="status" id="edit_job_status" class="form-control form-select" required>
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editJobModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Job Position</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Training Module Modal -->
    <div id="editTrainingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Training Module</h3>
                <button class="close" onclick="closeModal('editTrainingModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_training_module">
                    <input type="hidden" name="module_id" id="edit_module_id">
                    
                    <div class="form-group">
                        <label for="edit_training_name" class="form-label">Module Name</label>
                        <input type="text" name="module_name" id="edit_training_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_training_description" class="form-label">Description</label>
                        <textarea name="description" id="edit_training_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_training_url" class="form-label">Content URL</label>
                            <input type="url" name="content_url" id="edit_training_url" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_training_duration" class="form-label">Duration (Hours)</label>
                            <input type="number" name="duration_hours" id="edit_training_duration" class="form-control" min="1" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_training_dept" class="form-label">Department</label>
                            <select name="department" id="edit_training_dept" class="form-control form-select" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check" style="margin-top: 2rem;">
                                <input type="checkbox" name="is_mandatory" id="edit_training_mandatory">
                                <label for="edit_training_mandatory" class="form-label">Mandatory Training</label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editTrainingModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Module</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Onboarding Task Modal -->
    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Onboarding Task</h3>
                <button class="close" onclick="closeModal('editTaskModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_onboarding_task">
                    <input type="hidden" name="task_id" id="edit_task_id">
                    
                    <div class="form-group">
                        <label for="edit_task_name" class="form-label">Task Name</label>
                        <input type="text" name="task_name" id="edit_task_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_task_description" class="form-label">Description</label>
                        <textarea name="description" id="edit_task_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_task_dept" class="form-label">Department</label>
                            <select name="department" id="edit_task_dept" class="form-control form-select" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_task_order" class="form-label">Order Sequence</label>
                            <input type="number" name="order_sequence" id="edit_task_order" class="form-control" min="1" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_mandatory" id="edit_task_mandatory">
                            <label for="edit_task_mandatory" class="form-label">Mandatory Task</label>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editTaskModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add FAQ Modal -->
    <div id="addFaqModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add FAQ</h3>
                <button class="close" onclick="closeModal('addFaqModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_faq">
                    
                    <div class="form-group">
                        <label for="faq_question" class="form-label">Question</label>
                        <input type="text" name="question" id="faq_question" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="faq_answer" class="form-label">Answer</label>
                        <textarea name="answer" id="faq_answer" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="faq_category" class="form-label">Category</label>
                            <select name="category" id="faq_category" class="form-control form-select" required>
                                <option value="general">General</option>
                                <option value="hr_policy">HR Policy</option>
                                <option value="technical">Technical</option>
                                <option value="onboarding">Onboarding</option>
                                <option value="company">Company</option>
                                <option value="greeting">Greeting</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="faq_keywords" class="form-label">Keywords (comma-separated)</label>
                            <input type="text" name="keywords" id="faq_keywords" class="form-control" required>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addFaqModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add FAQ</button>
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
        
        function openTab(evt, tabName) {
            var i, tabcontent, tabbuttons;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tabbuttons = document.getElementsByClassName("tab-button");
            for (i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_department').value = user.department;
            document.getElementById('edit_status').value = user.status;
            openModal('editUserModal');
        }
        
        function editJob(job) {
            document.getElementById('edit_job_id').value = job.id;
            document.getElementById('edit_job_title').value = job.title;
            document.getElementById('edit_job_department').value = job.department;
            document.getElementById('edit_job_description').value = job.description;
            document.getElementById('edit_job_skills').value = job.required_skills;
            document.getElementById('edit_job_experience').value = job.experience_level;
            document.getElementById('edit_job_status').value = job.status;
            openModal('editJobModal');
        }
        
        function editTraining(module) {
            document.getElementById('edit_module_id').value = module.id;
            document.getElementById('edit_training_name').value = module.module_name;
            document.getElementById('edit_training_description').value = module.description || '';
            document.getElementById('edit_training_url').value = module.content_url || '';
            document.getElementById('edit_training_duration').value = module.duration_hours;
            document.getElementById('edit_training_dept').value = module.department;
            document.getElementById('edit_training_mandatory').checked = module.is_mandatory == 1;
            openModal('editTrainingModal');
        }
        
        function editTask(task) {
            document.getElementById('edit_task_id').value = task.id;
            document.getElementById('edit_task_name').value = task.task_name;
            document.getElementById('edit_task_description').value = task.description || '';
            document.getElementById('edit_task_dept').value = task.department;
            document.getElementById('edit_task_order').value = task.order_sequence;
            document.getElementById('edit_task_mandatory').checked = task.is_mandatory == 1;
            openModal('editTaskModal');
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to deactivate this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="' + userId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteJob(jobId) {
            if (confirm('Are you sure you want to close this job position?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_job_position"><input type="hidden" name="job_id" value="' + jobId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
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