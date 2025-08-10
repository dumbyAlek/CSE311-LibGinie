<?php
// Start the session to store data between pages
session_start();

// --- CAPTCHA Generation ---
if (!isset($_SESSION['captcha_answer'])) {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_num1'] = $num1;
    $_SESSION['captcha_num2'] = $num2;
    $_SESSION['captcha_answer'] = $num1 + $num2;
}

// Check if the form was submitted using the POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $membershipType = $_POST['membershipType'];
    $phone = trim($_POST['phone']);
    $street = trim($_POST['street']);
    $city = trim($_POST['city']);
    $zip = trim($_POST['zip']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
   $captchaInput = trim($_POST['captcha']);

    // --- Server-side validation ---
    // Validate passwords match
    if ($password !== $confirmPassword) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: signup.php");
        exit();
    }

    // Validate CAPTCHA against the SESSION variable
    if ($captchaInput != $_SESSION['captcha_answer']) {
        $_SESSION['error_message'] = "Incorrect CAPTCHA answer. Please try again.";
        // Regenerate CAPTCHA to force a new answer on reload
        unset($_SESSION['captcha_answer']);
        header("Location: signup.php");
        exit();
    }

    // Store the validated data in the session
    $_SESSION['signup_data'] = [
        'name' => $name,
        'email' => $email,
        'membershipType' => $membershipType,
        'phone' => $phone,
        'street' => $street,
        'city' => $city,
        'zip' => $zip,
        'password' => $password
    ];

    // Redirect to the second form
    header("Location: signup2.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LibGinie - Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=ILoveYaAsSister&display=swap" rel="stylesheet" />
    <style>
        html, body {
            height: 100%;
            margin: 0;
            font-family: 'I Love Ya as Sister', cursive;
            background: url('../images/bg.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #A6674C;
        }
        .signup-container {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px 35px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.25);
            max-width: 800px;
            width: 100%;
            text-align: center;
            font-family: 'Cinzel Decorative', cursive;
        }
        h2 {
            margin-bottom: 30px;
            font-weight: 700;
            font-size: 2.8rem;
            color: #A6674C;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
        }
        label {
            font-weight: 600;
            color: #7a5a3c;
            font-size: 1.1rem;
        }
        .form-control, select.form-select {
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: 'I Love Ya as Sister', cursive;
            font-size: 1.3rem;
            padding: 10px;
            border: 2px solid #A6674C;
            color: #7a5a3c;
        }
        .btn-signup {
            font-family: 'Cinzel Decorative', cursive;
            border-radius: 8px;
            padding: 12px 0;
            width: 100%;
            font-size: 1.3rem;
            cursor: pointer;
            margin-top: 10px;
            border: none;
            transition: background-color 0.3s ease;
            color: white;
            background-color: #A6674C;
        }
        .btn-signup:hover {
            background-color: #8c5633;
        }
        .show-pass-container {
            text-align: left;
            margin-bottom: 20px;
            user-select: none;
            font-family: 'I Love Ya as Sister', cursive;
            font-size: 1rem;
            color: #7a5a3c;
        }
        .login-link {
            font-family: 'I Love Ya as Sister', cursive;
            font-size: 1.1rem;
            color: #7a5a3c;
            margin-top: 10px;
        }
        .login-link a {
            color: #A6674C;
            text-decoration: none;
            font-weight: 700;
            margin-left: 4px;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .alert-danger {
            font-family: 'I Love Ya as Sister', cursive;
        }
    </style>
</head>
<body>

    <div class="signup-container">
        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger" role="alert">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>
        <h2>Sign Up</h2>
        <form action="signup.php" method="POST" onsubmit="return validateForm()">
            <div class="row">
                <div class="col-md-6 text-start">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" required />
                </div>
                <div class="col-md-6 text-start">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required />
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 text-start">
                    <label for="membershipType">Membership Type</label>
                    <select id="membershipType" name="membershipType" class="form-select" required>
                        <option value="" disabled selected>Select membership type</option>
                        <option value="general">General</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="author">Author</option>
                        <option value="librarian">Librarian</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-md-6 text-start">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" pattern="[0-9+\-\s]{6,15}" title="Enter a valid phone number" />
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 text-start">
                    <label for="street">Address - Street</label>
                    <input type="text" id="street" name="street" class="form-control" required />
                </div>
                <div class="col-md-6 text-start">
                    <label for="city">Address - City</label>
                    <input type="text" id="city" name="city" class="form-control" required />
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 text-start">
                    <label for="zip">Address - ZIP Code</label>
                    <input type="text" id="zip" name="zip" class="form-control" required pattern="\d{4,10}" />
                </div>
                <div class="col-md-6 text-start">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required />
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 text-start">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" required />
                </div>
                <div class="col-md-6 text-start">
                    <label id="captchaLabel" for="captchaInput">What is <?php echo $_SESSION['captcha_num1'] . ' + ' . $_SESSION['captcha_num2']; ?>?</label>
                    <input type="text" id="captchaInput" name="captcha" class="form-control" required />
                </div>
            </div>
            
            <div class="show-pass-container">
                <input type="checkbox" id="showPassword" onchange="togglePassword()" />
                <label for="showPassword">Show Passwords</label>
            </div>

            <button type="submit" class="btn-signup">Continue</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="loginn.php">Login here</a>
        </div>
    </div>

    <script>
        function togglePassword() {
        const pass = document.getElementById('password');
        const confirmPass = document.getElementById('confirmPassword');
        const show = document.getElementById('showPassword').checked;
        pass.type = show ? 'text' : 'password';
        confirmPass.type = show ? 'text' : 'password';
    }

    function validateForm() {
        const pass = document.getElementById('password').value;
        const confirmPass = document.getElementById('confirmPassword').value;

        if (pass !== confirmPass) {
            alert("Passwords don't match!");
            return false;
        }

        return true;
    }
    </script>

</body>
</html>