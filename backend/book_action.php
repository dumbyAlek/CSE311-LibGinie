<?php
// Start the session
session_start();

// Set the content type to JSON to ensure a proper response
header('Content-Type: application/json');

// Include the database configuration
require_once 'crud/db_config.php';
require_once 'crud/log_action.php';

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

// Manually convert the string to a boolean to avoid PHP's filter_var quirks
// This is already correct from the previous fix.
$status = ($_POST['status'] === 'true');

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
$sql = "INSERT INTO BookInteractions (UserID, ISBN, {$column_to_update})
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE {$column_to_update} = ?";

$stmt = $con->prepare($sql);

if ($stmt === false) {
    // Handle error if the statement preparation fails
    echo json_encode(['success' => false, 'message' => 'Failed to prepare the SQL statement: ' . $con->error]);
    exit;
}

// --- The corrected part ---
// Convert the boolean to an integer (0 or 1)
$status_int = $status ? 1 : 0;

// Bind the parameters: i (int) for user_id, s (string) for isbn, i (int) for status twice
$stmt->bind_param("isii", $user_id, $isbn, $status_int, $status_int);

// Execute the statement
if ($stmt->execute()) {
    log_action($_SESSION['UserID'], 'Book Interaction', 'User ' . $_SESSION['user_name'] . ' interacted with a book.');
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update book status: ' . $stmt->error]);
}

// Close the statement and connection
$stmt->close();
$con->close();
?>