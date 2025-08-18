<?php
// Start the session
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

require_once '../backend/crud/db_config.php';

$user_id = $_SESSION['UserID'];
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'guest';
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// Function to fetch book data for a specific list
function fetchUserBooks($con, $user_id, $list_type) {
    $sql = '';
    if ($list_type === 'favorites') {
        $sql = "SELECT B.ISBN, B.Title, B.CoverPicture, M.Name as AuthorName 
                FROM BookInteractions BI 
                JOIN Books B ON BI.ISBN = B.ISBN 
                JOIN Author A ON B.AuthorID = A.AuthorID
                JOIN Members M ON A.UserID = M.UserID
                WHERE BI.UserID = ? AND BI.IsFavorite = 1";
    } elseif ($list_type === 'read') {
        $sql = "SELECT B.ISBN, B.Title, B.CoverPicture, M.Name as AuthorName
                FROM BookInteractions BI 
                JOIN Books B ON BI.ISBN = B.ISBN 
                JOIN Author A ON B.AuthorID = A.AuthorID
                JOIN Members M ON A.UserID = M.UserID
                WHERE BI.UserID = ? AND BI.IsRead = 1";
    } elseif ($list_type === 'wishlist') {
        $sql = "SELECT B.ISBN, B.Title, B.CoverPicture, M.Name as AuthorName
                FROM BookInteractions BI 
                JOIN Books B ON BI.ISBN = B.ISBN 
                JOIN Author A ON B.AuthorID = A.AuthorID
                JOIN Members M ON A.UserID = M.UserID
                WHERE BI.UserID = ? AND BI.InWishlist = 1";
    }
    
    $books = [];
    if ($stmt = $con->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        $stmt->close();
    }
    return $books;
}

// Function to fetch a list of books based on an SQL query
function getBooks($con, $sql, $params, $types) {
    $books = [];
    if ($stmt = $con->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        $stmt->close();
    }
    return $books;
}

// Fetch all the book lists for the current user
$favorite_books = fetchUserBooks($con, $user_id, 'favorites');
$read_books = fetchUserBooks($con, $user_id, 'read');
$wishlist_books = fetchUserBooks($con, $user_id, 'wishlist');

// Recommended For You: Based on the genres of their favorite and read books
$sql_genres = "SELECT DISTINCT bg.GenreID FROM BookInteractions bi JOIN Book_Genres bg ON bi.ISBN = bg.ISBN WHERE bi.UserID = ? AND (bi.IsFavorite = 1 OR bi.IsRead = 1 OR bi.InWishlist = 1)";
$user_genres = getBooks($con, $sql_genres, [$user_id], "i");

$recommendedBooks = [];
if (!empty($user_genres)) {
    $genre_ids = array_column($user_genres, 'GenreID');
    $in_clause = implode(',', array_fill(0, count($genre_ids), '?'));

    // SQL query to get books from the same genres that the user has NOT interacted with
    $sql_recommended = "SELECT DISTINCT b.ISBN, b.Title, b.CoverPicture
                        FROM Books b
                        JOIN Book_Genres bg ON b.ISBN = bg.ISBN
                        WHERE bg.GenreID IN ($in_clause) 
                        AND b.ISBN NOT IN (SELECT ISBN 
                                        FROM BookInteractions 
                                        WHERE UserID = ? AND (IsFavorite = 1 OR IsRead = 1 OR InWishlist = 1)
                                        )
                        ORDER BY RAND() LIMIT 6";
    
    // Prepare the parameters for the SQL query
    $params = array_merge($genre_ids, [$user_id]);
    $types = str_repeat('i', count($genre_ids)) . 'i';

    $recommendedBooks = getBooks($con, $sql_recommended, $params, $types);
}

// Close the connection
$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Books - LibGinie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.4.0/css/all.css">
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
        .sidebar.closed ~ .content-wrapper {
            margin-left: 0;
        }
        .disabled-link {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
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
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #7b3fbf;
            border-bottom: 2px solid #7b3fbf;
            padding-bottom: 10px;
            margin-top: 40px;
        }
        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }
        .book-item {
            text-align: center;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
            border-radius: 8px;
            overflow: hidden;
            background-color: white;
            position: relative;
        }
        .book-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        .book-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        .book-info {
            padding: 10px;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .book-info p {
            margin: 0;
            font-weight: bold;
            color: #7b3fbf;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            width: 100%;
        }
        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(255, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 1rem;
            cursor: pointer;
            display: none;
        }
        .book-item:hover .remove-btn {
            display: block;
        }
        .list-container {
            margin-bottom: 50px;
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <main class="container mt-4">
            <h1 class="mb-4 text-center">My Books</h1>

            <div class="list-container">
                <h2 class="section-title">Favorite Books</h2>
                <div class="book-grid" id="favorite-books-grid">
                    <?php if (count($favorite_books) > 0): ?>
                        <?php foreach ($favorite_books as $book): ?>
                            <div class="book-item" data-isbn="<?php echo htmlspecialchars($book['ISBN']); ?>" data-list="favorites">
                                <button class="remove-btn" onclick="removeBook('<?php echo htmlspecialchars($book['ISBN']); ?>', 'IsFavorite')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <a href="BookPage.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>">
                                    <img src="<?php echo htmlspecialchars($book['CoverPicture'] ?: '../images/no-cover.png'); ?>" alt="<?php echo htmlspecialchars($book['Title']); ?>">
                                    <div class="book-info">
                                        <p><?php echo htmlspecialchars($book['Title']); ?></p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center w-100">You have no favorite books yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <hr>

            <div class="list-container">
                <h2 class="section-title">Read Books</h2>
                <div class="book-grid" id="read-books-grid">
                    <?php if (count($read_books) > 0): ?>
                        <?php foreach ($read_books as $book): ?>
                            <div class="book-item" data-isbn="<?php echo htmlspecialchars($book['ISBN']); ?>">
                                <a href="BookPage.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>">
                                    <img src="<?php echo htmlspecialchars($book['CoverPicture'] ?: '../images/no-cover.png'); ?>" alt="<?php echo htmlspecialchars($book['Title']); ?>">
                                    <div class="book-info">
                                        <p><?php echo htmlspecialchars($book['Title']); ?></p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center w-100">You haven't marked any books as read yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <hr>

            <div class="list-container">
                <h2 class="section-title">My Wishlist</h2>
                <div class="book-grid" id="wishlist-books-grid">
                    <?php if (count($wishlist_books) > 0): ?>
                        <?php foreach ($wishlist_books as $book): ?>
                            <div class="book-item" data-isbn="<?php echo htmlspecialchars($book['ISBN']); ?>" data-list="wishlist">
                                <button class="remove-btn" onclick="removeBook('<?php echo htmlspecialchars($book['ISBN']); ?>', 'InWishlist')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <a href="BookPage.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>">
                                    <img src="<?php echo htmlspecialchars($book['CoverPicture'] ?: '../images/no-cover.png'); ?>" alt="<?php echo htmlspecialchars($book['Title']); ?>">
                                    <div class="book-info">
                                        <p><?php echo htmlspecialchars($book['Title']); ?></p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center w-100">Your wishlist is empty.</p>
                    <?php endif; ?>
                </div>
            </div>

            <hr>

            <div class="list-container">
                <h2 class="section-title">Recommended for You</h2>
                <div class="book-grid" id="recommended-books-grid">
                    <?php if (count($recommendedBooks) > 0): ?>
                        <?php foreach ($recommendedBooks as $book): ?>
                            <a href="BookPage.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>" class="book-item">
                                <img src="<?php echo htmlspecialchars($book['CoverPicture'] ?: '../images/no-cover.png'); ?>" alt="<?php echo htmlspecialchars($book['Title']); ?>">
                                <div class="book-info">
                                    <p><?php echo htmlspecialchars($book['Title']); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center w-100">We couldn't find any recommendations for you yet.</p>
                    <?php endif; ?>
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
        }

        function removeBook(isbn, listType) {
            if (confirm("Are you sure you want to remove this book from your list?")) {
                $.ajax({
                    url: '../backend/crud/update_book_interaction.php',
                    type: 'POST',
                    data: {
                        isbn: isbn,
                        action: 'remove',
                        list_type: listType
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $(`.book-item[data-isbn="${isbn}"][data-list="${listType === 'IsFavorite' ? 'favorites' : 'wishlist'}"]`).remove();
                            const listGrid = $(`#${listType === 'IsFavorite' ? 'favorite' : 'wishlist'}-books-grid`);
                            if (listGrid.find('.book-item').length === 0) {
                                listGrid.html(`<p class="text-center w-100">You have no ${listType === 'IsFavorite' ? 'favorite books' : 'wishlist books'} yet.</p>`);
                            }
                        } else {
                            alert("Failed to remove book: " + response.message);
                        }
                    },
                    error: function() {
                        alert("An error occurred. Please try again.");
                    }
                });
            }
        }
    </script>
</body>
</html>