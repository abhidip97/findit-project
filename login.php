<?php
session_start();
require 'includes/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $email    = trim($_POST["email"]);
  $password = trim($_POST["password"]);

  // 1. Validate inputs
  if (empty($email) || empty($password)) {
    $error = "Please enter both email and password.";
  } else {

    // 2. Find user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Check password
    if ($user && password_verify($password, $user["password"])) {

      // 4. Store user info in session
      $_SESSION["user_id"]   = $user["id"];
      $_SESSION["user_name"] = $user["full_name"];
      $_SESSION["user_role"] = $user["role"];

      // 5. Redirect based on role
      if ($user["role"] == "admin") {
        header("Location: admin/dashboard.php");
      } elseif ($user["role"] == "provider") {
        header("Location: provider/dashboard.php");
      } else {
        header("Location: customer/dashboard.php");
      }
      exit();

    } else {
      $error = "Incorrect email or password.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – FindIt Nepal</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/auth.css">
</head>
<body>

<div id="auth-wrapper">

  <!-- Left side: branding -->
  <div id="auth-left">
    <a href="index.html" id="auth-logo">
      FindIt<span>.</span>np
    </a>
    <h2>Welcome back to FindIt Nepal</h2>
    <p>Log in to book services, manage your listings, or check your dashboard.</p>

    <div id="auth-features">
      <div class="auth-feature">
        <span class="auth-check">✓</span> Access your dashboard
      </div>
      <div class="auth-feature">
        <span class="auth-check">✓</span> Track your bookings
      </div>
      <div class="auth-feature">
        <span class="auth-check">✓</span> Manage your profile
      </div>
    </div>
  </div>

  <!-- Right side: form -->
  <div id="auth-right">
    <div id="auth-box">

      <h3 class="auth-title">Log in to your account</h3>
      <p class="auth-sub">Don't have an account?
        <a href="register.php">Register here</a>
      </p>

      <!-- Error message -->
      <?php if ($error): ?>
        <div class="alert-error">
          <p><?= $error ?></p>
        </div>
      <?php endif; ?>

      <!-- Login form -->
      <form method="POST" action="login.php">

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-input"
            placeholder="you@example.com"
            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input"
            placeholder="Enter your password">
        </div>

        <div id="form-options">
          <label class="remember-me">
            <input type="checkbox" name="remember"> Remember me
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-auth">Log In</button>

      </form>

    </div>
  </div>

</div>

</body>
</html>