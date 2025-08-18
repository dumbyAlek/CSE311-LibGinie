<?php
// Start the session
session_start();

// Redirect to login if the user is not logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

require_once '../backend/crud/db_config.php';
require_once '../backend/crud/log_action.php';

$user_id = $_SESSION['UserID'] ?? 0;
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'guest';
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// -------------------------
// Handle Book Request Submission
// -------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['request_book'])) {
    $book_name  = trim($_POST['book_name']);
    $author_name = trim($_POST['author_name']);

    if (!empty($book_name) && !empty($author_name)) {
        $sql = "INSERT INTO BookRequests (UserID, BookName, Author) VALUES (?, ?, ?)";

        if ($stmt = $con->prepare($sql)) {
            $stmt->bind_param("iss", $user_id, $book_name, $author_name);

            if ($stmt->execute()) {
                // Log the action
                $_SESSION['user_name'] = $uname;
                log_action($user_id, 'Book Request', 'User ' . $uname . ' requested a book.');
                $_SESSION['request_message'] = "Your book request has been submitted successfully!";
            } else {
                $_SESSION['request_message'] = "Error: Could not process your request. Please try again.";
            }
            $stmt->close();
        } else {
            $_SESSION['request_message'] = "Error: Failed to prepare the statement.";
        }
    } else {
        $_SESSION['request_message'] = "Error: Book Title and Author Name cannot be empty.";
    }

    // Redirect to avoid form resubmission on refresh
    header("Location: ReqBook.php");
    exit;
}

// -------------------------
// Fetch User's Book Requests
// -------------------------
$requested_books = [];
// Assuming a 'RequestDate' column exists in BookRequests as a best practice
$sql_history = "
    SELECT br.ReqID, br.BookName, br.Author, br.Status, m.Name AS RequesterName
    FROM BookRequests br
    JOIN Members m ON br.UserID = m.UserID
    WHERE br.UserID = ?
    ORDER BY br.ReqID DESC
";

if ($stmt = $con->prepare($sql_history)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $requested_books = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Request a Book - LibGinie</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.4.0/css/all.css">

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
        /* Page-specific styles */
        .container {
            max-width: 800px;
            margin-top: 50px;
        }
        .form-section, .history-section {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #7b3fbf;
            border-bottom: 2px solid #7b3fbf;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .btn-primary {
            background-color: #7b3fbf;
            border-color: #7b3fbf;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #6a35a5;
            border-color: #6a35a5;
        }
        .status-badge {
            font-size: 0.9em;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 12px;
        }
        .status-fulfilled { background-color: #d4edda; color: #155724; }
        .status-pending   { background-color: #fff3cd; color: #856404; }
    </style>
</head>
<body>
<button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>
<?php include 'sidebar.php'; ?>

<div class="content-wrapper">
    <div class="container">
        <h1 class="text-center mb-5">Request a Book</h1>

        <?php if (isset($_SESSION['request_message'])): ?>
            <div class="alert alert-<?php echo (strpos($_SESSION['request_message'], 'successfully') !== false) ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['request_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['request_message']); ?>
        <?php endif; ?>

        <div class="form-section">
            <h2 class="section-title">Submit a New Request</h2>
            <form action="ReqBook.php" method="POST">
                <div class="mb-3">
                    <label for="book_name" class="form-label">Book Title</label>
                    <input type="text" class="form-control" id="book_name" name="book_name" required>
                </div>
                <div class="mb-3">
                    <label for="author_name" class="form-label">Author Name</label>
                    <input type="text" class="form-control" id="author_name" name="author_name" required>
                </div>
                <button type="submit" name="request_book" class="btn btn-primary w-100">Submit Request</button>
            </form>
        </div>

        <div class="history-section">
            <h2 class="section-title">Your Request History</h2>
            <?php if (!empty($requested_books)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requested_books as $request): ?>
                                <tr>
                                    <td><?= htmlspecialchars($request['BookName']); ?></td>
                                    <td><?= htmlspecialchars($request['Author']); ?></td>
                                    <td><?= htmlspecialchars($request['Status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">You have not submitted any book requests yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
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