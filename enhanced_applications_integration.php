<?php
require_once 'includes/config.php';
require_once 'includes/db.php';  // Add this line
require_once 'includes/extracta_api.php';

/**
 * Enhanced integration with your existing applications table
 * This will also update the applications table with parsed data
 */
class EnhancedResumeProcessor {
    private $extracta;
    private $db;
    
    public function __construct($db_connection) {
        $this->extracta = new ExtractaAPI(EXTRACTA_API_KEY, EXTRACTA_EXTRACTION_ID);
        $this->db = $db_connection;
    }
    
    /**
     * Process resume and update both parsed_resumes and applications tables
     */
    public function processApplication($file_path, $original_filename, $candidate_id = null, $job_position_id = null) {
        // Step 1: Parse resume using Extracta.ai
        $parsed_result = $this->extracta->parseResume($file_path);
        
        if (isset($parsed_result['error'])) {
            return ['error' => $parsed_result['error']];
        }
        
        if (!isset($parsed_result['success']) || !$parsed_result['success']) {
            return ['error' => 'Failed to parse resume'];
        }
        
        $parsed_data = $parsed_result['data'];
        
        // Step 2: Save to parsed_resumes table
        $save_success = $this->extracta->saveParsedData($parsed_data, $original_filename, $this->db);
        
        if (!$save_success) {
            return ['error' => 'Failed to save parsed data'];
        }
        
        // Step 3: Update applications table if IDs provided
        if ($candidate_id && $job_position_id) {
            $this->updateApplicationsTable($parsed_data, $file_path, $original_filename, $candidate_id, $job_position_id);
        }
        
        return [
            'success' => true,
            'data' => $parsed_data,
            'message' => 'Resume processed and saved successfully'
        ];
    }
    
    /**
     * Update the applications table with parsed data
     */
    private function updateApplicationsTable($parsed_data, $file_path, $filename, $candidate_id, $job_position_id) {
        try {
            // Extract contact info for JSON field
            $contact_info = [];
            if (isset($parsed_data['personal_info'])) {
                $personal = $parsed_data['personal_info'];
                if (isset($personal['email'])) $contact_info['email'] = $personal['email'];
                if (isset($personal['phone'])) $contact_info['phone'] = $personal['phone'];
                if (isset($personal['address'])) $contact_info['address'] = $personal['address'];
                if (isset($personal['linkedin'])) $contact_info['linkedin'] = $personal['linkedin'];
                if (isset($personal['github'])) $contact_info['github'] = $personal['github'];
            }
            
            // Prepare data for applications table
            $extracted_skills = isset($parsed_data['skills']) ? json_encode($parsed_data['skills']) : '[]';
            $extracted_experience = isset($parsed_data['work_experience']) ? json_encode($parsed_data['work_experience']) : '[]';
            $extracted_education = isset($parsed_data['education']) ? json_encode($parsed_data['education']) : '[]';
            $contact_json = json_encode($contact_info);
            $api_response = json_encode($parsed_data);
            
            // Debug: Let's see what we're trying to insert
            error_log("Inserting into applications:");
            error_log("candidate_id: $candidate_id");
            error_log("job_position_id: $job_position_id");
            error_log("filename: $filename");
            error_log("file_path: $file_path");
            
            // Insert into applications table - FIXED: Check the exact column count
            $stmt = $this->db->prepare("
                INSERT INTO applications 
                (candidate_id, job_position_id, resume_filename, resume_path, 
                 api_response, extracted_skills, extracted_experience, extracted_education, 
                 extracted_contact, api_processing_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
            ");
            
            // 9 bind variables: i, i, s, s, s, s, s, s, s = iisssssss
            if (!$stmt->bind_param("iisssssss", 
                $candidate_id,           // i - integer
                $job_position_id,        // i - integer  
                $filename,               // s - string
                $file_path,              // s - string
                $api_response,           // s - string
                $extracted_skills,       // s - string
                $extracted_experience,   // s - string
                $extracted_education,    // s - string
                $contact_json            // s - string
            )) {
                error_log("Bind param failed: " . $stmt->error);
                return false;
            }
            
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error);
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error updating applications table: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate match percentage between resume and job requirements
     */
    public function calculateJobMatch($parsed_data, $job_id) {
        try {
            // Get job requirements
            $stmt = $this->db->prepare("SELECT required_skills FROM job_positions WHERE id = ?");
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $required_skills = explode(',', strtolower($row['required_skills']));
                $required_skills = array_map('trim', $required_skills);
                
                // Get candidate skills
                $candidate_skills = isset($parsed_data['skills']) ? $parsed_data['skills'] : [];
                $candidate_skills = array_map('strtolower', $candidate_skills);
                
                // Calculate match percentage
                $matches = 0;
                foreach ($required_skills as $required_skill) {
                    foreach ($candidate_skills as $candidate_skill) {
                        if (strpos($candidate_skill, $required_skill) !== false || 
                            strpos($required_skill, $candidate_skill) !== false) {
                            $matches++;
                            break;
                        }
                    }
                }
                
                $match_percentage = ($matches / count($required_skills)) * 100;
                return round($match_percentage, 2);
            }
            
            return 0;
            
        } catch (Exception $e) {
            error_log("Error calculating job match: " . $e->getMessage());
            return 0;
        }
    }
}

// Example usage and testing:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_integration'])) {
    echo "<h2>🧪 Testing Enhanced Integration</h2>";
    
    $processor = new EnhancedResumeProcessor($connection);
    
    // Test files to look for
    $test_files = [
        'uploads/resumes/sample_resume.pdf',
        'uploads/resumes/sample_resume.docx',
        'uploads/resumes/sample_resume.doc'
    ];
    
    $test_file = null;
    foreach ($test_files as $file) {
        if (file_exists($file)) {
            $test_file = $file;
            break;
        }
    }
    
    if ($test_file) {
        echo "<p>✅ Processing test file: <strong>" . htmlspecialchars($test_file) . "</strong></p>";
        
        // Test with sample candidate and job IDs from your database
        $result = $processor->processApplication($test_file, basename($test_file), 2, 1); // candidate_id=2, job_id=1
        
        if (isset($result['error'])) {
            echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 6px; color: #d00; margin: 15px 0;'>";
            echo "<strong>❌ Error:</strong> " . htmlspecialchars($result['error']);
            echo "</div>";
        } else {
            echo "<div style='background: #e6ffe6; padding: 15px; border-radius: 6px; color: #060; margin: 15px 0;'>";
            echo "<strong>✅ Success!</strong> Resume processed and saved to both tables.";
            echo "</div>";
            
            // Calculate job match
            if (isset($result['data'])) {
                $match_percentage = $processor->calculateJobMatch($result['data'], 1);
                echo "<p><strong>🎯 Job Match Score:</strong> <span style='font-size: 18px; font-weight: bold; color: " . 
                     ($match_percentage > 70 ? '#28a745' : ($match_percentage > 40 ? '#ffc107' : '#dc3545')) . 
                     ";'>{$match_percentage}%</span></p>";
                
                // Show some extracted data
                if (isset($result['data']['personal_info'])) {
                    $personal = $result['data']['personal_info'];
                    echo "<h4>📋 Extracted Information:</h4>";
                    echo "<ul>";
                    if (isset($personal['name'])) echo "<li><strong>Name:</strong> " . htmlspecialchars($personal['name']) . "</li>";
                    if (isset($personal['email'])) echo "<li><strong>Email:</strong> " . htmlspecialchars($personal['email']) . "</li>";
                    if (isset($result['data']['skills'])) echo "<li><strong>Skills:</strong> " . implode(', ', array_slice($result['data']['skills'], 0, 5)) . (count($result['data']['skills']) > 5 ? '...' : '') . "</li>";
                    echo "</ul>";
                }
            }
        }
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 6px; color: #856404; margin: 15px 0;'>";
        echo "<strong>⚠️ No test file found.</strong><br>";
        echo "Please place a resume file in one of these locations:";
        echo "<ul>";
        foreach ($test_files as $file) {
            echo "<li><code>" . htmlspecialchars($file) . "</code></li>";
        }
        echo "</ul>";
        echo "</div>";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_tables'])) {
    echo "<h2>📊 Database Table Status</h2>";
    
    // Check if tables exist
    $tables_to_check = ['parsed_resumes', 'applications', 'users', 'job_positions'];
    
    foreach ($tables_to_check as $table) {
        $result = $connection->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            $count_result = $connection->query("SELECT COUNT(*) as count FROM $table");
            $count = $count_result->fetch_assoc()['count'];
            echo "<p>✅ <strong>$table</strong> table exists with <strong>$count</strong> records</p>";
        } else {
            echo "<p>❌ <strong>$table</strong> table does not exist</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enhanced Resume Integration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .button:hover { background: #005a8b; }
        .button.secondary { background: #6c757d; }
        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007cba; }
    </style>
</head>
<body>
    <h1>🚀 Enhanced Resume Processing Integration</h1>
    
    <div class="info-box">
        <p><strong>This enhanced integration connects:</strong></p>
        <ul>
            <li>✅ Extracta.ai API parsing</li>
            <li>✅ Your existing <code>applications</code> table</li>
            <li>✅ New <code>parsed_resumes</code> table</li>
            <li>✅ Job matching algorithm</li>
        </ul>
    </div>
    
    <h2>🧪 Testing Options</h2>
    
    <form method="POST" style="margin: 20px 0;">
        <button type="submit" name="check_tables" class="button secondary">
            📊 Check Database Tables
        </button>
        <button type="submit" name="test_integration" class="button">
            🧪 Test Resume Processing
        </button>
    </form>
    
    <h2>📋 How to Use This Integration</h2>
    <ol>
        <li><strong>Check Tables:</strong> Verify your database tables are set up correctly</li>
        <li><strong>Test Processing:</strong> Process a sample resume to test the complete workflow</li>
        <li><strong>Upload Resumes:</strong> Use the regular upload form to process real resumes</li>
        <li><strong>View Results:</strong> Check both tables to see parsed data and applications</li>
    </ol>
    
    <hr style="margin: 30px 0;">
    <div>
        <a href="candidate-dashboard.php" class="button">📄 Upload Resume</a>
        <a href="test_api.php" class="button secondary">🔧 Test API</a>
        <a href="hr-dashboard.php" class="button secondary">📊 HR Dashboard</a>
    </div>
</body>
</html>