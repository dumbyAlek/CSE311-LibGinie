<?php
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['UserID'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

if (isset($_GET['isbn'])) {
    $isbn = $_GET['isbn'];
    $userID = $_SESSION['UserID'];
    
    try {
        $stmt = $con->prepare("INSERT INTO BookViews (UserID, ISBN) VALUES (?, ?)");
        $stmt->bind_param("is", $userID, $isbn);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'View logged successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to log view.']);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ISBN not provided.']);
}
$con->close();
?>