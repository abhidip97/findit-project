<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('provider');
require '../includes/db.php';

$user_id = $_SESSION["user_id"];

// Get provider info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get provider's services
$stmt = $pdo->prepare("
  SELECT s.*, c.name AS category_name, c.icon AS category_icon
  FROM services s
  JOIN service_categories c ON s.category_id = c.id
  WHERE s.provider_id = ?
  ORDER BY s.created_at DESC
");
$stmt->execute([$user_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get provider's bookings with customer info
$stmt = $pdo->prepare("
  SELECT 
    b.*,
    s.title       AS service_title,
    s.price       AS service_price,
    u.full_name   AS customer_name,
    u.phone       AS customer_phone,
    c.name        AS category_name,
    c.icon        AS category_icon
  FROM bookings b
  JOIN services s ON b.service_id = s.id
  JOIN users u    ON b.customer_id = u.id
  JOIN service_categories c ON s.category_id = c.id
  WHERE b.provider_id = ?
  ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count stats
$total_services = count($services);
$total_bookings = count($bookings);
$pending        = 0;
$confirmed      = 0;
$completed      = 0;
$total_earnings = 0;

foreach ($bookings as $b) {
  if ($b["status"] == "pending")   $pending++;
  if ($b["status"] == "confirmed") $confirmed++;
  if ($b["status"] == "completed") {
    $completed++;
    $total_earnings += $b["service_price"];
  }
}

// Get average rating
$stmt = $pdo->prepare("
  SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
  FROM reviews
  WHERE provider_id = ?
");
$stmt->execute([$user_id]);
$rating_data = $stmt->fetch(PDO::FETCH_ASSOC);
$avg_rating    = $rating_data["avg_rating"] ? number_format($rating_data["avg_rating"], 1) : "N/A";
$total_reviews = $rating_data["total_reviews"];

// Handle booking status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["booking_id"], $_POST["new_status"])) {
  $booking_id = $_POST["booking_id"];
  $new_status = $_POST["new_status"];

  $allowed = ["confirmed", "completed", "cancelled"];

  if (in_array($new_status, $allowed)) {
    $stmt = $pdo->prepare("
      UPDATE bookings SET status = ?
      WHERE id = ? AND provider_id = ?
    ");
    $stmt->execute([$new_status, $booking_id, $user_id]);
  }

  header("Location: dashboard.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Provider Dashboard – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

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
      <a href="services.php" class="sidebar-link">
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

  <!-- MAIN CONTENT -->
  <main id="dash-main">

    <!-- TOP BAR -->
    <div id="dash-topbar">
      <div>
        <h1 id="dash-title">Provider Dashboard</h1>
        <p id="dash-sub">
          Welcome back, <?= htmlspecialchars($user["full_name"]) ?>!
        </p>
      </div>
      <a href="add-service.php" id="btn-add-service">
        + Add New Service
      </a>
    </div>

    <!-- STAT CARDS -->
    <div id="dash-stats">

      <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #EFF6FF;">💼</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total_services ?></span>
          <span class="stat-card-label">My Services</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #FEF9C3;">📅</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total_bookings ?></span>
          <span class="stat-card-label">Total Bookings</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #DCFCE7;">⭐</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $avg_rating ?></span>
          <span class="stat-card-label">Avg Rating (<?= $total_reviews ?> reviews)</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-card-icon" style="background-color: #FFF7ED;">💰</div>
        <div class="stat-card-info">
          <span class="stat-card-num">Rs. <?= number_format($total_earnings, 0) ?></span>
          <span class="stat-card-label">Total Earnings</span>
        </div>
      </div>

    </div>

    <!-- BOOKING REQUESTS -->
    <div id="dash-bookings">

      <div class="section-header">
        <h2 class="section-heading">Recent Booking Requests</h2>
        <a href="bookings.php" class="view-all-link">View All →</a>
      </div>

      <?php if (empty($bookings)): ?>
        <div id="empty-state">
          <div id="empty-icon">📭</div>
          <h4>No booking requests yet</h4>
          <p>Add your services so customers can find and book you.</p>
          <a href="add-service.php" class="btn-empty">Add a Service</a>
        </div>

      <?php else: ?>
        <div id="bookings-table-wrap">
          <table id="bookings-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Service</th>
                <th>Date</th>
                <th>Price</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($bookings, 0, 6) as $b): ?>
              <tr>
                <td>
                  <div class="td-customer">
                    <div class="td-avatar">
                      <?= strtoupper(substr($b["customer_name"], 0, 1)) ?>
                    </div>
                    <div>
                      <span class="td-title">
                        <?= htmlspecialchars($b["customer_name"]) ?>
                      </span>
                      <span class="td-cat">
                        <?= htmlspecialchars($b["customer_phone"] ?? "No phone") ?>
                      </span>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="td-icon"><?= $b["category_icon"] ?></span>
                  <?= htmlspecialchars($b["service_title"]) ?>
                </td>
                <td><?= date("M d, Y", strtotime($b["book_date"])) ?></td>
                <td>Rs. <?= number_format($b["service_price"], 0) ?></td>
                <td>
                  <span class="status-badge status-<?= $b["status"] ?>">
                    <?= ucfirst($b["status"]) ?>
                  </span>
                </td>
                <td>
                  <!-- Action buttons based on status -->
                  <?php if ($b["status"] == "pending"): ?>
                    <div class="action-btns">
                      <form method="POST">
                        <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                        <input type="hidden" name="new_status" value="confirmed">
                        <button type="submit" class="btn-accept">Accept</button>
                      </form>
                      <form method="POST">
                        <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                        <input type="hidden" name="new_status" value="cancelled">
                        <button type="submit" class="btn-cancel">Decline</button>
                      </form>
                    </div>

                  <?php elseif ($b["status"] == "confirmed"): ?>
                    <form method="POST">
                      <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                      <input type="hidden" name="new_status" value="completed">
                      <button type="submit" class="btn-complete">Mark Done</button>
                    </form>

                  <?php else: ?>
                    <span class="td-cat">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>

    <!-- MY SERVICES -->
    <div id="dash-services">

      <div class="section-header">
        <h2 class="section-heading">My Listed Services</h2>
        <a href="add-service.php" class="view-all-link">+ Add New →</a>
      </div>

      <?php if (empty($services)): ?>
        <div id="empty-state-services">
          <p>You have not listed any services yet.</p>
          <a href="add-service.php" class="btn-empty">Add Your First Service</a>
        </div>

      <?php else: ?>
        <div id="services-grid">
          <?php foreach (array_slice($services, 0, 4) as $s): ?>
          <div class="service-item">

            <div class="service-item-top">
              <div class="service-item-icon"><?= $s["category_icon"] ?></div>
              <span class="availability-dot
                <?= $s["is_available"] ? 'available' : 'unavailable' ?>">
                <?= $s["is_available"] ? "Available" : "Unavailable" ?>
              </span>
            </div>

            <h5 class="service-item-title">
              <?= htmlspecialchars($s["title"]) ?>
            </h5>
            <p class="service-item-cat">
              <?= htmlspecialchars($s["category_name"]) ?>
            </p>
            <p class="service-item-price">
              Rs. <?= number_format($s["price"], 0) ?>
              <span class="price-type">
                / <?= str_replace("_", " ", $s["price_type"]) ?>
              </span>
            </p>

            <div class="service-item-actions">
              <a href="edit-service.php?id=<?= $s["id"] ?>"
                 class="btn-edit">Edit</a>
              <a href="delete-service.php?id=<?= $s["id"] ?>"
                 class="btn-delete"
                 onclick="return confirm('Delete this service?')">Delete</a>
            </div>

          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

  </main>
</div>

</body>
</html>