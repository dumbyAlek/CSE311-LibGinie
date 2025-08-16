<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../pages/loginn.php");
    exit();
}

// Path to your database configuration file
require_once 'crud/db_config.php';
require_once 'crud/log_action.php';

// Check if the connection is successful
if ($con->connect_error) {
    die("Database connection failed: " . $con->connect_error);
}

$email = trim($_POST['email']);
$password_input = $_POST['password'];

$sql = "SELECT m.UserID, m.Name, m.MembershipType, lc.PasswordHash 
        FROM Members AS m
        JOIN LoginCredentials AS lc ON m.UserID = lc.UserID
        WHERE lc.Email = ?";

$stmt = $con->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password_input, $user['PasswordHash'])) {
    $_SESSION['UserID'] = $user['UserID'];
    $_SESSION['user_name'] = $user['Name'];
    $_SESSION['membershipType'] = $user['MembershipType']; 
    $_SESSION['loggedin'] = true;
    // Log the action
    log_action($user['UserID'], 'Login and SignUp', 'User ' . $user['Name'] . ' logged in.');

    header("Location: ../pages/home.php");
    exit();
} else {
        $_SESSION['login_error'] = "Invalid email or password.";
        header("Location: ../pages/loginn.php");
        exit();
    }
} else {
    $_SESSION['login_error'] = "Invalid email or password.";
    header("Location: ../pages/loginn.php");
    exit();
}

$stmt->close();
$con->close();
?>