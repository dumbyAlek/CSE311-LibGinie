<?php
$password_to_hash = 'password123'; // Replace with the password you want to use
$hashed_password = password_hash($password_to_hash, PASSWORD_DEFAULT);
echo "Your new password hash is: " . $hashed_password;
?>