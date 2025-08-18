<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

require_once 'crud/db_config.php';
require_once 'crud/log_action.php';

$user_role = $_SESSION['membershipType'];
$is_admin = ($user_role === 'admin');
$is_librarian = ($user_role === 'librarian');

try {
    // Fetch all members who are fully "registered" (have a phone number)
    // and are NOT admins, librarians, or authors
    $sql_registered_members = "
        SELECT 
            m.UserID, m.Name, m.Email, m.MembershipType, r.RegistrationDate,
            COALESCE(s.NumBorrowed, 0) AS NumBorrowed,
            (SELECT COUNT(*) FROM Borrow b WHERE b.UserID = m.UserID AND b.Return_Date IS NULL AND b.Due_Date < CURDATE()) AS NumOverdue,
            CASE
                WHEN m.MembershipType = 'student' THEN 'Student'
                WHEN m.MembershipType = 'teacher' THEN 'Teacher'
                WHEN m.MembershipType = 'general' THEN 'General'
                ELSE 'N/A'
            END AS SpecificType
        FROM Members m 
        JOIN Registered r ON m.UserID = r.UserID
        LEFT JOIN MemberBorrowStats s ON m.UserID = s.UserID
        WHERE m.MembershipType IN ('student', 'teacher', 'general')
        ORDER BY m.Name ASC";

    $stmt_registered_members = $con->prepare($sql_registered_members);
    $stmt_registered_members->execute();
    $registered_members = $stmt_registered_members->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_registered_members->close();
    
    // Fetch registered authors (authors who are registered members)
    $sql_registered_authors = "
        SELECT
            m.UserID, m.Name, m.Email, r.RegistrationDate,
            a.AuthorTitle,
            (SELECT COUNT(*) FROM Books b WHERE b.AuthorID = a.AuthorID) AS NumBooks
        FROM Members m
        JOIN Registered r ON m.UserID = r.UserID
        LEFT JOIN Author a ON m.UserID = a.UserID
        LEFT JOIN Books b ON a.AuthorID = b.AuthorID
        GROUP BY m.UserID, m.Name, m.Email, r.RegistrationDate, a.AuthorTitle
        ORDER BY m.Name ASC";
        
    $stmt_registered_authors = $con->prepare($sql_registered_authors);
    $stmt_registered_authors->execute();
    $registered_authors = $stmt_registered_authors->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_registered_authors->close();

    // Fetch admins
    $sql_admins = "
        SELECT a.UserID, m.Name, m.Email
        FROM Admin a
        JOIN Members m ON a.UserID = m.UserID
        ORDER BY m.Name ASC";
    $stmt_admins = $con->prepare($sql_admins);
    $stmt_admins->execute();
    $admins = $stmt_admins->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_admins->close();

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Member Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet">
    <style>
        /* ... (Your existing CSS styles go here) ... */
        :root {
            --sidebar-width: 450px;
        }

        body {
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            transition: background-color 0.3s, color 0.3s;
            background-color: #eed9c4;
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
            transform: rotate(90deg); /* Rotated vertical bars */
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
        
        .container { max-width: 900px; margin-top: 50px; }
        .card, .book-card {
            background-color: #fff;
            color: #212529;
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-theme .card, body.dark-theme .book-card {
            background-color: #212529;
            color: #eee;
        }
        
        .table img { max-width: 50px; height: auto; }
        .user-type { font-weight: bold; color: #7b3fbf; }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn">☰</button>

    <?php include 'sidebar.php'; ?>

    <div class="theme-switch-wrapper">
        <label class="theme-switch" for="theme-switch">
            <input type="checkbox" id="theme-switch" />
            <div class="slider round"></div>
        </label>
    </div>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <h1 class="section-title text-center">Member Management</h1>

            <div class="card p-3 mb-4">
                <h5 class="card-title">Registered Members</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Type</th>
                                <th scope="col">Registration Date</th>
                                <th scope="col">Borrowed</th>
                                <th scope="col">Overdue</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($registered_members)): ?>
                                <?php foreach ($registered_members as $member): ?>
                                <tr>
                                    <td><?= htmlspecialchars($member['Name']) ?></td>
                                    <td><?= htmlspecialchars($member['Email']) ?></td>
                                    <td class="user-type"><?= htmlspecialchars($member['SpecificType']) ?></td>
                                    <td><?= htmlspecialchars($member['RegistrationDate']) ?></td>
                                    <td>
                                        <a href="#" onclick="showBooks(<?= $member['UserID'] ?>, 'borrowed', '<?= htmlspecialchars($member['Name']) ?>')">
                                            <?= htmlspecialchars($member['NumBorrowed']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="#" onclick="showBooks(<?= $member['UserID'] ?>, 'overdue', '<?= htmlspecialchars($member['Name']) ?>')">
                                            <?= htmlspecialchars($member['NumOverdue']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <form action="DeleteMember.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this member? This action cannot be undone.');">
                                            <input type="hidden" name="UserID" value="<?= htmlspecialchars($member['UserID']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">No registered members found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card p-3 mb-4">
                <h5 class="card-title">Authors</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Title</th>
                                <th scope="col">Registration Date</th>
                                <th scope="col">Books Published</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($registered_authors)): ?>
                                <?php foreach ($registered_authors as $author): ?>
                                <tr>
                                    <td><?= htmlspecialchars($author['Name']) ?></td>
                                    <td><?= htmlspecialchars($author['Email']) ?></td>
                                    <td><?= htmlspecialchars($author['AuthorTitle']) ?></td>
                                    <td><?= htmlspecialchars($author['RegistrationDate']) ?></td>
                                    <td><?= htmlspecialchars($author['NumBooks']) ?></td>
                                    <td>
                                        <form action="DeleteMember.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this author? This action cannot be undone.');">
                                            <input type="hidden" name="UserID" value="<?= htmlspecialchars($author['UserID']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center">No authors found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card p-3">
                <h5 class="card-title">Admins</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($admins)): ?>
                                <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($admin['Name']) ?></td>
                                    <td><?= htmlspecialchars($admin['Email']) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled>Cannot Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">No admins found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="booksModal" tabindex="-1" aria-labelledby="booksModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="booksModalLabel">Book Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <ul id="booksList"></ul>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="authorBioModal" tabindex="-1" aria-labelledby="authorBioModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="authorBioModalLabel">Author Bio</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="authorBioContent">
            </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.querySelector('.sidebar-toggle-btn');
        const contentWrapper = document.querySelector('.content-wrapper');
        const booksModal = new bootstrap.Modal(document.getElementById('booksModal'));
        const authorBioModal = new bootstrap.Modal(document.getElementById('authorBioModal'));

        // Sidebar toggle
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('closed');
            contentWrapper.classList.toggle('content-wrapper-no-sidebar');
        });

        // Sublist toggle
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

        // Theme switch
        const themeSwitch = document.getElementById('theme-switch');
        themeSwitch.addEventListener('change', () => {
            document.body.classList.toggle('dark-theme');
        });
        
        async function showBooks(userId, type, memberName) {
            try {
                const response = await fetch(`../backend/crud/GetBooks.php?UserID=${userId}&type=${type}`);
                const data = await response.json();
                
                const modalTitle = document.getElementById('booksModalLabel');
                const booksList = document.getElementById('booksList');
                booksList.innerHTML = '';
                
                modalTitle.textContent = `${type.charAt(0).toUpperCase() + type.slice(1)} Books for ${memberName}`;
                
                if (data.success && data.books.length > 0) {
                    data.books.forEach(book => {
                        const listItem = document.createElement('li');
                        const dueDate = new Date(book.Due_Date).toLocaleDateString();
                        listItem.textContent = `${book.Title} (Due: ${dueDate})`;
                        booksList.appendChild(listItem);
                    });
                } else {
                    const listItem = document.createElement('li');
                    listItem.textContent = `No ${type} books found.`;
                    booksList.appendChild(listItem);
                }
                
                booksModal.show();
                
            } catch (error) {
                console.error('Error fetching book details:', error);
                alert('Could not fetch book details. Please try again.');
            }
        }

        function showAuthorBio(authorName, authorBio) {
            const modalTitle = document.getElementById('authorBioModalLabel');
            const modalBody = document.getElementById('authorBioContent');
            
            modalTitle.textContent = `Bio for ${authorName}`;
            modalBody.textContent = authorBio;
            
            authorBioModal.show();
        }
    </script>
</body>
</html>