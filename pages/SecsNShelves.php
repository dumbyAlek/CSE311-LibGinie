<?php
// SecsNShelves.php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['membershipType'] !== 'admin') {
    header("Location: ../pages/loginn.php");
    exit;
}

// Include your database configuration
require_once '../backend/crud/db_config.php';
require_once '../backend/crud/log_action.php';

$user_role = $_SESSION['membershipType'];
$is_librarian = ($user_role === 'librarian');
$is_guest = ($user_role === 'guest');

$message = '';
$error = '';

// Handle form submission for adding a new section
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_section'])) {
    $sectionName = trim($_POST['section_name']);

    if (!empty($sectionName)) {
        $sql = "INSERT INTO Library_Sections (Name) VALUES (?)";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $sectionName);

        if ($stmt->execute()) {
            log_action($_SESSION['UserID'], 'Sections and Shelved', 'User ' . $_SESSION['user_name'] . ' added a new section.');
            $message = "Section '{$sectionName}' added successfully! ✅";
        } else {
            $error = "Error adding section: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Section name cannot be empty.";
    }
}

// Handle form submission for adding a new shelf
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shelf'])) {
    $sectionID = $_POST['section_id'];
    $shelfTopic = trim($_POST['shelf_topic']);

    if (!empty($shelfTopic) && !empty($sectionID)) {
        $sql = "INSERT INTO Shelf (SectionID, Topic) VALUES (?, ?)";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("is", $sectionID, $shelfTopic);

        if ($stmt->execute()) {
            log_action($_SESSION['UserID'], 'Sections and Shelved', 'User ' . $_SESSION['user_name'] . ' added a new shelf.');
            $message = "Shelf '{$shelfTopic}' added to section ID '{$sectionID}' successfully! ✅";
        } else {
            $error = "Error adding shelf: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Shelf topic and section must be selected.";
    }
}

// Handle form submission for editing a section
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_section'])) {
    $sectionID = $_POST['edit_section_id'];
    $sectionName = trim($_POST['edit_section_name']);

    if (!empty($sectionName) && !empty($sectionID)) {
        $sql = "UPDATE Library_Sections SET Name = ? WHERE SectionID = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("si", $sectionName, $sectionID);

        if ($stmt->execute()) {
            log_action($_SESSION['UserID'], 'Sections and Shelved', 'User ' . $_SESSION['user_name'] . ' updated a section.');
            $message = "Section '{$sectionName}' (ID: {$sectionID}) updated successfully! ✅";
        } else {
            $error = "Error updating section: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Section name cannot be empty.";
    }
}

// Handle form submission for deleting a section
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_section'])) {
    $sectionID = $_POST['delete_section_id'];

    if (!empty($sectionID)) {
        $sql = "DELETE FROM Library_Sections WHERE SectionID = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $sectionID);

        if ($stmt->execute()) {
            log_action($_SESSION['UserID'], 'Sections and Shelved', 'User ' . $_SESSION['user_name'] . ' deleted a section.');
            $message = "Section with ID '{$sectionID}' and its shelves deleted successfully! 🗑️";
        } else {
            $error = "Error deleting section: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Invalid section ID.";
    }
}

// Handle form submission for editing a shelf
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_shelf'])) {
    $shelfID = $_POST['edit_shelf_id'];
    $shelfTopic = trim($_POST['edit_shelf_topic']);

    if (!empty($shelfTopic) && !empty($shelfID)) {
        $sql = "UPDATE Shelf SET Topic = ? WHERE ShelfID = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("si", $shelfTopic, $shelfID);

        if ($stmt->execute()) {
            log_action($_SESSION['UserID'], 'Sections and Shelved', 'User ' . $_SESSION['user_name'] . ' updated a shelf.');
            $message = "Shelf '{$shelfTopic}' (ID: {$shelfID}) updated successfully! ✅";
        } else {
            $error = "Error updating shelf: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Shelf topic cannot be empty.";
    }
}

// Handle form submission for deleting a shelf
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_shelf'])) {
    $shelfID = $_POST['delete_shelf_id'];

    if (!empty($shelfID)) {
        $sql = "DELETE FROM Shelf WHERE ShelfID = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $shelfID);

        if ($stmt->execute()) {
            log_action($_SESSION['UserID'], 'Sections and Shelved', 'User ' . $_SESSION['user_name'] . ' deleted a shelf.');
            $message = "Shelf with ID '{$shelfID}' deleted successfully! 🗑️";
        } else {
            $error = "Error deleting shelf: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Invalid shelf ID.";
    }
}

// Fetch all sections and their shelves for display
$sections = [];
$sql = "SELECT SectionID, Name FROM Library_Sections ORDER BY Name";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sections[$row['SectionID']] = [
            'name' => $row['Name'],
            'shelves' => []
        ];
    }
}

$sql_shelves = "SELECT ShelfID, SectionID, Topic FROM Shelf ORDER BY SectionID, Topic";
$result_shelves = $con->query($sql_shelves);

if ($result_shelves && $result_shelves->num_rows > 0) {
    while ($row = $result_shelves->fetch_assoc()) {
        if (isset($sections[$row['SectionID']])) {
            $sections[$row['SectionID']]['shelves'][] = $row;
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
    <title>Manage Sections and Shelves</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #eed9c4;
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        :root {
            --sidebar-width: 400px;
        }

        body.dark-theme {
            background-color: #121212;
            color: #eee;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            width: var(--sidebar-width);
            height: 100vh;
            background-image: url('../images/sidebar.jpg');
            background-size: cover;
            background-position: center;
            padding: 20px;
            color: white;
            overflow-y: auto;
            transition: transform 0.3s ease-in-out;
        }

        .sidebar.closed {
            transform: translateX(calc(-1 * var(--sidebar-width)));
        }

        .sidebar .logo {
            max-width: 200px;
            margin: 20px auto;
            display: block;
        }

        .sidebar.closed .logo {
            display: none;
        }

        .sidebar ul {
            list-style: none;
            padding-left: 0;
            margin-top: 30px;
        }

        .sidebar li {
            margin-bottom: 0.5rem;
        }

        .sidebar a {
            text-decoration: none;
            color: white;
            font-size: 1.1rem;
            padding: 8px 12px;
            display: block;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .sidebar .collapsible-header {
            font-size: 1.1rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 8px 12px;
            color: white;
            border-radius: 4px;
        }

        .sidebar ul.sublist {
            padding-left: 20px;
            margin-top: 5px;
            display: none;
        }

        .sidebar ul.sublist.show {
            display: block;
        }

        .sidebar-toggle-btn {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            width: 40px;
            height: 40px;
            background-color: #7b3fbf;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle-btn::before {
            content: "≡";
            color: white;
            font-size: 20px;
            transform: rotate(90deg);
        }

        .content-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s;
        }

        .sidebar.closed ~ .content-wrapper {
            margin-left: 0;
        }
        
        .disabled-link {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        @media (max-width: 767px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.closed {
                transform: translateX(-100%);
            }
            .content-wrapper {
                margin-left: 0 !important;
            }
        }
        
        .container {
            max-width: 900px;
            margin-top: 50px;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #7b3fbf;
            color: white;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.25rem;
            text-align: center;
        }
        .form-control, .btn {
            border-radius: 5px;
        }
        .btn-primary {
            background-color: #7b3fbf;
            border-color: #7b3fbf;
        }
        .btn-primary:hover {
            background-color: #632d99;
            border-color: #632d99;
        }
        .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .alert {
            margin-top: 20px;
        }
        .section-list-container {
            margin-top: 40px;
        }
        .section-header-title {
            font-family: 'Montserrat', sans-serif;
            color: #7b3fbf;
        }
        ul.shelf-list {
            list-style: none;
            padding-left: 20px;
        }
        .shelf-item {
            font-style: italic;
            color: #555;
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="container">
            <h2 class="text-center mb-4" style="font-family: 'Montserrat', sans-serif;">Sections & Shelves Management</h2>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Add New Section</div>
                        <div class="card-body">
                            <form method="POST" action="SecsNShelves.php">
                                <input type="hidden" name="add_section" value="1">
                                <div class="mb-3">
                                    <label for="section_name" class="form-label">Section Name</label>
                                    <input type="text" class="form-control" id="section_name" name="section_name" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Add Section</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Add New Shelf</div>
                        <div class="card-body">
                            <form method="POST" action="SecsNShelves.php">
                                <input type="hidden" name="add_shelf" value="1">
                                <div class="mb-3">
                                    <label for="section_id" class="form-label">Select Section</label>
                                    <select class="form-select" id="section_id" name="section_id" required>
                                        <option value="">-- Choose Section --</option>
                                        <?php foreach ($sections as $id => $data): ?>
                                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($data['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="shelf_topic" class="form-label">Shelf Topic</label>
                                    <input type="text" class="form-control" id="shelf_topic" name="shelf_topic" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Add Shelf</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <hr>
            
            <div class="section-list-container">
                <h3 class="text-center section-header-title mb-4">Existing Sections and Shelves</h3>
                        <div class="mb-4">
                            <input type="text" id="searchBar" class="form-control" placeholder="Search for sections or shelves...">
                        </div>
                <?php if (empty($sections)): ?>
                    <div class="alert alert-info text-center">No sections have been created yet.</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($sections as $id => $data): ?>
                            <div class="list-group-item">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($data['name']); ?> (ID: <?php echo $id; ?>)</h5>
                                    <?php if (!empty($data['shelves'])): ?>
                                        <ul class="shelf-list">
                                            <?php foreach ($data['shelves'] as $shelf): ?>
                                                <li class="shelf-item">
                                                    <span>Shelf ID: <?php echo $shelf['ShelfID']; ?> - Topic: <?php echo htmlspecialchars($shelf['Topic']); ?></span>
                                                    <div>
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editShelfModal" data-shelf-id="<?php echo $shelf['ShelfID']; ?>" data-shelf-topic="<?php echo htmlspecialchars($shelf['Topic']); ?>">Edit</button>
                                                        <form method="POST" action="SecsNShelves.php" class="d-inline">
                                                            <input type="hidden" name="delete_shelf" value="1">
                                                            <input type="hidden" name="delete_shelf_id" value="<?php echo $shelf['ShelfID']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this shelf?');">Delete</button>
                                                        </form>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <small class="text-muted">No shelves in this section.</small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editSectionModal" data-section-id="<?php echo $id; ?>" data-section-name="<?php echo htmlspecialchars($data['name']); ?>">Edit</button>
                                    <form method="POST" action="SecsNShelves.php" class="d-inline">
                                        <input type="hidden" name="delete_section" value="1">
                                        <input type="hidden" name="delete_section_id" value="<?php echo $id; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this section and all its shelves?');">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="SecsNShelves.php">
                    <div class="modal-body">
                        <input type="hidden" name="edit_section" value="1">
                        <input type="hidden" name="edit_section_id" id="edit_section_id">
                        <div class="mb-3">
                            <label for="edit_section_name" class="form-label">Section Name</label>
                            <input type="text" class="form-control" id="edit_section_name" name="edit_section_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editShelfModal" tabindex="-1" aria-labelledby="editShelfModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editShelfModalLabel">Edit Shelf</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="SecsNShelves.php">
                    <div class="modal-body">
                        <input type="hidden" name="edit_shelf" value="1">
                        <input type="hidden" name="edit_shelf_id" id="edit_shelf_id">
                        <div class="mb-3">
                            <label for="edit_shelf_topic" class="form-label">Shelf Topic</label>
                            <input type="text" class="form-control" id="edit_shelf_topic" name="edit_shelf_topic" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const headerLogo = document.getElementById('headerLogo');

        function toggleSidebar() {
            if (!sidebar) return; // guest/librarian fallback
            sidebar.classList.toggle('closed');
            if (sidebar.classList.contains('closed')) {
                if (headerLogo) headerLogo.classList.add('visible');
            } else {
                if (headerLogo) headerLogo.classList.remove('visible');
            }
        }

        // Ensure header logo matches initial sidebar state
        if (sidebar && sidebar.classList.contains('closed') && headerLogo) {
            headerLogo.classList.add('visible');
        }

        // ✅ Theme toggle guarded
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('change', () => {
                document.body.classList.toggle('dark-theme');
            });
        }

        // ✅ Notification task box guarded
        function toggleTaskBox() {
            const taskBox = document.getElementById('taskBox');
            if (taskBox) taskBox.classList.toggle('collapsed');
        }

        // JavaScript for search functionality
        document.getElementById('searchBar').addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            const listGroupItems = document.querySelectorAll('.list-group-item');

            listGroupItems.forEach(item => {
                const sectionName = item.querySelector('h5').textContent.toLowerCase();
                const shelfItems = item.querySelectorAll('.shelf-item');
                let foundInShelves = false;

                shelfItems.forEach(shelf => {
                    const shelfTopic = shelf.querySelector('span').textContent.toLowerCase();
                    const matchesShelf = shelfTopic.includes(query);
                    shelf.style.display = matchesShelf ? 'flex' : 'none';
                    if (matchesShelf) {
                        foundInShelves = true;
                    }
                });

                const matchesSection = sectionName.includes(query);

                if (matchesSection || foundInShelves) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // JavaScript for Edit Section Modal
        document.getElementById('editSectionModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const sectionId = button.getAttribute('data-section-id');
            const sectionName = button.getAttribute('data-section-name');

            const modalTitle = this.querySelector('.modal-title');
            const inputId = this.querySelector('#edit_section_id');
            const inputName = this.querySelector('#edit_section_name');

            modalTitle.textContent = `Edit Section: ${sectionName}`;
            inputId.value = sectionId;
            inputName.value = sectionName;
        });

        // JavaScript for Edit Shelf Modal
        document.getElementById('editShelfModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const shelfId = button.getAttribute('data-shelf-id');
            const shelfTopic = button.getAttribute('data-shelf-topic');

            const modalTitle = this.querySelector('.modal-title');
            const inputId = this.querySelector('#edit_shelf_id');
            const inputTopic = this.querySelector('#edit_shelf_topic');

            modalTitle.textContent = `Edit Shelf ID: ${shelfId}`;
            inputId.value = shelfId;
            inputTopic.value = shelfTopic;
        });
    </script>
</body>
</html>