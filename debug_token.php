<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    echo "<h2>Token Debug Information</h2>";
    echo "Token: " . htmlspecialchars($token) . "<br>";
    echo "Token length: " . strlen($token) . "<br>";
    echo "Current time: " . date('Y-m-d H:i:s') . "<br><br>";
    
    try {
        // Check if token exists (regardless of expiration)
        $stmt = $pdo->prepare("SELECT id, username, reset_token, reset_expires FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "✅ Token found in database<br>";
            echo "User ID: " . $user['id'] . "<br>";
            echo "Username: " . $user['username'] . "<br>";
            echo "Stored Token: " . htmlspecialchars($user['reset_token']) . "<br>";
            echo "Expires at: " . $user['reset_expires'] . "<br>";
            echo "Tokens match: " . ($token === $user['reset_token'] ? "✅ Yes" : "❌ No") . "<br>";
            
            if ($user['reset_expires'] > date('Y-m-d H:i:s')) {
                echo "Token status: ✅ Valid (not expired)<br>";
            } else {
                echo "Token status: ❌ Expired<br>";
            }
        } else {
            echo "❌ Token not found in database<br>";
            
            // Check all reset tokens in database
            $stmt = $pdo->query("SELECT id, username, reset_token, reset_expires FROM users WHERE reset_token IS NOT NULL");
            $all_tokens = $stmt->fetchAll();
            
            if (count($all_tokens) > 0) {
                echo "<br><strong>All reset tokens in database:</strong><br>";
                foreach ($all_tokens as $row) {
                    echo "ID: {$row['id']}, Username: {$row['username']}, Token: " . substr($row['reset_token'], 0, 10) . "..., Expires: {$row['reset_expires']}<br>";
                }
            } else {
                echo "<br>No reset tokens found in database<br>";
            }
        }
        
    } catch (PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "No token provided. Add ?token=YOUR_TOKEN to the URL";
}

echo "<br><br><a href='index.php'>← Back to login</a>";
?>