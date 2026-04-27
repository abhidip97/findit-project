<?php
require '../includes/auth.php';
require_role('provider');
require '../includes/db.php';

$user_id    = $_SESSION["user_id"];
$service_id = $_GET["id"] ?? null;

if ($service_id) {
  // Only delete if it belongs to this provider
  $stmt = $pdo->prepare("
    DELETE FROM services
    WHERE id = ? AND provider_id = ?
  ");
  $stmt->execute([$service_id, $user_id]);
}

header("Location: services.php");
exit();
?>