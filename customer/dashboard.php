<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('customer');
require '../includes/db.php';

// Get logged in customer info
$user_id = $_SESSION["user_id"];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get customer's bookings with service and provider info
$stmt = $pdo->prepare("
  SELECT 
    b.*,
    s.title        AS service_title,
    s.price        AS service_price,
    s.price_type   AS price_type,
    u.full_name    AS provider_name,
    c.name         AS category_name,
    c.icon         AS category_icon
  FROM bookings b
  JOIN services s ON b.service_id = s.id
  JOIN users u    ON b.provider_id = u.id
  JOIN service_categories c ON s.category_id = c.id
  WHERE b.customer_id = ?
  ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count bookings by status
$total     = count($bookings);
$pending   = 0;
$confirmed = 0;
$completed = 0;

foreach ($bookings as $b) {
  if ($b["status"] == "pending")   $pending++;
  if ($b["status"] == "confirmed") $confirmed++;
  if ($b["status"] == "completed") $completed++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Dashboard – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

<!-- ── SIDEBAR + MAIN WRAPPER ── -->
<div id="dash-wrapper">

  <!-- SIDEBAR -->
  <aside id="sidebar">

    <a href="../index.html" id="sidebar-logo">
      FindIt<span>.</span>np
    </a>

    <nav id="sidebar-nav">
      <a href="dashboard.php" class="sidebar-link active">
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
      <a href="profile.php" class="sidebar-link">
        <span class="sidebar-icon">👤</span> My Profile
      </a>
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
        <h1 id="dash-title">My Dashboard</h1>
        <p id="dash-sub">Welcome back, <?= htmlspecialchars($user["full_name"]) ?>!</p>
      </div>
      <a href="search.php" id="btn-find-service">
        + Find a Service
      </a>
    </div>

    <!-- STAT CARDS -->
    <div id="dash-stats">

      <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #EFF6FF;">📋</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total ?></span>
          <span class="stat-card-label">Total Bookings</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #FEF9C3;">⏳</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $pending ?></span>
          <span class="stat-card-label">Pending</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #DCFCE7;">✅</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $confirmed ?></span>
          <span class="stat-card-label">Confirmed</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #F3F4F6;">🏁</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $completed ?></span>
          <span class="stat-card-label">Completed</span>
        </div>
      </div>

    </div>

    <!-- RECENT BOOKINGS TABLE -->
    <div id="dash-bookings">

      <div class="section-header">
        <h2 class="section-heading">Recent Bookings</h2>
        <a href="bookings.php" class="view-all-link">View All →</a>
      </div>

      <?php if (empty($bookings)): ?>
        <div id="empty-state">
          <div id="empty-icon">📭</div>
          <h4>No bookings yet</h4>
          <p>You have not booked any services yet.</p>
          <a href="search.php" class="btn-empty">Find a Service Now</a>
        </div>

      <?php else: ?>
        <div id="bookings-table-wrap">
          <table id="bookings-table">
            <thead>
              <tr>
                <th>Service</th>
                <th>Provider</th>
                <th>Date</th>
                <th>Price</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($bookings, 0, 5) as $b): ?>
              <tr>
                <td>
                  <div class="td-service">
                    <span class="td-icon"><?= $b["category_icon"] ?></span>
                    <div>
                      <span class="td-title"><?= htmlspecialchars($b["service_title"]) ?></span>
                      <span class="td-cat"><?= htmlspecialchars($b["category_name"]) ?></span>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($b["provider_name"]) ?></td>
                <td><?= date("M d, Y", strtotime($b["book_date"])) ?></td>
                <td>Rs. <?= number_format($b["service_price"], 0) ?></td>
                <td>
                  <span class="status-badge status-<?= $b["status"] ?>">
                    <?= ucfirst($b["status"]) ?>
                  </span>
                </td>
                <td>
                  <a href="booking-detail.php?id=<?= $b["id"] ?>" 
                     class="btn-view">View</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>

    <!-- QUICK SEARCH -->
    <div id="dash-quick-search">
      <h2 class="section-heading">Find a Service</h2>
      <p class="section-desc">What do you need help with today?</p>

      <div id="quick-search-grid">
        <?php
        $cats = $pdo->query("SELECT * FROM service_categories LIMIT 8");
        foreach ($cats as $cat):
        ?>
        <a href="search.php?category=<?= $cat["id"] ?>" class="quick-cat-card">
          <span class="quick-cat-icon"><?= $cat["icon"] ?></span>
          <span class="quick-cat-name"><?= htmlspecialchars($cat["name"]) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

  </main>

</div>

</body>
</html>