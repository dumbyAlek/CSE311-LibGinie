-- Create the database
CREATE DATABASE IF NOT EXISTS test_db;
USE test_db;

-- Create a sample 'users' table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL
);

-- Create a sample 'books' table
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100),
    available BOOLEAN DEFAULT TRUE
);

-- Insert some dummy users
INSERT INTO users (name, email) VALUES
('Alice', 'alice@example.com'),
('Bob', 'bob@example.com');

-- Insert some dummy books to check
INSERT INTO books (title, author, available) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', TRUE),
('1984', 'George Orwell', FALSE);
