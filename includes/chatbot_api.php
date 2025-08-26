<?php
// File: includes/chatbot_api.php

class GitHubModelsAPI {
    private $api_token;
    private $api_endpoint;
    private $model;
    
    public function __construct() {
        // Load environment variables
        $this->loadEnv();
        
        // Get token from environment or direct assignment
        $this->api_token = $_ENV['GITHUB_API_TOKEN'] ?? getenv('GITHUB_API_TOKEN') ?? 'YOUR_GITHUB_TOKEN_HERE';
        
        // If still default value, show clear error
        if ($this->api_token === 'YOUR_GITHUB_TOKEN_HERE') {
            error_log("Chatbot API: No valid GitHub token found. Please set GITHUB_API_TOKEN in .env file");
        }
        
        $this->api_endpoint = 'https://models.inference.ai.azure.com/chat/completions';
        $this->model = 'gpt-4o';
    }
    
    /**
     * Simple .env file loader
     */
    private function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
    
    /**
     * Send message to GPT-4o and get response
     */
    public function getChatResponse($user_message, $user_context = []) {
        // Check if we have a valid token
        if ($this->api_token === 'YOUR_GITHUB_TOKEN_HERE' || empty($this->api_token)) {
            return [
                'success' => false,
                'error' => 'No valid API token configured',
                'response' => 'I apologize, but the chatbot is not properly configured. Please contact your administrator or use the FAQ system.'
            ];
        }
        
        try {
            // Create system prompt with HR context
            $system_prompt = $this->createSystemPrompt($user_context);
            
            // Prepare the API request
            $messages = [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user',
                    'content' => $user_message
                ]
            ];
            
            $request_data = [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => 300,
                'temperature' => 0.7,
                'top_p' => 0.9,
                'stream' => false
            ];
            
            // Make API call
            $response = $this->makeAPICall($request_data);
            
            if ($response && isset($response['choices'][0]['message']['content'])) {
                return [
                    'success' => true,
                    'response' => $response['choices'][0]['message']['content'],
                    'usage' => $response['usage'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Invalid API response format',
                    'response' => 'I apologize, but I encountered an issue processing your request. Please try again or contact HR for assistance.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Chatbot API Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => 'I apologize, but I\'m having technical difficulties right now. Please contact HR directly at hr@haircare2u.my for immediate assistance.'
            ];
        }
    }
    
    /**
     * Create system prompt with HR context
     */

private function createSystemPrompt($user_context) {
    $context = "You are an AI assistant for HairCare2U's HR system. You help employees with onboarding, policies, and general HR questions.\n\n";
    
    // Add user context if available
    if (!empty($user_context)) {
        $context .= "Employee Context:\n";
        if (isset($user_context['name'])) $context .= "- Name: {$user_context['name']}\n";
        if (isset($user_context['department'])) $context .= "- Department: {$user_context['department']}\n";
        if (isset($user_context['role'])) $context .= "- Role: {$user_context['role']}\n";
        if (isset($user_context['job_title'])) $context .= "- Job Title: {$user_context['job_title']}\n";
        if (isset($user_context['join_date'])) $context .= "- Join Date: {$user_context['join_date']}\n";
        $context .= "\n";
    }
    
    $context .= "Key Guidelines and Response Formatting:
1. Always be helpful, professional, and friendly
2. Format responses for excellent readability using proper structure
3. Use numbered lists (1., 2., 3.) for step-by-step instructions
4. Use bullet points (•) for multiple options, contacts, or information lists
5. Keep paragraphs short (2-3 sentences maximum) with line breaks between topics
6. Use **bold formatting** for important headings and key information
7. For technical issues, suggest contacting IT support
8. For urgent matters, direct to HR contact: hr@haircare2u.my or +60 12-345 6790
9. Keep responses concise but comprehensive
10. If you don't know something specific, admit it and suggest appropriate contacts

Response Structure Examples:
- For procedures: Start with brief explanation, then numbered steps
- For contact information: Use bullet points with clear department labels
- For policies: Break into sections with bold headings
- For complex topics: Provide summary first, then detailed breakdown

Detailed Company Information:
**Company Overview:**
- Company: HairCare2U - Premium haircare products and solutions
- Founded: 2020
- Headquarters: Kuala Lumpur, Malaysia
- Mission: Providing premium haircare solutions while fostering employee growth

**Work Schedule & Environment:**
- Working Hours: 9:00 AM - 6:00 PM (Monday-Friday)
- Lunch Break: 12:00 PM - 1:00 PM
- Dress Code: Business casual (formal attire for client meetings)
- Remote Work: Available after successful completion of probation period
- Flexible Hours: Available post-probation with manager approval

**Contact Directory:**
- HR Department: hr@haircare2u.my | +60 12-345 6790
- IT Support: it@company.com | Extension 1234
- Main Reception: +60 3-1234 5678
- Emergency Contact: +60 12-345 6790 (HR)

**Leave Policies & Benefits:**
- Annual Leave: 14 days per year
- Medical Leave: 14 days per year (medical certificate required for 2+ consecutive days)
- Maternity Leave: 90 days (with proper documentation)
- Paternity Leave: 7 days
- Emergency Leave: As per company discretion and circumstances

**Department Structure:**
- **IT Department:** Software development, system administration, technical support
- **Sales & Marketing:** Product promotion, customer acquisition, brand management, digital marketing
- **Operations:** Supply chain management, logistics coordination, quality control
- **Customer Service:** Customer support, complaints handling, beauty consultation services
- **Management:** Leadership, strategic planning, departmental coordination

**Training & Development Programs:**
- **Mandatory:** New Employee Orientation (all new hires)
- **Technical:** Department-specific skills training
- **Leadership:** Management and leadership development programs
- **Compliance:** Safety, security, and regulatory compliance training
- **Professional:** Continuous learning and professional development courses

**Employee Benefits Package:**
- **Health Coverage:** Comprehensive medical, dental, and vision insurance
- **Financial:** Performance-based bonuses and salary reviews
- **Development:** Professional development budget for courses and certifications
- **Work-Life:** Flexible working hours (post-probation)
- **Perks:** Employee discounts on all HairCare2U products

**Common Topics You Can Help With:**
- Onboarding process and task completion
- Training module access and requirements
- Document submission guidelines and requirements
- Company policies and procedure explanations
- Working hours, leave policies, and time-off requests
- Contact information for various departments and services
- Benefits information and enrollment processes
- IT support and technical assistance requests
- Career development and training opportunities

**Response Quality Standards:**
- Start responses with a brief, direct answer
- Use clear section headings with **bold formatting**
- Provide specific contact information when relevant
- Include relevant policy details and procedures
- End with helpful follow-up questions or offers for additional assistance
- Maintain a warm, professional tone throughout
- Ensure all information is accurate and up-to-date

Always structure your responses for maximum readability and conclude with an offer to help further if needed.";

    return $context;
}
    
    /**
     * Make HTTP request to GitHub Models API with better error handling
     */
    private function makeAPICall($data) {
        $headers = [
            'Authorization: Bearer ' . $this->api_token,
            'Content-Type: application/json',
            'User-Agent: HairCare2U-Chatbot/1.0'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->api_endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // For localhost testing - set to true in production
            CURLOPT_SSL_VERIFYHOST => false, // For localhost testing - set to true in production
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_VERBOSE => false // Set to true for debugging
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        
        // Log detailed error information for debugging
        if ($curl_error) {
            error_log("CURL Error #{$curl_errno}: {$curl_error}");
            error_log("API Endpoint: {$this->api_endpoint}");
            error_log("HTTP Code: {$http_code}");
        }
        
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("Network Error: Unable to connect to AI service. Please check your internet connection or try again later.");
        }
        
        if ($http_code !== 200) {
            $error_response = json_decode($response, true);
            $error_message = isset($error_response['error']['message']) 
                ? $error_response['error']['message'] 
                : "API Error (HTTP {$http_code})";
            error_log("API Error Response: " . $response);
            throw new Exception($error_message);
        }
        
        $decoded_response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from API");
        }
        
        return $decoded_response;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $test_result = $this->getChatResponse("Hello, this is a test message.");
            return [
                'success' => $test_result['success'],
                'message' => $test_result['success'] ? 'API connection successful!' : $test_result['error'],
                'response' => $test_result['response'] ?? null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'response' => null
            ];
        }
    }
}