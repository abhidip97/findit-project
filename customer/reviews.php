<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('customer');
require '../includes/db.php';

$user_id = $_SESSION["user_id"];
$success = "";
$error   = "";

// Handle review submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $booking_id = $_POST["booking_id"];
  $rating     = (int)$_POST["rating"];
  $comment    = trim($_POST["comment"]);

  // Validate
  if ($rating < 1 || $rating > 5) {
    $error = "Please select a rating between 1 and 5.";
  } else {
    // Make sure booking belongs to this customer
    // and is completed and not already reviewed
    $stmt = $pdo->prepare("
      SELECT b.* FROM bookings b
      WHERE b.id = ?
      AND b.customer_id = ?
      AND b.status = 'completed'
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
      $error = "Invalid booking or booking not completed yet.";
    } else {
      // Check not already reviewed
      $stmt = $pdo->prepare("
        SELECT id FROM reviews WHERE booking_id = ?
      ");
      $stmt->execute([$booking_id]);

      if ($stmt->rowCount() > 0) {
        $error = "You have already reviewed this booking.";
      } else {
        // Insert review
        $stmt = $pdo->prepare("
          INSERT INTO reviews
            (booking_id, customer_id, provider_id, rating, comment)
          VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
          $booking_id,
          $user_id,
          $booking["provider_id"],
          $rating,
          $comment
        ]);
        $success = "Review submitted successfully! Thank you.";
      }
    }
  }
}

// Get all completed bookings for this customer
$stmt = $pdo->prepare("
  SELECT
    b.*,
    s.title      AS service_title,
    u.full_name  AS provider_name,
    c.icon       AS category_icon,
    c.name       AS category_name,
    r.id         AS review_id,
    r.rating     AS review_rating,
    r.comment    AS review_comment,
    r.created_at AS review_date
  FROM bookings b
  JOIN services s           ON b.service_id  = s.id
  JOIN users u              ON b.provider_id = u.id
  JOIN service_categories c ON s.category_id = c.id
  LEFT JOIN reviews r       ON r.booking_id  = b.id
  WHERE b.customer_id = ?
  AND b.status = 'completed'
  ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$completed_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
      <a href="search.php" class="sidebar-link">
        <span class="sidebar-icon">🔍</span> Find Services
      </a>
      <a href="bookings.php" class="sidebar-link">
        <span class="sidebar-icon">📅</span> My Bookings
      </a>
      <a href="reviews.php" class="sidebar-link active">
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
        <h1 id="dash-title">My Reviews</h1>
        <p id="dash-sub">
          Rate and review your completed services.
        </p>
      </div>
    </div>

    <!-- ALERTS -->
    <?php if ($success): ?>
      <div class="alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert-error"><?= $error ?></div>
    <?php endif; ?>

    <?php if (empty($completed_bookings)): ?>
      <div id="empty-state">
        <div id="empty-icon">⭐</div>
        <h4>No completed bookings yet</h4>
        <p>You can leave a review after a booking is marked as completed.</p>
        <a href="search.php" class="btn-empty">Find a Service</a>
      </div>

    <?php else: ?>
      <div id="reviews-grid">
        <?php foreach ($completed_bookings as $b): ?>
        <div class="review-card">

          <!-- Service Info -->
          <div class="review-card-top">
            <div class="review-service-icon">
              <?= $b["category_icon"] ?>
            </div>
            <div>
              <div class="review-service-title">
                <?= htmlspecialchars($b["service_title"]) ?>
              </div>
              <div class="review-provider">
                👤 <?= htmlspecialchars($b["provider_name"]) ?>
              </div>
              <div class="review-date-info">
                📅 <?= date("M d, Y", strtotime($b["book_date"])) ?>
              </div>
            </div>
          </div>

          <?php if ($b["review_id"]): ?>
            <!-- Already reviewed -->
            <div class="already-reviewed">
              <div class="submitted-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="<?= $i <= $b["review_rating"] ? "star-filled" : "star-empty" ?>">
                    ★
                  </span>
                <?php endfor; ?>
                <span class="rating-num">
                  <?= $b["review_rating"] ?>/5
                </span>
              </div>
              <?php if (!empty($b["review_comment"])): ?>
                <p class="submitted-comment">
                  "<?= htmlspecialchars($b["review_comment"]) ?>"
                </p>
              <?php endif; ?>
              <div class="reviewed-badge">✓ Review Submitted</div>
            </div>

          <?php else: ?>
            <!-- Review form -->
            <form method="POST" action="reviews.php"
                  class="review-form">
              <input type="hidden" name="booking_id"
                     value="<?= $b["id"] ?>">

              <!-- Star Rating -->
              <div class="star-picker">
                <label class="star-label">Your Rating *</label>
                <div class="stars-input">
                  <?php for ($i = 5; $i >= 1; $i--): ?>
                  <input type="radio" name="rating"
                         id="star<?= $b["id"] ?>-<?= $i ?>"
                         value="<?= $i ?>">
                  <label for="star<?= $b["id"] ?>-<?= $i ?>"
                         class="star-btn">★</label>
                  <?php endfor; ?>
                </div>
              </div>

              <!-- Comment -->
              <div class="form-group">
                <label class="form-label">
                  Your Review (optional)
                </label>
                <textarea name="comment"
                          class="form-input form-textarea"
                          placeholder="Describe your experience...">
                </textarea>
              </div>

              <button type="submit" class="btn-submit-review">
                Submit Review
              </button>

            </form>
          <?php endif; ?>

        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>