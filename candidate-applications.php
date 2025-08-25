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

// Check database connection
if (!isset($connection) || $connection->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

// Fetch user's applications with job details
$applications = [];
try {
    $stmt = $connection->prepare("
        SELECT 
            a.id,
            a.status,
            a.applied_at,
            a.updated_at,
            a.hr_notes,
            a.match_percentage,
            a.resume_filename,
            jp.title as job_title,
            jp.department,
            jp.description as job_description,
            jp.required_skills,
            jp.experience_level,
            u.full_name as hr_name
        FROM applications a
        JOIN job_positions jp ON a.job_position_id = jp.id
        LEFT JOIN users u ON jp.created_by = u.id
        WHERE a.candidate_id = ?
        ORDER BY a.applied_at DESC
    ");
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching applications: " . $e->getMessage());
}

// Function to get status badge class
function getStatusBadge($status) {
    $badges = [
        'pending' => ['class' => 'status-pending', 'icon' => '⏳', 'text' => 'Under Review'],
        'selected' => ['class' => 'status-selected', 'icon' => '✅', 'text' => 'Selected'],
        'rejected' => ['class' => 'status-rejected', 'icon' => '❌', 'text' => 'Not Selected'],
        'waiting_interview' => ['class' => 'status-interview', 'icon' => '📅', 'text' => 'Interview Scheduled'],
        'interview_completed' => ['class' => 'status-interview-done', 'icon' => '✔️', 'text' => 'Interview Completed'],
        'offer_sent' => ['class' => 'status-offer', 'icon' => '🎉', 'text' => 'Offer Sent'],
        'offer_accepted' => ['class' => 'status-accepted', 'icon' => '🤝', 'text' => 'Offer Accepted'],
        'offer_rejected' => ['class' => 'status-offer-rejected', 'icon' => '🚫', 'text' => 'Offer Declined'],
        'hired' => ['class' => 'status-hired', 'icon' => '🎊', 'text' => 'Hired']
    ];
    
    return $badges[$status] ?? ['class' => 'status-pending', 'icon' => '❓', 'text' => ucfirst($status)];
}

// Function to get next steps
function getNextSteps($status) {
    $steps = [
        'pending' => 'HR is reviewing your application. You will be notified of any updates.',
        'selected' => 'Congratulations! HR will contact you soon for the next steps.',
        'rejected' => 'Thank you for your interest. Keep applying to other positions.',
        'waiting_interview' => 'Please prepare for your upcoming interview. Check your email for details.',
        'interview_completed' => 'Your interview is complete. Please wait for the final decision.',
        'offer_sent' => 'You have received a job offer! Please review and respond.',
        'offer_accepted' => 'Welcome to the team! HR will contact you with onboarding details.',
        'offer_rejected' => 'Thank you for considering our offer. Best wishes for your career.',
        'hired' => 'Welcome aboard! Your journey with us begins now.'
    ];
    
    return $steps[$status] ?? 'Please contact HR for more information about your application status.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vector - My Applications</title>
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
        
        /* Application Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Application Cards */
        .application-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .application-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .application-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, rgba(255,107,53,0.1) 0%, rgba(43,76,140,0.1) 100%);
            display: flex;
            justify-content: between;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .application-info {
            flex: 1;
        }
        
        .job-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .job-department {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .application-meta {
            display: flex;
            gap: 1rem;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .application-status {
            align-self: flex-start;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #e65100;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }
        
        .status-selected {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .status-interview {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border: 1px solid rgba(23, 162, 184, 0.2);
        }
        
        .status-interview-done {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
            border: 1px solid rgba(111, 66, 193, 0.2);
        }
        
        .status-offer {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(255, 107, 53, 0.2);
        }
        
        .status-accepted {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .status-offer-rejected {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.2);
        }
        
        .status-hired {
            background: var(--kabel-gradient);
            color: white;
            border: none;
        }
        
        .application-body {
            padding: 2rem;
        }
        
        .job-description {
            color: #555;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .required-skills {
            margin-bottom: 1.5rem;
        }
        
        .required-skills h4 {
            color: var(--secondary-color);
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        
        .skill-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(255, 107, 53, 0.2);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 0.25rem 0.25rem 0.25rem 0;
        }
        
        .match-score {
            background: rgba(43, 76, 140, 0.05);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 3px solid var(--secondary-color);
        }
        
        .match-score h4 {
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .match-percentage {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .next-steps {
            background: rgba(255, 107, 53, 0.05);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 3px solid var(--primary-color);
        }
        
        .next-steps h4 {
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .hr-notes {
            background: rgba(108, 117, 125, 0.05);
            padding: 1rem;
            border-radius: 12px;
            margin-top: 1rem;
            border-left: 3px solid #6c757d;
        }
        
        .hr-notes h5 {
            color: #6c757d;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .empty-text {
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .application-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .application-meta {
                flex-direction: column;
                gap: 0.5rem;
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
                <a href="candidate-applications.php" class="active">
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
                <a href="candidate-help.php">
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
            <h1 class="page-title">📝 My Applications</h1>
            <div class="topbar-actions">
                <a href="candidate-dashboard.php" class="btn btn-primary">
                    📤 Apply for New Job
                </a>
            </div>
        </div>
        
        <div class="content-area">
            <?php if (!empty($applications)): ?>
            <!-- Application Statistics -->
            <div class="stats-grid">
                <?php
                $stats = [
                    'total' => count($applications),
                    'pending' => 0,
                    'selected' => 0,
                    'rejected' => 0
                ];
                
                foreach ($applications as $app) {
                    if (in_array($app['status'], ['pending'])) $stats['pending']++;
                    if (in_array($app['status'], ['selected', 'waiting_interview', 'interview_completed', 'offer_sent', 'offer_accepted', 'hired'])) $stats['selected']++;
                    if (in_array($app['status'], ['rejected', 'offer_rejected'])) $stats['rejected']++;
                }
                ?>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                
                <div class="stat-card" style="border-left-color: #28a745;">
                    <div class="stat-number"><?php echo $stats['selected']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                
                <div class="stat-card" style="border-left-color: #dc3545;">
                    <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Closed</div>
                </div>
            </div>
            
            <!-- Applications List -->
            <?php foreach ($applications as $app): ?>
                <?php $status_info = getStatusBadge($app['status']); ?>
                <div class="application-card">
                    <div class="application-header">
                        <div class="application-info">
                            <div class="job-title"><?php echo htmlspecialchars($app['job_title']); ?></div>
                            <div class="job-department"><?php echo htmlspecialchars($app['department']); ?> Department</div>
                            <div class="application-meta">
                                <span>📅 Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?></span>
                                <span>📄 Resume: <?php echo htmlspecialchars($app['resume_filename']); ?></span>
                                <?php if ($app['updated_at'] != $app['applied_at']): ?>
                                <span>🔄 Updated: <?php echo date('M j, Y', strtotime($app['updated_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="application-status">
                            <div class="status-badge <?php echo $status_info['class']; ?>">
                                <span><?php echo $status_info['icon']; ?></span>
                                <span><?php echo $status_info['text']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="application-body">
                        <div class="job-description">
                            <?php echo htmlspecialchars($app['job_description']); ?>
                        </div>
                        
                        <div class="required-skills">
                            <h4>Required Skills</h4>
                            <?php 
                            $skills = explode(',', $app['required_skills']);
                            foreach ($skills as $skill): 
                            ?>
                            <span class="skill-tag"><?php echo trim(htmlspecialchars($skill)); ?></span>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($app['match_percentage'] > 0): ?>
                        <div class="match-score">
                            <h4>🎯 Match Score</h4>
                            <div class="match-percentage"><?php echo number_format($app['match_percentage'], 1); ?>% Match</div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="next-steps">
                            <h4>🎯 Next Steps</h4>
                            <p><?php echo getNextSteps($app['status']); ?></p>
                        </div>
                        
                        <?php if (!empty($app['hr_notes'])): ?>
                        <div class="hr-notes">
                            <h5>💬 HR Notes</h5>
                            <p><?php echo nl2br(htmlspecialchars($app['hr_notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <div class="empty-title">No Applications Yet</div>
                <div class="empty-text">
                    You haven't applied for any positions yet. Start your career journey by uploading your resume and applying for available positions.
                </div>
                <a href="candidate-dashboard.php" class="btn btn-primary">
                    🚀 Apply for Your First Job
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Auto-refresh page every 5 minutes to check for status updates
        setTimeout(() => {
            window.location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>