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

    <?php if (!$is_guest) : // Main sidebar for all logged-in users ?>
    <nav class="sidebar closed" id="sidebar">
        <a href="home.php"><img src="../images/logo3.png" alt="Logo" class="logo" /></a>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="#">My Books</a></li>
            <li><a href="#">Favorites</a></li>

            <?php if ($user_role === 'admin') : ?>
            <li><a href="../backend/BookMng.php">Book Management</a></li>
            <li><a href="../backend/BookMain.php">Book Maintenance</a></li>
            <li><a href="SecsNShelves.php">Sections & Shelves</a></li>
            <li><a href="../backend/MemMng.php">Member Management</a></li>
            <li><a href="../backend/EmpMng.php">Employee Management</a></li>
            <?php elseif ($is_librarian) : ?>
            <li><a href="../backend/MemMng.php">Member Management</a></li>
            <li><a href="#">Request Book</a></li>
            <?php elseif (in_array($user_role, ['author', 'student', 'teacher', 'general'])) : ?>
            <li><a href="#">Request Book</a></li>
            <li><a href="#">Borrowed Books</a></li>
            <?php endif; ?>

            <?php if ($user_role === 'author') : ?>
            <li><a href="author_account.html">My Account</a></li>
            <?php endif; ?>
            
            <li class="collapsible-header" onclick="toggleSublist('categoryList')" aria-expanded="false" aria-controls="categoryList">
                <span class="arrow">v</span> Categories
            </li>
            <ul class="sublist" id="categoryList" hidden>
                <li><a href="categories.php?category=Text Books">Text Books</a></li>
                <li><a href="categories.php?category=Comics">Comics</a></li>
                <li><a href="categories.php?category=Novels">Novels</a></li>
                <li><a href="categories.php?category=Magazines">Magazines</a></li>
            </ul>

            <li class="collapsible-header" onclick="toggleSublist('genreList')" aria-expanded="false" aria-controls="genreList">
                <span class="arrow">></span> Genres
            </li>
            <ul class="sublist" id="genreList" hidden>
                <li><a href="#">Fantasy</a></li>
                <li><a href="#">Horror</a></li>
                <li><a href="#">Romance</a></li>
                <li><a href="#">[Browse All Genres]</a></li>
            </ul>
            
            <li><a href="#">Reserved</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="../backend/logout.php">Logout</a></li>
        </ul>
    </nav>
    <?php else: // Sidebar for Guest users only ?>
    <nav class="sidebar closed" id="sidebar">
        <a href="home.php"><img src="../images/logo3.png" alt="Logo" class="logo" /></a>
        <ul>
            <li><a href="signup.php">Sign Up</a></li>
            <li><a href="#" class="disabled-link">Reserved</a></li>
            <li><a href="#">Settings</a></li>
            <li><a href="../pages/loginn.php">Log In</a></li>
        </ul>
    </nav>
    <?php endif; ?>

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

        function toggleSidebar() {
            sidebar.classList.toggle('closed');
        }

        function toggleSublist(id) {
            const header = document.querySelector(`[aria-controls="${id}"]`);
            const sublist = document.getElementById(id);
            const arrow = header.querySelector('.arrow');

            const isExpanded = header.getAttribute('aria-expanded') === 'true';
            header.setAttribute('aria-expanded', !isExpanded);
            arrow.textContent = isExpanded ? '>' : 'v';
            sublist.hidden = isExpanded;
            sublist.classList.toggle('show');
        }

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