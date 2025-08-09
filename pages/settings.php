<?php
// Start the session
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

// Include your database configuration
require_once '../backend/crud/db_config.php';

// Get user data from the session
$user_id = $_SESSION['UserID'];
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'guest';
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// Initialize success and error messages
$success_message = '';
$error_message = '';

// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // Action 1: Update Account Information
    if ($action === 'update_info') {
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $street = $_POST['street'];
        $city = $_POST['city'];
        $zip = $_POST['zip'];
        
        // Start a transaction for safety
        $con->begin_transaction();
        try {
            $sql = "UPDATE Members SET Name = ?, PhoneNumber = ?, Address_Street = ?, Address_City = ?, Address_ZIP = ? WHERE UserID = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("sssssi", $name, $phone, $street, $city, $zip, $user_id);
            $stmt->execute();
            $stmt->close();

            // Update role-specific fields
            if ($user_role === 'author') {
                $author_title = $_POST['authorTitle'];
                $author_bio = $_POST['authorBio'];
                $sql_author = "UPDATE Author SET AuthorTitle = ?, AuthorBio = ? WHERE UserID = ?";
                $stmt_author = $con->prepare($sql_author);
                $stmt_author->bind_param("ssi", $author_title, $author_bio, $user_id);
                $stmt_author->execute();
                $stmt_author->close();
            } elseif (in_array($user_role, ['student', 'teacher'])) {
                $institution = $_POST['institution'];
                $sql_role = "UPDATE " . ucfirst($user_role) . " SET Institution = ? WHERE UserID = ?";
                $stmt_role = $con->prepare($sql_role);
                $stmt_role->bind_param("si", $institution, $user_id);
                $stmt_role->execute();
                $stmt_role->close();
            }

            $con->commit();
            $success_message = "Account information updated successfully!";
            $_SESSION['user_name'] = $name; // Update the session variable for the name
        } catch (mysqli_sql_exception $e) {
            $con->rollback();
            $error_message = "Error updating account: " . $e->getMessage();
        }
    } 
    // Action 2: Change Password
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];

        $sql = "SELECT PasswordHash FROM LoginCredentials WHERE UserID = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($current_password, $user['PasswordHash'])) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update = "UPDATE LoginCredentials SET PasswordHash = ? WHERE UserID = ?";
            $stmt_update = $con->prepare($sql_update);
            $stmt_update->bind_param("si", $hashed_new_password, $user_id);
            $stmt_update->execute();
            $stmt_update->close();
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Invalid current password.";
        }
    } 
    // Action 3: Delete Account
    elseif ($action === 'delete_account') {
        $con->begin_transaction();
        try {
            if ($user_role === 'author') {
                $con->query("DELETE FROM Author WHERE UserID = $user_id");
            } elseif ($user_role === 'student') {
                $con->query("DELETE FROM Student WHERE UserID = $user_id");
            } elseif ($user_role === 'teacher') {
                $con->query("DELETE FROM Teacher WHERE UserID = $user_id");
            } elseif ($user_role === 'general') {
                 $con->query("DELETE FROM General WHERE UserID = $user_id");
            }
            
            $con->query("DELETE FROM LoginCredentials WHERE UserID = $user_id");
            $con->query("DELETE FROM Registered WHERE UserID = $user_id");
            $con->query("DELETE FROM Members WHERE UserID = $user_id");

            $con->commit();

            session_destroy();
            header("Location: ../pages/loginn.php?deleted=true");
            exit;
        } catch (mysqli_sql_exception $e) {
            $con->rollback();
            $error_message = "Error deleting account: " . $e->getMessage();
        }
    }
}

// --- Fetch user's current info to pre-populate the forms ---
$user_info = null;
$sql_user = "SELECT * FROM Members WHERE UserID = ?";
$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($result_user->num_rows > 0) {
    $user_info = $result_user->fetch_assoc();

    if ($user_role === 'author') {
        $sql_author = "SELECT AuthorTitle, AuthorBio FROM Author WHERE UserID = ?";
        $stmt_author = $con->prepare($sql_author);
        $stmt_author->bind_param("i", $user_id);
        $stmt_author->execute();
        $result_author = $stmt_author->get_result();
        $author_info = $result_author->fetch_assoc();
        $user_info = array_merge($user_info, $author_info);
    } elseif (in_array($user_role, ['student', 'teacher', 'general'])) {
        $sql_role = "SELECT * FROM " . ucfirst($user_role) . " WHERE UserID = ?";
        $stmt_role = $con->prepare($sql_role);
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $result_role = $stmt_role->get_result();
        $role_info = $result_role->fetch_assoc();
        $user_info = array_merge($user_info, $role_info);
    }
}
$stmt_user->close();
$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LibGinie - Settings</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <style>
        /* New background color */
        body {
            background-color: #eed9c4; 
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        /* The rest of your existing CSS remains the same */
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

    <?php if (!$is_guest) : // Main sidebar for all logged-in users ?>
    <nav class="sidebar closed" id="sidebar">
        <a href="home.php"><img src="../images/logo3.png" alt="Logo" class="logo" /></a>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="#">My Books</a></li>
            <li><a href="#">Favorites</a></li>

            <?php if ($user_role === 'admin') : ?>
            <li><a href="../backend/BookMng.php">Book Management</a></li>
            <li><a href="../backend/MemMng.php">Member Management</a></li>
            <li><a href="EmpMng.html">Employee Management</a></li>
            <?php elseif ($is_librarian) : ?>
            <li><a href="MemberMng.html">Member Management</a></li>
            <li><a href="#">Request Book</a></li>
            <?php elseif (in_array($user_role, ['author', 'student', 'teacher', 'general'])) : ?>
            <li><a href="#">Request Book</a></li>
            <li><a href="#">Borrowed Books</a></li>
            <?php endif; ?>

            <?php if ($user_role === 'author') : ?>
            <li><a href="author_account.html">My Account</a></li>
            <?php endif; ?>
            
            <li class="collapsible-header" onclick="toggleSublist('categoryList')" aria-expanded="false" aria-controls="categoryList">
                <span class="arrow">></span> Categories
            </li>
            <ul class="sublist" id="categoryList" hidden>
                <li><a href="#">TextBooks</a></li>
                <li><a href="#">Comics</a></li>
                <li><a href="#">Novels</a></li>
                <li><a href="#">Magazines</a></li>
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
            <li><a href="../backend/logout.php">Log In</a></li>
        </ul>
    </nav>
    <?php endif; ?>

    <div class="content-wrapper">
        <main class="container mt-4">
            <h3 class="section-title">Account Settings</h3>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="settings-card">
                <h4 class="mb-3">Change Account Information</h4>
                <form action="settings.php" method="POST">
                    <input type="hidden" name="action" value="update_info">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user_info['Name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_info['Email'] ?? ''); ?>" disabled>
                        <div class="form-text">Email cannot be changed.</div>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_info['PhoneNumber'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="street" class="form-label">Street Address</label>
                        <input type="text" class="form-control" id="street" name="street" value="<?php echo htmlspecialchars($user_info['Address_Street'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="city" class="form-label">City</label>
                        <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user_info['Address_City'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="zip" class="form-label">ZIP Code</label>
                        <input type="text" class="form-control" id="zip" name="zip" value="<?php echo htmlspecialchars($user_info['Address_ZIP'] ?? ''); ?>">
                    </div>

                    <?php if ($user_role === 'author'): ?>
                        <div class="mb-3">
                            <label for="authorTitle" class="form-label">Author Title</label>
                            <input type="text" class="form-control" id="authorTitle" name="authorTitle" value="<?php echo htmlspecialchars($user_info['AuthorTitle'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="authorBio" class="form-label">Biography</label>
                            <textarea class="form-control" id="authorBio" name="authorBio" rows="3"><?php echo htmlspecialchars($user_info['AuthorBio'] ?? ''); ?></textarea>
                        </div>
                    <?php elseif ($user_role === 'general'): ?>
                        <div class="mb-3">
                            <label for="occupation" class="form-label">Occupation</label>
                            <input type="text" class="form-control" id="occupation" name="occupation" value="<?php echo htmlspecialchars($user_info['Occupation'] ?? ''); ?>">
                        </div>
                    <?php elseif (in_array($user_role, ['student', 'teacher'])): ?>
                        <div class="mb-3">
                            <label for="institution" class="form-label">Institution</label>
                            <input type="text" class="form-control" id="institution" name="institution" value="<?php echo htmlspecialchars($user_info['Institution'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-info">Save Changes</button>
                </form>
            </div>

            <div class="settings-card">
                <h4 class="mb-3">Change Password</h4>
                <form action="settings.php" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <button type="submit" class="btn btn-info">Change Password</button>
                </form>
            </div>

            <div class="settings-card bg-danger text-white">
                <h4 class="mb-3">Delete Account</h4>
                <p>This action is irreversible. All of your data will be permanently deleted.</p>
                <form action="settings.php" method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_account">
                    <button type="submit" class="btn btn-dark">Delete My Account</button>
                </form>
            </div>
        </main>

        <footer>
            <p>&copy; 2025 LibGinie. All rights reserved.</p>
        </footer>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');

        function toggleSidebar() {
            sidebar.classList.toggle('closed');
        }

        // Note: The logo-on-header JS is removed as there is no header logo anymore.

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