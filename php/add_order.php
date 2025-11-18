<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Almacenista' && $_SESSION['role'] !== 'Admin') {
  header("Location: ../dashboard.php");
  exit();
}

$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$product_id  = (int)($_POST['product_id'] ?? 0);
$quantity    = (int)($_POST['quantity'] ?? 0);
$unit_price  = (float)($_POST['unit_price'] ?? 0);
$eta_date    = $_POST['eta_date'] ?? null;

if ($supplier_id <= 0 || $product_id <= 0 || $quantity <= 0 || $unit_price <= 0) {
  $_SESSION['error'] = "Datos inválidos";
  header("Location: ../modules/pedidos.php");
  exit();
}

pg_query($conn, "BEGIN");

$orderRes = pg_query_params($conn,
  "INSERT INTO purchase_orders (supplier_id, status, requested_by, eta_date, created_at) 
   VALUES ($1,'PENDING',$2,$3,NOW()) RETURNING id",
  [$supplier_id, $_SESSION['user_id'], $eta_date]
);

if (!$orderRes) {
  pg_query($conn, "ROLLBACK");
  $_SESSION['error'] = "Error al crear pedido";
  header("Location: ../modules/pedidos.php");
  exit();
}
$order_id = pg_fetch_result($orderRes, 0, 0);

$itemRes = pg_query_params($conn,
  "INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price) 
   VALUES ($1,$2,$3,$4)",
  [$order_id, $product_id, $quantity, $unit_price]
);

if (!$itemRes) {
  pg_query($conn, "ROLLBACK");
  $_SESSION['error'] = "Error al agregar producto al pedido";
  header("Location: ../modules/pedidos.php");
  exit();
}

pg_query($conn, "COMMIT");

$_SESSION['success'] = "Pedido creado correctamente";
header("Location: ../modules/pedidos.php");
