<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}
require_once '../backend/crud/db_config.php';
require_once '../backend/crud/log_action.php';

$user_id = $_SESSION['UserID'];
$msg = "";
// Get user role from session, default to 'guest' if not set
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'guest';

// ===== ACTIONS =====

// Borrow a book
if (isset($_GET['borrow'])) {
    $isbn = $_GET['borrow'];
    $sql = "SELECT CopyID FROM BookCopy WHERE ISBN = ? AND Status = 'Available' LIMIT 1";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $copy_id = $row['CopyID'];
        $due_date = date('Y-m-d', strtotime('+14 days'));

        $con->begin_transaction();
        try {
            $con->query("UPDATE BookCopy SET Status='Borrowed' WHERE CopyID=$copy_id");
            $stmt2 = $con->prepare("INSERT INTO Borrow (UserID, CopyID, Borrow_Date, Due_Date) VALUES (?, ?, CURDATE(), ?)");
            $stmt2->bind_param("iis", $user_id, $copy_id, $due_date);
            $stmt2->execute();
            $con->commit();

            // Query to remove the book from the user's wishlist
            $stmt3 = $con->prepare("UPDATE BookInteractions SET InWishlist = FALSE WHERE UserID = ? AND ISBN = ?");
            $stmt3->bind_param("is", $user_id, $isbn);
            $stmt3->execute();
            $con->commit();

            // Log the action
            $_SESSION['user_name'] = $uname;
            log_action($user_id, 'Borrow and Reserve', 'User ' . $uname . ' borrowed a book.');
            $msg = "Book borrowed successfully! Due date: $due_date";
        } catch (Exception $e) {
            $con->rollback();
            $msg = "Error borrowing book.";
        }
    } else {
        $msg = "Sorry, no available copies for borrowing.";
    }
}

// Reserve a book
if (isset($_GET['reserve'])) {
    $isbn = $_GET['reserve'];
    $sql = "SELECT CopyID FROM BookCopy WHERE ISBN = ? AND Status = 'Available' LIMIT 1";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $copy_id = $row['CopyID'];

        $con->begin_transaction();
        try {
            $con->query("UPDATE BookCopy SET Status='Reserved' WHERE CopyID=$copy_id");
            $stmt2 = $con->prepare("INSERT INTO Reservation (UserID, CopyID, ReservationDate) VALUES (?, ?, CURDATE())");
            $stmt2->bind_param("ii", $user_id, $copy_id);
            $stmt2->execute();
            $con->commit();

            // Query to remove the book from the user's wishlist
            $stmt3 = $con->prepare("UPDATE BookInteractions SET InWishlist = FALSE WHERE UserID = ? AND ISBN = ?");
            $stmt3->bind_param("is", $user_id, $isbn);
            $stmt3->execute();
            $con->commit();

            $msg = "Book reserved successfully!";
            // Log the action
            $_SESSION['user_name'] = $uname;
            log_action($user_id, 'Borrow and Reserve', 'User ' . $uname . ' reserved a book.');
        } catch (Exception $e) {
            $con->rollback();
            $msg = "Error reserving book.";
        }
    } else {
        $msg = "Sorry, no available copies for reservation.";
    }
}

// Cancel reservation
if (isset($_GET['cancel'])) {
    $res_id = intval($_GET['cancel']);
    $sql = "SELECT CopyID FROM Reservation WHERE ResID = ? AND UserID = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ii", $res_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $copy_id = $row['CopyID'];

        $con->begin_transaction();
        try {
            $con->query("DELETE FROM Reservation WHERE ResID=$res_id");
            $con->query("UPDATE BookCopy SET Status='Available' WHERE CopyID=$copy_id");
            $con->commit();
            // Log the action
            $_SESSION['user_name'] = $uname;
            log_action($user_id, 'Borrow and Reserve', 'User ' . $uname . ' cancelled a book reservation.');
            $msg = "Reservation cancelled successfully.";
        } catch (Exception $e) {
            $con->rollback();
            $msg = "Error cancelling reservation.";
        }
    }
}

// Return a borrowed book
if (isset($_GET['return'])) {
    $copy_id = intval($_GET['return']);

    $sql = "SELECT * FROM Borrow WHERE CopyID = ? AND UserID = ? AND Return_Date IS NULL";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ii", $copy_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $con->begin_transaction();
        try {
            $stmt1 = $con->prepare("UPDATE Borrow SET Return_Date = CURDATE() WHERE CopyID = ? AND UserID = ? AND Return_Date IS NULL");
            $stmt1->bind_param("ii", $copy_id, $user_id);
            $stmt1->execute();

            $stmt2 = $con->prepare("UPDATE BookCopy SET Status='Available' WHERE CopyID = ?");
            $stmt2->bind_param("i", $copy_id);
            $stmt2->execute();

            $con->commit();
            $msg = "Book returned successfully.";
            // Log the action
            $_SESSION['user_name'] = $uname;
            log_action($user_id, 'Borrow and Reserve', 'User ' . $uname . ' returned a book.');
        } catch (Exception $e) {
            $con->rollback();
            $msg = "Error returning book.";
        }
    } else {
        $msg = "No active borrow found for this book.";
    }
}

// ===== SEARCH (UPDATED FOR YOUR SCHEMA) =====
$search = $_GET['search'] ?? '';
$books = [];
if ($search !== '') {
    $like = "%$search%";
    $sql = "SELECT 
                b.ISBN, b.Title, b.CoverPicture,
                GROUP_CONCAT(DISTINCT g.GenreName SEPARATOR ', ') AS Genre,
                AVG(br.Rating) AS Rating,
                COUNT(br.Rating) AS NumRatings,
                m.Name AS AuthorName
            FROM Books b
            LEFT JOIN Author a ON b.AuthorID = a.AuthorID
            LEFT JOIN Members m ON a.UserID = m.UserID
            LEFT JOIN Book_Genres bg ON b.ISBN = bg.ISBN
            LEFT JOIN Genres g ON bg.GenreID = g.GenreID
            LEFT JOIN BookReviews br ON b.ISBN = br.ISBN
            WHERE b.Title LIKE ? OR m.Name LIKE ? OR b.ISBN LIKE ?
            GROUP BY b.ISBN";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ===== Reserved Books =====
$sql = "SELECT
            r.ResID, b.ISBN, b.Title, b.CoverPicture, r.ReservationDate,
            GROUP_CONCAT(DISTINCT g.GenreName SEPARATOR ', ') AS Genre,
            AVG(br.Rating) AS Rating,
            COUNT(br.Rating) AS NumRatings,
            m.Name AS AuthorName,
            bc.Status AS CopyStatus,
            bc.CopyID
        FROM Reservation r
        JOIN BookCopy bc ON r.CopyID = bc.CopyID
        JOIN Books b ON bc.ISBN = b.ISBN
        LEFT JOIN Author a ON b.AuthorID = a.AuthorID
        LEFT JOIN Members m ON a.UserID = m.UserID
        LEFT JOIN Book_Genres bg ON b.ISBN = bg.ISBN
        LEFT JOIN Genres g ON bg.GenreID = g.GenreID
        LEFT JOIN BookReviews br ON b.ISBN = br.ISBN
        WHERE r.UserID = ?
        GROUP BY r.ResID, b.ISBN, b.Title, b.CoverPicture, r.ReservationDate, m.Name, bc.Status, bc.CopyID"; 
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reserved_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== Borrow History =====
$sql = "SELECT 
            bc.CopyID, b.ISBN, b.Title, b.CoverPicture, brw.Borrow_Date, brw.Due_Date, brw.Return_Date,
            GROUP_CONCAT(DISTINCT g.GenreName SEPARATOR ', ') AS Genre,
            AVG(br.Rating) AS Rating,
            COUNT(br.Rating) AS NumRatings,
            m.Name AS AuthorName,
            CASE
                WHEN brw.Return_Date IS NULL AND CURDATE() > brw.Due_Date THEN DATEDIFF(CURDATE(), brw.Due_Date)*50
                WHEN brw.Return_Date > brw.Due_Date THEN DATEDIFF(brw.Return_Date, brw.Due_Date)*50
                ELSE 0
            END AS Fine
        FROM Borrow brw
        JOIN BookCopy bc ON brw.CopyID = bc.CopyID
        JOIN Books b ON bc.ISBN = b.ISBN
        LEFT JOIN Author a ON b.AuthorID = a.AuthorID
        LEFT JOIN Members m ON a.UserID = m.UserID
        LEFT JOIN Book_Genres bg ON b.ISBN = bg.ISBN
        LEFT JOIN Genres g ON bg.GenreID = g.GenreID
        LEFT JOIN BookReviews br ON b.ISBN = br.ISBN
        WHERE brw.UserID = ?
        GROUP BY brw.BorrowID
        ORDER BY brw.Return_Date IS NOT NULL, brw.Due_Date DESC"; 
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrow_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== Wishlist Books (UPDATED FOR YOUR SCHEMA) =====
$sql = "SELECT 
            b.ISBN, b.Title, b.CoverPicture,
            GROUP_CONCAT(DISTINCT g.GenreName SEPARATOR ', ') AS Genre,
            AVG(br.Rating) AS Rating,
            COUNT(br.Rating) AS NumRatings,
            m.Name AS AuthorName
        FROM BookInteractions bi
        JOIN Books b ON bi.ISBN = b.ISBN
        LEFT JOIN Author a ON b.AuthorID = a.AuthorID
        LEFT JOIN Members m ON a.UserID = m.UserID
        LEFT JOIN Book_Genres bg ON b.ISBN = bg.ISBN
        LEFT JOIN Genres g ON bg.GenreID = g.GenreID
        LEFT JOIN BookReviews br ON b.ISBN = br.ISBN
        WHERE bi.UserID = ? AND bi.InWishlist = TRUE
        GROUP BY b.ISBN";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wishlist_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Borrow a reserved book
if (isset($_GET['borrow_reserved'])) {
    $copy_id = intval($_GET['borrow_reserved']);

    $sql = "SELECT ResID FROM Reservation WHERE CopyID = ? AND UserID = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ii", $copy_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $res_id = $res->fetch_assoc()['ResID'];
        $due_date = date('Y-m-d', strtotime('+14 days'));

        $con->begin_transaction();
        try {
            // Update BookCopy status to 'Borrowed'
            $con->query("UPDATE BookCopy SET Status='Borrowed' WHERE CopyID=$copy_id");
            // Insert into Borrow table
            $stmt2 = $con->prepare("INSERT INTO Borrow (UserID, CopyID, Borrow_Date, Due_Date) VALUES (?, ?, CURDATE(), ?)");
            $stmt2->bind_param("iis", $user_id, $copy_id, $due_date);
            $stmt2->execute();
            // Delete the reservation record
            $con->query("DELETE FROM Reservation WHERE ResID=$res_id");

            $con->commit();
            $msg = "Book borrowed from reservation successfully! Due date: $due_date";
            // Log the action
            $_SESSION['user_name'] = $uname;
            log_action($user_id, 'Borrow and Reservee', 'User ' . $uname . ' borrwed a book.');
        } catch (Exception $e) {
            $con->rollback();
            $msg = "Error borrowing reserved book.";
        }
    } else {
        $msg = "No active reservation found for this book.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LibGinie - Borrow & Reserve</title>
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
            background-color: rgba(0, 0, 0, 0.4);
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
        /* sidebarend */
        
        .container {
            margin-top: 20px;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        /* NEW STYLES FOR COVER PICTURE FOCUS */
        .book-cover {
            width: 100px;
            height: auto;
            display: block;
            margin: auto;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table th, .table td {
            vertical-align: middle;
            text-align: center;
        }
        .table thead th:first-child {
            width: 150px;
        }

        /* Hoverable rows */
        .table tbody tr {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }

        .table tbody tr:hover {
            background-color: #e9ecef; /* A slightly darker gray for more contrast */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px); /* Lifts the row slightly */
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>
    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper p-4">
        <main class="container">
            <h1 class="mb-4 text-center">Borrow & Reserve Books</h1>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-info mt-3" role="alert">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <form id="searchForm" method="get" class="mb-4">
                <div class="input-group">
                    <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search by title, author, or ISBN" value="<?=htmlspecialchars($search)?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>

            <?php if (!empty($books)): ?>
            <h4 class="mt-4">Search Results</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Cover</th><th>Title</th><th>Author</th><th>Genre<th>Rating (Users)</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($books as $bk): ?>
                        <tr onclick="window.location.href='BookPage.php?isbn=<?=urlencode($bk['ISBN'])?>'">
                            <td class="text-center">
                                <?php if (!empty($bk['CoverPicture'])): ?>
                                    <img src="<?=htmlspecialchars($bk['CoverPicture'])?>" alt="Book Cover" class="book-cover">
                                <?php else: ?>
                                    <span class="text-muted">No Cover</span>
                                <?php endif; ?>
                            </td>
                            <td><?=htmlspecialchars($bk['Title'])?></td>
                            <td><?=htmlspecialchars($bk['AuthorName'] ?? 'Unknown')?></td>
                            <td><?=htmlspecialchars($bk['Genre'] ?? 'N/A')?></td>
                            <td>
                                <?php if ($bk['Rating']): ?>
                                    <?= number_format($bk['Rating'], 1) ?> ⭐ (<?= $bk['NumRatings'] ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?borrow=<?=urlencode($bk['ISBN'])?>" class="btn btn-sm btn-success" onclick="event.stopPropagation()">Borrow</a>
                                <a href="?reserve=<?=urlencode($bk['ISBN'])?>" class="btn btn-sm btn-info" onclick="event.stopPropagation()">Reserve</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <h4 class="mt-5">Reserved Books</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Cover</th><th>Title</th><th>Author</th><th>Date Reserved</th><th>Genre</th><th>Rating (Users)</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($reserved_books as $rb): ?>
                        <tr onclick="window.location.href='BookPage.php?isbn=<?=urlencode($rb['ISBN'])?>'">
                            <td class="text-center">
                                <?php if (!empty($rb['CoverPicture'])): ?>
                                    <img src="<?=htmlspecialchars($rb['CoverPicture'])?>" alt="Book Cover" class="book-cover">
                                <?php else: ?>
                                    <span class="text-muted">No Cover</span>
                                <?php endif; ?>
                            </td>
                            <td><?=htmlspecialchars($rb['Title'])?></td>
                            <td><?=htmlspecialchars($rb['AuthorName'] ?? 'Unknown')?></td>
                            <td><?=$rb['ReservationDate']?></td>
                            <td><?=htmlspecialchars($rb['Genre'] ?? 'N/A')?></td>
                            <td>
                                <?php if ($bk['Rating']): ?>
                                    <?= number_format($bk['Rating'], 1) ?> ⭐ (<?= $bk['NumRatings'] ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?borrow_reserved=<?=urlencode($rb['CopyID'])?>" class="btn btn-sm btn-success" onclick="event.stopPropagation()">Borrow</a>
                                <a href="?cancel=<?=$rb['ResID']?>" class="btn btn-sm btn-danger" onclick="event.stopPropagation()">Cancel</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reserved_books)) echo "<tr><td colspan='7' class='text-center text-muted'>No reserved books.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

            <h4 class="mt-5">Wishlist</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Cover</th><th>Title</th><th>Author</th><th>Genre</th><th>Rating (Users)</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($wishlist_books as $wb): ?>
                        <tr onclick="window.location.href='BookPage.php?isbn=<?=urlencode($wb['ISBN'])?>'">
                            <td class="text-center">
                                <?php if (!empty($wb['CoverPicture'])): ?>
                                    <img src="<?=htmlspecialchars($wb['CoverPicture'])?>" alt="Book Cover" class="book-cover">
                                <?php else: ?>
                                    <span class="text-muted">No Cover</span>
                                <?php endif; ?>
                            </td>
                            <td><?=htmlspecialchars($wb['Title'])?></td>
                            <td><?=htmlspecialchars($wb['AuthorName'] ?? 'Unknown')?></td>
                            <td><?=htmlspecialchars($wb['Genre'] ?? 'N/A')?></td>
                            <td>
                                <?php if ($bk['Rating']): ?>
                                    <?= number_format($bk['Rating'], 1) ?> ⭐ (<?= $bk['NumRatings'] ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?borrow=<?=urlencode($wb['ISBN'])?>" class="btn btn-sm btn-success" onclick="event.stopPropagation()">Borrow</a>
                                <a href="?reserve=<?=urlencode($wb['ISBN'])?>" class="btn btn-sm btn-info" onclick="event.stopPropagation()">Reserve</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($wishlist_books)) echo "<tr><td colspan='6' class='text-center text-muted'>Your wishlist is empty.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

            <h4 class="mt-5">Borrow History</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Cover</th><th>Title</th><th>Author</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Fine</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $limit = 5; 
                        $count = 0; 
                        foreach ($borrow_history as $bh): 
                            $count++;
                            $hidden = ($count > $limit) ? 'd-none extra-row' : '';
                        ?>
                            <tr class="<?= $hidden ?>" onclick="window.location.href='BookPage.php?isbn=<?=urlencode($bh['ISBN'])?>'">
                                <td class="text-center">
                                    <?php if (!empty($bh['CoverPicture'])): ?>
                                        <img src="<?=htmlspecialchars($bh['CoverPicture'])?>" alt="Book Cover" class="book-cover">
                                    <?php else: ?>
                                        <span class="text-muted">No Cover</span>
                                    <?php endif; ?>
                                </td>
                                <td><?=htmlspecialchars($bh['Title'])?></td>
                                <td><?=htmlspecialchars($bh['AuthorName'] ?? 'Unknown')?></td>
                                <td><?=$bh['Borrow_Date']?></td>
                                <td><?=$bh['Due_Date']?></td>
                                <td><?=$bh['Return_Date'] ?? 'Not Returned'?></td>
                                <td><?=($bh['Fine'] > 0) ? "₹".$bh['Fine'] : '-'?></td>
                                <td>
                                    <?php if ($bh['Return_Date'] === null): ?>
                                        <a href="?return=<?=$bh['CopyID']?>" class="btn btn-sm btn-warning" onclick="event.stopPropagation()">Return</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($borrow_history)) echo "<tr><td colspan='8' class='text-center text-muted'>No borrow history.</td></tr>"; ?>

                    </tbody>
                </table>
                         <?php if (count($borrow_history) > $limit): ?>
                            <div class="text-center mt-3">
                                <button id="seeAllBtn" class="btn btn-primary btn-sm"> See All </button>
                            </div>
                        <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
            if (arrow) arrow.textContent = isExpanded ? '>' : 'v';
            sublist.hidden = isExpanded;
            sublist.classList.toggle('show');
        }
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keyup', function(event) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 500);
        });
        
        searchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                clearTimeout(searchTimeout);
                document.getElementById('searchForm').submit();
            }
        });

        document.getElementById('seeAllBtn')?.addEventListener('click', function() {
            document.querySelectorAll('.extra-row').forEach(row => row.classList.remove('d-none'));
            this.style.display = 'none'; // Hide button after expanding
        });

        const toggleBtn = document.getElementById('toggleHistoryBtn');
        if (toggleBtn) {
            let expanded = false;
            toggleBtn.addEventListener('click', function() {
                const rows = document.querySelectorAll('.extra-row');
                if (!expanded) {
                    // Show hidden rows
                    rows.forEach(row => row.classList.remove('d-none'));
                    toggleBtn.textContent = "Hide";
                    expanded = true;
                } else {
                    // Hide rows again
                    rows.forEach(row => row.classList.add('d-none'));
                    toggleBtn.textContent = "See All";
                    expanded = false;
                }
            });
        }
    </script>
</body>
</html>