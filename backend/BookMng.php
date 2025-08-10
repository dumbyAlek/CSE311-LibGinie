<?php
// Assume the user's role is stored in a session after login
session_start();

// Get user role from session, default to 'guest' if not set
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'guest';

// Set a flag for easier conditional checks
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// This part is for dynamic librarian tasks, included for consistency
if ($is_librarian) {
    $librarian_tasks = [
        'Shelve new arrivals in the Fantasy section.',
        'Inventory check of Fiction section.',
        'Assist with student library orientation.'
    ];
    $assigned_section = 'Fantasy';
}

require_once 'crud/db_config.php';

// === SEARCH BOOKS LOGIC ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    header('Content-Type: application/json');

    $search_query = '%' . $_GET['query'] . '%';

    try {
        $stmt = $con->prepare("SELECT b.ISBN, b.Title, b.CoverPicture, b.Category, m.Name AS AuthorName FROM Books b JOIN Author a ON b.AuthorID = a.AuthorID JOIN Members m ON a.UserID = m.UserID WHERE b.Title LIKE ? OR b.ISBN LIKE ? OR m.Name LIKE ?");
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
                'book_cover' => $row['CoverPicture']
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

        try {
            //Test Add Start
            // Validate or create section
                if (!empty($section_id) && is_numeric($section_id)) {
                    $stmt_check_section = $con->prepare("SELECT SectionID FROM Library_Sections WHERE SectionID = ?");
                    $stmt_check_section->bind_param("i", $section_id);
                    $stmt_check_section->execute();
                    $result_check_section = $stmt_check_section->get_result();

                    if ($result_check_section->num_rows === 0) {
                        // Instead of forcing a specific SectionID, let MySQL assign it if it's AUTO_INCREMENT
                        $new_section_name = "Section " . $section_id;
                        $stmt_create_section = $con->prepare("INSERT INTO Library_Sections (Name) VALUES (?)");
                        $stmt_create_section->bind_param("s", $new_section_name);
                        $stmt_create_section->execute();
                        $section_id = $stmt_create_section->insert_id; // Get the actual ID
                        $stmt_create_section->close();
                    }
                    $stmt_check_section->close();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid Section ID']);
                    exit;
                }

            //Test Add End
            
            // Step 1: Find the UserID from the Members table
            $user_id = null;
            $stmt = $con->prepare("SELECT UserID FROM Members WHERE Name = ?");
            $stmt->bind_param("s", $author_name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $user_id = $row['UserID'];
            } else {
                $stmt = $con->prepare("INSERT INTO Members (Name, Email, MembershipType) VALUES (?, ?, 'Registered')");
                $email = str_replace(' ', '.', strtolower($author_name)) . '@example.com';
                $stmt->bind_param("ss", $author_name, $email);
                $stmt->execute();
                $user_id = $stmt->insert_id;
            }
            $stmt->close();
            
            // Step 2: Find or create a Registered entry for the UserID
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

            // Step 3: Find or create the Author entry using the UserID
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
            
            // Step 4: Insert the new book using the AuthorID and SectionID
            if ($author_id) {
                $stmt = $con->prepare("INSERT INTO Books (ISBN, Title, AuthorID, SectionID, Category, Publisher, PublishedYear, CoverPicture) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiissis", $isbn, $title, $author_id, $section_id, $category, $publisher, $published_year, $cover_picture);

                if ($stmt->execute()) {
                    // New: Insert into the correct specialized table based on category
                    if ($category === 'Text Books') {
                        $stmt_insert = $con->prepare("INSERT INTO TextBook (ISBN) VALUES (?)");
                        $stmt_insert->bind_param("s", $isbn);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    } elseif ($category === 'Comics') {
                        $stmt_insert = $con->prepare("INSERT INTO Comics (ISBN) VALUES (?)");
                        $stmt_insert->bind_param("s", $isbn);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    } elseif ($category === 'Novels') {
                        $stmt_insert = $con->prepare("INSERT INTO Novels (ISBN) VALUES (?)");
                        $stmt_insert->bind_param("s", $isbn);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    } elseif ($category === 'Magazines') {
                        $stmt_insert = $con->prepare("INSERT INTO Magazines (ISBN) VALUES (?)");
                        $stmt_insert->bind_param("s", $isbn);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    }
                    
                    // Handle genres
                    foreach ($genres as $genre_name) {
                        $genre_name = trim($genre_name);
                        if (!empty($genre_name)) {
                            // Check if genre exists
                            $stmt_genre = $con->prepare("SELECT GenreID FROM Genres WHERE GenreName = ?");
                            $stmt_genre->bind_param("s", $genre_name);
                            $stmt_genre->execute();
                            $result_genre = $stmt_genre->get_result();
                            
                            $genre_id = null;
                            if ($row_genre = $result_genre->fetch_assoc()) {
                                $genre_id = $row_genre['GenreID'];
                            } else {
                                // If not, create it
                                $stmt_new_genre = $con->prepare("INSERT INTO Genres (GenreName) VALUES (?)");
                                $stmt_new_genre->bind_param("s", $genre_name);
                                $stmt_new_genre->execute();
                                $genre_id = $stmt_new_genre->insert_id;
                                $stmt_new_genre->close();
                            }
                            $stmt_genre->close();
                            
                            // Insert into Book_Genres
                            if ($genre_id) {
                                $stmt_book_genre = $con->prepare("INSERT INTO Book_Genres (ISBN, GenreID) VALUES (?, ?)");
                                $stmt_book_genre->bind_param("si", $isbn, $genre_id);
                                $stmt_book_genre->execute();
                                $stmt_book_genre->close();
                            }
                        }
                    }

                    // Add entry to BooksAdded table
                    $admin_user_id = $_SESSION['UserID'] ?? null; // Assumes admin UserID is stored in session
                    if ($admin_user_id) {
                        $stmt_books_added = $con->prepare("INSERT INTO BooksAdded (ISBN, UserID, AddDate) VALUES (?, ?, NOW())");
                        $stmt_books_added->bind_param("si", $isbn, $admin_user_id);
                        $stmt_books_added->execute();
                        $stmt_books_added->close();
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Book added successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add book.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to get or create Author ID.']);
            }
        } catch (Exception $e) {
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
        }

        body {
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            transition: background-color 0.3s, color 0.3s;
            background-color: #eed9c4;
        }

        body.dark-theme {
            background-color: #121212;
            color: #eee;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            width: var(--sidebar-width);
            height: 100vh;
            background-image: url('../../images/sidebar.jpg');
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
            content: "☰";
            color: white;
            font-size: 20px;
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
        
        /* Specific styles for this page */
        .container { max-width: 900px; margin-top: 50px; }
        .card { margin-bottom: 20px; }
        .table img { max-width: 50px; height: auto; }
    </style>
</head>

<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

    <?php if (!$is_guest) : // Main sidebar for all logged-in users ?>
    <nav class="sidebar closed" id="sidebar">
        <a href="../pages/home.php"><img src="../images/logo3.png" alt="Logo" class="logo" /></a>
        <ul>
            <li><a href="../../pages/dashboard.php">Dashboard</a></li>
            <li><a href="#">My Books</a></li>
            <li><a href="#">Favorites</a></li>

            <?php if ($user_role === 'admin') : ?>
            <li><a href="BookMng.php">Book Management</a></li>
            <li><a href="../backend/MemMng.php">Member Management</a></li>
            <li><a href="employee_management.html">Employee Management</a></li>
            <?php elseif ($is_librarian) : ?>
            <li><a href="member_management.html">Member Management</a></li>
            <li><a href="#">Request Book</a></li>
            <?php elseif (in_array($user_role, ['author', 'student', 'teacher', 'general'])) : ?>
            <li><a href="#">Request Book</a></li>
            <li><a href="#">Borrowed Books</a></li>
            <?php endif; ?>

            <?php if ($user_role === 'author') : ?>
            <li><a href="author_account.html">My Account</a></li>
            <?php endif; ?>
            
            <li class="collapsible-header" onclick="toggleSublist('categoryList')" aria-expanded="false" aria-controls="categoryList">
                <span class="arrow">></span> Categories
            </li>
            <ul class="sublist" id="categoryList" hidden>
                <li><a href="../pages/categories.php?category=Text Books">Text Books</a></li>
                <li><a href="../pages/categories.php?category=Comics">Comics</a></li>
                <li><a href="../pages/categories.php?category=Novels">Novels</a></li>
                <li><a href="../pages/categories.php?category=Magazines">Magazines</a></li>
            </ul>

            <li class="collapsible-header" onclick="toggleSublist('genreList')" aria-expanded="false" aria-controls="genreList">
                <span class="arrow">></span> Genres
            </li>
            <ul class="sublist" id="genreList" hidden>
                <li><a href="#">Fantasy</a></li>
                <li><a href="#">Horror</a></li>
                <li><a href="#">Romance</a></li>
                <li><a href="#">[Browse All Genres]</a></li>
            </ul>
            
            <li><a href="#">Reserved</a></li>
            <li><a href="../../pages/settings.php">Settings</a></li>
            <li><a href="../logout.php">Logout</a></li>
        </ul>
    </nav>
    <?php else: // Sidebar for Guest users only ?>
    <nav class="sidebar closed" id="sidebar">
        <img src="../../images/logo3.png" alt="Logo" class="logo" />
        <ul>
            <li><a href="signup.php">Sign Up</a></li>
            <li><a href="#" class="disabled-link">Reserved</a></li>
            <li><a href="#">Settings</a></li>
            <li><a href="login.php">Log In</a></li>
        </ul>
    </nav>
    <?php endif; ?>

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
                    <div class="form-group">
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
                        <input type="hidden" id="editBookISBN" name="isbn">
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
                            <input type="number" class="form-control" id="editSectionID" name="section_id" required>
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

        function toggleSidebar() {
            sidebar.classList.toggle('closed');
        }

        // Theme toggle logic
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('change', () => {
            document.body.classList.toggle('dark-theme');
        });
        
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
        
            // Function to fetch and render books
            async function fetchBooks(query = '') {
                const url = `BookMng.php?query=${encodeURIComponent(query)}`;
                try {
                    const response = await fetch(url);
                    const result = await response.json();
                    
                    booksTableBody.innerHTML = ''; // Clear table
                    
                    if (result.success && result.books.length > 0) {
                        result.books.forEach(book => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td><img src="${book.book_cover || 'placeholder.png'}" alt="Cover" class="img-thumbnail"></td>
                                <td>${book.isbn}</td>
                                <td>${book.title}</td>
                                <td>${book.category}</td>
                                <td>${book.author_name}</td>
                                <td>
                                    <button class="btn btn-sm btn-info edit-btn" data-isbn="${book.isbn}" data-bs-toggle="modal" data-bs-target="#editDeleteModal">Edit</button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-isbn="${book.isbn}" data-bs-toggle="modal" data-bs-target="#editDeleteModal">Delete</button>
                                </td>
                            `;
                            booksTableBody.appendChild(row);
                        });
                    } else {
                        booksTableBody.innerHTML = `<tr><td colspan="5" class="text-center">${result.message || 'No books found.'}</td></tr>`;
                    }
                } catch (error) {
                    console.error('Error fetching books:', error);
                    booksTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Failed to load books.</td></tr>`;
                }
            }
        
            // Handle form submission for adding a new book
            if (addBookForm) {
                addBookForm.addEventListener('submit', async function(event) {
                    event.preventDefault();
                    
                    const form = event.target;
                    const formData = new FormData(form);
                    const bookCover = formData.get('book_cover');
                    let coverUrl = '';

                    // Handle image upload first
                    if (bookCover && bookCover.name) {
                        try {
                            const uploadFormData = new FormData();
                            uploadFormData.append('book_cover', bookCover);
                            // Corrected fetch path for the separate image upload script
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

                    // Now submit the book data (including the image URL)
                    try {
                        const response = await fetch('BookMng.php', { 
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'add_book',
                                isbn: formData.get('isbn'),
                                title: formData.get('title'),
                                author_name: formData.get('author_name'),
                                category: formData.get('category'),
                                publisher: formData.get('publisher'),
                                published_year: formData.get('published_year'),
                                cover_picture: coverUrl,
                                section_id: formData.get('section_id'), // New
                                genres: formData.get('genres') // New
                            })
                        });
                        const result = await response.json();

                        if (result.success) {
                            alert('Book added successfully!');
                            form.reset();
                            fetchBooks(); // Refresh the book list
                        } else {
                            alert('Failed to add book: ' + result.message);
                        }
                    } catch (error) {
                        alert('An error occurred while adding the book.');
                        console.error(error);
                    }
                });
            }
        
            // Handle search functionality
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
        
            // Handle edit and delete buttons using event delegation
            if (booksTableBody) {
                booksTableBody.addEventListener('click', async (event) => {
                    const isbn = event.target.getAttribute('data-isbn');
                    if (!isbn) return;

                    if (event.target.classList.contains('edit-btn')) {
                        const modalTitle = document.getElementById('editDeleteModalLabel');
                        const saveBtn = document.getElementById('saveEditBtn');
                        const deleteBtn = document.getElementById('deleteBookBtn');

                        modalTitle.textContent = 'Edit Book';
                        saveBtn.style.display = 'block';
                        deleteBtn.style.display = 'none';

                        try {
                            const response = await fetch(`crud/EditOrDelete.php?isbn=${isbn}`);
                            const result = await response.json();

                            if (result.success) {
                                const book = result.book;
                                document.getElementById('editBookISBN').value = book.ISBN;
                                document.getElementById('editTitle').value = book.Title;
                                document.getElementById('editAuthorName').value = book.AuthorName;
                                document.getElementById('editCategory').value = book.Category || '';
                                document.getElementById('editPublisher').value = book.Publisher || '';
                                document.getElementById('editPublishedYear').value = book.PublishedYear || '';
                                document.getElementById('editCoverPicture').value = book.CoverPicture || '';
                            } else {
                                alert('Error fetching book details: ' + result.message);
                                editModal.hide();
                            }
                        } catch (error) {
                            console.error('Error fetching book data:', error);
                            // alert('Failed to fetch book data.');
                            editModal.hide();
                        }
                    } else if (event.target.classList.contains('delete-btn')) {
                        const modalTitle = document.getElementById('editDeleteModalLabel');
                        const modalBody = document.querySelector('#editDeleteModal .modal-body');
                        const saveBtn = document.getElementById('saveEditBtn');
                        const deleteBtn = document.getElementById('deleteBookBtn');

                        modalTitle.textContent = 'Delete Book';
                        modalBody.innerHTML = `<p>Are you sure you want to delete the book with ISBN: <strong>${isbn}</strong>?</p>`;
                        saveBtn.style.display = 'none';
                        deleteBtn.style.display = 'block';
                        deleteBtn.setAttribute('data-isbn', isbn);
                    }
                });
            }

            // Event listener for the "Save Changes" button in the modal
if (saveEditBtn) {
    saveEditBtn.addEventListener('click', async () => {
        const isbn = document.getElementById('editBookISBN').value;
        const title = document.getElementById('editTitle').value;
        const author_name = document.getElementById('editAuthorName').value;
        const category = document.getElementById('editCategory').value;
        const publisher = document.getElementById('editPublisher').value;
        const published_year = document.getElementById('editPublishedYear').value;
        const section_id = document.getElementById('editSectionID').value; // New
        const genres = document.getElementById('editGenres').value; // New
        const bookCoverFile = document.getElementById('editCoverPicture').files[0];
        let cover_picture = null;
        // Check if a new image file has been selected
        if (bookCoverFile) {
            const uploadFormData = new FormData();
            uploadFormData.append('book_cover', bookCoverFile);

            try {
                // Upload the new image
                const uploadResponse = await fetch('crud/upload_image.php', {
                    method: 'POST',
                    body: uploadFormData
                });
                const uploadResult = await uploadResponse.json();

                if (uploadResult.success) {
                    cover_picture = uploadResult.url;
                } else {
                    alert('Image upload failed: ' + uploadResult.message);
                    return; // Stop the process if the upload fails
                }
            } catch (error) {
                alert('An error occurred during image upload.');
                console.error(error);
                return; // Stop the process on error
            }
        }
        
        // Prepare the data to update the book, using the new URL or the existing one
        const updateData = {
        action: 'update_book',
        isbn,
        title,
        author_name,
        category,
        publisher,
        published_year,
        section_id, // New
        genres // New
        };
        
        // Only add the cover_picture key if a new one was uploaded
        if (cover_picture !== null) {
            updateData.cover_picture = cover_picture;
        }

        try {
            const response = await fetch('crud/EditOrDelete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(updateData)
            });
            const result = await response.json();

            if (result.success) {
                alert('Book updated successfully!');
                editModal.hide();
                fetchBooks(); // Refresh the list
            } else {
                alert('Update failed: ' + result.message);
            }
        } catch (error) {
            //console.error('Error updating book:', error);
            alert('An error occurred while updating the book.');
        }
    });
}
            
            // Event listener for the "Delete Book" button in the modal
            if (deleteBookBtn) {
                deleteBookBtn.addEventListener('click', async () => {
                    const isbn = deleteBookBtn.getAttribute('data-isbn');

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
                            editModal.hide();
                            fetchBooks(); // Refresh the list
                        } else {
                            alert('Deletion failed: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Error deleting book:', error);
                        alert('An error occurred while deleting the book.');
                    }
                });
            }

            // Initial fetch of books when the page loads
            fetchBooks();
        });
    </script>
</body>
</html>