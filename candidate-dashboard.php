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

// Check if user is now an employee (after being hired)
if ($_SESSION['role'] === 'employee') {
    // Redirect to employee dashboard
    header('Location: employee-dashboard.php');
    exit;
}

// Ensure candidate access only
requireRole('candidate');

// Check database connection
if (!isset($connection) || $connection->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

$processing = false;
$success = false;
$error = '';
$parsed_data = null;
$application_created = false;

// Fetch active job positions for dropdown
$job_positions = [];
try {
    $job_query = "SELECT id, title, department, description, required_skills, experience_level FROM job_positions WHERE status = 'active' ORDER BY title ASC";
    $job_result = $connection->query($job_query);
    if ($job_result) {
        while ($row = $job_result->fetch_assoc()) {
            $job_positions[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching job positions: " . $e->getMessage());
}

// Check if candidate has been hired and role updated
$role_check_stmt = $connection->prepare("SELECT role, department FROM users WHERE id = ?");
$role_check_stmt->bind_param("i", $_SESSION['user_id']);
$role_check_stmt->execute();
$role_check_result = $role_check_stmt->get_result();
if ($role_check_result->num_rows > 0) {
    $user_data = $role_check_result->fetch_assoc();
    if ($user_data['role'] === 'employee') {
        // Update session and redirect
        $_SESSION['role'] = 'employee';
        $_SESSION['department'] = $user_data['department'];
        header('Location: employee-dashboard.php');
        exit;
    }
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['resume'])) {
    $processing = true;
    
    // Validate job position selection
    $selected_job_id = isset($_POST['job_position']) ? (int)$_POST['job_position'] : 0;
    if ($selected_job_id <= 0) {
        $error = "Please select a job position to apply for.";
        $processing = false;
    } else {
        // Verify job position exists and is active
        $job_check_stmt = $connection->prepare("SELECT id, title FROM job_positions WHERE id = ? AND status = 'active'");
        $job_check_stmt->bind_param("i", $selected_job_id);
        $job_check_stmt->execute();
        $job_check_result = $job_check_stmt->get_result();
        
        if ($job_check_result->num_rows === 0) {
            $error = "Selected job position is not available.";
            $processing = false;
        } else {
            $selected_job = $job_check_result->fetch_assoc();
            
            // Check if candidate has already applied for this job
            $existing_app_stmt = $connection->prepare("SELECT id FROM applications WHERE candidate_id = ? AND job_position_id = ?");
            $existing_app_stmt->bind_param("ii", $_SESSION['user_id'], $selected_job_id);
            $existing_app_stmt->execute();
            $existing_app_result = $existing_app_stmt->get_result();
            
            if ($existing_app_result->num_rows > 0) {
                $error = "You have already applied for this position: " . htmlspecialchars($selected_job['title']);
                $processing = false;
            }
        }
    }
    
    if (!$error) {
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
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
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
                    error_log("Resume parsing error: " . $parsed_result['error']);
                } elseif (isset($parsed_result['success']) && $parsed_result['success']) {
                    // Parse was successful
                    $parsed_data = $parsed_result['data'];
                    
                    // Log success for debugging (optional)
                    error_log("Resume parsing successful for user: " . $_SESSION['user_id']);
                    
                    // Calculate match percentage based on job requirements
                    $match_percentage = calculateMatchPercentage($parsed_data, $selected_job_id, $connection);
                    
                    // Start transaction for saving both parsed data and application
                    $connection->begin_transaction();
                    
                    try {
                        // Save parsed data to parsed_resumes table
                        $save_result = $extracta->saveParsedData($parsed_data, $file_name, $connection);
                        
                        if ($save_result) {
                            
                            // Create application entry
                            $app_stmt = $connection->prepare("
                                INSERT INTO applications (
                                    candidate_id, 
                                    job_position_id, 
                                    resume_filename, 
                                    resume_path,
                                    api_response,
                                    extracted_skills,
                                    extracted_experience,
                                    extracted_education,
                                    extracted_contact,
                                    match_percentage,
                                    api_processing_status,
                                    status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', 'pending')
                            ");
                            
                            // Prepare data for application
                            $api_response = json_encode($parsed_data);
                            $skills = isset($parsed_data['skills']) ? json_encode($parsed_data['skills']) : null;
                            $experience = isset($parsed_data['work_experience']) ? json_encode($parsed_data['work_experience']) : null;
                            $education = isset($parsed_data['education']) ? json_encode($parsed_data['education']) : null;
                            $contact = isset($parsed_data['personal_info']) ? json_encode($parsed_data['personal_info']) : null;
                            
                            $app_stmt->bind_param("iisssssssd", 
                                $_SESSION['user_id'],
                                $selected_job_id,
                                $file_name,
                                $file_path,
                                $api_response,
                                $skills,
                                $experience,
                                $education,
                                $contact,
                                $match_percentage
                            );
                            
                            if ($app_stmt->execute()) {
                                $connection->commit();
                                $success = true;
                                $application_created = true;
                                error_log("Application created successfully for user: " . $_SESSION['user_id']);
                            } else {
                                $connection->rollback();
                                $error = "Resume parsed but failed to create application: " . $app_stmt->error;
                                error_log("Application creation failed: " . $app_stmt->error);
                            }
                            
                        } else {
                            $connection->rollback();
                            $error = "Failed to save parsed resume data.";
                            error_log("Failed to save parsed resume data - rolling back transaction");
                        }
                        
                    } catch (Exception $e) {
                        $connection->rollback();
                        $error = "Database error: " . $e->getMessage();
                        error_log("Database exception: " . $e->getMessage());
                    }
                    
                } else {
                    $error = "Unexpected response from API.";
                    error_log("Unexpected API response: " . print_r($parsed_result, true));
                }
                
            } else {
                $error = "Failed to upload file.";
            }
        }
    }
    $processing = false;
}

// Function to calculate match percentage
function calculateMatchPercentage($parsed_data, $job_position_id, $connection) {
    try {
        // Get job requirements
        $job_stmt = $connection->prepare("SELECT required_skills, experience_level FROM job_positions WHERE id = ?");
        $job_stmt->bind_param("i", $job_position_id);
        $job_stmt->execute();
        $job_result = $job_stmt->get_result();
        
        if ($job_result->num_rows === 0) {
            return 0;
        }
        
        $job_data = $job_result->fetch_assoc();
        $required_skills = explode(',', strtolower($job_data['required_skills']));
        $required_experience = strtolower($job_data['experience_level']);
        
        // Get candidate skills
        $candidate_skills = isset($parsed_data['skills']) ? array_map('strtolower', $parsed_data['skills']) : [];
        
        // Calculate skill match (70% weight)
        $skill_matches = 0;
        foreach ($required_skills as $required_skill) {
            $required_skill = trim($required_skill);
            foreach ($candidate_skills as $candidate_skill) {
                if (strpos($candidate_skill, $required_skill) !== false || 
                    strpos($required_skill, $candidate_skill) !== false) {
                    $skill_matches++;
                    break;
                }
            }
        }
        
        $skill_percentage = count($required_skills) > 0 ? ($skill_matches / count($required_skills)) * 100 : 0;
        
        // Calculate experience match (30% weight)
        $experience_percentage = 50; // Default middle score
        if (isset($parsed_data['work_experience']) && !empty($parsed_data['work_experience'])) {
            $years_experience = count($parsed_data['work_experience']); // Simplified calculation
            
            switch ($required_experience) {
                case 'entry':
                    $experience_percentage = $years_experience >= 0 ? 100 : 70;
                    break;
                case 'mid':
                    $experience_percentage = $years_experience >= 1 ? 100 : ($years_experience > 0 ? 80 : 60);
                    break;
                case 'senior':
                    $experience_percentage = $years_experience >= 3 ? 100 : ($years_experience >= 2 ? 80 : 50);
                    break;
            }
        }
        
        // Weighted average
        $final_percentage = ($skill_percentage * 0.7) + ($experience_percentage * 0.3);
        
        return round($final_percentage, 2);
        
    } catch (Exception $e) {
        error_log("Error calculating match percentage: " . $e->getMessage());
        return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vector - Resume Upload</title>
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
        
        /* Job Selection */
        .job-selection {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(43, 76, 140, 0.05);
            border-radius: 12px;
            border-left: 3px solid var(--secondary-color);
        }
        
        .job-selection label {
            display: block;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        
        .job-select {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            background: white;
            color: var(--secondary-color);
            transition: all 0.3s ease;
        }
        
        .job-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }
        
        .job-info {
            margin-top: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
            display: none;
        }
        
        .job-info.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .job-info h4 {
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .job-info p {
            color: #6c757d;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .skills-required {
            margin-top: 0.75rem;
        }
        
        .skill-req-tag {
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
        
        .upload-zone.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
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
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-color: #28a745;
        }
        
        .alert-info {
            background: rgba(102, 16, 242, 0.1);
            color: #5a1a6b;
            border-color: #6610f2;
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
        
        /* Application Success Message */
        .application-success {
            background: rgba(40, 167, 69, 0.05);
            border: 2px solid rgba(40, 167, 69, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .application-success .success-icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
            animation: bounce 1s ease-in-out infinite alternate;
        }
        
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-10px); }
        }
        
        .application-success h3 {
            color: #28a745;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        
        .application-success p {
            color: #155724;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        
        /* Role Check Notice */
        .role-notice {
            background: rgba(102, 16, 242, 0.05);
            border: 2px solid rgba(102, 16, 242, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .role-notice .notice-icon {
            font-size: 3rem;
            color: #6610f2;
            margin-bottom: 1rem;
        }
        
        .role-notice h3 {
            color: #6610f2;
            margin-bottom: 0.5rem;
        }
        
        .role-notice p {
            color: #5a1a6b;
            margin-bottom: 1rem;
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

        /* Custom Scrollbar for Sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-color); /* Orange color */
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color); /* Blue color */
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
                <a href="candidate-dashboard.php" class="active">
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
                <div style="color: rgba(255,255,255,0.7); font-size: 0.8rem; margin-bottom: 1rem;">
                    Candidate
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
            <h1 class="page-title">🤖 AI Resume Parser & Job Application</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
        
        <div class="content-area">
            <?php if ($success && $application_created): ?>
            <!-- Application Success Message -->
            <div class="application-success">
                <div class="success-icon">🎉</div>
                <h3>Application Submitted Successfully!</h3>
                <p>Your resume has been parsed and your application for "<strong><?php echo htmlspecialchars($selected_job['title']); ?></strong>" has been submitted with a match score of <strong><?php echo isset($match_percentage) ? number_format($match_percentage, 1) : 'N/A'; ?>%</strong>.</p>
                <div style="margin-top: 1.5rem;">
                    <a href="candidate-applications.php" class="btn btn-primary" style="margin-right: 1rem;">
                        📝 View My Applications
                    </a>
                    <a href="candidate-dashboard.php" class="btn" style="background: var(--secondary-color); color: white;">
                        📤 Apply for Another Job
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <!-- Upload Section -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">📤</span>
                    <div>
                        <h3>Apply for a Job Position</h3>
                        <p style="margin: 0; opacity: 0.9;">Select a job and upload your resume to apply</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <!-- Job Position Selection -->
                        <div class="job-selection">
                            <label for="job_position">
                                💼 Select Job Position to Apply For
                            </label>
                            <select name="job_position" id="job_position" class="job-select" required>
                                <option value="">-- Choose a job position --</option>
                                <?php foreach ($job_positions as $job): ?>
                                <option value="<?php echo $job['id']; ?>" 
                                        data-department="<?php echo htmlspecialchars($job['department']); ?>"
                                        data-description="<?php echo htmlspecialchars($job['description']); ?>"
                                        data-skills="<?php echo htmlspecialchars($job['required_skills']); ?>"
                                        data-experience="<?php echo htmlspecialchars($job['experience_level']); ?>">
                                    <?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['department']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div class="job-info" id="jobInfo">
                                <h4 id="jobTitle"></h4>
                                <p><strong>Department:</strong> <span id="jobDepartment"></span></p>
                                <p><strong>Experience Level:</strong> <span id="jobExperience"></span></p>
                                <p><strong>Description:</strong> <span id="jobDescription"></span></p>
                                <div class="skills-required">
                                    <strong>Required Skills:</strong>
                                    <div id="jobSkills"></div>
                                </div>
                            </div>
                        </div>
                        
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
                                Processing your application...
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" id="progressBar"></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn" style="width: 100%; margin-top: 1.5rem;" disabled>
                            🚀 Submit Application
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Available Job Positions Info -->
            <?php if (!empty($job_positions)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="icon">💼</span>
                    <div>
                        <h3>Available Job Positions</h3>
                        <p style="margin: 0; opacity: 0.9;">Browse all open positions</p>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($job_positions as $job): ?>
                        <div class="experience-item">
                            <div class="item-title"><?php echo htmlspecialchars($job['title']); ?></div>
                            <div class="item-company"><?php echo htmlspecialchars($job['department']); ?> Department</div>
                            <div class="item-duration">
                                Experience Level: <?php echo ucfirst(htmlspecialchars($job['experience_level'])); ?>
                            </div>
                            <div class="item-description">
                                <?php echo htmlspecialchars($job['description']); ?>
                            </div>
                            <div class="skills-required" style="margin-top: 1rem;">
                                <strong>Required Skills:</strong><br>
                                <?php 
                                $skills = explode(',', $job['required_skills']);
                                foreach ($skills as $skill): 
                                ?>
                                <span class="skill-req-tag"><?php echo trim(htmlspecialchars($skill)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
            
            <?php if ($success && $parsed_data && !$application_created): ?>
            <!-- Results Section (if parsing succeeded but application failed) -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">✅</span>
                    <div>
                        <h3>Resume Parsing Complete</h3>
                        <p style="margin: 0; opacity: 0.9;">AI successfully extracted information from your resume</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <strong>⚠️ Note:</strong> Resume was parsed successfully but application creation failed. Please try uploading again.
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="margin-top: 2rem; text-align: center; padding-top: 2rem; border-top: 1px solid #e9ecef;">
                        <a href="candidate-dashboard.php" class="btn btn-primary">
                            📤 Try Again
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($success && $parsed_data && $application_created): ?>
            <!-- Full Results Section -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">✅</span>
                    <div>
                        <h3>Resume Analysis Results</h3>
                        <p style="margin: 0; opacity: 0.9;">Here's what our AI extracted from your resume</p>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Match Score Display -->
                    <?php if (isset($match_percentage)): ?>
                    <div class="alert alert-info">
                        <span>🎯</span>
                        <div>
                            <strong>Job Match Score:</strong> <?php echo number_format($match_percentage, 1); ?>% - 
                            <?php 
                            if ($match_percentage >= 90) echo "Excellent match!";
                            elseif ($match_percentage >= 70) echo "Good match!";
                            elseif ($match_percentage >= 50) echo "Fair match";
                            else echo "Skills development recommended";
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
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
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Status Indicator -->
    <?php if ($success && $application_created): ?>
    <div class="status-indicator success show">
        ✅ Application submitted successfully!
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
        
        // Job position selection handling
        const jobSelect = document.getElementById('job_position');
        const jobInfo = document.getElementById('jobInfo');
        const jobTitle = document.getElementById('jobTitle');
        const jobDepartment = document.getElementById('jobDepartment');
        const jobDescription = document.getElementById('jobDescription');
        const jobExperience = document.getElementById('jobExperience');
        const jobSkills = document.getElementById('jobSkills');
        const uploadZone = document.getElementById('dropZone');
        
        jobSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (this.value) {
                // Show job information
                jobTitle.textContent = selectedOption.text.split(' - ')[0];
                jobDepartment.textContent = selectedOption.dataset.department;
                jobDescription.textContent = selectedOption.dataset.description;
                jobExperience.textContent = selectedOption.dataset.experience.charAt(0).toUpperCase() + selectedOption.dataset.experience.slice(1);
                
                // Display skills
                jobSkills.innerHTML = '';
                const skills = selectedOption.dataset.skills.split(',');
                skills.forEach(skill => {
                    const skillTag = document.createElement('span');
                    skillTag.className = 'skill-req-tag';
                    skillTag.textContent = skill.trim();
                    jobSkills.appendChild(skillTag);
                });
                
                jobInfo.classList.add('show');
                uploadZone.classList.remove('disabled');
                updateSubmitButton();
            } else {
                jobInfo.classList.remove('show');
                uploadZone.classList.add('disabled');
                updateSubmitButton();
            }
        });
        
        // File upload handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('resume');
        const fileInfo = document.getElementById('fileInfo');
        const selectedFile = document.getElementById('selectedFile');
        const submitBtn = document.getElementById('submitBtn');
        const uploadForm = document.getElementById('uploadForm');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        
        // Click to upload (only if job is selected)
        dropZone.addEventListener('click', () => {
            if (jobSelect.value && !dropZone.classList.contains('disabled')) {
                fileInput.click();
            }
        });
        
        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (jobSelect.value && !dropZone.classList.contains('disabled')) {
                dropZone.classList.add('drag-active');
            }
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-active');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-active');
            
            if (jobSelect.value && !dropZone.classList.contains('disabled')) {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelect();
                }
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
                updateSubmitButton();
            }
        }
        
        function updateSubmitButton() {
            const hasJob = jobSelect.value !== '';
            const hasFile = fileInput.files.length > 0;
            
            if (hasJob && hasFile) {
                submitBtn.disabled = false;
                submitBtn.style.background = 'var(--kabel-gradient)';
                submitBtn.innerHTML = '🚀 Submit Application';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.background = '#6c757d';
                if (!hasJob) {
                    submitBtn.innerHTML = '💼 Select Job Position First';
                } else if (!hasFile) {
                    submitBtn.innerHTML = '📄 Upload Resume First';
                }
            }
        }
        
        // Form submission with progress
        uploadForm.addEventListener('submit', function(e) {
            if (!jobSelect.value) {
                e.preventDefault();
                alert('Please select a job position first.');
                return;
            }
            
            if (!fileInput.files[0]) {
                e.preventDefault();
                alert('Please select a resume file first.');
                return;
            }
            
            // Show progress
            uploadProgress.style.display = 'block';
            submitBtn.innerHTML = '⏳ Processing Application...';
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
        
        // Initialize upload zone state
        if (!jobSelect.value) {
            uploadZone.classList.add('disabled');
        }
        
        updateSubmitButton();
    </script>
</body>
</html>