<?php
// sideSection.php

// Ensure this file is only included and not accessed directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    die("Direct access to this file is not allowed.");
}

// Ensure database connection is available
if (!isset($con)) {
    // This assumes db_config.php is in a parent directory.
    require_once 'crud/db_config.php';
}

// Fetch all sections and their shelves for display in the sidebar
$sections = [];
$sql = "SELECT SectionID, Name FROM Library_Sections ORDER BY Name";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sections[$row['SectionID']] = [
            'name' => $row['Name'],
            'shelves' => []
        ];
    }
}

$sql_shelves = "SELECT ShelfID, SectionID, Topic FROM Shelf ORDER BY SectionID, Topic";
$result_shelves = $con->query($sql_shelves);

if ($result_shelves && $result_shelves->num_rows > 0) {
    while ($row = $result_shelves->fetch_assoc()) {
        if (isset($sections[$row['SectionID']])) {
            $sections[$row['SectionID']]['shelves'][] = $row;
        }
    }
}
?>

<style>

    .sidebar-right {
    width: 300px;
    background-color: #f8f9fa; /* Light theme background */
    color: #333; /* Text color */
    padding: 20px;
    border-left: 1px solid #ddd; /* Border color */
    overflow-y: auto;
    position: fixed;
    right: -300px;
    top: 0;
    height: 100vh;
    transition: right 0.3s ease-in-out;
    z-index: 1040;
}

.sidebar-right.active {
    right: 0;
}

.sidebar-right h4 {
    color: #7b3fbf; /* Highlight color */
    font-family: 'Montserrat', sans-serif;
}

.search-input {
    margin-bottom: 20px;
}

.sidebar-list {
    list-style: none;
    padding-left: 0;
}

.section-item {
    margin-bottom: 15px;
}

.section-item h5 {
    color: #333; /* Dark text for section headers */
    font-size: 1.1rem;
    font-weight: bold;
}

.shelf-list {
    list-style: none;
    padding-left: 20px;
    font-size: 0.9rem;
    color: #666; /* Lighter text for shelf items */
}

.shelf-item {
    margin-top: 5px;
}

</style>

<div id="sidebar-right" class="sidebar-right">
    <h4>Library Sections & Shelves</h4>
    <input type="text" id="sidebarSearchBar" class="form-control search-input" placeholder="Search sections and shelves...">
    
    <div id="sidebarContent">
        <?php if (empty($sections)): ?>
            <p class="text-muted">No sections or shelves available.</p>
        <?php else: ?>
            <ul class="sidebar-list">
                <?php foreach ($sections as $id => $data): ?>
                    <li class="section-item" data-section-name="<?php echo htmlspecialchars(strtolower($data['name'])); ?>">
                        <h5>
                            <a class="section-link text-decoration-none text-dark">
                                <?php echo htmlspecialchars($data['name']); ?> (ID: <?php echo htmlspecialchars($id); ?>)
                            </a>
                        </h5>
                        <?php if (!empty($data['shelves'])): ?>
                            <ul class="shelf-list">
                                <?php foreach ($data['shelves'] as $shelf): ?>
                                    <li class="shelf-item" data-shelf-topic="<?php echo htmlspecialchars(strtolower($shelf['Topic'])); ?>">
                                        <a href="#" class="shelf-link text-decoration-none text-secondary">
                                            Shelf ID: <?php echo $shelf['ShelfID']; ?> - Topic: <?php echo htmlspecialchars($shelf['Topic']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
    // Sidebar search functionality
    document.getElementById('sidebarSearchBar').addEventListener('keyup', function() {
        const query = this.value.toLowerCase();
        const sectionItems = document.querySelectorAll('.sidebar-list .section-item');

        sectionItems.forEach(sectionItem => {
            const sectionName = sectionItem.getAttribute('data-section-name');
            const shelfItems = sectionItem.querySelectorAll('.shelf-item');
            let foundInShelves = false;

            shelfItems.forEach(shelfItem => {
                const shelfTopic = shelfItem.getAttribute('data-shelf-topic');
                const matchesShelf = shelfTopic.includes(query);
                shelfItem.style.display = matchesShelf ? 'block' : 'none';
                if (matchesShelf) {
                    foundInShelves = true;
                }
            });

            const matchesSection = sectionName.includes(query);

            if (matchesSection || foundInShelves) {
                sectionItem.style.display = 'block';
            } else {
                sectionItem.style.display = 'none';
            }
        });
    });
</script>