<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('customer');
require '../includes/db.php';

// Get all categories for filter dropdown
$categories = $pdo->query("
  SELECT * FROM service_categories ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Get search inputs
$search      = trim($_GET["search"]   ?? "");
$category_id = trim($_GET["category"] ?? "");
$location    = trim($_GET["location"] ?? "");
$sort        = trim($_GET["sort"]     ?? "newest");

// Build dynamic query based on filters
$where  = ["s.is_available = 1"];
$params = [];

if (!empty($search)) {
  $where[]  = "(s.title LIKE ? OR u.full_name LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

if (!empty($category_id)) {
  $where[]  = "s.category_id = ?";
  $params[] = $category_id;
}

if (!empty($location)) {
  $where[]  = "s.location LIKE ?";
  $params[] = "%$location%";
}

$where_sql = "WHERE " . implode(" AND ", $where);

// Sort
$order_sql = match($sort) {
  "price_low"  => "ORDER BY s.price ASC",
  "price_high" => "ORDER BY s.price DESC",
  "rating"     => "ORDER BY avg_rating DESC",
  default      => "ORDER BY s.created_at DESC"
};

// Final query
$stmt = $pdo->prepare("
  SELECT
    s.*,
    u.full_name   AS provider_name,
    u.phone       AS provider_phone,
    c.name        AS category_name,
    c.icon        AS category_icon,
    COALESCE(AVG(r.rating), 0)  AS avg_rating,
    COUNT(r.id)                 AS total_reviews
  FROM services s
  JOIN users u              ON s.provider_id  = u.id
  JOIN service_categories c ON s.category_id  = c.id
  LEFT JOIN bookings b      ON b.service_id   = s.id
  LEFT JOIN reviews r       ON r.booking_id   = b.id
  $where_sql
  GROUP BY s.id
  $order_sql
");
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find Services – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/search.css">
</head>
<body>

<div id="dash-wrapper">

  <!-- SIDEBAR -->
  <aside id="sidebar">
    <a href="../index.html" id="sidebar-logo">
      FindIt<span>.</span>np
    </a>
    <nav id="sidebar-nav">
      <a href="dashboard.php" class="sidebar-link">
        <span class="sidebar-icon">🏠</span> Dashboard
      </a>
      <a href="search.php" class="sidebar-link active">
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

  <!-- MAIN -->
  <main id="dash-main">

    <!-- TOP BAR -->
    <div id="dash-topbar">
      <div>
        <h1 id="dash-title">Find Services</h1>
        <p id="dash-sub">Browse and book trusted professionals near you.</p>
      </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <form method="GET" action="search.php" id="search-form">
      <div id="search-bar">

        <div class="search-field">
          <label class="search-label">Search</label>
          <input type="text" name="search"
            class="search-input"
            placeholder="Service or provider name..."
            value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="search-field">
          <label class="search-label">Category</label>
          <select name="category" class="search-input">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat["id"] ?>"
              <?= $category_id == $cat["id"] ? "selected" : "" ?>>
              <?= $cat["icon"] ?> <?= htmlspecialchars($cat["name"]) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="search-field">
          <label class="search-label">Location</label>
          <input type="text" name="location"
            class="search-input"
            placeholder="e.g. Kathmandu"
            value="<?= htmlspecialchars($location) ?>">
        </div>

        <div class="search-field">
          <label class="search-label">Sort By</label>
          <select name="sort" class="search-input">
            <option value="newest"     <?= $sort=="newest"     ? "selected":"" ?>>Newest</option>
            <option value="price_low"  <?= $sort=="price_low"  ? "selected":"" ?>>Price: Low to High</option>
            <option value="price_high" <?= $sort=="price_high" ? "selected":"" ?>>Price: High to Low</option>
            <option value="rating"     <?= $sort=="rating"     ? "selected":"" ?>>Top Rated</option>
          </select>
        </div>

        <button type="submit" id="btn-search">Search</button>

      </div>
    </form>

    <!-- RESULTS COUNT -->
    <div id="results-info">
      <?php if (!empty($search) || !empty($category_id) || !empty($location)): ?>
        <span>
          Found <strong><?= count($services) ?></strong> result(s)
          <?= !empty($search) ? "for \"<strong>" . htmlspecialchars($search) . "</strong>\"" : "" ?>
        </span>
        <a href="search.php" id="clear-filters">✕ Clear Filters</a>
      <?php else: ?>
        <span>Showing <strong><?= count($services) ?></strong> available services</span>
      <?php endif; ?>
    </div>

    <!-- SERVICES GRID -->
    <?php if (empty($services)): ?>
      <div id="empty-state">
        <div id="empty-icon">🔍</div>
        <h4>No services found</h4>
        <p>Try a different keyword, category or location.</p>
        <a href="search.php" class="btn-empty">Clear Search</a>
      </div>

    <?php else: ?>
      <div id="search-results-grid">
        <?php foreach ($services as $s): ?>
        <div class="service-result-card">

          <!-- Card Top -->
          <div class="src-top">
            <div class="src-icon"><?= $s["category_icon"] ?></div>
            <span class="src-category">
              <?= htmlspecialchars($s["category_name"]) ?>
            </span>
          </div>

          <!-- Service Title -->
          <h5 class="src-title">
            <?= htmlspecialchars($s["title"]) ?>
          </h5>

          <!-- Provider -->
          <div class="src-provider">
            <div class="src-avatar">
              <?= strtoupper(substr($s["provider_name"], 0, 1)) ?>
            </div>
            <span><?= htmlspecialchars($s["provider_name"]) ?></span>
          </div>

          <!-- Location -->
          <?php if (!empty($s["location"])): ?>
          <div class="src-location">
            📍 <?= htmlspecialchars($s["location"]) ?>
          </div>
          <?php endif; ?>

          <!-- Rating -->
          <div class="src-rating">
            <?php
            $stars = round($s["avg_rating"]);
            for ($i = 1; $i <= 5; $i++) {
              echo $i <= $stars ? "★" : "☆";
            }
            ?>
            <span class="src-rating-num">
              <?= $s["avg_rating"] > 0
                ? number_format($s["avg_rating"], 1)
                : "No reviews" ?>
            </span>
            <?php if ($s["total_reviews"] > 0): ?>
              <span class="src-reviews">
                (<?= $s["total_reviews"] ?> reviews)
              </span>
            <?php endif; ?>
          </div>

          <!-- Price -->
          <div class="src-price">
            Rs. <?= number_format($s["price"], 0) ?>
            <span class="src-price-type">
              / <?= str_replace("_", " ", $s["price_type"]) ?>
            </span>
          </div>

          <!-- Book Button -->
          <a href="booking.php?service_id=<?= $s["id"] ?>"
             class="btn-book-now">
            Book Now
          </a>

        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>s