<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$search_term = $_GET['search'] ?? '';
$genres = $_GET['genres'] ?? [];
$years = $_GET['years'] ?? [];
$publishers = $_GET['publishers'] ?? [];
$authors = $_GET['authors'] ?? [];

$books = [];

// Base SQL query
$sql = "
    SELECT 
        b.ISBN, b.Title, b.CoverPicture
    FROM Books b
    LEFT JOIN Book_Genres bg ON b.ISBN = bg.ISBN
    LEFT JOIN Genres g ON bg.GenreID = g.GenreID
    LEFT JOIN Author a ON b.AuthorID = a.AuthorID
    LEFT JOIN Members m ON a.UserID = m.UserID
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

// Add author filter (correct way)
if (!empty($authors)) {
    $placeholders = implode(',', array_fill(0, count($authors), '?'));
    $sql .= " AND m.Name IN ($placeholders)";
    foreach ($authors as $author) {
        $params[] = $author;
        $types .= 's';
    }
}

// Group by ISBN to avoid duplicate books
$sql .= " GROUP BY b.ISBN ORDER BY b.Title";

// Prepare and execute the statement
$stmt = $con->prepare($sql);

if ($types) {
    // Correctly bind parameters
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
$stmt->close();
$con->close();

echo json_encode($books);
?>