<?php
// Start the session to get user information
session_start();

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

// Check for required POST data
if (!isset($_POST['isbn'], $_POST['list_type'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

require_once 'db_config.php';
require_once 'log_action.php';

// Sanitize and validate input
$user_id = $_SESSION['UserID'];
$isbn = $_POST['isbn'];
$list_type = $_POST['list_type'];

// Define a mapping of list types to database columns
$valid_lists = [
    'IsFavorite' => 'favorites',
    'InWishlist' => 'wishlist',
];

// Ensure the list type is valid to prevent SQL injection
if (!array_key_exists($list_type, $valid_lists)) {
    echo json_encode(['success' => false, 'message' => 'Invalid list type.']);
    exit;
}

// Prepare the SQL UPDATE statement
// We set the specified column to 0 (false) for the given user and ISBN.
$sql = "UPDATE BookInteractions SET {$list_type} = 0 WHERE UserID = ? AND ISBN = ?";

// Use a prepared statement to prevent SQL injection
if ($stmt = $con->prepare($sql)) {
    $stmt->bind_param("is", $user_id, $isbn);

    if ($stmt->execute()) {
        // Check if any rows were affected
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Book successfully removed.']);
        } else {
            // No rows were updated, which means the interaction might not exist
            echo json_encode(['success' => false, 'message' => 'Book not found in this list.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
}

// Close the database connection
$con->close();

?>