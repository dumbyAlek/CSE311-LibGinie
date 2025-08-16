<?php
session_start();
$_SESSION['loggedin'] = true;
$_SESSION['membershipType'] = 'guest';
$_SESSION['UserID'] = 0; // A placeholder ID for guests

header("Location: ../../pages/home.php");
exit;
?>