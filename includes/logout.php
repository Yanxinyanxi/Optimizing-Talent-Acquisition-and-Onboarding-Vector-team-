<?php
require_once 'auth.php';

// Clear all session data
session_start();
session_destroy();

// Redirect to login page (go up one directory)
header('Location: ../index.php');
exit;
?>