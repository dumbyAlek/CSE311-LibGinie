<?php
require_once 'db_config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'books' => []];

if (isset($_GET['UserID']) && isset($_GET['type'])) {
    $userID = $_GET['UserID'];
    $type = $_GET['type'];

    try {
        if ($type === 'borrowed') {
            $sql = "
                SELECT
                    b.BorrowID, b.Due_Date, k.Title
                FROM Borrow b
                JOIN BookCopy c ON b.CopyID = c.CopyID
                JOIN Books k ON c.ISBN = k.ISBN
                WHERE b.UserID = ? AND b.Return_Date IS NULL
            ";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("i", $userID);
        } elseif ($type === 'overdue') {
            $sql = "
                SELECT
                    b.BorrowID, b.Due_Date, k.Title
                FROM Borrow b
                JOIN BookCopy c ON b.CopyID = c.CopyID
                JOIN Books k ON c.ISBN = k.ISBN
                WHERE b.UserID = ? AND b.Return_Date IS NULL AND b.Due_Date < CURDATE()
            ";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("i", $userID);
        } else {
            // Handle other types or invalid input
            $response['message'] = "Invalid book type requested.";
            echo json_encode($response);
            $con->close();
            exit;
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response['books'][] = $row;
            }
            $response['success'] = true;
        } else {
            $response['message'] = "No books found for this member.";
            $response['success'] = true; // Still a successful query, just no results
        }

        $stmt->close();

    } catch (Exception $e) {
        $response['message'] = "Database error: " . $e->getMessage();
    }
} else {
    $response['message'] = "Missing UserID or type parameter.";
}

echo json_encode($response);

$con->close();
?>