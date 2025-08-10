<?php
$password = 'password123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo "New Hash: " . $hashed_password;
?>