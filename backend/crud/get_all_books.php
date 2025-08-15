<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$search_term = $_GET['search'] ?? '';
$genres = $_GET['genres'] ?? [];
$years = $_GET['years'] ?? [];
$publishers = $_GET['publishers'] ?? [];
$authors = $_GET['authors'] ?? [];
$orderby = $_GET['orderby'] ?? 'newarrivals'; // Default value for ordering

$books = [];

// Base SQL query with joins for filtering and sorting
$sql = "
    SELECT
        b.ISBN, b.Title, b.CoverPicture, b.PublishedYear,
        COALESCE(AVG(br.Rating), 0) AS avg_rating,
        COUNT(bo.BorrowID) AS borrow_count
    FROM Books b
    LEFT JOIN Book_Genres bg ON b.ISBN = bg.ISBN
    LEFT JOIN Genres g ON bg.GenreID = g.GenreID
    LEFT JOIN Author a ON b.AuthorID = a.AuthorID
    LEFT JOIN Members m ON a.UserID = m.UserID
    LEFT JOIN BookReviews br ON b.ISBN = br.ISBN
    LEFT JOIN BookCopy bc ON b.ISBN = bc.ISBN
    LEFT JOIN Borrow bo ON bc.CopyID = bo.CopyID
    WHERE 1=1
";

$params = [];
$types = '';

// Add search condition for Title, Author Name, and ISBN
if (!empty($search_term)) {
    $sql .= " AND (b.Title LIKE ? OR m.Name LIKE ? OR b.ISBN LIKE ?)";
    $search_param = '%' . $search_term . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Add genre filter
if (!empty($genres)) {
    $placeholders = implode(',', array_fill(0, count($genres), '?'));
    $sql .= " AND g.GenreName IN ($placeholders)";
    foreach ($genres as $genre) {
        $params[] = $genre;
        $types .= 's';
    }
}

// Add year filter
if (!empty($years)) {
    $placeholders = implode(',', array_fill(0, count($years), '?'));
    $sql .= " AND b.PublishedYear IN ($placeholders)";
    foreach ($years as $year) {
        $params[] = $year;
        $types .= 'i';
    }
}

// Add publisher filter
if (!empty($publishers)) {
    $placeholders = implode(',', array_fill(0, count($publishers), '?'));
    $sql .= " AND b.Publisher IN ($placeholders)";
    foreach ($publishers as $publisher) {
        $params[] = $publisher;
        $types .= 's';
    }
}

// Add author filter
if (!empty($authors)) {
    $placeholders = implode(',', array_fill(0, count($authors), '?'));
    $sql .= " AND m.Name IN ($placeholders)";
    foreach ($authors as $author) {
        $params[] = $author;
        $types .= 's';
    }
}

// Group by ISBN to avoid duplicate books and enable sorting on aggregate data
$sql .= " GROUP BY b.ISBN";

// The dynamic ORDER BY logic based on the user's selection
switch ($orderby) {
    case 'trending':
        $sql .= " ORDER BY borrow_count DESC, b.Title ASC";
        break;
    case 'toprated':
        $sql .= " ORDER BY avg_rating DESC, b.Title ASC";
        break;
    case 'author_name':
        $sql .= " ORDER BY m.Name ASC, b.Title ASC";
        break;
    case 'book_title':
        $sql .= " ORDER BY b.Title ASC";
        break;
    case 'newarrivals':
    default:
        $sql .= " ORDER BY b.PublishedYear DESC, b.Title ASC";
        break;
}

// Prepare and execute the statement
$stmt = $con->prepare($sql);
if ($stmt === false) {
    // Handle prepare statement error
    http_response_code(500);
    echo json_encode(["error" => "Failed to prepare statement: " . $con->error]);
    exit;
}

if ($types) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
} else {
    // Handle execute statement error
    http_response_code(500);
    echo json_encode(["error" => "Failed to execute statement: " . $stmt->error]);
    exit;
}

$stmt->close();
$con->close();

echo json_encode($books);
?>