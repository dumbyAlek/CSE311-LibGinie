<?php
// Start output buffering
ob_start();
session_start();

// Check admin access
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['membershipType'] !== 'admin') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit;
    } else {
        ob_end_clean();
        header("Location: ../pages/loginn.php");
        exit;
    }
}

require_once '../backend/crud/db_config.php';
require_once '../backend/crud/log_action.php';

if ($con->connect_error) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $con->connect_error]);
    exit;
}

$user_role = $_SESSION['membershipType'];

// --- Helper functions ---
function getBookRequests($con) {
    $sql = "SELECT br.ReqID, br.BookName, br.Author, m.Name AS UserName
            FROM BookRequests br
            JOIN Members m ON br.UserID = m.UserID
            WHERE br.Status is NULL
            ORDER BY br.ReqID DESC";
    $res = $con->query($sql);
    $requests = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $requests[] = $row;
        }
    }
    return $requests;
}

function getRequestHistory($con) {
    $sql = "SELECT 
                br.ReqID, 
                br.BookName, 
                br.Author, 
                m.Name AS UserName,
                br.Status,
                b.CoverPicture
            FROM BookRequests br
            JOIN Members m ON br.UserID = m.UserID
            LEFT JOIN Books b ON br.BookName = b.Title
            LEFT JOIN Author a ON b.AuthorID = a.AuthorID
            LEFT JOIN Members am ON a.UserID = am.UserID AND br.Author = am.Name
            ORDER BY br.ReqID DESC";
    
    $res = $con->query($sql);
    $history = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $history[] = $row;
        }
    }
    return $history;
}

function getAuthors($con) {
    $sql = "SELECT a.AuthorID, m.Name 
            FROM Author a 
            JOIN Members m ON a.UserID = m.UserID";
    $res = $con->query($sql);
    $authors = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $authors[] = $row;
        }
    }
    return $authors;
}

function getSections($con) {
    $sql = "SELECT SectionID, Name FROM Library_Sections";
    $res = $con->query($sql);
    $sections = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sections[] = $row;
        }
    }
    return $sections;
}

function getGenres($con) {
    $sql = "SELECT GenreID, GenreName FROM Genres";
    $res = $con->query($sql);
    $genres = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $genres[] = $row;
        }
    }
    return $genres;
}

// --- Handle POST requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['action'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // --- Decline request ---
    if ($data['action'] === 'decline_request') {
        $req_id = filter_var($data['req_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$req_id) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid Request ID']);
            exit;
        }
        try {
            $stmt = $con->prepare("UPDATE BookRequests SET Status = 'Declined' WHERE ReqID = ?");
            $stmt->bind_param("i", $req_id);
            $stmt->execute();
            $stmt->close();
            $con->close();
            ob_end_clean();
            // Log the action
            log_action($_SESSION['UserID'], 'Book Request', 'User ' . $_SESSION['user_name'] . ' book request declined.');
            echo json_encode(['success' => true, 'message' => 'Request declined successfully']);
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }

    // --- Add book ---
    if ($data['action'] === 'add_book') {
        $isbn = trim($data['isbn'] ?? '');
        $copies = filter_var($data['copies'] ?? 0, FILTER_VALIDATE_INT);
        $title = trim($data['title'] ?? '');
        $author_name = trim($data['author_name'] ?? '');
        $category = $data['category'] ?? '';
        $publisher = $data['publisher'] ?? null;
        $published_year = filter_var($data['published_year'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $section_id = filter_var($data['section_id'] ?? null, FILTER_VALIDATE_INT);
        $genres = isset($data['genres']) ? array_map('htmlspecialchars', array_map('trim', explode(',', $data['genres']))) : [];
        $description = htmlspecialchars(trim($data['description'] ?? '')) ?: null;
        $req_id = filter_var($data['req_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $subject = $data['subject'] ?? null;
        $edition = filter_var($data['edition'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $artist = $data['artist'] ?? null;
        $studio = $data['studio'] ?? null;
        $narrative = $data['narrative'] ?? null;
        $timeline = $data['timeline'] ?? null;
        $cover_picture = trim($data['cover_picture'] ?? '') ?: null;
        $author_title = trim($data['author_title'] ?? '') ?: null;
        $author_bio = trim($data['author_bio'] ?? '') ?: null;

        // Validate lengths
        if (strlen($title) > 200) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Book title must not exceed 200 characters']);
            exit;
        }
        if (strlen($author_name) > 100) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Author name must not exceed 100 characters']);
            exit;
        }

        // Server-side validation for required fields
        if (empty($isbn) || $copies < 0 || empty($title) || empty($author_name) || empty($category) || !$section_id) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Validate category-specific fields
        $requiredFields = [
            'Text Books' => ['subject', 'edition'],
            'Comics' => ['artist', 'studio'],
            'Novels' => ['narrative'],
            'Magazines' => ['timeline']
        ];
        if (isset($requiredFields[$category])) {
            foreach ($requiredFields[$category] as $field) {
                if (empty($data[$field])) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => "Field '$field' is required for $category"]);
                    exit;
                }
            }
        }

        try {
            $con->begin_transaction();

            // --- Check if ISBN already exists ---
            $stmt = $con->prepare("SELECT ISBN FROM Books WHERE ISBN = ?");
            $stmt->bind_param("s", $isbn);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                throw new Exception('ISBN already exists');
            }
            $stmt->close();

            // --- Section ---
            $stmt = $con->prepare("SELECT SectionID FROM Library_Sections WHERE SectionID = ?");
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                $stmt_insert = $con->prepare("INSERT INTO Library_Sections (Name) VALUES (?)");
                $new_section_name = "Section $section_id";
                $stmt_insert->bind_param("s", $new_section_name);
                $stmt_insert->execute();
                $section_id = $stmt_insert->insert_id;
                $stmt_insert->close();
            }
            $stmt->close();

            // --- Author / Member ---
            $user_id = null;
            $stmt = $con->prepare("SELECT UserID FROM Members WHERE Name = ?");
            $stmt->bind_param("s", $author_name);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $user_id = $row['UserID'];
            } else {
                $email = str_replace(' ', '.', strtolower($author_name)) . '@example.com';
                $stmt2 = $con->prepare("INSERT INTO Members (Name, Email, MembershipType) VALUES (?, ?, 'Registered')");
                $stmt2->bind_param("ss", $author_name, $email);
                $stmt2->execute();
                $user_id = $stmt2->insert_id;
                $stmt2->close();
            }
            $stmt->close();

            // --- Registered entry ---
            $stmt = $con->prepare("SELECT UserID FROM Registered WHERE UserID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                $stmt2 = $con->prepare("INSERT INTO Registered (UserID, RegistrationDate) VALUES (?, NOW())");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $stmt2->close();
            }
            $stmt->close();


            // --- Author table ---
            $author_id = null;
            $stmt = $con->prepare("SELECT AuthorID FROM Author WHERE UserID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $author_id = $row['AuthorID'];
            } else {
                $stmt2 = $con->prepare("INSERT INTO Author (UserID) VALUES (?)");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $author_id = $stmt2->insert_id;
                $stmt2->close();
            }
            $stmt->close();

            // --- Books table ---
            $stmt = $con->prepare("INSERT INTO Books (ISBN, Title, AuthorID, SectionID, Category, Publisher, PublishedYear, Description, CoverPicture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiisssss", $isbn, $title, $author_id, $section_id, $category, $publisher, $published_year, $description, $cover_picture);
            if (!$stmt->execute()) {
                throw new Exception('Failed to insert book');
            }
            $stmt->close();

            // --- Category-specific ---
            switch ($category) {
                case 'Text Books':
                    $stmt = $con->prepare("INSERT INTO TextBook (ISBN, Subject, Editions) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $isbn, $subject, $edition);
                    $stmt->execute();
                    $stmt->close();
                    break;
                case 'Comics':
                    $stmt = $con->prepare("INSERT INTO Comics (ISBN, Artist, Studio) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $isbn, $artist, $studio);
                    $stmt->execute();
                    $stmt->close();
                    break;
                case 'Novels':
                    $stmt = $con->prepare("INSERT INTO Novels (ISBN, Narration) VALUES (?, ?)");
                    $stmt->bind_param("ss", $isbn, $narrative);
                    $stmt->execute();
                    $stmt->close();
                    break;
                case 'Magazines':
                    $stmt = $con->prepare("INSERT INTO Magazines (ISBN, Timeline) VALUES (?, ?)");
                    $stmt->bind_param("ss", $isbn, $timeline);
                    $stmt->execute();
                    $stmt->close();
                    break;
            }

            // --- Genres ---
            foreach ($genres as $genre_name) {
                $genre_name = trim($genre_name);
                if (!$genre_name) continue;
                $stmt = $con->prepare("SELECT GenreID FROM Genres WHERE GenreName = ?");
                $stmt->bind_param("s", $genre_name);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $genre_id = $row['GenreID'];
                } else {
                    $stmt2 = $con->prepare("INSERT INTO Genres (GenreName) VALUES (?)");
                    $stmt2->bind_param("s", $genre_name);
                    $stmt2->execute();
                    $genre_id = $stmt2->insert_id;
                    $stmt2->close();
                }
                $stmt->close();

                $stmt = $con->prepare("INSERT INTO Book_Genres (ISBN, GenreID) VALUES (?, ?)");
                $stmt->bind_param("si", $isbn, $genre_id);
                $stmt->execute();
                $stmt->close();
            }

            // --- BooksAdded ---
            $admin_user_id = $_SESSION['UserID'] ?? null;
            if ($admin_user_id) {
                $stmt = $con->prepare("SELECT UserID FROM Admin WHERE UserID = ?");
                $stmt->bind_param("i", $admin_user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) {
                    $con->rollback();
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'User is not an admin']);
                    exit;
                }
                $stmt->close();

                $stmt = $con->prepare("INSERT INTO BooksAdded (ISBN, UserID, AddDate) VALUES (?, ?, NOW())");
                $stmt->bind_param("si", $isbn, $admin_user_id);
                $stmt->execute();
                $stmt->close();
            }

            // --- Update BookRequests if exists ---
            if ($req_id) {
                $stmt = $con->prepare("UPDATE BookRequests SET Status = 'Added' WHERE ReqID = ?");
                $stmt->bind_param("i", $req_id);
                $stmt->execute();
                $stmt->close();
            }

            // --- Add Book Copies ---
            if ($copies > 0) {
                $stmt = $con->prepare("INSERT INTO BookCopy (ISBN, Status) VALUES (?, 'Available')");
                $stmt->bind_param("s", $isbn);
                for ($i = 0; $i < $copies; $i++) {
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to add book copy');
                    }
                }
                $stmt->close();
            }

            $con->commit();
            ob_end_clean();
            // Log the action
            $_SESSION['UserID'] = $user_id;
            $_SESSION['user_name'] = $uname;
            log_action($user_id, 'Book Request', 'User ' . $uname . ' book added from requests.');
            echo json_encode(['success' => true, 'message' => 'Book added successfully']);
            exit;
        } catch (Exception $e) {
            $con->rollback();
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }

    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// --- Fetch page data ---
$requests = getBookRequests($con);
$history = getRequestHistory($con);
$authors = getAuthors($con);
$sections = getSections($con);
$genres = getGenres($con);
$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibGinie - Manage Book Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #eed9c4;
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        :root {
            --sidebar-width: 400px;
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

        .request-container, .history-container {
            margin-top: 50px;
            padding: 20px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .request-container.dark-theme, .history-container.dark-theme {
            background-color: #222;
            color: #eee;
        }

        h1, h2 {
            font-family: 'Montserrat', sans-serif;
            color: #7b3fbf;
        }

        .table {
            background-color: #f8f9fa;
        }

        .table.dark-theme {
            background-color: #333;
            color: #eee;
        }

        .modal-content {
            background-color: #fff;
        }

        .modal-content.dark-theme {
            background-color: #222;
            color: #eee;
        }

        .form-label {
            color: #7b3fbf;
        }

        .conditional-fields {
            display: none;
        }

        .alert-dismissible {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            max-width: 400px;
        }

        body.dark-theme .form-control {
            background-color: #333;
            color: #eee;
            border-color: #555;
        }
        body.dark-theme .form-control:focus {
            background-color: #444;
            color: #eee;
            border-color: #7b3fbf;
        }
        body.dark-theme .btn-primary {
            background-color: #7b3fbf;
            border-color: #7b3fbf;
        }
        body.dark-theme .btn-primary:hover {
            background-color: #6a32a3;
            border-color: #6a32a3;
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>
    <?php 
    // Check if sidebar.php exists before including
    if (file_exists('sidebar.php')) {
        include 'sidebar.php';
    } else {
        echo '<div class="sidebar" id="sidebar"><p>Sidebar not found. Please check sidebar.php.</p></div>';
    }
    ?>

    <div class="content-wrapper content-wrapper-no-sidebar">
        <main class="container mt-4">
            <div id="message" class="mt-3"></div>

            <div class="request-container">
                <h2>Book Requests</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Book Name</th>
                            <th>Author</th>
                            <th>Requested By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <?php foreach ($requests as $request): ?>
                            <tr data-req-id="<?php echo htmlspecialchars($request['ReqID']); ?>">
                                <td><?php echo htmlspecialchars($request['ReqID']); ?></td>
                                <td><?php echo htmlspecialchars($request['BookName']); ?></td>
                                <td><?php echo htmlspecialchars($request['Author'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['UserName']); ?></td>
                                <td>
                                    <button class="btn btn-success btn-sm add-book-btn" 
                                            data-req-id="<?php echo htmlspecialchars($request['ReqID']); ?>" 
                                            data-book-name="<?php echo htmlspecialchars($request['BookName']); ?>" 
                                            data-author="<?php echo htmlspecialchars($request['Author'] ?? ''); ?>">
                                        Add Book
                                    </button>
                                    <button class="btn btn-danger btn-sm decline-request-btn" 
                                            data-req-id="<?php echo htmlspecialchars($request['ReqID']); ?>">
                                        Decline Request
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="history-container mt-5">
    <h2>Request History</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Book Name</th>
                <th>Author</th>
                <th>Requested By</th>
                <th>Status</th>
                <th>Cover</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $entry): ?>
                <tr>
                    <td><?php echo htmlspecialchars($entry['ReqID']); ?></td>
                    <td><?php echo htmlspecialchars($entry['BookName']); ?></td>
                    <td><?php echo htmlspecialchars($entry['Author'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($entry['UserName'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($entry['Status']); ?></td>
                    <td>
                        <?php if ($entry['Status'] === 'Added' && !empty($entry['CoverPicture'])): ?>
                            <img src="<?php echo htmlspecialchars($entry['CoverPicture']); ?>" alt="Cover" style="max-width: 50px; height: auto;">
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

            <div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addBookModalLabel">Add Book</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addBookForm">
                                <input type="hidden" name="req_id" id="reqId">
                                <div class="mb-3">
                                    <label for="isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" required>
                                </div>
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" maxlength="200" required>
                                </div>
                                <div class="mb-3">
                                    <label for="author_name" class="form-label">Author Name</label>
                                    <input type="text" class="form-control" id="author_name" name="author_name" maxlength="100" required>
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-control" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Text Books">Text Books</option>
                                        <option value="Comics">Comics</option>
                                        <option value="Novels">Novels</option>
                                        <option value="Magazines">Magazines</option>
                                    </select>
                                </div>
                                <div id="textBookFields" class="conditional-fields">
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="subject" name="subject">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edition" class="form-label">Edition</label>
                                        <input type="number" class="form-control" id="edition" name="edition" min="1" step="1">
                                    </div>
                                </div>
                                <div id="comicsFields" class="conditional-fields">
                                    <div class="mb-3">
                                        <label for="artist" class="form-label">Artist</label>
                                        <input type="text" class="form-control" id="artist" name="artist">
                                    </div>
                                    <div class="mb-3">
                                        <label for="studio" class="form-label">Studio</label>
                                        <input type="text" class="form-control" id="studio" name="studio">
                                    </div>
                                </div>
                                <div id="novelsFields" class="conditional-fields">
                                    <div class="mb-3">
                                        <label for="narrative" class="form-label">Narrative</label>
                                        <input type="text" class="form-control" id="narrative" name="narrative">
                                    </div>
                                </div>
                                <div id="magazinesFields" class="conditional-fields">
                                    <div class="mb-3">
                                        <label for="timeline" class="form-label">Timeline</label>
                                        <input type="text" class="form-control" id="timeline" name="timeline">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="section_id" class="form-label">Section</label>
                                    <select class="form-control" id="section_id" name="section_id" required>
                                        <option value="">Select Section</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo htmlspecialchars($section['SectionID']); ?>">
                                                <?php echo htmlspecialchars($section['Name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="genres" class="form-label">Genres (comma-separated)</label>
                                    <input type="text" class="form-control" id="genres" name="genres">
                                </div>
                                <div class="mb-3">
                                    <label for="publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="publisher" name="publisher">
                                </div>
                                <div class="mb-3">
                                    <label for="published_year" class="form-label">Published Year</label>
                                    <input type="number" class="form-control" id="published_year" name="published_year" min="1000" max="<?php echo date('Y'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="cover_picture" class="form-label">Cover Picture</label>
                                    <input type="file" class="form-control" id="cover_picture" name="cover_picture" accept="image/*">
                                </div>
                                <div class="mb-3">
                                    <label for="copies" class="form-label">Number of Copies</label>
                                    <input type="number" class="form-control" id="copies" name="copies" min="0" step="1" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Add Book</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        function toggleSidebar() {
            sidebar.classList.toggle('closed');
            const contentWrapper = document.querySelector('.content-wrapper');
            contentWrapper.classList.toggle('content-wrapper-no-sidebar');
        }

        function toggleSublist(id) {
            const sublist = document.getElementById(id);
            if (sublist) {
                const isShown = sublist.classList.toggle('show');
                const arrow = document.querySelector(`[aria-controls="${id}"] .arrow`);
                if (arrow) {
                    arrow.textContent = isShown ? 'v' : '>';
                }
            }
        }

        document.querySelectorAll('.collapsible-header').forEach(header => {
            header.addEventListener('click', function() {
                toggleSublist(this.getAttribute('aria-controls'));
            });
        });

        function conditionalFields() {
            const category = document.getElementById('category').value;
            const fields = {
                'Text Books': ['subject', 'edition'],
                'Comics': ['artist', 'studio'],
                'Novels': ['narrative'],
                'Magazines': ['timeline']
            };

            // Reset all conditional fields
            ['textBookFields', 'comicsFields', 'novelsFields', 'magazinesFields'].forEach(field => {
                document.getElementById(field).style.display = 'none';
                document.querySelectorAll(`#${field} input`).forEach(input => {
                    input.removeAttribute('required');
                });
            });

            // Show and set required for selected category
            if (category && fields[category]) {
                const fieldGroup = {
                    'Text Books': 'textBookFields',
                    'Comics': 'comicsFields',
                    'Novels': 'novelsFields',
                    'Magazines': 'magazinesFields'
                }[category];
                document.getElementById(fieldGroup).style.display = 'block';
                fields[category].forEach(field => {
                    document.getElementById(field).setAttribute('required', 'required');
                });
            }
        }

        document.getElementById('category').addEventListener('change', conditionalFields);

        const addBookModal = new bootstrap.Modal(document.getElementById('addBookModal'));

        document.querySelectorAll('.add-book-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reqId = this.dataset.reqId;
                const bookName = this.dataset.bookName;
                const author = this.dataset.author;

                document.getElementById('reqId').value = reqId || '';
                document.getElementById('title').value = bookName || '';
                document.getElementById('author_name').value = author || '';
                conditionalFields();
                addBookModal.show();
            });
        });

        document.querySelectorAll('.decline-request-btn').forEach(button => {
            button.addEventListener('click', async function() {
                const reqId = this.dataset.reqId;
                if (!confirm('Are you sure you want to decline this request?')) return;

                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ action: 'decline_request', req_id: reqId })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }

                    const data = await response.json();
                    const messageDiv = document.getElementById('message');
                    if (data.success) {
                        messageDiv.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                            ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>`;
                        document.querySelector(`tr[data-req-id="${reqId}"]`).remove();
                    } else {
                        messageDiv.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>`;
                    }
                    setTimeout(() => messageDiv.innerHTML = '', 3000);
                } catch (error) {
                    console.error('Fetch Error:', error);
                    document.getElementById('message').innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Error: ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                    setTimeout(() => document.getElementById('message').innerHTML = '', 3000);
                }
            });
        });

        document.getElementById('addBookForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = this;
            const formData = new FormData(form);
            let cover_picture = '';

            // Upload image if present
            const coverFile = formData.get('cover_picture');
            if (coverFile && coverFile.name) {
                try {
                    const uploadFormData = new FormData();
                    uploadFormData.append('book_cover', coverFile);
                    const uploadResponse = await fetch('../backend/crud/upload_image.php', {
                        method: 'POST',
                        body: uploadFormData
                    });
                    const uploadResult = await uploadResponse.json();
                    if (uploadResult.success) {
                        cover_picture = uploadResult.url;
                    } else {
                        throw new Error(uploadResult.message);
                    }
                } catch (error) {
                    const messageDiv = document.getElementById('message');
                    messageDiv.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Image upload failed: ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                    setTimeout(() => messageDiv.innerHTML = '', 3000);
                    return;
                }
            }

            const bookData = {
                action: 'add_book',
                isbn: formData.get('isbn')?.trim(),
                title: formData.get('title')?.trim(),
                author_name: formData.get('author_name')?.trim(),
                author_title: formData.get('author_title')?.trim() || null,
                author_bio: formData.get('author_bio')?.trim() || null,
                category: formData.get('category'),
                publisher: formData.get('publisher')?.trim() || null,
                published_year: formData.get('published_year') || null,
                section_id: formData.get('section_id'),
                genres: formData.get('genres')?.trim().replace(/</g, '&lt;').replace(/>/g, '&gt;') || '',
                description: formData.get('description')?.trim().replace(/</g, '&lt;').replace(/>/g, '&gt;') || null,
                cover_picture: cover_picture || null,
                copies: parseInt(formData.get('copies')) || 0,
                req_id: formData.get('req_id') || null,
                subject: formData.get('subject')?.trim() || null,
                edition: parseInt(formData.get('edition')) || null,
                artist: formData.get('artist')?.trim() || null,
                studio: formData.get('studio')?.trim() || null,
                narrative: formData.get('narrative')?.trim() || null,
                timeline: formData.get('timeline')?.trim() || null
            };

            // Client-side validation
            if (!bookData.isbn) {
                alert('Please provide a valid ISBN');
                return;
            }
            if (isNaN(bookData.copies) || bookData.copies < 0) {
                alert('Number of copies must be a non-negative integer');
                return;
            }
            if (!bookData.title || !bookData.author_name || !bookData.category || !bookData.section_id) {
                alert('Please fill all required fields');
                return;
            }

            const requiredFields = {
                'Text Books': ['subject', 'edition'],
                'Comics': ['artist', 'studio'],
                'Novels': ['narrative'],
                'Magazines': ['timeline']
            };

            if (requiredFields[bookData.category]) {
                for (const field of requiredFields[bookData.category]) {
                    if (!bookData[field]) {
                        alert(`The field '${field}' is required for '${bookData.category}'`);
                        return;
                    }
                }
            }

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(bookData)
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                const result = await response.json();
                const messageDiv = document.getElementById('message');
                if (result.success) {
                    messageDiv.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                        ${result.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                    addBookModal.hide();
                    form.reset();
                    conditionalFields();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    messageDiv.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ${result.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                }
                setTimeout(() => messageDiv.innerHTML = '', 3000);
            } catch (error) {
                console.error('Fetch Error:', error);
                document.getElementById('message').innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Error: ${error.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
                setTimeout(() => document.getElementById('message').innerHTML = '', 3000);
            }
        });
    </script>
</body>
</html>