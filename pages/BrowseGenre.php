<?php
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

// Function to fetch all genres from the database
function getAllGenres($con) {
    $sql = "SELECT GenreID, GenreName FROM Genres ORDER BY GenreName";
    $result = $con->query($sql);
    $genres = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $genres[] = $row;
        }
    }
    return $genres;
}

$all_genres = getAllGenres($con);
$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LibGinie - Browse Genres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-ieo+pA15t6D6p6d8I9t6Jv2i6jP0UjI3j33p2d+3bF8G2R6P5+a+wG2E2Xw8f+uL2z3t3g3L7g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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

        /* New Styles for BrowseGenre.php */
        .search-container {
            position: relative;
            margin-bottom: 20px;
        }

        #genreSearchInput {
            border-radius: 20px;
            padding-right: 40px;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: white;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-suggestions a {
            padding: 10px;
            display: block;
            text-decoration: none;
            color: #333;
        }

        .search-suggestions a:hover {
            background-color: #f1f1f1;
        }

        .filter-tags-container {
            margin-bottom: 20px;
        }

        .genre-tag {
            background-color: #7b3fbf;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            margin: 5px;
            display: inline-block;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .genre-tag.active {
            background-color: #4b2670;
            font-weight: bold;
        }

        .genre-tag:hover {
            background-color: #5d3190;
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
            height: 300px;
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

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <main class="container mt-4">
            <h1 class="mb-4 text-center">Browse Books by Genre</h1>
            <div class="search-container">
                <input type="text" id="genreSearchInput" class="form-control" placeholder="Search for genres..." />
                <div id="genreSuggestions" class="search-suggestions"></div>
            </div>

            <div id="filterTagsContainer" class="filter-tags-container d-flex flex-wrap"></div>
            <div id="bookGrid" class="book-grid"></div>
            <div id="noBooksFound" class="text-center mt-5" style="display: none;">
                <h3>No books found for the selected genre(s).</h3>
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
            const allGenres = <?php echo json_encode($all_genres); ?>;
            let selectedGenres = new Set();

            function updateBooksByGenre() {
                const genreIds = Array.from(selectedGenres).join(',');
                $.ajax({
                    url: '../backend/crud/get_genres_books.php',
                    type: 'GET',
                    data: { genre_ids: genreIds },
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
                                    </a>`;
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

            function addGenreTag(genreId, genreName) {
                if (selectedGenres.has(genreId)) return;
                selectedGenres.add(genreId);
                const tag = $(`<span class="genre-tag" data-genre-id="${genreId}">${genreName} <i class="fa fa-times-circle"></i></span>`);
                $('#filterTagsContainer').append(tag);
                tag.on('click', function() {
                    selectedGenres.delete(genreId);
                    $(this).remove();
                    updateBooksByGenre();
                });
                updateBooksByGenre();
            }

            updateBooksByGenre();

            $('#genreSearchInput').on('keyup', function() {
                const query = $(this).val().toLowerCase();
                const suggestionsBox = $('#genreSuggestions');
                suggestionsBox.empty();

                if (query.length > 0) {
                    const filteredGenres = allGenres.filter(g => g.GenreName.toLowerCase().includes(query));
                    if (filteredGenres.length > 0) {
                        suggestionsBox.show();
                        filteredGenres.forEach(genre => {
                            const suggestionItem = $(`<a href="#">${genre.GenreName}</a>`);
                            suggestionItem.on('click', function(e) {
                                e.preventDefault();
                                addGenreTag(genre.GenreID.toString(), genre.GenreName);
                                $('#genreSearchInput').val('');
                                suggestionsBox.hide();
                            });
                            suggestionsBox.append(suggestionItem);
                        });
                    } else {
                        suggestionsBox.hide();
                    }
                } else {
                    suggestionsBox.hide();
                }
            });

            $('.filter-genre-link').on('click', function(e) {
                e.preventDefault();
                const genreId = $(this).data('genre-id').toString();
                const genreName = $(this).data('genre-name');
                addGenreTag(genreId, genreName);
            });

            $('#browseAllGenresLink').on('click', function(e) {
                e.preventDefault();
                const genreSuggestions = $('#genreSuggestions');
                const filterTagsContainer = $('#filterTagsContainer');

                genreSuggestions.empty();
                filterTagsContainer.empty();
                selectedGenres.clear();

                genreSuggestions.show();
                allGenres.forEach(genre => {
                    const suggestionItem = $(`<a href="#">${genre.GenreName}</a>`);
                    suggestionItem.on('click', function(e) {
                        e.preventDefault();
                        addGenreTag(genre.GenreID.toString(), genre.GenreName);
                        $('#genreSearchInput').val('');
                        genreSuggestions.hide();
                    });
                    genreSuggestions.append(suggestionItem);
                });
                updateBooksByGenre();
            });
        });
    </script>
</body>
</html>
