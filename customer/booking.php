<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('customer');
require '../includes/db.php';

$user_id    = $_SESSION["user_id"];
$service_id = $_GET["service_id"] ?? null;
$success    = "";
$error      = "";

// Get service details
if (!$service_id) {
  header("Location: search.php");
  exit();
}

$stmt = $pdo->prepare("
  SELECT
    s.*,
    u.full_name AS provider_name,
    u.phone     AS provider_phone,
    u.address   AS provider_address,
    c.name      AS category_name,
    c.icon      AS category_icon
  FROM services s
  JOIN users u              ON s.provider_id = u.id
  JOIN service_categories c ON s.category_id = c.id
  WHERE s.id = ? AND s.is_available = 1
");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

// If service not found redirect back
if (!$service) {
  header("Location: search.php");
  exit();
}

// Get service reviews
$stmt = $pdo->prepare("
  SELECT r.*, u.full_name AS customer_name
  FROM reviews r
  JOIN users u ON r.customer_id = u.id
  JOIN bookings b ON r.booking_id = b.id
  WHERE b.service_id = ?
  ORDER BY r.created_at DESC
  LIMIT 4
");
$stmt->execute([$service_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$avg_rating = count($reviews) > 0
  ? number_format(array_sum(array_column($reviews, "rating")) / count($reviews), 1)
  : null;

// Handle booking form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $book_date = $_POST["book_date"];
  $book_time = $_POST["book_time"];
  $address   = trim($_POST["address"]);
  $message   = trim($_POST["message"]);

  // Validate
  if (empty($book_date) || empty($address)) {
    $error = "Please fill in the date and address fields.";
  } elseif (strtotime($book_date) < strtotime("today")) {
    $error = "Please select a future date.";
  } else {
    // Check not already booked same service
    $stmt = $pdo->prepare("
      SELECT id FROM bookings
      WHERE customer_id = ? AND service_id = ?
      AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$user_id, $service_id]);

    if ($stmt->rowCount() > 0) {
      $error = "You already have an active booking for this service.";
    } else {
      // Insert booking
      $stmt = $pdo->prepare("
        INSERT INTO bookings
          (customer_id, service_id, provider_id, book_date, book_time, address, message)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([
        $user_id,
        $service_id,
        $service["provider_id"],
        $book_date,
        $book_time,
        $address,
        $message
      ]);

      $success = "Booking request sent successfully! The provider will confirm shortly.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book Service – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/booking.css">
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

    <!-- BACK BUTTON -->
    <div>
      <a href="search.php" id="btn-back">← Back to Search</a>
    </div>

    <div id="booking-layout">

      <!-- LEFT: Booking Form -->
      <div id="booking-form-panel">

        <h2 id="booking-title">Book This Service</h2>
        <p id="booking-sub">
          Fill in the details below and the provider
          will confirm your request.
        </p>

        <!-- Success -->
        <?php if ($success): ?>
          <div class="alert-success">
            <?= $success ?>
            <br>
            <a href="bookings.php" style="color:#166534;font-weight:700;">
              View My Bookings →
            </a>
          </div>
        <?php endif; ?>

        <!-- Error -->
        <?php if ($error): ?>
          <div class="alert-error"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST"
              action="booking.php?service_id=<?= $service_id ?>"
              id="booking-form">

          <div class="form-group">
            <label class="form-label">Date of Service *</label>
            <!-- REPLACE with this -->
            <input type="date" name="book_date" class="form-input"
              min="<?= date('Y-m-d') ?>"
              value="<?= isset($_POST['book_date']) ? $_POST['book_date'] : date('Y-m-d') ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Preferred Time</label>
            <input type="time" name="book_time" class="form-input"
              value="<?= isset($_POST['book_time']) ? $_POST['book_time'] : '' ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Your Address *</label>
            <input type="text" name="address" class="form-input"
              placeholder="Where do you need the service?"
              value="<?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Message to Provider</label>
            <textarea name="message" class="form-input form-textarea"
              placeholder="Describe your problem or any special instructions..."
              ><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
          </div>

          <button type="submit" class="btn-auth">
            Send Booking Request
          </button>

        </form>
        <?php endif; ?>

      </div>

      <!-- RIGHT: Service Info -->
      <div id="service-info-panel">

        <!-- Service Card -->
        <div id="service-summary">

          <div id="service-summary-top">
            <span class="src-icon" style="font-size:2rem;">
              <?= $service["category_icon"] ?>
            </span>
            <span class="src-category">
              <?= htmlspecialchars($service["category_name"]) ?>
            </span>
          </div>

          <h3 id="service-summary-title">
            <?= htmlspecialchars($service["title"]) ?>
          </h3>

          <?php if (!empty($service["description"])): ?>
          <p id="service-summary-desc">
            <?= htmlspecialchars($service["description"]) ?>
          </p>
          <?php endif; ?>

          <div id="service-summary-price">
            Rs. <?= number_format($service["price"], 0) ?>
            <span>/ <?= str_replace("_", " ", $service["price_type"]) ?></span>
          </div>

          <?php if (!empty($service["location"])): ?>
          <div class="src-location" style="margin-top:8px;">
            📍 <?= htmlspecialchars($service["location"]) ?>
          </div>
          <?php endif; ?>

          <!-- Provider info -->
          <div id="provider-info">
            <div class="src-avatar" style="width:40px;height:40px;font-size:0.95rem;">
              <?= strtoupper(substr($service["provider_name"], 0, 1)) ?>
            </div>
            <div>
              <div style="font-size:0.875rem;font-weight:700;color:#0F172A;">
                <?= htmlspecialchars($service["provider_name"]) ?>
              </div>
              <div style="font-size:0.75rem;color:#64748B;">Service Provider</div>
            </div>
          </div>

          <!-- Rating summary -->
          <?php if ($avg_rating): ?>
          <div id="rating-summary">
            <span style="color:#F59E0B;font-size:1rem;">★</span>
            <strong><?= $avg_rating ?></strong>
            <span style="color:#64748B;font-size:0.78rem;">
              (<?= count($reviews) ?> reviews)
            </span>
          </div>
          <?php endif; ?>

        </div>

        <!-- Reviews -->
        <?php if (!empty($reviews)): ?>
        <div id="service-reviews">
          <h4 id="reviews-title">Customer Reviews</h4>
          <?php foreach ($reviews as $r): ?>
          <div class="review-item">
            <div class="review-top">
              <div class="review-avatar">
                <?= strtoupper(substr($r["customer_name"], 0, 1)) ?>
              </div>
              <div>
                <div class="review-name">
                  <?= htmlspecialchars($r["customer_name"]) ?>
                </div>
                <div class="review-stars">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?= $i <= $r["rating"] ? "★" : "☆" ?>
                  <?php endfor; ?>
                </div>
              </div>
            </div>
            <?php if (!empty($r["comment"])): ?>
            <p class="review-comment">
              <?= htmlspecialchars($r["comment"]) ?>
            </p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>

    </div>
  </main>
</div>

</body>
</html>