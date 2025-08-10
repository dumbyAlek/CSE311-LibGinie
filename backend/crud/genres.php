<?php
require_once 'db_config.php';
header('Content-Type: application/json');

try {
    $stmt = $con->prepare("SELECT GenreName FROM Genres ORDER BY GenreName");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $genres = [];
    while ($row = $result->fetch_assoc()) {
        $genres[] = $row['GenreName'];
    }
    
    echo json_encode($genres);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
$con->close();
?>