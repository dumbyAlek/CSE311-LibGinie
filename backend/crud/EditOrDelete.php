<?php
require_once 'db_config.php';
header('Content-Type: application/json');

// Handle GET request to fetch a single book's data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['isbn'])) {
    $isbn = $_GET['isbn'];

    try {
        $stmt = $con->prepare("SELECT b.ISBN, b.Title, b.CoverPicture, b.Category, b.Publisher, b.PublishedYear, m.Name AS AuthorName FROM Books b JOIN Author a ON b.AuthorID = a.AuthorID JOIN Members m ON a.UserID = m.UserID WHERE b.ISBN = ?");
        $stmt->bind_param("s", $isbn);
        $stmt->execute();
        $result = $stmt->get_result();
        $book = $result->fetch_assoc();

        if ($book) {
            echo json_encode(['success' => true, 'book' => $book]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Book not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    $con->close();
    exit;
}

// Handle POST request for updating or deleting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';
    $isbn = $data['isbn'] ?? '';

    if ($action === 'update_book') {
        $title = $data['title'];
        $author_name = $data['author_name'];
        $category = $data['category'];
        $publisher = $data['publisher'];
        $published_year = $data['published_year'];
        $cover_picture = $data['cover_picture'];

        try {
            // First, update the Author's name if it has changed
            $stmt = $con->prepare("UPDATE Members m JOIN Author a ON m.UserID = a.UserID JOIN Books b ON a.AuthorID = b.AuthorID SET m.Name = ? WHERE b.ISBN = ?");
            $stmt->bind_param("ss", $author_name, $isbn);
            $stmt->execute();
            $stmt->close();
            
            // Now, update the book details
            $stmt = $con->prepare("UPDATE Books SET Title = ?, Category = ?, Publisher = ?, PublishedYear = ?, CoverPicture = ? WHERE ISBN = ?");
            $stmt->bind_param("ssiiss", $title, $category, $publisher, $published_year, $cover_picture, $isbn);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Book updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update book.']);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Update database error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'delete_book') {
        try {
            $stmt = $con->prepare("DELETE FROM Books WHERE ISBN = ?");
            $stmt->bind_param("s", $isbn);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Book deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete book.']);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Delete database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
    $con->close();
    exit;
}

// Fallback for invalid requests
echo json_encode(['success' => false, 'message' => 'Invalid request method or action.']);