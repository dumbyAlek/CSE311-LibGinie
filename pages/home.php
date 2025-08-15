<?php
// Start the session
session_start();

// Check if the user is logged in, if not, redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

require_once '../backend/crud/db_config.php';

$user_id = $_SESSION['UserID'];
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'guest';
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// Function to fetch books from the database
function getBooks($con, $sql, $params = [], $types = "") {
    $books = [];
    $stmt = $con->prepare($sql);
    if ($stmt) {
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        $stmt->close();
    }
    return $books;
}

// Function to render a book section
function renderBookSection($title, $books, $search = false, $query = null) {
    if($title == "Top Rated" || $title == "New Arrivals" || $title == "Trending") {
        echo '<h3 class="section-title"><a href="AllBooks.php?orderby=' . htmlspecialchars(strtolower(str_replace(' ', '', $title))) . '">' . htmlspecialchars($title) . '</a></h3>';
    }
    else {
        echo '<h3 class="section-title">'  . htmlspecialchars($title) .'</h3>';
    }
    echo '<div class="book-section">';
    if (empty($books)) {
        echo '<p>No books available in this section.</p>';
    } else {
        $count = 0;
        foreach ($books as $book) {
            if ($count >= 8 && $search) break;
            echo '<a href="BookPage.php?isbn=' . htmlspecialchars($book['ISBN']) . '" class="book-card">';
            echo '<img src="' . htmlspecialchars($book['CoverPicture']) . '" alt="' . htmlspecialchars($book['Title']) . '">';
            echo '<p>' . htmlspecialchars($book['Title']) . '</p>';
            echo '</a>';
            $count++;
        }
    }
    echo '</div>';
}

// Fetch books for each section based on your logic and schema
// New Arrivals: Order by PublishedYear descending
$newArrivalsBooks = getBooks($con, "SELECT ISBN, Title, CoverPicture FROM Books ORDER BY PublishedYear DESC LIMIT 10");

// Trending: Most visited books (using a new "views" column/table or a simplified join)
// This is a placeholder as your schema doesn't have a 'views' column. A more complete solution would require a new 'BookViews' table.
// For now, we'll use Borrow count as a proxy for popularity.
$trendingBooks = getBooks($con, "SELECT b.ISBN, b.Title, b.CoverPicture, COUNT(bo.BorrowID) as borrow_count 
                                 FROM Books b LEFT JOIN BookCopy bc ON b.ISBN = bc.ISBN 
                                 LEFT JOIN Borrow bo ON bc.CopyID = bo.CopyID 
                                 GROUP BY b.ISBN ORDER BY borrow_count DESC LIMIT 10");

// Top Rated: Books with the highest average rating
$topRatedBooks = getBooks($con, "SELECT b.ISBN, b.Title, b.CoverPicture, AVG(br.Rating) as avg_rating 
                                 FROM Books b JOIN BookReviews br ON b.ISBN = br.ISBN 
                                 GROUP BY b.ISBN ORDER BY avg_rating DESC LIMIT 10");

if (!$is_guest) {
    // Favorites: from the BookInteractions table
    $favBooks = getBooks($con, "SELECT b.ISBN, b.Title, b.CoverPicture FROM Books b INNER JOIN BookInteractions bi ON b.ISBN = bi.ISBN WHERE bi.UserID = ? AND bi.IsFavorite = 1 LIMIT 10", [$user_id], "i");

    // Your Read: from the BookInteractions table
    $readBooks = getBooks($con, "SELECT b.ISBN, b.Title, b.CoverPicture FROM Books b INNER JOIN BookInteractions bi ON b.ISBN = bi.ISBN WHERE bi.UserID = ? AND bi.IsRead = 1 LIMIT 10", [$user_id], "i");

    // Recommended For You: Based on the genres of their favorite and read books
    $sql_genres = "SELECT DISTINCT bg.GenreID FROM BookInteractions bi JOIN Book_Genres bg ON bi.ISBN = bg.ISBN WHERE bi.UserID = ?";
    $user_genres = getBooks($con, $sql_genres, [$user_id], "i");

    $recommendedBooks = [];
    if (!empty($user_genres)) {
        $genre_ids = array_column($user_genres, 'GenreID');
        $in_clause = str_repeat('?,', count($genre_ids) - 1) . '?';
        $sql_recommended = "SELECT DISTINCT b.ISBN, b.Title, b.CoverPicture FROM Books b JOIN Book_Genres bg ON b.ISBN = bg.ISBN WHERE bg.GenreID IN ($in_clause) LIMIT 10";
        $types = str_repeat('i', count($genre_ids));
        $recommendedBooks = getBooks($con, $sql_recommended, $genre_ids, $types);
    }
}
$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LibGinie - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
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
            background-color: #3d3125ff;
            color: #eee;
        }

        /* sidebarstart  */

        .sidebar {
            background-color: rgba(0, 0, 0, 0.4); /* Dark overlay */
            background-blend-mode: multiply;
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

        /* controlling space between sidebar links */
        .sidebar li {
            margin-bottom: 0.6rem; 
        }

        .sidebar a {
            text-decoration: none;
            color: white;
            font-weight: 500;
            font-size: 1.2rem;
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
            background-color: #7b3fbf; /* violet square */
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
            transform: rotate(90deg); /* Rotated vertical bars */
        }


        .content-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s;
        }

        .sidebar.closed ~ .content-wrapper {
            margin-left: 0;
        }

        .sidebar.closed ~ .content-wrapper .bg-header {
            margin-left: 0;
        }

        /* sidebarend */

        .bg-header {
            background-image: url('../images/header.jpg');
            background-size: cover;
            height: 300px;
            background-position: center;
            padding: 80px 20px 40px;
            color: white;
            transition: margin-left 0.3s, width 0.3s;
        }

        .sidebar.closed ~ .content-wrapper .bg-header {
            margin-left: 0;
        }

        .search-bar {
            max-width: 600px;
            margin: auto;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            margin: 40px 0 20px;
        }

        .section-title a {
            text-decoration: none;
            color: #7b3fbf;
        }

        .section-title:hover {
            transform: translateY(-7px);
            background-color: #e1ccf9ff;;
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
            background: #f0e4fcff;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .book-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .book-card p {
            font-weight: bold;
            color: #7e189bff;
            border-radius: 0 0 8px 8px;
        }
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
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
            margin-right: 8px; /* Adjust the space as you want */
        }

        input:checked + .slider {
            background-color: #7b3fbf; /* violet */
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .logo-on-header {
            display: none;
            position: absolute;
            top: 0px;
            left: 50px;
            width: 200px;
            z-index: 100;
        }

        .logo-on-header.visible {
            display: block;
        }
        
        /* New Styles for Admin and Librarian features */
        .notification-icon {
            position: fixed;
            top: 60px; /* Below the toggle switch */
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

        .list-group-item-action:hover {
            background-color: #c4bef4ff; /* Light gray background on hover */
            transition: background-color 0.3s ease;
        }

        .list-group-item-action img:hover {
            transform: scale(5); /* Slightly enlarge the image on hover */
            transition: transform 0.3s ease;
        }

        .list-group {
            width: 600px;
        }

        .list-group-item {
            width: 600px;
        }

        .section-header {
            display: flex;
            justify-content: space-between; /* Pushes the title and link to opposite ends */
            align-items: center; /* Vertically centers the items */
            margin-bottom: 10px; /* Optional: adds some space below the header */
        }

        .view-all-link {
            font-size: 0.9em; /* Makes the font a bit smaller */
            color: #007bff; /* Use a brand color, like Bootstrap's blue */
            text-decoration: none; /* Removes the underline */
        }

        .view-all-link:hover {
            text-decoration: underline; /* Adds an underline on hover for a better user experience */
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

    </style>
</head>

<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>
    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="theme-switch-wrapper">
            <label class="theme-switch" for="themeToggle">
                <input type="checkbox" id="themeToggle" />
                <span class="slider"></span>
            </label>
        </div>
        
        <?php if ($is_librarian) : ?>
        <a href="#" class="notification-icon" title="View Notifications" onclick="toggleTaskBox()">🔔</a>
        <?php endif; ?>
        
        <header class="bg-header text-center">
            <div id="headerLogo" class="logo-on-header">
                <img src="../images/logo3.png" alt="Logo" />
            </div>
            
            <div class="search-bar">
                <input type="text" id="mainSearchBar" class="form-control form-control-lg" placeholder="Search books...">
                <div id="searchResultsDropdown" class="list-group" style="display:none; position: absolute; width: 100%; z-index: 1000;">
                    <div id="resultsList" class="list-group">
                        </div>
                    <a id="viewAllResultsLink" href="#" class="list-group-item list-group-item-action text-center fw-bold" style="display:none;">View All Results</a>
                </div>
            </div>
        </header>

        <div id="searchOverlay" style="display: none;">
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-baseline">
                    <h3 class="section-title">Search Results</h3>
                    <button id="closeOverlayBtn" class="btn btn-secondary">Close</button>
                </div>
                <a id="viewAllResultsLink" href="#" style="display:none;">View All</a>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <tbody id="searchResultsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <main class="container mt-4">
            <div id="mainContent">
                <?php
                renderBookSection("New Arrivals", $newArrivalsBooks, 'AllBooks.php?orderby=newarrivals');
                renderBookSection("Trending", $trendingBooks, 'AllBooks.php?orderby=trending');
                renderBookSection("Top Rated", $topRatedBooks, 'AllBooks.php?orderby=toprated');
                if (!$is_guest) {
                    renderBookSection("Recommended For You", $recommendedBooks);
                    renderBookSection("Favourites", $favBooks);
                    renderBookSection("Your Read", $readBooks);
                }
                ?>
            </div>
        </main>
        
        <?php
        if ($is_librarian && isset($_SESSION['UserID'])) {
            require_once '../backend/crud/db_config.php';
            $librarian_user_id = $_SESSION['UserID'];
            $assigned_section = 'Not assigned yet';
            $sql = "SELECT InChargeOf FROM Librarian WHERE UserID = ?";
            $stmt = $con->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $librarian_user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $assigned_section = htmlspecialchars($row['InChargeOf']);
                }
                $stmt->close();
            }
            $con->close();
            ?>
            <div class="librarian-tasks" id="taskBox">
                <div class="tasks-header">
                    <span>My Tasks</span>
                    <button class="btn btn-sm btn-link text-dark" onclick="toggleTaskBox()">▼</button>
                </div>
                <div style="font-size: 1.25rem; font-weight: bold;">
                    Assigned Section:
                </div>
                <div style="font-size: 0.9rem; margin-top: 5px;">
                    <?php echo $assigned_section; ?>
                </div>
                <ul class="task-list mt-2" style="font-size: 0.9rem;">
                </ul>
            </div>
            <?php
        }
        ?>
        
        <footer>
            <p>&copy; 2025 LibGinie. All rights reserved.</p>
        </footer>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const headerLogo = document.getElementById('headerLogo');

        function toggleSidebar() {
            sidebar.classList.toggle('closed');
            if (sidebar.classList.contains('closed')) {
                headerLogo.classList.add('visible');
            } else {
                headerLogo.classList.remove('visible');
            }
        }

        if (sidebar.classList.contains('closed')) {
            headerLogo.classList.add('visible');
        }

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

        $(document).ready(function() {
            const mainSearchBar = $('#mainSearchBar');
            const searchResultsDropdown = $('#searchResultsDropdown');
            const searchResults = $('#searchResults');
            const searchResultsTableBody = $('#searchResultsTableBody');
            const viewAllResultsLink = $('#viewAllResultsLink');
            const mainContent = $('#mainContent');
            const searchOverlay = $('#searchOverlay');

            // Function to hide the search dropdown
            function hideSearchDropdown() {
                searchResultsDropdown.hide();
            }

             // Function to perform the search
            function performSearch(query) {
                if (query.length > 0) {
                    $.ajax({
                        url: '../backend/crud/search.php',
                        type: 'GET',
                        data: { q: query },
                        dataType: 'json',
                        success: function(response) {
                            const resultsList = $('#resultsList');
                            resultsList.empty();
                            $('#viewAllResultsLink').hide();

                            if (response.length > 0) {
                                response.slice(0, 4).forEach(book => {
                                    const resultItem = `
                                        <a href="BookPage.php?isbn=${book.ISBN}" class="list-group-item list-group-item-action d-flex align-items-center">
                                            <img src="${book.CoverPicture}" alt="${book.Title}" class="me-3" style="width: 40px; height: 60px; object-fit: cover;">
                                            <span>${book.Title}</span>
                                        </a>
                                    `;
                                    resultsList.append(resultItem);
                                });

                                if (response.length > 0) {
                                        $('#viewAllResultsLink').attr('href', `AllBooks.php?search=${encodeURIComponent(query)}`).show();
                                }
                            } else {
                                const noResults = `<a href="#" class="list-group-item list-group-item-action disabled">No books found.</a>`;
                                resultsList.append(noResults);
                            }
                            $('#searchResultsDropdown').show();
                        },
                        error: function(xhr, status, error) {
                            console.error("AJAX Error:", error);
                            searchResultsTableBody.html('<tr><td colspan="4" class="text-danger">An error occurred while searching.</td></tr>');
                            searchOverlay.show(); // NEW: Show the overlay even on error
                        }
                    });
                } else {
                    searchOverlay.hide();
                }
            }

            let typingTimer;
            const doneTypingInterval = 250;

            mainSearchBar.on('keyup', function(event) {
                clearTimeout(typingTimer);
                const query = $(this).val().trim();
                if (query.length > 0) {
                    typingTimer = setTimeout(() => performSearch(query), doneTypingInterval);
                } else {
                    hideSearchDropdown();
                }
                // Check for Enter key press on an empty query
                if (event.key === 'Enter' && query.length === 0) {
                    hideSearchDropdown();
                }
            });

            // Event listener to close the dropdown when clicking outside
            $(document).on('click', function(event) {
                // Check if the click target is NOT the search bar or the dropdown itself
                if (!mainSearchBar.is(event.target) && !searchResultsDropdown.is(event.target) && searchResultsDropdown.has(event.target).length === 0) {
                hideSearchDropdown();
            }
            });
        });
    </script>
</body>
</html>