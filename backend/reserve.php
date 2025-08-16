<?php
session_start();
require_once 'crud/db_config.php';
require_once 'crud/log_action.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

$user_id = $_SESSION['UserID'];
$isbn = $_GET['isbn'] ?? '';
$redirect = $_GET['redirect'] ?? '../pages/BookPage.php?isbn=' . urlencode($isbn);

if ($isbn === '') {
    header("Location: $redirect&msg=" . urlencode("No ISBN provided."));
    exit;
}

$sql = "SELECT CopyID FROM BookCopy WHERE ISBN = ? AND Status = 'Available' LIMIT 1";
$stmt = $con->prepare($sql);
$stmt->bind_param("s", $isbn);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $copy_id = $row['CopyID'];

    $con->begin_transaction();
    try {
        $con->query("UPDATE BookCopy SET Status='Reserved' WHERE CopyID=$copy_id");
        $stmt2 = $con->prepare("INSERT INTO Reservation (UserID, CopyID, ReservationDate) VALUES (?, ?, CURDATE())");
        $stmt2->bind_param("ii", $user_id, $copy_id);
        $stmt2->execute();
        $con->commit();
        log_action($_SESSION['UserID'], 'Borrow and Reserve', 'User ' . $_SESSION['user_name'] . ' reserved a book.');
        $msg = "Book reserved successfully!";
    } catch (Exception $e) {
        $con->rollback();
        $msg = "Error reserving book.";
    }
} else {
    $msg = "Sorry, no available copies for reservation.";
}

header("Location: $redirect&msg=" . urlencode($msg));
exit;
?>
