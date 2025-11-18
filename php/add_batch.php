<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Almacenista' && $_SESSION['role'] !== 'Admin') {
  header("Location: ../dashboard.php");
  exit();
}

$product_id  = (int)($_POST['product_id'] ?? 0);
$batch_code  = trim($_POST['batch_code'] ?? '');
$expiry_date = $_POST['expiry_date'] ?? '';
$quantity    = (int)($_POST['quantity'] ?? 0);
$unit_cost   = (float)($_POST['unit_cost'] ?? 0);

if ($product_id <= 0 || $quantity <= 0 || $unit_cost <= 0 || !$batch_code || !$expiry_date) {
  $_SESSION['error'] = "Datos inválidos";
  header("Location: ../modules/inventario.php");
  exit();
}

$res = pg_query_params($conn, "
  INSERT INTO inventory_batches (product_id, batch_code, expiry_date, quantity, unit_cost, created_at)
  VALUES ($1,$2,$3,$4,$5,NOW())
", [$product_id, $batch_code, $expiry_date, $quantity, $unit_cost]);

if ($res) {
  $_SESSION['success'] = "Lote agregado correctamente";
} else {
  $_SESSION['error'] = "Error al agregar lote";
}

header("Location: ../modules/inventario.php");
