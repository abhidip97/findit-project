<?php
require 'includes/db.php';

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  // 1. Get and clean form data
  $full_name = trim($_POST["full_name"]);
  $email     = trim($_POST["email"]);
  $password  = trim($_POST["password"]);
  $confirm   = trim($_POST["confirm_password"]);
  $role      = $_POST["role"];

  // 2. Validate inputs
  if (empty($full_name)) {
    $errors[] = "Full name is required.";
  }

  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid email is required.";
  }

  if (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters.";
  }

  if ($password !== $confirm) {
    $errors[] = "Passwords do not match.";
  }

  // 3. Check if email already exists
  if (empty($errors)) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
      $errors[] = "This email is already registered.";
    }
  }

  // 4. If no errors, insert into database
  if (empty($errors)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users 
      (full_name, email, password, role) 
      VALUES (?, ?, ?, ?)");

    $stmt->execute([$full_name, $email, $hashed_password, $role]);

    $success = "Account created successfully! You can now log in.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – FindIt Nepal</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/auth.css">
</head>
<body>

<div id="auth-wrapper">

  <!-- Left side: branding -->
  <div id="auth-left">
    <a href="home.html" id="auth-logo">
      FindIt<span>.</span>np
    </a>
    <h2>Join thousands of users across Nepal</h2>
    <p>Find trusted service providers or grow your business — all in one platform.</p>

    <div id="auth-features">
      <div class="auth-feature">
        <span class="auth-check">✓</span> Free to join
      </div>
      <div class="auth-feature">
        <span class="auth-check">✓</span> Verified providers
      </div>
      <div class="auth-feature">
        <span class="auth-check">✓</span> Secure platform
      </div>
    </div>
  </div>

  <!-- Right side: form -->
  <div id="auth-right">
    <div id="auth-box">

      <h3 class="auth-title">Create your account</h3>
      <p class="auth-sub">Already have an account? 
        <a href="login.php">Log in here</a>
      </p>

      <!-- Success message -->
      <?php if ($success): ?>
        <div class="alert-success"><?= $success ?></div>
      <?php endif; ?>

      <!-- Error messages -->
      <?php if (!empty($errors)): ?>
        <div class="alert-error">
          <?php foreach ($errors as $error): ?>
            <p><?= $error ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Register form -->
      <form method="POST" action="register.php">

        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-input"
            placeholder="e.g. Ram Kumar Shrestha"
            value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-input"
            placeholder="you@example.com"
            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        </div>

        <div class="form-group">
          <label class="form-label">I want to join as</label>
          <select name="role" class="form-input">
            <option value="customer" 
              <?= (isset($_POST['role']) && $_POST['role'] == 'customer') ? 'selected' : '' ?>>
              Customer – I want to find services
            </option>
            <option value="provider"
              <?= (isset($_POST['role']) && $_POST['role'] == 'provider') ? 'selected' : '' ?>>
              Service Provider – I offer services
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input"
            placeholder="Minimum 6 characters">
        </div>

        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-input"
            placeholder="Repeat your password">
        </div>

        <button type="submit" class="btn-auth">Create Account</button>

      </form>

    </div>
  </div>

</div>

</body>
</html>