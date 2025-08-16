<?php
session_start();
require_once 'crud/db_config.php';
require_once '../backend/crud/log_action.php';

// Check if the form was submitted from signup2.php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 1. GATHER DATA FROM BOTH FORMS ---
    if (!isset($_SESSION['signup_data'])) {
        die("Session data not found. Please start the signup process from the beginning.");
    }
    
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

    // --- 3. DATABASE CONNECTION & TRANSACTION ---
    if ($con->connect_error) {
        die("Database connection failed: " . $con->connect_error);
    }
    $con->begin_transaction();

    try {
        $isExistingAuthor = false;
        $userID = null;

        // Step 4: Special logic for Authors - Check if an author with this name already exists
        if ($membershipType === 'author') {
            $authorCheckSql = "SELECT m.UserID FROM Members m JOIN Author a ON m.UserID = a.UserID WHERE m.Name = ?";
            $stmt = $con->prepare($authorCheckSql);
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Author exists: flag it and get the UserID
                $authorData = $result->fetch_assoc();
                $userID = $authorData['UserID'];
                $isExistingAuthor = true;
            }
            $stmt->close();
        }

        // Step 5: Insert or Update based on the check
        if ($isExistingAuthor) {
            // Case A: Existing Author - Update their information
            $updateMemberSql = "UPDATE Members SET Email = ?, MembershipType = ?, PhoneNumber = ?, Address_Street = ?, Address_City = ?, Address_ZIP = ? WHERE UserID = ?";
            $updateMemberStmt = $con->prepare($updateMemberSql);
            $updateMemberStmt->bind_param("ssssssi", $email, $membershipType, $phone, $street, $city, $zip, $userID);
            $updateMemberStmt->execute();
            $updateMemberStmt->close();

            // Check if LoginCredentials exists for this UserID
            $checkLoginSql = "SELECT Email FROM LoginCredentials WHERE UserID = ?";
            $checkStmt = $con->prepare($checkLoginSql);
            $checkStmt->bind_param("i", $userID);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $loginRow = $checkResult->fetch_assoc();
            $checkStmt->close();

            if ($loginRow) {
                // Case 3: Author already has login → update credentials
                $updateLoginSql = "UPDATE LoginCredentials SET Email = ?, PasswordHash = ? WHERE UserID = ?";
                $updateLoginStmt = $con->prepare($updateLoginSql);
                $updateLoginStmt->bind_param("ssi", $email, $hashedPassword, $userID);
                $updateLoginStmt->execute();
                $updateLoginStmt->close();
            } else {
                // Case 2: Author exists but has no login → insert new login
                $insertLoginSql = "INSERT INTO LoginCredentials (UserID, Email, PasswordHash) VALUES (?, ?, ?)";
                $insertLoginStmt = $con->prepare($insertLoginSql);
                $insertLoginStmt->bind_param("iss", $userID, $email, $hashedPassword);
                $insertLoginStmt->execute();
                $insertLoginStmt->close();
            }
            
            // Update Author-specific details
            $updateAuthorSql = "UPDATE Author SET AuthorTitle = ?, AuthorBio = ? WHERE UserID = ?";
            $updateAuthorStmt = $con->prepare($updateAuthorSql);
            $updateAuthorStmt->bind_param("ssi", $extra_data['authorTitle'], $extra_data['authorBio'], $userID);
            $updateAuthorStmt->execute();
            $updateAuthorStmt->close();

        } else {
            // Case B: New User (including a new author)
            // Insert into Members table
            $sql_members = "INSERT INTO Members (Name, Email, MembershipType, PhoneNumber, Address_Street, Address_City, Address_ZIP) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_members = $con->prepare($sql_members);
            $stmt_members->bind_param("sssssss", $name, $email, $membershipType, $phone, $street, $city, $zip);
            $stmt_members->execute();
            $userID = $stmt_members->insert_id;
            $stmt_members->close();

            // Insert into LoginCredentials table
            $sql_login = "INSERT INTO LoginCredentials (UserID, Email, PasswordHash) VALUES (?, ?, ?)";
            $stmt_login = $con->prepare($sql_login);
            $stmt_login->bind_param("iss", $userID, $email, $hashedPassword);
            $stmt_login->execute();
            $stmt_login->close();

            // Insert into specific membership tables
            if (in_array($membershipType, ['author', 'general', 'student', 'teacher'])) {
                $sql_registered = "INSERT INTO Registered (UserID, RegistrationDate) VALUES (?, NOW())";
                $stmt_registered = $con->prepare($sql_registered);
                $stmt_registered->bind_param("i", $userID);
                $stmt_registered->execute();
                $stmt_registered->close();

                if ($membershipType === 'author') {
                    $sql_author = "INSERT INTO Author (UserID, AuthorTitle, AuthorBio) VALUES (?, ?, ?)";
                    $stmt_author = $con->prepare($sql_author);
                    $stmt_author->bind_param("iss", $userID, $extra_data['authorTitle'], $extra_data['authorBio']);
                    $stmt_author->execute();
                    $stmt_author->close();
                } elseif ($membershipType === 'general') {
                    $sql_general = "INSERT INTO General (UserID, Occupation) VALUES (?, ?)";
                    $stmt_general = $con->prepare($sql_general);
                    $stmt_general->bind_param("is", $userID, $extra_data['occupation']);
                    $stmt_general->execute();
                    $stmt_general->close();
                } elseif ($membershipType === 'student') {
                    $sql_student = "INSERT INTO Student (UserID, StudentID, Institution) VALUES (?, ?, ?)";
                    $stmt_student = $con->prepare($sql_student);
                    $stmt_student->bind_param("iss", $userID, $extra_data['studentId'], $extra_data['institution']);
                    $stmt_student->execute();
                    $stmt_student->close();
                } elseif ($membershipType === 'teacher') {
                    $sql_teacher = "INSERT INTO Teacher (UserID, TeacherID, Institution) VALUES (?, ?, ?)";
                    $stmt_teacher = $con->prepare($sql_teacher);
                    $stmt_teacher->bind_param("iss", $userID, $extra_data['teacherId'], $extra_data['institution']);
                    $stmt_teacher->execute();
                    $stmt_teacher->close();
                }
            } elseif ($membershipType === 'librarian') {
                $employeeSql = "INSERT INTO Employee (UserID, start_date) VALUES (?, CURDATE())";
                $employeeStmt = $con->prepare($employeeSql);
                $employeeStmt->bind_param("i", $userID);
                $employeeStmt->execute();
                $employeeStmt->close();
                
                $librarianSql = "INSERT INTO Librarian (UserID, LibrarianID) VALUES (?, ?)";
                $librarianId = 'LIBR' . $userID;
                $librarianStmt = $con->prepare($librarianSql);
                $librarianStmt->bind_param("is", $userID, $librarianId);
                $librarianStmt->execute();
                $librarianStmt->close();
            } elseif ($membershipType === 'admin') {
                $employeeSql = "INSERT INTO Employee (UserID, start_date) VALUES (?, CURDATE())";
                $employeeStmt = $con->prepare($employeeSql);
                $employeeStmt->bind_param("i", $userID);
                $employeeStmt->execute();
                $employeeStmt->close();

                $adminSql = "INSERT INTO Admin (UserID, AdminID) VALUES (?, ?)";
                $adminId = 'ADM' . $userID;
                $adminStmt = $con->prepare($adminSql);
                $adminStmt->bind_param("is", $userID, $adminId);
                $adminStmt->execute();
                $adminStmt->close();
            }
        }

        // --- 6. COMMIT, CLEAN UP, AND REDIRECT ---
        $con->commit();

        // Clear session data after successful registration
        unset($_SESSION['signup_data']);

        // Log the user in automatically after signup
        $_SESSION['loggedin'] = true;
        $_SESSION['UserID'] = $userID;
        $_SESSION['user_name'] = $name;
        $_SESSION['membershipType'] = $membershipType;
        log_action($_SESSION['UserID'], 'Login and SignUp', 'User ' . $_SESSION['user_name'] . ' signed up.');
        header('Location: ../pages/home.php');
        exit();

    } catch (mysqli_sql_exception $e) {
        $con->rollback();
        $con->close();
        unset($_SESSION['signup_data']); // Clean up session on error
        die("Error: " . $e->getMessage());
    }

} else {
    // Redirect to the signup form if accessed directly
    header('Location: signup.php');
    exit();
}
?>