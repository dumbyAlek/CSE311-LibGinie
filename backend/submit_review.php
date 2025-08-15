<?php
// submit_review.php
error_reporting(0);
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['UserID'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

if (!isset($_POST['isbn'], $_POST['rating'], $_POST['reviewText'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

require_once 'crud/db_config.php';

if (!$con || $con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$userId = (int)$_SESSION['UserID'];
$isbn = $_POST['isbn'];
$rating = (int)$_POST['rating'];
$reviewText = $_POST['reviewText'];

if ($rating < 0 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value.']);
    exit;
}

$sql_check = "SELECT ReviewID FROM BookReviews WHERE UserID = ? AND ISBN = ?";
$stmt_check = $con->prepare($sql_check);

if (!$stmt_check) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare check statement: ' . $con->error]);
    $con->close();
    exit;
}

$stmt_check->bind_param("is", $userId, $isbn);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$stmt_check->close();

if ($result_check->num_rows > 0) {
    $sql_update = "UPDATE BookReviews SET Rating = ?, ReviewText = ?, ReviewTime = NOW() WHERE UserID = ? AND ISBN = ?";
    $stmt_update = $con->prepare($sql_update);
    if (!$stmt_update) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare update statement: ' . $con->error]);
        $con->close();
        exit;
    }
    $stmt_update->bind_param("isss", $rating, $reviewText, $userId, $isbn);
    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update review: ' . $stmt_update->error]);
    }
    $stmt_update->close();
} else {
    $sql_insert = "INSERT INTO BookReviews (UserID, ISBN, Rating, ReviewText, ReviewTime) VALUES (?, ?, ?, ?, NOW())";
    $stmt_insert = $con->prepare($sql_insert);
    if (!$stmt_insert) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare insert statement: ' . $con->error]);
        $con->close();
        exit;
    }
    $stmt_insert->bind_param("isss", $userId, $isbn, $rating, $reviewText);
    if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit review: ' . $stmt_insert->error]);
    }
    $stmt_insert->close();
}

$con->close();
exit;