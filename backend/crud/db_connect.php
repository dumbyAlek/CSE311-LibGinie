<?php
// connect.php
// This is your main database connection file

// It's a good practice to keep credentials in a separate file.
// This file can then be excluded from version control for security.
require_once 'db_config.php';

// The 'mysqli' object is created in db_config.php
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>