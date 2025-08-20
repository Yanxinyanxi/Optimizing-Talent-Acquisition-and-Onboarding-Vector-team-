<?php
require_once 'includes/config.php';
require_once 'includes/extracta_api.php';

echo "<h1>🔍 Check Existing Extraction</h1>";

// Check if API key is configured
if (!defined('EXTRACTA_API_KEY') || EXTRACTA_API_KEY === 'your_actual_api_key_here') {
    echo "<p style='color: red;'>❌ Please configure your API key in includes/config.php first!</p>";
    exit;
}

echo "<p>✅ API Key configured</p>";

// Get extraction ID from user
if (isset($_POST['extraction_id'])) {
    $extraction_id = $_POST['extraction_id'];
    
    // Initialize API
    $extracta = new ExtractaAPI(EXTRACTA_API_KEY);
    
    echo "<h2>📋 Checking Extraction ID: " . htmlspecialchars($extraction_id) . "</h2>";
    
    // Check extraction details
    $result = $extracta->makeRequest('POST', 'https://api.extracta.ai/api/v1/viewExtraction', [
        'extractionId' => $extraction_id
    ]);
    
    if (isset($result['error'])) {
        echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 6px; color: #d00; margin: 15px 0;'>";
        echo "<strong>❌ Error:</strong> " . htmlspecialchars($result['error']);
        echo "</div>";
    } else {
        echo "<div style='background: #e6ffe6; padding: 15px; border-radius: 6px; color: #060; margin: 15px 0;'>";
        echo "<strong>✅ Extraction found and valid!</strong>";
        echo "</div>";
        
        // Display extraction details
        if (isset($result['extractionDetails'])) {
            $details = $result['extractionDetails'];
            
            echo "<h3>📝 Extraction Details:</h3>";
            echo "<div style='background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd;'>";
            
            if (isset($details['name'])) {
                echo "<p><strong>Name:</strong> " . htmlspecialchars($details['name']) . "</p>";
            }
            if (isset($details['description'])) {
                echo "<p><strong>Description:</strong> " . htmlspecialchars($details['description']) . "</p>";
            }
            if (isset($details['language'])) {
                echo "<p><strong>Language:</strong> " . htmlspecialchars($details['language']) . "</p>";
            }
            
            // Show fields
            if (isset($details['fields']) && is_array($details['fields'])) {
                echo "<h4>🏷️ Fields to Extract (" . count($details['fields']) . " fields):</h4>";
                echo "<ul>";
                foreach ($details['fields'] as $field) {
                    $key = isset($field['key']) ? $field['key'] : 'N/A';
                    $description = isset($field['description']) ? $field['description'] : 'No description';
                    echo "<li><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($description) . "</li>";
                }
                echo "</ul>";
            }
            
            echo "</div>";
            
            // Show configuration code
            echo "<h3>🔧 Add this to your config.php:</h3>";
            echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 6px; border: 1px solid #007cba;'>";
            echo "<code style='background: #fff; padding: 10px; display: block; font-family: monospace;'>";
            echo "define('EXTRACTA_EXTRACTION_ID', '" . htmlspecialchars($extraction_id) . "');";
            echo "</code>";
            echo "</div>";
            
            echo "<p><a href='test_api.php'>🧪 Test with this extraction</a></p>";
        }
        
        echo "<h3>🔍 Full API Response:</h3>";
        echo "<details>";
        echo "<summary style='cursor: pointer;'>Click to view</summary>";
        echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;'>";
        echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT));
        echo "</pre>";
        echo "</details>";
    }
} else {
    // Show form to enter extraction ID
    echo "<p>Enter your existing Extraction ID to check its configuration:</p>";
    
    echo "<form method='POST' style='background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 15px 0;'>";
    echo "<div style='margin-bottom: 15px;'>";
    echo "<label for='extraction_id' style='display: block; margin-bottom: 5px; font-weight: bold;'>Extraction ID:</label>";
    echo "<input type='text' name='extraction_id' id='extraction_id' style='width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;' placeholder='Enter your extraction ID' required>";
    echo "</div>";
    echo "<button type='submit' style='background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Check Extraction</button>";
    echo "</form>";
    
    echo "<h3>📍 How to find your Extraction ID:</h3>";
    echo "<ol>";
    echo "<li>Go to your Extracta.ai dashboard</li>";
    echo "<li>Navigate to <strong>Data Extraction</strong> section</li>";
    echo "<li>Find your resume extraction</li>";
    echo "<li>The Extraction ID should be displayed (it might be labeled as 'ID' or 'extractionId')</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<p><a href='setup_extracta.php'>🔧 Create New Extraction</a> | <a href='test_api.php'>🧪 Test API</a></p>";
?>