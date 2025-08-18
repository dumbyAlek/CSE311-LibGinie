<?php
// Start the session
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: loginn.php");
    exit;
}

// Include your database configuration
require_once '../backend/crud/db_config.php';

// Get user data from the session
$user_id = $_SESSION['UserID'];
$user_name = $_SESSION['user_name'];
$user_role = isset($_SESSION['membershipType']) ? $_SESSION['membershipType'] : 'general';
$is_guest = ($user_role === 'guest');
$is_librarian = ($user_role === 'librarian');

// Initialize data arrays for personal stats and graphs
$booksReadCount = 0;
$favoritesCount = 0;
$booksViewedCount = 0;
$lastViewedBooks = [];

$borrowActivity = ['labels' => [], 'borrowData' => [], 'returnData' => [], 'viewData' => []];
$adminActivity = ['labels' => [], 'booksAdded' => [], 'maintenanceFixed' => []];
$authorActivity = ['labels' => [], 'borrowData' => [], 'viewData' => []];

try {
    // --- Personal Stats for ALL Members ---
    $stmt = $con->prepare("SELECT COUNT(*) AS total_read FROM BookInteractions WHERE UserID = ? AND IsRead = TRUE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $booksReadCount = $row['total_read'];
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(*) AS total_favorites FROM BookInteractions WHERE UserID = ? AND IsFavorite = TRUE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $favoritesCount = $row['total_favorites'];
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(DISTINCT ISBN) AS total_views FROM BookInteractions WHERE UserID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $booksViewedCount = $row['total_views'];
    }
    $stmt->close();

    // Fetch the last 3 books viewed by the user
    $stmt = $con->prepare("
        SELECT b.Title, b.ISBN, b.CoverPicture, m.Name AS AuthorName
        FROM BookInteractions AS bi
        JOIN Books AS b ON bi.ISBN = b.ISBN
        JOIN Author AS a ON b.AuthorID = a.AuthorID
        JOIN Members AS m ON a.UserID = m.UserID
        WHERE bi.UserID = ?
        ORDER BY bi.LastViewed DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lastViewedBooks = [];
    while ($row = $result->fetch_assoc()) {
        $lastViewedBooks[] = $row;
    }
    $stmt->close();
    
    // --- Bar Graph Data based on User Role ---
    if (in_array($user_role, ['student', 'teacher', 'general', 'librarian'])) {
        // Fetch Borrowed, Returned, and Viewed activity for the last 6 months
        $monthData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = (new DateTime())->sub(new DateInterval("P{$i}M"));
            $month = $date->format('Y-m');
            $monthData[$month] = ['borrow' => 0, 'return' => 0, 'view' => 0];
        }

        $stmt = $con->prepare("SELECT DATE_FORMAT(Borrow_Date, '%Y-%m') as month, COUNT(*) as borrow_count FROM Borrow WHERE UserID = ? AND Borrow_Date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $borrowResult = $stmt->get_result();
        while ($row = $borrowResult->fetch_assoc()) {
            $monthData[$row['month']]['borrow'] = $row['borrow_count'];
        }
        $stmt->close();

        $stmt = $con->prepare("SELECT DATE_FORMAT(Return_Date, '%Y-%m') as month, COUNT(*) as return_count FROM Borrow WHERE UserID = ? AND Return_Date IS NOT NULL AND Return_Date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $returnResult = $stmt->get_result();
        while ($row = $returnResult->fetch_assoc()) {
            $monthData[$row['month']]['return'] = $row['return_count'];
        }
        $stmt->close();
        
        $stmt = $con->prepare("SELECT DATE_FORMAT(LastViewed, '%Y-%m') as month, COUNT(DISTINCT ISBN) as view_count FROM BookInteractions WHERE UserID = ? AND LastViewed >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $viewResult = $stmt->get_result();
        while ($row = $viewResult->fetch_assoc()) {
            $monthData[$row['month']]['view'] = $row['view_count'];
        }
        $stmt->close();
        
        foreach ($monthData as $month => $counts) {
            $borrowActivity['labels'][] = date('M Y', strtotime($month));
            $borrowActivity['borrowData'][] = $counts['borrow'];
            $borrowActivity['returnData'][] = $counts['return'];
            $borrowActivity['viewData'][] = $counts['view'];
        }

    } elseif ($user_role === 'admin') {
            $stmt = $con->prepare("SELECT DATE_FORMAT(AddDate, '%Y-%m') as month, COUNT(*) as count FROM BooksAdded WHERE UserID = ? AND AddDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $booksAddedResult = $stmt->get_result();

            // Updated to use DateReported (since ResolvedDate doesn't exist in schema)
            $stmt = $con->prepare("SELECT DATE_FORMAT(DateReported, '%Y-%m') as month, COUNT(*) as count FROM MaintenanceLog WHERE IsResolved = TRUE AND UserID = ? AND DateReported >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $maintenanceFixedResult = $stmt->get_result();

            $monthData = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = (new DateTime())->sub(new DateInterval("P{$i}M"));
                $month = $date->format('Y-m');
                $monthData[$month] = ['booksAdded' => 0, 'maintenanceFixed' => 0];
            }

            while ($row = $booksAddedResult->fetch_assoc()) {
                if (isset($monthData[$row['month']])) {
                    $monthData[$row['month']]['booksAdded'] = (int)$row['count'];
                }
            }
            while ($row = $maintenanceFixedResult->fetch_assoc()) {
                if (isset($monthData[$row['month']])) {
                    $monthData[$row['month']]['maintenanceFixed'] = (int)$row['count'];
                }
            }

            foreach ($monthData as $month => $counts) {
                $adminActivity['labels'][] = date('M Y', strtotime($month));
                $adminActivity['booksAdded'][] = $counts['booksAdded'];
                $adminActivity['maintenanceFixed'][] = $counts['maintenanceFixed'];
            }
            $stmt->close();
    }
    
    // Additional author-specific graph data
    if ($user_role === 'author') {
            $stmt = $con->prepare("
                SELECT b.ISBN, b.Title, b.CoverPicture, COUNT(bor.BorrowID) AS TotalBorrows, COUNT(bi.InteractionID) AS TotalViews 
                FROM Books AS b 
                JOIN Author AS a ON b.AuthorID = a.AuthorID 
                LEFT JOIN BookCopy AS bc ON b.ISBN = bc.ISBN 
                LEFT JOIN Borrow AS bor ON bc.CopyID = bor.CopyID 
                LEFT JOIN BookInteractions AS bi ON b.ISBN = bi.ISBN 
                WHERE a.UserID = ? 
                GROUP BY b.ISBN 
                ORDER BY TotalBorrows DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $publishedResult = $stmt->get_result();

            $authorActivity = ['labels' => [], 'borrowData' => [], 'viewData' => []];
            while ($row = $publishedResult->fetch_assoc()) {
                $authorActivity['labels'][] = htmlspecialchars($row['Title']);
                $authorActivity['borrowData'][] = $row['TotalBorrows'];
                $authorActivity['viewData'][] = $row['TotalViews'];
            }
            $stmt->close();
    }

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LibGinie - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Reusing your CSS from settings.php for a consistent look */
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

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            margin: 40px 0 20px;
            color: #4d2600; /* Darker shade for title */
        }

        footer {
            background: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
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
        
        /* New and improved dashboard styles */
        .dashboard-welcome {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }
        .dashboard-welcome h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.5rem;
            color: #7b3fbf;
        }
        .dashboard-welcome p {
            font-size: 1.1rem;
            color: #555;
        }
        .dark-theme .dashboard-welcome {
            background-color: #222;
            color: #eee;
        }
        .dark-theme .dashboard-welcome p {
            color: #ccc;
        }
        .info-card {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
            border-left: 5px solid #7b3fbf; /* Accent color */
        }
        .dark-theme .info-card {
            background-color: #2e2e2e;
            color: #eee;
        }
        .info-card:hover {
            transform: translateY(-5px);
        }
        .info-card h4 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: #7b3fbf;
        }
        .info-card ul {
            list-style: none;
            padding-left: 0;
        }
        .info-card li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
            color: #555;
        }
        .dark-theme .info-card li {
            border-bottom: 1px solid #444;
            color: #ccc;
        }
        .info-card li:last-child {
            border-bottom: none;
        }
        .btn-custom {
            background-color: #7b3fbf;
            border-color: #7b3fbf;
            color: white;
            transition: background-color 0.3s;
        }
        .btn-custom:hover {
            background-color: #6a35a5;
            border-color: #6a35a5;
            color: white;
        }

        /* New container for the chart to limit its size */
        .chart-container {
            position: relative;
            height: 200px; /* Optional: Set a fixed height for consistency */
            width: 100%;
            max-width: 400px; /* Limits the chart's max width */
            margin: 0 auto; /* Centers the chart */
        }

        .book-list-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .book-link-item {
            text-decoration: none; /* Removes the underline */
            color: inherit; /* Inherits text color from parent to avoid blue link color */
            display: flex; /* Makes the link a flex container to use the layout properties */
            align-items: center; /* Vertically aligns the content */
        }

        .book-item {
            display: flex; /* This is no longer needed on the inner div but can be kept for clarity if you wish */
            align-items: center; /* Same as above */
            gap: 15px;
            width: 100%; /* Ensures the link fills the width */
        }

        .book-item:hover {
            transform: translateY(-5px);
        }

        .book-cover {
            width: 60px;
            height: auto;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .book-details {
            display: flex;
            flex-direction: column;
        }

        .book-title {
            font-weight: bold;
            color: #333;
        }

        .book-author {
            color: #666;
            font-size: 0.9em;
        }

        .book-list-item {
            display: flex;
            align-items: center;
            gap: 15px; /* Adds space between the image and the text */
            padding: 10px 0;
        }

        .book-link {
            text-decoration: none;
            color: inherit; /* Prevents the text from turning blue */
            display: flex; /* Makes the link a flex container */
            align-items: center;
            gap: 15px;
        }

        .book-link:hover {
            transform: translateY(-5px);
        }

        .book-cover-thumb {
            width: 50px; /* Adjust the width as needed */
            height: auto;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .book-details {
            display: flex;
            flex-direction: column;
        }

        .book-title-bold {
            font-weight: bold;
            color: #333;
        }

        .book-stats {
            font-size: 0.9em;
            color: #666;
        }

    </style>
</head>

<body>

    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <main class="container mt-4">
            <h3 class="section-title">Dashboard</h3>

            <?php switch ($user_role):
                case 'admin':
                        $totalMembers = $con->query("SELECT COUNT(*) FROM Members")->fetch_row()[0];
                        $totalBooks = $con->query("SELECT COUNT(*) FROM Books")->fetch_row()[0];
                        $overdueBooks = $con->query("SELECT COUNT(*) FROM Borrow WHERE Due_Date < CURDATE() AND Return_Date IS NULL")->fetch_row()[0];

                        $newMembersStmt = $con->prepare("SELECT m.Name, r.RegistrationDate FROM Members AS m JOIN Registered AS r ON m.UserID = r.UserID ORDER BY r.RegistrationDate DESC LIMIT 5");
                        $newMembersStmt->execute();
                        $newMembersResult = $newMembersStmt->get_result();

                        $newBooksStmt = $con->prepare("SELECT b.Title FROM BooksAdded AS ba JOIN Books AS b ON ba.ISBN = b.ISBN ORDER BY ba.AddDate DESC LIMIT 5");
                        $newBooksStmt->execute();
                        $newBooksResult = $newBooksStmt->get_result();

                        $maintenanceIssuesStmt = $con->prepare("SELECT IssueDescription, DateReported FROM MaintenanceLog WHERE IsResolved = FALSE ORDER BY DateReported DESC LIMIT 5");
                        $maintenanceIssuesStmt->execute();
                        $maintenanceIssuesResult = $maintenanceIssuesStmt->get_result();
                    ?>
                    <div class="dashboard-welcome">
                        <h1>Welcome, Admin!</h1>
                        <p>Your personalized library overview is here.</p>
                    </div>

                    <h4 class="section-title mt-5">My Personal Info</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-book-reader"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > Books Read </a></h4>
                                <p class="h2"><?php echo $booksReadCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-eye"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > Books Viewed </a></h4>
                                <p class="h2"><?php echo $booksViewedCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-heart"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > My Favorites</a></h4>
                                <p class="h2"><?php echo $favoritesCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-chart-bar"></i> System Activity</h4>
                                <div class="chart-container">
                                    <canvas id="adminActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="section-title mt-5">Recent Activity</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-card">
                                <h4><i class="fas fa-user-plus"></i> <a href="../backend/MemMng.php" style="text-decoration: none; color: #7b3fbf;" > New Members </a></h4>
                                <ul>
                                    <?php while ($row = $newMembersResult->fetch_assoc()): ?>
                                        <li>**<?php echo htmlspecialchars($row['Name']); ?>** - Joined: <?php echo htmlspecialchars($row['RegistrationDate']); ?></li>
                                    <?php endwhile; ?>
                                    <?php if ($newMembersResult->num_rows === 0) echo '<li>No new members.</li>'; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-card">
                                <h4><i class="fas fa-plus-circle"></i> <a href="AllBooks.php?orderby=newarrival" style="text-decoration: none; color: #7b3fbf;" > New Books </a></h4>
                                <ul>
                                    <?php while ($row = $newBooksResult->fetch_assoc()): ?>
                                        <li>**<?php echo htmlspecialchars($row['Title']); ?>**</li>
                                    <?php endwhile; ?>
                                    <?php if ($newBooksResult->num_rows === 0) echo '<li>No new books.</li>'; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-card">
                                <h4><i class="fas fa-tools"></i> <a href="../backend/BookMain.php" style="text-decoration: none; color: #7b3fbf;" > Maintenance Issues </a></h4>
                                <ul>
                                    <?php while ($row = $maintenanceIssuesResult->fetch_assoc()): ?>
                                        <li>**<?php echo htmlspecialchars($row['IssueDescription']); ?>** - Reported: <?php echo htmlspecialchars($row['DateReported']); ?></li>
                                    <?php endwhile; ?>
                                    <?php if ($maintenanceIssuesResult->num_rows === 0) echo '<li>No unresolved issues.</li>'; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php break;

                case 'librarian':
                        $overdueBooksStmt = $con->prepare("
                            SELECT m.Name AS MemberName, k.Title, b.Due_Date 
                            FROM Borrow AS b 
                            JOIN BookCopy AS c ON b.CopyID = c.CopyID 
                            JOIN Books AS k ON c.ISBN = k.ISBN 
                            JOIN Members AS m ON b.UserID = m.UserID 
                            WHERE b.Due_Date < CURDATE() AND b.Return_Date IS NULL 
                            LIMIT 5
                        ");
                        $overdueBooksStmt->execute();
                        $overdueResult = $overdueBooksStmt->get_result();

                        $reservedBooksStmt = $con->prepare("
                            SELECT k.Title, m.Name AS MemberName 
                            FROM Reservation AS r 
                            JOIN BookCopy AS c ON r.CopyID = c.CopyID 
                            JOIN Books AS k ON c.ISBN = k.ISBN 
                            JOIN Members AS m ON r.UserID = m.UserID 
                            WHERE r.UserID = ? 
                            LIMIT 5
                        ");
                        $reservedBooksStmt->bind_param("i", $user_id);
                        $reservedBooksStmt->execute();
                        $reservedResult = $reservedBooksStmt->get_result();
                    ?>
                    <div class="dashboard-welcome">
                        <h1>Welcome, Librarian!</h1>
                        <p>Your personalized library overview is here.</p>
                    </div>

                    <h4 class="section-title mt-5">My Personal Info</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-book-reader"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > Books Read </a></h4>
                                <p class="h2"><?php echo $booksReadCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-eye"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > Books Viewed </a></h4>
                                <p class="h2"><?php echo $booksViewedCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-heart"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > My Favorites </a></h4>
                                <p class="h2"><?php echo $favoritesCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-chart-bar"></i> My Activity (Last 6 Months)</h4>
                                <div class="chart-container">
                                    <canvas id="memberActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-history"></i> Last Viewed Books</h4>
                                <div class="book-list-container">
                                    <?php if (!empty($lastViewedBooks)): ?>
                                        <?php foreach ($lastViewedBooks as $book): ?>
                                            <a href="BookPage.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>" class="book-link-item">
                                                <div class="book-item">
                                                    <img src="<?php echo htmlspecialchars($book['CoverPicture']); ?>" alt="<?php echo htmlspecialchars($book['Title']); ?> Cover" class="book-cover">
                                                    <div class="book-details">
                                                        <span class="book-title"><?php echo htmlspecialchars($book['Title']); ?></span>
                                                        <span class="book-author">by <?php echo htmlspecialchars($book['AuthorName']); ?></span>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p>No books viewed yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="section-title mt-5">Library Operations</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-exclamation-triangle"></i> Overdue Books</h4>
                                <ul>
                                    <?php while ($row = $overdueResult->fetch_assoc()): ?>
                                        <li>**<?php echo htmlspecialchars($row['Title']); ?>** by <?php echo htmlspecialchars($row['MemberName']); ?> - Due: <?php echo htmlspecialchars($row['Due_Date']); ?></li>
                                    <?php endwhile; ?>
                                    <?php if ($overdueResult->num_rows === 0) echo '<li>No overdue books.</li>'; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-bell"></i> Pending Reservations</h4>
                                <ul>
                                    <?php while ($row = $reservedResult->fetch_assoc()): ?>
                                        <li>**<?php echo htmlspecialchars($row['Title']); ?>** by <?php echo htmlspecialchars($row['MemberName']); ?></li>
                                    <?php endwhile; ?>
                                    <?php if ($reservedResult->num_rows === 0) echo '<li>No pending reservations.</li>'; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php break;

                case 'author':
                        $publishedBooksStmt = $con->prepare("
                            SELECT b.ISBN, b.Title, b.CoverPicture, COUNT(bor.BorrowID) AS TotalBorrows, COUNT(bi.InteractionID) AS TotalViews 
                            FROM Books AS b 
                            JOIN Author AS a ON b.AuthorID = a.AuthorID 
                            LEFT JOIN BookCopy AS bc ON b.ISBN = bc.ISBN 
                            LEFT JOIN Borrow AS bor ON bc.CopyID = bor.CopyID 
                            LEFT JOIN BookInteractions AS bi ON b.ISBN = bi.ISBN 
                            WHERE a.UserID = ? 
                            GROUP BY b.ISBN 
                            ORDER BY TotalBorrows DESC
                        ");
                        $publishedBooksStmt->bind_param("i", $user_id);
                        $publishedBooksStmt->execute();
                        $publishedResult = $publishedBooksStmt->get_result();
                    ?>
                    <div class="dashboard-welcome">
                        <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
                        <p>Your personalized library overview is here.</p>
                    </div>
                    
                    <h4 class="section-title mt-5">My Personal Info</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-book-reader"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > Books Read </a></h4>
                                <p class="h2"><?php echo $booksReadCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-eye"></i><a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > Books Viewed </a></h4>
                                <p class="h2"><?php echo $booksViewedCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-heart"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > My Favorites</a></h4>
                                <p class="h2"><?php echo $favoritesCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-chart-bar"></i> My Activity (Last 6 Months)</h4>
                                <div class="chart-container">
                                    <canvas id="memberActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-history"></i> Last Viewed Books</h4>
                                <div class="book-list-container">
                                    <?php if (!empty($lastViewedBooks)): ?>
                                        <?php foreach ($lastViewedBooks as $book): ?>
                                            <a href="BookPage.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>" class="book-link-item">
                                                <div class="book-item">
                                                    <img src="<?php echo htmlspecialchars($book['CoverPicture']); ?>" alt="<?php echo htmlspecialchars($book['Title']); ?> Cover" class="book-cover">
                                                    <div class="book-details">
                                                        <span class="book-title"><?php echo htmlspecialchars($book['Title']); ?></span>
                                                        <span class="book-author">by <?php echo htmlspecialchars($book['AuthorName']); ?></span>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p>No books viewed yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="section-title mt-5">My Published Works</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-book-open"></i> Your Published Books</h4>
                                <ul>
                                    <?php while ($row = $publishedResult->fetch_assoc()): ?>
                                        <li class="book-list-item">
                                            <a href="BookPage.php?isbn=<?php echo htmlspecialchars($row['ISBN']); ?>" class="book-link">
                                                <img src="<?php echo htmlspecialchars($row['CoverPicture']); ?>" alt="<?php echo htmlspecialchars($row['Title']); ?> Cover" class="book-cover-thumb">
                                                <div class="book-details">
                                                    <span class="book-title-bold"><?php echo htmlspecialchars($row['Title']); ?></span>
                                                </div>
                                            </a>
                                        </li>
                                    <?php endwhile; ?>
                                    <?php if ($publishedResult->num_rows === 0) echo '<li>No books published yet.</li>'; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-chart-line"></i> Total Borrows & Views</h4>
                                <div class="chart-container">
                                    <canvas id="authorActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php break;

                case 'student':
                case 'teacher':
                case 'general':
                        $borrowedBooksStmt = $con->prepare("
                            SELECT k.Title, b.Due_Date 
                            FROM Borrow AS b 
                            JOIN BookCopy AS c ON b.CopyID = c.CopyID 
                            JOIN Books AS k ON c.ISBN = k.ISBN 
                            WHERE b.UserID = ? AND b.Return_Date IS NULL 
                            LIMIT 5
                        ");
                        $borrowedBooksStmt->bind_param("i", $user_id);
                        $borrowedBooksStmt->execute();
                        $borrowedResult = $borrowedBooksStmt->get_result();

                        $reservedBooksStmt = $con->prepare("
                            SELECT k.Title, m.Name AS MemberName 
                            FROM Reservation AS r 
                            JOIN BookCopy AS c ON r.CopyID = c.CopyID 
                            JOIN Books AS k ON c.ISBN = k.ISBN 
                            JOIN Members AS m ON r.UserID = m.UserID 
                            WHERE r.UserID = ? 
                            LIMIT 5
                        ");
                        $reservedBooksStmt->bind_param("i", $user_id);
                        $reservedBooksStmt->execute();
                        $reservedResult = $reservedBooksStmt->get_result();
                    ?>
                    <div class="dashboard-welcome">
                        <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
                        <p>Your personalized library overview is here.</p>
                    </div>

                    <h4 class="section-title mt-5">My Personal Info</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-book-reader"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > Books Read </a></h4>
                                <p class="h2"><?php echo $booksReadCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-eye"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > Books Viewed </a></h4>
                                <p class="h2"><?php echo $booksViewedCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-heart"></i> <a href="MyBooks.php" style="text-decoration: none; color: #7b3fbf;" > My Favorites</a></h4>
                                <p class="h2"><?php echo $favoritesCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-card">
                                <h4><i class="fas fa-chart-bar"></i> My Activity (Last 6 Months)</h4>
                                <div class="chart-container">
                                    <canvas id="memberActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-history"></i> Last Viewed Books</h4>
                                <div class="book-list-container">
                                    <?php if (!empty($lastViewedBooks)): ?>
                                        <?php foreach ($lastViewedBooks as $book): ?>
                                            <a href="BookPage.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>" class="book-link-item">
                                                <div class="book-item">
                                                    <img src="<?php echo htmlspecialchars($book['CoverPicture']); ?>" alt="<?php echo htmlspecialchars($book['Title']); ?> Cover" class="book-cover">
                                                    <div class="book-details">
                                                        <span class="book-title"><?php echo htmlspecialchars($book['Title']); ?></span>
                                                        <span class="book-author">by <?php echo htmlspecialchars($book['AuthorName']); ?></span>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p>No books viewed yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="section-title mt-5">My Library Status</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-book"></i> <a href="BorrowNReserve.php" style="text-decoration: none; color: #7b3fbf;" >Currently Borrowed Books </a></h4>
                                <ul>
                                    <?php while ($row = $borrowedResult->fetch_assoc()): ?>
                                        <li>**<?php echo htmlspecialchars($row['Title']); ?>** - Due: <?php echo htmlspecialchars($row['Due_Date']); ?></li>
                                    <?php endwhile; ?>
                                    <?php if ($borrowedResult->num_rows === 0) echo '<li>You have no books currently borrowed.</li>'; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4><i class="fas fa-bookmark"></i> <a href="BorrowNReserve.php" style="text-decoration: none; color: #7b3fbf;" > Reserved Books </a></h4>
                                <ul>
                                    <?php while ($row = $reservedResult->fetch_assoc()): ?>
                                        <li>**<?php echo htmlspecialchars($row['Title']); ?>**</li>
                                    <?php endwhile; ?>
                                    <?php if ($reservedResult->num_rows === 0) echo '<li>You have no books currently reserved.</li>'; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php break;

                default: ?>
                    <div class="dashboard-welcome">
                        <h1>Welcome to LibGinie</h1>
                        <p>Please log in to view your personalized dashboard.</p>
                        <a href="loginn.php" class="btn btn-custom">Log In</a>
                    </div>
                <?php break;
            endswitch; ?>
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
        
        // General Member Activity Chart
        const memberCtx = document.getElementById('memberActivityChart');
        if (memberCtx) {
            const chartLabels = <?php echo json_encode($borrowActivity['labels']); ?>;
            const borrowData = <?php echo json_encode($borrowActivity['borrowData']); ?>;
            const returnData = <?php echo json_encode($borrowActivity['returnData']); ?>;
            const viewData = <?php echo json_encode($borrowActivity['viewData']); ?>;

            new Chart(memberCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Books Borrowed',
                        data: borrowData,
                        backgroundColor: '#7b3fbf',
                        borderColor: '#6a35a5',
                        borderWidth: 1
                    }, {
                        label: 'Books Returned',
                        data: returnData,
                        backgroundColor: '#4CAF50', // Green for returns
                        borderColor: '#45a049',
                        borderWidth: 1
                    }, {
                        label: 'Books Viewed',
                        data: viewData,
                        backgroundColor: '#FFEB3B', // Yellow for views
                        borderColor: '#FBC02D',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // Admin Activity Chart
        const adminCtx = document.getElementById('adminActivityChart');
        if (adminCtx) {
            const chartLabels = <?php echo json_encode($adminActivity['labels']); ?>;
            const booksAddedData = <?php echo json_encode($adminActivity['booksAdded']); ?>;
            const maintenanceFixedData = <?php echo json_encode($adminActivity['maintenanceFixed']); ?>;

            new Chart(adminCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Books Added',
                        data: booksAddedData,
                        backgroundColor: '#7b3fbf',
                        borderColor: '#6a35a5',
                        borderWidth: 1
                    }, {
                        label: 'Maintenance Fixed',
                        data: maintenanceFixedData,
                        backgroundColor: '#ffc107', // Yellow for maintenance
                        borderColor: '#e0a800',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // Author Activity Chart
        const authorCtx = document.getElementById('authorActivityChart');
        if (authorCtx) {
            const chartLabels = <?php echo json_encode($authorActivity['labels']); ?>;
            const borrowData = <?php echo json_encode($authorActivity['borrowData']); ?>;
            const viewData = <?php echo json_encode($authorActivity['viewData']); ?>;

            new Chart(authorCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Total Borrows',
                        data: borrowData,
                        backgroundColor: '#7b3fbf',
                        borderColor: '#6a35a5',
                        borderWidth: 1
                    }, {
                        label: 'Total Views',
                        data: viewData,
                        backgroundColor: '#FFC107',
                        borderColor: '#E0A800',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>