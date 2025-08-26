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

// Define categories to hide from employee view
$hidden_categories = ['general', 'greeting', 'company'];

// Create the WHERE clause to exclude hidden categories
$category_placeholders = str_repeat('?,', count($hidden_categories) - 1) . '?';

try {
    $stmt = $connection->prepare("
        SELECT 
            id,
            question,
            answer,
            category,
            keywords
        FROM chatbot_faq 
        WHERE is_active = 1 
        AND category NOT IN ($category_placeholders)
        ORDER BY category, question
    ");
    
    // Bind the hidden categories as parameters
    $stmt->bind_param(str_repeat('s', count($hidden_categories)), ...$hidden_categories);
    $stmt->execute();
    $faqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Group FAQs by category
    $faq_categories = [];
    foreach ($faqs as $faq) {
        $category = $faq['category'] ?: 'general';
        if (!isset($faq_categories[$category])) {
            $faq_categories[$category] = [];
        }
        $faq_categories[$category][] = $faq;
    }
    
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $faqs = [];
    $faq_categories = [];
}

// Create support_tickets table if it doesn't exist (for demo purposes)
$connection->query("
    CREATE TABLE IF NOT EXISTS support_tickets (
        id int(11) NOT NULL AUTO_INCREMENT,
        employee_id int(11) NOT NULL,
        subject varchar(255) NOT NULL,
        category varchar(100) DEFAULT 'general',
        priority enum('low','medium','high','urgent') DEFAULT 'medium',
        status enum('open','in_progress','resolved','closed') DEFAULT 'open',
        description text NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at timestamp NULL DEFAULT NULL,
        PRIMARY KEY (id),
        KEY employee_id (employee_id)
    )
");

// Handle support ticket submission
if ($_POST && isset($_POST['submit_ticket'])) {
    $subject = $_POST['subject'];
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $description = $_POST['description'];
    
    try {
        $stmt = $connection->prepare("
            INSERT INTO support_tickets (employee_id, subject, category, priority, description) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $user_id, $subject, $category, $priority, $description);
        
        if ($stmt->execute()) {
            $success = "Support ticket submitted successfully! You will receive a response within 24 hours.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
        }
    } catch(Exception $e) {
        $error = "Failed to submit ticket: " . $e->getMessage();
    }
}

// Get employee's support tickets
try {
    $stmt = $connection->query("
        SELECT 
            id,
            subject,
            category,
            priority,
            status,
            description,
            created_at,
            updated_at,
            resolved_at
        FROM support_tickets 
        WHERE employee_id = $user_id 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $tickets = $stmt->fetch_all(MYSQLI_ASSOC);
} catch(Exception $e) {
    $tickets = [];
}

if (isset($_GET['success'])) {
    $success = "Support ticket submitted successfully! You will receive a response within 24 hours.";
}

// Category display names
$category_names = [
    'onboarding' => 'Onboarding',
    'hr_policy' => 'HR Policy',
    'technical' => 'Technical Support',
    'general' => 'General'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Help - Vector HR System</title>
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
        
        /* Sidebar Styles - Same as other pages */
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
        
        /* Hero Section */
        .hero-section {
            background: var(--kabel-gradient);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .hero-section h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .action-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .action-description {
            color: #6c757d;
            margin-bottom: 1.5rem;
            line-height: 1.5;
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
        
        /* FAQ Sections */
        .faq-category {
            margin-bottom: 2rem;
        }
        
        .category-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .faq-item {
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-left: 4px solid var(--primary-color);
        }
        
        .faq-question:hover {
            background: #f8f9fa;
        }
        
        .faq-question.active {
            background: rgba(255, 107, 53, 0.05);
        }
        
        .faq-text {
            font-weight: 600;
            color: var(--secondary-color);
            flex: 1;
        }
        
        .faq-toggle {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }
        
        .faq-question.active .faq-toggle {
            transform: rotate(45deg);
        }
        
        .faq-answer {
            padding: 0 1.5rem;
            background: #f8f9fa;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-answer.active {
            padding: 1.5rem;
            max-height: 200px;
        }
        
        .faq-answer p {
            color: #6c757d;
            line-height: 1.6;
            margin: 0;
        }
        
        /* Support Tickets */
        .ticket-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .ticket-item.open {
            border-left-color: var(--warning-color);
        }
        
        .ticket-item.in_progress {
            border-left-color: var(--info-color);
        }
        
        .ticket-item.resolved,
        .ticket-item.closed {
            border-left-color: var(--success-color);
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .ticket-title {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .ticket-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .ticket-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .ticket-status.open {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .ticket-status.in_progress {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        
        .ticket-status.resolved,
        .ticket-status.closed {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        /* Contact Cards */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .contact-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }
        
        .contact-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .contact-title {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .contact-info {
            color: #6c757d;
            margin-bottom: 1rem;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
            max-width: 600px;
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
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .contact-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .hero-section h2 {
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
            <h1 class="page-title">❓ Get Help</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Need assistance? We're here to help!
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

            <!-- Hero Section -->
            <div class="hero-section">
                <h2>🤝 We're Here to Help!</h2>
                <p>
                    Having trouble with your onboarding? Need clarification on a policy? 
                    Our support team is ready to assist you every step of the way.
                </p>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-card">
                    <div class="action-icon">🎫</div>
                    <div class="action-title">Submit Support Ticket</div>
                    <div class="action-description">
                        Get personalized help from our support team for any issues or questions.
                    </div>
                    <button class="btn btn-primary" onclick="openTicketModal()">
                        Create Ticket
                    </button>
                </div>
                
                <div class="action-card">
                    <div class="action-icon">💬</div>
                    <div class="action-title">Live Chat Support</div>
                    <div class="action-description">
                        Chat with our support team for immediate assistance during business hours.
                    </div>
                    <button class="btn btn-outline" onclick="openChatbot()">
                        Start Chat
                    </button>
                </div>
                
                <div class="action-card">
                    <div class="action-icon">📞</div>
                    <div class="action-title">Call HR Department</div>
                    <div class="action-description">
                        Speak directly with our HR team for urgent matters or complex questions.
                    </div>
                    <a href="tel:+60123456789" class="btn btn-outline">
                        Call Now
                    </a>
                </div>
            </div>

            <!-- My Support Tickets -->
            <?php if (!empty($tickets)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="icon">🎫</span>
                    <div>
                        <h3>My Recent Support Tickets</h3>
                        <p style="margin: 0; opacity: 0.9;">Track your support requests</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="ticket-item <?php echo $ticket['status']; ?>">
                            <div class="ticket-header">
                                <div>
                                    <div class="ticket-title">
                                        #<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </div>
                                    <div class="ticket-meta">
                                        Category: <?php echo ucfirst($ticket['category']); ?> • 
                                        Priority: <?php echo ucfirst($ticket['priority']); ?> • 
                                        Created: <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="ticket-status <?php echo $ticket['status']; ?>">
                                    <?php echo str_replace('_', ' ', $ticket['status']); ?>
                                </div>
                            </div>
                            <div style="color: #6c757d; font-size: 0.95rem;">
                                <?php echo htmlspecialchars(substr($ticket['description'], 0, 150)) . (strlen($ticket['description']) > 150 ? '...' : ''); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- FAQ Section -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">❓</span>
                    <div>
                        <h3>Frequently Asked Questions</h3>
                        <p style="margin: 0; opacity: 0.9;">Find quick answers to common questions</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($faq_categories as $category => $category_faqs): ?>
                        <div class="faq-category">
                            <div class="category-title">
                                <?php echo $category_names[$category] ?? ucfirst($category); ?>
                            </div>
                            <?php foreach ($category_faqs as $faq): ?>
                                <div class="faq-item">
                                    <div class="faq-question" onclick="toggleFaq(this)">
                                        <span class="faq-text"><?php echo htmlspecialchars($faq['question']); ?></span>
                                        <span class="faq-toggle">+</span>
                                    </div>
                                    <div class="faq-answer">
                                        <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">📧</span>
                    <div>
                        <h3>Contact Information</h3>
                        <p style="margin: 0; opacity: 0.9;">Get in touch with us directly</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="contact-grid">
                        <div class="contact-card">
                            <div class="contact-icon">👥</div>
                            <div class="contact-title">HR Department</div>
                            <div class="contact-info">
                                Email: hr@haircare2u.my<br>
                                Phone: +60 12-345 6789<br>
                                Hours: 9 AM - 6 PM (Mon-Fri)
                            </div>
                            <a href="mailto:hr@haircare2u.my" class="btn btn-outline btn-sm">
                                Send Email
                            </a>
                        </div>
                        
                        <div class="contact-card">
                            <div class="contact-icon">🔧</div>
                            <div class="contact-title">IT Support</div>
                            <div class="contact-info">
                                Email: it@haircare2u.my<br>
                                Phone: +60 12-345 6790<br>
                                Hours: 24/7 Support Available
                            </div>
                            <a href="mailto:it@haircare2u.my" class="btn btn-outline btn-sm">
                                Get IT Help
                            </a>
                        </div>
                        
                        <div class="contact-card">
                            <div class="contact-icon">🏢</div>
                            <div class="contact-title">Office Location</div>
                            <div class="contact-info">
                                HairCare2U Headquarters<br>
                                Kuala Lumpur, Malaysia<br>
                                Visit us during business hours
                            </div>
                            <button class="btn btn-outline btn-sm" onclick="alert('Office directions will be provided via email.')">
                                Get Directions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Ticket Modal -->
    <div class="modal" id="ticketModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Submit Support Ticket</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="ticketForm">
                <div class="form-group">
                    <label for="subject" class="form-label">Subject *</label>
                    <input type="text" name="subject" id="subject" class="form-control" 
                           placeholder="Brief description of your issue" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category" class="form-label">Category *</label>
                        <select name="category" id="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="onboarding">Onboarding</option>
                            <option value="hr_policy">HR Policy</option>
                            <option value="technical">Technical Support</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority" class="form-label">Priority *</label>
                        <select name="priority" id="priority" class="form-control" required>
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description *</label>
                    <textarea name="description" id="description" class="form-control" rows="5" 
                              placeholder="Please provide detailed information about your issue or question..." required></textarea>
                </div>
                
                <input type="hidden" name="submit_ticket" value="1">
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn" onclick="closeModal()" 
                            style="background: #6c757d; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-primary">🎫 Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        function toggleFaq(element) {
            const faqAnswer = element.nextElementSibling;
            const isActive = element.classList.contains('active');
            
            // Close all other FAQs
            document.querySelectorAll('.faq-question.active').forEach(function(activeElement) {
                if (activeElement !== element) {
                    activeElement.classList.remove('active');
                    activeElement.nextElementSibling.classList.remove('active');
                }
            });
            
            // Toggle current FAQ
            if (isActive) {
                element.classList.remove('active');
                faqAnswer.classList.remove('active');
            } else {
                element.classList.add('active');
                faqAnswer.classList.add('active');
            }
        }
        
        function openTicketModal() {
            document.getElementById('ticketModal').classList.add('show');
            document.getElementById('subject').focus();
        }
        
        function closeModal() {
            document.getElementById('ticketModal').classList.remove('show');
        }
        
        function openChatbot() {
            // Implement chatbot functionality here
            alert('Live chat will be available soon! Please submit a support ticket or contact HR directly for immediate assistance.');
        }
        
        // Close modal when clicking outside
        document.getElementById('ticketModal').addEventListener('click', function(e) {
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
        
        // Form validation and submission
        document.getElementById('ticketForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Submitting...';
            
            // Re-enable after 5 seconds if form doesn't redirect
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 5000);
        });
        
        // Search functionality for FAQs
        function searchFAQs() {
            const searchTerm = document.getElementById('faqSearch').value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(function(item) {
                const question = item.querySelector('.faq-text').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer p').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>