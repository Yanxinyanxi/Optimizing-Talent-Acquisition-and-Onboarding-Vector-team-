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
        
        /* Include all the sidebar and main styles from your original file */
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
        
        .candidate-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .candidate-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .candidate-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .candidate-body {
            padding: 2rem;
        }
        
        .match-score {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .match-excellent {
            background: rgba(34, 197, 94, 0.1);
            color: #166534;
            border: 2px solid rgba(34, 197, 94, 0.3);
        }
        
        .match-good {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 2px solid rgba(245, 158, 11, 0.3);
        }
        
        .match-fair {
            background: rgba(249, 115, 22, 0.1);
            color: #9a3412;
            border: 2px solid rgba(249, 115, 22, 0.3);
        }
        
        .match-poor {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 2px solid rgba(239, 68, 68, 0.3);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-section h4 {
            color: var(--secondary-color);
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .info-section p {
            color: #6c757d;
            line-height: 1.5;
            margin: 0;
        }
        
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .stat-box.excellent {
            border-left-color: #10b981;
        }
        
        .stat-box.good {
            border-left-color: #f59e0b;
        }
        
        .stat-box.fair {
            border-left-color: #f97316;
        }
        
        .stat-box.poor {
            border-left-color: #ef4444;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        .mobile-toggle {
            display: none;
        }
        
        .match-label {
    font-size: 0.75rem;
    color: #6c757d;
    font-weight: 500;
    margin-top: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
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
                <a href="hr-dashboard.php" >
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
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <span>⚠️</span>
                    <div><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
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
<td class="match-col" style="text-align: center;">
    <?php 
    $percentage = is_numeric($app['match_percentage']) ? floatval($app['match_percentage']) : 0;
    ?>
    <span style="font-size: 1.3rem; font-weight: 800; color: #2B4C8C;">
        <?php echo $percentage > 0 ? number_format($percentage, 1) . '%' : 'N/A'; ?>
    </span>
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
    // Debug: Let's see what status we're getting
    $currentStatus = $app['status'];
    // Uncomment the next line temporarily to debug:
    // echo "<!-- Debug Status for " . $app['candidate_name'] . ": '" . $currentStatus . "' -->";
    ?>
    <span class="status-badge status-<?php echo str_replace('_', '-', $currentStatus); ?>">
        
        <?php echo ucwords(str_replace('_', ' ', $currentStatus)); ?>
    </span>
    
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
                                    <option value="hired">Hired</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">HR Notes</label>
                                <textarea name="notes" id="modalNotes" class="form-control" rows="4" 
                                          placeholder="Add notes about this candidate..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>

            <style>
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
            </style>

            <script>
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
            }

            function closeModal() {
                document.getElementById('statusModal').style.display = 'none';
            }

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
                
                rows.forEach(row => {
                    const cols = row.querySelectorAll('td, th');
                    const rowData = [];
                    cols.forEach((col, index) => {
                        if (index > 0 && index < cols.length - 1) { // Skip checkbox and actions columns
                            rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
                        }
                    });
                    if (rowData.length > 0) {
                        csv.push(rowData.join(','));
                    }
                });
                
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
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);
            </script>
        </div>
    </div>
</body>
</html>