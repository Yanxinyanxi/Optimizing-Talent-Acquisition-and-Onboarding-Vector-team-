<?php
// Authentication functions
require_once 'config.php';
require_once 'db.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Login function
function login($username, $password) {
    global $connection;
    
    try {
        $stmt = $connection->prepare("SELECT id, username, password, full_name, email, role, status, department, job_position_id FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if password is hashed or plaintext
            $password_valid = false;
            
            // First try hashed password verification
            if (password_verify($password, $user['password'])) {
                $password_valid = true;
            } 
            // Fallback to plaintext comparison for existing users
            else if ($user['password'] === $password) {
                $password_valid = true;
                
                // Optional: Update to hashed password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $connection->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user['id']);
                $update_stmt->execute();
            }
            
            if ($password_valid) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['job_position_id'] = $user['job_position_id'];
                
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

// Logout function
function logout() {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check for role transitions and refresh session data
function checkRoleTransition($connection = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!$connection) {
        global $connection;
    }
    
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $connection->prepare("SELECT role, department, job_position_id, full_name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $current_role = $_SESSION['role'];
            $actual_role = $user_data['role'];
            
            // Check if role has changed
            if ($current_role !== $actual_role) {
                // Update session with new data
                $_SESSION['role'] = $actual_role;
                $_SESSION['department'] = $user_data['department'];
                $_SESSION['job_position_id'] = $user_data['job_position_id'];
                $_SESSION['full_name'] = $user_data['full_name'];
                $_SESSION['email'] = $user_data['email'];
                
                return $actual_role; // Return new role
            }
            
            // Even if role hasn't changed, update session data in case other info changed
            $_SESSION['department'] = $user_data['department'];
            $_SESSION['job_position_id'] = $user_data['job_position_id'];
            $_SESSION['full_name'] = $user_data['full_name'];
            $_SESSION['email'] = $user_data['email'];
        }
        
    } catch (Exception $e) {
        error_log("Error checking role transition: " . $e->getMessage());
    }
    
    return false;
}

// Require specific role with automatic role transition detection
function requireRole($required_role) {
    global $connection;
    
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
    
    // Check for role transitions first
    $new_role = checkRoleTransition($connection);
    
    // If role has changed, redirect to appropriate dashboard
    if ($new_role && $new_role !== $required_role) {
        redirectToDashboard($new_role);
        exit;
    }
    
    // If current role doesn't match required role
    if ($_SESSION['role'] !== $required_role) {
        // Special case: if candidate was hired and became employee
        if ($_SESSION['role'] === 'employee' && $required_role === 'candidate') {
            redirectToDashboard('employee');
            exit;
        }
        
        // Redirect to appropriate dashboard based on user's actual role
        redirectToDashboard($_SESSION['role']);
        exit;
    }
}

// Redirect to appropriate dashboard
function redirectToDashboard($role = null) {
    if (!$role) {
        $role = $_SESSION['role'] ?? 'candidate';
    }
    
    switch ($role) {
        case 'hr':
            header('Location: hr-dashboard.php');
            break;
        case 'employee':
            header('Location: employee-dashboard.php');
            break;
        case 'candidate':
            header('Location: candidate-dashboard.php');
            break;
        default:
            header('Location: index.php');
    }
    exit;
}

// Check if user has permission
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $permissions = [
        'hr' => ['view_applications', 'manage_applications', 'view_employees', 'manage_jobs', 'manage_onboarding'],
        'employee' => ['view_profile', 'view_onboarding', 'view_training', 'submit_tickets', 'upload_documents'],
        'candidate' => ['upload_resume', 'view_applications', 'apply_jobs', 'view_profile']
    ];
    
    $userRole = $_SESSION['role'];
    return isset($permissions[$userRole]) && in_array($permission, $permissions[$userRole]);
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role'],
        'department' => $_SESSION['department'] ?? null,
        'job_position_id' => $_SESSION['job_position_id'] ?? null
    ];
}

// Get user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Get user ID
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get user name
function getUserName() {
    return $_SESSION['full_name'] ?? '';
}

// Get user email
function getUserEmail() {
    return $_SESSION['email'] ?? '';
}

// Get user department
function getUserDepartment() {
    return $_SESSION['department'] ?? null;
}

// Get user job position ID
function getUserJobPositionId() {
    return $_SESSION['job_position_id'] ?? null;
}

// Refresh user session data from database
function refreshUserSession($connection = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!$connection) {
        global $connection;
    }
    
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $connection->prepare("SELECT role, department, job_position_id, full_name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Update session with current data
            $_SESSION['role'] = $user['role'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['job_position_id'] = $user['job_position_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Error refreshing user session: " . $e->getMessage());
    }
    
    return false;
}

// Handle role redirect - call this at the beginning of protected pages
function handleRoleRedirect($connection = null, $current_page = '') {
    if (!$connection) {
        global $connection;
    }
    
    $new_role = checkRoleTransition($connection);
    
    if ($new_role) {
        // Role has changed, redirect to appropriate dashboard
        switch ($new_role) {
            case 'employee':
                if (strpos($current_page, 'employee-') !== 0 && $current_page !== 'employee-dashboard.php') {
                    header('Location: employee-dashboard.php');
                    exit;
                }
                break;
            case 'hr':
                if (strpos($current_page, 'hr-') !== 0 && $current_page !== 'hr-dashboard.php') {
                    header('Location: hr-dashboard.php');
                    exit;
                }
                break;
            case 'candidate':
                if (strpos($current_page, 'candidate-') !== 0 && $current_page !== 'candidate-dashboard.php') {
                    header('Location: candidate-dashboard.php');
                    exit;
                }
                break;
        }
    }
}

// Check if user is a specific role
function isRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Check if user is candidate
function isCandidate() {
    return isRole('candidate');
}

// Check if user is employee
function isEmployee() {
    return isRole('employee');
}

// Check if user is HR
function isHR() {
    return isRole('hr');
}

// Get user's job position details
function getUserJobPosition($connection = null) {
    if (!isLoggedIn() || !$_SESSION['job_position_id']) {
        return null;
    }
    
    if (!$connection) {
        global $connection;
    }
    
    try {
        $stmt = $connection->prepare("SELECT * FROM job_positions WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['job_position_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    } catch (Exception $e) {
        error_log("Error getting user job position: " . $e->getMessage());
    }
    
    return null;
}

// Create new user account (for HR to create employees)
function createUser($username, $email, $password, $full_name, $role = 'candidate', $department = null, $job_position_id = null) {
    global $connection;
    
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $connection->prepare("INSERT INTO users (username, email, password, full_name, role, department, job_position_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $username, $email, $hashed_password, $full_name, $role, $department, $job_position_id);
        return $stmt->execute();
    } catch(Exception $e) {
        error_log("Create user error: " . $e->getMessage());
        return false;
    }
}

// Generate random password for new employees
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

// Log user activity (optional - for audit trail)
function logUserActivity($action, $details = '', $connection = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!$connection) {
        global $connection;
    }
    
    try {
        // Create activity log table if it doesn't exist
        $connection->query("
            CREATE TABLE IF NOT EXISTS user_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        $stmt = $connection->prepare("INSERT INTO user_activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param("issss", $user_id, $action, $details, $ip_address, $user_agent);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error logging user activity: " . $e->getMessage());
        return false;
    }
}
?>