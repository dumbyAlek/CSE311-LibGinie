<?php
require_once 'db_config.php';
header('Content-Type: application/json');

// Handle GET request to fetch a single book's data for editing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['isbn'])) {
    $isbn = $_GET['isbn'];

    try {
        $stmt = $con->prepare("
            SELECT 
                b.ISBN, 
                b.Title, 
                b.CoverPicture, 
                b.Category, 
                b.Publisher, 
                b.PublishedYear, 
                b.SectionID, 
                m.Name AS AuthorName, 
                GROUP_CONCAT(g.GenreName SEPARATOR ', ') AS Genres 
            FROM Books b 
            JOIN Author a ON b.AuthorID = a.AuthorID 
            JOIN Members m ON a.UserID = m.UserID
            LEFT JOIN Book_Genres bg ON b.ISBN = bg.ISBN
            LEFT JOIN Genres g ON bg.GenreID = g.GenreID
            WHERE b.ISBN = ?
            GROUP BY b.ISBN
        ");
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
        $author_name = trim($data['author_name']);
        $category = $data['category'] ?? null;
        $publisher = $data['publisher'] ?? null;
        $published_year = $data['published_year'] ?? null;
        $cover_picture = $data['cover_picture'] ?? null;
        $section_id = $data['section_id'];
        $genres = isset($data['genres']) ? explode(',', $data['genres']) : [];

        try {
            // Find or create AuthorID based on the new author_name
            $author_id = null;
            $stmt_author = $con->prepare("
                SELECT a.AuthorID FROM Author a 
                JOIN Members m ON a.UserID = m.UserID 
                WHERE m.Name = ?
            ");
            $stmt_author->bind_param("s", $author_name);
            $stmt_author->execute();
            $result_author = $stmt_author->get_result();
            if ($row_author = $result_author->fetch_assoc()) {
                $author_id = $row_author['AuthorID'];
            } else {
                // If author doesn't exist, create a new Member and Author record
                $stmt_member = $con->prepare("INSERT INTO Members (Name, Email, MembershipType) VALUES (?, ?, 'Registered')");
                $email = str_replace(' ', '.', strtolower($author_name)) . '@example.com';
                $stmt_member->bind_param("ss", $author_name, $email);
                $stmt_member->execute();
                $new_user_id = $stmt_member->insert_id;
                $stmt_member->close();

                $stmt_reg = $con->prepare("INSERT INTO Registered (UserID, RegistrationDate) VALUES (?, NOW())");
                $stmt_reg->bind_param("i", $new_user_id);
                $stmt_reg->execute();
                $stmt_reg->close();

                $stmt_new_author = $con->prepare("INSERT INTO Author (UserID) VALUES (?)");
                $stmt_new_author->bind_param("i", $new_user_id);
                $stmt_new_author->execute();
                $author_id = $stmt_new_author->insert_id;
                $stmt_new_author->close();
            }
            $stmt_author->close();
            
            // Validate or create section
if (!empty($section_id) && is_numeric($section_id)) {
    $stmt_check_section = $con->prepare("SELECT SectionID FROM Library_Sections WHERE SectionID = ?");
    $stmt_check_section->bind_param("i", $section_id);
    $stmt_check_section->execute();
    $result_check_section = $stmt_check_section->get_result();

    if ($result_check_section->num_rows === 0) {
        // Let MySQL assign the ID if table uses AUTO_INCREMENT
        $new_section_name = "Section " . $section_id;
        $stmt_create_section = $con->prepare("INSERT INTO Library_Sections (Name) VALUES (?)");
        $stmt_create_section->bind_param("s", $new_section_name);
        $stmt_create_section->execute();
        $section_id = $stmt_create_section->insert_id; // Use the real SectionID
        $stmt_create_section->close();
    }
    $stmt_check_section->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Section ID']);
    exit;
}


            // Update the main Books table
            $update_query = "UPDATE Books SET Title = ?, AuthorID = ?, SectionID = ?, Category = ?, Publisher = ?, PublishedYear = ?";
            $params = [$title, $author_id, $section_id, $category, $publisher, $published_year];
            $types = "siissi";
            
            if ($cover_picture) {
                $update_query .= ", CoverPicture = ?";
                $params[] = $cover_picture;
                $types .= "s";
            }
            $update_query .= " WHERE ISBN = ?";
            $params[] = $isbn;
            $types .= "s";

            $stmt_update = $con->prepare($update_query);
            $stmt_update->bind_param($types, ...$params);

            if ($stmt_update->execute()) {
                // Remove from specialized tables to handle category change
                $stmt_del_textbook = $con->prepare("DELETE FROM TextBook WHERE ISBN = ?");
                $stmt_del_textbook->bind_param("s", $isbn);
                $stmt_del_textbook->execute();
                $stmt_del_textbook->close();

                $stmt_del_comics = $con->prepare("DELETE FROM Comics WHERE ISBN = ?");
                $stmt_del_comics->bind_param("s", $isbn);
                $stmt_del_comics->execute();
                $stmt_del_comics->close();

                $stmt_del_novels = $con->prepare("DELETE FROM Novels WHERE ISBN = ?");
                $stmt_del_novels->bind_param("s", $isbn);
                $stmt_del_novels->execute();
                $stmt_del_novels->close();

                $stmt_del_magazines = $con->prepare("DELETE FROM Magazines WHERE ISBN = ?");
                $stmt_del_magazines->bind_param("s", $isbn);
                $stmt_del_magazines->execute();
                $stmt_del_magazines->close();

                // Insert into the new specialized table based on category
                if ($category === 'Text Books') {
                    $stmt_insert = $con->prepare("INSERT INTO TextBook (ISBN, Editions, Subject) VALUES (?, ?, ?)");
                    $editions = null; // Assuming these are not provided in the form
                    $subject = null;
                    $stmt_insert->bind_param("sis", $isbn, $editions, $subject);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                } elseif ($category === 'Comics') {
                    $stmt_insert = $con->prepare("INSERT INTO Comics (ISBN, Artist, Studio) VALUES (?, ?, ?)");
                    $artist = null;
                    $studio = null;
                    $stmt_insert->bind_param("sss", $isbn, $artist, $studio);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                } elseif ($category === 'Novels') {
                    $stmt_insert = $con->prepare("INSERT INTO Novels (ISBN, Narration) VALUES (?, ?)");
                    $narration = null;
                    $stmt_insert->bind_param("ss", $isbn, $narration);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                } elseif ($category === 'Magazines') {
                    $stmt_insert = $con->prepare("INSERT INTO Magazines (ISBN, Timeline) VALUES (?, ?)");
                    $timeline = null;
                    $stmt_insert->bind_param("ss", $isbn, $timeline);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }

                // Delete existing genres for the book
                $stmt_delete_genres = $con->prepare("DELETE FROM Book_Genres WHERE ISBN = ?");
                $stmt_delete_genres->bind_param("s", $isbn);
                $stmt_delete_genres->execute();
                $stmt_delete_genres->close();

                // Insert new genres
                foreach ($genres as $genre_name) {
                    $genre_name = trim($genre_name);
                    if (!empty($genre_name)) {
                        $stmt_genre = $con->prepare("SELECT GenreID FROM Genres WHERE GenreName = ?");
                        $stmt_genre->bind_param("s", $genre_name);
                        $stmt_genre->execute();
                        $result_genre = $stmt_genre->get_result();
                        
                        $genre_id = null;
                        if ($row_genre = $result_genre->fetch_assoc()) {
                            $genre_id = $row_genre['GenreID'];
                        } else {
                            $stmt_new_genre = $con->prepare("INSERT INTO Genres (GenreName) VALUES (?)");
                            $stmt_new_genre->bind_param("s", $genre_name);
                            $stmt_new_genre->execute();
                            $genre_id = $stmt_new_genre->insert_id;
                            $stmt_new_genre->close();
                        }
                        $stmt_genre->close();
                        
                        if ($genre_id) {
                            $stmt_book_genre = $con->prepare("INSERT INTO Book_Genres (ISBN, GenreID) VALUES (?, ?)");
                            $stmt_book_genre->bind_param("si", $isbn, $genre_id);
                            $stmt_book_genre->execute();
                            $stmt_book_genre->close();
                        }
                    }
                }
                echo json_encode(['success' => true, 'message' => 'Book updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update book.']);
            }
            $stmt_update->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Update database error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'delete_book') {
        try {
            // Delete from all tables that have foreign key constraints
            $con->begin_transaction();

            $stmt_del_bg = $con->prepare("DELETE FROM Book_Genres WHERE ISBN = ?");
            $stmt_del_bg->bind_param("s", $isbn);
            $stmt_del_bg->execute();
            $stmt_del_bg->close();

            $stmt_del_va = $con->prepare("DELETE FROM BooksAdded WHERE ISBN = ?");
            $stmt_del_va->bind_param("s", $isbn);
            $stmt_del_va->execute();
            $stmt_del_va->close();

            // Delete from child tables (TextBook, Comics, etc.) first
            $stmt_del_comic = $con->prepare("DELETE FROM Comics WHERE ISBN = ?");
            $stmt_del_comic->bind_param("s", $isbn);
            $stmt_del_comic->execute();
            $stmt_del_comic->close();

            $stmt_del_novel = $con->prepare("DELETE FROM Novels WHERE ISBN = ?");
            $stmt_del_novel->bind_param("s", $isbn);
            $stmt_del_novel->execute();
            $stmt_del_novel->close();

            $stmt_del_mag = $con->prepare("DELETE FROM Magazines WHERE ISBN = ?");
            $stmt_del_mag->bind_param("s", $isbn);
            $stmt_del_mag->execute();
            $stmt_del_mag->close();
            
            $stmt_del_textbook = $con->prepare("DELETE FROM TextBook WHERE ISBN = ?");
            $stmt_del_textbook->bind_param("s", $isbn);
            $stmt_del_textbook->execute();
            $stmt_del_textbook->close();

            $stmt_del_views = $con->prepare("DELETE FROM BookViews WHERE ISBN = ?");
            $stmt_del_views->bind_param("s", $isbn);
            $stmt_del_views->execute();
            $stmt_del_views->close();
            
            $stmt_del_copy_reserve = $con->prepare("DELETE FROM Reservation WHERE CopyID IN (SELECT CopyID FROM BookCopy WHERE ISBN = ?)");
            $stmt_del_copy_reserve->bind_param("s", $isbn);
            $stmt_del_copy_reserve->execute();
            $stmt_del_copy_reserve->close();

            $stmt_del_copy_borrow = $con->prepare("DELETE FROM Borrow WHERE CopyID IN (SELECT CopyID FROM BookCopy WHERE ISBN = ?)");
            $stmt_del_copy_borrow->bind_param("s", $isbn);
            $stmt_del_copy_borrow->execute();
            $stmt_del_copy_borrow->close();

            $stmt_del_copy_maintenance = $con->prepare("DELETE FROM MaintenanceLog WHERE CopyID IN (SELECT CopyID FROM BookCopy WHERE ISBN = ?)");
            $stmt_del_copy_maintenance->bind_param("s", $isbn);
            $stmt_del_copy_maintenance->execute();
            $stmt_del_copy_maintenance->close();
            
            $stmt_del_copy = $con->prepare("DELETE FROM BookCopy WHERE ISBN = ?");
            $stmt_del_copy->bind_param("s", $isbn);
            $stmt_del_copy->execute();
            $stmt_del_copy->close();


            // Finally, delete the book itself
            $stmt = $con->prepare("DELETE FROM Books WHERE ISBN = ?");
            $stmt->bind_param("s", $isbn);

            if ($stmt->execute()) {
                $con->commit();
                echo json_encode(['success' => true, 'message' => 'Book and associated data deleted successfully.']);
            } else {
                $con->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to delete book.']);
            }
            $stmt->close();

        } catch (Exception $e) {
            $con->rollback();
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