<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

$order_id = (int)($_POST['order_id'] ?? 0);
$status   = trim($_POST['status'] ?? '');
$user_id  = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'] ?? '';

if ($order_id <= 0 || $status === '') {
  $_SESSION['error'] = "Datos inválidos";
  header("Location: ../modules/pedidos.php");
  exit();
}

// Roles permitidos simplificados
$allowedRoles = ['Almacenista','Proveedor','Admin'];
if (!in_array($role, $allowedRoles)) {
  $_SESSION['error'] = "No autorizado";
  header("Location: ../modules/pedidos.php");
  exit();
}

// Restricciones por rol
if ($role === 'Proveedor' && $status !== 'ENVIADO' && $status !== 'PENDING') {
  $_SESSION['error'] = "Proveedor sólo puede marcar ENVIADO o dejar PENDING";
  header("Location: ../modules/pedidos.php");
  exit();
}
if ($role === 'Almacenista' && !in_array($status, ['PENDING','APPROVED','RECEIVED','CANCELLED'])) {
  $_SESSION['error'] = "Estado no permitido para Almacenista";
  header("Location: ../modules/pedidos.php");
  exit();
}

pg_query($conn, "BEGIN");

try {
  // Bloqueamos la fila del pedido para evitar condiciones de carrera
  $poRes = pg_query_params($conn,
    "SELECT id, supplier_id, status FROM purchase_orders WHERE id = $1 FOR UPDATE",
    [$order_id]
  );
  if (!$poRes || pg_num_rows($poRes) === 0) {
    throw new Exception("Pedido no encontrado");
  }
  $po = pg_fetch_assoc($poRes);
  $old_status = $po['status'];

  // Evitar procesar RECEIVED dos veces
  if ($old_status === 'RECEIVED' && $status === 'RECEIVED') {
    throw new Exception("El pedido ya está marcado como RECEIVED");
  }

  // Actualizar estado
  $upd = pg_query_params($conn,
    "UPDATE purchase_orders SET status = $1, updated_by = $2, updated_at = NOW() WHERE id = $3",
    [$status, $user_id, $order_id]
  );
  if ($upd === false) throw new Exception("Error al actualizar pedido");

  // Si cambiamos a RECEIVED (o si proveedor marcó ENVIADO y quieres que Almacenista confirme RECEIVED más tarde,
  // solo procesar creación de lotes cuando llegue RECEIVED).
  if ($status === 'RECEIVED') {
    // Obtener items del pedido
    $itemsRes = pg_query_params($conn,
      "SELECT id, product_id, quantity, unit_price FROM purchase_order_items WHERE purchase_order_id = $1",
      [$order_id]
    );
    if ($itemsRes === false) throw new Exception("Error al obtener items del pedido");

    // Si no hay items, es un error
    if (pg_num_rows($itemsRes) === 0) {
      throw new Exception("El pedido no tiene items");
    }

    // Preparar statement para insertar lote y movimiento (usamos pg_query_params cada vez)
    while ($item = pg_fetch_assoc($itemsRes)) {
      $product_id = (int)$item['product_id'];
      $qty = (int)$item['quantity'];
      $unit_price = (float)$item['unit_price'];

      // Crear batch_code único
      $batch_code = 'PO'.$order_id.'_'.time().'_'.mt_rand(100,999);
      $expiry = date('Y-m-d', strtotime('+1 year'));

      $batchRes = pg_query_params($conn,
        "INSERT INTO inventory_batches (product_id, batch_code, expiry_date, quantity, unit_cost)
         VALUES ($1,$2,$3,$4,$5) RETURNING id",
        [$product_id, $batch_code, $expiry, $qty, $unit_price]
      );
      if ($batchRes === false) throw new Exception("Error al crear lote para producto {$product_id}");

      $batch_id = (int)pg_fetch_result($batchRes, 0, 0);

      // Insertar movimiento IN
      $moveRes = pg_query_params($conn,
        "INSERT INTO stock_movements (batch_id, product_id, movement_type, quantity, reason, ref_table, ref_id, created_at)
         VALUES ($1,$2,$3,$4,$5,$6,$7,NOW())",
        [$batch_id, $product_id, 'IN', $qty, 'PURCHASE_RECEIVE', 'purchase_orders', $order_id]
      );
      if ($moveRes === false) throw new Exception("Error al registrar movimiento para batch {$batch_id}");
    }
  }

  pg_query($conn, "COMMIT");
  $_SESSION['success'] = "Estado actualizado correctamente";
  header("Location: ../modules/pedidos.php");
  exit();

} catch (Exception $e) {
  pg_query($conn, "ROLLBACK");
  $_SESSION['error'] = "Error: " . $e->getMessage();
  header("Location: ../modules/pedidos.php");
  exit();
}
