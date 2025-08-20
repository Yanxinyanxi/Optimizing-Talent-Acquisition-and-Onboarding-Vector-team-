<?php
// session_start();
// session_destroy();
// header('Location: index.php');
// exit;

session_start();
require_once 'includes/auth.php';

// Call the logout function
logout();
?>