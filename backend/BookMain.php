<?php
// BookMain.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}
require_once '../backend/crud/db_config.php';

$user_id    = $_SESSION['UserID'];
$user_role  = $_SESSION['membershipType'];

if ($user_role !== 'admin') {
    exit("Access denied. Admins only.");
}

// Fetch active (unresolved) maintenance logs
$sql_active = "
    SELECT ml.LogID, ml.CopyID, bc.ISBN, b.Title, ml.DateReported, ml.IssueDescription, ml.IsResolved
    FROM MaintenanceLog ml
    JOIN BookCopy bc ON ml.CopyID = bc.CopyID
    JOIN Books b ON bc.ISBN = b.ISBN
    WHERE ml.IsResolved = FALSE
    ORDER BY ml.DateReported DESC, ml.LogID DESC
";
$active = $con->query($sql_active);

// Fetch history (resolved) maintenance logs
$sql_hist = "
    SELECT ml.LogID, ml.CopyID, bc.ISBN, b.Title, ml.DateReported, ml.IssueDescription, ml.IsResolved
    FROM MaintenanceLog ml
    JOIN BookCopy bc ON ml.CopyID = bc.CopyID
    JOIN Books b ON bc.ISBN = b.ISBN
    WHERE ml.IsResolved = TRUE
    ORDER BY ml.DateReported DESC, ml.LogID DESC
";
$history = $con->query($sql_hist);

// Optional toast message
$msg = $_GET['msg'] ?? '';
$typ = $_GET['type'] ?? 'success';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LibGinie - Book Maintenance</title>
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

        /* sidebarstart (keep this identical to your pages) */
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

        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .search-bar {
            width: 320px;
        }
        .table thead th {
            background-color: #f1f1f1;
        }
        .badge-status {
            font-size: 0.9rem;
        }
        .badge-Available { background-color: #28a745; }
        .badge-Borrowed { background-color: #0d6efd; }
        .badge-Reserved { background-color: #6c757d; }
        .badge-Maintenance { background-color: #fd7e14; }

        .small-input { max-width: 280px; }
    </style>
</head>
<body>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <main class="container mt-4">
            <h1 class="mb-4 text-center">Book Maintenance</h1>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="maintTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="search-tab" data-bs-toggle="tab" data-bs-target="#tab-search" type="button" role="tab">Search Copies</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#tab-active" type="button" role="tab">In Maintenance (Active)</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">History (Resolved)</button>
                </li>
            </ul>

            <div class="tab-content pt-3">
                <!-- Search Tab -->
                <div class="tab-pane fade show active" id="tab-search" role="tabpanel" aria-labelledby="search-tab">
                    <div class="header-controls">
                        <div class="search-bar">
                            <input type="text" id="copySearchInput" class="form-control" placeholder="Search by title, author or ISBN..." />
                        </div>
                        <div class="d-flex gap-2">
                            <select id="statusFilter" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="Available">Available</option>
                                <option value="Borrowed">Borrowed</option>
                                <option value="Reserved">Reserved</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 90px;">CopyID</th>
                                    <th style="width: 160px;">ISBN</th>
                                    <th>Title</th>
                                    <th style="width: 140px;">Status</th>
                                    <th style="width: 420px;">Maintenance Action</th>
                                </tr>
                            </thead>
                            <tbody id="searchResultsBody">
                                <tr><td colspan="5" class="text-center text-muted">Start typing to search…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Active Tab -->
                <div class="tab-pane fade" id="tab-active" role="tabpanel" aria-labelledby="active-tab">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>LogID</th>
                                    <th>CopyID</th>
                                    <th>ISBN</th>
                                    <th>Title</th>
                                    <th>Date Reported</th>
                                    <th>Issue</th>
                                    <th>Resolve</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($active && $active->num_rows > 0): ?>
                                    <?php while ($row = $active->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['LogID']) ?></td>
                                            <td><?= htmlspecialchars($row['CopyID']) ?></td>
                                            <td><?= htmlspecialchars($row['ISBN']) ?></td>
                                            <td><?= htmlspecialchars($row['Title']) ?></td>
                                            <td><?= htmlspecialchars($row['DateReported']) ?></td>
                                            <td><?= htmlspecialchars($row['IssueDescription']) ?></td>
                                            <td>
                                                <form method="POST" action="maintenance.php" class="d-inline">
                                                    <input type="hidden" name="action" value="resolve">
                                                    <input type="hidden" name="log_id" value="<?= htmlspecialchars($row['LogID']) ?>">
                                                    <input type="hidden" name="copy_id" value="<?= htmlspecialchars($row['CopyID']) ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fa-solid fa-check"></i> Mark Resolved
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted">No active maintenance records.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- History Tab -->
                <div class="tab-pane fade" id="tab-history" role="tabpanel" aria-labelledby="history-tab">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>LogID</th>
                                    <th>CopyID</th>
                                    <th>ISBN</th>
                                    <th>Title</th>
                                    <th>Date Reported</th>
                                    <th>Issue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history && $history->num_rows > 0): ?>
                                    <?php while ($row = $history->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['LogID']) ?></td>
                                            <td><?= htmlspecialchars($row['CopyID']) ?></td>
                                            <td><?= htmlspecialchars($row['ISBN']) ?></td>
                                            <td><?= htmlspecialchars($row['Title']) ?></td>
                                            <td><?= htmlspecialchars($row['DateReported']) ?></td>
                                            <td><?= htmlspecialchars($row['IssueDescription']) ?></td>
                                            <td><span class="badge bg-secondary">Resolved</span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted">No maintenance history yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= htmlspecialchars($typ) ?> mt-3" role="alert">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>
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

        // Debounced search + Enter
        const $search = $('#copySearchInput');
        const $status = $('#statusFilter');
        const $tbody = $('#searchResultsBody');
        let t;
        const delay = 300;

        function badge(status) {
            return `<span class="badge badge-status text-light badge-${status}">${status}</span>`;
        }

        function renderRows(rows) {
            if (!rows || rows.length === 0) {
                $tbody.html(`<tr><td colspan="5" class="text-center text-muted">No copies found.</td></tr>`);
                return;
            }
            let html = '';
            rows.forEach(r => {
                const maintBtn = (r.Status === 'Available')
                    ? `
                    <form method="POST" action="maintenance.php" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="action" value="issue">
                        <input type="hidden" name="copy_id" value="${r.CopyID}">
                        <input type="text" name="issue" class="form-control form-control-sm small-input" placeholder="Issue description" required>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fa-solid fa-screwdriver-wrench"></i> Send to Maintenance
                        </button>
                    </form>`
                    : `<span class="text-muted">—</span>`;

                html += `
                    <tr>
                        <td>${r.CopyID}</td>
                        <td>${r.ISBN}</td>
                        <td>${r.Title} <br><small class="text-muted">by ${r.AuthorName}</small></td>
                        <td>${badge(r.Status)}</td>
                        <td>${maintBtn}</td>
                    </tr>
                `;
            });
            $tbody.html(html);
        }

        function doSearch() {
            const term = $search.val().trim();
            const st = $status.val();
            
            // Only search if there's a search term or a status filter selected
            if (term.length === 0 && st.length === 0) {
                $tbody.html(`<tr><td colspan="5" class="text-center text-muted">Start typing to search or select a status…</td></tr>`);
                return;
            }

            const url = new URL('../backend/crud/search_book_copies.php', window.location.href);
            url.searchParams.set('search', term);
            if (st) url.searchParams.set('status', st);

            fetch(url.toString())
                .then(r => r.json())
                .then(renderRows)
                .catch(() => $tbody.html(`<tr><td colspan="5" class="text-center text-danger">Error fetching copies.</td></tr>`));
        }

        $search.on('input', function() {
            clearTimeout(t);
            t = setTimeout(doSearch, delay);
        });
        $search.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                clearTimeout(t);
                doSearch();
            }
        });
        $status.on('change', function() {
            clearTimeout(t);
            doSearch();
        });
    </script>
</body>
</html>
