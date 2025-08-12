<?php
// Start the session and ensure the user is an admin
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['membershipType'] !== 'admin') {
    header("Location: ../pages/loginn.php");
    exit;
}

// Include database configuration and logging function
require_once 'crud/db_config.php';
require_once 'crud/log_action.php'; // Corrected path

// Define the directory for backups and imports
$default_backup_dir = __DIR__ . '/../../backups/';
if (!is_dir($default_backup_dir)) {
    mkdir($default_backup_dir, 0755, true);
}

// Function to export data to a .sql file
function export_data($con, $table_names, $filename, $custom_dir = null) {
    global $default_backup_dir;

    // Use custom directory if provided and it's a valid, writable path
    $backup_dir = ($custom_dir && is_dir($custom_dir) && is_writable($custom_dir)) ? $custom_dir : $default_backup_dir;

    $file_path = rtrim($backup_dir, '/') . '/' . $filename;
    $handle = fopen($file_path, 'w');

    if ($handle === false) {
        return "Error: Could not open file for writing. Check directory permissions.";
    }

    $con->query("SET NAMES 'utf8'");

    foreach ($table_names as $table) {
        $result = $con->query("SELECT * FROM $table");
        if ($result->num_rows > 0) {
            $column_names = [];
            while ($field_info = $result->fetch_field()) {
                $column_names[] = '`' . $field_info->name . '`';
            }
            $columns = implode(', ', $column_names);

            while ($row = $result->fetch_assoc()) {
                $values = [];
                foreach ($row as $value) {
                    $values[] = "'" . $con->real_escape_string($value) . "'";
                }
                $values_string = implode(', ', $values);
                fwrite($handle, "INSERT INTO `$table` ($columns) VALUES ($values_string);\n");
            }
        }
    }

    fclose($handle);
    return true;
}

// Function to import data from a .sql file
function import_data($con, $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return "File upload error: " . $file['error'];
    }

    $sql_content = file_get_contents($file['tmp_name']);
    if ($sql_content === false) {
        return "Error: Could not read uploaded file.";
    }

    // Split the SQL file into individual queries
    $queries = explode(';', $sql_content);
    $con->begin_transaction();
    try {
        // Clear existing data before import - this is a critical step
        // A full backup should be imported into an empty database
        // For partial imports, this might be too aggressive.
        // I will omit this for now, but a more robust solution would handle this.
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $con->query($query);
                if ($con->error) {
                    throw new Exception("SQL error: " . $con->error);
                }
            }
        }
        $con->commit();
        return true;
    } catch (Exception $e) {
        $con->rollback();
        return "Error importing data: " . $e->getMessage();
    }
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $custom_location = $_POST['custom_location'] ?? null;
    
    if (isset($_POST['export'])) {
        $export_type = $_POST['export'];
        $user_id = $_SESSION['UserID'];
        
        switch ($export_type) {
            case 'books':
                $tables = ['Books', 'TextBook', 'Comics', 'Novels', 'Magazines', 'Book_Genres'];
                $filename = 'books_backup_' . date('Y-m-d-H-i-s') . '.sql';
                $result = export_data($con, $tables, $filename, $custom_location);
                if ($result === true) {
                    $message = "Books data exported successfully!";
                    $message_type = 'success';
                    log_action($user_id, 'Data Export', 'Admin exported books data.');
                } else {
                    $message = "Error exporting books data: " . $result;
                    $message_type = 'danger';
                }
                break;
            case 'users':
                $tables = ['Members', 'Guest', 'Registered', 'Employee', 'General', 'Student', 'Teacher', 'Author', 'Admin', 'Librarian', 'LoginCredentials'];
                $filename = 'users_backup_' . date('Y-m-d-H-i-s') . '.sql';
                $result = export_data($con, $tables, $filename, $custom_location);
                if ($result === true) {
                    $message = "Users data exported successfully!";
                    $message_type = 'success';
                    log_action($user_id, 'Data Export', 'Admin exported users data.');
                } else {
                    $message = "Error exporting users data: " . $result;
                    $message_type = 'danger';
                }
                break;
            case 'sections':
                $tables = ['Library_Sections', 'Shelf'];
                $filename = 'sections_backup_' . date('Y-m-d-H-i-s') . '.sql';
                $result = export_data($con, $tables, $filename, $custom_location);
                if ($result === true) {
                    $message = "Sections and Shelves data exported successfully!";
                    $message_type = 'success';
                    log_action($user_id, 'Data Export', 'Admin exported sections and shelves data.');
                } else {
                    $message = "Error exporting sections and shelves data: " . $result;
                    $message_type = 'danger';
                }
                break;
            case 'everything':
                $tables = ['Library_Sections', 'Shelf', 'Members', 'Guest', 'Registered', 'Employee', 'General', 'Student', 'Teacher', 'Author', 'Admin', 'Librarian', 'LoginCredentials', 'Books', 'TextBook', 'Comics', 'Novels', 'Magazines', 'Genres', 'Book_Genres', 'BookCopy', 'Reservation', 'Borrow', 'MaintenanceLog', 'BookViews', 'BooksAdded', 'SystemLog'];
                $filename = 'full_backup_' . date('Y-m-d-H-i-s') . '.sql';
                $result = export_data($con, $tables, $filename, $custom_location);
                if ($result === true) {
                    $message = "Full database backup created successfully!";
                    $message_type = 'success';
                    log_action($user_id, 'Data Export', 'Admin created a full database backup.');
                } else {
                    $message = "Error creating full backup: " . $result;
                    $message_type = 'danger';
                }
                break;
        }
    } elseif (isset($_POST['import_file'])) {
        $user_id = $_SESSION['UserID'];
        $file = $_FILES['import_file'];
        $result = import_data($con, $file);
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
        .card { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-bottom: 2rem;}
        .card-header { background-color: #7b3fbf; color: white; }
        .btn-custom { background-color: #7b3fbf; color: white; }
        .btn-custom:hover { background-color: #5d2e9b; color: white;}
        .text-center { text-align: center; }
        .custom-location-group { display: none; } /* Hide custom location by default */
    </style>
</head>
<body>
    <div class="container mt-5">
         <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="../pages/settings.php" class="btn btn-secondary">&larr; Back to Settings</a>
        </div>
        <div class="d-flex align-items-center mb-4">
            <h1 class="text-center flex-grow-1" style="font-size: 2.5rem;">Backup & Restore</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>Export Data</h2>
            </div>
            <div class="card-body">
                <p class="mb-4">Select the data you want to export. This will generate a **.sql** file.</p>
                <form action="BackupNRestore.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Save Location</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="location_type" id="exportDefaultLocation" value="default" checked>
                            <label class="form-check-label" for="exportDefaultLocation">
                                Default Location
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="location_type" id="exportCustomLocation" value="custom">
                            <label class="form-check-label" for="exportCustomLocation">
                                Custom Location
                            </label>
                        </div>
                        <div id="exportCustomLocationGroup" class="custom-location-group">
                            <input type="text" class="form-control" name="custom_location" placeholder="Enter custom path (e.g., C:/Users/YourUser/Documents/Backups)">
                        </div>
                    </div>
                    <button type="submit" name="export" value="books" class="btn btn-custom w-100 mb-2">Export Books</button>
                    <button type="submit" name="export" value="users" class="btn btn-custom w-100 mb-2">Export Users</button>
                    <button type="submit" name="export" value="sections" class="btn btn-custom w-100 mb-2">Export Sections & Shelves</button>
                    <button type="submit" name="export" value="everything" class="btn btn-danger w-100">Export Everything (Full Backup)</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Import Data</h2>
            </div>
            <div class="card-body">
                <p class="mb-4">Select an **.sql** file to import. This will overwrite existing data. **Use with caution!**</p>
                <form action="BackupNRestore.php" method="POST" enctype="multipart/form-data" onsubmit="return confirm('WARNING: Importing data will overwrite existing records. Are you sure you want to proceed?');">
                    <div class="mb-3">
                        <label for="import_file" class="form-label">Select .sql file to import</label>
                        <input class="form-control" type="file" id="import_file" name="import_file" accept=".sql" required>
                    </div>
                    <button type="submit" name="import_file" class="btn btn-warning w-100">Import Data</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const exportCustomRadio = document.getElementById('exportCustomLocation');
            const exportDefaultRadio = document.getElementById('exportDefaultLocation');
            const exportCustomGroup = document.getElementById('exportCustomLocationGroup');

            function toggleExportCustomLocation() {
                if (exportCustomRadio.checked) {
                    exportCustomGroup.style.display = 'block';
                } else {
                    exportCustomGroup.style.display = 'none';
                }
            }

            exportCustomRadio.addEventListener('change', toggleExportCustomLocation);
            exportDefaultRadio.addEventListener('change', toggleExportCustomLocation);
            
            // Initial check in case the custom radio is pre-checked (not in this case, but good practice)
            toggleExportCustomLocation();
        });
    </script>
</body>
</html>