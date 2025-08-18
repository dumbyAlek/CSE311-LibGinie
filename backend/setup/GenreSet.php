<?php
// genreSet.php

// Include your database configuration
require_once 'db_config.php';

// Array of default genre names to add
$default_genres = [
    'Fantasy',
    'Science Fiction',
    'Mystery',
    'Thriller',
    'Romance',
    'Horror',
    'Historical Fiction',
    'Biography',
    'Self-Help',
    'Cookbook',
    'Children\'s',
    'Young Adult',
    'Poetry',
    'Drama',
    'Action and Adventure',
    'Graphic Novel',
    'Adult Fiction',
    'Superhero',
    'Tragedy',
    'Comedy',
    'Classic'
];

echo "<h2>Setting up default genres...</h2>";

foreach ($default_genres as $genre) {
    // Sanitize the genre name to prevent SQL injection
    $safe_genre = $con->real_escape_string($genre);

    // SQL query to check if the genre already exists
    $check_sql = "SELECT GenreID FROM Genres WHERE GenreName = '$safe_genre'";
    $result = $con->query($check_sql);

    if ($result->num_rows == 0) {
        // Genre does not exist, so insert it
        $insert_sql = "INSERT INTO Genres (GenreName) VALUES ('$safe_genre')";
        if ($con->query($insert_sql) === TRUE) {
            echo "✅ Successfully added genre: '{$genre}'<br>";
        } else {
            echo "❌ Error adding genre '{$genre}': " . $con->error . "<br>";
        }
    } else {
        // Genre already exists
        echo "ℹ️ Genre '{$genre}' already exists. Skipping.<br>";
    }
}

// Close the database connection
$con->close();

echo "<h3>Default genre setup complete.</h3>";
?>