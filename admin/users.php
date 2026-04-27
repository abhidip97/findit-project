<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('admin');
require '../includes/db.php';

// Handle delete user
if (isset($_GET["delete"])) {
  $delete_id = $_GET["delete"];
  // Prevent admin from deleting themselves
  if ($delete_id != $_SESSION["user_id"]) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$delete_id]);
  }
  header("Location: users.php");
  exit();
}

// Handle role change
if (isset($_GET["role"]) && isset($_GET["id"])) {
  $new_role = $_GET["role"];
  $user_id  = $_GET["id"];
  $allowed  = ["customer", "provider", "admin"];
  if (in_array($new_role, $allowed)) {
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$new_role, $user_id]);
  }
  header("Location: users.php");
  exit();
}

// Search filter
$search = trim($_GET["search"] ?? "");
$role   = trim($_GET["role_filter"] ?? "");

$where  = ["1=1"];
$params = [];

if (!empty($search)) {
  $where[]  = "(full_name LIKE ? OR email LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

if (!empty($role)) {
  $where[]  = "role = ?";
  $params[] = $role;
}

$where_sql = implode(" AND ", $where);

$stmt = $pdo->prepare("
  SELECT * FROM users
  WHERE $where_sql
  ORDER BY created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by role
$counts = $pdo->query("
  SELECT role, COUNT(*) as total
  FROM users GROUP BY role
")->fetchAll(PDO::FETCH_ASSOC);
$role_counts = [];
foreach ($counts as $c) {
  $role_counts[$c["role"]] = $c["total"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users – FindIt Nepal</title>
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
      <a href="users.php" class="sidebar-link active">
        <span class="sidebar-icon">👥</span> Manage Users
      </a>
      <a href="services.php" class="sidebar-link">
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
        <h1 id="dash-title">Manage Users</h1>
        <p id="dash-sub">
          View, filter and manage all registered users.
        </p>
      </div>
    </div>

    <!-- ROLE STAT CARDS -->
    <div id="dash-stats">
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#EFF6FF;">👥</div>
        <div class="stat-card-info">
          <span class="stat-card-num">
            <?= array_sum(array_column($counts, 'total')) ?>
          </span>
          <span class="stat-card-label">Total Users</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#DCFCE7;">👤</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $role_counts["customer"] ?? 0 ?></span>
          <span class="stat-card-label">Customers</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#FFF7ED;">🛠️</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $role_counts["provider"] ?? 0 ?></span>
          <span class="stat-card-label">Providers</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon" style="background:#FEE2E2;">🔑</div>
        <div class="stat-card-info">
          <span class="stat-card-num"><?= $role_counts["admin"] ?? 0 ?></span>
          <span class="stat-card-label">Admins</span>
        </div>
      </div>
    </div>

    <!-- SEARCH & FILTER -->
    <form method="GET" action="users.php" id="filter-form">
      <div class="filter-bar">
        <input type="text" name="search"
          class="filter-input" placeholder="Search by name or email..."
          value="<?= htmlspecialchars($search) ?>">
        <select name="role_filter" class="filter-input">
          <option value="">All Roles</option>
          <option value="customer" <?= $role=="customer" ? "selected":"" ?>>Customer</option>
          <option value="provider" <?= $role=="provider" ? "selected":"" ?>>Provider</option>
          <option value="admin"    <?= $role=="admin"    ? "selected":"" ?>>Admin</option>
        </select>
        <button type="submit" class="filter-btn">Filter</button>
        <?php if (!empty($search) || !empty($role)): ?>
          <a href="users.php" class="filter-clear">✕ Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- USERS TABLE -->
    <div class="admin-panel">
      <div class="section-header">
        <h2 class="section-heading">
          All Users
          <span class="count-badge"><?= count($users) ?></span>
        </h2>
      </div>

      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>User</th>
              <th>Phone</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Change Role</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr>
              <td class="td-num"><?= $i + 1 ?></td>
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
              <td><?= htmlspecialchars($u["phone"] ?? "—") ?></td>
              <td>
                <span class="role-badge role-<?= $u["role"] ?>">
                  <?= ucfirst($u["role"]) ?>
                </span>
              </td>
              <td><?= date("M d, Y", strtotime($u["created_at"])) ?></td>
              <td>
                <?php if ($u["id"] != $_SESSION["user_id"]): ?>
                <div class="role-change-btns">
                  <?php if ($u["role"] != "customer"): ?>
                  <a href="users.php?id=<?= $u["id"] ?>&role=customer"
                     class="role-btn customer">Customer</a>
                  <?php endif; ?>
                  <?php if ($u["role"] != "provider"): ?>
                  <a href="users.php?id=<?= $u["id"] ?>&role=provider"
                     class="role-btn provider">Provider</a>
                  <?php endif; ?>
                  <?php if ($u["role"] != "admin"): ?>
                  <a href="users.php?id=<?= $u["id"] ?>&role=admin"
                     class="role-btn admin-role">Admin</a>
                  <?php endif; ?>
                </div>
                <?php else: ?>
                  <span style="font-size:0.75rem;color:#94A3B8;">You</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($u["id"] != $_SESSION["user_id"]): ?>
                <a href="users.php?delete=<?= $u["id"] ?>"
                   class="btn-del-sm"
                   onclick="return confirm('Delete <?= htmlspecialchars($u["full_name"]) ?>? This cannot be undone.')">
                  Delete
                </a>
                <?php endif; ?>
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