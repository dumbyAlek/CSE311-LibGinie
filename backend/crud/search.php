<?php
require_once 'db_config.php';

header('Content-Type: application/json');

$searchTerm = isset($_GET['q']) ? $_GET['q'] : '';
$books = [];

if (!empty($searchTerm)) {
    // The corrected SQL query to join through the tables.
    $sql = "SELECT DISTINCT
                b.ISBN,
                b.Title,
                b.CoverPicture,
                m.Name AS Author
            FROM
                Books b
            JOIN
                Author a ON b.AuthorID = a.AuthorID
            JOIN
                Registered r ON a.UserID = r.UserID
            JOIN
                Members m ON r.UserID = m.UserID
            WHERE
                b.Title LIKE ? OR m.Name LIKE ? OR b.ISBN LIKE ?
            LIMIT 50";

    $stmt = $con->prepare($sql);
    $likeTerm = '%' . $searchTerm . '%';
    $stmt->bind_param("sss", $likeTerm, $likeTerm, $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }

    $stmt->close();
}

$con->close();
echo json_encode($books);
?>