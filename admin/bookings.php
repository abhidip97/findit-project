
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('admin');
require '../includes/db.php';

// Handle delete booking
if (isset($_GET["delete"])) {
  $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
  $stmt->execute([$_GET["delete"]]);
  header("Location: bookings.php");
  exit();
}

// Filters
$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");

$where  = ["1=1"];
$params = [];

if (!empty($search)) {
  $where[]  = "(cu.full_name LIKE ? OR s.title LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

if (!empty($status)) {
  $where[]  = "b.status = ?";
  $params[] = $status;
}

$where_sql = implode(" AND ", $where);

$stmt = $pdo->prepare("
  SELECT
    b.*,
    s.title       AS service_title,
    s.price       AS service_price,
    cu.full_name  AS customer_name,
    pr.full_name  AS provider_name,
    c.icon        AS category_icon,
    c.name        AS category_name
  FROM bookings b
  JOIN services s           ON b.service_id  = s.id
  JOIN users cu             ON b.customer_id = cu.id
  JOIN users pr             ON b.provider_id = pr.id
  JOIN service_categories c ON s.category_id = c.id
  WHERE $where_sql
  ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Booking stats
$stats = $pdo->query("
  SELECT status, COUNT(*) as total
  FROM bookings GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);
$stat_map = [];
foreach ($stats as $s) {
  $stat_map[$s["status"]] = $s["total"];
}
$total_bookings = array_sum(array_column($stats, 'total'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Bookings – FindIt Nepal</title>
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
      <a href="dashboard.php" class="sidebar-link">
        <span class="sidebar-icon">🏠</span> Dashboard
      </a>
      <a href="users.php" class="sidebar-link">
        <span class="sidebar-icon">👥</span> Manage Users
      </a>
      <a href="services.php" class="sidebar-link">
        <span class="sidebar-icon">💼</span> Manage Services
      </a>
      <a href="bookings.php" class="sidebar-link active">
        <span class="sidebar-icon">📅</span> Manage Bookings
      </a>
      <a href="reviews.php" class="sidebar-link">
        <span class="sidebar-icon">⭐</span> Reviews
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
        <h1 id="dash-title">Manage Bookings</h1>
        <p id="dash-sub">Monitor and manage all bookings on the platform.</p>
      </div>
    </div>

    <!-- STAT CARDS -->
    <div id="dash-stats">
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

    <!-- FILTER BAR -->
    <form method="GET" action="bookings.php">
      <div class="filter-bar">
        <input type="text" name="search"
          class="filter-input"
          placeholder="Search by customer or service..."
          value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="filter-input">
          <option value="">All Statuses</option>
          <option value="pending"   <?= $status=="pending"   ? "selected":"" ?>>Pending</option>
          <option value="confirmed" <?= $status=="confirmed" ? "selected":"" ?>>Confirmed</option>
          <option value="completed" <?= $status=="completed" ? "selected":"" ?>>Completed</option>
          <option value="cancelled" <?= $status=="cancelled" ? "selected":"" ?>>Cancelled</option>
        </select>
        <button type="submit" class="filter-btn">Filter</button>
        <?php if (!empty($search) || !empty($status)): ?>
          <a href="bookings.php" class="filter-clear">✕ Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- BOOKINGS TABLE -->
    <div class="admin-panel">
      <div class="section-header">
        <h2 class="section-heading">
          All Bookings
          <span class="count-badge"><?= count($bookings) ?></span>
        </h2>
      </div>

      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Service</th>
              <th>Customer</th>
              <th>Provider</th>
              <th>Date</th>
              <th>Price</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bookings as $i => $b): ?>
            <tr>
              <td class="td-num"><?= $i + 1 ?></td>
              <td>
                <span><?= $b["category_icon"] ?></span>
                <?= htmlspecialchars($b["service_title"]) ?>
              </td>
              <td><?= htmlspecialchars($b["customer_name"]) ?></td>
              <td><?= htmlspecialchars($b["provider_name"]) ?></td>
              <td><?= date("M d, Y", strtotime($b["book_date"])) ?></td>
              <td>Rs. <?= number_format($b["service_price"], 0) ?></td>
              <td>
                <span class="status-badge status-<?= $b["status"] ?>">
                  <?= ucfirst($b["status"]) ?>
                </span>
              </td>
              <td>
                <a href="bookings.php?delete=<?= $b["id"] ?>"
                   class="btn-del-sm"
                   onclick="return confirm('Delete this booking?')">
                  Delete
                </a>
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