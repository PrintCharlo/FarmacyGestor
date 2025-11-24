<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Almacenista' && $_SESSION['role'] !== 'Admin')) {
  header("Location: ../dashboard.php");
  exit();
}

$product_id  = (int)($_POST['product_id'] ?? 0);
$batch_code  = trim($_POST['batch_code'] ?? '');
$expiry_date = $_POST['expiry_date'] ?? '';
$quantity    = (int)($_POST['quantity'] ?? 0);
$unit_cost   = (float)($_POST['unit_cost'] ?? 0);

if ($product_id <= 0 || $quantity <= 0 || $unit_cost <= 0 || !$batch_code || !$expiry_date) {
  $_SESSION['error'] = "Datos inválidos. Verifica que todos los campos estén llenos y sean correctos.";
  header("Location: ../modules/inventario.php");
  exit();
}

// Verificar que el producto existe
$prodCheck = pg_query_params($conn, "SELECT id FROM products WHERE id = $1", [$product_id]);
if (!$prodCheck || pg_num_rows($prodCheck) === 0) {
  $_SESSION['error'] = "El producto seleccionado no existe";
  header("Location: ../modules/inventario.php");
  exit();
}

// Verificar que el batch_code no esté duplicado
$batchCheck = pg_query_params($conn, "SELECT id FROM inventory_batches WHERE batch_code = $1", [$batch_code]);
if ($batchCheck && pg_num_rows($batchCheck) > 0) {
  $_SESSION['error'] = "El código de lote '{$batch_code}' ya existe";
  header("Location: ../modules/inventario.php");
  exit();
}

$res = pg_query_params($conn, "
  INSERT INTO inventory_batches (product_id, batch_code, expiry_date, quantity, unit_cost, created_at)
  VALUES ($1, $2, $3, $4, $5, NOW())
", [$product_id, $batch_code, $expiry_date, $quantity, $unit_cost]);

if ($res) {
  $_SESSION['success'] = "Lote '{$batch_code}' agregado correctamente con {$quantity} unidades";
} else {
  $_SESSION['error'] = "Error al agregar lote: " . pg_last_error($conn);
}

header("Location: ../modules/inventario.php");
exit();
?>