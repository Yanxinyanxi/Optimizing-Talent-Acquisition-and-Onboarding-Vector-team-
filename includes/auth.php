<?php
require_once 'db.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// Redirect if wrong role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: index.php');
        exit;
    }
}

// Login function - FIXED to use MySQLi instead of PDO
function login($username, $password) {
    global $connection; // Changed from $pdo to $connection
    
    try {
        // MySQLi prepared statement instead of PDO
        $stmt = $connection->prepare("SELECT id, username, email, password, full_name, role FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // DEBUG: Add these lines temporarily
        echo "<pre>";
        echo "Debug Info:\n";
        echo "Username entered: " . htmlspecialchars($username) . "\n";
        echo "Password entered: " . htmlspecialchars($password) . "\n";
        echo "User found: " . ($user ? 'YES' : 'NO') . "\n";
        if ($user) {
            echo "Stored hash: " . htmlspecialchars($user['password']) . "\n";
            echo "Password verify result: " . (password_verify($password, $user['password']) ? 'TRUE' : 'FALSE') . "\n";
        }
        echo "</pre>";
        // END DEBUG
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    } catch(Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

// Logout function
function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Create new user account - FIXED to use MySQLi
function createUser($username, $email, $password, $full_name, $role = 'candidate') {
    global $connection; // Changed from $pdo to $connection
    
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $connection->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $role);
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
?>