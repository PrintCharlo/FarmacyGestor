<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Vendedor' && $_SESSION['role'] !== 'Admin') {
  header("Location: ../dashboard.php");
  exit();
}

$product_id = (int)($_POST['product_id'] ?? 0);
$quantity   = (int)($_POST['quantity'] ?? 0);

if ($product_id <= 0 || $quantity <= 0) {
  $_SESSION['error'] = "Datos inválidos";
  header("Location: ../modules/ventas.php");
  exit();
}

// Precio unitario (usamos el último costo del lote como referencia)
$prodRes = pg_query_params($conn, "
  SELECT unit_cost FROM inventory_batches 
  WHERE product_id=$1 ORDER BY created_at DESC LIMIT 1
", [$product_id]);

if (!$prodRes || pg_num_rows($prodRes) === 0) {
  $_SESSION['error'] = "No hay lotes disponibles para este producto";
  header("Location: ../modules/ventas.php");
  exit();
}
$unit_price = (float)pg_fetch_result($prodRes, 0, 0);
$total = $unit_price * $quantity;

// Validar stock disponible
$stockRes = pg_query_params($conn, "
  SELECT COALESCE(SUM(quantity),0) FROM inventory_batches WHERE product_id=$1
", [$product_id]);
$stock = (int)pg_fetch_result($stockRes, 0, 0);

if ($stock < $quantity) {
  $_SESSION['error'] = "Stock insuficiente. Disponible: $stock";
  header("Location: ../modules/ventas.php");
  exit();
}

// Registrar venta y descontar inventario
pg_query($conn, "BEGIN");

$saleRes = pg_query_params($conn,
  "INSERT INTO sales (seller_id, total, created_at) VALUES ($1, $2, NOW()) RETURNING id",
  [$_SESSION['user_id'], $total]
);
if (!$saleRes) {
  pg_query($conn, "ROLLBACK");
  $_SESSION['error'] = "Error al registrar la venta";
  header("Location: ../modules/ventas.php");
  exit();
}
$sale_id = pg_fetch_result($saleRes, 0, 0);

// Insertar item de venta
$itemRes = pg_query_params($conn,
  "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price) VALUES ($1,$2,$3,$4)",
  [$sale_id, $product_id, $quantity, $unit_price]
);

// Descontar stock (FIFO por fecha de caducidad)
$batchRes = pg_query_params($conn,
  "SELECT id, quantity FROM inventory_batches WHERE product_id=$1 AND quantity>0 ORDER BY expiry_date ASC",
  [$product_id]
);

$remaining = $quantity;
while ($remaining > 0 && ($batch = pg_fetch_assoc($batchRes))) {
  $batch_id = (int)$batch['id'];
  $avail = (int)$batch['quantity'];
  $use = min($avail, $remaining);
  pg_query_params($conn,
    "UPDATE inventory_batches SET quantity = quantity - $1 WHERE id = $2",
    [$use, $batch_id]
  );
  $remaining -= $use;
}

if (!$itemRes || $remaining > 0) {
  pg_query($conn, "ROLLBACK");
  $_SESSION['error'] = "Error al registrar venta o descontar inventario";
  header("Location: ../modules/ventas.php");
  exit();
}

pg_query($conn, "COMMIT");

$_SESSION['success'] = "Venta registrada correctamente";
header("Location: ../modules/ventas.php");
