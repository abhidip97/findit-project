<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('provider');
require '../includes/db.php';

$user_id = $_SESSION["user_id"];

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST"
    && isset($_POST["booking_id"], $_POST["new_status"])) {
  $booking_id = $_POST["booking_id"];
  $new_status = $_POST["new_status"];
  $allowed    = ["confirmed", "completed", "cancelled"];

  if (in_array($new_status, $allowed)) {
    $stmt = $pdo->prepare("
      UPDATE bookings SET status = ?
      WHERE id = ? AND provider_id = ?
    ");
    $stmt->execute([$new_status, $booking_id, $user_id]);
  }
  header("Location: bookings.php");
  exit();
}

// Filter
$status_filter = trim($_GET["status"] ?? "");

$where  = ["b.provider_id = ?"];
$params = [$user_id];

if (!empty($status_filter)) {
  $where[]  = "b.status = ?";
  $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where);

$stmt = $pdo->prepare("
  SELECT
    b.*,
    s.title      AS service_title,
    s.price      AS service_price,
    s.price_type AS price_type,
    u.full_name  AS customer_name,
    u.phone      AS customer_phone,
    c.name       AS category_name,
    c.icon       AS category_icon
  FROM bookings b
  JOIN services s           ON b.service_id  = s.id
  JOIN users u              ON b.customer_id = u.id
  JOIN service_categories c ON s.category_id = c.id
  WHERE $where_sql
  ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$counts = $pdo->prepare("
  SELECT status, COUNT(*) as total
  FROM bookings WHERE provider_id = ?
  GROUP BY status
");
$counts->execute([$user_id]);
$stat_map = [];
foreach ($counts->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $stat_map[$row["status"]] = $row["total"];
}
$total = array_sum($stat_map);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bookings – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/bookings.css">
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
      <a href="services.php" class="sidebar-link">
        <span class="sidebar-icon">💼</span> My Services
      </a>
      <a href="bookings.php" class="sidebar-link active">
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
        <h1 id="dash-title">My Bookings</h1>
        <p id="dash-sub">Manage all booking requests from customers.</p>
      </div>
    </div>

    <!-- STAT CARDS -->
    <div id="dash-stats">
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#EFF6FF;">📋</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total ?></span>
          <span class="stat-card-label">Total Bookings</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#FEF9C3;">⏳</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $stat_map["pending"]   ?? 0 ?></span>
          <span class="stat-card-label">Pending</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#DCFCE7;">✅</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $stat_map["confirmed"] ?? 0 ?></span>
          <span class="stat-card-label">Confirmed</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#F3F4F6;">🏁</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $stat_map["completed"] ?? 0 ?></span>
          <span class="stat-card-label">Completed</span>
        </div>
      </div>
    </div>

    <!-- STATUS FILTER TABS -->
    <div id="status-tabs">
      <a href="bookings.php"
         class="status-tab <?= empty($status_filter) ? 'active':'' ?>">
        All (<?= $total ?>)
      </a>
      <a href="bookings.php?status=pending"
         class="status-tab <?= $status_filter=='pending' ? 'active':'' ?>">
        Pending (<?= $stat_map["pending"] ?? 0 ?>)
      </a>
      <a href="bookings.php?status=confirmed"
         class="status-tab <?= $status_filter=='confirmed' ? 'active':'' ?>">
        Confirmed (<?= $stat_map["confirmed"] ?? 0 ?>)
      </a>
      <a href="bookings.php?status=completed"
         class="status-tab <?= $status_filter=='completed' ? 'active':'' ?>">
        Completed (<?= $stat_map["completed"] ?? 0 ?>)
      </a>
      <a href="bookings.php?status=cancelled"
         class="status-tab <?= $status_filter=='cancelled' ? 'active':'' ?>">
        Cancelled (<?= $stat_map["cancelled"] ?? 0 ?>)
      </a>
    </div>

    <!-- BOOKINGS LIST -->
    <?php if (empty($bookings)): ?>
      <div id="empty-state">
        <div id="empty-icon">📭</div>
        <h4>No bookings found</h4>
        <p>
          <?= !empty($status_filter)
            ? "No " . $status_filter . " bookings."
            : "You have not received any bookings yet." ?>
        </p>
        <a href="add-service.php" class="btn-empty">Add a Service</a>
      </div>

    <?php else: ?>
      <div id="bookings-list">
        <?php foreach ($bookings as $b): ?>
        <div class="booking-item">

          <!-- Left -->
          <div class="booking-item-left">
            <div class="booking-icon"><?= $b["category_icon"] ?></div>
            <div class="booking-details">
              <h5 class="booking-title">
                <?= htmlspecialchars($b["service_title"]) ?>
              </h5>
              <p class="booking-meta">
                👤 <?= htmlspecialchars($b["customer_name"]) ?>
                <?php if (!empty($b["customer_phone"])): ?>
                  · 📞 <?= htmlspecialchars($b["customer_phone"]) ?>
                <?php endif; ?>
              </p>
              <p class="booking-meta">
                📅 <?= date("F d, Y", strtotime($b["book_date"])) ?>
                <?php if (!empty($b["book_time"])): ?>
                  at <?= date("h:i A", strtotime($b["book_time"])) ?>
                <?php endif; ?>
              </p>
              <?php if (!empty($b["address"])): ?>
              <p class="booking-meta">
                📍 <?= htmlspecialchars($b["address"]) ?>
              </p>
              <?php endif; ?>
              <?php if (!empty($b["message"])): ?>
              <p class="booking-message">
                "<?= htmlspecialchars($b["message"]) ?>"
              </p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Right -->
          <div class="booking-item-right">
            <div class="booking-price">
              Rs. <?= number_format($b["service_price"], 0) ?>
              <span>/ <?= str_replace("_"," ",$b["price_type"]) ?></span>
            </div>

            <span class="status-badge status-<?= $b["status"] ?>">
              <?= ucfirst($b["status"]) ?>
            </span>

            <!-- Action buttons -->
            <div class="booking-actions">
              <?php if ($b["status"] == "pending"): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                  <input type="hidden" name="new_status" value="confirmed">
                  <button type="submit" class="btn-accept-sm">Accept</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                  <input type="hidden" name="new_status" value="cancelled">
                  <button type="submit" class="btn-decline-sm">Decline</button>
                </form>

              <?php elseif ($b["status"] == "confirmed"): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                  <input type="hidden" name="new_status" value="completed">
                  <button type="submit" class="btn-done-sm">Mark Done</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>