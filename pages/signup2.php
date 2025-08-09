<?php
session_start();

// Redirect if session data is not available, which means the first form wasn't submitted
if (!isset($_SESSION['signup_data'])) {
    header('Location: signup.php');
    exit();
}

$signupData = $_SESSION['signup_data'];
$membershipType = $signupData['membershipType'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibGinie - Additional Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=ILoveYaAsSister&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'I Love Ya as Sister', cursive;
            background: url('../images/bg.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #A6674C;
        }
        .signup2-container {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.25);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
            font-family: 'Cinzel Decorative', cursive;
            font-size: 2rem;
        }
        label {
            font-weight: 600;
            color: #7a5a3c;
        }
        .form-control, .form-select {
            margin-bottom: 20px;
            border: 2px solid #A6674C;
            font-size: 1.2rem;
        }
        .btn-next {
            background-color: #A6674C;
            color: white;
            font-size: 1.3rem;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 8px;
            transition: background 0.3s;
        }
        .btn-next:hover {
            background-color: #8c5633;
        }
    </style>
</head>
<body>

<div class="signup2-container">
    <h2>Additional Information</h2>
    
    <form id="extraForm" action="../backend/process_signup.php" method="POST">
        <input type="hidden" name="name" value="<?= htmlspecialchars($signupData['name']) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($signupData['email']) ?>">
        <input type="hidden" name="membershipType" value="<?= htmlspecialchars($signupData['membershipType']) ?>">
        <input type="hidden" name="phoneNumber" value="<?= htmlspecialchars($signupData['phone']) ?>">
        <input type="hidden" name="address_street" value="<?= htmlspecialchars($signupData['street']) ?>">
        <input type="hidden" name="address_city" value="<?= htmlspecialchars($signupData['city']) ?>">
        <input type="hidden" name="address_zip" value="<?= htmlspecialchars($signupData['zip']) ?>">
        <input type="hidden" name="password" value="<?= htmlspecialchars($signupData['password']) ?>">

        <div id="extraFields">
            <?php
            // PHP logic to dynamically generate form fields based on membership type
            if ($membershipType === "author") {
                echo '
                    <div class="mb-3 text-start">
                        <label for="authorTitle" class="form-label">Author Title</label>
                        <input type="text" id="authorTitle" name="authorTitle" class="form-control" required>
                    </div>
                    <div class="mb-3 text-start">
                        <label for="authorBio" class="form-label">Author Bio</label>
                        <textarea id="authorBio" name="authorBio" class="form-control" required></textarea>
                    </div>
                ';
            } else if ($membershipType === "general") {
                echo '
                    <div class="mb-3 text-start">
                        <label for="occupation" class="form-label">Occupation</label>
                        <input type="text" id="occupation" name="occupation" class="form-control" required>
                    </div>
                ';
            } else if ($membershipType === "student") {
                echo '
                    <div class="mb-3 text-start">
                        <label for="studentId" class="form-label">Student ID</label>
                        <input type="text" id="studentId" name="studentId" class="form-control" required>
                    </div>
                    <div class="mb-3 text-start">
                        <label for="institution" class="form-label">Institution</label>
                        <input type="text" id="institution" name="institution" class="form-control" required>
                    </div>
                ';
            } else if ($membershipType === "teacher") {
                echo '
                    <div class="mb-3 text-start">
                        <label for="teacherId" class="form-label">Teacher ID</label>
                        <input type="text" id="teacherId" name="teacherId" class="form-control" required>
                    </div>
                    <div class="mb-3 text-start">
                        <label for="institution" class="form-label">Institution</label>
                        <input type="text" id="institution" name="institution" class="form-control" required>
                    </div>
                ';
            } else if ($membershipType === "librarian" || $membershipType === "admin") {
                echo '
                    <p>Click "Finish" to complete your sign-up as a ' . htmlspecialchars($membershipType) . '.</p>
                ';
            }
            ?>
        </div>
        
        <button type="submit" class="btn-next">Finish</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>