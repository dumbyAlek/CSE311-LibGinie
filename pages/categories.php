<?php
// Start the session
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

// Include your database configuration
require_once '../backend/crud/db_config.php';

// Get user data from the session
$user_id = $_SESSION['UserID'];
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'guest';
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// Get the category from the URL, or default to an empty string
$category = $_GET['category'] ?? '';
$search_query = $_GET['search'] ?? '';

// Initialize an empty array for books
$books = [];
$error_message = '';

// Fetch books based on the selected category and search query
if ($category) {
    // Sanitize the category and search query
    $safe_category = $con->real_escape_string($category);
    $search_param = "%" . $con->real_escape_string($search_query) . "%";

    $sql = "
        SELECT 
            b.Title, 
            b.ISBN, 
            b.CoverPicture, 
            m.Name AS AuthorName,
            GROUP_CONCAT(g.GenreName SEPARATOR ', ') AS Genres
        FROM 
            Books b
        LEFT JOIN 
            Author a ON b.AuthorID = a.AuthorID
        LEFT JOIN 
            Members m ON a.UserID = m.UserID
        LEFT JOIN 
            Book_Genres bg ON b.ISBN = bg.ISBN
        LEFT JOIN 
            Genres g ON bg.GenreID = g.GenreID
        WHERE 
            b.Category = ? AND b.Title LIKE ?
        GROUP BY 
            b.ISBN
        ORDER BY 
            b.Title";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ss", $safe_category, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
    } else {
        $error_message = "No books found in the '" . htmlspecialchars($category) . "' category matching your search.";
    }

    $stmt->close();
} else {
    $error_message = "Please select a category.";
}

$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LibGinie - <?php echo htmlspecialchars($category); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
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

        /* Container for the heading and search bar */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 40px 0 20px;
        }
        
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            margin: 0;
        }

        /* Search Form Styles */
        .search-form {
            display: flex;
            align-items: center;
        }
        .search-input {
            width: 250px; /* Adjust size as needed */
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 20px; /* Rounded corners */
            font-size: 1rem;
            transition: width 0.3s ease-in-out;
        }
        .search-input:focus {
            outline: none;
            border-color: #7b3fbf;
            box-shadow: 0 0 0 2px rgba(123, 63, 191, 0.2);
            width: 300px; /* Expand on focus */
        }
        .search-btn {
            background-color: #7b3fbf;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            margin-left: 10px;
            cursor: pointer;
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
        .book-card {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
        }
        .book-card img {
            max-width: 120px;
            height: auto;
            border-radius: 4px;
            margin-right: 20px;
        }
        .book-card:hover {
            transform: translateX(-5px);
            color: #7b3fbf;
            background-color: #f3e9ffff;;
        }
        .book-info {
            flex-grow: 1;
        }
        .book-info h5 {
            font-family: 'Montserrat', sans-serif;
            color: #7b3fbf;
        }
        .dark-theme .book-card {
            background-color: #222;
            color: #eee;
            border-color: #444;
        }
        .book-card-link {
            text-decoration: none; /* Removes the underline */
            color: inherit; /* Inherits the text color from the parent */
        }
    </style>
</head>

<body>

    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <main class="container mt-4">
            <div class="section-header">
                <h3 class="section-title"><?php echo htmlspecialchars($category); ?></h3>
                <form class="search-form" action="categories.php" method="GET">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <input 
                        type="search" 
                        name="search" 
                        class="form-control search-input" 
                        id="searchInput" placeholder="Search books..." 
                        value="<?php echo htmlspecialchars($search_query); ?>"
                    >
                </form>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-warning"><?php echo $error_message; ?></div>
            <?php else: ?>
                <div class="book-list">
                    <?php foreach ($books as $book): ?>
                        <a href="BookPage.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>" class="book-card-link">
                            <div class="book-card">
                                <?php if ($book['CoverPicture']): ?>
                                    <img src="<?php echo htmlspecialchars($book['CoverPicture']); ?>" alt="Cover of <?php echo htmlspecialchars($book['Title']); ?>">
                                <?php else: ?>
                                    <img src="../images/no-cover.png" alt="No cover available">
                                <?php endif; ?>
                                <div class="book-info">
                                    <h5><?php echo htmlspecialchars($book['Title']); ?></h5>
                                    <p><strong>Author:</strong> <?php echo htmlspecialchars($book['AuthorName'] ?? 'Unknown'); ?></p>
                                    <p><strong>ISBN:</strong> <?php echo htmlspecialchars($book['ISBN']); ?></p>
                                    <p><strong>Genres:</strong> <?php echo htmlspecialchars($book['Genres'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <footer>
            <p>&copy; 2025 LibGinie. All rights reserved.</p>
        </footer>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');

        function toggleSidebar() {
            sidebar.classList.toggle('closed');
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
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchForm = document.querySelector('.search-form');
            const bookList = document.querySelector('.book-list');

            // Debounce function to delay execution
            function debounce(func, delay) {
                let timeout;
                return function(...args) {
                    const context = this;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), delay);
                };
            }

            const handleSearch = debounce(() => {
                const formData = new FormData(searchForm);
                const urlParams = new URLSearchParams(formData);

                // Construct the URL for the AJAX request
                const url = searchForm.getAttribute('action') + '?' + urlParams.toString();

                // Fetch the search results from the server
                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Helps PHP identify an AJAX request
                    }
                })
                .then(response => response.text())
                .then(html => {
                    // Find and replace the book list section
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newBookList = doc.querySelector('.book-list');
                    
                    // Replace the old content with the new content
                    if (newBookList) {
                        bookList.innerHTML = newBookList.innerHTML;
                    } else {
                        // Handle case where no books are found
                        bookList.innerHTML = `<div class="alert alert-warning">No books found in the selected category matching your search.</div>`;
                    }
                })
                .catch(error => console.error('Error fetching search results:', error));

            }, 500); // 500ms delay

            searchInput.addEventListener('input', handleSearch);
        });
    </script>
</body>
</html>