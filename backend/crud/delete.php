<?php
include 'db_connect.php';
if (isset($_GET['deleteid'])) {
    $id = $_GET['deleteid'];

    // Using a prepared statement for consistency and security
    $sql = "DELETE FROM `crud` WHERE id=?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header('Location: read.php');
        exit();
    } else {
        die("Error: " . $stmt->error);
    }
}
?>