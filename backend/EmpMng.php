<?php
// Start the session
session_start();

// Check if the user is logged in and is an administrator
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['membershipType'] !== 'admin') {
    header("Location: ../pages/loginn.php");
    exit;
}

// Include database configuration
require_once '../backend/crud/db_config.php';

// Initialize messages
$success_message = '';
$error_message = '';

// --- Handle Form Submissions ---

// Function to safely sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // Action 1: Add a new librarian
    if ($action === 'add_librarian') {
        $user_id = sanitize_input($_POST['user_id']);

        // Start a transaction
        $con->begin_transaction();
        try {
            // Check if user exists and is not already an employee
            $check_sql = "SELECT UserID, MembershipType FROM Members WHERE UserID = ?";
            $check_stmt = $con->prepare($check_sql);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $user = $result->fetch_assoc();
            $check_stmt->close();

            if ($user && $user['MembershipType'] !== 'librarian' && $user['MembershipType'] !== 'admin') {
                // Add to Employee table
                $employee_id = "LIB" . str_pad($user_id, 5, '0', STR_PAD_LEFT);
                $sql_employee = "INSERT INTO Employee (UserID, EmployeeID, start_date) VALUES (?, ?, CURDATE())";
                $stmt_employee = $con->prepare($sql_employee);
                $stmt_employee->bind_param("is", $user_id, $employee_id);
                $stmt_employee->execute();
                $stmt_employee->close();

                // Add to Librarian table
                $sql_librarian = "INSERT INTO Librarian (UserID, LibrarianID) VALUES (?, ?)";
                $stmt_librarian = $con->prepare($sql_librarian);
                $stmt_librarian->bind_param("is", $user_id, $employee_id);
                $stmt_librarian->execute();
                $stmt_librarian->close();
                
                // Update MembershipType in Members table
                $sql_update_member = "UPDATE Members SET MembershipType = 'librarian' WHERE UserID = ?";
                $stmt_update_member = $con->prepare($sql_update_member);
                $stmt_update_member->bind_param("i", $user_id);
                $stmt_update_member->execute();
                $stmt_update_member->close();

                $con->commit();
                $success_message = "New librarian added successfully!";
            } else {
                $error_message = "User not found or is already an employee.";
                $con->rollback();
            }
        } catch (mysqli_sql_exception $e) {
            $con->rollback();
            $error_message = "Error adding librarian: " . $e->getMessage();
        }
    }
    
    // Action 2: Update a librarian's section and tasks
    elseif ($action === 'update_librarian') {
        $user_id = sanitize_input($_POST['librarian_id']);
        $in_charge_of = sanitize_input($_POST['in_charge_of']);
        
        $con->begin_transaction();
        try {
            $sql_update_section = "UPDATE Librarian SET InChargeOf = ? WHERE UserID = ?";
            $stmt_update_section = $con->prepare($sql_update_section);
            $stmt_update_section->bind_param("si", $in_charge_of, $user_id);
            $stmt_update_section->execute();
            $stmt_update_section->close();
            
            $con->commit();
            $success_message = "Librarian information updated successfully!";
        } catch (mysqli_sql_exception $e) {
            $con->rollback();
            $error_message = "Error updating librarian: " . $e->getMessage();
        }
    }
    
    // Action 3: Remove a librarian
    elseif ($action === 'remove_librarian') {
        $user_id = sanitize_input($_POST['librarian_id']);
        
        $con->begin_transaction();
        try {
            // Update MembershipType in Members table
            $sql_update_member = "UPDATE Members SET MembershipType = 'general' WHERE UserID = ?";
            $stmt_update_member = $con->prepare($sql_update_member);
            $stmt_update_member->bind_param("i", $user_id);
            $stmt_update_member->execute();
            $stmt_update_member->close();

            // Delete from Librarian table
            $sql_delete_librarian = "DELETE FROM Librarian WHERE UserID = ?";
            $stmt_delete_librarian = $con->prepare($sql_delete_librarian);
            $stmt_delete_librarian->bind_param("i", $user_id);
            $stmt_delete_librarian->execute();
            $stmt_delete_librarian->close();
            
            // Delete from Employee table
            $sql_delete_employee = "DELETE FROM Employee WHERE UserID = ?";
            $stmt_delete_employee = $con->prepare($sql_delete_employee);
            $stmt_delete_employee->bind_param("i", $user_id);
            $stmt_delete_employee->execute();
            $stmt_delete_employee->close();
            
            $con->commit();
            $success_message = "Librarian account removed successfully!";
        } catch (mysqli_sql_exception $e) {
            $con->rollback();
            $error_message = "Error removing librarian: " . $e->getMessage();
        }
    }
}

// --- Fetch data for display ---
$total_employees = 0;
$total_admins = 0;
$total_librarians = 0;

$count_sql = "SELECT (SELECT COUNT(*) FROM Employee) as total_employees,
                     (SELECT COUNT(*) FROM Admin) as total_admins,
                     (SELECT COUNT(*) FROM Librarian) as total_librarians";
$count_result = $con->query($count_sql);
if ($count_result && $counts = $count_result->fetch_assoc()) {
    $total_employees = $counts['total_employees'];
    $total_admins = $counts['total_admins'];
    $total_librarians = $counts['total_librarians'];
}

$librarians = [];
$librarian_sql = "SELECT M.UserID, M.Name, M.Email, L.LibrarianID, L.InChargeOf
                  FROM Members M
                  JOIN Librarian L ON M.UserID = L.UserID
                  ORDER BY M.Name";
$librarian_result = $con->query($librarian_sql);
if ($librarian_result) {
    while ($row = $librarian_result->fetch_assoc()) {
        $librarians[] = $row;
    }
}

$non_employees = [];
$non_employee_sql = "SELECT UserID, Name FROM Members WHERE MembershipType NOT IN ('librarian', 'admin')";
$non_employee_result = $con->query($non_employee_sql);
if ($non_employee_result) {
    while ($row = $non_employee_result->fetch_assoc()) {
        $non_employees[] = $row;
    }
}

$sections = [];
$sections_sql = "SELECT SectionID, Name FROM Library_Sections";
$sections_result = $con->query($sections_sql);
if ($sections_result) {
    while ($row = $sections_result->fetch_assoc()) {
        $sections[] = $row;
    }
}

$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LibGinie - Employee Management</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
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
            padding-top: 60px;
        }

        .sidebar.closed ~ .content-wrapper {
            margin-left: 0;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            margin: 40px 0 20px;
        }

        footer {
            background: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .theme-switch-wrapper {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            z-index: 1000;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        .arrow {
            margin-right: 8px;
        }

        input:checked + .slider {
            background-color: #7b3fbf;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }
        
        .notification-icon {
            position: fixed;
            top: 60px;
            right: 20px;
            z-index: 1000;
            color: #7b3fbf;
            font-size: 24px;
            cursor: pointer;
        }
        .disabled-link {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .settings-card {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .dark-theme .settings-card {
            background: #222;
            color: #eee;
        }
        .form-label {
            font-weight: 600;
        }
        .employee-stats .card {
            background-color: #fff;
            border: 1px solid #ddd;
        }
        .employee-stats .card-title {
            font-size: 1.25rem;
            font-weight: bold;
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
    </style>
</head>

<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

    <nav class="sidebar closed" id="sidebar">
        <a href="../pages/home.php"><img src="../images/logo3.png" alt="Logo" class="logo" /></a>
        <ul>
            <li><a href="../pages/dashboard.php">Dashboard</a></li>
            <li><a href="#">My Books</a></li>
            <li><a href="#">Favorites</a></li>
            
            
            <?php if ($_SESSION['membershipType'] === 'admin') : ?>
                
                <li><a href="../backend/BookMng.php">Book Management</a></li>
                <li><a href="BookMain.php">Book Maintenance</a></li>
                <li><a href="../pages/SecsNShelves.php">Sections & Shelves</a></li>
                <li><a href="MemMng.php">Member Management</a></li>
                <li><a href="EmpMng.php">Employee Management</a></li>
            <?php endif; ?>
            
            <li class="collapsible-header" onclick="toggleSublist('categoryList')" aria-expanded="false" aria-controls="categoryList">
                <span class="arrow">></span> Categories
            </li>
            <ul class="sublist" id="categoryList" hidden>
                <li><a href="../pages/categories.php?category=Text Books">Text Books</a></li>
                <li><a href="../pages/categories.php?category=Comics">Comics</a></li>
                <li><a href="../pages/categories.php?category=Novels">Novels</a></li>
                <li><a href="../pages/categories.php?category=Magazines">Magazines</a></li>
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

    <div class="content-wrapper">
        <main class="container-fluid mt-4">
            <h3 class="section-title">Employee Management</h3>
            <p class="mb-4">Dashboard for managing all library staff members.</p>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="row mb-4 employee-stats">
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Employees</h5>
                            <h2 class="card-text"><?php echo $total_employees; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <div class="card-body">
                            <h5 class="card-title">Administrators</h5>
                            <h2 class="card-text"><?php echo $total_admins; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <div class="card-body">
                            <h5 class="card-title">Librarians</h5>
                            <h2 class="card-text"><?php echo $total_librarians; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="card settings-card mt-4">
                <div class="card-header">
                    <h4>Add New Librarian</h4>
                </div>
                <div class="card-body">
                    <form action="EmpMng.php" method="POST">
                        <input type="hidden" name="action" value="add_librarian">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select a User to Promote</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">-- Select a user --</option>
                                <?php foreach ($non_employees as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['UserID']); ?>">
                                        <?php echo htmlspecialchars($user['Name']) . " (ID: " . htmlspecialchars($user['UserID']) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">Add Librarian</button>
                    </form>
                </div>
            </div>
            
            <hr>

            <div class="card settings-card mt-4">
                <div class="card-header">
                    <h4>Current Librarians</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($librarians)): ?>
                        <p>No librarians found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Librarian ID</th>
                                        <th>Section/Tasks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($librarians as $librarian): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($librarian['Name']); ?></td>
                                            <td><?php echo htmlspecialchars($librarian['Email']); ?></td>
                                            <td><?php echo htmlspecialchars($librarian['LibrarianID']); ?></td>
                                            <td>
                                                <form action="EmpMng.php" method="POST" class="d-flex">
                                                    <input type="hidden" name="action" value="update_librarian">
                                                    <input type="hidden" name="librarian_id" value="<?php echo htmlspecialchars($librarian['UserID']); ?>">
                                                    <input type="text" class="form-control form-control-sm me-2" name="in_charge_of" placeholder="Enter Section or Tasks" value="<?php echo htmlspecialchars($librarian['InChargeOf']); ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                                </form>
                                            </td>
                                            <td>
                                                <form action="EmpMng.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this librarian?');">
                                                    <input type="hidden" name="action" value="remove_librarian">
                                                    <input type="hidden" name="librarian_id" value="<?php echo htmlspecialchars($librarian['UserID']); ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
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
    </script>
</body>
</html>