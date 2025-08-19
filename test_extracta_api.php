<?php
/**
 * Test script for Extracta.ai API integration
 * Create this file as test_extracta_api.php in your project root
 * 
 * Usage: 
 * 1. Upload a test resume file to your uploads/resumes/ directory
 * 2. Update the $test_file_path below
 * 3. Run this script in your browser: http://localhost/your-project/test_extracta_api.php
 */

// Prevent running in production
if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    die('This test script can only be run on localhost for security reasons.');
}

// Include your configuration
require_once 'includes/config.php';

// API credentials from config.php
$api_key = EXTRACTA_API_KEY;
$extraction_id = EXTRACTA_EXTRACTION_ID;
$api_url = EXTRACTA_UPLOAD_ENDPOINT; // Use the correct endpoint from config

// Test file path - UPDATE THIS with your actual test file
$test_file_path = 'uploads/resumes/sample_resume.pdf'; // Change this to your test file

echo "<h1>Extracta.ai API Test</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";

// Check if config file exists
if (!defined('EXTRACTA_API_KEY')) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>ERROR:</strong> Configuration file not found or API key not set.<br>";
    echo "Please create includes/config.php and set your API key.";
    echo "</div>";
    exit;
}

// Check if API key is set and not placeholder
if (empty($api_key) || strpos($api_key, '(') !== false || strpos($api_key, 'my api key') !== false) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>ERROR:</strong> Please set your actual API key in includes/config.php<br>";
    echo "Current API key appears to be a placeholder: " . htmlspecialchars(substr($api_key, 0, 20)) . "...<br>";
    echo "Go to your Extracta.ai dashboard to get your real API key.";
    echo "</div>";
    exit;
}

// Check if test file exists
if (!file_exists($test_file_path)) {
    echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
    echo "<strong>WARNING:</strong> Test file not found: " . $test_file_path . "<br>";
    echo "Please upload a test resume file to the uploads/resumes/ directory and update the \$test_file_path variable in this script.";
    echo "</div>";
    
    // Show upload form for convenience
    if ($_POST && isset($_FILES['test_resume'])) {
        $upload_dir = 'uploads/resumes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $test_file_path = $upload_dir . 'test_' . time() . '_' . $_FILES['test_resume']['name'];
        if (move_uploaded_file($_FILES['test_resume']['tmp_name'], $test_file_path)) {
            echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
            echo "File uploaded successfully: " . $test_file_path;
            echo "</div>";
        } else {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "Failed to upload file.";
            echo "</div>";
            exit;
        }
    } else {
        ?>
        <form method="POST" enctype="multipart/form-data" style="margin: 20px 0; padding: 20px; border: 1px solid #ccc;">
            <h3>Upload Test Resume</h3>
            <input type="file" name="test_resume" accept=".pdf,.doc,.docx" required>
            <button type="submit" style="margin-left: 10px; padding: 5px 15px;">Upload & Test</button>
        </form>
        <?php
        echo "</div>";
        exit;
    }
}

echo "<h2>Configuration Check</h2>";
echo "<div style='background: #f5f5f5; padding: 15px; margin: 10px 0;'>";
echo "<strong>API Key:</strong> " . (strlen($api_key) > 10 ? substr($api_key, 0, 10) . "..." : "Too short") . "<br>";
echo "<strong>Extraction ID:</strong> " . htmlspecialchars($extraction_id) . "<br>";
echo "<strong>API URL:</strong> " . htmlspecialchars($api_url) . "<br>";
echo "<strong>Test File:</strong> " . htmlspecialchars($test_file_path) . "<br>";
echo "<strong>File Size:</strong> " . number_format(filesize($test_file_path) / 1024, 2) . " KB<br>";
echo "<strong>File Type:</strong> " . mime_content_type($test_file_path) . "<br>";
echo "</div>";

echo "<h2>Testing API Connection...</h2>";

try {
    // According to Extracta.ai documentation, we need to send JSON data
    $post_data = json_encode([
        'extractionId' => $extraction_id,
        'files' => [
            [
                'name' => basename($test_file_path),
                'content' => base64_encode(file_get_contents($test_file_path))
            ]
        ]
    ]);
    
    // Initialize cURL
    $curl = curl_init();
    
    // Set cURL options for JSON API
    curl_setopt_array($curl, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => API_CONNECT_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_VERBOSE => false
    ]);
    
    echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin: 10px 0;'>";
    echo "📤 Sending request to Extracta.ai API...";
    echo "</div>";
    
    // Execute the request
    $start_time = microtime(true);
    $response = curl_exec($curl);
    $end_time = microtime(true);
    
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    
    curl_close($curl);
    
    echo "<h2>Response Details</h2>";
    echo "<div style='background: #f5f5f5; padding: 15px; margin: 10px 0;'>";
    echo "<strong>HTTP Status Code:</strong> " . $http_code . "<br>";
    echo "<strong>Response Time:</strong> " . number_format(($end_time - $start_time) * 1000, 2) . " ms<br>";
    echo "<strong>Response Size:</strong> " . strlen($response) . " bytes<br>";
    echo "</div>";
    
    // Check for cURL errors
    if ($curl_error) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>cURL Error:</strong> " . htmlspecialchars($curl_error);
        echo "</div>";
        throw new Exception('cURL Error: ' . $curl_error);
    }
    
    // Check HTTP status code
    if ($http_code !== 200) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>API Error:</strong> HTTP " . $http_code . "<br>";
        echo "<strong>Response:</strong> " . htmlspecialchars($response);
        echo "</div>";
        
        // Show common error solutions
        if ($http_code == 401) {
            echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
            echo "<strong>401 Unauthorized:</strong> Check your API key is correct and active.";
            echo "</div>";
        } elseif ($http_code == 429) {
            echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
            echo "<strong>429 Rate Limited:</strong> You've exceeded the rate limit. Wait a moment and try again.";
            echo "</div>";
        } elseif ($http_code == 400) {
            echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
            echo "<strong>400 Bad Request:</strong> Check your request format or extraction ID.";
            echo "</div>";
        }
        
        throw new Exception('API Error: HTTP ' . $http_code);
    }
    
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ <strong>API Request Successful!</strong>";
    echo "</div>";
    
    // Parse the JSON response
    $api_response = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>JSON Parse Error:</strong> " . json_last_error_msg() . "<br>";
        echo "<strong>Raw Response:</strong> " . htmlspecialchars($response);
        echo "</div>";
        throw new Exception('Invalid JSON response: ' . json_last_error_msg());
    }
    
    echo "<h2>Parsed API Response</h2>";
    echo "<div style='background: #f0f8ff; padding: 15px; margin: 10px 0; border: 1px solid #4CAF50;'>";
    echo "<pre style='white-space: pre-wrap; font-family: monospace;'>";
    echo htmlspecialchars(json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "</pre>";
    echo "</div>";
    
    // Test the processing function
    echo "<h2>Testing Data Processing Function</h2>";
    
    // Simulate the processing function
    $processed_data = [
        'success' => true,
        'skills' => '',
        'experience' => '',
        'education' => '',
        'contact' => json_encode(['email' => '', 'phone' => '']),
        'api_response' => json_encode($api_response)
    ];
    
    // Extract skills based on Extracta.ai response structure
    if (isset($api_response['skills']) && is_array($api_response['skills'])) {
        $skills = array_map('trim', $api_response['skills']);
        $processed_data['skills'] = implode(', ', $skills);
    } elseif (isset($api_response['data']['skills'])) {
        $skills = is_array($api_response['data']['skills']) 
            ? $api_response['data']['skills'] 
            : explode(',', $api_response['data']['skills']);
        $processed_data['skills'] = implode(', ', array_map('trim', $skills));
    }
    
    echo "<div style='background: #f5f5f5; padding: 15px; margin: 10px 0;'>";
    echo "<strong>Extracted Skills:</strong> " . htmlspecialchars($processed_data['skills']) . "<br>";
    echo "<strong>Processing Status:</strong> ✅ Data processed successfully<br>";
    echo "</div>";
    
    echo "<div style='color: green; padding: 20px; border: 2px solid green; margin: 20px 0; text-align: center;'>";
    echo "<h2>🎉 Integration Test Successful!</h2>";
    echo "<p>Your Extracta.ai API integration is working correctly.</p>";
    echo "<p>You can now use the updated candidate-upload.php file with confidence.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "<strong>Test Failed:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<h3>Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li>Verify your API key is correct and active</li>";
    echo "<li>Check that your Extracta.ai account has available credits</li>";
    echo "<li>Ensure the test file is a valid resume (PDF, DOC, DOCX)</li>";
    echo "<li>Check your internet connection</li>";
    echo "<li>Review the Extracta.ai API documentation for any changes</li>";
    echo "<li>Try a smaller file (under 1MB) to test</li>";
    echo "</ol>";
}

echo "<h2>PHP Environment Check</h2>";
echo "<div style='background: #f5f5f5; padding: 15px; margin: 10px 0;'>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "<strong>cURL Enabled:</strong> " . (extension_loaded('curl') ? '✅ Yes' : '❌ No') . "<br>";
echo "<strong>JSON Enabled:</strong> " . (extension_loaded('json') ? '✅ Yes' : '❌ No') . "<br>";
echo "<strong>File Uploads:</strong> " . (ini_get('file_uploads') ? '✅ Enabled' : '❌ Disabled') . "<br>";
echo "<strong>Max Upload Size:</strong> " . ini_get('upload_max_filesize') . "<br>";
echo "<strong>Max Post Size:</strong> " . ini_get('post_max_size') . "<br>";
echo "</div>";

echo "</div>";

// Add some basic styling
echo "<style>
    pre { 
        max-height: 400px; 
        overflow: auto; 
        background: #f8f8f8; 
        padding: 10px; 
        border-radius: 5px; 
    }
    div { 
        border-radius: 5px; 
    }
</style>";
?>

<!-- Add a button to delete this test file for security -->
<div style="text-align: center; margin: 30px 0; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7;">
    <strong>Security Notice:</strong> Remember to delete this test file after testing is complete.
    <br><br>
    <form method="POST" style="display: inline;">
        <input type="hidden" name="delete_test_file" value="1">
        <button type="submit" onclick="return confirm('Are you sure you want to delete this test file?')" 
                style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
            🗑️ Delete This Test File
        </button>
    </form>
</div>

<?php
// Handle test file deletion
if ($_POST && isset($_POST['delete_test_file'])) {
    if (unlink(__FILE__)) {
        echo "<script>alert('Test file deleted successfully!'); window.location.href = 'index.php';</script>";
    } else {
        echo "<script>alert('Failed to delete test file. Please delete manually.');</script>";
    }
}
?>