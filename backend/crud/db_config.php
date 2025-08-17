
<?php
// db_config.php
ini_set('display_errors', '0');        // Turn off displaying errors
ini_set('display_startup_errors', '0'); // Turn off startup errors
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); 

$servername = "localhost";
$username = "libginie_user";
$password = "NiloyGoddie";
$dbname = "library_db";

$con = mysqli_connect($servername, $username, $password, $dbname);
?>