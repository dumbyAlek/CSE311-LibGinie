<?php
// Include your database connection file
require_once 'db_config.php';

header('Content-Type: application/json');

// **CRITICAL CHANGE:** Get data from $_GET
$isbn = $_GET['isbn'] ?? null;
$new_copies = $_GET['copies'] ?? null;

// Validate input
if (!$isbn || !is_numeric($new_copies) || $new_copies < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input. ISBN and a non-negative number of copies are required.']);
    exit;
}

$new_copies = (int)$new_copies;

try {
    // Start a transaction for data integrity
    $con->begin_transaction();

    // 1. Get the current number of copies for the book
    $stmt = $con->prepare("SELECT COUNT(*) FROM BookCopy WHERE ISBN = ?");
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $stmt->bind_result($current_copies);
    $stmt->fetch();
    $stmt->close();

    // 2. Determine the action (add or remove)
    if ($new_copies > $current_copies) {
        $copies_to_add = $new_copies - $current_copies;
        $stmt = $con->prepare("INSERT INTO BookCopy (ISBN) VALUES (?)");
        for ($i = 0; $i < $copies_to_add; $i++) {
            $stmt->bind_param("s", $isbn);
            $stmt->execute();
        }
        $stmt->close();
    } elseif ($new_copies < $current_copies) {
        $copies_to_remove = $current_copies - $new_copies;
        
        $stmt = $con->prepare("SELECT COUNT(*) FROM BookCopy WHERE ISBN = ? AND Status = 'Available'");
        $stmt->bind_param("s", $isbn);
        $stmt->execute();
        $stmt->bind_result($available_copies);
        $stmt->fetch();
        $stmt->close();

        if ($copies_to_remove > $available_copies) {
            $con->rollback();
            echo json_encode(['success' => false, 'message' => 'Cannot delete copies. Not enough are available.']);
            exit;
        }

        $stmt = $con->prepare("DELETE FROM BookCopy WHERE CopyID IN (
            SELECT CopyID FROM (
                SELECT CopyID FROM BookCopy 
                WHERE ISBN = ? AND Status = 'Available'
                ORDER BY CopyID ASC 
                LIMIT ?
            ) as t
        )");
        $stmt->bind_param("si", $isbn, $copies_to_remove);
        $stmt->execute();
        $stmt->close();
    }

    $con->commit();
    echo json_encode(['success' => true, 'message' => 'Book copies updated successfully.']);

} catch (Exception $e) {
    $con->rollback();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$con->close();
?>