<?php
require_once 'includes/config.php';
require_once 'includes/extracta_api.php';

echo "<h1>🚀 Extracta.ai Setup</h1>";
echo "<p>This script will create an extraction template for resume parsing.</p>";

// Check if API key is configured
if (!defined('EXTRACTA_API_KEY') || EXTRACTA_API_KEY === 'your_actual_api_key_here') {
    echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 6px; color: #d00; margin: 15px 0;'>";
    echo "<strong>❌ Error:</strong> Please update your API key in includes/config.php first!";
    echo "</div>";
    exit;
}

echo "<div style='background: #e6ffe6; padding: 15px; border-radius: 6px; color: #060; margin: 15px 0;'>";
echo "<strong>✅ API Key configured!</strong>";
echo "</div>";

// Initialize API
$extracta = new ExtractaAPI(EXTRACTA_API_KEY);

echo "<h2>Creating Resume Extraction Template...</h2>";

// Create extraction template
$result = $extracta->createResumeExtraction();

if (isset($result['error'])) {
    echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 6px; color: #d00; margin: 15px 0;'>";
    echo "<strong>❌ Error creating extraction template:</strong><br>";
    echo htmlspecialchars($result['error']);
    echo "</div>";
} else {
    echo "<div style='background: #e6ffe6; padding: 15px; border-radius: 6px; color: #060; margin: 15px 0;'>";
    echo "<strong>✅ Extraction template created successfully!</strong>";
    echo "</div>";
    
    if (isset($result['extractionId'])) {
        $extraction_id = $result['extractionId'];
        
        echo "<h3>📋 Important: Save this Extraction ID</h3>";
        echo "<div style='background: #f0f8ff; padding: 20px; border-radius: 8px; border: 2px solid #007cba; margin: 15px 0;'>";
        echo "<p><strong>Your Extraction ID:</strong></p>";
        echo "<code style='background: #fff; padding: 10px; display: block; font-size: 16px; font-weight: bold; border-radius: 4px; border: 1px solid #ddd;'>";
        echo htmlspecialchars($extraction_id);
        echo "</code>";
        echo "</div>";
        
        echo "<h3>🔧 Next Steps:</h3>";
        echo "<ol style='line-height: 1.8;'>";
        echo "<li>Copy the Extraction ID above</li>";
        echo "<li>Open <code>includes/config.php</code></li>";
        echo "<li>Replace <code>'your_extraction_id_here'</code> with your actual Extraction ID</li>";
        echo "<li>Save the file</li>";
        echo "<li>Test the integration using <a href='test_api.php'>test_api.php</a></li>";
        echo "</ol>";
        
        echo "<h3>📝 Config.php should look like this:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;'>";
        echo htmlspecialchars("define('EXTRACTA_API_KEY', '" . EXTRACTA_API_KEY . "');\n");
        echo htmlspecialchars("define('EXTRACTA_EXTRACTION_ID', '" . $extraction_id . "');");
        echo "</pre>";
        
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 6px; color: #856404; margin: 15px 0;'>";
        echo "<strong>⚠️ Warning:</strong> Extraction created but no ID returned. Check the full response below.";
        echo "</div>";
    }
    
    echo "<h3>🔍 Full API Response:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;'>";
    echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT));
    echo "</pre>";
}

echo "<hr style='margin: 30px 0;'>";
echo "<p><a href='test_api.php'>🧪 Test API Integration</a> | <a href='candidate-dashboard.php'>📄 Upload Resume</a></p>";
?>