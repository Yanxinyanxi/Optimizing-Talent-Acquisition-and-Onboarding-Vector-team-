<?php
// includes/config.php

// =============================================================================
// EXTRACTA.AI API CONFIGURATION - CORRECTED
// =============================================================================

// Your API credentials from Extracta.ai dashboard
define('EXTRACTA_API_KEY', 'MTk4NzAwNDMxOQ==_2sz09qx0ic73vk30ts32uq'); // Replace with your real API key

// Your extraction ID
define('EXTRACTA_EXTRACTION_ID', '-OY-tgWlYrpfUxqIQqWr');

// =============================================================================
// CORRECT API ENDPOINTS (from Extracta.ai documentation)
// =============================================================================

// Base URL (from their documentation)
define('EXTRACTA_API_BASE_URL', 'https://api.extracta.ai/api/v1');

// Available endpoints
define('EXTRACTA_UPLOAD_ENDPOINT', EXTRACTA_API_BASE_URL . '/uploadFiles');
define('EXTRACTA_RESULTS_ENDPOINT', EXTRACTA_API_BASE_URL . '/getBatchResults');
define('EXTRACTA_VIEW_ENDPOINT', EXTRACTA_API_BASE_URL . '/viewExtraction');

// =============================================================================
// API REQUEST CONFIGURATION
// =============================================================================

// HTTP method (POST for all endpoints according to their docs)
define('EXTRACTA_HTTP_METHOD', 'POST');

// Content type (application/json according to their examples)
define('EXTRACTA_CONTENT_TYPE', 'application/json');

// =============================================================================
// FILE UPLOAD SETTINGS
// =============================================================================

define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_DIR', 'uploads/resumes/');

// Allowed file types for resume upload
define('ALLOWED_RESUME_TYPES', [
    'application/pdf',
    'application/msword', 
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);

// =============================================================================
// API TIMEOUT SETTINGS
// =============================================================================

define('API_TIMEOUT', 30); // 30 seconds
define('API_CONNECT_TIMEOUT', 10); // 10 seconds

// =============================================================================
// APPLICATION SETTINGS
// =============================================================================

define('APP_NAME', 'Kabel HR System');
define('APP_VERSION', '1.0.0');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour

// Email settings (for future use)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

// =============================================================================
// DEBUG SETTINGS
// =============================================================================

// Debug mode (set to false in production)
define('DEBUG_MODE', true); // Change to false when you go live

// Error reporting (only for development)
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// =============================================================================
// CONFIGURATION VALIDATION
// =============================================================================

// Log current configuration for debugging
if (DEBUG_MODE) {
    error_log("Extracta.ai Config: Upload URL = " . EXTRACTA_UPLOAD_ENDPOINT);
    error_log("Extracta.ai Config: Results URL = " . EXTRACTA_RESULTS_ENDPOINT);
    error_log("Extracta.ai Config: Extraction ID = " . EXTRACTA_EXTRACTION_ID);
}
?>