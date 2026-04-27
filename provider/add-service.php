<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../includes/auth.php';
require_role('provider');
require '../includes/db.php';

$user_id = $_SESSION["user_id"];
$success = "";
$errors  = [];

// Get all categories for dropdown
$categories = $pdo->query("
  SELECT * FROM service_categories ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  // 1. Get and clean inputs
  $title        = trim($_POST["title"]);
  $category_id  = trim($_POST["category_id"]);
  $price        = trim($_POST["price"]);
  $price_type   = trim($_POST["price_type"]);
  $location     = trim($_POST["location"]);
  $description  = trim($_POST["description"]);
  $is_available = isset($_POST["is_available"]) ? 1 : 0;

  // 2. Validate
  if (empty($title)) {
    $errors[] = "Service title is required.";
  }

  if (empty($category_id)) {
    $errors[] = "Please select a category.";
  }

  if (empty($price) || !is_numeric($price) || $price <= 0) {
    $errors[] = "Please enter a valid price.";
  }

  if (empty($price_type)) {
    $errors[] = "Please select a price type.";
  }

  if (empty($location)) {
    $errors[] = "Please enter your service location.";
  }

  // 3. Insert if no errors
  if (empty($errors)) {
    $stmt = $pdo->prepare("
      INSERT INTO services
        (provider_id, category_id, title, description,
         price, price_type, location, is_available)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $user_id,
      $category_id,
      $title,
      $description,
      $price,
      $price_type,
      $location,
      $is_available
    ]);

    $success = "Service added successfully! Customers can now find and book you.";

    // Clear form values after success
    $_POST = [];
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Service – FindIt Nepal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="css/add-service.css">
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
      <a href="services.php" class="sidebar-link">
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

    <!-- TOP BAR -->
    <div id="dash-topbar">
      <div>
        <h1 id="dash-title">Add New Service</h1>
        <p id="dash-sub">
          List your skills so customers can find and book you.
        </p>
      </div>
      <a href="dashboard.php" id="btn-back-dash">← Back to Dashboard</a>
    </div>

    <!-- FORM + TIPS LAYOUT -->
    <div id="add-service-layout">

      <!-- LEFT: Form -->
      <div id="add-service-form-panel">

        <!-- Success -->
        <?php if ($success): ?>
          <div class="alert-success">
            <?= $success ?>
            <br><br>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <a href="add-service.php" class="alert-link-btn">
                + Add Another Service
              </a>
              <a href="dashboard.php" class="alert-link-btn outline">
                Go to Dashboard
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
          <div class="alert-error">
            <?php foreach ($errors as $e): ?>
              <p>⚠ <?= $e ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="add-service.php">

          <!-- Service Title -->
          <div class="form-group">
            <label class="form-label">
              Service Title *
              <span class="form-hint">
                Be specific e.g. "Home Electrical Repair"
              </span>
            </label>
            <input type="text" name="title" class="form-input"
              placeholder="e.g. Home Electrical Wiring & Repair"
              value="<?= isset($_POST['title'])
                ? htmlspecialchars($_POST['title']) : '' ?>">
          </div>

          <!-- Category + Price Row -->
          <div class="form-row">

            <div class="form-group">
              <label class="form-label">Category *</label>
              <select name="category_id" class="form-input">
                <option value="">Select a category</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat["id"] ?>"
                  <?= (isset($_POST["category_id"]) &&
                    $_POST["category_id"] == $cat["id"]) ? "selected" : "" ?>>
                  <?= $cat["icon"] ?> <?= htmlspecialchars($cat["name"]) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Your Price (Rs.) *</label>
              <input type="number" name="price" class="form-input"
                placeholder="e.g. 500"
                min="1"
                value="<?= isset($_POST['price'])
                  ? htmlspecialchars($_POST['price']) : '' ?>">
            </div>

          </div>

          <!-- Price Type + Location Row -->
          <div class="form-row">

            <div class="form-group">
              <label class="form-label">Price Type *</label>
              <select name="price_type" class="form-input">
                <option value="">Select price type</option>
                <option value="per_hour"
                  <?= (isset($_POST["price_type"]) &&
                    $_POST["price_type"] == "per_hour") ? "selected" : "" ?>>
                  Per Hour
                </option>
                <option value="per_day"
                  <?= (isset($_POST["price_type"]) &&
                    $_POST["price_type"] == "per_day") ? "selected" : "" ?>>
                  Per Day
                </option>
                <option value="per_visit"
                  <?= (isset($_POST["price_type"]) &&
                    $_POST["price_type"] == "per_visit") ? "selected" : "" ?>>
                  Per Visit
                </option>
                <option value="fixed"
                  <?= (isset($_POST["price_type"]) &&
                    $_POST["price_type"] == "fixed") ? "selected" : "" ?>>
                  Fixed Price
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Service Location *</label>
              <input type="text" name="location" class="form-input"
                placeholder="e.g. Kathmandu, Lalitpur"
                value="<?= isset($_POST['location'])
                  ? htmlspecialchars($_POST['location']) : '' ?>">
            </div>

          </div>

          <!-- Description -->
          <div class="form-group">
            <label class="form-label">
              Description
              <span class="form-hint">
                Describe what you offer, your experience, tools used etc.
              </span>
            </label>
            <textarea name="description" class="form-input form-textarea"
              placeholder="e.g. I have 5 years of experience in home electrical work including wiring, socket installation, fan fitting and fault repair..."
              ><?= isset($_POST['description'])
                ? htmlspecialchars($_POST['description']) : '' ?></textarea>
          </div>

          <!-- Availability Toggle -->
          <div class="form-group">
            <label class="form-label">Availability</label>
            <div id="availability-options">
              <label class="radio-option">
                <input type="radio" name="is_available" value="1"
                  <?= (!isset($_POST["is_available"]) ||
                    $_POST["is_available"] == "1") ? "checked" : "" ?>>
                <span class="radio-box">
                  <span class="radio-dot"></span>
                </span>
                <span class="radio-label">
                  ✅ Available — Customers can book me now
                </span>
              </label>
              <label class="radio-option">
                <input type="radio" name="is_available" value="0"
                  <?= (isset($_POST["is_available"]) &&
                    $_POST["is_available"] == "0") ? "checked" : "" ?>>
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
            Add Service →
          </button>

        </form>
        <?php endif; ?>

      </div>

      <!-- RIGHT: Tips -->
      <div id="tips-panel">

        <h4 id="tips-title">💡 Tips for a Great Listing</h4>

        <div class="tip-item">
          <div class="tip-num">1</div>
          <div>
            <div class="tip-heading">Use a clear title</div>
            <div class="tip-desc">
              Instead of "Electrical Work" write
              "Home Electrical Wiring & Repair" — it
              gets more bookings.
            </div>
          </div>
        </div>

        <div class="tip-item">
          <div class="tip-num">2</div>
          <div>
            <div class="tip-heading">Set a fair price</div>
            <div class="tip-desc">
              Research what others charge in your area.
              You can always edit your price later.
            </div>
          </div>
        </div>

        <div class="tip-item">
          <div class="tip-num">3</div>
          <div>
            <div class="tip-heading">Write a good description</div>
            <div class="tip-desc">
              Mention your years of experience, tools
              you use, and what makes you different.
              Customers trust detailed profiles.
            </div>
          </div>
        </div>

        <div class="tip-item">
          <div class="tip-num">4</div>
          <div>
            <div class="tip-heading">Add your exact location</div>
            <div class="tip-desc">
              Customers search by location. Be specific
              — "Baneshwor, Kathmandu" is better than
              just "Kathmandu".
            </div>
          </div>
        </div>

        <!-- Preview card -->
        <div id="preview-box">
          <div id="preview-label">Your card will look like this:</div>
          <div id="preview-card">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
              <span id="prev-icon" style="font-size:1.4rem;">⚡</span>
              <span class="src-category">Category</span>
            </div>
            <div id="prev-title" style="font-weight:700;font-size:0.9rem;color:#0F172A;margin-bottom:6px;">
              Your Service Title
            </div>
            <div style="font-size:0.78rem;color:#64748B;margin-bottom:8px;">
              👤 <?= htmlspecialchars($user["full_name"] ?? "Your Name") ?>
            </div>
            <div style="font-size:0.95rem;font-weight:800;color:#0F172A;">
              Rs. <span id="prev-price">0</span>
              <span id="prev-type" style="font-size:0.72rem;font-weight:400;color:#64748B;">
                / per visit
              </span>
            </div>
          </div>
        </div>

      </div>

    </div>

  </main>
</div>

<!-- Live Preview Script -->
<script>
  // Update preview card as user types
  const titleInput    = document.querySelector('input[name="title"]');
  const priceInput    = document.querySelector('input[name="price"]');
  const priceType     = document.querySelector('select[name="price_type"]');
  const categorySelect = document.querySelector('select[name="category_id"]');

  const prevTitle = document.getElementById('prev-title');
  const prevPrice = document.getElementById('prev-price');
  const prevType  = document.getElementById('prev-type');
  const prevIcon  = document.getElementById('prev-icon');

  // Category icons map
  const icons = {
    '⚡': 'Electrician', '🔧': 'Plumber', '🪵': 'Carpenter',
    '📚': 'Tutor', '🧹': 'Cleaner', '🎨': 'Painter',
    '🚗': 'Mechanic', '💻': 'IT Support', '🍳': 'Cook', '🌿': 'Gardener'
  };

  if (titleInput) {
    titleInput.addEventListener('input', () => {
      prevTitle.textContent = titleInput.value || 'Your Service Title';
    });
  }

  if (priceInput) {
    priceInput.addEventListener('input', () => {
      prevPrice.textContent = priceInput.value || '0';
    });
  }

  if (priceType) {
    priceType.addEventListener('change', () => {
      prevType.textContent = '/ ' + priceType.value.replace('_', ' ');
    });
  }

  if (categorySelect) {
    categorySelect.addEventListener('change', () => {
      const selected = categorySelect.options[categorySelect.selectedIndex].text;
      const icon = selected.trim().charAt(0);
      prevIcon.textContent = icon || '⚡';
    });
  }
</script>

</body>
</html>