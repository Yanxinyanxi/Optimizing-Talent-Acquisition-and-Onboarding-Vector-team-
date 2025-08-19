<?php
require_once 'db.php';
require_once 'config.php'; // Add this for API configuration

// Generate random password for new candidates
function generatePassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Create new user account
function createUser($username, $email, $password, $full_name, $role = 'candidate') {
    global $pdo;
    
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $email, $hashed_password, $full_name, $role]);
    } catch(PDOException $e) {
        error_log('Error creating user: ' . $e->getMessage());
        return false;
    }
}

// Enhanced file upload function for resumes
function uploadResume($file, $candidate_id, $job_position_id) {
    $upload_dir = 'uploads/resumes/';
    $max_file_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    try {
        // Create upload directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        if ($file['size'] > $max_file_size) {
            throw new Exception('File size too large. Maximum 5MB allowed.');
        }
        
        // Check file type by MIME type (more secure than extension)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Invalid file type. Only PDF, DOC, and DOCX files are allowed.');
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_filename = 'resume_' . $candidate_id . '_' . $job_position_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $safe_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        return [
            'success' => true,
            'filename' => $safe_filename,
            'path' => $file_path,
            'message' => 'Resume uploaded successfully'
        ];
        
    } catch (Exception $e) {
        error_log('Resume upload error: ' . $e->getMessage());
        return [
            'success' => false,
            'filename' => '',
            'path' => '',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Parse resume using Extracta.ai API
 * @param string $file_path - Path to the uploaded resume file
 * @param int $candidate_id - ID of the candidate
 * @param int $job_position_id - ID of the job position
 * @return array - Parsed resume data or error
 */
function parseResumeWithExtracta($file_path, $candidate_id, $job_position_id) {
    // Your Extracta.ai API credentials from config
    $api_key = 'MTk4NzAwNDMxOQ==_2sz09qx0ic73vk30ts32uq';
$extraction_id = '-OY-tgWlYrpfUxqIQqWr';
$api_url = 'https://api.extracta.ai/api/v1' . $extraction_id . '/extract';

    try {
        // Check if file exists
        if (!file_exists($file_path)) {
            throw new Exception('Resume file not found: ' . $file_path);
        }
        
        // Prepare the file for upload
        $file_data = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
        
        // Prepare the POST data
        $post_data = [
            'file' => $file_data
        ];
        
        // Initialize cURL
        $curl = curl_init();
        
        // Set cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => API_CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        // Execute the request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        
        curl_close($curl);
        
        // Check for cURL errors
        if ($curl_error) {
            throw new Exception('cURL Error: ' . $curl_error);
        }
        
        // Check HTTP status code
        if ($http_code !== 200) {
            throw new Exception('API Error: HTTP ' . $http_code . ' - ' . $response);
        }
        
        // Parse the JSON response
        $api_response = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        // Update application status in database
        updateApplicationApiStatus($candidate_id, $job_position_id, 'completed', $response, null);
        
        // Process and return the parsed data
        return processExtractaResponse($api_response);
        
    } catch (Exception $e) {
        // Log the error and update database
        error_log('Extracta API Error: ' . $e->getMessage());
        updateApplicationApiStatus($candidate_id, $job_position_id, 'failed', null, $e->getMessage());
        
        // Return fallback data
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'skills' => '',
            'experience' => '',
            'education' => '',
            'contact' => json_encode(['email' => '', 'phone' => '']),
            'api_response' => null
        ];
    }
}

/**
 * Process Extracta.ai API response and format for database storage
 * @param array $api_response - Raw API response from Extracta
 * @return array - Formatted data for database
 */
function processExtractaResponse($api_response) {
    // Initialize default values
    $processed_data = [
        'success' => true,
        'skills' => '',
        'experience' => '',
        'education' => '',
        'contact' => json_encode(['email' => '', 'phone' => '']),
        'api_response' => json_encode($api_response)
    ];
    
    try {
        // Extract skills - try multiple possible paths in the response
        if (isset($api_response['skills']) && is_array($api_response['skills'])) {
            $skills = array_map('trim', $api_response['skills']);
            $processed_data['skills'] = implode(', ', $skills);
        } elseif (isset($api_response['data']['skills'])) {
            $skills = is_array($api_response['data']['skills']) 
                ? $api_response['data']['skills'] 
                : explode(',', $api_response['data']['skills']);
            $processed_data['skills'] = implode(', ', array_map('trim', $skills));
        } elseif (isset($api_response['extracted_data']['skills'])) {
            $skills = is_array($api_response['extracted_data']['skills']) 
                ? $api_response['extracted_data']['skills'] 
                : explode(',', $api_response['extracted_data']['skills']);
            $processed_data['skills'] = implode(', ', array_map('trim', $skills));
        }
        
        // Extract experience
        if (isset($api_response['experience']) && is_array($api_response['experience'])) {
            $experience_entries = [];
            foreach ($api_response['experience'] as $exp) {
                if (is_array($exp)) {
                    $entry = [];
                    if (isset($exp['company'])) $entry[] = 'Company: ' . $exp['company'];
                    if (isset($exp['position'])) $entry[] = 'Position: ' . $exp['position'];
                    if (isset($exp['duration'])) $entry[] = 'Duration: ' . $exp['duration'];
                    if (isset($exp['description'])) $entry[] = 'Description: ' . $exp['description'];
                    $experience_entries[] = implode(' | ', $entry);
                } else {
                    $experience_entries[] = $exp;
                }
            }
            $processed_data['experience'] = implode("\n\n", $experience_entries);
        } elseif (isset($api_response['data']['experience'])) {
            $processed_data['experience'] = is_string($api_response['data']['experience']) 
                ? $api_response['data']['experience'] 
                : json_encode($api_response['data']['experience']);
        } elseif (isset($api_response['work_experience'])) {
            $processed_data['experience'] = is_string($api_response['work_experience']) 
                ? $api_response['work_experience'] 
                : json_encode($api_response['work_experience']);
        }
        
        // Extract education
        if (isset($api_response['education']) && is_array($api_response['education'])) {
            $education_entries = [];
            foreach ($api_response['education'] as $edu) {
                if (is_array($edu)) {
                    $entry = [];
                    if (isset($edu['institution'])) $entry[] = 'Institution: ' . $edu['institution'];
                    if (isset($edu['degree'])) $entry[] = 'Degree: ' . $edu['degree'];
                    if (isset($edu['field'])) $entry[] = 'Field: ' . $edu['field'];
                    if (isset($edu['year'])) $entry[] = 'Year: ' . $edu['year'];
                    $education_entries[] = implode(' | ', $entry);
                } else {
                    $education_entries[] = $edu;
                }
            }
            $processed_data['education'] = implode("\n\n", $education_entries);
        } elseif (isset($api_response['data']['education'])) {
            $processed_data['education'] = is_string($api_response['data']['education']) 
                ? $api_response['data']['education'] 
                : json_encode($api_response['data']['education']);
        }
        
        // Extract contact information
        $contact_info = ['email' => '', 'phone' => ''];
        
        if (isset($api_response['contact'])) {
            if (isset($api_response['contact']['email'])) {
                $contact_info['email'] = $api_response['contact']['email'];
            }
            if (isset($api_response['contact']['phone'])) {
                $contact_info['phone'] = $api_response['contact']['phone'];
            }
        } elseif (isset($api_response['data']['contact'])) {
            $contact_data = $api_response['data']['contact'];
            if (isset($contact_data['email'])) {
                $contact_info['email'] = $contact_data['email'];
            }
            if (isset($contact_data['phone'])) {
                $contact_info['phone'] = $contact_data['phone'];
            }
        }
        
        // Also check for email and phone at root level
        if (isset($api_response['email'])) {
            $contact_info['email'] = $api_response['email'];
        }
        if (isset($api_response['phone'])) {
            $contact_info['phone'] = $api_response['phone'];
        }
        
        $processed_data['contact'] = json_encode($contact_info);
        
    } catch (Exception $e) {
        error_log('Error processing Extracta response: ' . $e->getMessage());
    }
    
    return $processed_data;
}

/**
 * Update application API processing status in database
 * @param int $candidate_id
 * @param int $job_position_id  
 * @param string $status
 * @param string|null $api_response
 * @param string|null $error_message
 */
function updateApplicationApiStatus($candidate_id, $job_position_id, $status, $api_response = null, $error_message = null) {
    global $pdo;
    
    try {
        $sql = "UPDATE applications SET 
                api_processing_status = ?, 
                api_response = ?, 
                api_error_message = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE candidate_id = ? AND job_position_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $api_response, $error_message, $candidate_id, $job_position_id]);
        
    } catch (Exception $e) {
        error_log('Error updating application API status: ' . $e->getMessage());
    }
}

// Updated parseResume function to use Extracta.ai API
function parseResume($file_path, $candidate_id = null, $job_position_id = null) {
    // Use Extracta.ai API if candidate and job IDs are provided
    if ($candidate_id && $job_position_id) {
        return parseResumeWithExtracta($file_path, $candidate_id, $job_position_id);
    }
    
    // Fallback to mock data if API fails or IDs not provided
    $mock_skills = [
        'PHP', 'JavaScript', 'MySQL', 'HTML', 'CSS', 'Laravel', 'React', 'Node.js'
    ];
    
    return [
        'success' => true,
        'skills' => implode(', ', array_slice($mock_skills, 0, rand(3, 6))),
        'experience' => rand(1, 10) . ' years',
        'education' => 'Bachelor\'s Degree in Computer Science',
        'contact' => json_encode(['email' => 'candidate@example.com', 'phone' => '+60123456789']),
        'api_response' => null
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
        error_log('Error updating application status: ' . $e->getMessage());
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
            
            // Assign onboarding tasks (if tables exist)
            assignOnboardingTasks($app['candidate_id'], $app['department']);
            
            // Assign training modules (if tables exist)
            assignTrainingModules($app['candidate_id'], $app['department']);
            
            return true;
        }
        return false;
    } catch(PDOException $e) {
        error_log('Error creating employee from application: ' . $e->getMessage());
        return false;
    }
}

// Assign onboarding tasks to new employee
function assignOnboardingTasks($employee_id, $department) {
    global $pdo;
    
    try {
        // Check if onboarding_tasks table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'onboarding_tasks'");
        if ($stmt->rowCount() == 0) {
            return true; // Table doesn't exist, skip
        }
        
        $stmt = $pdo->prepare("SELECT id FROM onboarding_tasks WHERE department = 'ALL' OR department = ? ORDER BY order_sequence");
        $stmt->execute([$department]);
        $tasks = $stmt->fetchAll();
        
        foreach ($tasks as $task) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO employee_onboarding (employee_id, task_id) VALUES (?, ?)");
            $stmt->execute([$employee_id, $task['id']]);
        }
        
        return true;
    } catch(PDOException $e) {
        error_log('Error assigning onboarding tasks: ' . $e->getMessage());
        return false;
    }
}

// Assign training modules to new employee
function assignTrainingModules($employee_id, $department) {
    global $pdo;
    
    try {
        // Check if training_modules table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'training_modules'");
        if ($stmt->rowCount() == 0) {
            return true; // Table doesn't exist, skip
        }
        
        $stmt = $pdo->prepare("SELECT id FROM training_modules WHERE department = 'ALL' OR department = ?");
        $stmt->execute([$department]);
        $modules = $stmt->fetchAll();
        
        foreach ($modules as $module) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO employee_training (employee_id, module_id) VALUES (?, ?)");
            $stmt->execute([$employee_id, $module['id']]);
        }
        
        return true;
    } catch(PDOException $e) {
        error_log('Error assigning training modules: ' . $e->getMessage());
        return false;
    }
}

// Get onboarding progress for employee
function getOnboardingProgress($employee_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT t.task_name, t.description, eo.status, eo.completed_at, t.order_sequence
            FROM employee_onboarding eo 
            JOIN onboarding_tasks t ON eo.task_id = t.id 
            WHERE eo.employee_id = ? 
            ORDER BY t.order_sequence
        ");
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log('Error getting onboarding progress: ' . $e->getMessage());
        return [];
    }
}

// Get training modules for employee
function getTrainingModules($employee_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT tm.module_name, tm.description, tm.duration_hours, et.status, et.progress_percentage
            FROM employee_training et 
            JOIN training_modules tm ON et.module_id = tm.id 
            WHERE et.employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log('Error getting training modules: ' . $e->getMessage());
        return [];
    }
}

// Update onboarding task status
function updateTaskStatus($employee_id, $task_id, $status) {
    global $pdo;
    
    try {
        $completed_at = ($status === 'completed') ? 'NOW()' : 'NULL';
        $stmt = $pdo->prepare("UPDATE employee_onboarding SET status = ?, completed_at = $completed_at WHERE employee_id = ? AND task_id = ?");
        return $stmt->execute([$status, $employee_id, $task_id]);
    } catch(PDOException $e) {
        error_log('Error updating task status: ' . $e->getMessage());
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
        error_log('Chatbot error: ' . $e->getMessage());
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
        error_log('Error saving chat message: ' . $e->getMessage());
        return false;
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

// Redirect if doesn't have required role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: dashboard.php');
        exit();
    }
}
?>