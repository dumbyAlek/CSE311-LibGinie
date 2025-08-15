<?php 
// This file assumes the following variables are already set in the including file:
// $is_guest, $is_librarian, $user_role
?>

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
        <?php elseif (in_array($user_role, ['author', 'student', 'teacher', 'general'])) : ?>
        <li><a href="#">Request Book</a></li>
        <li><a href="#">Borrow and Reserve Books</a></li>
        <?php endif; ?>

        <?php if ($user_role === 'author') : ?>
        <li><a href="author_account.html">My Account</a></li>
        <?php endif; ?>
        
        <li class="collapsible-header" onclick="toggleSublist('categoryList')" aria-expanded="false" aria-controls="categoryList">
            <span class="arrow">></span> Categories
        </li>
        <ul class="sublist" id="categoryList" hidden>
            <li><a href="categories.php?category=Text Books">Text Books</a></li>
            <li><a href="categories.php?category=Comics">Comics</a></li>
            <li><a href="categories.php?category=Novels">Novels</a></li>
            <li><a href="categories.php?category=Magazines">Magazines</a></li>
            <li><a href="AllBooks.php">Browse All Books</a></li>
        </ul>

        <li><a href="BrowseGenre.php">Browse Books By Genres</a></li>
        <li><a href="settings.php">Settings</a></li>
        <li><a href="../backend/logout.php">Logout</a></li>
    </ul>
</nav>
<?php else: // Sidebar for Guest users only ?>
<nav class="sidebar closed" id="sidebar">
    <img src="../images/logo3.png" alt="Logo" class="logo" />
    <ul>
        <li><a href="signup.php">Sign Up</a></li>
        <li><a href="#" class="disabled-link">Reserved</a></li>
        <li><a href="#">Settings</a></li>
        <li><a href="login.php">Log In</a></li>
    </ul>
</nav>
<?php endif; ?>