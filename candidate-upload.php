<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/extracta_api.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Check database connection
if (!isset($connection) || $connection->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

$processing = false;
$success = false;
$error = '';
$parsed_data = null;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['resume'])) {
    $processing = true;
    
    $upload_dir = 'uploads/resumes/';
    $allowed_types = array('pdf', 'doc', 'docx');
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['resume'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate file
    if ($file_error !== UPLOAD_ERR_OK) {
        $error = "Upload error occurred.";
        $processing = false;
    } elseif (!in_array($file_ext, $allowed_types)) {
        $error = "Only PDF, DOC, and DOCX files are allowed.";
        $processing = false;
    } elseif ($file_size > $max_size) {
        $error = "File size must be less than 5MB.";
        $processing = false;
    } else {
        // Generate unique filename
        $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file_tmp, $file_path)) {
            
            // Initialize Extracta API
            $extracta = new ExtractaAPI(EXTRACTA_API_KEY, EXTRACTA_EXTRACTION_ID);
            
            $parsed_result = $extracta->parseResume($file_path);
            
            if (isset($parsed_result['error'])) {
                $error = "Error parsing resume: " . $parsed_result['error'];
            } elseif (isset($parsed_result['success']) && $parsed_result['success']) {
                // Parse was successful
                $parsed_data = $parsed_result['data'];
                
                // Save parsed data to database
                if ($extracta->saveParsedData($parsed_data, $file_name, $connection)) {
                    $success = true;
                } else {
                    $error = "Resume parsed but failed to save to database.";
                }
            } else {
                $error = "Unexpected response from API.";
            }
            
        } else {
            $error = "Failed to upload file.";
        }
        $processing = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kabel Talent Hub - Resume Upload</title>
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
        
        /* Upload Zone */
        .upload-zone {
            border: 2px dashed var(--primary-color);
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            background: rgba(255, 107, 53, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .upload-zone:hover {
            background: rgba(255, 107, 53, 0.1);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .upload-zone.drag-active {
            background: rgba(255, 107, 53, 0.15);
            border-color: var(--secondary-color);
            transform: scale(1.02);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .upload-text {
            font-size: 1.1rem;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .upload-hint {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .file-info {
            background: rgba(43, 76, 140, 0.1);
            padding: 1rem;
            border-radius: 12px;
            margin-top: 1rem;
            border-left: 3px solid var(--secondary-color);
        }
        
        /* Form Elements */
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
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-color: #dc3545;
        }
        
        /* Results Display */
        .info-section {
            background: rgba(43, 76, 140, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-section h4 {
            color: var(--secondary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }
        
        .info-item strong {
            color: var(--secondary-color);
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .experience-item, .education-item {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            position: relative;
        }
        
        .experience-item::before, .education-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 1.5rem;
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
        }
        
        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .item-company, .item-institution {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .item-duration {
            font-size: 0.9rem;
            color: #6c757d;
            font-style: italic;
            margin-bottom: 0.75rem;
        }
        
        .item-description {
            color: #555;
            line-height: 1.5;
        }
        
        /* Tags */
        .skill-tag, .lang-tag, .cert-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin: 0.25rem 0.25rem 0.25rem 0;
        }
        
        .skill-tag {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .lang-tag {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(255, 107, 53, 0.2);
        }
        
        .cert-tag {
            background: rgba(43, 76, 140, 0.1);
            color: var(--secondary-color);
            border: 1px solid rgba(43, 76, 140, 0.2);
        }
        
        /* Progress */
        .upload-progress {
            margin-top: 1rem;
            display: none;
        }
        
        .progress-bar-container {
            background: #e9ecef;
            border-radius: 10px;
            height: 10px;
            overflow: hidden;
        }
        
        .progress-bar-fill {
            background: var(--kabel-gradient);
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }
        
        /* Status Indicator */
        .status-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            z-index: 1001;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .status-indicator.show {
            transform: translateX(0);
        }
        
        .status-indicator.success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .status-indicator.error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
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
            
            .info-grid {
                grid-template-columns: 1fr;
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
            <div class="logo">K</div>
            <h3>Kabel Talent Hub</h3>
            <p>Candidate Portal</p>
        </div>
        
        <ul class="sidebar-nav">
            <li>
                <a href="candidate-upload.php" class="active">
                    <span class="icon">📄</span>
                    <span>Upload Resume</span>
                </a>
            </li>
            <li>
                <a href="my-applications.php">
                    <span class="icon">📝</span>
                    <span>My Applications</span>
                </a>
            </li>
            <li>
                <a href="job-opportunities.php">
                    <span class="icon">💼</span>
                    <span>Job Opportunities</span>
                </a>
            </li>
            <li>
                <a href="profile.php">
                    <span class="icon">👤</span>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="help.php">
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
            <h1 class="page-title">🤖 AI Resume Parser</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
        
        <div class="content-area">
            <?php if (!$success && !$parsed_data): ?>
            <!-- Upload Section -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">📤</span>
                    <div>
                        <h3>Upload Your Resume</h3>
                        <p style="margin: 0; opacity: 0.9;">Let our AI extract key information automatically</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-zone" id="dropZone">
                            <div class="upload-icon">📄</div>
                            <div class="upload-text">
                                Drag & drop your resume here
                            </div>
                            <div class="upload-hint">
                                or click to browse files
                            </div>
                            <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" required style="display: none;">
                        </div>
                        
                        <div class="file-info" id="fileInfo" style="display: none;">
                            <strong>Supported formats:</strong> PDF, DOC, DOCX<br>
                            <strong>Maximum file size:</strong> 5MB<br>
                            <div id="selectedFile"></div>
                        </div>
                        
                        <div class="upload-progress" id="uploadProgress">
                            <div style="margin-bottom: 0.5rem; font-weight: 600; color: var(--secondary-color);">
                                Processing your resume...
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" id="progressBar"></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn" style="width: 100%; margin-top: 1.5rem;" disabled>
                            🚀 Parse Resume with AI
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($success && $parsed_data): ?>
            <!-- Results Section -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">✅</span>
                    <div>
                        <h3>Resume Parsing Complete</h3>
                        <p style="margin: 0; opacity: 0.9;">AI successfully extracted information from your resume</p>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Personal Information Section -->
                    <?php if (isset($parsed_data['personal_info']) && is_array($parsed_data['personal_info'])): ?>
                    <?php $personal = $parsed_data['personal_info']; ?>
                    <div class="info-section">
                        <h4>👤 Personal Information</h4>
                        <div class="info-grid">
                            <?php if (!empty($personal['name'])): ?>
                            <div class="info-item">
                                <strong>📛 Full Name</strong>
                                <?php echo htmlspecialchars($personal['name']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($personal['email'])): ?>
                            <div class="info-item">
                                <strong>📧 Email Address</strong>
                                <a href="mailto:<?php echo htmlspecialchars($personal['email']); ?>" style="color: var(--primary-color);">
                                    <?php echo htmlspecialchars($personal['email']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($personal['phone'])): ?>
                            <div class="info-item">
                                <strong>📞 Phone Number</strong>
                                <?php echo htmlspecialchars($personal['phone']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($personal['address'])): ?>
                            <div class="info-item">
                                <strong>📍 Address</strong>
                                <?php echo htmlspecialchars($personal['address']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($personal['linkedin'])): ?>
                            <div class="info-item">
                                <strong>💼 LinkedIn</strong>
                                <a href="<?php echo htmlspecialchars($personal['linkedin']); ?>" target="_blank" style="color: var(--secondary-color);">
                                    <?php echo htmlspecialchars($personal['linkedin']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($personal['github'])): ?>
                            <div class="info-item">
                                <strong>💻 GitHub</strong>
                                <a href="<?php echo htmlspecialchars($personal['github']); ?>" target="_blank" style="color: var(--dark-color);">
                                    <?php echo htmlspecialchars($personal['github']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Work Experience Section -->
                    <?php if (isset($parsed_data['work_experience']) && is_array($parsed_data['work_experience']) && !empty($parsed_data['work_experience'])): ?>
                    <div class="info-section">
                        <h4>💼 Work Experience</h4>
                        <?php foreach ($parsed_data['work_experience'] as $experience): ?>
                        <div class="experience-item">
                            <?php if (!empty($experience['title'])): ?>
                            <div class="item-title"><?php echo htmlspecialchars($experience['title']); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($experience['company'])): ?>
                            <div class="item-company"><?php echo htmlspecialchars($experience['company']); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($experience['start_date']) || !empty($experience['end_date'])): ?>
                            <div class="item-duration">
                                <?php 
                                $start = !empty($experience['start_date']) ? $experience['start_date'] : '';
                                $end = !empty($experience['end_date']) ? $experience['end_date'] : 'Present';
                                echo htmlspecialchars($start . ' - ' . $end);
                                ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($experience['description'])): ?>
                            <div class="item-description"><?php echo nl2br(htmlspecialchars($experience['description'])); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Education Section -->
                    <?php if (isset($parsed_data['education']) && is_array($parsed_data['education']) && !empty($parsed_data['education'])): ?>
                    <div class="info-section">
                        <h4>🎓 Education</h4>
                        <?php foreach ($parsed_data['education'] as $education): ?>
                        <div class="education-item">
                            <?php if (!empty($education['title'])): ?>
                            <div class="item-title"><?php echo htmlspecialchars($education['title']); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($education['institute'])): ?>
                            <div class="item-institution"><?php echo htmlspecialchars($education['institute']); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($education['location'])): ?>
                            <div style="color: #6c757d; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                📍 <?php echo htmlspecialchars($education['location']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($education['start_date']) || !empty($education['end_date'])): ?>
                            <div class="item-duration">
                                <?php 
                                $start = !empty($education['start_date']) ? $education['start_date'] : '';
                                $end = !empty($education['end_date']) ? $education['end_date'] : 'Present';
                                echo htmlspecialchars($start . ' - ' . $end);
                                ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($education['description'])): ?>
                            <div class="item-description"><?php echo nl2br(htmlspecialchars($education['description'])); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Skills, Languages, and Certificates -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <!-- Skills -->
                        <?php if (isset($parsed_data['skills']) && is_array($parsed_data['skills']) && !empty($parsed_data['skills'])): ?>
                        <div class="info-section">
                            <h4>🛠️ Skills</h4>
                            <div>
                                <?php foreach ($parsed_data['skills'] as $skill): ?>
                                <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Languages -->
                        <?php if (isset($parsed_data['languages']) && is_array($parsed_data['languages']) && !empty($parsed_data['languages'])): ?>
                        <div class="info-section">
                            <h4>🌐 Languages</h4>
                            <div>
                                <?php foreach ($parsed_data['languages'] as $language): ?>
                                <span class="lang-tag"><?php echo htmlspecialchars($language); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Certificates -->
                        <?php if (isset($parsed_data['certificates']) && is_array($parsed_data['certificates']) && !empty($parsed_data['certificates'])): ?>
                        <div class="info-section">
                            <h4>🏆 Certificates</h4>
                            <div>
                                <?php foreach ($parsed_data['certificates'] as $cert): ?>
                                <span class="cert-tag"><?php echo htmlspecialchars($cert); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="margin-top: 2rem; text-align: center; padding-top: 2rem; border-top: 1px solid #e9ecef;">
                        <a href="candidate-upload.php" class="btn btn-primary" style="margin-right: 1rem;">
                            📤 Upload Another Resume
                        </a>
                        <a href="my-applications.php" class="btn" style="background: var(--secondary-color); color: white;">
                            📝 View My Applications
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Raw Data Debug (Collapsible) -->
            <details style="margin-top: 2rem;">
                <summary style="cursor: pointer; color: #6c757d; font-size: 0.9rem; padding: 1rem; background: #f8f9fa; border-radius: 12px;">
                    🔍 View Raw API Response (for debugging)
                </summary>
                <pre style="background: #f5f5f5; padding: 1.5rem; border-radius: 12px; overflow-x: auto; font-size: 0.8rem; margin-top: 1rem;">
<?php echo htmlspecialchars(json_encode($parsed_data, JSON_PRETTY_PRINT)); ?>
                </pre>
            </details>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Status Indicator -->
    <?php if ($success): ?>
    <div class="status-indicator success show">
        ✅ Resume parsed successfully!
    </div>
    <?php elseif ($error): ?>
    <div class="status-indicator error show">
        ❌ <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        // File upload handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('resume');
        const fileInfo = document.getElementById('fileInfo');
        const selectedFile = document.getElementById('selectedFile');
        const submitBtn = document.getElementById('submitBtn');
        const uploadForm = document.getElementById('uploadForm');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        
        // Click to upload
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-active');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-active');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-active');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });
        
        // File selection
        fileInput.addEventListener('change', handleFileSelect);
        
        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                selectedFile.innerHTML = `<strong>Selected:</strong> ${file.name} (${fileSize} MB)`;
                fileInfo.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.style.background = 'var(--kabel-gradient)';
            }
        }
        
        // Form submission with progress
        uploadForm.addEventListener('submit', function(e) {
            if (!fileInput.files[0]) {
                e.preventDefault();
                alert('Please select a file first.');
                return;
            }
            
            // Show progress
            uploadProgress.style.display = 'block';
            submitBtn.innerHTML = '⏳ Processing Resume...';
            submitBtn.disabled = true;
            
            // Simulate progress
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 30;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
                
                if (progress >= 90) {
                    clearInterval(interval);
                }
            }, 500);
        });
        
        // Auto-hide status indicator
        setTimeout(() => {
            const statusIndicator = document.querySelector('.status-indicator.show');
            if (statusIndicator) {
                statusIndicator.classList.remove('show');
            }
        }, 5000);
    </script>
</body>
</html>