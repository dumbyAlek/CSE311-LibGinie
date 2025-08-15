<?php
// Start the session
session_start();

// Set the content type to JSON to ensure a proper response
header('Content-Type: application/json');

// Include the database configuration
require_once 'crud/db_config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'User is not logged in.']);
    exit;
}

// Check if all necessary POST variables are set
if (!isset($_POST['isbn']) || !isset($_POST['action']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Sanitize and retrieve POST data
$isbn = $_POST['isbn'];
$user_id = $_SESSION['UserID'];
$action = $_POST['action'];
$status = filter_var($_POST['status'], FILTER_VALIDATE_BOOLEAN);

// Determine the column to update based on the action
$column_to_update = '';
switch ($action) {
    case 'read':
        $column_to_update = 'IsRead';
        break;
    case 'wishlist':
        $column_to_update = 'InWishlist';
        break;
    case 'favorite':
        $column_to_update = 'IsFavorite';
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        exit;
}

// Prepare the SQL statement using ON DUPLICATE KEY UPDATE
// This is an efficient way to handle both INSERT and UPDATE in a single query.
// It inserts a new row if the combination of UserID and ISBN doesn't exist,
// or updates the specified column if it does.
$sql = "INSERT INTO BookInteractions (UserID, ISBN, {$column_to_update}) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE {$column_to_update} = ?";

$stmt = $con->prepare($sql);

if ($stmt === false) {
    // Handle error if the statement preparation fails
    echo json_encode(['success' => false, 'message' => 'Failed to prepare the SQL statement: ' . $con->error]);
    exit;
}

// Bind the parameters to the statement
$stmt->bind_param("isis", $user_id, $isbn, $status, $status);

// Execute the statement
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update book status: ' . $stmt->error]);
}

// Close the statement and connection
$stmt->close();
$con->close();
?>