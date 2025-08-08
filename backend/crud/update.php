<?php
include 'db_connect.php';
$id = $_GET['updateid'];

// Fetch the existing data to pre-fill the form
$sql_select = "SELECT * FROM `crud` WHERE id=?";
$stmt_select = $con->prepare($sql_select);
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result_select = $stmt_select->get_result();
$row = $result_select->fetch_assoc();

$name_current = $row['name'];
$email_current = $row['email'];
$mobile_current = $row['mobile'];
// Note: We don't fetch the password here for security

if (isset($_POST['submit'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $password = $_POST['password'];

    // Hashing the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Using a prepared statement for the update query
    $sql_update = "UPDATE `crud` SET name=?, email=?, mobile=?, password=? WHERE id=?";
    $stmt_update = $con->prepare($sql_update);
    $stmt_update->bind_param("ssssi", $name, $email, $mobile, $hashed_password, $id);

    if ($stmt_update->execute()) {
        header('Location: read.php');
        exit();
    } else {
        die("Error: " . $stmt_update->error);
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
    <h2 class="text-center my-4">Update</h2>
    <div class="container">
        <form method="post">
            <div class="form-group mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Enter your name" value="<?php echo htmlspecialchars($name_current); ?>" required>
            </div>
            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email_current); ?>" required>
            </div>
            <div class="form-group mb-3">
                <label for="mobile" class="form-label">Mobile Number</label>
                <input type="text" id="mobile" name="mobile" class="form-control" placeholder="Enter your mobile number" value="<?php echo htmlspecialchars($mobile_current); ?>" required>
            </div>
            <div class="form-group mb-3">
                <label for="pass" class="form-label">Password</label>
                <input type="password" id="pass" name="password" class="form-control" placeholder="Enter new password" required>
            </div>
            <button type="submit" name="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</body>
</html>