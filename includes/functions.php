<?php
require_once 'db.php';

// File upload function for resumes
function uploadResume($file, $candidate_id, $job_id) {
    $target_dir = "uploads/resumes/";
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    
    // Validate file type
    $allowed_types = ['pdf', 'doc', 'docx'];
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Only PDF, DOC, and DOCX files are allowed.'];
    }
    
    // Validate file size (5MB max)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'File size must be less than 5MB.'];
    }
    
    // Create unique filename
    $filename = "resume_" . $candidate_id . "_" . $job_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $filename;
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return [
            'success' => true, 
            'filename' => $filename, 
            'path' => $target_file
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file.'];
    }
}

// Simple resume parsing (placeholder for API integration)
function parseResume($file_path) {
    // For now, return mock data
    // In real implementation, call external API here
    $mock_skills = [
        'PHP', 'JavaScript', 'MySQL', 'HTML', 'CSS', 'Laravel', 'React', 'Node.js'
    ];
    
    return [
        'skills' => implode(', ', array_slice($mock_skills, 0, rand(3, 6))),
        'experience' => rand(1, 10) . ' years',
        'education' => 'Bachelor\'s Degree in Computer Science',
        'contact' => json_encode(['email' => 'candidate@example.com', 'phone' => '+60123456789'])
    ];
}

// Calculate match percentage between resume skills and job requirements
function calculateMatchPercentage($resume_skills, $job_skills) {
    if (empty($resume_skills) || empty($job_skills)) {
        return 0;
    }
    
    $resume_skills_array = array_map('trim', explode(',', strtolower($resume_skills)));
    $job_skills_array = array_map('trim', explode(',', strtolower($job_skills)));
    
    $matches = count(array_intersect($resume_skills_array, $job_skills_array));
    $total_required = count($job_skills_array);
    
    return round(($matches / $total_required) * 100, 2);
}

// Get all job positions
function getJobPositions() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM job_positions WHERE status = 'active' ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

// Get applications for HR dashboard
function getApplicationsForHR($job_id = null) {
    global $pdo;
    
    $sql = "SELECT a.*, u.full_name as candidate_name, u.email as candidate_email, 
                   j.title as job_title, j.department
            FROM applications a 
            JOIN users u ON a.candidate_id = u.id 
            JOIN job_positions j ON a.job_position_id = j.id";
    
    if ($job_id) {
        $sql .= " WHERE a.job_position_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$job_id]);
    } else {
        $sql .= " ORDER BY a.applied_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

// Update application status
function updateApplicationStatus($application_id, $status, $notes = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, hr_notes = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$status, $notes, $application_id]);
        
        // If status is 'hired', create employee account
        if ($result && $status === 'hired') {
            createEmployeeFromApplication($application_id);
        }
        
        return $result;
    } catch(PDOException $e) {
        return false;
    }
}

// Create employee account when hired
function createEmployeeFromApplication($application_id) {
    global $pdo;
    
    try {
        // Get application details
        $stmt = $pdo->prepare("SELECT a.candidate_id, u.username, u.email, u.full_name, j.department 
                              FROM applications a 
                              JOIN users u ON a.candidate_id = u.id 
                              JOIN job_positions j ON a.job_position_id = j.id 
                              WHERE a.id = ?");
        $stmt->execute([$application_id]);
        $app = $stmt->fetch();
        
        if ($app) {
            // Update user role to employee
            $stmt = $pdo->prepare("UPDATE users SET role = 'employee' WHERE id = ?");
            $stmt->execute([$app['candidate_id']]);
            
            // Assign onboarding tasks
            assignOnboardingTasks($app['candidate_id'], $app['department']);
            
            // Assign training modules
            assignTrainingModules($app['candidate_id'], $app['department']);
            
            return true;
        }
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

// Assign onboarding tasks to new employee
function assignOnboardingTasks($employee_id, $department) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM onboarding_tasks WHERE department = 'ALL' OR department = ? ORDER BY order_sequence");
        $stmt->execute([$department]);
        $tasks = $stmt->fetchAll();
        
        foreach ($tasks as $task) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO employee_onboarding (employee_id, task_id) VALUES (?, ?)");
            $stmt->execute([$employee_id, $task['id']]);
        }
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Assign training modules to new employee
function assignTrainingModules($employee_id, $department) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM training_modules WHERE department = 'ALL' OR department = ?");
        $stmt->execute([$department]);
        $modules = $stmt->fetchAll();
        
        foreach ($modules as $module) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO employee_training (employee_id, module_id) VALUES (?, ?)");
            $stmt->execute([$employee_id, $module['id']]);
        }
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Get onboarding progress for employee
function getOnboardingProgress($employee_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT t.task_name, t.description, eo.status, eo.completed_at, t.order_sequence
        FROM employee_onboarding eo 
        JOIN onboarding_tasks t ON eo.task_id = t.id 
        WHERE eo.employee_id = ? 
        ORDER BY t.order_sequence
    ");
    $stmt->execute([$employee_id]);
    return $stmt->fetchAll();
}

// Get training modules for employee
function getTrainingModules($employee_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT tm.module_name, tm.description, tm.duration_hours, et.status, et.progress_percentage
        FROM employee_training et 
        JOIN training_modules tm ON et.module_id = tm.id 
        WHERE et.employee_id = ?
    ");
    $stmt->execute([$employee_id]);
    return $stmt->fetchAll();
}

// Update onboarding task status
function updateTaskStatus($employee_id, $task_id, $status) {
    global $pdo;
    
    try {
        $completed_at = ($status === 'completed') ? 'NOW()' : 'NULL';
        $stmt = $pdo->prepare("UPDATE employee_onboarding SET status = ?, completed_at = $completed_at WHERE employee_id = ? AND task_id = ?");
        return $stmt->execute([$status, $employee_id, $task_id]);
    } catch(PDOException $e) {
        return false;
    }
}

// Simple chatbot function (static FAQ matching)
function getChatbotResponse($user_message) {
    global $pdo;
    
    $user_message_lower = strtolower($user_message);
    
    try {
        $stmt = $pdo->prepare("SELECT answer FROM chatbot_faq WHERE LOWER(keywords) LIKE ? AND is_active = 1 LIMIT 1");
        $stmt->execute(['%' . $user_message_lower . '%']);
        $response = $stmt->fetch();
        
        if ($response) {
            return $response['answer'];
        } else {
            // Default response
            $stmt = $pdo->prepare("SELECT setting_value FROM chatbot_settings WHERE setting_key = 'default_response'");
            $stmt->execute();
            $default = $stmt->fetch();
            return $default ? $default['setting_value'] : "I'm sorry, I couldn't understand your question. Please contact HR for assistance.";
        }
    } catch(PDOException $e) {
        return "I'm experiencing technical difficulties. Please contact HR for assistance.";
    }
}

// Save chat conversation
function saveChatMessage($user_id, $session_id, $message, $response) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO chat_conversations (user_id, session_id, message, response, message_type) VALUES (?, ?, ?, ?, 'user')");
        $stmt->execute([$user_id, $session_id, $message, $response]);
        
        $stmt = $pdo->prepare("INSERT INTO chat_conversations (user_id, session_id, message, response, message_type) VALUES (?, ?, ?, ?, 'bot')");
        $stmt->execute([$user_id, $session_id, $response, $message]);
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}
?>