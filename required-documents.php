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

// Create employee_documents table if it doesn't exist (for demo purposes)
$connection->query("
    CREATE TABLE IF NOT EXISTS employee_documents (
        id int(11) NOT NULL AUTO_INCREMENT,
        employee_id int(11) NOT NULL,
        document_name varchar(255) NOT NULL,
        document_type varchar(100) DEFAULT NULL,
        file_path varchar(500) DEFAULT NULL,
        file_size int(11) DEFAULT NULL,
        status enum('pending','submitted','approved','rejected') DEFAULT 'pending',
        is_required tinyint(1) DEFAULT 0,
        description text DEFAULT NULL,
        uploaded_at timestamp DEFAULT CURRENT_TIMESTAMP,
        reviewed_at timestamp NULL DEFAULT NULL,
        reviewer_notes text DEFAULT NULL,
        PRIMARY KEY (id),
        KEY employee_id (employee_id),
        UNIQUE KEY unique_employee_document (employee_id, document_type)
    )
");

// Get employee documents
try {
    // First, check which required documents already exist for this employee
    $stmt = $connection->prepare("SELECT document_type FROM employee_documents WHERE employee_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $existing_docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $existing_types = array_column($existing_docs, 'document_type');
    
    // Define required documents
    $required_docs = [
        ['Employment Contract', 'contract', 'Your employment contract and terms of service', 1],
        ['Personal Information Form', 'personal_form', 'Complete personal details and emergency contacts', 1],
        ['Bank Details Form', 'bank_form', 'Banking information for salary processing', 1],
        ['ID Copy', 'identification', 'Copy of your identification document (IC/Passport)', 1],
        ['Educational Certificates', 'education', 'Copies of your educational qualifications', 1],
        ['Medical Certificate', 'medical', 'Health clearance certificate', 0],
        ['Previous Employment Letter', 'employment_history', 'Letter from previous employer (if applicable)', 0]
    ];
    
    // Only insert documents that don't already exist
    foreach ($required_docs as $doc) {
        if (!in_array($doc[1], $existing_types)) {
            $stmt = $connection->prepare("
                INSERT INTO employee_documents (employee_id, document_name, document_type, description, is_required, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("isssi", $user_id, $doc[0], $doc[1], $doc[2], $doc[3]);
            $stmt->execute();
        }
    }
    
    // Get all documents for the employee
    $stmt = $connection->prepare("
        SELECT 
            id,
            document_name,
            document_type,
            file_path,
            file_size,
            status,
            is_required,
            description,
            uploaded_at,
            reviewed_at,
            reviewer_notes
        FROM employee_documents 
        WHERE employee_id = ?
        ORDER BY is_required DESC, document_name
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Separate required and optional documents
    $required_documents = [];
    $optional_documents = [];
    foreach ($documents as $doc) {
        if ($doc['is_required'] == 1) {
            $required_documents[] = $doc;
        } else {
            $optional_documents[] = $doc;
        }
    }
    
    // Calculate completion stats
    $total_required = count($required_documents);
    $submitted_required = 0;
    $approved_required = 0;
    
    foreach ($required_documents as $doc) {
        if (in_array($doc['status'], ['submitted', 'approved'])) {
            $submitted_required++;
        }
        if ($doc['status'] == 'approved') {
            $approved_required++;
        }
    }
    
    $completion_percentage = $total_required > 0 ? round(($approved_required / $total_required) * 100) : 0;
    $submission_percentage = $total_required > 0 ? round(($submitted_required / $total_required) * 100) : 0;
    
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $documents = [];
    $required_documents = [];
    $optional_documents = [];
    $completion_percentage = 0;
}

// Handle file upload
if ($_POST && isset($_POST['upload_document'])) {
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === 0) {
        $file = $_FILES['document_file'];
        $document_id = (int)$_POST['document_id'];
        
        // Validate file
        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            $error = "Only PDF, DOC, DOCX, JPG, JPEG, and PNG files are allowed.";
        } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $error = "File size must be less than 10MB.";
        } else {
            // Verify document belongs to this employee
            $stmt = $connection->prepare("SELECT id FROM employee_documents WHERE id = ? AND employee_id = ?");
            $stmt->bind_param("ii", $document_id, $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                // Create upload directory if it doesn't exist
                $upload_dir = "uploads/documents/employee_" . $user_id . "/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $file['name']);
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    try {
                        $stmt = $connection->prepare("
                            UPDATE employee_documents 
                            SET file_path = ?, file_size = ?, status = 'submitted', uploaded_at = NOW() 
                            WHERE id = ? AND employee_id = ?
                        ");
                        $stmt->bind_param("siii", $file_path, $file['size'], $document_id, $user_id);
                        
                        if ($stmt->execute()) {
                            $success = "Document uploaded successfully! It will be reviewed by HR.";
                            header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
                            exit;
                        }
                    } catch(Exception $e) {
                        $error = "Failed to save document: " . $e->getMessage();
                        if (file_exists($file_path)) {
                            unlink($file_path); // Remove uploaded file on database error
                        }
                    }
                } else {
                    $error = "Failed to upload file.";
                }
            } else {
                $error = "Invalid document selected.";
            }
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Handle URL parameters for messages
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// Get file icon based on extension
function getFileIcon($file_path) {
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf': return '📄';
        case 'doc':
        case 'docx': return '📝';
        case 'jpg':
        case 'jpeg':
        case 'png': return '🖼️';
        default: return '📁';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Required Documents - Vector HR System</title>
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
        
        /* Progress Overview */
        .progress-overview {
            background: var(--kabel-gradient);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 2rem;
        }
        
        .progress-info h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .progress-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            position: relative;
            overflow: hidden;
        }
        
        .progress-circle::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 50%;
            background: conic-gradient(
                rgba(255,255,255,0.8) 0deg,
                rgba(255,255,255,0.8) calc(var(--progress) * 3.6deg),
                rgba(255,255,255,0.2) calc(var(--progress) * 3.6deg),
                rgba(255,255,255,0.2) 360deg
            );
            animation: progressSpin 2s ease-in-out;
        }
        
        @keyframes progressSpin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
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
        
        /* Document Cards */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .document-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .document-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .document-card.pending {
            border-left-color: #6c757d;
        }
        
        .document-card.submitted {
            border-left-color: var(--warning-color);
            background: rgba(255, 193, 7, 0.02);
        }
        
        .document-card.approved {
            border-left-color: var(--success-color);
            background: rgba(40, 167, 69, 0.02);
        }
        
        .document-card.rejected {
            border-left-color: var(--danger-color);
            background: rgba(220, 53, 69, 0.02);
        }
        
        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .document-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }
        
        .document-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .document-status.pending {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .document-status.submitted {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }
        
        .document-status.approved {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .document-status.rejected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .document-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        
        .document-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .file-icon {
            font-size: 2rem;
        }
        
        .file-details {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.25rem;
        }
        
        .file-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .document-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .upload-section {
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }
        
        .upload-section:hover {
            border-color: var(--primary-color);
            background: rgba(255, 107, 53, 0.02);
        }
        
        .upload-section.dragover {
            border-color: var(--primary-color);
            background: rgba(255, 107, 53, 0.05);
        }
        
        /* Progress Bar */
        .progress {
            background: #e9ecef;
            border-radius: 10px;
            height: 25px;
            overflow: hidden;
            margin: 1rem 0;
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
        
        .btn-warning {
            background: var(--warning-color);
            color: #856404;
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
        
        /* File Input Styling */
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-button {
            background: var(--kabel-gradient);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .file-input-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.3);
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
            max-width: 500px;
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
        
        /* Clean Up Section */
        .cleanup-section {
            background: rgba(255, 193, 7, 0.1);
            border: 2px solid rgba(255, 193, 7, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .cleanup-btn {
            background: var(--warning-color);
            color: #856404;
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
            
            .documents-grid {
                grid-template-columns: 1fr;
            }
            
            .progress-overview {
                flex-direction: column;
                text-align: center;
            }
            
            .progress-stats {
                justify-content: center;
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
            <h1 class="page-title">📄 Required Documents</h1>
            <div class="topbar-actions">
                <span style="color: #6c757d; font-size: 0.9rem;">
                    Upload and manage your onboarding documents
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

            <!-- Show cleanup option if duplicates detected -->
            <?php
            $duplicate_count = 0;
            $stmt = $connection->prepare("
                SELECT document_type, COUNT(*) as count 
                FROM employee_documents 
                WHERE employee_id = ? 
                GROUP BY document_type 
                HAVING COUNT(*) > 1
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $duplicates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($duplicates as $dup) {
                $duplicate_count += $dup['count'] - 1;
            }
            ?>

            <?php if ($duplicate_count > 0): ?>
            <div class="cleanup-section">
                <h4 style="color: #856404; margin-bottom: 1rem;">🧹 Database Cleanup Required</h4>
                <p style="margin-bottom: 1rem; color: #6c757d;">
                    We detected <?php echo $duplicate_count; ?> duplicate document entries. Click below to clean them up.
                </p>
                <a href="?cleanup=1" class="btn cleanup-btn">
                    🗑️ Clean Up Duplicates
                </a>
            </div>
            <?php endif; ?>

            <?php 
            // Handle cleanup if requested
            if (isset($_GET['cleanup']) && $_GET['cleanup'] == '1') {
                try {
                    // Keep only the latest entry for each document type
                    $connection->query("
                        DELETE ed1 FROM employee_documents ed1
                        INNER JOIN employee_documents ed2 
                        WHERE ed1.employee_id = $user_id 
                        AND ed2.employee_id = $user_id
                        AND ed1.document_type = ed2.document_type
                        AND ed1.id < ed2.id
                    ");
                    
                    echo '<script>
                        alert("✅ Duplicate documents have been cleaned up!");
                        window.location.href = "' . $_SERVER['PHP_SELF'] . '";
                    </script>';
                } catch(Exception $e) {
                    echo '<script>alert("❌ Error during cleanup: ' . $e->getMessage() . '");</script>';
                }
            }
            ?>

            <!-- Progress Overview -->
            <div class="progress-overview">
                <div class="progress-info">
                    <h2>Document Submission Progress</h2>
                    <p style="opacity: 0.9; margin-bottom: 0;">
                        Upload all required documents to complete your onboarding
                    </p>
                    <div class="progress-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $approved_required; ?></span>
                            <span class="stat-label">Approved</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $submitted_required - $approved_required; ?></span>
                            <span class="stat-label">Under Review</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $total_required - $submitted_required; ?></span>
                            <span class="stat-label">Pending</span>
                        </div>
                    </div>
                </div>
                <div class="progress-circle" style="--progress: <?php echo $completion_percentage; ?>">
                    <?php echo $completion_percentage; ?>%
                </div>
            </div>

            <!-- Overall Progress -->
            <div class="card">
                <div class="card-body">
                    <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">Document Completion Status</h4>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%">
                            <?php echo $completion_percentage; ?>% Complete
                        </div>
                    </div>
                    <p style="color: #6c757d; margin: 0.5rem 0 0 0;">
                        <?php echo $approved_required; ?> of <?php echo $total_required; ?> required documents approved
                    </p>
                </div>
            </div>

            <!-- Required Documents -->
            <div class="card">
                <div class="card-header">
                    <span class="icon">📋</span>
                    <div>
                        <h3>Required Documents</h3>
                        <p style="margin: 0; opacity: 0.9;">Essential documents for your onboarding process</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($required_documents)): ?>
                        <div style="text-align: center; padding: 2rem; color: #6c757d;">
                            <span style="font-size: 3rem;">📋</span>
                            <p>No required documents found. Please contact HR.</p>
                        </div>
                    <?php else: ?>
                        <div class="documents-grid">
                            <?php foreach ($required_documents as $document): ?>
                                <div class="document-card <?php echo $document['status']; ?>">
                                    <div class="document-header">
                                        <div>
                                            <div class="document-title">
                                                <?php echo htmlspecialchars($document['document_name']); ?>
                                            </div>
                                        </div>
                                        <div class="document-status <?php echo $document['status']; ?>">
                                            <?php echo ucfirst($document['status']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="document-description">
                                        <?php echo htmlspecialchars($document['description']); ?>
                                    </div>
                                    
                                    <?php if ($document['file_path']): ?>
                                        <div class="document-info">
                                            <div class="file-icon">
                                                <?php echo getFileIcon($document['file_path']); ?>
                                            </div>
                                            <div class="file-details">
                                                <div class="file-name">
                                                    <?php echo htmlspecialchars(basename($document['file_path'])); ?>
                                                </div>
                                                <div class="file-meta">
                                                    Uploaded: <?php echo date('M j, Y', strtotime($document['uploaded_at'])); ?>
                                                    • Size: <?php echo formatFileSize($document['file_size']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="upload-section" onclick="uploadDocument(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars($document['document_name']); ?>')">
                                            <div style="font-size: 2rem; margin-bottom: 1rem;">📁</div>
                                            <p style="color: #6c757d; margin-bottom: 1rem;">
                                                Click to upload your <?php echo strtolower($document['document_name']); ?>
                                            </p>
                                            <p style="font-size: 0.85rem; color: #6c757d;">
                                                Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="document-actions">
                                        <?php if ($document['status'] == 'approved'): ?>
                                            <div style="color: var(--success-color); display: flex; align-items: center; gap: 0.5rem;">
                                                <span>✅</span>
                                                <span>Document approved</span>
                                            </div>
                                            <?php if ($document['file_path'] && file_exists($document['file_path'])): ?>
                                                <a href="<?php echo $document['file_path']; ?>" target="_blank" class="btn btn-outline btn-sm">
                                                    👁️ View Document
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($document['status'] == 'submitted'): ?>
                                            <div style="color: var(--warning-color); display: flex; align-items: center; gap: 0.5rem;">
                                                <span>⏳</span>
                                                <span>Under review by HR</span>
                                            </div>
                                            <button class="btn btn-primary btn-sm" onclick="uploadDocument(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars($document['document_name']); ?>')">
                                                🔄 Replace Document
                                            </button>
                                        <?php elseif ($document['status'] == 'rejected'): ?>
                                            <div style="color: var(--danger-color); margin-bottom: 1rem;">
                                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                    <span>❌</span>
                                                    <span>Document rejected</span>
                                                </div>
                                                <?php if ($document['reviewer_notes']): ?>
                                                    <div style="font-size: 0.9rem; background: rgba(220, 53, 69, 0.1); padding: 0.75rem; border-radius: 8px;">
                                                        <strong>HR Notes:</strong> <?php echo htmlspecialchars($document['reviewer_notes']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn btn-primary btn-sm" onclick="uploadDocument(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars($document['document_name']); ?>')">
                                                📤 Upload New Document
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm" onclick="uploadDocument(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars($document['document_name']); ?>')">
                                                📤 Upload Document
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Optional Documents -->
            <?php if (!empty($optional_documents)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="icon">📑</span>
                    <div>
                        <h3>Optional Documents</h3>
                        <p style="margin: 0; opacity: 0.9;">Additional documents that may be helpful</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="documents-grid">
                        <?php foreach ($optional_documents as $document): ?>
                            <div class="document-card <?php echo $document['status']; ?>">
                                <div class="document-header">
                                    <div>
                                        <div class="document-title">
                                            <?php echo htmlspecialchars($document['document_name']); ?>
                                        </div>
                                    </div>
                                    <div class="document-status <?php echo $document['status']; ?>">
                                        <?php echo ucfirst($document['status']); ?>
                                    </div>
                                </div>
                                
                                <div class="document-description">
                                    <?php echo htmlspecialchars($document['description']); ?>
                                </div>
                                
                                <?php if ($document['file_path']): ?>
                                    <div class="document-info">
                                        <div class="file-icon">
                                            <?php echo getFileIcon($document['file_path']); ?>
                                        </div>
                                        <div class="file-details">
                                            <div class="file-name">
                                                <?php echo htmlspecialchars(basename($document['file_path'])); ?>
                                            </div>
                                            <div class="file-meta">
                                                Uploaded: <?php echo date('M j, Y', strtotime($document['uploaded_at'])); ?>
                                                • Size: <?php echo formatFileSize($document['file_size']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="upload-section" onclick="uploadDocument(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars($document['document_name']); ?>')">
                                        <div style="font-size: 2rem; margin-bottom: 1rem;">📁</div>
                                        <p style="color: #6c757d; margin-bottom: 1rem;">
                                            Click to upload your <?php echo strtolower($document['document_name']); ?>
                                        </p>
                                        <p style="font-size: 0.85rem; color: #6c757d;">
                                            Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="document-actions">
                                    <?php if ($document['status'] == 'approved'): ?>
                                        <div style="color: var(--success-color); display: flex; align-items: center; gap: 0.5rem;">
                                            <span>✅</span>
                                            <span>Document approved</span>
                                        </div>
                                        <?php if ($document['file_path'] && file_exists($document['file_path'])): ?>
                                            <a href="<?php echo $document['file_path']; ?>" target="_blank" class="btn btn-outline btn-sm">
                                                👁️ View Document
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif ($document['status'] == 'submitted'): ?>
                                        <div style="color: var(--warning-color); display: flex; align-items: center; gap: 0.5rem;">
                                            <span>⏳</span>
                                            <span>Under review by HR</span>
                                        </div>
                                        <button class="btn btn-outline btn-sm" onclick="uploadDocument(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars($document['document_name']); ?>')">
                                            🔄 Replace Document
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline btn-sm" onclick="uploadDocument(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars($document['document_name']); ?>')">
                                            📤 Upload Document
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Document Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Upload Document</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-group">
                    <label class="form-label">Document:</label>
                    <p id="documentNameDisplay" style="color: var(--secondary-color); font-weight: 600;"></p>
                </div>
                <div class="form-group">
                    <label for="document_file" class="form-label">Choose File:</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="document_file" id="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <label for="document_file" class="file-input-button">
                            📁 Choose File
                        </label>
                    </div>
                    <div id="selectedFile" style="margin-top: 0.5rem; color: #6c757d; font-size: 0.9rem;"></div>
                </div>
                <div class="form-group">
                    <small style="color: #6c757d;">
                        Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG<br>
                        Maximum file size: 10MB
                    </small>
                </div>
                <input type="hidden" name="document_id" id="documentId">
                <input type="hidden" name="upload_document" value="1">
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn" onclick="closeModal()" 
                            style="background: #6c757d; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-primary">📤 Upload Document</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        function uploadDocument(documentId, documentName) {
            document.getElementById('documentId').value = documentId;
            document.getElementById('documentNameDisplay').textContent = documentName;
            document.getElementById('modalTitle').textContent = 'Upload ' + documentName;
            document.getElementById('uploadModal').classList.add('show');
            document.getElementById('document_file').value = '';
            document.getElementById('selectedFile').textContent = '';
        }
        
        function closeModal() {
            document.getElementById('uploadModal').classList.remove('show');
        }
        
        // Handle file input change
        document.getElementById('document_file').addEventListener('change', function() {
            const file = this.files[0];
            const selectedFileDiv = document.getElementById('selectedFile');
            const submitBtn = document.querySelector('#uploadForm button[type="submit"]');
            
            if (file) {
                const fileSize = file.size / 1024 / 1024; // Convert to MB
                const fileName = file.name.toLowerCase();
                const allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                const fileExtension = fileName.split('.').pop();
                
                if (!allowedTypes.includes(fileExtension)) {
                    selectedFileDiv.textContent = 'Error: Only PDF, DOC, DOCX, JPG, JPEG, PNG files are allowed.';
                    selectedFileDiv.style.color = '#dc3545';
                    submitBtn.disabled = true;
                    return;
                }
                
                if (fileSize > 10) {
                    selectedFileDiv.textContent = 'Error: File size must be less than 10MB.';
                    selectedFileDiv.style.color = '#dc3545';
                    submitBtn.disabled = true;
                    return;
                }
                
                selectedFileDiv.textContent = `Selected: ${file.name} (${fileSize.toFixed(2)} MB)`;
                selectedFileDiv.style.color = '#28a745';
                submitBtn.disabled = false;
            } else {
                selectedFileDiv.textContent = '';
                submitBtn.disabled = false;
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('uploadModal').addEventListener('click', function(e) {
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
        
        // Add loading state to form when submitted
        document.getElementById('uploadForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Uploading...';
            
            // Re-enable after 10 seconds if form doesn't redirect
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 10000);
        });
        
        // Drag and drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const uploadSections = document.querySelectorAll('.upload-section');
            
            uploadSections.forEach(function(section) {
                section.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });
                
                section.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                });
                
                section.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        const documentCard = this.closest('.document-card');
                        const uploadBtn = documentCard.querySelector('.btn');
                        if (uploadBtn) {
                            uploadBtn.click();
                            
                            // Wait for modal to open, then set the file
                            setTimeout(function() {
                                const fileInput = document.getElementById('document_file');
                                fileInput.files = files;
                                fileInput.dispatchEvent(new Event('change'));
                            }, 100);
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>