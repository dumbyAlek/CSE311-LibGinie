<?php 
// This file assumes the following variables are already set in the including file:
// $is_guest, $is_librarian, $user_role
?>

<?php if (!$is_guest) : // Main sidebar for all logged-in users ?>
<nav class="sidebar closed" id="sidebar">
    <a href="../pages/home.php"><img src="../images/logo3.png" alt="Logo" class="logo" /></a>

    <ul>
        <li><a href="../pages/dashboard.php">Dashboard</a></li>
        <li><a href="../pages/MyBooks.php">My Books</a></li>

        <?php if ($user_role === 'admin') : ?>
        <li><a href="BookMng.php">Book Management</a></li>
        <li><a href="BookMain.php">Book Maintenance</a></li>
        <li><a href="../pages/SecsNShelves.php">Sections & Shelves</a></li>
        <li><a href="MemMng.php">Member Management</a></li>
        <li><a href="EmpMng.php">Employee Management</a></li>
        <?php elseif (in_array($user_role, ['author', 'student', 'teacher', 'general'])) : ?>
        <li><a href="../pages/ReqBook.php">Request Book</a></li>
        <li><a href="../pages/BorrowNReserve.php">Borrow and Reserve Books</a></li>
        <?php endif; ?>

        <?php if ($user_role === 'author') : ?>
        <li><a href="author_account.html">My Account</a></li>
        <?php endif; ?>
        
        <li class="collapsible-header" onclick="toggleSublist('categoryList')" aria-expanded="false" aria-controls="categoryList">
            <span class="arrow">></span> Categories
        </li>
        <ul class="sublist" id="categoryList" hidden>
            <li><a href="../pages/categories.php?category=Text Books">Text Books</a></li>
            <li><a href="../pages/categories.php?category=Comics">Comics</a></li>
            <li><a href="../pages/categories.php?category=Novels">Novels</a></li>
            <li><a href="../pages/categories.php?category=Magazines">Magazines</a></li>
            <li><a href="../pages/AllBooks.php">Browse All Books</a></li>
        </ul>
        <li><a href="../pages/BrowseGenre.php">Browse Books By Genres</a></li>
        <li><a href="../pages/settings.php">Settings</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>
<?php else: // Sidebar for Guest users only ?>
<nav class="sidebar closed" id="sidebar">
    <img src="../images/logo3.png" alt="Logo" class="logo" />
    <ul>
        <li><a href="../pages/signup.php">Sign Up</a></li>
        <li><a href="../pages/loginn.php">Log In</a></li>
    </ul>
</nav>
<?php endif; ?>