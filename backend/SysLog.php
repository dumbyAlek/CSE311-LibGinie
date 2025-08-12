<?php
// Start the session and ensure the user is an admin
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['membershipType'] !== 'admin') {
    header("Location: ../pages/loginn.php");
    exit;
}

// Define the log file path
$log_file = __DIR__ . '/../log.txt';

// Check if the log file exists and is readable
$log_entries = [];
if (file_exists($log_file) && is_readable($log_file)) {
    // Read the entire file into an array of lines
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // Reverse the array to show the most recent logs first
    $lines = array_reverse($lines);

    foreach ($lines as $line) {
        // Use a regular expression to parse the log entry
        if (preg_match('/^\[(.*?)\] \[UserID: (.*?)\] \[(.*?)\]: (.*)$/', $line, $matches)) {
            $log_entries[] = [
                'timestamp' => $matches[1],
                'user_id' => $matches[2],
                'action_type' => $matches[3],
                'description' => $matches[4],
            ];
        }
    }
} else {
    $error_message = "System log file not found or is unreadable. Please check if 'log.txt' exists in the root directory and has correct permissions.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibGinie - System Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet">
    <style>
        body { background-color: #eed9c4; font-family: 'Open Sans', sans-serif; }
        .container { max-width: 1200px; }
        h1, h3 { font-family: 'Montserrat', sans-serif; color: #7b3fbf; }
        .table-container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .table thead th { background-color: #7b3fbf; color: #fff; }
        .table tbody tr:hover { background-color: #f1f1f1; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="../pages/settings.php" class="btn btn-secondary">&larr; Back to Settings</a>
        </div>
        <h1 class="mb-4">System Logs</h1>
        <p class="mb-4 text-secondary">A record of all key actions performed by users and the system.</p>
        
        <div class="table-container">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User ID</th>
                                <th>Action Type</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($log_entries)): ?>
                                <?php foreach ($log_entries as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['timestamp']) ?></td>
                                    <td><?= htmlspecialchars($entry['user_id']) ?></td>
                                    <td><?= htmlspecialchars($entry['action_type']) ?></td>
                                    <td><?= htmlspecialchars($entry['description']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan='4' class='text-center'>No system logs found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>