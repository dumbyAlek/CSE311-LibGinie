<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$genre_ids = isset($_GET['genre_ids']) ? $_GET['genre_ids'] : '';
$books = [];

// Determine the SQL query and parameters based on whether genre_ids are provided
if (!empty($genre_ids)) {
    // Sanitize and convert the comma-separated string of IDs to an array of integers
    $genre_ids_array = array_map('intval', explode(',', $genre_ids));
    
    // Create the placeholders for the IN clause dynamically
    $placeholders = implode(',', array_fill(0, count($genre_ids_array), '?'));

    $sql = "
        SELECT 
            b.ISBN, b.Title, b.CoverPicture
        FROM Books b
        JOIN Book_Genres bg ON b.ISBN = bg.ISBN
        WHERE bg.GenreID IN ($placeholders)
        GROUP BY b.ISBN
        ORDER BY b.Title
    ";

    $stmt = $con->prepare($sql);
    
    // Create a string of types for bind_param, all 'i' for integers
    $types = str_repeat('i', count($genre_ids_array));
    
    // Bind each genre ID as a parameter
    $stmt->bind_param($types, ...$genre_ids_array);

} else {
    // If no genres are selected, display all books
    $sql = "SELECT ISBN, Title, CoverPicture FROM Books ORDER BY Title LIMIT 50";
    $stmt = $con->prepare($sql);
}

// Execute the statement and fetch the results
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}

$stmt->close();
$con->close();

echo json_encode($books);
?>