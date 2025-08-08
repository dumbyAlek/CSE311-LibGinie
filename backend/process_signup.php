<?php
session_start();

// Check if the form was submitted and the session data exists
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['signup_data'])) {

    // --- 1. GATHER DATA FROM BOTH FORMS ---
    $signup_data = $_SESSION['signup_data'];
    $name = $signup_data['name'];
    $email = $signup_data['email'];
    $membershipType = $signup_data['membershipType'];
    $phone = $signup_data['phone'];
    $street = $signup_data['street'];
    $city = $signup_data['city'];
    $zip = $signup_data['zip'];
    $password = $signup_data['password'];

    // Data from the second form (submitted via POST)
    $extra_data = [];
    if ($membershipType === 'author') {
        $extra_data['authorTitle'] = $_POST['authorTitle'] ?? null;
        $extra_data['authorBio'] = $_POST['authorBio'] ?? null;
    } elseif ($membershipType === 'general') {
        $extra_data['occupation'] = $_POST['occupation'] ?? null;
    } elseif ($membershipType === 'student') {
        $extra_data['studentId'] = $_POST['studentId'] ?? null;
        $extra_data['institution'] = $_POST['institution'] ?? null;
    } elseif ($membershipType === 'teacher') {
        $extra_data['teacherId'] = $_POST['teacherId'] ?? null;
        $extra_data['institution'] = $_POST['institution'] ?? null;
    }
    
    // --- 2. HASH THE PASSWORD FOR SECURITY ---
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // --- 3. DATABASE CONNECTION ---
    // Path to your database configuration file
    require_once 'crud/db_config.php';
    // Check if the connection is successful
    if ($con->connect_error) {
        die("Database connection failed: " . $con->connect_error);
    }
    
    // Start a transaction to ensure all inserts are successful
    $con->begin_transaction();

    // --- 4. INSERT DATA INTO THE DATABASE (PREPARED STATEMENTS) ---
    try {
        // First, insert into the Members table
        $sql_members = "INSERT INTO Members (Name, Email, MembershipType, PhoneNumber, Address_Street, Address_City, Address_ZIP) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_members = $con->prepare($sql_members);
        $stmt_members->bind_param("sssssss", $name, $email, $membershipType, $phone, $street, $city, $zip);
        $stmt_members->execute();
        $new_user_id = $stmt_members->insert_id;
        $stmt_members->close();

        // Second, insert into the LoginCredentials table
        $sql_login = "INSERT INTO LoginCredentials (UserID, Email, PasswordHash) VALUES (?, ?, ?)";
        $stmt_login = $con->prepare($sql_login);
        $stmt_login->bind_param("iss", $new_user_id, $email, $hashedPassword);
        $stmt_login->execute();
        $stmt_login->close();

        // Third, insert into the specific membership table if it's a registered user type
        if (in_array($membershipType, ['author', 'general', 'student', 'teacher'])) {
            $sql_registered = "INSERT INTO Registered (UserID, RegistrationDate) VALUES (?, NOW())";
            $stmt_registered = $con->prepare($sql_registered);
            $stmt_registered->bind_param("i", $new_user_id);
            $stmt_registered->execute();
            $stmt_registered->close();

            // Insert into the specific subclass table
            if ($membershipType === 'author') {
                $sql_author = "INSERT INTO Author (UserID, AuthorTitle, AuthorBio) VALUES (?, ?, ?)";
                $stmt_author = $con->prepare($sql_author);
                $stmt_author->bind_param("iss", $new_user_id, $extra_data['authorTitle'], $extra_data['authorBio']);
                $stmt_author->execute();
                $stmt_author->close();
            } elseif ($membershipType === 'general') {
                $sql_general = "INSERT INTO General (UserID, Occupation) VALUES (?, ?)";
                $stmt_general = $con->prepare($sql_general);
                $stmt_general->bind_param("is", $new_user_id, $extra_data['occupation']);
                $stmt_general->execute();
                $stmt_general->close();
            } elseif ($membershipType === 'student') {
                $sql_student = "INSERT INTO Student (UserID, StudentID, Institution) VALUES (?, ?, ?)";
                $stmt_student = $con->prepare($sql_student);
                $stmt_student->bind_param("iss", $new_user_id, $extra_data['studentId'], $extra_data['institution']);
                $stmt_student->execute();
                $stmt_student->close();
            } elseif ($membershipType === 'teacher') {
                $sql_teacher = "INSERT INTO Teacher (UserID, TeacherID, Institution) VALUES (?, ?, ?)";
                $stmt_teacher = $con->prepare($sql_teacher);
                $stmt_teacher->bind_param("iss", $new_user_id, $extra_data['teacherId'], $extra_data['institution']);
                $stmt_teacher->execute();
                $stmt_teacher->close();
            }
        }
        
        // If all statements were successful, commit the transaction
        $con->commit();
        
        // --- 5. CLEAN UP AND REDIRECT ---
        $con->close();
        session_unset();
        session_destroy();
        header('Location: ../pages/home.php');
        exit();

    } catch (mysqli_sql_exception $e) {
        // Something went wrong, roll back the transaction
        $con->rollback();
        $con->close();
        die("Error: " . $e->getMessage());
    }

} else {
    // If the session data is missing, redirect back to the first signup page
    header('Location: signup.php');
    exit();
}
?>