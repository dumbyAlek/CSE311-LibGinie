<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Start the session to access session variables like login errors
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>LibGinie - Login</title>

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

    .login-container {
      background-color: rgba(255, 255, 255, 0.95);
      padding: 40px 35px;
      border-radius: 12px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.25);
      width: 380px;
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

    .form-control {
      border-radius: 8px;
      margin-bottom: 20px;
      font-family: 'I Love Ya as Sister', cursive;
      font-size: 1.3rem;
      padding: 10px;
      border: 2px solid #A6674C;
    }

    .btn-login, .btn-guest {
      font-family: 'Cinzel Decorative', cursive;
      border-radius: 8px;
      padding: 12px 0;
      width: 100%;
      font-size: 1.3rem;
      cursor: pointer;
      margin-bottom: 15px;
      border: none;
      transition: background-color 0.3s ease;
      color: white;
    }

      .btn-login {
      background-color: #A6674C;
    }
      .btn-login:hover {
      background-color: #8c5633;
    }

    .btn-guest {
      background-color: #6c757d;
    }
    .btn-guest:hover {
      background-color: #545b62;
    }

    .show-pass-container {
      text-align: left;
      margin-bottom: 20px;
      user-select: none;
      font-family: 'I Love Ya as Sister', cursive;
      font-size: 1rem;
      color: #7a5a3c;
      }

      .signup-link {
      font-family: 'I Love Ya as Sister', cursive;
      font-size: 1.1rem;
      color: #7a5a3c;
      margin-top: 10px;
      }

      .signup-link a {
      color: #A6674C;
      text-decoration: none;
      font-weight: 700;
    margin-left: 4px;
    }

    .signup-link a:hover {
      text-decoration: underline;
    }

    .alert {
        margin-bottom: 15px;
        font-family: 'I Love Ya as Sister', cursive;
    }
  </style>
</head>
<body>

  <div class="login-container" role="main">
    <?php
    // Check for and display any login errors
    if (isset($_SESSION['login_error'])) {
        echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
        // Clear the error after displaying it
        unset($_SESSION['login_error']); 
    }
    ?>
    <h2>Login</h2>
        <form action="../backend/login_process.php" method="POST">
      <div class="mb-3 text-start">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control" required autofocus />
      </div>

      <div class="mb-3 text-start">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control" required />
      </div>

      <div class="show-pass-container">
        <input type="checkbox" id="showPassword" onchange="togglePassword()" />
      <label for="showPassword">Show Password</label>
      </div>

      <button type="submit" class="btn-login" aria-label="Login">Login</button>
      <button type="button" class="btn-guest" onclick="guestLogin()" aria-label="Login as Guest">Login as Guest</button>
    </form>

  <div class="signup-link">
      No account? <a href="signup.php">Sign up now</a>
    </div>
  </div>

  <script>
    function togglePassword() {
      const passInput = document.getElementById('password');
      const showPassCheckbox = document.getElementById('showPassword');
      passInput.type = showPassCheckbox.checked ? 'text' : 'password';
    }

    function guestLogin() {
      window.location.href = "../backend/crud/guestLogin.php";
    }
  </script>
</body>
</html>