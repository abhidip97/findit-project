<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
  header("Location: ../login.php");
  exit();
}

// Check if user has correct role
function require_role($role) {
  if ($_SESSION["user_role"] !== $role) {
    header("Location: ../login.php");
    exit();
  }
}
?>