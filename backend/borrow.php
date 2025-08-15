<?php
session_start();
require_once 'crud/db_config.php';

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
    $due_date = date('Y-m-d', strtotime('+14 days'));

    $con->begin_transaction();
    try {
        $con->query("UPDATE BookCopy SET Status='Borrowed' WHERE CopyID=$copy_id");
        $stmt2 = $con->prepare("INSERT INTO Borrow (UserID, CopyID, Borrow_Date, Due_Date) VALUES (?, ?, CURDATE(), ?)");
        $stmt2->bind_param("iis", $user_id, $copy_id, $due_date);
        $stmt2->execute();
        $con->commit();
        $msg = "Book borrowed successfully! Due date: $due_date";
    } catch (Exception $e) {
        $con->rollback();
        $msg = "Error borrowing book.";
    }
} else {
    $msg = "Sorry, no available copies for borrowing.";
}

header("Location: $redirect&msg=" . urlencode($msg));
exit;
?>
