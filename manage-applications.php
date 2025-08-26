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

// Handle status updates and bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $success = '';
    $error = '';
    
    try {
        // Handle single status update
        if (isset($_POST['update_status']) && isset($_POST['application_id'])) {
            $application_id = (int)$_POST['application_id'];
            $new_status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';
            
            // Start transaction
            $connection->begin_transaction();
            
            // Update application status
            $stmt = $connection->prepare("UPDATE applications SET status = ?, hr_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("ssi", $new_status, $notes, $application_id);
            
            if ($stmt->execute()) {
                // If status is 'hired', convert candidate to employee
                if ($new_status === 'hired') {
                    // Get application details
                    $app_stmt = $connection->prepare("SELECT candidate_id, job_position_id FROM applications WHERE id = ?");
                    $app_stmt->bind_param("i", $application_id);
                    $app_stmt->execute();
                    $app_result = $app_stmt->get_result();
                    $app_data = $app_result->fetch_assoc();
                    
                    if ($app_data) {
                        // Get job details
                        $job_stmt = $connection->prepare("SELECT department, title FROM job_positions WHERE id = ?");
                        $job_stmt->bind_param("i", $app_data['job_position_id']);
                        $job_stmt->execute();
                        $job_result = $job_stmt->get_result();
                        $job_data = $job_result->fetch_assoc();
                        
                        if ($job_data) {
                            // Update user role and department
                            $user_stmt = $connection->prepare("UPDATE users SET role = 'employee', department = ?, job_position_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                            $user_stmt->bind_param("sii", $job_data['department'], $app_data['job_position_id'], $app_data['candidate_id']);
                            
                            if ($user_stmt->execute()) {
                                // Create onboarding tasks
                                $task_stmt = $connection->prepare("
                                    INSERT IGNORE INTO employee_onboarding (employee_id, task_id, status)
                                    SELECT ?, ot.id, 'pending'
                                    FROM onboarding_tasks ot
                                    WHERE ot.department = ? OR ot.department = 'ALL'
                                ");
                                $task_stmt->bind_param("is", $app_data['candidate_id'], $job_data['department']);
                                $task_stmt->execute();
                                
                                // Create training assignments
                                $training_stmt = $connection->prepare("
                                    INSERT IGNORE INTO employee_training (employee_id, module_id, status, progress_percentage)
                                    SELECT ?, tm.id, 'not_started', 0
                                    FROM training_modules tm
                                    WHERE tm.department = ? OR tm.department = 'ALL'
                                ");
                                $training_stmt->bind_param("is", $app_data['candidate_id'], $job_data['department']);
                                $training_stmt->execute();
                                
                                // Create employee documents
                                $doc_stmt = $connection->prepare("
                                    INSERT IGNORE INTO employee_documents (employee_id, document_name, document_type, status, is_required, description)
                                    VALUES 
                                    (?, 'Employment Contract', 'contract', 'pending', 1, 'Your employment contract and terms of service'),
                                    (?, 'Personal Information Form', 'personal_form', 'pending', 1, 'Complete personal details and emergency contacts'),
                                    (?, 'Bank Details Form', 'bank_form', 'pending', 1, 'Banking information for salary processing'),
                                    (?, 'ID Copy', 'identification', 'pending', 1, 'Copy of your identification document (IC/Passport)'),
                                    (?, 'Educational Certificates', 'education', 'pending', 1, 'Copies of your educational qualifications')
                                ");
                                $doc_stmt->bind_param("iiiii", 
                                    $app_data['candidate_id'], 
                                    $app_data['candidate_id'], 
                                    $app_data['candidate_id'], 
                                    $app_data['candidate_id'], 
                                    $app_data['candidate_id']
                                );
                                $doc_stmt->execute();
                                
                                $connection->commit();
                                $success = "Application status updated successfully! Candidate has been converted to employee and onboarding materials have been created.";
                            } else {
                                $connection->rollback();
                                $error = "Failed to update user role: " . $connection->error;
                            }
                        } else {
                            $connection->rollback();
                            $error = "Job position not found.";
                        }
                    } else {
                        $connection->rollback();
                        $error = "Application not found.";
                    }
                } else {
                    $connection->commit();
                    $success = "Application status updated successfully!";
                }
            } else {
                $connection->rollback();
                $error = "Failed to update application status: " . $connection->error;
            }
        }
        
        // Handle bulk actions
        elseif (isset($_POST['bulk_action']) && isset($_POST['selected_applications'])) {
            $bulk_action = $_POST['bulk_action'];
            $selected_applications = $_POST['selected_applications'];
            $processed = 0;
            $hired_count = 0;
            
            if (!empty($selected_applications) && !empty($bulk_action)) {
                $connection->begin_transaction();
                
                foreach ($selected_applications as $app_id) {
                    $app_id = (int)$app_id;
                    
                    switch ($bulk_action) {
                        case 'approve_selected':
                            $stmt = $connection->prepare("UPDATE applications SET status = 'selected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                            $stmt->bind_param("i", $app_id);
                            if ($stmt->execute()) $processed++;
                            break;
                            
                        case 'reject_selected':
                            $stmt = $connection->prepare("UPDATE applications SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                            $stmt->bind_param("i", $app_id);
                            if ($stmt->execute()) $processed++;
                            break;
                            
                        case 'interview_selected':
                            $stmt = $connection->prepare("UPDATE applications SET status = 'waiting_interview', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                            $stmt->bind_param("i", $app_id);
                            if ($stmt->execute()) $processed++;
                            break;
                            
                        case 'hire_selected':
                            // Update status to hired
                            $stmt = $connection->prepare("UPDATE applications SET status = 'hired', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                            $stmt->bind_param("i", $app_id);
                            if ($stmt->execute()) {
                                // Convert to employee (same logic as above)
                                $app_stmt = $connection->prepare("SELECT candidate_id, job_position_id FROM applications WHERE id = ?");
                                $app_stmt->bind_param("i", $app_id);
                                $app_stmt->execute();
                                $app_result = $app_stmt->get_result();
                                $app_data = $app_result->fetch_assoc();
                                
                                if ($app_data) {
                                    $job_stmt = $connection->prepare("SELECT department, title FROM job_positions WHERE id = ?");
                                    $job_stmt->bind_param("i", $app_data['job_position_id']);
                                    $job_stmt->execute();
                                    $job_result = $job_stmt->get_result();
                                    $job_data = $job_result->fetch_assoc();
                                    
                                    if ($job_data) {
                                        $user_stmt = $connection->prepare("UPDATE users SET role = 'employee', department = ?, job_position_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                                        $user_stmt->bind_param("sii", $job_data['department'], $app_data['job_position_id'], $app_data['candidate_id']);
                                        
                                        if ($user_stmt->execute()) {
                                            // Create onboarding materials (simplified for bulk)
                                            $task_stmt = $connection->prepare("INSERT IGNORE INTO employee_onboarding (employee_id, task_id, status) SELECT ?, ot.id, 'pending' FROM onboarding_tasks ot WHERE ot.department = ? OR ot.department = 'ALL'");
                                            $task_stmt->bind_param("is", $app_data['candidate_id'], $job_data['department']);
                                            $task_stmt->execute();
                                            
                                            $hired_count++;
                                            $processed++;
                                        }
                                    }
                                }
                            }
                            break;
                    }
                }
                
                if ($processed > 0) {
                    $connection->commit();
                    $success = "Successfully processed $processed applications.";
                    if ($hired_count > 0) {
                        $success .= " $hired_count candidates have been converted to employees.";
                    }
                } else {
                    $connection->rollback();
                    $error = "No applications were processed.";
                }
            } else {
                $error = "Please select applications and an action.";
            }
        }
        
    } catch (Exception $e) {
        if ($connection) $connection->rollback();
        $error = "Database error: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
$match_filter = isset($_GET['match_filter']) ? $_GET['match_filter'] : 'all';

try {
    // Get job positions for filtering
    $stmt = $connection->query("SELECT id, title, department FROM job_positions ORDER BY title");
    $job_positions = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Build query for applications with ranking
$query = "
    SELECT 
        a.id,
        u.full_name as candidate_name,
        u.email as candidate_email,
        u.role as `current_role`,
        u.department as current_department,
        a.extracted_contact,
        a.match_percentage,
        a.extracted_skills,
        a.extracted_experience,
        a.extracted_education,
        a.api_response as parsed_data,
        a.resume_filename,
        a.applied_at,
        a.status,
        a.hr_notes,
        j.title as job_title,
        j.department,
        j.required_skills,
        j.experience_level,
        j.description as job_description,
        ROW_NUMBER() OVER (ORDER BY a.match_percentage DESC, a.applied_at DESC) as ranking
    FROM applications a
    JOIN job_positions j ON a.job_position_id = j.id
    JOIN users u ON a.candidate_id = u.id
    WHERE 1=1
";

    $params = [];
$types = '';

if ($filter_job_id) {
    $query .= " AND a.job_position_id = ?";
    $params[] = $filter_job_id;
    $types .= 'i';
}

// Apply status filter
if ($status_filter && $status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Apply match percentage filter
switch($match_filter) {
    case '90-100':
        $query .= " AND a.match_percentage >= 90";
        break;
    case '70-89':
        $query .= " AND a.match_percentage >= 70 AND a.match_percentage < 90";
        break;
    case '50-69':
        $query .= " AND a.match_percentage >= 50 AND a.match_percentage < 70";
        break;
    case 'below-50':
        $query .= " AND a.match_percentage < 50";
        break;
}

$query .= " ORDER BY a.match_percentage DESC, a.applied_at DESC";
    if ($params) {
        $stmt = $connection->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $applications = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                $applications = [];
            }
        } else {
            $applications = [];
            $error = "Failed to prepare statement: " . $connection->error;
        }
    } else {
        $stmt = $connection->query($query);
        if ($stmt) {
            $applications = $stmt->fetch_all(MYSQLI_ASSOC);
        } else {
            $applications = [];
            $error = "Query failed: " . $connection->error;
        }
    }
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $applications = [];
    $job_positions = [];
}

// Function to format extracted data for display
function formatExtractedData($data, $limit = 5) {
    if (empty($data)) return 'Not available';
    
    $decoded = json_decode($data, true);
    if (is_array($decoded)) {
        $items = array_slice($decoded, 0, $limit);
        $result = implode(', ', $items);
        if (count($decoded) > $limit) {
            $result .= ' (+' . (count($decoded) - $limit) . ' more)';
        }
        return $result;
    }
    return $data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications - Vector HR System</title>
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #1e3a75;
            transform: translateY(-1px);
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-form .form-group {
            flex: 1;
            margin-bottom: 0;
            min-width: 200px;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #f1f3f4;
        }

        .applications-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .applications-table thead {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #1e3a75 100%);
        }

        .applications-table th {
            padding: 1.25rem 1rem;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            position: relative;
        }

        .th-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .th-content span:first-child {
            font-size: 1rem;
        }

        /* Column Widths */
        .checkbox-col { width: 50px; text-align: center; }
        .rank-col { width: 80px; text-align: center; }
        .candidate-col { width: 250px; }
        .job-col { width: 200px; }
        .match-col { width: 120px; text-align: center; }
        .date-col { width: 150px; }
        .status-col { width: 140px; text-align: center; }
        .actions-col { width: 180px; text-align: center; }

        .applications-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f8f9fa;
            vertical-align: middle;
        }

        .application-row {
            transition: all 0.2s ease;
            background: white;
        }

        .application-row:hover {
            background: rgba(255, 107, 53, 0.02);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        /* Candidate Info Styles */
        .candidate-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .candidate-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--kabel-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .candidate-details {
            flex: 1;
            min-width: 0;
        }

        .candidate-name {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .candidate-email, .candidate-phone {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.1rem;
        }

        .role-indicator {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 0.25rem;
        }

        .role-candidate {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .role-employee {
            background: rgba(102, 16, 242, 0.1);
            color: #5a1a6b;
            border: 1px solid rgba(102, 16, 242, 0.2);
        }

        /* Job Info Styles */
        .job-info {
            line-height: 1.4;
        }

        .job-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .job-department, .job-level {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.1rem;
        }

        /* Ranking Styles */
        .ranking-container {
            text-align: center;
        }

        .ranking-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            margin-bottom: 0.25rem;
        }

        .ranking-1 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            box-shadow: 0 3px 12px rgba(255, 215, 0, 0.4);
        }

        .ranking-2 {
            background: linear-gradient(135deg, #C0C0C0, #A0A0A0);
            box-shadow: 0 3px 12px rgba(192, 192, 192, 0.4);
        }

        .ranking-3 {
            background: linear-gradient(135deg, #CD7F32, #B8860B);
            box-shadow: 0 3px 12px rgba(205, 127, 50, 0.4);
        }

        .ranking-other {
            background: linear-gradient(135deg, var(--secondary-color), #1e3a75);
            box-shadow: 0 2px 8px rgba(43, 76, 140, 0.3);
        }

        .ranking-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Match Score Styles */
        .match-container {
            text-align: center;
        }

        .match-score {
            display: inline-block;
            padding: 0.5rem 0.75rem;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            border: 2px solid;
        }

        .match-excellent {
            background: rgba(34, 197, 94, 0.1);
            color: #166534;
            border-color: rgba(34, 197, 94, 0.3);
        }

        .match-good {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border-color: rgba(245, 158, 11, 0.3);
        }

        .match-fair {
            background: rgba(249, 115, 22, 0.1);
            color: #9a3412;
            border-color: rgba(249, 115, 22, 0.3);
        }

        .match-poor {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .match-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            width: 60px;
            margin: 0 auto;
        }

        .match-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }

        .match-fill.match-excellent {
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .match-fill.match-good {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .match-fill.match-fair {
            background: linear-gradient(90deg, #f97316, #ea580c);
        }

        .match-fill.match-poor {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }

        /* Date Info Styles */
        .date-info {
            text-align: center;
            line-height: 1.3;
        }

        .date-main {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.25rem;
        }

        .date-time {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.1rem;
        }

        .date-relative {
            font-size: 0.75rem;
            color: #9ca3af;
            font-style: italic;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-xs {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 8px;
            font-weight: 500;
            min-width: 80px;
            text-align: center;
        }

        /* Status Badges */
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
        
        .status-hired {
            background: rgba(102, 16, 242, 0.1);
            color: #5a1a6b;
            border: 1px solid rgba(102, 16, 242, 0.2);
        }
        
        .status-waiting-interview {
            background: rgba(23, 162, 184, 0.1);
            color: #155160;
            border: 1px solid rgba(23, 162, 184, 0.2);
        }

        /* Bulk Actions Enhancement */
        .bulk-actions {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }

        /* Enhanced Empty State */
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

        /* Card Header Fix */
        .card-header .header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .application-count {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            font-weight: 500;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
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

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .applications-table {
                font-size: 0.8rem;
            }
            
            .applications-table th,
            .applications-table td {
                padding: 1rem 0.75rem;
            }
            
            .candidate-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .action-buttons {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 0.25rem;
            }
            
            .btn-xs {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
                min-width: 70px;
            }
            
            /* Hide less important columns on mobile */
            .rank-col,
            .date-col {
                display: none;
            }
            
            .candidate-col,
            .job-col {
                width: auto;
            }
            
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
                <a href="manage-applications.php" class="active">
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
            <h1 class="page-title">📋 Applications Management - Candidate Ranking</h1>
        </div>

        <div class="content-area">
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-danger">
                    <span>⚠️</span>
                    <div><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($success) && !empty($success)): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div><strong>Success:</strong> <?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <div class="header-left">
                        <span class="icon" style="font-size: 1.5rem;">🎯</span>
                        <h3 style="margin: 0; font-size: 1.25rem;">Filter Applications</h3>
                    </div>
                    <div class="header-right">
                        <button onclick="exportApplications()" class="btn btn-outline btn-sm" style="color: white">
                            📊 Export Data
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label class="form-label">Job Position</label>
                            <select name="job_id" class="form-control form-select">
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
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control form-select">
                                <option value="all">All Status</option>
                                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="selected" <?php echo ($status_filter == 'selected') ? 'selected' : ''; ?>>Selected</option>
                                <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                <option value="waiting_interview" <?php echo ($status_filter == 'waiting_interview') ? 'selected' : ''; ?>>Waiting Interview</option>
                                <option value="hired" <?php echo ($status_filter == 'hired') ? 'selected' : ''; ?>>Hired</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Match Score</label>
                            <select name="match_filter" class="form-control form-select">
                                <option value="all">All Scores</option>
                                <option value="90-100" <?php echo ($match_filter == '90-100') ? 'selected' : ''; ?>>90% - 100%</option>
                                <option value="70-89" <?php echo ($match_filter == '70-89') ? 'selected' : ''; ?>>70% - 89%</option>
                                <option value="50-69" <?php echo ($match_filter == '50-69') ? 'selected' : ''; ?>>50% - 69%</option>
                                <option value="below-50" <?php echo ($match_filter == 'below-50') ? 'selected' : ''; ?>>Below 50%</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="manage-applications.php" class="btn btn-outline">Clear</a>
                    </form>
                </div>
            </div>

            <!-- Applications Table -->
            <div class="card">
                <div class="card-header">
                    <div class="header-left">
                        <span class="icon" style="font-size: 1.5rem;">📋</span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.25rem;">Applications Ranking</h3>
                            <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">Candidates ranked by match percentage</p>
                        </div>
                    </div>
                    <div class="header-right">
                        <span class="application-count">
                            <?php echo count($applications); ?> applications
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Bulk Actions -->
                    <form method="POST" id="bulkForm">
                        <div class="bulk-actions">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--secondary-color); font-weight: 500;">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" style="transform: scale(1.1);"> 
                                    Select All
                                </label>
                                <select name="bulk_action" class="form-control form-select" style="width: auto; min-width: 180px;">
                                    <option value="">Bulk Actions</option>
                                    <option value="approve_selected">✅ Approve Selected</option>
                                    <option value="reject_selected">❌ Reject Selected</option>
                                    <option value="interview_selected">📞 Schedule Interview</option>
                                    <option value="hire_selected">👔 Hire Selected</option>
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm">Apply Action</button>
                            </div>
                        </div>

                        <?php if (empty($applications)): ?>
                            <div class="empty-state">
                                <div class="icon">📭</div>
                                <h3>No applications found</h3>
                                <p>No applications match your current filters. Try adjusting your search criteria.</p>
                                <a href="manage-applications.php" class="btn btn-outline" style="margin-top: 1rem;">Clear All Filters</a>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <div class="table-responsive">
                                    <table class="applications-table">
                                        <thead>
                                            <tr>
                                                <th class="checkbox-col">
                                                    <input type="checkbox" id="selectAllHeader" onchange="toggleSelectAll()" style="transform: scale(1.1);">
                                                </th>
                                                <th class="rank-col">
                                                    <div class="th-content">
                                                        <span>🏆</span>
                                                        <span>Rank</span>
                                                    </div>
                                                </th>
                                                <th class="candidate-col">
                                                    <div class="th-content">
                                                        <span>👤</span>
                                                        <span>Candidate</span>
                                                    </div>
                                                </th>
                                                <th class="job-col">
                                                    <div class="th-content">
                                                        <span>💼</span>
                                                        <span>Position</span>
                                                    </div>
                                                </th>
                                                <th class="match-col">
                                                    <div class="th-content">
                                                        <span>🎯</span>
                                                        <span>Match</span>
                                                    </div>
                                                </th>
                                                <th class="date-col">
                                                    <div class="th-content">
                                                        <span>📅</span>
                                                        <span>Applied</span>
                                                    </div>
                                                </th>
                                                <th class="status-col">
                                                    <div class="th-content">
                                                        <span>📊</span>
                                                        <span>Status</span>
                                                    </div>
                                                </th>
                                                <th class="actions-col">
                                                    <div class="th-content">
                                                        <span>⚙️</span>
                                                        <span>Actions</span>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($applications as $index => $app): ?>
                                                <tr class="application-row">
                                                    <td class="checkbox-col">
                                                        <input type="checkbox" name="selected_applications[]" value="<?php echo $app['id']; ?>" class="app-checkbox" style="transform: scale(1.1);">
                                                    </td>
                                                    <td class="rank-col">
                                                        <?php
                                                        $rankClass = 'ranking-other';
                                                        if ($app['ranking'] == 1) $rankClass = 'ranking-1';
                                                        elseif ($app['ranking'] == 2) $rankClass = 'ranking-2';
                                                        elseif ($app['ranking'] == 3) $rankClass = 'ranking-3';
                                                        ?>
                                                        <div class="ranking-container">
                                                            <span class="ranking-badge <?php echo $rankClass; ?>">
                                                                <?php echo $app['ranking']; ?>
                                                            </span>
                                                            <?php if ($app['ranking'] <= 3): ?>
                                                                <div class="ranking-label">
                                                                    <?php 
                                                                    echo $app['ranking'] == 1 ? 'Best Match' : 
                                                                         ($app['ranking'] == 2 ? 'Excellent' : 'Great');
                                                                    ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="candidate-col">
                                                        <div class="candidate-info">
                                                            <div class="candidate-avatar">
                                                                <?php echo strtoupper(substr($app['candidate_name'], 0, 1)); ?>
                                                            </div>
                                                            <div class="candidate-details">
                                                                <div class="candidate-name">
                                                                    <?php echo htmlspecialchars($app['candidate_name']); ?>
                                                                </div>
                                                                <div class="candidate-email">
                                                                    📧 <?php echo htmlspecialchars($app['candidate_email']); ?>
                                                                </div>
                                                                <?php 
                                                                // Extract phone from JSON contact info
                                                                if (!empty($app['extracted_contact'])) {
                                                                    $contact = json_decode($app['extracted_contact'], true);
                                                                    if (isset($contact['phone']) && !empty($contact['phone'])) {
                                                                        echo '<div class="candidate-phone">📱 ' . htmlspecialchars($contact['phone']) . '</div>';
                                                                    }
                                                                }
                                                                ?>
                                                                <!-- Role Indicator -->
                                                                <div class="role-indicator role-<?php echo $app['current_role']; ?>">
                                                                    <?php 
                                                                    echo ucfirst($app['current_role']); 
                                                                    if ($app['current_role'] === 'employee' && !empty($app['current_department'])) {
                                                                        echo ' - ' . $app['current_department'];
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="job-col">
                                                        <div class="job-info">
                                                            <div class="job-title">
                                                                <?php echo htmlspecialchars($app['job_title']); ?>
                                                            </div>
                                                            <div class="job-department">
                                                                🏢 <?php echo htmlspecialchars($app['department']); ?>
                                                            </div>
                                                            <div class="job-level">
                                                                📈 <?php echo ucfirst($app['experience_level']); ?> Level
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="match-col">
                                                        <div class="match-container">
                                                            <?php 
                                                            $percentage = is_numeric($app['match_percentage']) ? floatval($app['match_percentage']) : 0;
                                                            $matchClass = '';
                                                            if ($percentage >= 90) $matchClass = 'match-excellent';
                                                            elseif ($percentage >= 70) $matchClass = 'match-good';
                                                            elseif ($percentage >= 50) $matchClass = 'match-fair';
                                                            else $matchClass = 'match-poor';
                                                            ?>
                                                            <span class="match-score <?php echo $matchClass; ?>">
                                                                <?php echo $percentage > 0 ? number_format($percentage, 1) . '%' : 'N/A'; ?>
                                                            </span>
                                                            <?php if ($percentage > 0): ?>
                                                            <div class="match-bar">
                                                                <div class="match-fill <?php echo $matchClass; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="date-col">
                                                        <div class="date-info">
                                                            <div class="date-main">
                                                                <?php echo date('M j, Y', strtotime($app['applied_at'])); ?>
                                                            </div>
                                                            <div class="date-time">
                                                                🕐 <?php echo date('g:i A', strtotime($app['applied_at'])); ?>
                                                            </div>
                                                            <div class="date-relative">
                                                                <?php 
                                                                $days = floor((time() - strtotime($app['applied_at'])) / 86400);
                                                                echo $days == 0 ? 'Today' : ($days == 1 ? 'Yesterday' : $days . ' days ago');
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="status-col">
                                                        <?php 
                                                        $currentStatus = $app['status'];
                                                        ?>
                                                        <span class="status-badge status-<?php echo str_replace('_', '-', $currentStatus); ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', $currentStatus)); ?>
                                                        </span>
                                                    </td>
                                                    <td class="actions-col">
                                                        <div class="action-buttons">
                                                            <button type="button" class="btn btn-primary btn-xs" 
                                                                    onclick="openStatusModal(<?php echo $app['id']; ?>, '<?php echo $currentStatus; ?>', '<?php echo addslashes($app['hr_notes'] ?? ''); ?>')">
                                                                ✏️ Update
                                                            </button>
                                                            
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Status Update Modal -->
            <div id="statusModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Update Application Status</h3>
                        <span class="close" onclick="closeModal()">&times;</span>
                    </div>
                    <form method="POST" id="statusForm">
                        <div class="modal-body">
                            <input type="hidden" name="application_id" id="modalApplicationId">
                            <input type="hidden" name="update_status" value="1">
                            
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" id="modalStatus" class="form-control form-select">
                                    <option value="pending">Pending</option>
                                    <option value="selected">Selected</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="waiting_interview">Waiting Interview</option>
                                    <option value="interview_completed">Interview Completed</option>
                                    <option value="offer_sent">Offer Sent</option>
                                    <option value="offer_accepted">Offer Accepted</option>
                                    <option value="offer_rejected">Offer Rejected</option>
                                    <option value="hired">Hired (Convert to Employee)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">HR Notes</label>
                                <textarea name="notes" id="modalNotes" class="form-control" rows="4" 
                                          placeholder="Add notes about this candidate..."></textarea>
                            </div>
                            
                            <div id="hireWarning" class="alert alert-success" style="display: none;">
                                <span>✅</span>
                                <div>
                                    <strong>Note:</strong> Selecting "Hired" will automatically convert this candidate to an employee and create their onboarding tasks, training modules, and required documents.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('active');
            }

            function toggleSelectAll() {
                const selectAll = document.getElementById('selectAll');
                const selectAllHeader = document.getElementById('selectAllHeader');
                const checkboxes = document.querySelectorAll('.app-checkbox');
                
                // Sync both select all checkboxes
                if (selectAll) selectAll.checked = selectAllHeader ? selectAllHeader.checked : selectAll.checked;
                if (selectAllHeader) selectAllHeader.checked = selectAll ? selectAll.checked : selectAllHeader.checked;
                
                const isChecked = selectAll ? selectAll.checked : (selectAllHeader ? selectAllHeader.checked : false);
                
                checkboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            }

            function openStatusModal(applicationId, currentStatus, currentNotes) {
                document.getElementById('modalApplicationId').value = applicationId;
                document.getElementById('modalStatus').value = currentStatus;
                document.getElementById('modalNotes').value = currentNotes;
                document.getElementById('statusModal').style.display = 'flex';
                
                // Show/hide hire warning
                toggleHireWarning(currentStatus);
            }

            function closeModal() {
                document.getElementById('statusModal').style.display = 'none';
            }

            function toggleHireWarning(status) {
                const hireWarning = document.getElementById('hireWarning');
                if (status === 'hired') {
                    hireWarning.style.display = 'flex';
                } else {
                    hireWarning.style.display = 'none';
                }
            }

            // Add event listener for status change
            document.getElementById('modalStatus').addEventListener('change', function() {
                toggleHireWarning(this.value);
            });

            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('statusModal');
                if (event.target == modal) {
                    closeModal();
                }
            }

            function exportApplications() {
                // Create CSV export
                const table = document.querySelector('.applications-table');
                if (!table) return;
                
                const rows = table.querySelectorAll('tr');
                let csv = [];
                
                // Add header row
                const headerCols = rows[0].querySelectorAll('th');
                const headerData = [];
                headerCols.forEach((col, index) => {
                    if (index > 0 && index < headerCols.length - 1) { // Skip checkbox and actions columns
                        headerData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
                    }
                });
                csv.push(headerData.join(','));
                
                // Add data rows
                for (let i = 1; i < rows.length; i++) {
                    const cols = rows[i].querySelectorAll('td');
                    const rowData = [];
                    cols.forEach((col, index) => {
                        if (index > 0 && index < cols.length - 1) { // Skip checkbox and actions columns
                            rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
                        }
                    });
                    if (rowData.length > 0) {
                        csv.push(rowData.join(','));
                    }
                }
                
                const csvContent = csv.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'applications_' + new Date().toISOString().slice(0, 10) + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            // Auto-hide alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (!alert.id || alert.id !== 'hireWarning') { // Don't auto-hide the hire warning
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 300);
                    }
                });
            }, 5000);

            // Enhanced bulk form validation
            document.getElementById('bulkForm').addEventListener('submit', function(e) {
                const selectedCheckboxes = document.querySelectorAll('.app-checkbox:checked');
                const bulkAction = document.querySelector('select[name="bulk_action"]').value;
                
                if (selectedCheckboxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one application.');
                    return;
                }
                
                if (!bulkAction) {
                    e.preventDefault();
                    alert('Please select an action to perform.');
                    return;
                }
                
                // Special confirmation for hire action
                if (bulkAction === 'hire_selected') {
                    const confirmed = confirm(`Are you sure you want to hire ${selectedCheckboxes.length} selected candidate(s)? This will convert them to employees and create their onboarding materials.`);
                    if (!confirmed) {
                        e.preventDefault();
                        return;
                    }
                }
                
                // Show processing indicator
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Processing...';
                submitBtn.disabled = true;
                
                // Re-enable button after form submission (in case of errors)
                setTimeout(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });

            // Status form validation
            document.getElementById('statusForm').addEventListener('submit', function(e) {
                const status = document.getElementById('modalStatus').value;
                
                if (status === 'hired') {
                    const confirmed = confirm('Are you sure you want to hire this candidate? This will convert them to an employee and create their onboarding materials.');
                    if (!confirmed) {
                        e.preventDefault();
                        return;
                    }
                }
                
                // Show processing indicator
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Processing...';
                submitBtn.disabled = true;
                
                // Re-enable button after form submission (in case of errors)
                setTimeout(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });

            // Initialize page
            document.addEventListener('DOMContentLoaded', function() {
                // Add loading states to action buttons
                const actionButtons = document.querySelectorAll('.action-buttons .btn');
                actionButtons.forEach(button => {
                    if (button.href && button.href.includes('view-application.php')) {
                        button.addEventListener('click', function() {
                            this.innerHTML = '⏳ Loading...';
                            this.style.pointerEvents = 'none';
                        });
                    }
                });
                
                // Add hover effects to rows
                const rows = document.querySelectorAll('.application-row');
                rows.forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = 'rgba(255, 107, 53, 0.03)';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = 'white';
                    });
                });
                
                // Enhanced checkbox interactions
                const checkboxes = document.querySelectorAll('.app-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const row = this.closest('tr');
                        if (this.checked) {
                            row.style.backgroundColor = 'rgba(255, 107, 53, 0.05)';
                        } else {
                            row.style.backgroundColor = 'white';
                        }
                    });
                });
            });

            // Add smooth scrolling for better UX
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            </script>
        </div>
    </div>
</body>
</html>