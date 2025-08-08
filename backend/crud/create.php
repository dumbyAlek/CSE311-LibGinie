<?php
include 'db_connect.php';

if (isset($_POST['submit'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $password = $_POST['password'];

    // Hashing the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Using a prepared statement to prevent SQL injection
    $sql = "INSERT INTO `crud` (name, email, mobile, password) VALUES (?, ?, ?, ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $mobile, $hashed_password);

    if ($stmt->execute()) {
        header('Location: read.php');
        exit();
    } else {
        die("Error: " . $stmt->error);
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSE311L: CRUD Operation Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <h1 class="text-center my-4">CSE311L: CRUD Operation Demo</h1>
    <h2 class="text-center my-4">Create</h2>
    <div class="container">
        <form method="post">
            <div class="form-group mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Enter your name" required>
            </div>
            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email address" required>
            </div>
            <div class="form-group mb-3">
                <label for="mobile" class="form-label">Mobile Number</label>
                <input type="text" id="mobile" name="mobile" class="form-control" placeholder="Enter your mobile number" required>
            </div>
            <div class="form-group mb-3">
                <label for="pass" class="form-label">Password</label>
                <input type="password" id="pass" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
            <button type="submit" name="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
</body>
</html>