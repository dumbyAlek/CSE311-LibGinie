<?php
// Include your database connection file
require_once 'crud/db_config.php';

// Start or resume session
session_start();

// Check if the form was submitted using POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if the UserID is set and is a valid integer
    if (isset($_POST['UserID']) && filter_var($_POST['UserID'], FILTER_VALIDATE_INT)) {
        $userID = intval($_POST['UserID']);

        // Start a transaction for data integrity
        $con->begin_transaction();

        try {
            // Step 1: Determine the user's role
            $stmt = $con->prepare("
                SELECT 
                    CASE 
                        WHEN EXISTS (SELECT 1 FROM Author WHERE UserID = ?) THEN 'author'
                        WHEN EXISTS (SELECT 1 FROM Student WHERE UserID = ?) THEN 'student'
                        WHEN EXISTS (SELECT 1 FROM Teacher WHERE UserID = ?) THEN 'teacher'
                        WHEN EXISTS (SELECT 1 FROM General WHERE UserID = ?) THEN 'general'
                        WHEN EXISTS (SELECT 1 FROM Admin WHERE UserID = ?) THEN 'admin'
                        WHEN EXISTS (SELECT 1 FROM Librarian WHERE UserID = ?) THEN 'librarian'
                        WHEN EXISTS (SELECT 1 FROM Guest WHERE UserID = ?) THEN 'guest'
                        ELSE 'unknown'
                    END AS UserRole
            ");
            $stmt->bind_param("iiiiiii", $userID, $userID, $userID, $userID, $userID, $userID, $userID);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $user_role = $row['UserRole'];

            if ($user_role === 'unknown') {
                throw new Exception("User not found or invalid role.");
            }

            // Step 2: Handle role-specific deletions
            if ($user_role === 'author') {
                // Get AuthorID for book-related deletions
                $stmt = $con->prepare("SELECT AuthorID FROM Author WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $authorID = $row['AuthorID'];
                    // Set AuthorID to NULL in Books to avoid foreign key issues
                    $stmt = $con->prepare("UPDATE Books SET AuthorID = NULL WHERE AuthorID = ?");
                    $stmt->bind_param("i", $authorID);
                    $stmt->execute();
                }
                // Delete from Author table
                $stmt = $con->prepare("DELETE FROM Author WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            } elseif ($user_role === 'student') {
                $stmt = $con->prepare("DELETE FROM Student WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            } elseif ($user_role === 'teacher') {
                $stmt = $con->prepare("DELETE FROM Teacher WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            } elseif ($user_role === 'general') {
                $stmt = $con->prepare("DELETE FROM General WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            } elseif ($user_role === 'admin') {
                // Delete from BooksAdded for admins
                $stmt = $con->prepare("DELETE FROM BooksAdded WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                // Delete from MaintenanceLog for admins
                $stmt = $con->prepare("DELETE FROM MaintenanceLog WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                // Delete from Admin table
                $stmt = $con->prepare("DELETE FROM Admin WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            } elseif ($user_role === 'librarian') {
                $stmt = $con->prepare("DELETE FROM Librarian WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            } elseif ($user_role === 'guest') {
                $stmt = $con->prepare("DELETE FROM Guest WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            }

            // Step 3: Delete common related records
            // Delete from BookInteractions
            $stmt = $con->prepare("DELETE FROM BookInteractions WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Delete from BookReviews
            $stmt = $con->prepare("DELETE FROM BookReviews WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Delete from BookRequests
            $stmt = $con->prepare("DELETE FROM BookRequests WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Delete from Borrow
            $stmt = $con->prepare("DELETE FROM Borrow WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Delete from Reservation
            $stmt = $con->prepare("DELETE FROM Reservation WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Delete from LoginCredentials
            $stmt = $con->prepare("DELETE FROM LoginCredentials WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Delete from Registered (if applicable)
            $stmt = $con->prepare("DELETE FROM Registered WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Delete from Employee (if applicable)
            $stmt = $con->prepare("DELETE FROM Employee WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Step 4: Delete from Members table
            $stmt = $con->prepare("DELETE FROM Members WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();

            // Commit the transaction
            $con->commit();

            // Log the deletion (instead of calling log_action, implement logging here)
            $log_stmt = $con->prepare("
                INSERT INTO ActivityLog (UserID, Action, Description, LogTime)
                VALUES (?, 'Account Deletion', ?, NOW())
            ");
            $description = "User with ID $userID deleted their account.";
            $log_stmt->bind_param("is", $userID, $description);
            $log_stmt->execute();
            $log_stmt->close();

            // Destroy session if the user is deleting their own account
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userID) {
                session_destroy();
                header("Location: ../pages/loginn.php?deleted=true");
                exit();
            } else {
                $_SESSION['message'] = "User deleted successfully.";
                header("Location: MemMng.php"); // Adjust to your user list page
                exit();
            }
        } catch (Exception $e) {
            // Rollback the transaction on error
            $con->rollback();

            // Set error message
            $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
            header("Location: MemMng.php"); // Adjust to your user list page
            exit();
        }
    } else {
        // Invalid UserID
        $_SESSION['error'] = "Invalid user ID provided.";
        header("Location: MemMng.php"); // Adjust to your user list page
        exit();
    }
} else {
    // Not a POST request
    $_SESSION['error'] = "Invalid request method.";
    header("Location: MemMng.php"); // Adjust to your user list page
    exit();
}
?>  