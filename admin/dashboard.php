<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('admin');
require '../includes/db.php';

// ── STATS ──
// Total users by role
$stmt = $pdo->query("SELECT role, COUNT(*) as total FROM users GROUP BY role");
$role_counts = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $role_counts[$row["role"]] = $row["total"];
}
$total_customers = $role_counts["customer"] ?? 0;
$total_providers = $role_counts["provider"] ?? 0;
$total_admins    = $role_counts["admin"]    ?? 0;
$total_users     = $total_customers + $total_providers + $total_admins;

// Total services
$total_services = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();

// Total bookings by status
$stmt = $pdo->query("SELECT status, COUNT(*) as total FROM bookings GROUP BY status");
$booking_counts = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $booking_counts[$row["status"]] = $row["total"];
}
$total_bookings   = array_sum($booking_counts);
$pending_bookings = $booking_counts["pending"]   ?? 0;
$confirmed        = $booking_counts["confirmed"] ?? 0;
$completed        = $booking_counts["completed"] ?? 0;

// Total revenue (completed bookings)
$total_revenue = $pdo->query("
  SELECT SUM(s.price)
  FROM bookings b
  JOIN services s ON b.service_id = s.id
  WHERE b.status = 'completed'
")->fetchColumn() ?? 0;

// Average rating
$avg_rating = $pdo->query("
  SELECT AVG(rating) FROM reviews
")->fetchColumn();
$avg_rating = $avg_rating ? number_format($avg_rating, 1) : "N/A";

// Recent 6 users
$recent_users = $pdo->query("
  SELECT * FROM users
  ORDER BY created_at DESC
  LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Recent 6 bookings
$recent_bookings = $pdo->query("
  SELECT
    b.*,
    s.title      AS service_title,
    s.price      AS service_price,
    cu.full_name AS customer_name,
    pr.full_name AS provider_name,
    c.icon       AS category_icon
  FROM bookings b
  JOIN services s           ON b.service_id  = s.id
  JOIN users cu             ON b.customer_id = cu.id
  JOIN users pr             ON b.provider_id = pr.id
  JOIN service_categories c ON s.category_id = c.id
  ORDER BY b.created_at DESC
  LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

<div id="dash-wrapper">

  <!-- SIDEBAR -->
  <aside id="sidebar">
    <a href="../home.html" id="sidebar-logo">
      FindIt<span>.</span>np
    </a>

    <div id="admin-badge">Admin Panel</div>

    <nav id="sidebar-nav">
      <a href="dashboard.php" class="sidebar-link active">
        <span class="sidebar-icon">🏠</span> Dashboard
      </a>
      <a href="users.php" class="sidebar-link">
        <span class="sidebar-icon">👥</span> Manage Users
      </a>
      <a href="services.php" class="sidebar-link">
        <span class="sidebar-icon">💼</span> Manage Services
      </a>
      <a href="bookings.php" class="sidebar-link">
        <span class="sidebar-icon">📅</span> Manage Bookings
      </a>
      <!-- <a href="reviews.php" class="sidebar-link">
        <span class="sidebar-icon">⭐</span> Reviews
      </a> -->
      <!-- <a href="categories.php" class="sidebar-link">
        <span class="sidebar-icon">📂</span> Categories
      </a> -->
    </nav>

    <a href="../logout.php" id="sidebar-logout">
      <span>🚪</span> Log Out
    </a>
  </aside>

  <!-- MAIN CONTENT -->
  <main id="dash-main">

    <!-- TOP BAR -->
    <div id="dash-topbar">
      <div>
        <h1 id="dash-title">Admin Dashboard</h1>
        <p id="dash-sub">
          Welcome back, <?= htmlspecialchars($_SESSION["user_name"]) ?>!
          Here's what's happening on the platform.
        </p>
      </div>
      <div id="topbar-date">
        <?= date("l, F j, Y") ?>
      </div>
    </div>

    <!-- STAT CARDS ROW 1: Users & Platform -->
    <div id="dash-stats">

      <div class="stat-card">
        <div class="stat-card-icon" style="background:#EFF6FF;">👥</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total_users ?></span>
          <span class="stat-card-label">Total Users</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background:#DCFCE7;">👤</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total_customers ?></span>
          <span class="stat-card-label">Customers</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background:#FFF7ED;">🛠️</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total_providers ?></span>
          <span class="stat-card-label">Providers</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background:#F3F4F6;">💼</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total_services ?></span>
          <span class="stat-card-label">Listed Services</span>
        </div>
      </div>

    </div>

    <!-- STAT CARDS ROW 2: Bookings & Revenue -->
    <div id="dash-stats-2">

      <div class="stat-card">
        <div class="stat-card-icon" style="background:#EFF6FF;">📋</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total_bookings ?></span>
          <span class="stat-card-label">Total Bookings</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background:#FEF9C3;">⏳</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $pending_bookings ?></span>
          <span class="stat-card-label">Pending</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background:#DCFCE7;">✅</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $completed ?></span>
          <span class="stat-card-label">Completed</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background:#FEF3C7;">💰</div>
        <div class="stat-card-info">
          <span class="stat-card-num">
            Rs. <?= number_format($total_revenue, 0) ?>
          </span>
          <span class="stat-card-label">Platform Revenue</span>
        </div>
      </div>

    </div>

    <!-- TWO COLUMN LAYOUT -->
    <div id="dash-columns">

      <!-- Recent Users -->
      <div class="dash-panel">
        <div class="section-header">
          <h2 class="section-heading">Recent Users</h2>
          <a href="users.php" class="view-all-link">View All →</a>
        </div>

        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_users as $u): ?>
            <tr>
              <td>
                <div class="td-user">
                  <div class="td-avatar-sm">
                    <?= strtoupper(substr($u["full_name"], 0, 1)) ?>
                  </div>
                  <div>
                    <span class="td-title">
                      <?= htmlspecialchars($u["full_name"]) ?>
                    </span>
                    <span class="td-cat">
                      <?= htmlspecialchars($u["email"]) ?>
                    </span>
                  </div>
                </div>
              </td>
              <td>
                <span class="role-badge role-<?= $u["role"] ?>">
                  <?= ucfirst($u["role"]) ?>
                </span>
              </td>
              <td><?= date("M d, Y", strtotime($u["created_at"])) ?></td>
              <td>
                <a href="users.php?delete=<?= $u["id"] ?>"
                   class="btn-del-sm"
                   onclick="return confirm('Delete this user?')">
                  Delete
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Recent Bookings -->
      <div class="dash-panel">
        <div class="section-header">
          <h2 class="section-heading">Recent Bookings</h2>
          <a href="bookings.php" class="view-all-link">View All →</a>
        </div>

        <table class="admin-table">
          <thead>
            <tr>
              <th>Service</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_bookings as $b): ?>
            <tr>
              <td>
                <span class="td-icon"><?= $b["category_icon"] ?></span>
                <?= htmlspecialchars($b["service_title"]) ?>
              </td>
              <td><?= htmlspecialchars($b["customer_name"]) ?></td>
              <td><?= date("M d", strtotime($b["book_date"])) ?></td>
              <td>
                <span class="status-badge status-<?= $b["status"] ?>">
                  <?= ucfirst($b["status"]) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>

  </main>
</div>

</body>
</html>