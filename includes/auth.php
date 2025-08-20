<?php
// Authentication functions
require_once 'config.php';
require_once 'db.php';
require_once 'db.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// // Check user role
// function hasRole($role) {
//     return isset($_SESSION['role']) && $_SESSION['role'] === $role;
// }

// // Redirect if not logged in
// function requireLogin() {
//     if (!isLoggedIn()) {
//         header('Location: index.php');
//         exit;
//     }
// }


// Login function
function login($username, $password) {
    global $connection;
    
    try {
        $stmt = $connection->prepare("SELECT id, username, password, full_name, email, role, status FROM users WHERE username = ? AND status = 'active'");
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

// Require specific role
function requireRole($required_role) {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
    
    if ($_SESSION['role'] !== $required_role) {
        // Redirect to appropriate dashboard based on user's actual role
        switch ($_SESSION['role']) {
            case 'hr':
                header('Location: hr-dashboard.php');
                break;
            case 'employee':
                header('Location: employee-dashboard.php');
                break;
            case 'candidate':
                header('Location: candidate-upload.php');
                break;
            default:
                header('Location: index.php');
        }
        exit;
    }
}

// Check if user has permission
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $permissions = [
        'hr' => ['view_applications', 'manage_applications', 'view_employees', 'manage_jobs'],
        'employee' => ['view_profile', 'view_onboarding'],
        'candidate' => ['upload_resume', 'view_applications', 'apply_jobs']
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
        'role' => $_SESSION['role']
    ];
}

// // Create new user account - FIXED to use MySQLi
// function createUser($username, $email, $password, $full_name, $role = 'candidate') {
//     global $connection; // Changed from $pdo to $connection
    
//     try {
//         $hashed_password = password_hash($password, PASSWORD_DEFAULT);
//         $stmt = $connection->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
//         $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $role);
//         return $stmt->execute();
//     } catch(Exception $e) {
//         error_log("Create user error: " . $e->getMessage());
//         return false;
//     }
// }

// // Generate random password for new employees
// function generatePassword($length = 8) {
//     $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
//     return substr(str_shuffle($chars), 0, $length);
// }
?>