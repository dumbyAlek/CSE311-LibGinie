<?php
// Assume the user's role is stored in a session after login
session_start();

// Get user role from session, default to 'guest' if not set
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'guest';

// Set a flag for easier conditional checks
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// In a real application, you would get this from your database
// For this example, we'll use dummy data only if the user is a librarian
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LibGinie - Home</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <style>
        :root {
            --sidebar-width: 400px;
        }

        body {
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-theme {
            background-color: #121212;
            color: #eee;
        }

        /* sidebarstart  */

        .sidebar {
            background-color: rgba(0, 0, 0, 0.4); /* Dark overlay */
            background-blend-mode: multiply;
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

        /* controlling space between sidebar links */
        .sidebar li {
            margin-bottom: 0.6rem; 
        }

        .sidebar a {
            text-decoration: none;
            color: white;
            font-weight: 500;
            font-size: 1.2rem;
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
            background-color: #7b3fbf; /* violet square */
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
            transform: rotate(90deg); /* Rotated vertical bars */
        }


        .content-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s;
        }

        .sidebar.closed ~ .content-wrapper {
            margin-left: 0;
        }

        .sidebar.closed ~ .content-wrapper .bg-header {
            margin-left: 0;
        }

        /* sidebarend */

        .bg-header {
            background-image: url('../images/header.jpg');
            background-size: cover;
            height: 300px;
            background-position: center;
            padding: 80px 20px 40px;
            color: white;
            transition: margin-left 0.3s, width 0.3s;
        }

        .sidebar.closed ~ .content-wrapper .bg-header {
            margin-left: 0;
        }

        .search-bar {
            max-width: 600px;
            margin: auto;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            margin: 40px 0 20px;
        }

        .book-section {
            white-space: nowrap;
            overflow-x: auto;
            padding-bottom: 10px;
        }

        .book-card {
            display: inline-block;
            width: 150px;
            margin-right: 15px;
            background: white;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
        }

        .book-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
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
            margin-right: 8px; /* Adjust the space as you want */
        }

        input:checked + .slider {
            background-color: #7b3fbf; /* violet */
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .logo-on-header {
            display: none;
            position: absolute;
            top: 20px;
            left: 70px;
            width: 200px;
            z-index: 100;
        }

        .logo-on-header.visible {
            display: block;
        }
        
        /* New Styles for Admin and Librarian features */
        .notification-icon {
            position: fixed;
            top: 60px; /* Below the toggle switch */
            right: 20px;
            z-index: 1000;
            color: #7b3fbf;
            font-size: 24px;
            cursor: pointer;
        }
        .librarian-tasks {
            position: fixed;
            top: 150px;
            right: 20px;
            width: 300px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 990;
            transition: transform 0.3s ease-in-out;
        }
        .librarian-tasks.collapsed {
            transform: translateX(calc(100% + 20px));
        }
        .tasks-header {
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .task-list li {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .disabled-link {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Responsive */
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

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="theme-switch-wrapper">
            <label class="theme-switch" for="themeToggle">
                <input type="checkbox" id="themeToggle" />
                <span class="slider"></span>
            </label>
        </div>
        
        <?php if ($is_librarian) : // Notification icon for all logged-in users ?>
        <a href="#" class="notification-icon" title="View Notifications" onclick="toggleTaskBox()">🔔</a>

        <?php endif; ?>
        
        <header class="bg-header text-center">
            <div id="headerLogo" class="logo-on-header">
                <img src="../images/logo3.png" alt="Logo" />
            </div>

            <div class="search-bar">
                <input type="text" class="form-control form-control-lg" placeholder="Search books...">
            </div>
        </header>
        
        <?php
// ... existing PHP code for session, role checks, etc. ...

// This block combines the data retrieval and display logic for the librarian task box.
if ($is_librarian && isset($_SESSION['UserID'])) {
    // Database connection details are assumed to be available
    require_once '../backend/crud/db_config.php';

    $librarian_user_id = $_SESSION['UserID'];
    $assigned_section = 'Not assigned yet';

    // Retrieve the assigned section from the database
    $sql = "SELECT InChargeOf FROM Librarian WHERE UserID = ?";
    $stmt = $con->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $librarian_user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $assigned_section = htmlspecialchars($row['InChargeOf']);
        }
        $stmt->close();
    }
    $con->close();

    // Now, render the HTML for the librarian's task box
    // This part is included inside the same 'if' block
    ?>
        <div class="librarian-tasks" id="taskBox">
        <div class="tasks-header">
            <span>My Tasks</span>
            <button class="btn btn-sm btn-link text-dark" onclick="toggleTaskBox()">▼</button>
        </div>
        
        <div style="font-size: 1.25rem; font-weight: bold;">
            Assigned Section:
        </div>
        
        <div style="font-size: 0.9rem; margin-top: 5px;">
            <?php echo $assigned_section; ?>
        </div>

        <ul class="task-list mt-2" style="font-size: 0.9rem;">
            </ul>
    </div>
    <?php
}
?>

        <main class="container mt-4">
            <h3 class="section-title">New Arrivals</h3>
            <div class="book-section">
                <div class="book-card"><img src="images/book1.jpg" alt="Book"><p>Book Title</p></div>
            </div>

            <h3 class="section-title">Recommended For You</h3>
            <div class="book-section">
                <div class="book-card"><img src="images/book2.jpg" alt="Book"><p>Book Title</p></div>
            </div>

            <h3 class="section-title">Trending</h3>
            <div class="book-section">
                <div class="book-card"><img src="images/book3.jpg" alt="Book"><p>Book Title</p></div>
            </div>

            <h3 class="section-title">Top Rated</h3>
            <div class="book-section">
                <div class="book-card"><img src="images/book4.jpg" alt="Book"><p>Book Title</p></div>
            </div>

            <h3 class="section-title">Favourites</h3>
            <div class="book-section">
                <div class="book-card"><img src="images/book5.jpg" alt="Book"><p>Book Title</p></div>
            </div>

            <h3 class="section-title">Your Read</h3>
            <div class="book-section">
                <div class="book-card"><img src="images/book6.jpg" alt="Book"><p>Book Title</p></div>
            </div>
        </main>

        <footer>
            <p>&copy; 2025 LibGinie. All rights reserved.</p>
        </footer>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const headerLogo = document.getElementById('headerLogo');

        function toggleSidebar() {
            sidebar.classList.toggle('closed');

            if (sidebar.classList.contains('closed')) {
                headerLogo.classList.add('visible');
            } else {
                headerLogo.classList.remove('visible');
            }
        }

        // Initial check to show the logo if the sidebar starts closed
        if (sidebar.classList.contains('closed')) {
            headerLogo.classList.add('visible');
        }

        // Theme toggle logic
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('change', () => {
            document.body.classList.toggle('dark-theme');
        });

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

        // Librarian task box toggle
        function toggleTaskBox() {
            const taskBox = document.getElementById('taskBox');
            taskBox.classList.toggle('collapsed');
        }
    </script>
</body>
</html>