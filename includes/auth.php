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

// Login function
function login($username, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, password, full_name, role FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // DEBUG: Add these lines temporarily
        echo "<pre>";
        echo "Debug Info:\n";
        echo "Username entered: " . $username . "\n";
        echo "Password entered: " . $password . "\n";
        echo "User found: " . ($user ? 'YES' : 'NO') . "\n";
        if ($user) {
            echo "Stored hash: " . $user['password'] . "\n";
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
    } catch(PDOException $e) {
        return false;
    }
}

// Logout function
function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Create new user account
function createUser($username, $email, $password, $full_name, $role = 'candidate') {
    global $pdo;
    
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $email, $hashed_password, $full_name, $role]);
    } catch(PDOException $e) {
        return false;
    }
}

// Generate random password for new employees
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}
?>