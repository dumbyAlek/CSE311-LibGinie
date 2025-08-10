-- Relation Schema

-- Create the database
CREATE DATABASE IF NOT EXISTS library_db;

-- Use the newly created database
USE library_db;

-- 1. Library_Sections: Partitions the library into sections.
CREATE TABLE IF NOT EXISTS Library_Sections (
    SectionID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(100) NOT NULL
);

-- 2. Shelf: Physical shelves where books are stored.
CREATE TABLE IF NOT EXISTS Shelf (
    ShelfID INT PRIMARY KEY AUTO_INCREMENT,
    SectionID INT,
    Topic VARCHAR(100),
    FOREIGN KEY (SectionID) REFERENCES Library_Sections(SectionID)
);

-- 3. Members: The base table for all users of the library system.
CREATE TABLE IF NOT EXISTS Members (
    UserID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(100) NOT NULL,
    Email VARCHAR(100) UNIQUE NOT NULL,
    MembershipType VARCHAR(20) NOT NULL,
    PhoneNumber VARCHAR(20),
    Address_Street VARCHAR(150),
    Address_City VARCHAR(100),
    Address_ZIP VARCHAR(10)
);

-- 4. Guest: A specialization of Members for temporary users.
CREATE TABLE IF NOT EXISTS Guest (
    UserID INT PRIMARY KEY,
    LoginTime DATETIME,
    FOREIGN KEY (UserID) REFERENCES Members(UserID)
);

-- 5. Registered: A specialization of Members for permanent users.
CREATE TABLE IF NOT EXISTS Registered (
    UserID INT PRIMARY KEY,
    RegistrationDate DATE,
    FOREIGN KEY (UserID) REFERENCES Members(UserID)
);

-- 6. Employee: A specialization of Members for staff.
CREATE TABLE IF NOT EXISTS Employee (
    UserID INT PRIMARY KEY,
    EmployeeID VARCHAR(50) UNIQUE,
    start_date DATE,
    FOREIGN KEY (UserID) REFERENCES Members(UserID)
);

-- 7. General: A specialization of Registered for general members.
CREATE TABLE IF NOT EXISTS General (
    UserID INT PRIMARY KEY,
    Occupation VARCHAR(100),
    FOREIGN KEY (UserID) REFERENCES Registered(UserID)
);

-- 8. Student: A specialization of Registered.
CREATE TABLE IF NOT EXISTS Student (
    UserID INT PRIMARY KEY,
    StudentID VARCHAR(50) UNIQUE,
    Institution VARCHAR(100),
    FOREIGN KEY (UserID) REFERENCES Registered(UserID)
);

-- 9. Teacher: A specialization of Registered.
CREATE TABLE IF NOT EXISTS Teacher (
    UserID INT PRIMARY KEY,
    TeacherID VARCHAR(50) UNIQUE,
    Institution VARCHAR(100),
    FOREIGN KEY (UserID) REFERENCES Registered(UserID)
);

-- 10. Author: A specialization of Registered.
CREATE TABLE IF NOT EXISTS Author (
    UserID INT PRIMARY KEY,
    AuthorID INT AUTO_INCREMENT UNIQUE,
    AuthorTitle VARCHAR(50),
    AuthorBio TEXT,
    FOREIGN KEY (UserID) REFERENCES Registered(UserID)
);

-- 11. Admin: A specialization of Employee.
CREATE TABLE IF NOT EXISTS Admin (
    UserID INT PRIMARY KEY,
    AdminID VARCHAR(50) UNIQUE,
    FOREIGN KEY (UserID) REFERENCES Employee(UserID)
);

-- 12. Librarian: A specialization of Employee.
CREATE TABLE IF NOT EXISTS Librarian (
    UserID INT PRIMARY KEY,
    LibrarianID VARCHAR(50) UNIQUE,
    InChargeOf VARCHAR(100),
    FOREIGN KEY (UserID) REFERENCES Employee(UserID)
);

-- 13. Main Books table (superclass)
CREATE TABLE IF NOT EXISTS Books (
    ISBN VARCHAR(20) PRIMARY KEY,
    AuthorID INT,
    SectionID INT,
    Title VARCHAR(200) NOT NULL,
    Category VARCHAR(50),
    Publisher VARCHAR(100),
    PublishedYear YEAR,
    CoverPicture VARCHAR(255),
    FOREIGN KEY (AuthorID) REFERENCES Author(AuthorID),
    FOREIGN KEY (SectionID) REFERENCES Library_Sections(SectionID)
);

-- 14. TextBook table (specialization of Books)
CREATE TABLE IF NOT EXISTS TextBook (
    ISBN VARCHAR(20) PRIMARY KEY,
    Editions INT,
    Subject VARCHAR(20),
    FOREIGN KEY (ISBN) REFERENCES Books(ISBN)
);

-- 15. Comics table (specialization of Books)
CREATE TABLE IF NOT EXISTS Comics (
    ISBN VARCHAR(20) PRIMARY KEY,
    Artist VARCHAR(100),
    Studio VARCHAR(100),
    FOREIGN KEY (ISBN) REFERENCES Books(ISBN)
);

-- 16. Novels table (specialization of Books)
CREATE TABLE IF NOT EXISTS Novels (
    ISBN VARCHAR(20) PRIMARY KEY,
    Narration  VARCHAR(20),
    FOREIGN KEY (ISBN) REFERENCES Books(ISBN)
);

-- 17. Magazines table (specialization of Books)
CREATE TABLE IF NOT EXISTS Magazines (
    ISBN VARCHAR(20) PRIMARY KEY,
    Timeline VARCHAR(50),
    FOREIGN KEY (ISBN) REFERENCES Books(ISBN)
);

-- 18. Genres table to store all possible genres
CREATE TABLE IF NOT EXISTS Genres (
    GenreID INT PRIMARY KEY AUTO_INCREMENT,
    GenreName VARCHAR(50) UNIQUE NOT NULL
);

-- 19. Junction table for Books and Genres
CREATE TABLE IF NOT EXISTS Book_Genres (
    ISBN VARCHAR(20),
    GenreID INT,
    PRIMARY KEY (ISBN, GenreID),
    FOREIGN KEY (ISBN) REFERENCES Books(ISBN),
    FOREIGN KEY (GenreID) REFERENCES Genres(GenreID)
);

-- 21. BookCopy: Individual physical copies of a book.
CREATE TABLE IF NOT EXISTS BookCopy (
    CopyID INT PRIMARY KEY AUTO_INCREMENT,
    ISBN VARCHAR(20),
    Status ENUM('Available', 'Borrowed', 'Reserved', 'Maintenance') DEFAULT 'Available',
    FOREIGN KEY (ISBN) REFERENCES Books(ISBN)
);

-- 22. Reservation: Tracking Reserved BookCopy
CREATE TABLE IF NOT EXISTS Reservation (
    ResID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT,
    CopyID INT,
    ReservationDate DATE,
    FOREIGN KEY (UserID) REFERENCES Members(UserID),
    FOREIGN KEY (CopyID) REFERENCES BookCopy(CopyID)
);

-- 23. Borrow: Tracking Borrowed Books
CREATE TABLE IF NOT EXISTS Borrow (
    BorrowID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT,
    CopyID INT,
    Borrow_Date DATE,
    Due_Date DATE,
    Return_Date DATE,
    FOREIGN KEY (UserID) REFERENCES Members(UserID),
    FOREIGN KEY (CopyID) REFERENCES BookCopy(CopyID)
);

-- 24. MaintenanceLog: Log for the Maintenance history/status
CREATE TABLE IF NOT EXISTS MaintenanceLog (
    LogID INT PRIMARY KEY AUTO_INCREMENT,
    CopyID INT,
    UserID INT,
    DateReported DATE,
    IssueDescription TEXT,
    IsResolved BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (CopyID) REFERENCES BookCopy(CopyID),
    FOREIGN KEY (UserID) REFERENCES Admin(UserID)
);

-- 25. LoginCredentials: To separately store password and handle logins
CREATE TABLE IF NOT EXISTS LoginCredentials (
    UserID INT,
    Email VARCHAR(100) UNIQUE NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    PRIMARY KEY (UserID, Email),
    FOREIGN KEY (UserID) REFERENCES Members(UserID),
    FOREIGN KEY (Email) REFERENCES Members(Email)
);

-- 26. Calculate NumBorrowed
CREATE VIEW MemberBorrowStats AS
SELECT
    m.UserID,
    m.Name,
    COUNT(b.BorrowID) AS NumBorrowed
FROM
    Members AS m
LEFT JOIN
    Borrow AS b ON m.UserID = b.UserID
WHERE
    b.Return_Date IS NULL
GROUP BY
    m.UserID, m.Name;

-- 27. Calculate Fine
CREATE VIEW LateBorrowings AS
SELECT
    BorrowID,
    UserID,
    CopyID,
    Borrow_Date,
    Due_Date,
    Return_Date,
    CASE
        WHEN Return_Date > Due_Date THEN DATEDIFF(Return_Date, Due_Date) * 50.00
        ELSE 0.00
    END AS FineAmount
FROM
    Borrow;

    
-- 28. Keep track of viewed books
CREATE TABLE BookViews (
    ViewID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    ISBN VARCHAR(13) NOT NULL,
    ViewDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Members(UserID),
    FOREIGN KEY (ISBN) REFERENCES Books(ISBN)
);

-- 29. Keep track of added books
CREATE TABLE IF NOT EXISTS BooksAdded (
    ISBN VARCHAR(20) NOT NULL,
    UserID INT NOT NULL,
    AddDate DATE NOT NULL,
    PRIMARY KEY (ISBN, UserID),
    FOREIGN KEY (ISBN) REFERENCES Books(ISBN),
    FOREIGN KEY (UserID) REFERENCES Admin(UserID)
);