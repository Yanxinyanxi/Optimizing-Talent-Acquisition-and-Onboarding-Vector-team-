<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Ensure HR access only
requireRole('hr');

// Get dashboard statistics
try {
    // Total applications
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM applications");
    $total_applications = $stmt->fetch()['total'];
    
    // Pending applications
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM applications WHERE status = 'pending'");
    $pending_applications = $stmt->fetch()['pending'];
    
    // Hired candidates
    $stmt = $pdo->query("SELECT COUNT(*) as hired FROM applications WHERE status = 'hired'");
    $hired_candidates = $stmt->fetch()['hired'];
    
    // Active employees
    $stmt = $pdo->query("SELECT COUNT(*) as employees FROM users WHERE role = 'employee' AND status = 'active'");
    $active_employees = $stmt->fetch()['employees'];
    
    // Get all applications with details
    $applications = getApplicationsForHR();
    
    // Get job positions for filtering
    $job_positions = getJobPositions();
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle status updates
if ($_POST && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'];
    $new_status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    if (updateApplicationStatus($application_id, $new_status, $notes)) {
        $success = "Application status updated successfully!";
        // Refresh applications data
        $applications = getApplicationsForHR();
    } else {
        $error = "Failed to update application status.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - Kabel HR System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="hr-dashboard.php" class="logo">
                <div class="logo-icon">K</div>
                <div class="logo-text">Kabel HR</div>
            </a>
            <div class="user-info">
                <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="includes/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <h1 style="color: white; margin-bottom: 2rem; text-align: center;">
            HR Dashboard - Talent Acquisition Control Center
        </h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Dashboard Statistics -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_applications; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_applications; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $hired_candidates; ?></div>
                <div class="stat-label">Successful Hires</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_employees; ?></div>
                <div class="stat-label">Active Employees</div>
            </div>
        </div>

        <!-- Job Position Filter -->
        <div class="card">
            <div class="card-header">
                <i>🎯</i> Filter Applications by Job Position
            </div>
            <div class="card-body">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label for="job_filter" class="form-label">Job Position</label>
                        <select name="job_id" id="job_filter" class="form-control form-select">
                            <option value="">All Positions</option>
                            <?php foreach ($job_positions as $job): ?>
                                <option value="<?php echo $job['id']; ?>" 
                                        <?php echo (isset($_GET['job_id']) && $_GET['job_id'] == $job['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="hr-dashboard.php" class="btn btn-outline">Clear</a>
                </form>
            </div>
        </div>

        <!-- Applications Management -->
        <div class="card">
            <div class="card-header">
                <i>📋</i> Candidate Applications Management
            </div>
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6c757d;">
                        <h3>No applications found</h3>
                        <p>There are currently no applications to review.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Job Position</th>
                                    <th>Match %</th>
                                    <th>Applied Date</th>
                                    <th>Current Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong><br>
                                            <small style="color: #6c757d;"><?php echo htmlspecialchars($app['candidate_email']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                            <small style="color: #6c757d;"><?php echo htmlspecialchars($app['department']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $match_class = '';
                                            if ($app['match_percentage'] >= 80) $match_class = 'match-high';
                                            elseif ($app['match_percentage'] >= 60) $match_class = 'match-medium';
                                            else $match_class = 'match-low';
                                            ?>
                                            <span class="match-percentage <?php echo $match_class; ?>">
                                                <?php echo number_format($app['match_percentage'], 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($app['applied_at'])); ?><br>
                                            <small style="color: #6c757d;"><?php echo date('g:i A', strtotime($app['applied_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo str_replace('_', '-', $app['status']); ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline-block;">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <select name="status" class="form-control form-select" style="margin-bottom: 0.5rem; min-width: 150px;" onchange="this.form.submit()">
                                                    <option value="pending" <?php echo ($app['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="selected" <?php echo ($app['status'] == 'selected') ? 'selected' : ''; ?>>Selected</option>
                                                    <option value="rejected" <?php echo ($app['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                    <option value="waiting_interview" <?php echo ($app['status'] == 'waiting_interview') ? 'selected' : ''; ?>>Waiting Interview</option>
                                                    <option value="interview_completed" <?php echo ($app['status'] == 'interview_completed') ? 'selected' : ''; ?>>Interview Completed</option>
                                                    <option value="offer_sent" <?php echo ($app['status'] == 'offer_sent') ? 'selected' : ''; ?>>Offer Sent</option>
                                                    <option value="offer_accepted" <?php echo ($app['status'] == 'offer_accepted') ? 'selected' : ''; ?>>Offer Accepted</option>
                                                    <option value="offer_rejected" <?php echo ($app['status'] == 'offer_rejected') ? 'selected' : ''; ?>>Offer Rejected</option>
                                                    <option value="hired" <?php echo ($app['status'] == 'hired') ? 'selected' : ''; ?>>Hired</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            <br>
                                            <a href="uploads/resumes/<?php echo htmlspecialchars($app['resume_filename']); ?>" 
                                               target="_blank" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                                                📄 View Resume
                                            </a>
                                            <?php if (!empty($app['hr_notes'])): ?>
                                                <br><br>
                                                <small style="color: #6c757d;">
                                                    <strong>Notes:</strong> <?php echo htmlspecialchars($app['hr_notes']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Employee Onboarding Progress -->
        <div class="card">
            <div class="card-header">
                <i>🚀</i> Employee Onboarding Progress
            </div>
            <div class="card-body">
                <?php
                // Get all employees and their onboarding progress
                try {
                    $stmt = $pdo->query("
                        SELECT u.id, u.full_name, u.email,
                               COUNT(eo.id) as total_tasks,
                               SUM(CASE WHEN eo.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                        FROM users u
                        LEFT JOIN employee_onboarding eo ON u.id = eo.employee_id
                        WHERE u.role = 'employee' AND u.status = 'active'
                        GROUP BY u.id, u.full_name, u.email
                        ORDER BY u.full_name
                    ");
                    $employees = $stmt->fetchAll();
                } catch(PDOException $e) {
                    $employees = [];
                }
                ?>

                <?php if (empty($employees)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6c757d;">
                        <h3>No employees found</h3>
                        <p>No employees are currently in the onboarding process.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Email</th>
                                    <th>Onboarding Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                    <?php
                                    $total = max(1, $employee['total_tasks']); // Avoid division by zero
                                    $completed = $employee['completed_tasks'];
                                    $progress = round(($completed / $total) * 100);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($employee['email']); ?>
                                        </td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo $progress; ?>%">
                                                    <?php echo $progress; ?>%
                                                </div>
                                            </div>
                                            <small style="color: #6c757d;">
                                                <?php echo $completed; ?> of <?php echo $total; ?> tasks completed
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($progress == 100): ?>
                                                <span class="status-badge status-completed">Completed</span>
                                            <?php elseif ($progress > 0): ?>
                                                <span class="status-badge status-in-progress">In Progress</span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending">Not Started</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <i>⚡</i> Quick Actions
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="candidate-upload.php" class="btn btn-primary" style="padding: 1.5rem; text-align: center;">
                        📤 Upload Resume<br>
                        <small>Add new candidate</small>
                    </a>
                    <button onclick="exportApplications()" class="btn btn-secondary" style="padding: 1.5rem; text-align: center;">
                        📊 Export Data<br>
                        <small>Download applications</small>
                    </button>
                    <button onclick="generateReport()" class="btn btn-warning" style="padding: 1.5rem; text-align: center;">
                        📈 Generate Report<br>
                        <small>Hiring analytics</small>
                    </button>
                    <button onclick="manageJobs()" class="btn btn-success" style="padding: 1.5rem; text-align: center;">
                        💼 Manage Jobs<br>
                        <small>Add/edit positions</small>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>