<?php
// Start the session
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

// Include your database configuration and functions
require_once '../backend/crud/db_config.php';

// Check if an ISBN is provided in the URL
if (!isset($_GET['isbn']) || empty($_GET['isbn'])) {
    header("Location: home.php"); // Redirect if no ISBN is provided
    exit;
}

$isbn = $_GET['isbn'];
$user_id = $_SESSION['UserID'];
$user_role = $_SESSION['membershipType'] ?? 'guest';
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// --- Database Logic ---

/**
 * @param mysqli $con
 * @param string $isbn
 * @return float|int
 */
function getAverageRating($con, $isbn) {
    $sql = "SELECT AVG(Rating) as avg_rating FROM BookReviews WHERE ISBN = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['avg_rating'] ?? 0;
}

/**
 * @param mysqli $con
 * @param string $isbn
 * @return array|null
 */
function getBookDetails($con, $isbn) {
    $sql = "
    SELECT
        b.ISBN, b.Title, b.CoverPicture, b.PublishedYear, b.Publisher, b.Description, b.Category,
        m.Name AS AuthorName,
        GROUP_CONCAT(g.GenreName SEPARATOR ', ') AS Genres
    FROM Books b
    LEFT JOIN Author a ON b.AuthorID = a.AuthorID
    LEFT JOIN Members m ON a.UserID = m.UserID
    LEFT JOIN Book_Genres bg ON b.ISBN = bg.ISBN
    LEFT JOIN Genres g ON bg.GenreID = g.GenreID
    WHERE b.ISBN = ?
    GROUP BY b.ISBN";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();
    return $book;
}

/**
 * @param mysqli $con
 * @param string $isbn
 * @return array
 */
function getBookReviews($con, $isbn) {
    $sql = "
    SELECT
        br.ReviewText, br.Rating, br.ReviewTime, m.Name AS UserName
    FROM BookReviews br
    JOIN Members m ON br.UserID = m.UserID
    WHERE br.ISBN = ?
    ORDER BY br.ReviewTime DESC";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
    $stmt->close();
    return $reviews;
}

/**
 * @param mysqli $con
 * @param int $user_id
 * @param string $isbn
 * @return array
 */
function getUserInteraction($con, $user_id, $isbn) {
    $sql = "SELECT IsFavorite, InWishlist, IsRead FROM BookInteractions WHERE UserID = ? AND ISBN = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("is", $user_id, $isbn);
    $stmt->execute();
    $result = $stmt->get_result();
    $interaction = $result->fetch_assoc();
    $stmt->close();
    return $interaction ? $interaction : ['IsFavorite' => false, 'InWishlist' => false, 'IsRead' => false];
}

// Execute database queries
$book = getBookDetails($con, $isbn);
if (!$book) {
    // If the book doesn't exist, redirect
    header("Location: home.php");
    exit;
}

$reviews = getBookReviews($con, $isbn);
$average_rating = getAverageRating($con, $isbn);

$user_interaction = [];
// Check user interaction only if logged in
if (!$is_guest) {
    $user_interaction = getUserInteraction($con, $user_id, $isbn);
    // Update the BookViews table
    $sql = "INSERT INTO BookInteractions (UserID, ISBN, LastViewed) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE LastViewed = NOW()";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("is", $user_id, $isbn);
    $stmt->execute();
    $stmt->close();
} else {
    $user_interaction = ['IsFavorite' => false, 'InWishlist' => false, 'IsRead' => false];
}

$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LibGinie - <?php echo htmlspecialchars($book['Title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
        
        /* The sidebar styling is copied from the provided code */
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
        /* End of provided sidebar styling */

        .book-details-container {
            margin-top: 50px;
            padding: 20px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .book-details-container.dark-theme {
            background-color: #222;
            color: #eee;
        }

        .book-cover-img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .book-info h1 {
            font-family: 'Montserrat', sans-serif;
            color: #7b3fbf;
        }
        
        .book-rating .fa-star {
            color: #ccc;
        }
        
        .book-rating .fa-star.checked {
            color: orange;
        }

        /* New CSS to apply star color for reviews */
        .review-card .book-rating .fa-star.checked {
            color: orange;
        }

        .action-buttons .btn {
            width: 100%;
            margin-bottom: 10px;
        }

        .review-section {
            margin-top: 40px;
        }
        
        .review-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }
        
        .review-card.dark-theme {
            background-color: #333;
            color: #eee;
            border-color: #555;
        }
        
        .review-card strong {
            color: #7b3fbf;
        }

        .review-form-container {
            margin-top: 20px;
            padding: 20px;
            border-radius: 8px;
            background-color: #f1f1f1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .review-form-container.dark-theme {
            background-color: #2a2a2a;
            color: #eee;
        }
        
        .rating-stars {
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .rating-stars .fa-star {
            color: #ddd;
            transition: color 0.2s;
        }
        
        .rating-stars .fa-star:hover,
        .rating-stars .fa-star.selected {
            color: gold;
        }

    </style>
</head>
<body>

    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>
    
    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <main class="container mt-4">
            <div class="book-details-container">
                <div class="row">
                    <div class="col-md-4 text-center mb-3 mb-md-0">
                        <?php if ($book['CoverPicture']): ?>
                            <img src="<?php echo htmlspecialchars($book['CoverPicture']); ?>" alt="Book Cover" class="book-cover-img" />
                        <?php else: ?>
                            <img src="../images/no-cover.png" alt="No cover available" class="book-cover-img" />
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8">
                        <div class="book-info">
                            <h1 class="mb-2"><?php echo htmlspecialchars($book['Title']); ?></h1>
                            <p><strong>Author:</strong> <?php echo htmlspecialchars($book['AuthorName'] ?? 'Unknown'); ?></p>
                            <p><strong>Publisher:</strong> <?php echo htmlspecialchars($book['Publisher'] ?? 'Unknown'); ?></p>
                            <p><strong>Published Year:</strong> <?php echo htmlspecialchars($book['PublishedYear'] ?? 'N/A'); ?></p>
                            <p><strong>Genres:</strong> <?php echo htmlspecialchars($book['Genres'] ?? 'N/A'); ?></p>
                            <div class="book-rating mb-3">
                                <?php
                                $rating = round($average_rating);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo '<span class="fa fa-star ' . ($i <= $rating ? 'checked' : '') . '"></span>';
                                }
                                echo " (" . number_format($average_rating, 1) . ")";
                                ?>
                            </div>
                            <p class="mb-4"><?php echo htmlspecialchars($book['Description'] ?? 'No description available.'); ?></p>
                        </div>
                        
                        <?php if (!$is_guest): ?>
                        <div class="action-buttons row">
                            <div class="col-md-4">
                                <button id="readBtn" class="btn btn-secondary" data-status="<?php echo $user_interaction['IsRead'] ? 'true' : 'false'; ?>">
                                    <?php echo $user_interaction['IsRead'] ? '✅ Read' : 'Mark as Read'; ?>
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button id="wishlistBtn" class="btn btn-secondary" data-status="<?php echo $user_interaction['InWishlist'] ? 'true' : 'false'; ?>">
                                    <?php echo $user_interaction['InWishlist'] ? '✅ Wishlisted' : 'Wishlist'; ?>
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button id="favoriteBtn" class="btn btn-secondary" data-status="<?php echo $user_interaction['IsFavorite'] ? 'true' : 'false'; ?>">
                                    <?php echo $user_interaction['IsFavorite'] ? '✅ Favorited' : 'Favorite'; ?>
                                </button>
                            </div>
                            <div class="col-md-6 mt-2">
                                <a href="borrow_page.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>" class="btn btn-primary">Borrow</a>
                            </div>
                            <div class="col-md-6 mt-2">
                                <a href="reserve_page.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>" class="btn btn-info">Reserve</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="review-section">
                <h2 class="mb-4">Reviews</h2>

                <?php if (!$is_guest): ?>
                <div class="review-form-container mb-5">
                    <h4 class="mb-3">Leave a Review</h4>
                    <form id="reviewForm">
                        <div class="mb-3">
                            <label class="form-label">Your Rating:</label>
                            <div class="rating-stars" id="ratingStars">
                                <i class="fa fa-star" data-rating="1"></i>
                                <i class="fa fa-star" data-rating="2"></i>
                                <i class="fa fa-star" data-rating="3"></i>
                                <i class="fa fa-star" data-rating="4"></i>
                                <i class="fa fa-star" data-rating="5"></i>
                                <input type="hidden" name="rating" id="ratingInput">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="reviewText" class="form-label">Your Review:</label>
                            <textarea class="form-control" id="reviewText" name="reviewText" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit Review</button>
                        <div id="reviewMessage" class="mt-3"></div>
                    </form>
                </div>
                <?php endif; ?>

                <div id="reviewsList">
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <strong><?php echo htmlspecialchars($review['UserName']); ?></strong>
                                <span class="float-end text-muted"><?php echo htmlspecialchars($review['ReviewTime']); ?></span>
                                <div class="book-rating">
                                    <?php
                                    $review_rating = (int)$review['Rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo '<span class="fa fa-star ' . ($i <= $review_rating ? 'checked' : '') . '"></span>';
                                    }
                                    ?>
                                </div>
                                <p class="mt-2"><?php echo htmlspecialchars($review['ReviewText']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No reviews yet. Be the first to review this book!</p>
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

        // --- AJAX for user interaction buttons ---
        function updateBookStatus(action) {
            const isbn = "<?php echo htmlspecialchars($isbn); ?>";
            const userId = "<?php echo htmlspecialchars($user_id); ?>";
            const btnId = `#${action}Btn`;
            const button = $(btnId);
            const status = button.data('status') === 'true';

            $.ajax({
                url: '../backend/book_action.php', // This file will handle the update
                type: 'POST',
                data: {
                    isbn: isbn,
                    userId: parseInt(userId),
                    action: action,
                    status: !status // Toggle the status
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            button.data('status', !status);
                            if (action === 'read') {
                                button.text(!status ? '✅ Read' : 'Mark as Read');
                            } else if (action === 'wishlist') {
                                button.text(!status ? '✅ Wishlisted' : 'Wishlist');
                            } else if (action === 'favorite') {
                                button.text(!status ? '✅ Favorited' : 'Favorite');
                            }
                        } else {
                            alert('Error updating status: ' + data.message);
                        }
                    } catch (e) {
                        //alert('An unexpected error occurred: ' + response);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }

        $(document).ready(function() {
            $('#readBtn').on('click', function() { updateBookStatus('read'); });
            $('#wishlistBtn').on('click', function() { updateBookStatus('wishlist'); });
            $('#favoriteBtn').on('click', function() { updateBookStatus('favorite'); });
        });

        // --- Review Form and Star Rating ---
        const stars = document.querySelectorAll('.rating-stars .fa-star');
        const ratingInput = document.getElementById('ratingInput');

        stars.forEach(star => {
            star.addEventListener('click', function() {
                const ratingValue = parseInt(this.dataset.rating);
                ratingInput.value = ratingValue;

                stars.forEach(s => s.classList.remove('selected'));
                for (let i = 0; i < ratingValue; i++) {
                    stars[i].classList.add('selected');
                }
            });
        });

        $('#reviewForm').on('submit', function(e) {
            e.preventDefault();
            const isbn = "<?php echo htmlspecialchars($isbn); ?>";
            const userId = "<?php echo htmlspecialchars($user_id); ?>";
            const reviewText = $('#reviewText').val();
            const selectedRating = parseInt(ratingInput.value);

            if (isNaN(selectedRating) || selectedRating < 1 || selectedRating > 5) {
                alert('Please select a rating before submitting.');
                return;
            }

           $.ajax({
                url: '../backend/submit_review.php',
                type: 'POST',
                dataType: 'json', // ✅ Let jQuery parse JSON
                data: {
                    isbn: isbn,
                    rating: selectedRating,
                    reviewText: reviewText
                },
                success: function(data) { // ✅ Already an object
                    const reviewMessage = $('#reviewMessage');
                    if (data.success) {
                        reviewMessage.html('<div class="alert alert-success">' + data.message + '</div>');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        reviewMessage.html('<div class="alert alert-danger">Error: ' + data.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#reviewMessage').html('<div class="alert alert-danger">AJAX error: ' + error + '</div>');
                }
            });
        });
    </script>
</body>
</html>