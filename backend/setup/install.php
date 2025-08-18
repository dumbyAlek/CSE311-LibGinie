<?php
// install.php
// This script runs the setup for a new development environment.
// WARNING: Delete this file after running it once for security!

// Path to your database configuration file
require_once '../crud/db_config.php';

// Password for 'password123'
$passwordHash = '$2y$10$66OsT/.Gf89ymqJyhuaYLekBEZeKel5515XKSSrv5w0yXif1emvjG';
$email = 'admin@libginie.com';
$name = 'Admin User';
$membershipType = 'admin';
$employeeID = 'E001';
$adminID = 'AD001';

// Check if the connection is successful
if ($con->connect_error) {
    die("Database connection failed: " . $con->connect_error);
}

echo "Starting installation script...<br>";

// Use a transaction to ensure all or none of the queries run
$con->begin_transaction();

try {
    // Check if the admin user already exists
    $check_sql = "SELECT UserID FROM Members WHERE Email = ?";
    $stmt_check = $con->prepare($check_sql);
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        echo "Admin user already exists. Installation aborted.<br>";
        $con->rollback();
        exit();
    }
    
    // Step 1: Insert into the Members table
    $sql_members = "INSERT INTO Members (Name, Email, MembershipType) VALUES (?, ?, ?)";
    $stmt_members = $con->prepare($sql_members);
    $stmt_members->bind_param("sss", $name, $email, $membershipType);
    $stmt_members->execute();
    $userID = $con->insert_id;
    $stmt_members->close();
    
    // Step 2: Insert into the Employee table
    $sql_employee = "INSERT INTO Employee (UserID, EmployeeID, start_date) VALUES (?, ?, NOW())";
    $stmt_employee = $con->prepare($sql_employee);
    $stmt_employee->bind_param("is", $userID, $employeeID);
    $stmt_employee->execute();
    $stmt_employee->close();
    
    // Step 3: Insert into the Admin table
    $sql_admin = "INSERT INTO Admin (UserID, AdminID) VALUES (?, ?)";
    $stmt_admin = $con->prepare($sql_admin);
    $stmt_admin->bind_param("is", $userID, $adminID);
    $stmt_admin->execute();
    $stmt_admin->close();
    
    // Step 4: Insert the LoginCredentials
    $sql_login = "INSERT INTO LoginCredentials (UserID, Email, PasswordHash) VALUES (?, ?, ?)";
    $stmt_login = $con->prepare($sql_login);
    $stmt_login->bind_param("iss", $userID, $email, $passwordHash);
    $stmt_login->execute();
    $stmt_login->close();
    
    $con->commit();
    echo "Default admin user created successfully!<br>";
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password: password123<br>";

} catch (mysqli_sql_exception $exception) {
    $con->rollback();
    echo "Error: Installation failed. " . $exception->getMessage() . "<br>";
}

$con->close();
?>