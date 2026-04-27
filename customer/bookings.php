<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('customer');
require '../includes/db.php';

$user_id = $_SESSION["user_id"];

// Handle booking cancellation
if (isset($_GET["cancel"])) {
  $booking_id = $_GET["cancel"];
  $stmt = $pdo->prepare("
    UPDATE bookings SET status = 'cancelled'
    WHERE id = ? AND customer_id = ? AND status = 'pending'
  ");
  $stmt->execute([$booking_id, $user_id]);
  header("Location: bookings.php");
  exit();
}

// Filter by status
$status_filter = trim($_GET["status"] ?? "");

$where  = ["b.customer_id = ?"];
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
    u.full_name  AS provider_name,
    u.phone      AS provider_phone,
    c.name       AS category_name,
    c.icon       AS category_icon,
    r.id         AS review_id
  FROM bookings b
  JOIN services s           ON b.service_id  = s.id
  JOIN users u              ON b.provider_id = u.id
  JOIN service_categories c ON s.category_id = c.id
  LEFT JOIN reviews r       ON r.booking_id  = b.id
  WHERE $where_sql
  ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$counts = $pdo->prepare("
  SELECT status, COUNT(*) as total
  FROM bookings WHERE customer_id = ?
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
  <title>My Bookings – FindIt Nepal</title>
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
      <a href="search.php" class="sidebar-link">
        <span class="sidebar-icon">🔍</span> Find Services
      </a>
      <a href="bookings.php" class="sidebar-link active">
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

  <!-- MAIN -->
  <main id="dash-main">

    <div id="dash-topbar">
      <div>
        <h1 id="dash-title">My Bookings</h1>
        <p id="dash-sub">Track and manage all your service bookings.</p>
      </div>
      <a href="search.php" id="btn-find-service">+ Find a Service</a>
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
         class="status-tab <?= empty($status_filter) ? 'active' : '' ?>">
        All (<?= $total ?>)
      </a>
      <a href="bookings.php?status=pending"
         class="status-tab <?= $status_filter=='pending' ? 'active' : '' ?>">
        Pending (<?= $stat_map["pending"] ?? 0 ?>)
      </a>
      <a href="bookings.php?status=confirmed"
         class="status-tab <?= $status_filter=='confirmed' ? 'active' : '' ?>">
        Confirmed (<?= $stat_map["confirmed"] ?? 0 ?>)
      </a>
      <a href="bookings.php?status=completed"
         class="status-tab <?= $status_filter=='completed' ? 'active' : '' ?>">
        Completed (<?= $stat_map["completed"] ?? 0 ?>)
      </a>
      <a href="bookings.php?status=cancelled"
         class="status-tab <?= $status_filter=='cancelled' ? 'active' : '' ?>">
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
            : "You have not booked any services yet." ?>
        </p>
        <a href="search.php" class="btn-empty">Find a Service</a>
      </div>

    <?php else: ?>
      <div id="bookings-list">
        <?php foreach ($bookings as $b): ?>
        <div class="booking-item">

          <!-- Left: icon + details -->
          <div class="booking-item-left">
            <div class="booking-icon">
              <?= $b["category_icon"] ?>
            </div>
            <div class="booking-details">
              <h5 class="booking-title">
                <?= htmlspecialchars($b["service_title"]) ?>
              </h5>
              <p class="booking-meta">
                👤 <?= htmlspecialchars($b["provider_name"]) ?>
                <?php if (!empty($b["provider_phone"])): ?>
                  · 📞 <?= htmlspecialchars($b["provider_phone"]) ?>
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

          <!-- Right: price + status + actions -->
          <div class="booking-item-right">
            <div class="booking-price">
              Rs. <?= number_format($b["service_price"], 0) ?>
              <span>/ <?= str_replace("_"," ",$b["price_type"]) ?></span>
            </div>

            <span class="status-badge status-<?= $b["status"] ?>">
              <?= ucfirst($b["status"]) ?>
            </span>

            <div class="booking-actions">
              <!-- Cancel button (only pending) -->
              <?php if ($b["status"] == "pending"): ?>
                <a href="bookings.php?cancel=<?= $b["id"] ?>"
                   class="btn-cancel-booking"
                   onclick="return confirm('Cancel this booking?')">
                  Cancel
                </a>
              <?php endif; ?>

              <!-- Leave review (completed, not yet reviewed) -->
              <?php if ($b["status"] == "completed" && !$b["review_id"]): ?>
                <a href="reviews.php" class="btn-review">
                  Leave Review
                </a>
              <?php endif; ?>

              <!-- Already reviewed -->
              <?php if ($b["status"] == "completed" && $b["review_id"]): ?>
                <span class="reviewed-tag">✓ Reviewed</span>
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