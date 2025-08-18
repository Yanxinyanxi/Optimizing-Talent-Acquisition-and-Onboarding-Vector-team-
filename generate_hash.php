<?php
$password = 'password123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br>";
echo "Verification test: " . (password_verify($password, $hash) ? 'SUCCESS' : 'FAILED');
?>