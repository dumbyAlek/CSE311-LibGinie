<?php
require_once 'db_config.php';

header('Content-Type: application/json');

$isbn = $_GET['isbn'] ?? null;

if (!$isbn) {
    echo json_encode(['success' => false, 'message' => 'ISBN is required.']);
    exit;
}

try {
    $stmt = $con->prepare("SELECT COUNT(*) FROM BookCopy WHERE ISBN = ?");
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    $con->close();

    echo json_encode(['success' => true, 'copies' => $count]);

} catch (Exception $e) {
    $con->close();
    echo json_encode(['success' => false, 'message' => 'Error fetching copy count: ' . $e->getMessage()]);
}
?>