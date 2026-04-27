<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('provider');
require '../includes/db.php';

$user_id    = $_SESSION["user_id"];
$service_id = $_GET["id"] ?? null;
$success    = "";
$errors     = [];

// Redirect if no ID
if (!$service_id) {
  header("Location: services.php");
  exit();
}

// Get service — make sure it belongs to this provider
$stmt = $pdo->prepare("
  SELECT * FROM services WHERE id = ? AND provider_id = ?
");
$stmt->execute([$service_id, $user_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
  header("Location: services.php");
  exit();
}

// Get categories
$categories = $pdo->query("
  SELECT * FROM service_categories ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $title        = trim($_POST["title"]);
  $category_id  = trim($_POST["category_id"]);
  $price        = trim($_POST["price"]);
  $price_type   = trim($_POST["price_type"]);
  $location     = trim($_POST["location"]);
  $description  = trim($_POST["description"]);
  $is_available = isset($_POST["is_available"]) ? 1 : 0;

  if (empty($title))       $errors[] = "Service title is required.";
  if (empty($category_id)) $errors[] = "Please select a category.";
  if (empty($price) || !is_numeric($price) || $price <= 0)
    $errors[] = "Please enter a valid price.";
  if (empty($location))    $errors[] = "Location is required.";

  if (empty($errors)) {
    $stmt = $pdo->prepare("
      UPDATE services SET
        category_id  = ?,
        title        = ?,
        description  = ?,
        price        = ?,
        price_type   = ?,
        location     = ?,
        is_available = ?
      WHERE id = ? AND provider_id = ?
    ");
    $stmt->execute([
      $category_id, $title, $description,
      $price, $price_type, $location,
      $is_available, $service_id, $user_id
    ]);

    $success = "Service updated successfully!";

    // Refresh service data
    $stmt = $pdo->prepare("
      SELECT * FROM services WHERE id = ? AND provider_id = ?
    ");
    $stmt->execute([$service_id, $user_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Service – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/add-service.css">
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
      <a href="services.php" class="sidebar-link active">
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

  <!-- MAIN -->
  <main id="dash-main">

    <div id="dash-topbar">
      <div>
        <h1 id="dash-title">Edit Service</h1>
        <p id="dash-sub">Update your service details.</p>
      </div>
      <a href="services.php" id="btn-back-dash">← Back to Services</a>
    </div>

    <div id="add-service-layout">

      <!-- FORM PANEL -->
      <div id="add-service-form-panel">

        <?php if ($success): ?>
          <div class="alert-success">
            <?= $success ?>
            <br><br>
            <a href="services.php" class="alert-link-btn">
              ← Back to My Services
            </a>
          </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <div class="alert-error">
            <?php foreach ($errors as $e): ?>
              <p>⚠ <?= $e ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST"
              action="edit-service.php?id=<?= $service_id ?>">

          <div class="form-group">
            <label class="form-label">Service Title *</label>
            <input type="text" name="title" class="form-input"
              value="<?= htmlspecialchars($service["title"]) ?>">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Category *</label>
              <select name="category_id" class="form-input">
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat["id"] ?>"
                  <?= $service["category_id"] == $cat["id"]
                    ? "selected" : "" ?>>
                  <?= $cat["icon"] ?> <?= htmlspecialchars($cat["name"]) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Price (Rs.) *</label>
              <input type="number" name="price" class="form-input"
                min="1"
                value="<?= htmlspecialchars($service["price"]) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Price Type *</label>
              <select name="price_type" class="form-input">
                <?php
                $types = ["per_hour","per_day","per_visit","fixed"];
                foreach ($types as $t):
                ?>
                <option value="<?= $t ?>"
                  <?= $service["price_type"] == $t ? "selected":""?>>
                  <?= ucfirst(str_replace("_"," ",$t)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Location *</label>
              <input type="text" name="location" class="form-input"
                value="<?= htmlspecialchars($service["location"] ?? '') ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description"
                      class="form-input form-textarea">
              <?= htmlspecialchars($service["description"] ?? '') ?>
            </textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Availability</label>
            <div id="availability-options">
              <label class="radio-option">
                <input type="radio" name="is_available" value="1"
                  <?= $service["is_available"] == 1 ? "checked":"" ?>>
                <span class="radio-box">
                  <span class="radio-dot"></span>
                </span>
                <span class="radio-label">
                  ✅ Available — Customers can book me now
                </span>
              </label>
              <label class="radio-option">
                <input type="radio" name="is_available" value="0"
                  <?= $service["is_available"] == 0 ? "checked":"" ?>>
                <span class="radio-box">
                  <span class="radio-dot"></span>
                </span>
                <span class="radio-label">
                  ⏸ Unavailable — Hide this service for now
                </span>
              </label>
            </div>
          </div>

          <button type="submit" class="btn-submit">
            Update Service →
          </button>

        </form>
      </div>

      <!-- TIPS PANEL -->
      <div id="tips-panel">
        <h4 id="tips-title">💡 Editing Tips</h4>
        <div class="tip-item">
          <div class="tip-num">1</div>
          <div>
            <div class="tip-heading">Update your price regularly</div>
            <div class="tip-desc">
              Adjust your price based on demand and
              what competitors charge in your area.
            </div>
          </div>
        </div>
        <div class="tip-item">
          <div class="tip-num">2</div>
          <div>
            <div class="tip-heading">Toggle availability</div>
            <div class="tip-desc">
              Going on holiday? Set your service to
              Unavailable so customers don't book
              during that period.
            </div>
          </div>
        </div>
        <div class="tip-item">
          <div class="tip-num">3</div>
          <div>
            <div class="tip-heading">Improve your description</div>
            <div class="tip-desc">
              The more detail you provide, the more
              trust customers have in booking you.
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>