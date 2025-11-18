<?php
// php/middleware.php
session_start();

function requireAuth() {
  if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
  }
}

function requireRole(array $roles = []) {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php"); exit();
  }
  $role = $_SESSION['role'] ?? '';

  // Admin pasa siempre
  if ($role === 'Admin') return;

  if (!in_array($role, $roles)) {
    header("Location: /dashboard.php"); exit();
  }
}
