<?php
session_start();

// Ensure only logged-in admins can access
if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    $_SESSION['membershipType'] !== 'admin'
) {
    header("Location: ../pages/loginn.php");
    exit;
}

require_once 'crud/db_config.php';
require_once 'crud/log_action.php';

// Default backup directory
$DEFAULT_BACKUP_DIR = __DIR__ . '/../backups/';
if (!is_dir($DEFAULT_BACKUP_DIR)) {
    mkdir($DEFAULT_BACKUP_DIR, 0755, true);
}

/**
 * Export database tables to a SQL file.
 */
function export_data($con, $table_names, $filename, $custom_dir = null)
{
    global $DEFAULT_BACKUP_DIR;

    $backup_dir = ($custom_dir && is_dir($custom_dir) && is_writable($custom_dir))
        ? $custom_dir
        : $DEFAULT_BACKUP_DIR;

    $file_path = rtrim($backup_dir, '/') . '/' . $filename;
    $handle = fopen($file_path, 'w');
    if (!$handle) {
        return "Error: Could not open file for writing. Check directory permissions.";
    }

    $con->query("SET NAMES 'utf8'");

    foreach ($table_names as $table) {
        $result = $con->query("SELECT * FROM `$table`");
        if ($result && $result->num_rows > 0) {
            $columns = [];
            while ($field = $result->fetch_field()) {
                $columns[] = "`{$field->name}`";
            }
            $column_list = implode(', ', $columns);

            while ($row = $result->fetch_assoc()) {
                $values = [];
                foreach ($row as $value) {
                    $values[] = ($value === '' || $value === null)
                        ? "NULL"
                        : "'" . $con->real_escape_string($value) . "'";
                }
                fwrite($handle, "INSERT INTO `$table` ($column_list) VALUES (" . implode(', ', $values) . ");\n");
            }
        }
    }

    fclose($handle);
    return true;
}

/**
 * Import SQL file into the database.
 */
function import_data($con, $file)
{
    $tables_to_truncate = [
        'BooksAdded', 'MaintenanceLog', 'Borrow', 'Reservation',
        'BookInteractions', 'BookReviews', 'Book_Genres', 'Comics',
        'Novels', 'Magazines', 'TextBook', 'BookCopy', 'Books',
        'Author', 'Librarian', 'Admin', 'Teacher', 'Student',
        'General', 'Guest', 'Registered', 'Employee', 'Members',
        'LoginCredentials', 'Shelf', 'Library_Sections', 'Genres'
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return "File upload error: " . $file['error'];
    }

    $sql_content = file_get_contents($file['tmp_name']);
    if ($sql_content === false) {
        return "Error: Could not read uploaded file.";
    }

    $queries = explode(';', $sql_content);

    $con->begin_transaction();
    try {
        if (!$con->query("SET FOREIGN_KEY_CHECKS = 0;")) {
            throw new Exception("Failed to disable foreign key checks: " . $con->error);
        }

        foreach ($tables_to_truncate as $table) {
            $con->query("TRUNCATE TABLE `$table`");
            if ($con->error) {
                throw new Exception("SQL error: {$con->error} while truncating $table");
            }
        }

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '' || str_starts_with($query, '--') || str_starts_with($query, '#')) {
                continue;
            }
            $con->query($query);
            if ($con->error) {
                throw new Exception("SQL error: {$con->error} in query: $query");
            }
        }

        if (!$con->query("SET FOREIGN_KEY_CHECKS = 1;")) {
            throw new Exception("Failed to re-enable foreign key checks: " . $con->error);
        }

        $con->commit();
        return true;
    } catch (Exception $e) {
        $con->rollback();
        $con->query("SET FOREIGN_KEY_CHECKS = 1;");
        return "Error importing data: " . $e->getMessage();
    }
}

// Table presets for exports
$EXPORT_PRESETS = [
    'books' => [
        'tables' => [
            'Library_Sections', 'Author', 'Genres',
            'Books', 'TextBook', 'Comics', 'Novels', 'Magazines',
            'Book_Genres', 'BookCopy', 'BookReviews', 'BookInteractions'
        ],
        'filename' => 'books_backup_',
        'desc' => 'Admin exported books data.'
    ],
    'users' => [
        'tables' => [
            'Members', 'Registered', 'Employee', 'Guest',
            'General', 'Student', 'Teacher', 'Author',
            'Admin', 'Librarian', 'LoginCredentials'
        ],
        'filename' => 'users_backup_',
        'desc' => 'Admin exported users data.'
    ],
    'sections' => [
        'tables' => ['Library_Sections', 'Shelf'],
        'filename' => 'sections_backup_',
        'desc' => 'Admin exported sections and shelves data.'
    ],
    'everything' => [
        'tables' => [
            'Library_Sections', 'Shelf',
            'Members', 'Registered', 'Employee', 'Guest',
            'General', 'Student', 'Teacher', 'Author',
            'Admin', 'Librarian', 'LoginCredentials',
            'Genres',
            'Books', 'TextBook', 'Comics', 'Novels', 'Magazines',
            'Book_Genres', 'BookCopy', 'BookReviews', 'BookInteractions',
            'Reservation', 'Borrow',
            'MaintenanceLog', 'BooksAdded'
        ],
        'filename' => 'full_backup_',
        'desc' => 'Admin created a full database backup.'
    ]
];

// Handle POST requests
$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $custom_location = $_POST['custom_location'] ?? null;
    $user_id = $_SESSION['UserID'];

    if (isset($_POST['export']) && isset($EXPORT_PRESETS[$_POST['export']])) {
        $preset = $EXPORT_PRESETS[$_POST['export']];
        $filename = $preset['filename'] . date('Y-m-d-H-i-s') . '.sql';
        $result = export_data($con, $preset['tables'], $filename, $custom_location);

        if ($result === true) {
            $message = ucfirst($_POST['export']) . " data exported successfully!";
            $message_type = 'success';
            log_action($user_id, 'Data Export', $preset['desc']);
        } else {
            $message = "Error exporting data: " . $result;
            $message_type = 'danger';
        }
    }

    if (isset($_FILES['import_file'])) {
        $result = import_data($con, $_FILES['import_file']);
        if ($result === true) {
            $message = "Data imported successfully!";
            $message_type = 'success';
            log_action($user_id, 'Data Import', 'Admin imported data from a SQL file.');
        } else {
            $message = $result;
            $message_type = 'danger';
        }
    }
}

$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibGinie - Backup & Restore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet">
    <style>
        body { background-color: #eed9c4; font-family: 'Open Sans', sans-serif; }
        .container { max-width: 900px; }
        h1, h2 { font-family: 'Montserrat', sans-serif; color: #7b3fbf; }
        .card { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .btn-custom { background-color: #7b3fbf; color: white; }
        .btn-custom:hover { background-color: #5d2e9b; }
        .custom-location-group { display: none; }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="../pages/settings.php" class="btn btn-secondary">&larr; Back to Settings</a>
    </div>
    <h1 class="text-center mb-4">Backup & Restore</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Export Data</h2>
        <p>Select the data to export as a <strong>.sql</strong> file.</p>
        <form action="" method="POST">
            <div class="mb-3">
                <label class="form-label">Save Location</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="location_type" id="exportDefaultLocation" value="default" checked>
                    <label class="form-check-label" for="exportDefaultLocation">Default Location</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="location_type" id="exportCustomLocation" value="custom">
                    <label class="form-check-label" for="exportCustomLocation">Custom Location</label>
                </div>
                <div id="exportCustomLocationGroup" class="custom-location-group">
                    <input type="text" class="form-control" name="custom_location" placeholder="Enter custom path (e.g., C:/Users/Name/Backups)">
                </div>
            </div>
            <button type="submit" name="export" value="books" class="btn btn-custom w-100 mb-2">Export Books</button>
            <button type="submit" name="export" value="users" class="btn btn-custom w-100 mb-2">Export Users</button>
            <button type="submit" name="export" value="sections" class="btn btn-custom w-100 mb-2">Export Sections & Shelves</button>
            <button type="submit" name="export" value="everything" class="btn btn-danger w-100">Export Everything</button>
        </form>
    </div>

    <div class="card">
        <h2>Import Data</h2>
        <p>Select a <strong>.sql</strong> file to import. This will overwrite existing data. <strong>Use with caution!</strong></p>
        <form action="" method="POST" enctype="multipart/form-data" onsubmit="return confirm('WARNING: This will overwrite existing records. Continue?');">
            <div class="mb-3">
                <label for="import_file" class="form-label">Select .sql file</label>
                <input class="form-control" type="file" id="import_file" name="import_file" accept=".sql" required>
            </div>
            <button type="submit" class="btn btn-warning w-100">Import Data</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const exportCustomRadio = document.getElementById('exportCustomLocation');
    const exportDefaultRadio = document.getElementById('exportDefaultLocation');
    const exportCustomGroup = document.getElementById('exportCustomLocationGroup');

    function toggleCustomLocation() {
        exportCustomGroup.style.display = exportCustomRadio.checked ? 'block' : 'none';
    }

    exportCustomRadio.addEventListener('change', toggleCustomLocation);
    exportDefaultRadio.addEventListener('change', toggleCustomLocation);
    toggleCustomLocation();
});
</script>
</body>
</html>
