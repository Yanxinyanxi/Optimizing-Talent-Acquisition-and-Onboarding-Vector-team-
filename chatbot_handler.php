<?php
// File: chatbot_handler.php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/chatbot_api.php';

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'chatbot_errors.log');

header('Content-Type: application/json');

// Log the request for debugging
error_log("Chatbot Handler - Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Chatbot Handler - Raw Input: " . file_get_contents('php://input'));

// Check if user is logged in
if (!isLoggedIn()) {
    error_log("Chatbot Handler - User not logged in");
    echo json_encode([
        'success' => false,
        'response' => 'Please log in to use the chatbot.'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Chatbot Handler - Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'response' => 'Invalid request method.'
    ]);
    exit;
}

// Get input data
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Chatbot Handler - JSON decode error: " . json_last_error_msg());
    echo json_encode([
        'success' => false,
        'response' => 'Invalid JSON format.'
    ]);
    exit;
}

$user_message = trim($input['message'] ?? '');
error_log("Chatbot Handler - User message: " . $user_message);

if (empty($user_message)) {
    error_log("Chatbot Handler - Empty message received");
    echo json_encode([
        'success' => false,
        'response' => 'Please enter a message.'
    ]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $session_id = session_id();
    
    error_log("Chatbot Handler - Processing message for user ID: " . $user_id);
    
    // Get user context from database
    $stmt = $connection->prepare("
        SELECT u.full_name, u.role, u.department, u.created_at,
               jp.title as job_title
        FROM users u 
        LEFT JOIN job_positions jp ON u.job_position_id = jp.id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    
    if (!$user_data) {
        error_log("Chatbot Handler - User data not found for ID: " . $user_id);
        echo json_encode([
            'success' => false,
            'response' => 'User information not found.'
        ]);
        exit;
    }
    
    $user_context = [
        'name' => $user_data['full_name'] ?? '',
        'department' => $user_data['department'] ?? '',
        'role' => $user_data['role'] ?? '',
        'job_title' => $user_data['job_title'] ?? '',
        'join_date' => $user_data['created_at'] ?? ''
    ];
    
    error_log("Chatbot Handler - User context: " . json_encode($user_context));
    
    // First, check if we have a matching FAQ
    $faq_response = checkFAQ($connection, $user_message);
    
    if ($faq_response) {
        error_log("Chatbot Handler - FAQ match found");
        $response = $faq_response;
        $api_used = false;
        $response_time = 0;
    } else {
        error_log("Chatbot Handler - No FAQ match, trying API");
        // Use GPT-4o API
        $start_time = microtime(true);
        $api = new GitHubModelsAPI();
        $api_result = $api->getChatResponse($user_message, $user_context);
        $response_time = microtime(true) - $start_time;
        
        error_log("Chatbot Handler - API result: " . json_encode($api_result));
        
        $response = $api_result['response'];
        $api_used = true;
        
        if (!$api_result['success']) {
            error_log("Chatbot Handler - API error: " . ($api_result['error'] ?? 'Unknown error'));
        }
    }
    
    // Log the conversation
    $stmt = $connection->prepare("
        INSERT INTO chat_conversations (user_id, session_id, message, response, message_type, api_response_time) 
        VALUES (?, ?, ?, ?, 'user', ?)
    ");
    $stmt->bind_param("isssd", $user_id, $session_id, $user_message, $response, $response_time);
    $stmt->execute();
    
    if ($stmt->error) {
        error_log("Chatbot Handler - Database insert error: " . $stmt->error);
    } else {
        error_log("Chatbot Handler - Conversation logged successfully");
    }
    
    echo json_encode([
        'success' => true,
        'response' => $response,
        'api_used' => $api_used,
        'debug_info' => [
            'faq_checked' => true,
            'faq_found' => $faq_response ? true : false,
            'api_called' => !$faq_response,
            'response_time' => $response_time
        ]
    ]);
    
    error_log("Chatbot Handler - Response sent successfully");
    
} catch (Exception $e) {
    error_log("Chatbot Handler - Exception: " . $e->getMessage());
    error_log("Chatbot Handler - Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'response' => 'I apologize, but I\'m experiencing technical difficulties. Please contact HR directly at hr@haircare2u.my for immediate assistance.',
        'error_details' => $e->getMessage() // Remove this in production
    ]);
}

/**
 * Check for FAQ matches using keywords - FIXED VERSION
 */
function checkFAQ($connection, $message) {
    try {
        $message_lower = strtolower($message);
        
        // Faster query with better indexing
        $stmt = $connection->prepare("
            SELECT answer, question 
            FROM chatbot_faq 
            WHERE is_active = 1 
            AND (
                LOWER(question) LIKE ? 
                OR FIND_IN_SET(?, LOWER(REPLACE(keywords, ' ', ','))) > 0
            )
            ORDER BY CHAR_LENGTH(question) ASC
            LIMIT 1
        ");
        
        $search_term = "%{$message_lower}%";
        $stmt->bind_param("ss", $search_term, $message_lower);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['answer'];
        }
        return null;
    } catch (Exception $e) {
        error_log("FAQ Check - Error: " . $e->getMessage());
        return null;
    }
}
?>