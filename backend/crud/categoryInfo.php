<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$data = [];

// Handle POST requests
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? null;
} else {
    // Handle GET requests
    $action = $_GET['action'] ?? null;
}

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified.']);
    exit;
}

switch ($action) {
    case 'get_category_data':
        $isbn = $_GET['isbn'];
        $category = $_GET['category'];
        $result = null;

        try {
            switch ($category) {
                case 'Text Books':
                    $stmt = $con->prepare("SELECT Subject, Editions FROM TextBook WHERE ISBN = ?");
                    $stmt->bind_param("s", $isbn);
                    break;
                case 'Comics':
                    $stmt = $con->prepare("SELECT Artist, Studio FROM Comics WHERE ISBN = ?");
                    $stmt->bind_param("s", $isbn);
                    break;
                case 'Novels':
                    $stmt = $con->prepare("SELECT Narration FROM Novels WHERE ISBN = ?");
                    $stmt->bind_param("s", $isbn);
                    break;
                case 'Magazines':
                    $stmt = $con->prepare("SELECT Timeline FROM Magazines WHERE ISBN = ?");
                    $stmt->bind_param("s", $isbn);
                    break;
                default:
                    echo json_encode(['success' => true, 'data' => null]);
                    exit;
            }

            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();
                $result = $res->fetch_assoc();
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
            }
        } catch (mysqli_sql_exception $e) {
            echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}

$con->close();
?>