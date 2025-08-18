<?php
// Assume the user's role is stored in a session after login
session_start();

$user_role = $_SESSION['membershipType'];

require_once 'crud/db_config.php';
require_once 'crud/log_action.php';

// === SEARCH BOOKS LOGIC ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    header('Content-Type: application/json');

    $search_query = '%' . $_GET['query'] . '%';

    try {
        $stmt = $con->prepare("SELECT b.ISBN, b.Title, b.CoverPicture, b.Category, m.Name AS AuthorName, a.AuthorTitle, b.Description 
                                FROM Books b LEFT JOIN Author a ON b.AuthorID = a.AuthorID LEFT JOIN Members m ON a.UserID = m.UserID 
                                WHERE b.Title LIKE ? OR b.ISBN LIKE ? OR m.Name LIKE ?
                                ORDER BY b.Title");
        $stmt->bind_param("sss", $search_query, $search_query, $search_query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = [
                'isbn' => $row['ISBN'],
                'title' => $row['Title'],
                'category' => $row['Category'],
                'author_name' => $row['AuthorName'],
                'book_cover' => $row['CoverPicture'],
                'description' => $row['Description']
            ];
        }
        
        echo json_encode(['success' => true, 'books' => $books]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    $con->close();
    exit;
}

// === ADD BOOK LOGIC ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if ($data && isset($data['action']) && $data['action'] === 'add_book') {
        $isbn = $data['isbn'];
        $title = $data['title'];
        $author_name = trim($data['author_name']);
        $category = $data['category'];
        $publisher = $data['publisher'];
        $published_year = $data['published_year'];
        $cover_picture = $data['cover_picture'];
        $section_id = $data['section_id']; 
        $genres = isset($data['genres']) ? explode(',', $data['genres']) : [];
        $description = $data['description'] ?? null;
        // Category-specific fields
        $subject = $data['subject'] ?? null;
        $edition = $data['edition'] ?? null;
        $artist = $data['artist'] ?? null;
        $studio = $data['studio'] ?? null;
        $narrative = $data['narrative'] ?? null;
        $timeline = $data['timeline'] ?? null;

        try {
            $con->begin_transaction();

            // Validate or create section
            if (!empty($section_id) && is_numeric($section_id)) {
                $stmt_check_section = $con->prepare("SELECT SectionID FROM Library_Sections WHERE SectionID = ?");
                $stmt_check_section->bind_param("i", $section_id);
                $stmt_check_section->execute();
                $result_check_section = $stmt_check_section->get_result();

                if ($result_check_section->num_rows === 0) {
                    $new_section_name = "Section " . $section_id;
                    $stmt_create_section = $con->prepare("INSERT INTO Library_Sections (Name) VALUES (?)");
                    $stmt_create_section->bind_param("s", $new_section_name);
                    $stmt_create_section->execute();
                    $section_id = $stmt_create_section->insert_id;
                    $stmt_create_section->close();
                }
                $stmt_check_section->close();
            } else {
                $con->rollback();
                echo json_encode(['success' => false, 'message' => 'Invalid Section ID']);
                exit;
            }

            // Step 1: Find or create UserID in Members
            $user_id = null;
            $stmt = $con->prepare("SELECT UserID FROM Members WHERE Name = ?");
            $stmt->bind_param("s", $author_name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $user_id = $row['UserID'];
            } else {
                $email = str_replace(' ', '.', strtolower($author_name)) . '@example.com';
                $stmt = $con->prepare("INSERT INTO Members (Name, Email, MembershipType) VALUES (?, ?, 'Registered')");
                $stmt->bind_param("ss", $author_name, $email);
                $stmt->execute();
                $user_id = $stmt->insert_id;
            }
            $stmt->close();
            
            // Step 2: Ensure Registered entry
            $stmt = $con->prepare("SELECT UserID FROM Registered WHERE UserID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt = $con->prepare("INSERT INTO Registered (UserID, RegistrationDate) VALUES (?, NOW())");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            }
            $stmt->close();

            // Step 3: Find or create Author entry
            $author_id = null;
            $stmt = $con->prepare("SELECT AuthorID FROM Author WHERE UserID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $author_id = $row['AuthorID'];
            } else {
                $stmt = $con->prepare("INSERT INTO Author (UserID) VALUES (?)");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $author_id = $stmt->insert_id;
            }
            $stmt->close();
            
            // Step 4: Insert into Books table
            if ($author_id) {
                $stmt = $con->prepare("INSERT INTO Books (ISBN, Title, AuthorID, SectionID, Category, Publisher, PublishedYear, Description, CoverPicture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiississ", $isbn, $title, $author_id, $section_id, $category, $publisher, $published_year, $description, $cover_picture);

                if ($stmt->execute()) {
                    // Step 5: Insert into category-specific table
                    switch ($category) {
                        case 'Text Books':
                            $stmt_insert = $con->prepare("INSERT INTO TextBook (ISBN, Subject, Editions) VALUES (?, ?, ?)");
                            $stmt_insert->bind_param("ssi", $isbn, $subject, $edition);
                            break;
                        case 'Comics':
                            $stmt_insert = $con->prepare("INSERT INTO Comics (ISBN, Artist, Studio) VALUES (?, ?, ?)");
                            $stmt_insert->bind_param("sss", $isbn, $artist, $studio);
                            break;
                        case 'Novels':
                            $stmt_insert = $con->prepare("INSERT INTO Novels (ISBN, Narration) VALUES (?, ?)");
                            $stmt_insert->bind_param("ss", $isbn, $narrative);
                            break;
                        case 'Magazines':
                            $stmt_insert = $con->prepare("INSERT INTO Magazines (ISBN, Timeline) VALUES (?, ?)");
                            $stmt_insert->bind_param("ss", $isbn, $timeline);
                            break;
                        default:
                            $stmt_insert = null;
                    }
                    if ($stmt_insert) {
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    }

                    // Step 6: Handle genres
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

                    // Step 7: Add entry to BooksAdded table
                    $admin_user_id = $_SESSION['UserID'] ?? null;
                    if ($admin_user_id) {
                        $stmt_books_added = $con->prepare("INSERT INTO BooksAdded (ISBN, UserID, AddDate) VALUES (?, ?, NOW())");
                        $stmt_books_added->bind_param("si", $isbn, $admin_user_id);
                        $stmt_books_added->execute();
                        $stmt_books_added->close();
                    }
                    
                    $con->commit();
                    log_action($_SESSION['UserID'], 'Book Management', 'User ' . $_SESSION['user_name'] . ' added a new book.');
                    echo json_encode(['success' => true, 'message' => 'Book added successfully.']);
                } else {
                    $con->rollback();
                    echo json_encode(['success' => false, 'message' => 'Failed to add book.']);
                }
                $stmt->close();
            } else {
                $con->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to get or create Author ID.']);
            }
        } catch (Exception $e) {
            $con->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data or action.']);
    }
    $con->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Book Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />

    <style>

        :root {
            --sidebar-width: 400px;
            --right-sidebar-width: 300px;
            --sidebar-bg: #f8f9fa; /* Light theme background */
            --text-color: #333; /* Default text color */
            --border-color: #ddd; /* Border color */
            --highlight-color: #7b3fbf; /* Highlight color for headers */
            --text-color-dark: #333; /* Dark text for section headers */
            --text-color-light: #666; /* Lighter text for shelf items */
        }

        body {
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            transition: background-color 0.3s, color 0.3s;
            background-color: #eed9c4;
        }

        body.dark-theme {
            background-color: #3d3125ff;
            color: #eee;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            width: var(--sidebar-width);
            height: 100vh;
            background-image: url('../images/sidebar.jpg');
            background-size: cover;
            background-position: center;
            padding: 20px;
            color: white;
            overflow-y: auto;
            transition: transform 0.3s ease-in-out;
        }
        .sidebar.closed {
            transform: translateX(calc(-1 * var(--sidebar-width)));
        }
        .sidebar .logo {
            max-width: 200px;
            margin: 20px auto;
            display: block;
        }

        .sidebar.closed .logo {
            display: none;
        }

        .sidebar ul {
            list-style: none;
            padding-left: 0;
            margin-top: 30px;
        }

        .sidebar li {
            margin-bottom: 0.5rem;
        }

        .sidebar a {
            text-decoration: none;
            color: white;
            font-size: 1.1rem;
            padding: 8px 12px;
            display: block;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .sidebar .collapsible-header {
            font-size: 1.1rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 8px 12px;
            color: white;
            border-radius: 4px;
        }

        .sidebar ul.sublist {
            padding-left: 20px;
            margin-top: 5px;
            display: none;
        }

        .sidebar ul.sublist.show {
            display: block;
        }

        .sidebar-toggle-btn {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            width: 40px;
            height: 40px;
            background-color: #7b3fbf;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle-btn::before {
            content: "≡";
            color: white;
            font-size: 20px;
            transform: rotate(90deg);
        }

        .content-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s;
        }
        
        .content-wrapper-no-sidebar {
            margin-left: 0;
            padding-left: 70px; /* Add padding to prevent content from going under the toggle button */
        }

        .sidebar.closed ~ .content-wrapper {
            margin-left: 0;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            margin: 40px 0 20px;
        }

        .book-section {
            white-space: nowrap;
            overflow-x: auto;
            padding-bottom: 10px;
        }

        .book-card {
            display: inline-block;
            width: 150px;
            margin-right: 15px;
            background: white;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
        }

        .book-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        footer {
            background: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .theme-switch-wrapper {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            z-index: 1000;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        .arrow {
            margin-right: 8px;
        }

        input:checked + .slider {
            background-color: #7b3fbf;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .notification-icon {
            position: fixed;
            top: 60px;
            right: 20px;
            z-index: 1000;
            color: #7b3fbf;
            font-size: 24px;
            cursor: pointer;
        }
        .librarian-tasks {
            position: fixed;
            top: 150px;
            right: 20px;
            width: 300px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 990;
            transition: transform 0.3s ease-in-out;
        }
        .librarian-tasks.collapsed {
            transform: translateX(calc(100% + 20px));
        }
        .tasks-header {
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .task-list li {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .disabled-link {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Responsive */
        @media (max-width: 767px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.closed {
                transform: translateX(-100%);
            }

            .content-wrapper {
                margin-left: 0 !important;
            }
        }

        .main-content.sidebar-open {
            margin-right: var(--right-sidebar-width); 
        }
        
        .sidebar-toggle-btn-right {
            position: fixed; /* Use fixed or absolute to position relative to the viewport/container */
            top: 15px;
            right: 20px; /* Adjust this value to set the initial position when the sidebar is closed */
            z-index: 1000;
            transition: right 0.3s ease-in-out; /* Smooth transition for the movement */
        }

        /* New class for when the sidebar is open */
        .sidebar-toggle-btn-right.active {
            right: 320px; /* This value should be the width of the sidebar plus a small margin */
        }

        /* Right Sidebar  */
        .sidebar-right {
            width: 300px;
            background-color: #f8f9fa; /* Fallback for light theme */
            color: #333; /* Fallback text color */
            padding: 20px;
            border-left: 1px solid #ddd; /* Fallback border color */
            overflow-y: auto;
            position: fixed;
            right: -300px;
            top: 0;
            height: 100vh;
            transition: right 0.3s ease-in-out;
            z-index: 1200;
        }

        .sidebar-right.active {
            right: 0;
        }

        .sidebar-right h4 {
            color: #7b3fbf; /* Fallback for highlight color */
            font-family: 'Montserrat', sans-serif;
        }

        body.dark-theme .sidebar-right {
            background-color: #3d3125;
            color: #eee;
            border-left: 1px solid #555;
        }

        body.dark-theme .sidebar-right h4 {
            color: #7b3fbf;
        }

        body.dark-theme .sidebar-right .section-link {
            color: #eee;
        }

        body.dark-theme .sidebar-right .shelf-link {
            color: #ccc;
        }
        
        /* Specific styles for this page */
        .container { max-width: 1200px; margin-top: 50px; }
        .card { margin-bottom: 20px; }
        .table img { max-width: 50px; height: auto; }
    </style>
</head>

<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

    <?php include 'sidebar.php'; ?>
   
    
    <button class="btn btn-primary sidebar-toggle-btn-right" onclick="toggleRightSidebar()" style="z-index: 1100;">📚 Sections</button>

     <?php include 'sideSection.php'; ?>


        


    <div class="content-wrapper content-wrapper-no-sidebar">
        <div class="theme-switch-wrapper">
            <label class="theme-switch" for="themeToggle">
                <input type="checkbox" id="themeToggle" />
                <span class="slider"></span>
            </label>
        </div>
         
        <main class="container mt-4">
            <h1 class="section-title">Book Management</h1>
            <hr>

            <div class="card p-4">
                <h4>Add New Book</h4>
                <form id="addBookForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="isbn" class="form-label">ISBN</label>
                        <input type="text" class="form-control" id="isbn" name="isbn" required>
                    </div>
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="author_name" class="form-label">Author Name</label>
                        <input type="text" class="form-control" id="author_name" name="author_name" required>
                    </div>
                    <div class="form-group, mb-3">
                        <label for="category">Category</label>
                        <select class="form-control" id="category" name="category" required>
                            <option value="">Select a Category</option>
                            <option value="Text Books">Text Books</option>
                            <option value="Comics">Comics</option>
                            <option value="Novels">Novels</option>
                            <option value="Magazines">Magazines</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="section_id" class="form-label">Section ID</label>
                        <input type="number" class="form-control" id="section_id" name="section_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="genres" class="form-label">Genre</label>
                        <input type="text" class="form-control" id="genres" name="genres">
                    </div>
                    <div class="mb-3">
                        <label for="publisher" class="form-label">Publisher</label>
                        <input type="text" class="form-control" id="publisher" name="publisher">
                    </div>
                    <div class="mb-3">
                        <label for="published_year" class="form-label">Published Year</label>
                        <input type="number" class="form-control" id="published_year" name="published_year">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                    </div>

                    <div id="addTextbookFields" style="display: none;">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject">
                        </div>
                        <div class="mb-3">
                            <label for="edition" class="form-label">Edition</label>
                            <input type="number" class="form-control" id="edition" name="edition">
                        </div>
                    </div>

                    <div id="addComicFields" style="display: none;">
                        <div class="mb-3">
                            <label for="artist" class="form-label">Artist</label>
                            <input type="text" class="form-control" id="artist" name="artist">
                        </div>
                        <div class="mb-3">
                            <label for="studio" class="form-label">Studio</label>
                            <input type="text" class="form-control" id="studio" name="studio">
                        </div>
                    </div>

                    <div id="addNovelFields" style="display: none;">
                        <div class="mb-3">
                            <label for="narrative" class="form-label">Narrative</label>
                            <input type="text" class="form-control" id="narrative" name="narrative">
                        </div>
                    </div>

                    <div id="addMagazinesFields" style="display: none;">
                        <div class="mb-3">
                            <label for="timeline" class="form-label">Timeline</label>
                            <input type="text" class="form-control" id="timeline" name="timeline">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="copies">Number of Copies:</label>
                        <input type="number" class="form-control" id="copies" name="copies" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="book_cover" class="form-label">Book Cover Image</label>
                        <input class="form-control" type="file" id="book_cover" name="book_cover" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary">Add Book</button>
                </form>
            </div>

            <hr>

            <div class="card p-4">
                <h4 class="section-title">Search & Manage Books</h4>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="Search by Title, ISBN, or Author..." id="searchQuery">
                    <button class="btn btn-outline-secondary" type="button" id="searchButton">Search</button>
                </div>
                <table class="table table-striped" id="booksTable">
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>ISBN</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Author Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </main>

        <footer>
            <p>&copy; 2025 LibGinie. All rights reserved.</p>
        </footer>
    </div>

    <div class="modal fade" id="editDeleteModal" tabindex="-1" aria-labelledby="editDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDeleteModalLabel">Edit Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editBookForm">
                        <div class="mb-3">
                            <label for="editBookISBN" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="editBookISBN" name="isbn" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAuthorName" class="form-label">Author Name</label>
                            <input type="text" class="form-control" id="editAuthorName" name="author_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editCategory" class="form-label">Category</label>
                            <select class="form-control" id="editCategory" name="category" required>
                                <option value="">Select a Category</option>
                                <option value="Text Books">Text Books</option>
                                <option value="Comics">Comics</option>
                                <option value="Novels">Novels</option>
                                <option value="Magazines">Magazines</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editSectionID" class="form-label">Section ID</label>
                            <input type="number" class="form-control" id="editSectionID" name="section_id">
                        </div>
                        <div class="mb-3">
                            <label for="editGenres" class="form-label">Genre</label>
                            <input type="text" class="form-control" id="editGenres" name="genres">
                        </div>
                        <div class="mb-3">
                            <label for="editPublisher" class="form-label">Publisher</label>
                            <input type="text" class="form-control" id="editPublisher" name="publisher">
                        </div>
                        <div class="mb-3">
                            <label for="editPublishedYear" class="form-label">Published Year</label>
                            <input type="number" class="form-control" id="editPublishedYear" name="published_year">
                        </div>
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                        </div>
                
                        <div id="editTextbookFields" style="display: none;">
                            <div class="mb-3">
                                <label for="editSubject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="editSubject" name="subject">
                            </div>
                            <div class="mb-3">
                                <label for="editEdition" class="form-label">Edition</label>
                                <input type="number" class="form-control" id="editEdition" name="edition">
                            </div>
                        </div>

                        <div id="editComicFields" style="display: none;">
                            <div class="mb-3">
                                <label for="editArtist" class="form-label">Artist</label>
                                <input type="text" class="form-control" id="editArtist" name="artist">
                            </div>
                            <div class="mb-3">
                                <label for="editStudio" class="form-label">Studio</label>
                                <input type="text" class="form-control" id="editStudio" name="studio">
                            </div>
                        </div>

                        <div id="editNovelFields" style="display: none;">
                            <div class="mb-3">
                                <label for="editNarrative" class="form-label">Narrative</label>
                                <input type="text" class="form-control" id="editNarrative" name="narrative">
                            </div>
                        </div>

                        <div id="editMagazinesFields" style="display: none;">
                            <div class="mb-3">
                                <label for="editTimeline" class="form-label">Timeline</label>
                                <input type="text" class="form-control" id="editTimeline" name="timeline">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editCopies" class="form-label">Number of Copies</label>
                            <input type="number" class="form-control" id="editCopies" name="editCopies" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="editCoverPicture" class="form-label">Book Cover Image</label>
                            <input class="form-control" type="file" id="editCoverPicture" name="cover_picture" accept="image/*">
                            <small class="form-text text-muted">Leave blank to keep the current image.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">Save Changes</button>
                    <button type="button" class="btn btn-danger" id="deleteBookBtn" data-bs-dismiss="modal">Delete Book</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const sidebar = document.getElementById('sidebar');
        const modalBodyEl = document.querySelector('#editDeleteModal .modal-body');
        const originalModalBodyHTML = modalBodyEl.innerHTML;

        function conditionalFields() {
            const BookCategoryDIV = document.getElementById('category');
            const EditBookCategoryDIV = document.getElementById('editCategory');
            
            const BookCategory = BookCategoryDIV?.value || '';
            const EditBookCategory = EditBookCategoryDIV?.value || '';

            const addTextbookDiv = document.getElementById('addTextbookFields');
            const addComicDiv = document.getElementById('addComicFields');
            const addNovelDiv = document.getElementById('addNovelFields');
            const addMagazinesDiv = document.getElementById('addMagazinesFields');

            const editTextbookDiv = document.getElementById('editTextbookFields');
            const editComicDiv = document.getElementById('editComicFields');
            const editNovelDiv = document.getElementById('editNovelFields');
            const editMagazinesDiv = document.getElementById('editMagazinesFields');

            function showFieldsFor(category, textbookDiv, comicDiv, novelDiv, magazinesDiv) {
                if (textbookDiv) textbookDiv.style.display = 'none';
                if (comicDiv) comicDiv.style.display = 'none';
                if (novelDiv) novelDiv.style.display = 'none';
                if (magazinesDiv) magazinesDiv.style.display = 'none';

                if (category === "Text Books") {
                    if (textbookDiv) textbookDiv.style.display = 'block';
                } else if (category === "Comics") {
                    if (comicDiv) comicDiv.style.display = 'block';
                } else if (category === "Novels") {
                    if (novelDiv) novelDiv.style.display = 'block';
                } else if (category === "Magazines") {
                    if (magazinesDiv) magazinesDiv.style.display = 'block';
                }
            }

            showFieldsFor(BookCategory, addTextbookDiv, addComicDiv, addNovelDiv, addMagazinesDiv);
            showFieldsFor(EditBookCategory, editTextbookDiv, editComicDiv, editNovelDiv, editMagazinesDiv);
        }

        const BookCategoryDIV = document.getElementById('category');
        const EditBookCategoryDIV = document.getElementById('editCategory');

        if (BookCategoryDIV) {
            BookCategoryDIV.addEventListener('change', conditionalFields);
        }
        if (EditBookCategoryDIV) {
            EditBookCategoryDIV.addEventListener('change', conditionalFields);
        }

        conditionalFields();

        function toggleSidebar() {
            sidebar.classList.toggle('closed');
            const contentWrapper = document.querySelector('.content-wrapper');
            contentWrapper.classList.toggle('content-wrapper-no-sidebar');
        }

        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('change', () => {
            document.body.classList.toggle('dark-theme');
        });

        function toggleRightSidebar() {
            const sidebar = document.getElementById('sidebar-right');
            const toggleBtn = document.querySelector('.sidebar-toggle-btn-right');

            // Toggle the 'active' class on both the sidebar and the button
            sidebar.classList.toggle('active');
            toggleBtn.classList.toggle('active');
        }
                
        function toggleSublist(id) {
            const header = document.querySelector(`[aria-controls="${id}"]`);
            const sublist = document.getElementById(id);
            const arrow = header.querySelector('.arrow');

            const isExpanded = header.getAttribute('aria-expanded') === 'true';
            header.setAttribute('aria-expanded', !isExpanded);
            arrow.textContent = isExpanded ? '>' : 'v';
            sublist.hidden = isExpanded;
            sublist.classList.toggle('show');
        }

        function toggleTaskBox() {
            const taskBox = document.getElementById('taskBox');
            taskBox.classList.toggle('collapsed');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const addBookForm = document.getElementById('addBookForm');
            const booksTableBody = document.querySelector('#booksTable tbody');
            const searchQueryInput = document.getElementById('searchQuery');
            const searchButton = document.getElementById('searchButton');
            const editModal = new bootstrap.Modal(document.getElementById('editDeleteModal'));
            const editBookForm = document.getElementById('editBookForm');
            const saveEditBtn = document.getElementById('saveEditBtn');
            const deleteBookBtn = document.getElementById('deleteBookBtn');

            async function fetchBooks(query = '') {
                const url = `BookMng.php?query=${encodeURIComponent(query)}`;
                try {
                    const response = await fetch(url);
                    const result = await response.json();

                    booksTableBody.innerHTML = '';
                    
                    if (result.success && result.books.length > 0) {
                        result.books.forEach(book => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td><img src="${book.book_cover || 'placeholder.png'}" alt="Cover" class="img-thumbnail" style="width: 50px;"></td>
                                <td>${book.isbn}</td>
                                <td>${book.title}</td>
                                <td>${book.category}</td>
                                <td>${book.author_name}</td>
                                <td>
                                    <button class="btn btn-sm btn-info edit-btn" data-isbn="${book.isbn}">Edit</button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-isbn="${book.isbn}">Delete</button>
                                </td>
                            `;
                            booksTableBody.appendChild(row);
                        });
                    } else {
                        booksTableBody.innerHTML = `<tr><td colspan="6" class="text-center">${result.message || 'No books found.'}</td></tr>`;
                    }
                } catch (error) {
                    console.error('Error fetching books:', error);
                    booksTableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Failed to load books.</td></tr>`;
                }
            }

            if (addBookForm) {
                addBookForm.addEventListener('submit', async function(event) {
                    event.preventDefault();
                    
                    const form = event.target;
                    const formData = new FormData(form);
                    const bookCover = formData.get('book_cover');
                    let coverUrl = '';

                    if (bookCover && bookCover.name) {
                        try {
                            const uploadFormData = new FormData();
                            uploadFormData.append('book_cover', bookCover);
                            const uploadResponse = await fetch('crud/upload_image.php', {
                                method: 'POST',
                                body: uploadFormData
                            });
                            const uploadResult = await uploadResponse.json();

                            if (uploadResult.success) {
                                coverUrl = uploadResult.url;
                            } else {
                                alert('Image upload failed: ' + uploadResult.message);
                                return;
                            }
                        } catch (error) {
                            alert('An error occurred during image upload.');
                            console.error(error);
                            return;
                        }
                    }

                    const bookData = {
                        action: 'add_book',
                        isbn: formData.get('isbn'),
                        title: formData.get('title'),
                        author_name: formData.get('author_name'),
                        category: formData.get('category'),
                        publisher: formData.get('publisher'),
                        published_year: formData.get('published_year'),
                        cover_picture: coverUrl,
                        section_id: formData.get('section_id'),
                        genres: formData.get('genres'),
                        description: formData.get('description'),
                        subject: formData.get('subject'),
                        edition: formData.get('edition'),
                        artist: formData.get('artist'),
                        studio: formData.get('studio'),
                        narrative: formData.get('narrative'),
                        timeline: formData.get('timeline')
                    };

                    try {
                        const response = await fetch('BookMng.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(bookData)
                        });
                        const result = await response.json();

                        if (result.success) {
                            const copiesResponse = await fetch(`crud/CopyCRUD.php?isbn=${formData.get('isbn')}&copies=${formData.get('copies')}`);
                            const copiesResult = await copiesResponse.json();

                            if (copiesResult.success) {
                                alert('Book and copies added successfully!');
                                form.reset();
                                conditionalFields();
                                fetchBooks();
                            } else {
                                alert('Book added successfully, but failed to create copies: ' + copiesResult.message);
                            }
                        } else {
                            alert('Failed to add book: ' + result.message);
                        }
                    } catch (error) {
                        alert('An error occurred while adding the book.');
                        console.error(error);
                    }
                });
            }

            if (searchButton) {
                searchButton.addEventListener('click', () => {
                    const query = searchQueryInput.value;
                    fetchBooks(query);
                });

                searchQueryInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        searchButton.click();
                    }
                });
            }

            if (booksTableBody) {
                booksTableBody.addEventListener('click', async (event) => {
                    const isbn = event.target.getAttribute('data-isbn');
                    if (!isbn) return;

                    if (event.target.classList.contains('edit-btn')) {
                        try {
                            const response = await fetch(`crud/EditOrDelete.php?isbn=${isbn}`);
                            const result = await response.json();

                            if (result.success) {
                                const book = result.book;

                                document.getElementById('editBookISBN').value = book.ISBN;
                                document.getElementById('editTitle').value = book.Title;
                                document.getElementById('editAuthorName').value = book.AuthorName;
                                document.getElementById('editCategory').value = book.Category || '';
                                document.getElementById('editSectionID').value = book.SectionID || '';
                                document.getElementById('editGenres').value = book.Genres || '';
                                document.getElementById('editPublisher').value = book.Publisher || '';
                                document.getElementById('editPublishedYear').value = book.PublishedYear || '';
                                document.getElementById('editDescription').value = book.Description || '';

                                // Fetch the number of copies for the book
                                const copiesResponse = await fetch(`crud/get_copies.php?isbn=${isbn}`);
                                const copiesResult = await copiesResponse.json();

                                if (copiesResult.success) {
                                    // Populate the 'copies' input field with the fetched number
                                    // Ensure your input field has the ID 'editCopies'
                                    document.getElementById('editCopies').value = copiesResult.copies;
                                } else {
                                    console.error('Error fetching copies:', copiesResult.message);
                                    document.getElementById('editCopies').value = 0; // Default to 0 on failure
                                }


                                const categoryResult = await fetch(`crud/categoryInfo.php?action=get_category_data&isbn=${isbn}&category=${book.Category}`);
                                const categoryData = await categoryResult.json();

                                if (categoryData.success && categoryData.data) {
                                    const data = categoryData.data;
                                    switch (book.Category) {
                                        case 'Text Books':
                                            document.getElementById('editSubject').value = data.Subject || '';
                                            document.getElementById('editEdition').value = data.Editions || '';
                                            break;
                                        case 'Comics':
                                            document.getElementById('editArtist').value = data.Artist || '';
                                            document.getElementById('editStudio').value = data.Studio || '';
                                            break;
                                        case 'Novels':
                                            document.getElementById('editNarrative').value = data.Narration || '';
                                            break;
                                        case 'Magazines':
                                            document.getElementById('editTimeline').value = data.Timeline || '';
                                            break;
                                    }
                                }

                                conditionalFields();
                                editModal.show();
                            } else {
                                alert('Error fetching book details: ' + result.message);
                            }
                        } catch (error) {
                            console.error('Error fetching book data:', error);
                            alert('Failed to fetch book data.');
                        }
                    } else if (event.target.classList.contains('delete-btn')) {
                        if (confirm('Are you sure you want to delete this book?')) {
                            try {
                                const response = await fetch('crud/EditOrDelete.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        action: 'delete_book',
                                        isbn
                                    })
                                });
                                const result = await response.json();

                                if (result.success) {
                                    alert('Book deleted successfully!');
                                    fetchBooks();
                                } else {
                                    alert('Deletion failed: ' + result.message);
                                }
                            } catch (error) {
                                console.error('Error deleting book:', error);
                                alert('An error occurred while deleting the book.');
                            }
                        }
                    }
                });
            }

if (saveEditBtn) {
    saveEditBtn.addEventListener('click', async () => {
        const form = document.getElementById('editBookForm');
        const formData = new FormData(form);
        const bookCoverFile = formData.get('cover_picture');
        let cover_picture = null;

        // Validate required fields
        const requiredFields = ['isbn', 'title', 'author_name', 'category', 'section_id', 'editCopies'];
        for (const field of requiredFields) {
            if (!formData.get(field)) {
                alert(`Please fill in the ${field.replace('editCopies', 'number of copies').replace('_', ' ')} field.`);
                return;
            }
        }

        // Validate ISBN format (basic check: non-empty string, at least 10 characters)
        const isbn = formData.get('isbn').trim();
        if (!isbn) { // Adjust length based on your ISBN format
            alert('Please provide a valid ISBN.');
            return;
        }

        // Validate copies (non-negative integer)
        const copies = parseInt(formData.get('editCopies'), 10);
        if (isNaN(copies) || copies < 0) {
            alert('Number of copies must be a non-negative integer.');
            return;
        }

        // Validate category-specific fields
        const category = formData.get('category');
        if (category === 'Text Books' && (!formData.get('subject') || !formData.get('edition'))) {
            alert('Subject and Edition are required for Text Books.');
            return;
        } else if (category === 'Comics' && (!formData.get('artist') || !formData.get('studio'))) {
            alert('Artist and Studio are required for Comics.');
            return;
        } else if (category === 'Novels' && !formData.get('narrative')) {
            alert('Narrative is required for Novels.');
            return;
        } else if (category === 'Magazines' && !formData.get('timeline')) {
            alert('Timeline is required for Magazines.');
            return;
        }

        // Log form data for debugging
        console.log('Form data:', Object.fromEntries(formData));

        // Fetch current number of copies
        let currentCopies = 0;
        try {
            const copiesResponse = await fetch(`crud/get_copies.php?isbn=${encodeURIComponent(isbn)}`);
            if (!copiesResponse.ok) {
                throw new Error(`Failed to fetch current copies: ${copiesResponse.status}`);
            }
            const copiesResult = await copiesResponse.json();
            if (copiesResult.success) {
                currentCopies = parseInt(copiesResult.copies, 10) || 0;
            } else {
                console.error('Error fetching current copies:', copiesResult.message);
                alert('Failed to fetch current copies: ' + copiesResult.message);
                return;
            }
        } catch (error) {
            console.error('Error fetching current copies:', error);
            alert('An error occurred while fetching current copies: ' + error.message);
            return;
        }

        // Log current and new copies for debugging
        console.log('Current copies:', currentCopies, 'New copies:', copies);

        // Handle image upload
        if (bookCoverFile && bookCoverFile.name) {
            try {
                const uploadFormData = new FormData();
                uploadFormData.append('book_cover', bookCoverFile);
                const uploadResponse = await fetch('crud/upload_image.php', {
                    method: 'POST',
                    body: uploadFormData
                });
                if (!uploadResponse.ok) {
                    throw new Error(`Image upload failed with status: ${uploadResponse.status}`);
                }
                const uploadResult = await uploadResponse.json();

                if (!uploadResult.success) {
                    console.error('Image upload failed:', uploadResult.message);
                    alert('Image upload failed: ' + uploadResult.message);
                    return;
                }
                cover_picture = uploadResult.url;
            } catch (error) {
                console.error('Image upload error:', error);
                alert('An error occurred during image upload: ' + error.message);
                return;
            }
        }

        // Prepare update data
        const updateData = {
            action: 'update_book',
            isbn: isbn,
            title: formData.get('title'),
            author_name: formData.get('author_name'),
            category: formData.get('category'),
            publisher: formData.get('publisher') || null,
            published_year: formData.get('published_year') || null,
            section_id: formData.get('section_id'),
            genres: formData.get('genres') || '',
            description: formData.get('description') || null,
            subject: formData.get('subject') || null,
            edition: formData.get('edition') || null,
            artist: formData.get('artist') || null,
            studio: formData.get('studio') || null,
            narrative: formData.get('narrative') || null,
            timeline: formData.get('timeline') || null
        };

        if (cover_picture) {
            updateData.cover_picture = cover_picture;
        }

        // Log update data for debugging
        console.log('Update data:', updateData);

        try {
            // Update book details
            const response = await fetch('crud/EditOrDelete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(updateData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // Only update copies if the number has changed
                if (copies !== currentCopies) {
                    console.log('Copy data:', { isbn, copies });
                    const copiesResponse = await fetch(`crud/CopyCRUD.php?isbn=${encodeURIComponent(isbn)}&copies=${copies}`);
                    
                    if (!copiesResponse.ok) {
                        throw new Error(`Copy update failed with status: ${copiesResponse.status}`);
                    }

                    const copiesResult = await copiesResponse.json();

                    if (copiesResult.success) {
                        alert('Book and copies updated successfully!');
                    } else {
                        console.error('Copy update failed:', copiesResult.message);
                        alert('Book updated, but failed to adjust copies: ' + copiesResult.message);
                        return;
                    }
                } else {
                    console.log('No change in copies, skipping CopyCRUD.php request.');
                    alert('Book updated successfully! No changes to copies.');
                }
                editModal.hide();
                fetchBooks();
            } else {
                console.error('Update failed:', result.message);
                alert('Update failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error updating book:', error);
            alert('An error occurred while updating the book: ' + error.message);
        }
    });
}
    
            fetchBooks();
        });
        

    </script>
</body>
</html>