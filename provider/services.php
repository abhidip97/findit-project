<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('provider');
require '../includes/db.php';

$user_id = $_SESSION["user_id"];

// Get all services for this provider
$stmt = $pdo->prepare("
  SELECT s.*, c.name AS category_name, c.icon AS category_icon
  FROM services s
  JOIN service_categories c ON s.category_id = c.id
  WHERE s.provider_id = ?
  ORDER BY s.created_at DESC
");
$stmt->execute([$user_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total     = count($services);
$available = array_filter($services, fn($s) => $s["is_available"] == 1);
$unavailable = $total - count($available);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Services – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/services.css">
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
      <a href="services.php" class="sidebar-link active">
        <span class="sidebar-icon">💼</span> My Services
      </a>
      <a href="bookings.php" class="sidebar-link">
        <span class="sidebar-icon">📅</span> Bookings
      </a>
      <a href="reviews.php" class="sidebar-link">
        <span class="sidebar-icon">⭐</span> Reviews
      </a>
      <a href="profile.php" class="sidebar-link">
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
        <h1 id="dash-title">My Services</h1>
        <p id="dash-sub">Manage all your listed services.</p>
      </div>
      <a href="add-service.php" id="btn-add-service">+ Add New Service</a>
    </div>

    <!-- STAT CARDS -->
    <div id="dash-stats">
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#EFF6FF;">💼</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total ?></span>
          <span class="stat-card-label">Total Services</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#DCFCE7;">✅</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= count($available) ?></span>
          <span class="stat-card-label">Available</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#FEE2E2;">⏸</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $unavailable ?></span>
          <span class="stat-card-label">Unavailable</span>
        </div>
      </div>
    </div>

    <!-- SERVICES GRID -->
    <?php if (empty($services)): ?>
      <div id="empty-state">
        <div id="empty-icon">💼</div>
        <h4>No services listed yet</h4>
        <p>Add your first service so customers can find and book you.</p>
        <a href="add-service.php" class="btn-empty">Add a Service</a>
      </div>

    <?php else: ?>
      <div id="services-full-grid">
        <?php foreach ($services as $s): ?>
        <div class="service-full-card">

          <div class="sfc-top">
            <div class="sfc-icon"><?= $s["category_icon"] ?></div>
            <span class="avail-badge <?= $s["is_available"] ? 'available' : 'unavailable' ?>">
              <?= $s["is_available"] ? "✅ Available" : "⏸ Unavailable" ?>
            </span>
          </div>

          <h5 class="sfc-title">
            <?= htmlspecialchars($s["title"]) ?>
          </h5>
          <p class="sfc-category">
            <?= htmlspecialchars($s["category_name"]) ?>
          </p>

          <?php if (!empty($s["description"])): ?>
          <p class="sfc-desc">
            <?= htmlspecialchars(substr($s["description"], 0, 100)) ?>
            <?= strlen($s["description"]) > 100 ? "..." : "" ?>
          </p>
          <?php endif; ?>

          <div class="sfc-details">
            <span class="sfc-price">
              Rs. <?= number_format($s["price"], 0) ?>
              <span>/ <?= str_replace("_"," ",$s["price_type"]) ?></span>
            </span>
            <?php if (!empty($s["location"])): ?>
            <span class="sfc-location">
              📍 <?= htmlspecialchars($s["location"]) ?>
            </span>
            <?php endif; ?>
          </div>

          <div class="sfc-date">
            Listed on <?= date("M d, Y", strtotime($s["created_at"])) ?>
          </div>

          <div class="sfc-actions">
            <a href="edit-service.php?id=<?= $s["id"] ?>"
               class="btn-edit-sfc">Edit</a>
            <a href="delete-service.php?id=<?= $s["id"] ?>"
               class="btn-delete-sfc"
               onclick="return confirm('Delete this service? This cannot be undone.')">
              Delete
            </a>
          </div>

        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>