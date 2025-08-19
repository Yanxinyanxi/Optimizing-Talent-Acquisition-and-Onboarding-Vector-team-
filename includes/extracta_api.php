<?php
class ExtractaAPI {
    private $api_key;
    private $base_url = 'https://api.extracta.ai/api/v1/';
    private $extraction_id; // Will store the extraction template ID
    
    public function __construct($api_key, $extraction_id = null) {
        $this->api_key = $api_key;
        $this->extraction_id = $extraction_id;
    }
    
    /**
     * Create an extraction template for resumes
     * @return array - Response with extractionId or error
     */
    public function createResumeExtraction() {
        $url = $this->base_url . 'createExtraction';
        
        $extraction_details = [
            'name' => 'Resume Parser',
            'description' => 'Extract key information from resumes and CVs',
            'language' => 'English',
            'options' => [
                'hasTable' => false,
                'handwrittenTextRecognition' => false
            ],
            'fields' => [
                [
                    'key' => 'name',
                    'description' => 'Full name of the candidate',
                    'example' => 'John Doe'
                ],
                [
                    'key' => 'email',
                    'description' => 'Email address of the candidate',
                    'example' => 'john.doe@email.com'
                ],
                [
                    'key' => 'phone',
                    'description' => 'Phone number of the candidate',
                    'example' => '+1 (555) 123-4567'
                ],
                [
                    'key' => 'address',
                    'description' => 'Address or location of the candidate',
                    'example' => 'New York, NY'
                ],
                [
                    'key' => 'experience',
                    'description' => 'Work experience including job titles, companies, and dates',
                    'example' => 'Software Engineer at ABC Corp (2020-2023)'
                ],
                [
                    'key' => 'education',
                    'description' => 'Educational background including degrees, institutions, and dates',
                    'example' => 'Bachelor of Computer Science, University of XYZ (2016-2020)'
                ],
                [
                    'key' => 'skills',
                    'description' => 'Technical and professional skills',
                    'example' => 'JavaScript, Python, React, Node.js'
                ],
                [
                    'key' => 'summary',
                    'description' => 'Professional summary or objective',
                    'example' => 'Experienced software developer with 5+ years...'
                ]
            ]
        ];
        
        return $this->makeRequest('POST', $url, ['extractionDetails' => $extraction_details]);
    }
    
    /**
     * Upload and parse a resume file
     * @param string $file_path - Full path to the resume file
     * @param string $extraction_id - Extraction template ID (optional, uses class property if not provided)
     * @return array - Upload response or error
     */
    public function uploadResume($file_path, $extraction_id = null) {
        // Use provided extraction_id or class property
        $extraction_id = $extraction_id ?: $this->extraction_id;
        
        if (!$extraction_id) {
            return ['error' => 'Extraction ID is required. Please create an extraction template first.'];
        }
        
        // Check if file exists
        if (!file_exists($file_path)) {
            return ['error' => 'File not found: ' . $file_path];
        }
        
        $url = $this->base_url . 'uploadFiles';
        
        // Prepare the file for upload
        $cfile = new CURLFile($file_path);
        
        // Prepare POST data
        $post_data = [
            'extractionId' => $extraction_id,
            'files' => $cfile
        ];
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options for file upload
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60, // Longer timeout for file upload
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->api_key,
                'Accept: application/json'
                // Don't set Content-Type for multipart/form-data - let cURL handle it
            ]
        ]);
        
        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        // Handle cURL errors
        if ($curl_error) {
            return ['error' => 'cURL Error: ' . $curl_error];
        }
        
        // Handle HTTP errors
        if ($http_code !== 200) {
            return ['error' => 'HTTP Error ' . $http_code . ': ' . $response];
        }
        
        // Decode JSON response
        $decoded_response = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON response: ' . json_last_error_msg()];
        }
        
        return $decoded_response;
    }
    
    /**
     * Get batch results for processed files
     * @param string $extraction_id - Extraction ID
     * @param string $batch_id - Batch ID from upload response
     * @param string $file_id - Optional file ID to get specific file results
     * @return array - Batch results or error
     */
    public function getBatchResults($extraction_id, $batch_id, $file_id = null) {
        $url = $this->base_url . 'getBatchResults';
        
        $payload = [
            'extractionId' => $extraction_id,
            'batchId' => $batch_id
        ];
        
        if ($file_id) {
            $payload['fileId'] = $file_id;
        }
        
        return $this->makeRequest('POST', $url, $payload);
    }
    
    /**
     * Parse a resume file (complete workflow)
     * @param string $file_path - Full path to the resume file
     * @return array - Parsed resume data or error
     */
    public function parseResume($file_path) {
        // Step 1: Upload the file
        $upload_result = $this->uploadResume($file_path);
        
        if (isset($upload_result['error'])) {
            return $upload_result;
        }
        
        // Extract batch ID from upload response
        if (!isset($upload_result['batchId'])) {
            return ['error' => 'No batch ID returned from upload'];
        }
        
        $batch_id = $upload_result['batchId'];
        $extraction_id = $upload_result['extractionId'] ?? $this->extraction_id;
        
        // Step 2: Wait a moment for processing to start
        sleep(2);
        
        // Step 3: Poll for results (with timeout)
        $max_attempts = 30; // 30 attempts with 2-second delays = 1 minute max wait
        $attempts = 0;
        
        while ($attempts < $max_attempts) {
            $results = $this->getBatchResults($extraction_id, $batch_id);
            
            if (isset($results['error'])) {
                return $results;
            }
            
            // Check if any files are processed
            if (isset($results['files']) && is_array($results['files'])) {
                foreach ($results['files'] as $file) {
                    if (isset($file['status']) && $file['status'] === 'processed') {
                        return [
                            'success' => true,
                            'data' => $file['result'] ?? [],
                            'filename' => $file['fileName'] ?? 'unknown',
                            'url' => $file['url'] ?? ''
                        ];
                    }
                }
            }
            
            // Wait before next attempt
            sleep(2);
            $attempts++;
        }
        
        return ['error' => 'Processing timeout. Please check results later using getBatchResults.'];
    }
    
    /**
     * Make HTTP request to API
     * @param string $method - HTTP method (GET, POST, etc.)
     * @param string $url - Full URL
     * @param array $data - Request data
     * @return array - Response or error
     */
    public function makeRequest($method, $url, $data = []) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key,
            'Accept: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            return ['error' => 'cURL Error: ' . $curl_error];
        }
        
        if ($http_code !== 200) {
            return ['error' => 'HTTP Error ' . $http_code . ': ' . $response];
        }
        
        $decoded_response = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON response: ' . json_last_error_msg()];
        }
        
        return $decoded_response;
    }
    
    /**
     * Set extraction ID
     * @param string $extraction_id
     */
    public function setExtractionId($extraction_id) {
        $this->extraction_id = $extraction_id;
    }
    
    /**
     * Save parsed resume data to database
     * @param array $parsed_data - Data from API (from the 'data' field of parseResume response)
     * @param string $original_filename - Original resume filename
     * @param object $db_connection - Database connection
     * @return bool - Success status
     */
    public function saveParsedData($parsed_data, $original_filename, $db_connection) {
        try {
            // Extract personal_info object
            $personal_info = isset($parsed_data['personal_info']) ? $parsed_data['personal_info'] : [];
            
            // Extract personal information fields
            $name = isset($personal_info['name']) ? $personal_info['name'] : '';
            $email = isset($personal_info['email']) ? $personal_info['email'] : '';
            $phone = isset($personal_info['phone']) ? $personal_info['phone'] : '';
            $address = isset($personal_info['address']) ? $personal_info['address'] : '';
            $linkedin = isset($personal_info['linkedin']) ? $personal_info['linkedin'] : '';
            $github = isset($personal_info['github']) ? $personal_info['github'] : '';
            
            // Extract array fields and convert to JSON
            $work_experience = isset($parsed_data['work_experience']) ? json_encode($parsed_data['work_experience']) : '[]';
            $education = isset($parsed_data['education']) ? json_encode($parsed_data['education']) : '[]';
            $languages = isset($parsed_data['languages']) ? json_encode($parsed_data['languages']) : '[]';
            $skills = isset($parsed_data['skills']) ? json_encode($parsed_data['skills']) : '[]';
            $certificates = isset($parsed_data['certificates']) ? json_encode($parsed_data['certificates']) : '[]';
            
            // Prepare SQL statement
            $stmt = $db_connection->prepare("
                INSERT INTO parsed_resumes 
                (original_filename, name, email, phone, address, linkedin, github, 
                 work_experience, education, languages, skills, certificates, raw_data, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $raw_data = json_encode($parsed_data);
            
            $stmt->bind_param("sssssssssssss", 
                $original_filename, 
                $name, 
                $email, 
                $phone, 
                $address,
                $linkedin,
                $github,
                $work_experience, 
                $education, 
                $languages,
                $skills,
                $certificates,
                $raw_data
            );
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error saving parsed data: " . $e->getMessage());
            return false;
        }
    }
}
?>