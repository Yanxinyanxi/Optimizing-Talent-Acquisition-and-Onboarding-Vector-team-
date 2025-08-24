<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Handle contact form submission
$contact_success = false;
$contact_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_inquiry'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($subject) || empty($message)) {
        $contact_error = "Please fill in all required fields.";
    } else {
        // Here you would typically save to database or send email
        // For now, we'll simulate success
        $contact_success = true;
        
        // In a real implementation, you might do:
        /*
        try {
            $stmt = $connection->prepare("INSERT INTO support_tickets (user_id, subject, message, priority, status, created_at) VALUES (?, ?, ?, ?, 'open', NOW())");
            $stmt->bind_param("isss", $_SESSION['user_id'], $subject, $message, $priority);
            
            if ($stmt->execute()) {
                $contact_success = true;
                
                // Send notification email to support team
                $to = SUPPORT_EMAIL;
                $email_subject = "New Support Ticket: " . $subject;
                $email_message = "New support ticket from: " . $_SESSION['full_name'] . "\n\n" . $message;
                mail($to, $email_subject, $email_message);
                
            } else {
                $contact_error = "Failed to submit your inquiry. Please try again.";
            }
        } catch (Exception $e) {
            $contact_error = "An error occurred. Please try again later.";
            error_log("Support ticket error: " . $e->getMessage());
        }
        */
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vector - Help & Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #2B4C8C;
            --kabel-gradient: linear-gradient(135deg, #FF6B35 0%, #2B4C8C 100%);
            --border-radius: 12px;
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
            padding: 2rem 0;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            z-index: 1000;
        }
        
        .sidebar-brand {
            text-align: center;
            padding: 0 2rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 2rem;
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
            position: absolute;
            bottom: 2rem;
            left: 2rem;
            right: 2rem;
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
        
        /* FAQ Section */
        .faq-item {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .faq-question {
            background: rgba(43, 76, 140, 0.05);
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--secondary-color);
            transition: all 0.3s ease;
        }
        
        .faq-question:hover {
            background: rgba(43, 76, 140, 0.1);
        }
        
        .faq-question.active {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary-color);
        }
        
        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .faq-answer.show {
            padding: 1.5rem;
            max-height: 500px;
        }
        
        .faq-toggle {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }
        
        .faq-toggle.rotate {
            transform: rotate(180deg);
        }
        
        /* Contact Form */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 3rem;
        }
        
        /* Buttons */
        .btn {
            padding: 1rem 2rem;
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
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-color: #28a745;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-color: #dc3545;
        }
        
        .alert-info {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border-color: #17a2b8;
        }
        
        /* Info Sections */
        .info-section {
            background: rgba(43, 76, 140, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid var(--secondary-color);
        }
        
        .info-section h4 {
            color: var(--secondary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        
        /* Contact Info Grid */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .contact-item {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .contact-item:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .contact-item .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .contact-item h5 {
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .contact-item p {
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .contact-item a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .contact-item a:hover {
            text-decoration: underline;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .quick-action {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action .icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--primary-color);
        }
        
        .quick-action h6 {
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .quick-action p {
            color: #6c757d;
            font-size: 0.9rem;
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
            
            .content-area {
                padding: 1rem;
            }
            
            .contact-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
        
        .mobile-toggle {
            display: none;
        }
        
        /* Haircare specific styling */
        .haircare-highlight {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.1) 0%, rgba(43, 76, 140, 0.1) 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-left: 4px solid var(--primary-color);
        }
        
        .haircare-highlight h5 {
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .haircare-highlight p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo">V</div>
            <h3>Vector</h3>
            <p>Candidate Portal</p>
        </div>
        
        <ul class="sidebar-nav">
            <li>
                <a href="candidate-dashboard.php">
                    <span class="icon">📄</span>
                    <span>Upload Resume</span>
                </a>
            </li>
            <li>
                <a href="candidate-applications.php">
                    <span class="icon">📝</span>
                    <span>My Applications</span>
                </a>
            </li>
            <li>
                <a href="candidate-profile.php">
                    <span class="icon">👤</span>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="candidate-help.php" class="active">
                    <span class="icon">❓</span>
                    <span>Help & Support</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="avatar"><?php echo substr($_SESSION['full_name'], 0, 1); ?></div>
                <div style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
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
            <h1 class="page-title">❓ Help & Support Center</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
        
        <div class="content-area">
            <!-- Welcome Section -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🌟</span>
                    <div>
                        <h3>Welcome to HairCare2U Career Support</h3>
                        <p style="margin: 0; opacity: 0.9;">Your gateway to joining Malaysia's premier hair care industry</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="haircare-highlight">
                        <h5>💼 Join the HairCare2U Family</h5>
                        <p>At HairCare2U, we're passionate about providing exceptional hair care products and services across Malaysia. We're always looking for talented individuals who share our commitment to beauty, quality, and customer satisfaction. Whether you're interested in sales, marketing, customer service, or operations, we have opportunities for you to grow with us.</p>
                    </div>
                    
                    <div class="quick-actions">
                        <a href="candidate-dashboard.php" class="quick-action">
                            <div class="icon">📤</div>
                            <h6>Upload Resume</h6>
                            <p>Submit your application for available positions</p>
                        </a>
                        <a href="candidate-applications.php" class="quick-action">
                            <div class="icon">📋</div>
                            <h6>Track Applications</h6>
                            <p>Monitor your application status and progress</p>
                        </a>
                        <a href="candidate-profile.php" class="quick-action">
                            <div class="icon">👤</div>
                            <h6>Update Profile</h6>
                            <p>Keep your information current and complete</p>
                        </a>
                        <a href="#contact" class="quick-action">
                            <div class="icon">💬</div>
                            <h6>Contact Support</h6>
                            <p>Get help with any questions or issues</p>
                        </a>
                    </div>
                </div>
            </div>
            
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
                    <div class="faq-list">
                        <!-- FAQ Item 1 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>🏢 What career opportunities are available at HairCare2U?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>HairCare2U offers diverse career opportunities across multiple departments including:</p>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li><strong>Sales & Marketing:</strong> Product specialists, digital marketers, brand ambassadors</li>
                                    <li><strong>Customer Service:</strong> Support representatives, beauty consultants</li>
                                    <li><strong>Operations:</strong> Logistics, inventory management, quality control</li>
                                    <li><strong>IT & Digital:</strong> E-commerce developers, data analysts</li>
                                    <li><strong>Management:</strong> Team leaders, department heads, regional managers</li>
                                </ul>
                                <p>We regularly update our job postings, so check back frequently for new opportunities!</p>
                            </div>
                        </div>
                        
                        <!-- FAQ Item 2 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>📄 How do I upload my resume and apply for jobs?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>Applying is easy with our AI-powered system:</p>
                                <ol style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li>Go to the <strong>Upload Resume</strong> page</li>
                                    <li>Select the job position you're interested in</li>
                                    <li>Upload your resume (PDF, DOC, or DOCX format, max 5MB)</li>
                                    <li>Our AI will automatically parse your resume and extract key information</li>
                                    <li>Review the extracted data and submit your application</li>
                                </ol>
                                <p>You can apply for multiple positions, but each requires a separate application.</p>
                            </div>
                        </div>
                        
                        <!-- FAQ Item 3 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>🤖 How does the AI resume parsing work?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>Our advanced AI technology automatically extracts important information from your resume including:</p>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li>Personal information (name, contact details, location)</li>
                                    <li>Work experience and job titles</li>
                                    <li>Education background and qualifications</li>
                                    <li>Skills and competencies</li>
                                    <li>Certifications and languages</li>
                                </ul>
                                <p>This helps us match you with suitable positions more effectively and speeds up the application process.</p>
                            </div>
                        </div>
                        
                        <!-- FAQ Item 4 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>⏰ How long does the application process take?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>Our hiring process timeline typically follows these stages:</p>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li><strong>Application Review:</strong> 3-5 business days</li>
                                    <li><strong>Initial Screening:</strong> 1-2 weeks (if shortlisted)</li>
                                    <li><strong>Interview Process:</strong> 1-3 weeks (depending on position level)</li>
                                    <li><strong>Final Decision:</strong> 1 week after final interview</li>
                                </ul>
                                <p>You'll receive email updates at each stage, and you can track your progress in the "My Applications" section.</p>
                            </div>
                        </div>
                        
                        <!-- FAQ Item 5 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>📧 How will I be notified about my application status?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>We keep you informed through multiple channels:</p>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li><strong>Email notifications</strong> for major status changes</li>
                                    <li><strong>In-app notifications</strong> when you log into the portal</li>
                                    <li><strong>Real-time status updates</strong> in the "My Applications" section</li>
                                </ul>
                                <p>Make sure to check your email regularly and whitelist our domain to avoid missing important updates.</p>
                            </div>
                        </div>
                        
                        <!-- FAQ Item 6 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>🔧 What should I do if I encounter technical issues?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>If you experience any technical difficulties:</p>
                                <ol style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li>Try refreshing the page and clearing your browser cache</li>
                                    <li>Ensure you're using a supported browser (Chrome, Firefox, Safari, Edge)</li>
                                    <li>Check that your file meets our requirements (PDF/DOC/DOCX, max 5MB)</li>
                                    <li>Try using a different device or internet connection</li>
                                    <li>If issues persist, contact our support team using the form below</li>
                                </ol>
                                <p>Include screenshots and error messages when reporting technical issues for faster resolution.</p>
                            </div>
                        </div>
                        
                        <!-- FAQ Item 7 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>💰 What benefits does HairCare2U offer employees?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>HairCare2U offers comprehensive benefits to support our team members:</p>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li><strong>Health & Wellness:</strong> Medical insurance, dental coverage, wellness programs</li>
                                    <li><strong>Professional Development:</strong> Training programs, skills workshops, career advancement opportunities</li>
                                    <li><strong>Work-Life Balance:</strong> Flexible working hours, annual leave, public holidays</li>
                                    <li><strong>Employee Discounts:</strong> Special pricing on all HairCare2U products</li>
                                    <li><strong>Performance Rewards:</strong> Bonuses, recognition programs, performance incentives</li>
                                    <li><strong>Team Building:</strong> Company events, team outings, social activities</li>
                                </ul>
                                <p>Specific benefits may vary depending on your position and employment terms.</p>
                                </div>
                        </div>
                        
                        <!-- FAQ Item 8 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>🏢 Can I visit HairCare2U offices or stores?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>Yes! We welcome prospective employees to learn more about our company culture:</p>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li><strong>Store Visits:</strong> Visit any of our retail locations to see our products and talk to staff</li>
                                    <li><strong>Office Tours:</strong> Schedule a visit to our headquarters (appointment required)</li>
                                    <li><strong>Career Events:</strong> Attend our job fairs and recruitment events</li>
                                    <li><strong>Virtual Tours:</strong> Request a virtual office tour via video call</li>
                                </ul>
                                <p>Contact us to arrange a visit or check our website for upcoming career events and open houses.</p>
                            </div>
                        </div>
                        
                        <!-- FAQ Item 9 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>🔄 Can I update my application after submission?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>While you cannot modify applications after submission, you have these options:</p>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li><strong>Update Profile:</strong> Keep your personal information current in the profile section</li>
                                    <li><strong>New Applications:</strong> Submit fresh applications for other positions with updated information</li>
                                    <li><strong>Contact HR:</strong> Reach out if you need to provide additional important information</li>
                                    <li><strong>Reapply:</strong> If your application is unsuccessful, you can reapply after 6 months</li>
                                </ul>
                                <p>We recommend reviewing all information carefully before submitting your application.</p>
                            </div>
                        </div>
                        
                        <!-- FAQ Item 10 -->
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <span>📱 Is there a mobile app for job applications?</span>
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer">
                                <p>Currently, our career portal is web-based and optimized for all devices:</p>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li><strong>Mobile Responsive:</strong> Full functionality on smartphones and tablets</li>
                                    <li><strong>Cross-Browser:</strong> Works on all major browsers (Chrome, Safari, Firefox)</li>
                                    <li><strong>Easy Access:</strong> Bookmark our career portal for quick access</li>
                                    <li><strong>Future Plans:</strong> Mobile app development is being considered for future releases</li>
                                </ul>
                                <p>You can access all features from any device with an internet connection.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Support Section -->
            <div class="card" id="contact">
                <div class="card-header">
                    <span class="icon">📞</span>
                    <div>
                        <h3>Contact Our Support Team</h3>
                        <p style="margin: 0; opacity: 0.9;">Get personalized help from our HR and recruitment team</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($contact_success): ?>
                        <div class="alert alert-success">
                            <strong>✅ Message Sent Successfully!</strong> 
                            Thank you for contacting us. Our support team will review your inquiry and respond within 24-48 hours. You should receive a confirmation email shortly.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($contact_error): ?>
                        <div class="alert alert-danger">
                            <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($contact_error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <!-- Contact Form -->
                        <div>
                            <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">📝 Send us a Message</h4>
                            <form method="POST" action="#contact">
                                <div class="form-group">
                                    <label for="subject">Subject *</label>
                                    <select name="subject" id="subject" class="form-control form-select" required>
                                        <option value="">Select a topic...</option>
                                        <option value="Application Status">❓ Application Status Inquiry</option>
                                        <option value="Technical Issue">🔧 Technical Issue/Bug Report</option>
                                        <option value="Job Information">💼 Job Position Information</option>
                                        <option value="Interview Process">📅 Interview Process Questions</option>
                                        <option value="Account Issues">👤 Account/Login Issues</option>
                                        <option value="General Inquiry">💬 General Inquiry</option>
                                        <option value="Feedback">⭐ Feedback/Suggestions</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="priority">Priority Level</label>
                                    <select name="priority" id="priority" class="form-control form-select">
                                        <option value="low">🟢 Low - General inquiry</option>
                                        <option value="medium" selected>🟡 Medium - Standard support</option>
                                        <option value="high">🔴 High - Urgent assistance needed</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message">Your Message *</label>
                                    <textarea name="message" id="message" class="form-control" rows="6" required 
                                              placeholder="Please provide detailed information about your inquiry, including any error messages, steps you've taken, or specific questions you have..."></textarea>
                                </div>
                                
                                <button type="submit" name="submit_inquiry" class="btn btn-primary">
                                    📤 Send Message
                                </button>
                            </form>
                        </div>
                        
                        <!-- Contact Information -->
                        <div>
                            <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">📞 Other Ways to Reach Us</h4>
                            
                            <div class="contact-grid">
                                <div class="contact-item">
                                    <div class="icon">📧</div>
                                    <h5>Email Support</h5>
                                    <p>For general inquiries</p>
                                    <a href="mailto:careers@haircare2u.my">careers@haircare2u.my</a>
                                </div>
                                
                                <div class="contact-item">
                                    <div class="icon">📱</div>
                                    <h5>WhatsApp</h5>
                                    <p>Quick questions</p>
                                    <a href="https://wa.me/60123456789" target="_blank">+60 12-345 6789</a>
                                </div>
                                
                                <div class="contact-item">
                                    <div class="icon">📞</div>
                                    <h5>Phone Support</h5>
                                    <p>Mon-Fri, 9AM-6PM</p>
                                    <a href="tel:+60387654321">+60 3-8765 4321</a>
                                </div>
                                
                                <div class="contact-item">
                                    <div class="icon">🏢</div>
                                    <h5>Visit Our Office</h5>
                                    <p>By appointment only</p>
                                    <p style="font-size: 0.85rem; margin: 0;">Kuala Lumpur, Malaysia</p>
                                </div>
                            </div>
                            
                            <div class="info-section" style="margin-top: 1.5rem;">
                                <h4>⏰ Support Hours</h4>
                                <p><strong>Monday - Friday:</strong> 9:00 AM - 6:00 PM (MYT)</p>
                                <p><strong>Saturday:</strong> 9:00 AM - 1:00 PM (MYT)</p>
                                <p><strong>Sunday & Public Holidays:</strong> Closed</p>
                                <p style="margin-top: 1rem; font-size: 0.9rem; color: #6c757d;">
                                    <strong>Response Time:</strong> We aim to respond to all inquiries within 24-48 hours during business days.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Guide Section -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">📖</span>
                    <div>
                        <h3>User Guide & Tips</h3>
                        <p style="margin: 0; opacity: 0.9;">Step-by-step instructions for using our career portal</p>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <!-- Getting Started -->
                        <div class="info-section">
                            <h4>🚀 Getting Started</h4>
                            <ol style="padding-left: 1.5rem; line-height: 1.6;">
                                <li>Create your account and complete your profile</li>
                                <li>Browse available job positions</li>
                                <li>Prepare your resume in PDF, DOC, or DOCX format</li>
                                <li>Select a position and upload your resume</li>
                                <li>Review the AI-extracted information</li>
                                <li>Submit your application</li>
                                <li>Track your application status</li>
                            </ol>
                        </div>
                        
                        <!-- Resume Tips -->
                        <div class="info-section">
                            <h4>📄 Resume Tips</h4>
                            <ul style="padding-left: 1.5rem; line-height: 1.6;">
                                <li><strong>Format:</strong> Use PDF for best results with our AI parser</li>
                                <li><strong>Structure:</strong> Include clear sections for experience, education, skills</li>
                                <li><strong>Contact Info:</strong> Ensure phone and email are current</li>
                                <li><strong>Keywords:</strong> Include relevant skills mentioned in job descriptions</li>
                                <li><strong>Length:</strong> Keep it concise (1-2 pages for most positions)</li>
                                <li><strong>Update:</strong> Use your most recent and relevant experience</li>
                            </ul>
                        </div>
                        
                        <!-- Application Tips -->
                        <div class="info-section">
                            <h4>✅ Application Best Practices</h4>
                            <ul style="padding-left: 1.5rem; line-height: 1.6;">
                                <li><strong>Read Job Descriptions:</strong> Carefully review requirements and responsibilities</li>
                                <li><strong>Tailor Applications:</strong> Customize your approach for different positions</li>
                                <li><strong>Follow Up:</strong> Monitor your application status regularly</li>
                                <li><strong>Be Patient:</strong> Allow adequate time for the review process</li>
                                <li><strong>Stay Professional:</strong> Maintain professional communication</li>
                                <li><strong>Prepare for Interviews:</strong> Research the company and practice common questions</li>
                            </ul>
                        </div>
                        
                        <!-- Technical Requirements -->
                        <div class="info-section">
                            <h4>💻 Technical Requirements</h4>
                            <ul style="padding-left: 1.5rem; line-height: 1.6;">
                                <li><strong>Browsers:</strong> Chrome, Firefox, Safari, Edge (latest versions)</li>
                                <li><strong>File Size:</strong> Maximum 5MB for resume uploads</li>
                                <li><strong>File Types:</strong> PDF, DOC, DOCX formats supported</li>
                                <li><strong>Internet:</strong> Stable internet connection required</li>
                                <li><strong>JavaScript:</strong> Enable JavaScript for full functionality</li>
                                <li><strong>Cookies:</strong> Allow cookies to save your session</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Company Information -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">🏢</span>
                    <div>
                        <h3>About HairCare2U</h3>
                        <p style="margin: 0; opacity: 0.9;">Learn more about our company and culture</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="haircare-highlight">
                        <h5>🌟 Our Mission</h5>
                        <p>HairCare2U is dedicated to providing high-quality hair care solutions to customers across Malaysia. We combine innovative products with exceptional service to help everyone achieve their hair care goals. Our commitment to excellence extends to our workplace culture, where we foster growth, creativity, and teamwork.</p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
                        <div class="info-section">
                            <h4>🎯 Our Values</h4>
                            <ul style="padding-left: 1.5rem; line-height: 1.6;">
                                <li><strong>Quality First:</strong> Excellence in every product and service</li>
                                <li><strong>Customer Focus:</strong> Understanding and exceeding expectations</li>
                                <li><strong>Innovation:</strong> Embracing new technologies and methods</li>
                                <li><strong>Integrity:</strong> Honest and ethical business practices</li>
                                <li><strong>Teamwork:</strong> Collaboration and mutual support</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h4>🏆 Why Join Us</h4>
                            <ul style="padding-left: 1.5rem; line-height: 1.6;">
                                <li><strong>Growth Opportunities:</strong> Clear career progression paths</li>
                                <li><strong>Training & Development:</strong> Continuous learning programs</li>
                                <li><strong>Inclusive Culture:</strong> Diverse and welcoming workplace</li>
                                <li><strong>Work-Life Balance:</strong> Flexible arrangements when possible</li>
                                <li><strong>Recognition:</strong> Performance-based rewards and recognition</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h4>📍 Our Presence</h4>
                            <ul style="padding-left: 1.5rem; line-height: 1.6;">
                                <li><strong>Headquarters:</strong> Kuala Lumpur, Malaysia</li>
                                <li><strong>Retail Stores:</strong> Multiple locations nationwide</li>
                                <li><strong>Online Platform:</strong> Comprehensive e-commerce website</li>
                                <li><strong>Service Centers:</strong> Customer support locations</li>
                                <li><strong>Partners:</strong> Authorized dealers and distributors</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; text-align: center;">
                        <a href="https://www.haircare2u.my" target="_blank" class="btn btn-outline" style="margin-right: 1rem;">
                            🌐 Visit Our Website
                        </a>
                        <a href="candidate-dashboard.php" class="btn btn-primary">
                            💼 Browse Job Openings
                        </a>
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
        
        // FAQ Toggle functionality
        function toggleFaq(element) {
            const answer = element.nextElementSibling;
            const toggle = element.querySelector('.faq-toggle');
            const isActive = element.classList.contains('active');
            
            // Close all other FAQs
            document.querySelectorAll('.faq-question').forEach(q => {
                q.classList.remove('active');
                q.nextElementSibling.classList.remove('show');
                q.querySelector('.faq-toggle').classList.remove('rotate');
            });
            
            // Toggle current FAQ
            if (!isActive) {
                element.classList.add('active');
                answer.classList.add('show');
                toggle.classList.add('rotate');
            }
        }
        
        // Smooth scroll to contact form
        function scrollToContact() {
            document.getElementById('contact').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        // Form validation
        document.getElementById('subject').addEventListener('change', function() {
            const messageTextarea = document.getElementById('message');
            const subject = this.value;
            
            // Auto-populate message placeholder based on subject
            const placeholders = {
                'Application Status': 'Please provide your application reference number or the position you applied for, and specify what information you need about your application status...',
                'Technical Issue': 'Please describe the technical issue you encountered, including any error messages, the browser you\'re using, and the steps that led to the problem...',
                'Job Information': 'Please specify which job position you\'re interested in and what additional information you need (responsibilities, requirements, benefits, etc.)...',
                'Interview Process': 'Please let us know what stage of the interview process you\'re at and what questions you have about the next steps...',
                'Account Issues': 'Please describe the login or account issue you\'re experiencing, including any error messages you see...',
                'General Inquiry': 'Please provide details about your inquiry...',
                'Feedback': 'We value your feedback! Please share your suggestions or comments about our career portal or application process...'
            };
            
            if (placeholders[subject]) {
                messageTextarea.placeholder = placeholders[subject];
            }
        });
        
        // Character counter for message textarea
        const messageTextarea = document.getElementById('message');
        const characterLimit = 2000;
        
        // Create character counter
        const counterDiv = document.createElement('div');
        counterDiv.style.cssText = 'font-size: 0.85rem; color: #6c757d; text-align: right; margin-top: 0.5rem;';
        messageTextarea.parentNode.appendChild(counterDiv);
        
        messageTextarea.addEventListener('input', function() {
            const remaining = characterLimit - this.value.length;
            counterDiv.textContent = `${this.value.length}/${characterLimit} characters`;
            
            if (remaining < 100) {
                counterDiv.style.color = '#dc3545';
            } else if (remaining < 200) {
                counterDiv.style.color = '#ffc107';
            } else {
                counterDiv.style.color = '#6c757d';
            }
            
            if (this.value.length > characterLimit) {
                this.value = this.value.substring(0, characterLimit);
            }
        });
        
        // Update initial character count
        messageTextarea.dispatchEvent(new Event('input'));
        
        // Auto-hide success/error messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 500);
            });
        }, 5000);
        
        // Add loading state to form submission
        const contactForm = document.querySelector('form');
        if (contactForm) {
            contactForm.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                submitBtn.innerHTML = '⏳ Sending Message...';
                submitBtn.disabled = true;
                
                // Re-enable button after 3 seconds (in case of errors)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });
        }
        
        // Initialize tooltips for better user experience
        function addTooltips() {
            const tooltipElements = [
                { selector: '.btn-primary', text: 'Click to proceed' },
                { selector: '.faq-question', text: 'Click to expand/collapse' },
                { selector: '.contact-item a', text: 'Click to contact us' }
            ];
            
            tooltipElements.forEach(item => {
                document.querySelectorAll(item.selector).forEach(el => {
                    el.title = item.text;
                });
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            addTooltips();
        });
    </script>
</body>
</html>