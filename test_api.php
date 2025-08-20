<?php
require_once 'includes/config.php';
require_once 'includes/db.php';  // Add this line
require_once 'includes/extracta_api.php';

echo "<h1>🧪 Testing Extracta.ai API</h1>";

// Check if API key is configured
if (!defined('EXTRACTA_API_KEY') || EXTRACTA_API_KEY === 'your_extracta_api_key_here') {
    echo "<p style='color: red;'>❌ API key not configured. Please update includes/config.php</p>";
    exit;
}

// Check if extraction ID is configured
if (!defined('EXTRACTA_EXTRACTION_ID') || EXTRACTA_EXTRACTION_ID === 'your_extraction_id_here') {
    echo "<p style='color: orange;'>⚠️ Extraction ID not configured. Please run <a href='setup_extracta.php'>setup_extracta.php</a> first.</p>";
    exit;
}

echo "<p>✅ Configuration looks good!</p>";
echo "<p><strong>API Key:</strong> " . substr(EXTRACTA_API_KEY, 0, 10) . "..." . substr(EXTRACTA_API_KEY, -5) . "</p>";
echo "<p><strong>Extraction ID:</strong> " . EXTRACTA_EXTRACTION_ID . "</p>";

// Test with your sample resume
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

if (!$test_file) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 6px; color: #856404; margin: 15px 0;'>";
    echo "<p><strong>⚠️ No test file found.</strong></p>";
    echo "<p>Please place a resume file in one of these locations:</p>";
    echo "<ul>";
    foreach ($test_files as $file) {
        echo "<li><code>" . htmlspecialchars($file) . "</code></li>";
    }
    echo "</ul>";
    echo "<p>Then refresh this page to test.</p>";
    echo "</div>";
    exit;
}

echo "<p>📄 Testing with file: <strong>" . htmlspecialchars($test_file) . "</strong></p>";

// Initialize API
$extracta = new ExtractaAPI(EXTRACTA_API_KEY, EXTRACTA_EXTRACTION_ID);

echo "<hr>";
echo "<h2>🚀 Starting API Test...</h2>";

// Test the API
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 6px; margin: 15px 0;'>";
echo "<p><strong>Step 1:</strong> Uploading file to Extracta.ai...</p>";

$start_time = microtime(true);
$result = $extracta->parseResume($test_file);
$end_time = microtime(true);
$processing_time = round($end_time - $start_time, 2);

echo "<p><strong>Processing time:</strong> {$processing_time} seconds</p>";
echo "</div>";

echo "<h2>📊 API Response:</h2>";

if (isset($result['error'])) {
    echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 6px; color: #d00; margin: 15px 0;'>";
    echo "<p><strong>❌ Error:</strong> " . htmlspecialchars($result['error']) . "</p>";
    echo "</div>";
    
    echo "<h3>🔧 Troubleshooting Tips:</h3>";
    echo "<ul>";
    echo "<li>Check that your API key is correct</li>";
    echo "<li>Verify that your extraction ID exists</li>";
    echo "<li>Make sure the file is a valid resume document</li>";
    echo "<li>Check your internet connection</li>";
    echo "</ul>";
    
} elseif (isset($result['success']) && $result['success']) {
    echo "<div style='background: #e6ffe6; padding: 15px; border-radius: 6px; color: #060; margin: 15px 0;'>";
    echo "<p><strong>✅ Success!</strong> Resume parsed successfully.</p>";
    echo "</div>";
    
    $parsed_data = $result['data'];
    
    echo "<h3>📋 Extracted Data:</h3>";
    echo "<div style='background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd;'>";
    
    echo "<h3>📋 Extracted Data:</h3>";
    echo "<div style='background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd;'>";
    
    $found_fields = 0;
    
    // Personal Information
    if (isset($parsed_data['personal_info']) && is_array($parsed_data['personal_info'])) {
        $personal = $parsed_data['personal_info'];
        echo "<h4 style='color: #007cba;'>👤 Personal Information:</h4>";
        echo "<div style='background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;'>";
        
        $personal_fields = ['name', 'email', 'phone', 'address', 'linkedin', 'github'];
        foreach ($personal_fields as $field) {
            if (isset($personal[$field]) && !empty($personal[$field])) {
                $found_fields++;
                echo "<p><strong>" . ucfirst($field) . ":</strong> " . htmlspecialchars($personal[$field]) . "</p>";
            }
        }
        echo "</div>";
    }
    
    // Work Experience
    if (isset($parsed_data['work_experience']) && is_array($parsed_data['work_experience']) && !empty($parsed_data['work_experience'])) {
        $found_fields++;
        echo "<h4 style='color: #007cba;'>💼 Work Experience (" . count($parsed_data['work_experience']) . " entries):</h4>";
        echo "<div style='background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;'>";
        foreach ($parsed_data['work_experience'] as $exp) {
            echo "<div style='border-left: 3px solid #007cba; padding-left: 10px; margin-bottom: 10px;'>";
            if (isset($exp['title'])) echo "<strong>" . htmlspecialchars($exp['title']) . "</strong><br>";
            if (isset($exp['company'])) echo htmlspecialchars($exp['company']) . "<br>";
            if (isset($exp['start_date']) || isset($exp['end_date'])) {
                echo "<em>" . (isset($exp['start_date']) ? $exp['start_date'] : '') . " - " . (isset($exp['end_date']) ? $exp['end_date'] : 'Present') . "</em>";
            }
            echo "</div>";
        }
        echo "</div>";
    }
    
    // Education
    if (isset($parsed_data['education']) && is_array($parsed_data['education']) && !empty($parsed_data['education'])) {
        $found_fields++;
        echo "<h4 style='color: #007cba;'>🎓 Education (" . count($parsed_data['education']) . " entries):</h4>";
        echo "<div style='background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;'>";
        foreach ($parsed_data['education'] as $edu) {
            echo "<div style='border-left: 3px solid #28a745; padding-left: 10px; margin-bottom: 10px;'>";
            if (isset($edu['title'])) echo "<strong>" . htmlspecialchars($edu['title']) . "</strong><br>";
            if (isset($edu['institute'])) echo htmlspecialchars($edu['institute']) . "<br>";
            if (isset($edu['location'])) echo htmlspecialchars($edu['location']) . "<br>";
            echo "</div>";
        }
        echo "</div>";
    }
    
    // Skills, Languages, Certificates
    $array_fields = [
        'skills' => '🛠️ Skills',
        'languages' => '🌐 Languages', 
        'certificates' => '🏆 Certificates'
    ];
    
    foreach ($array_fields as $field => $label) {
        if (isset($parsed_data[$field]) && is_array($parsed_data[$field]) && !empty($parsed_data[$field])) {
            $found_fields++;
            echo "<h4 style='color: #007cba;'>$label (" . count($parsed_data[$field]) . " items):</h4>";
            echo "<div style='background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;'>";
            echo "<p>" . implode(', ', array_map('htmlspecialchars', $parsed_data[$field])) . "</p>";
            echo "</div>";
        }
    }
    
    if ($found_fields === 0) {
        echo "<p style='color: #856404;'>⚠️ No data fields were extracted. The API may have returned an empty result.</p>";
    }
    
    echo "</div>";
    
    echo "<h3>🎯 Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Fields extracted:</strong> {$found_fields} sections</li>";
    echo "<li><strong>Processing time:</strong> {$processing_time} seconds</li>";
    echo "<li><strong>Status:</strong> " . ($found_fields > 0 ? "✅ Working correctly" : "⚠️ Needs attention") . "</li>";
    echo "</ul>";
    
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 6px; color: #856404; margin: 15px 0;'>";
    echo "<p><strong>⚠️ Unexpected response format.</strong></p>";
    echo "</div>";
}

echo "<h3>🔍 Raw API Response (for debugging):</h3>";
echo "<details>";
echo "<summary style='cursor: pointer; color: #666;'>Click to view raw response</summary>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;'>";
echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT));
echo "</pre>";
echo "</details>";

echo "<hr style='margin: 30px 0;'>";
echo "<p>";
echo "<a href='setup_extracta.php'>🔧 Setup</a> | ";
echo "<a href='candidate-upload.php'>📄 Upload Resume</a> | ";
echo "<a href='hr-dashboard.php'>📊 Dashboard</a>";
echo "</p>";
?>