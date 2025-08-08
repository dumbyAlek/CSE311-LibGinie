<?php
// db_config.php
// Store your database credentials here
$servername = "localhost";
$username = "libginie_user"; // Your database username
$password = "NiloyGoddie";     // Your database password
$dbname = "library_db"; // Your database name

// Establish the connection using mysqli
$con = mysqli_connect($servername, $username, $password, $dbname);
?>