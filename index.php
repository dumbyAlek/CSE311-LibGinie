<?php 
ini_set('display_errors', '0');        // Turn off displaying errors
ini_set('display_startup_errors', '0'); // Turn off startup errors
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>LibGinie - Welcome</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script&family=ILoveYaAsSister&family=Montserrat:wght@700&family=IM+Fell+English+SC&display=swap" rel="stylesheet" />
    
  <style>
    body, html {
      margin: 0;
      padding: 0;
      height: 100%;
      font-family: 'Open Sans', sans-serif;
    }

    .bg-image {
      height: 100vh;
      background-image: url('images/bg.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }

    .overlay {
      background-color: rgba(0, 0, 0, 0.6);
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      color: white;
      text-align: center;
      position: relative;
      padding: 0 15px;
    }

    .content-shift {
      margin-top: -50px;
    }

    .btn-custom {
      padding: 10px 30px;
      font-size: 1.1rem;
    }

    img.logo {
      height: 400px;
      margin-bottom: -50px;
    }

    h1.title {
      font-family: 'IM Fell English SC', cursive;
      font-weight: 500;
      font-size: 4.5rem;
      margin-bottom: 30px;
      margin-top: 0px;
      color: #A6674C;
      text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.6);
    }

    p.subtitle {
      font-family: 'ILoveYaAsSister', cursive;
      font-weight: 400;
      font-size: 1.8rem;
      color: #A9846A;
      margin-bottom: 50px;
    }

    .about-section {
      padding: 60px 15px;
      background-color: #f8f9fa;
      text-align: center;
      font-family: 'Open Sans', sans-serif;
    }
  </style>
</head>
<body>

  <!-- Hero Section with Background -->
  <div class="bg-image">
    <div class="overlay">
      <div class="content-shift">
        <img src="images/logo3.png" alt="LibGinie Logo" class="logo" />
        <h1 class="title">LibGinie</h1>
        <p class="subtitle">Your Smart Digital Library Companion</p>
        <div>
          <a href="pages/loginn.php" class="btn btn-primary btn-custom me-3">Login</a>
          <a href="pages/signup.php" class="btn btn-outline-light btn-custom">Sign Up</a>
        </div>
      </div>
    </div>
  </div>

  <!-- About Section -->
  <div class="about-section">
    <div class="container">
      <h2>About LibGinie</h2>
      <p class="mt-3">
        LibGinie is a simple and powerful digital library management system built for students and small institutions. 
        Browse, borrow, and manage your collection with ease.
      </p>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-dark text-white text-center py-3">
    <small>&copy; 2025 Team LibGinie</small>
  </footer>

</body>
</html>
