<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('admin');
require '../includes/db.php';

// Handle delete service
if (isset($_GET["delete"])) {
  $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
  $stmt->execute([$_GET["delete"]]);
  header("Location: services.php");
  exit();
}

// Handle toggle availability
if (isset($_GET["toggle"])) {
  $stmt = $pdo->prepare("
    UPDATE services
    SET is_available = IF(is_available = 1, 0, 1)
    WHERE id = ?
  ");
  $stmt->execute([$_GET["toggle"]]);
  header("Location: services.php");
  exit();
}

// Search & filter
$search   = trim($_GET["search"]   ?? "");
$category = trim($_GET["category"] ?? "");

$where  = ["1=1"];
$params = [];

if (!empty($search)) {
  $where[]  = "(s.title LIKE ? OR u.full_name LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

if (!empty($category)) {
  $where[]  = "s.category_id = ?";
  $params[] = $category;
}

$where_sql = implode(" AND ", $where);

$stmt = $pdo->prepare("
  SELECT
    s.*,
    u.full_name AS provider_name,
    c.name      AS category_name,
    c.icon      AS category_icon
  FROM services s
  JOIN users u              ON s.provider_id = u.id
  JOIN service_categories c ON s.category_id = c.id
  WHERE $where_sql
  ORDER BY s.created_at DESC
");
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categories for filter
$categories = $pdo->query("
  SELECT * FROM service_categories ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_services     = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$available_services = $pdo->query("SELECT COUNT(*) FROM services WHERE is_available=1")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Services – FindIt Nepal</title>
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
      <a href="services.php" class="sidebar-link active">
        <span class="sidebar-icon">💼</span> Manage Services
      </a>
      <a href="bookings.php" class="sidebar-link">
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
        <h1 id="dash-title">Manage Services</h1>
        <p id="dash-sub">View, filter and control all listed services.</p>
      </div>
    </div>

    <!-- STAT CARDS -->
    <div id="dash-stats">
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#EFF6FF;">💼</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $total_services ?></span>
          <span class="stat-card-label">Total Services</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#DCFCE7;">✅</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $available_services ?></span>
          <span class="stat-card-label">Available</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#FEE2E2;">⏸</div>
        <div class="stat-card-info">
          <span class="stat-card-num">
            <?= $total_services - $available_services ?>
          </span>
          <span class="stat-card-label">Unavailable</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#F3F4F6;">📂</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= count($categories) ?></span>
          <span class="stat-card-label">Categories</span>
        </div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" action="services.php">
      <div class="filter-bar">
        <input type="text" name="search"
          class="filter-input"
          placeholder="Search by title or provider..."
          value="<?= htmlspecialchars($search) ?>">
        <select name="category" class="filter-input">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat["id"] ?>"
            <?= $category == $cat["id"] ? "selected" : "" ?>>
            <?= $cat["icon"] ?> <?= htmlspecialchars($cat["name"]) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="filter-btn">Filter</button>
        <?php if (!empty($search) || !empty($category)): ?>
          <a href="services.php" class="filter-clear">✕ Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- SERVICES TABLE -->
    <div class="admin-panel">
      <div class="section-header">
        <h2 class="section-heading">
          All Services
          <span class="count-badge"><?= count($services) ?></span>
        </h2>
      </div>

      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Service</th>
              <th>Provider</th>
              <th>Price</th>
              <th>Location</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $i => $s): ?>
            <tr>
              <td class="td-num"><?= $i + 1 ?></td>
              <td>
                <div class="td-user">
                  <span style="font-size:1.3rem;">
                    <?= $s["category_icon"] ?>
                  </span>
                  <div>
                    <span class="td-title">
                      <?= htmlspecialchars($s["title"]) ?>
                    </span>
                    <span class="td-cat">
                      <?= htmlspecialchars($s["category_name"]) ?>
                    </span>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars($s["provider_name"]) ?></td>
              <td>
                Rs. <?= number_format($s["price"], 0) ?>
                <span style="font-size:0.72rem;color:#94A3B8;">
                  / <?= str_replace("_"," ",$s["price_type"]) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($s["location"] ?? "—") ?></td>
              <td>
                <?php if ($s["is_available"]): ?>
                  <span class="status-badge status-confirmed">Available</span>
                <?php else: ?>
                  <span class="status-badge status-cancelled">Unavailable</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="action-row">
                  <a href="services.php?toggle=<?= $s["id"] ?>"
                     class="btn-toggle">
                    <?= $s["is_available"] ? "Disable" : "Enable" ?>
                  </a>
                  <a href="services.php?delete=<?= $s["id"] ?>"
                     class="btn-del-sm"
                     onclick="return confirm('Delete this service?')">
                    Delete
                  </a>
                </div>
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