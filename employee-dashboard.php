<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Ensure employee access only
requireRole('employee');

$employee_id = $_SESSION['user_id'];

// Get employee onboarding progress
$onboarding_tasks = getOnboardingProgress($employee_id);

// Get training modules
$training_modules = getTrainingModules($employee_id);

// Calculate progress percentages
$total_tasks = count($onboarding_tasks);
$completed_tasks = count(array_filter($onboarding_tasks, function($task) {
    return $task['status'] === 'completed';
}));
$onboarding_percentage = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

$total_training = count($training_modules);
$completed_training = count(array_filter($training_modules, function($module) {
    return $module['status'] === 'completed';
}));
$training_percentage = $total_training > 0 ? round(($completed_training / $total_training) * 100) : 0;

// Handle task updates via AJAX
if ($_POST && isset($_POST['ajax_update_task'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'];
    
    if (updateTaskStatus($employee_id, $task_id, $status)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Kabel HR System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="employee-dashboard.php" class="logo">
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
            Welcome to Your Onboarding Journey
        </h1>

        <!-- Progress Overview -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $onboarding_percentage; ?>%</div>
                <div class="stat-label">Onboarding Complete</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_tasks; ?>/<?php echo $total_tasks; ?></div>
                <div class="stat-label">Tasks Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $training_percentage; ?>%</div>
                <div class="stat-label">Training Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_training; ?>/<?php echo $total_training; ?></div>
                <div class="stat-label">Modules Done</div>
            </div>
        </div>

        <!-- Onboarding Checklist -->
        <div class="card">
            <div class="card-header">
                <i>✅</i> Your Onboarding Checklist
            </div>
            <div class="card-body">
                <div style="margin-bottom: 1.5rem;">
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo $onboarding_percentage; ?>%">
                            <?php echo $onboarding_percentage; ?>% Complete
                        </div>
                    </div>
                </div>

                <?php if (empty($onboarding_tasks)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6c757d;">
                        <h3>🎉 All Set!</h3>
                        <p>You have completed all onboarding tasks. Welcome to the team!</p>
                    </div>
                <?php else: ?>
                    <div class="onboarding-tasks">
                        <?php foreach ($onboarding_tasks as $index => $task): ?>
                            <div class="task-item" style="display: flex; align-items: start; padding: 1rem; margin-bottom: 1rem; 
                                 background: <?php echo $task['status'] === 'completed' ? 'rgba(40, 167, 69, 0.1)' : 'rgba(255, 107, 53, 0.05)'; ?>; 
                                 border-radius: 12px; border-left: 4px solid <?php echo $task['status'] === 'completed' ? '#28a745' : '#ffc107'; ?>;">
                                
                                <div style="margin-right: 1rem; margin-top: 0.25rem;">
                                    <input type="checkbox" 
                                           class="task-checkbox" 
                                           data-task-id="<?php echo $task['task_id'] ?? $index; ?>"
                                           data-employee-id="<?php echo $employee_id; ?>"
                                           <?php echo $task['status'] === 'completed' ? 'checked' : ''; ?>
                                           style="transform: scale(1.2);">
                                </div>
                                
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 0.5rem 0; color: var(--secondary-color);">
                                        <?php echo htmlspecialchars($task['task_name']); ?>
                                    </h4>
                                    <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($task['description']); ?>
                                    </p>
                                    
                                    <?php if ($task['status'] === 'completed' && $task['completed_at']): ?>
                                        <small style="color: #28a745; font-weight: 600;">
                                            ✓ Completed on <?php echo date('M j, Y', strtotime($task['completed_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="margin-left: 1rem;">
                                    <span class="status-badge status-<?php echo $task['status']; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Training Modules -->
        <div class="card">
            <div class="card-header">
                <i>📚</i> Your Training Modules
            </div>
            <div class="card-body">
                <?php if (empty($training_modules)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6c757d;">
                        <h3>No training modules assigned</h3>
                        <p>Training modules will be assigned based on your role and department.</p>
                    </div>
                <?php else: ?>
                    <div class="training-modules">
                        <?php foreach ($training_modules as $module): ?>
                            <div class="module-item" style="display: flex; align-items: center; padding: 1.5rem; margin-bottom: 1rem; 
                                 background: white; border-radius: 12px; box-shadow: var(--box-shadow); border-left: 4px solid var(--primary-color);">
                                
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 0.5rem 0; color: var(--secondary-color);">
                                        <?php echo htmlspecialchars($module['module_name']); ?>
                                    </h4>
                                    <p style="margin: 0 0 0.5rem 0; color: #6c757d;">
                                        <?php echo htmlspecialchars($module['description']); ?>
                                    </p>
                                    <small style="color: var(--primary-color); font-weight: 600;">
                                        Duration: <?php echo $module['duration_hours']; ?> hours
                                    </small>
                                </div>
                                
                                <div style="margin-left: 2rem; text-align: center;">
                                    <div class="progress" style="width: 100px; margin-bottom: 0.5rem;">
                                        <div class="progress-bar" style="width: <?php echo $module['progress_percentage']; ?>%">
                                            <?php echo $module['progress_percentage']; ?>%
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo str_replace('_', '-', $module['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $module['status'])); ?>
                                    </span>
                                </div>
                                
                                <div style="margin-left: 1rem;">
                                    <?php if ($module['status'] === 'not_started'): ?>
                                        <button class="btn btn-primary" onclick="startTraining('<?php echo $module['module_name']; ?>')">
                                            Start
                                        </button>
                                    <?php elseif ($module['status'] === 'in_progress'): ?>
                                        <button class="btn btn-warning" onclick="continueTraining('<?php echo $module['module_name']; ?>')">
                                            Continue
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-success" disabled>
                                            ✓ Complete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Help & Support -->
        <div class="card">
            <div class="card-header">
                <i>💬</i> Need Help? Chat with our Support Bot
            </div>
            <div class="card-body">
                <div style="text-align: center; padding: 2rem;">
                    <p style="margin-bottom: 1.5rem; color: #6c757d;">
                        Have questions about your onboarding process? Our AI assistant is here to help 24/7!
                    </p>
                    <button id="chatbot-button" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                        💬 Start Chat
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Chatbot Widget -->
    <div class="chatbot-container">
        <button id="chatbot-button" class="chatbot-button">
            💬
        </button>
        
        <div id="chatbot-window" class="chatbot-window">
            <div class="chatbot-header">
                Kabel HR Assistant
            </div>
            <div id="chatbot-messages" class="chatbot-messages">
                <!-- Messages will be added here -->
            </div>
            <div class="chatbot-input">
                <input type="text" id="chatbot-input" placeholder="Type your question..." />
                <button id="chatbot-send">➤</button>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Task checkbox handling
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.task-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const taskId = this.dataset.taskId;
                    const employeeId = this.dataset.employeeId;
                    const status = this.checked ? 'completed' : 'pending';
                    
                    // Send AJAX request
                    const formData = new FormData();
                    formData.append('ajax_update_task', '1');
                    formData.append('task_id', taskId);
                    formData.append('employee_id', employeeId);
                    formData.append('status', status);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('Task updated successfully!', 'success');
                            updateProgressDisplay();
                        } else {
                            showAlert('Failed to update task', 'danger');
                            // Revert checkbox
                            this.checked = !this.checked;
                        }
                    })
                    .catch(error => {
                        showAlert('Error updating task', 'danger');
                        // Revert checkbox
                        this.checked = !this.checked;
                    });
                });
            });
        });
        
        // Update progress display
        function updateProgressDisplay() {
            const checkboxes = document.querySelectorAll('.task-checkbox');
            const total = checkboxes.length;
            const completed = document.querySelectorAll('.task-checkbox:checked').length;
            const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
            
            // Update progress bar
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = percentage + '%';
                progressBar.textContent = percentage + '% Complete';
            }
            
            // Update stat cards
            const statCards = document.querySelectorAll('.stat-number');
            if (statCards[0]) statCards[0].textContent = percentage + '%';
            if (statCards[1]) statCards[1].textContent = completed + '/' + total;
        }
        
        // Training module functions
        function startTraining(moduleName) {
            showAlert(`Starting training module: ${moduleName}`, 'info');
            // In a real implementation, this would redirect to the training content
        }
        
        function continueTraining(moduleName) {
            showAlert(`Continuing training module: ${moduleName}`, 'info');
            // In a real implementation, this would redirect to the training content
        }
        
        // Initialize progress animations
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>