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

// Function to fetch distinct values for filters
function getDistinctValues($con, $table, $column, $join = '') {
    $sql = "SELECT DISTINCT T1.$column FROM $table T1 $join ORDER BY T1.$column";
    $result = $con->query($sql);
    $values = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row[$column])) {
                $values[] = htmlspecialchars($row[$column]);
            }
        }
    }
    return $values;
}

// Fetch data for the filters
$genres = getDistinctValues($con, 'Genres', 'GenreName');
$publishers = getDistinctValues($con, 'Books', 'Publisher');
$published_years = getDistinctValues($con, 'Books', 'PublishedYear');
// Fetch authors separately due to the more complex join
$sql_authors = "SELECT DISTINCT m.Name FROM Members m JOIN Author a ON m.UserID = a.UserID ORDER BY m.Name";
$result_authors = $con->query($sql_authors);
$authors = [];
if ($result_authors) {
    while ($row = $result_authors->fetch_assoc()) {
        $authors[] = htmlspecialchars($row['Name']);
    }
}

// Keep the existing calls for other values
$genres = getDistinctValues($con, 'Genres', 'GenreName');
$publishers = getDistinctValues($con, 'Books', 'Publisher');
$published_years = getDistinctValues($con, 'Books', 'PublishedYear');

$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LibGinie - All Books</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.4.0/css/all.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.css" rel="stylesheet" />
    
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
        /* AllBooks specific styles */
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .search-bar {
            width: 300px;
        }
        .filter-toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 25px;
            color: #4f4d4dff;
            transition: color 0.3s;
        }
        .filter-toggle-btn.active {
            color: #0080ffff;
        }
        .filter-options {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 10px;
            display: none;
        }
        .filter-options.dark-theme {
            background-color: #222;
            border-color: #444;
            color: #eee;
        }
        .filter-options .form-label {
            font-weight: bold;
            color: #333;
        }
        .filter-options.dark-theme .form-label {
            color: #eee;
        }
        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .book-item {
            text-align: center;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.2s, box-shadow 0.2s;
            border-radius: 8px;
            overflow: hidden;
        }
        .book-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        .book-item img {
            width: 100%;
            height: 300px; /* Standardize image height */
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        .book-item p {
            background-color: #fff;
            margin: 0;
            padding: 10px 5px;
            font-weight: bold;
            color: #7b3fbf;
            border-radius: 0 0 8px 8px;
        }
        .book-item:hover p {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    
    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <main class="container mt-4">
            <h1 class="mb-4 text-center">All Books</h1>

            <div class="header-controls">
                <div class="search-bar">
                    <input type="text" id="bookSearchInput" class="form-control" placeholder="Search by title, author or ISBN..." />
                </div>
                <button id="filterToggleBtn" class="filter-toggle-btn">
                    <i class="fa-solid fa-filter"></i>
                </button>
            </div>

            <div id="filterOptions" class="filter-options">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="genreFilter" class="form-label">Genre</label>
                        <div class="multiselect-container">
                            <select id="genreFilter" class="form-select" multiple>
                                <?php foreach ($genres as $genre): ?>
                                    <option value="<?php echo $genre; ?>"><?php echo $genre; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="yearFilter" class="form-label">Year</label>
                        <div class="multiselect-container">
                            <select id="yearFilter" class="form-select" multiple>
                                <?php foreach ($published_years as $year): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="publisherFilter" class="form-label">Publisher</label>
                        <div class="multiselect-container">
                            <select id="publisherFilter" class="form-select" multiple>
                                <?php foreach ($publishers as $publisher): ?>
                                    <option value="<?php echo $publisher; ?>"><?php echo $publisher; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="authorFilter" class="form-label">Author</label>
                        <div class="multiselect-container">
                            <select id="authorFilter" class="form-select" multiple>
                                <?php foreach ($authors as $author): ?>
                                    <option value="<?php echo $author; ?>"><?php echo $author; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button id="applyFiltersBtn" class="btn btn-primary me-2">Apply Filters</button>
                    <button id="resetFiltersBtn" class="btn btn-secondary">Reset Filters</button>
                </div>
            </div>
            <div id="bookGrid" class="book-grid">
                </div>

            <div id="noBooksFound" class="text-center mt-5" style="display: none;">
                <h3>No books found.</h3>
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

        $(document).ready(function() {
            const filterToggleBtn = $('#filterToggleBtn');
            const filterOptions = $('#filterOptions');
            const bookSearchInput = $('#bookSearchInput');
            const genreFilter = $('#genreFilter');
            const yearFilter = $('#yearFilter');
            const publisherFilter = $('#publisherFilter');
            const authorFilter = $('#authorFilter');
            const applyFiltersBtn = $('#applyFiltersBtn');
            const resetFiltersBtn = $('#resetFiltersBtn');

            function fetchBooks(filters = {}) {
                $.ajax({
                    url: '../backend/crud/get_all_books.php',
                    type: 'GET',
                    data: filters,
                    dataType: 'json',
                    success: function(response) {
                        const bookGrid = $('#bookGrid');
                        bookGrid.empty();
                        if (response.length > 0) {
                            $('#noBooksFound').hide();
                            response.forEach(book => {
                                const bookItem = `
                                    <a href="BookPage.php?isbn=${book.ISBN}" class="book-item">
                                        <img src="${book.CoverPicture ? book.CoverPicture : '../images/no-cover.png'}" alt="${book.Title}">
                                        <p>${book.Title}</p>
                                    </a>
                                `;
                                bookGrid.append(bookItem);
                            });
                        } else {
                            $('#noBooksFound').show();
                        }
                    },
                    error: function() {
                        $('#bookGrid').html('<p class="text-center text-danger">An error occurred while fetching books.</p>');
                    }
                });
            }

            function updateFilterIconColor() {
                const isFiltered = 
                    (bookSearchInput.val() !== '') || 
                    (genreFilter.val() && genreFilter.val().length > 0) || 
                    (yearFilter.val() && yearFilter.val().length > 0) || 
                    (publisherFilter.val() && publisherFilter.val().length > 0) || 
                    (authorFilter.val() && authorFilter.val().length > 0);
                
                if (isFiltered) {
                    filterToggleBtn.addClass('active');
                } else {
                    filterToggleBtn.removeClass('active');
                }
            }

            // Toggle filter options visibility
            filterToggleBtn.on('click', function() {
                filterOptions.slideToggle();
            });

            // Initial load of all books
            fetchBooks();

            // Live search functionality with debounce
                let typingTimer; // A timer to debounce the search
                const doneTypingInterval = 250; // 500ms delay after typing stops

                bookSearchInput.on('keyup', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function() {
                        const searchTerm = bookSearchInput.val();
                        fetchBooks({
                            search: searchTerm
                        });
                        updateFilterIconColor();
                    }, doneTypingInterval);
                });

                // Handle 'Enter' key press on the search input
                bookSearchInput.on('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault(); // Prevents page reload
                        clearTimeout(typingTimer); // Clear any pending debounced search
                        const searchTerm = $(this).val();
                        fetchBooks({
                            search: searchTerm
                        });
                        updateFilterIconColor();
                    }
                });

            // Apply Filters button click
            applyFiltersBtn.on('click', function() {
                const filters = {
                    search: bookSearchInput.val(),
                    genres: genreFilter.val(),
                    years: yearFilter.val(),
                    publishers: publisherFilter.val(),
                    authors: authorFilter.val()
                };
                fetchBooks(filters);
                updateFilterIconColor();
            });

            // Reset Filters button click
            resetFiltersBtn.on('click', function() {
                bookSearchInput.val('');
                genreFilter.val([]);
                yearFilter.val([]);
                publisherFilter.val([]);
                authorFilter.val([]);
                fetchBooks(); // Fetch all books
                updateFilterIconColor();
            });
            // Initialize the searchable multiselects on all filter dropdowns
            new TomSelect("#genreFilter", {
                plugins: ['remove_button', 'clear_button']
            });
            new TomSelect("#yearFilter", {
                plugins: ['remove_button', 'clear_button']
            });
            new TomSelect("#publisherFilter", {
                plugins: ['remove_button', 'clear_button']
            });
            new TomSelect("#authorFilter", {
                plugins: ['remove_button', 'clear_button']
            });
        });
    </script>
</body>
</html>