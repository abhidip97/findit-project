<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('customer');
require '../includes/db.php';

$user_id = $_SESSION["user_id"];
$success = "";
$errors  = [];

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if (isset($_POST["update_profile"])) {
  $full_name = trim($_POST["full_name"]);
  $phone     = trim($_POST["phone"]);
  $address   = trim($_POST["address"]);

  if (empty($full_name)) {
    $errors[] = "Full name is required.";
  }

  if (empty($errors)) {
    $stmt = $pdo->prepare("
      UPDATE users SET full_name = ?, phone = ?, address = ?
      WHERE id = ?
    ");
    $stmt->execute([$full_name, $phone, $address, $user_id]);
    $_SESSION["user_name"] = $full_name;
    $success = "Profile updated successfully!";

    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
  }
}

// Handle password change
if (isset($_POST["change_password"])) {
  $current  = $_POST["current_password"];
  $new      = $_POST["new_password"];
  $confirm  = $_POST["confirm_password"];

  if (!password_verify($current, $user["password"])) {
    $errors[] = "Current password is incorrect.";
  } elseif (strlen($new) < 6) {
    $errors[] = "New password must be at least 6 characters.";
  } elseif ($new !== $confirm) {
    $errors[] = "New passwords do not match.";
  }

  if (empty($errors)) {
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $stmt   = $pdo->prepare("
      UPDATE users SET password = ? WHERE id = ?
    ");
    $stmt->execute([$hashed, $user_id]);
    $success = "Password changed successfully!";
  }
}

// Get customer booking stats
$stats = $pdo->prepare("
  SELECT status, COUNT(*) as total
  FROM bookings WHERE customer_id = ?
  GROUP BY status
");
$stats->execute([$user_id]);
$stat_map = [];
foreach ($stats->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $stat_map[$row["status"]] = $row["total"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/profile.css">
</head>
<body>

<div id="dash-wrapper">

  <!-- SIDEBAR -->
  <aside id="sidebar">
    <a href="../home.html" id="sidebar-logo">
      FindIt<span>.</span>np
    </a>
    <nav id="sidebar-nav">
      <a href="dashboard.php" class="sidebar-link">
        <span class="sidebar-icon">🏠</span> Dashboard
      </a>
      <a href="search.php" class="sidebar-link">
        <span class="sidebar-icon">🔍</span> Find Services
      </a>
      <a href="bookings.php" class="sidebar-link">
        <span class="sidebar-icon">📅</span> My Bookings
      </a>
      <a href="reviews.php" class="sidebar-link">
        <span class="sidebar-icon">⭐</span> My Reviews
      </a>
      <a href="profile.php" class="sidebar-link active">
        <span class="sidebar-icon">👤</span> My Profile
      </a>
    </nav>
    <a href="../logout.php" id="sidebar-logout">
      <span>🚪</span> Log Out
    </a>
  </aside>

  <!-- MAIN -->
  <main id="dash-main">

    <div id="dash-topbar">
      <div>
        <h1 id="dash-title">My Profile</h1>
        <p id="dash-sub">Manage your account details and password.</p>
      </div>
    </div>

    <!-- ALERTS -->
    <?php if ($success): ?>
      <div class="alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert-error">
        <?php foreach ($errors as $e): ?>
          <p>⚠ <?= $e ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div id="profile-layout">

      <!-- LEFT: Profile card -->
      <div id="profile-left">

        <!-- Avatar card -->
        <div id="profile-card">
          <div id="profile-avatar">
            <?= strtoupper(substr($user["full_name"], 0, 1)) ?>
          </div>
          <h3 id="profile-name">
            <?= htmlspecialchars($user["full_name"]) ?>
          </h3>
          <p id="profile-email">
            <?= htmlspecialchars($user["email"]) ?>
          </p>
          <span class="role-badge role-customer">Customer</span>

          <div id="profile-stats">
            <div class="p-stat">
              <span class="p-stat-num">
                <?= array_sum($stat_map) ?>
              </span>
              <span class="p-stat-label">Total Bookings</span>
            </div>
            <div class="p-stat">
              <span class="p-stat-num">
                <?= $stat_map["completed"] ?? 0 ?>
              </span>
              <span class="p-stat-label">Completed</span>
            </div>
            <div class="p-stat">
              <span class="p-stat-num">
                <?= $stat_map["pending"] ?? 0 ?>
              </span>
              <span class="p-stat-label">Pending</span>
            </div>
          </div>

          <div id="profile-joined">
            Member since
            <?= date("F Y", strtotime($user["created_at"])) ?>
          </div>
        </div>

      </div>

      <!-- RIGHT: Edit forms -->
      <div id="profile-right">

        <!-- Edit Profile Form -->
        <div class="profile-panel">
          <h4 class="panel-title">Edit Profile</h4>

          <form method="POST" action="profile.php">

            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input type="text" name="full_name" class="form-input"
                value="<?= htmlspecialchars($user["full_name"]) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-input"
                value="<?= htmlspecialchars($user["email"]) ?>"
                disabled>
              <span class="form-note">Email cannot be changed.</span>
            </div>

            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="text" name="phone" class="form-input"
                placeholder="e.g. 9800000000"
                value="<?= htmlspecialchars($user["phone"] ?? '') ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-input"
                placeholder="e.g. Kathmandu, Nepal"
                value="<?= htmlspecialchars($user["address"] ?? '') ?>">
            </div>

            <button type="submit" name="update_profile"
                    class="btn-save">
              Save Changes
            </button>

          </form>
        </div>

        <!-- Change Password Form -->
        <div class="profile-panel">
          <h4 class="panel-title">Change Password</h4>

          <form method="POST" action="profile.php">

            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password"
                     class="form-input"
                     placeholder="Enter current password">
            </div>

            <div class="form-group">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password"
                     class="form-input"
                     placeholder="Minimum 6 characters">
            </div>

            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password"
                     class="form-input"
                     placeholder="Repeat new password">
            </div>

            <button type="submit" name="change_password"
                    class="btn-save outline">
              Change Password
            </button>

          </form>
        </div>

      </div>
    </div>

  </main>
</div>

</body>
</html>