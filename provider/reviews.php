<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('provider');
require '../includes/db.php';

$user_id = $_SESSION["user_id"];

// Get all reviews for this provider
$stmt = $pdo->prepare("
  SELECT
    r.*,
    u.full_name  AS customer_name,
    s.title      AS service_title,
    c.icon       AS category_icon
  FROM reviews r
  JOIN users u    ON r.customer_id = u.id
  JOIN bookings b ON r.booking_id  = b.id
  JOIN services s ON b.service_id  = s.id
  JOIN service_categories c ON s.category_id = c.id
  WHERE r.provider_id = ?
  ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_reviews = count($reviews);
$avg_rating    = $total_reviews > 0
  ? number_format(
      array_sum(array_column($reviews, 'rating')) / $total_reviews, 1
    )
  : 0;

// Count per star
$star_counts = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
foreach ($reviews as $r) {
  $star_counts[$r["rating"]]++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Reviews – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/reviews.css">
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
      <a href="bookings.php" class="sidebar-link">
        <span class="sidebar-icon">📅</span> Bookings
      </a>
      <a href="reviews.php" class="sidebar-link active">
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
        <h1 id="dash-title">My Reviews</h1>
        <p id="dash-sub">See what customers say about your services.</p>
      </div>
    </div>

    <?php if ($total_reviews > 0): ?>

    <!-- RATING SUMMARY -->
    <div id="rating-summary-box">

      <!-- Big average -->
      <div id="rating-big">
        <div id="rating-big-num"><?= $avg_rating ?></div>
        <div id="rating-big-stars">
          <?php
          $rounded = round($avg_rating);
          for ($i = 1; $i <= 5; $i++) {
            echo $i <= $rounded
              ? '<span class="star-filled">★</span>'
              : '<span class="star-empty">★</span>';
          }
          ?>
        </div>
        <div id="rating-big-total">
          <?= $total_reviews ?> reviews
        </div>
      </div>

      <!-- Star breakdown bars -->
      <div id="rating-bars">
        <?php for ($star = 5; $star >= 1; $star--): ?>
        <div class="rating-bar-row">
          <span class="bar-label"><?= $star ?> ★</span>
          <div class="bar-track">
            <div class="bar-fill" style="width: <?=
              $total_reviews > 0
                ? ($star_counts[$star] / $total_reviews * 100)
                : 0
            ?>%"></div>
          </div>
          <span class="bar-count"><?= $star_counts[$star] ?></span>
        </div>
        <?php endfor; ?>
      </div>

    </div>

    <?php endif; ?>

    <!-- REVIEWS LIST -->
    <?php if (empty($reviews)): ?>
      <div id="empty-state">
        <div id="empty-icon">⭐</div>
        <h4>No reviews yet</h4>
        <p>Complete bookings and customers will be able to leave reviews.</p>
      </div>

    <?php else: ?>
      <div id="reviews-list">
        <?php foreach ($reviews as $r): ?>
        <div class="review-list-item">

          <div class="rli-left">
            <div class="rli-avatar">
              <?= strtoupper(substr($r["customer_name"], 0, 1)) ?>
            </div>
          </div>

          <div class="rli-right">
            <div class="rli-top">
              <div>
                <span class="rli-name">
                  <?= htmlspecialchars($r["customer_name"]) ?>
                </span>
                <span class="rli-service">
                  <?= $r["category_icon"] ?>
                  <?= htmlspecialchars($r["service_title"]) ?>
                </span>
              </div>
              <div class="rli-meta">
                <div class="rli-stars">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="<?= $i <= $r["rating"]
                      ? "star-filled" : "star-empty" ?>">★</span>
                  <?php endfor; ?>
                </div>
                <span class="rli-date">
                  <?= date("M d, Y", strtotime($r["created_at"])) ?>
                </span>
              </div>
            </div>

            <?php if (!empty($r["comment"])): ?>
            <p class="rli-comment">
              "<?= htmlspecialchars($r["comment"]) ?>"
            </p>
            <?php endif; ?>
          </div>

        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>