<?php
// search_book_copies.php
require_once 'db_config.php';
header('Content-Type: application/json');

$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? ''; // New line to get status filter
$books = [];

// Base SQL for copies + book info. Added joins for Author Name.
$sql = "
    SELECT 
        bc.CopyID, 
        bc.ISBN, 
        b.Title, 
        b.CoverPicture,
        bc.Status,
        m.Name as AuthorName
    FROM BookCopy bc
    INNER JOIN Books b ON bc.ISBN = b.ISBN
    INNER JOIN Author a ON b.AuthorID = a.AuthorID
    INNER JOIN Members m ON a.UserID = m.UserID
    WHERE 1=1
";

$params = [];
$types = '';

// Add search by Book Title, ISBN, or Author Name
if (!empty($search_term)) {
    $sql .= " AND (b.Title LIKE ? OR b.ISBN LIKE ? OR m.Name LIKE ?)";
    $search_param = '%' . $search_term . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Add a condition for the status filter if one is selected
if (!empty($status_filter)) {
    $sql .= " AND bc.Status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql .= " ORDER BY b.Title, bc.CopyID";

$stmt = $con->prepare($sql);
if ($types) {
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