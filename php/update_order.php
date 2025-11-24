<?php
session_start();
include("db.php");

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

// Validar datos de entrada
$order_id = (int)($_POST['order_id'] ?? 0);
$status   = trim($_POST['status'] ?? '');
$user_id  = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'] ?? '';

if ($order_id <= 0 || $status === '') {
  $_SESSION['error'] = "Datos inválidos";
  header("Location: ../modules/pedidos.php");
  exit();
}

// Validar roles permitidos
$allowedRoles = ['Almacenista','Proveedor','Admin'];
if (!in_array($role, $allowedRoles)) {
  $_SESSION['error'] = "No autorizado";
  header("Location: ../modules/pedidos.php");
  exit();
}

// Validar estados permitidos
$validStatuses = ['PENDING', 'APPROVED', 'ENVIADO', 'RECEIVED', 'CANCELLED'];
if (!in_array($status, $validStatuses)) {
  $_SESSION['error'] = "Estado no válido: {$status}";
  header("Location: ../modules/pedidos.php");
  exit();
}

// Restricciones por rol
if ($role === 'Proveedor' && !in_array($status, ['ENVIADO', 'PENDING'])) {
  $_SESSION['error'] = "Proveedor sólo puede marcar ENVIADO o dejar PENDING";
  header("Location: ../modules/pedidos.php");
  exit();
}

if ($role === 'Almacenista' && !in_array($status, ['PENDING','APPROVED','RECEIVED','CANCELLED'])) {
  $_SESSION['error'] = "Estado no permitido para Almacenista";
  header("Location: ../modules/pedidos.php");
  exit();
}

// Iniciar transacción
if (!pg_query($conn, "BEGIN")) {
  $_SESSION['error'] = "Error al iniciar transacción: " . pg_last_error($conn);
  header("Location: ../modules/pedidos.php");
  exit();
}

try {
  // Obtener pedido actual con bloqueo
  $poRes = pg_query_params($conn,
    "SELECT id, supplier_id, status FROM purchase_orders WHERE id = $1 FOR UPDATE",
    [$order_id]
  );
  
  if (!$poRes) {
    throw new Exception("Error en la consulta: " . pg_last_error($conn));
  }
  
  if (pg_num_rows($poRes) === 0) {
    throw new Exception("Pedido #{$order_id} no encontrado");
  }
  
  $po = pg_fetch_assoc($poRes);
  $old_status = $po['status'];

  // Evitar procesar RECEIVED dos veces
  if ($old_status === 'RECEIVED' && $status === 'RECEIVED') {
    throw new Exception("El pedido ya está marcado como RECEIVED");
  }

  // Verificar si las columnas updated_by y updated_at existen
  $columnCheck = pg_query($conn, "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'purchase_orders' 
    AND column_name IN ('updated_by', 'updated_at')
  ");
  
  $columns = [];
  while ($row = pg_fetch_assoc($columnCheck)) {
    $columns[] = $row['column_name'];
  }
  
  $hasUpdatedBy = in_array('updated_by', $columns);
  $hasUpdatedAt = in_array('updated_at', $columns);

  // Actualizar estado del pedido (adaptar según columnas disponibles)
  if ($hasUpdatedBy && $hasUpdatedAt) {
    // Si existen ambas columnas
    $upd = pg_query_params($conn,
      "UPDATE purchase_orders SET status = $1, updated_by = $2, updated_at = NOW() WHERE id = $3",
      [$status, $user_id, $order_id]
    );
  } elseif ($hasUpdatedAt) {
    // Solo existe updated_at
    $upd = pg_query_params($conn,
      "UPDATE purchase_orders SET status = $1, updated_at = NOW() WHERE id = $2",
      [$status, $order_id]
    );
  } else {
    // No existen las columnas, solo actualizar status
    $upd = pg_query_params($conn,
      "UPDATE purchase_orders SET status = $1 WHERE id = $2",
      [$status, $order_id]
    );
  }
  
  if ($upd === false) {
    throw new Exception("Error al actualizar pedido: " . pg_last_error($conn));
  }

  // Solo crear lotes cuando el estado cambie a RECEIVED
  if ($status === 'RECEIVED' && $old_status !== 'RECEIVED') {
    // Obtener items del pedido
    $itemsRes = pg_query_params($conn,
      "SELECT id, product_id, quantity, unit_price 
       FROM purchase_order_items 
       WHERE purchase_order_id = $1",
      [$order_id]
    );
    
    if ($itemsRes === false) {
      throw new Exception("Error al obtener items: " . pg_last_error($conn));
    }

    if (pg_num_rows($itemsRes) === 0) {
      throw new Exception("El pedido no tiene items asociados");
    }

    $itemsProcessed = 0;
    
    // Procesar cada item del pedido
    while ($item = pg_fetch_assoc($itemsRes)) {
      $product_id = (int)$item['product_id'];
      $qty = (int)$item['quantity'];
      $unit_price = (float)$item['unit_price'];

      // Verificar que el producto existe
      $prodCheck = pg_query_params($conn,
        "SELECT id, name FROM products WHERE id = $1",
        [$product_id]
      );
      
      if (!$prodCheck || pg_num_rows($prodCheck) === 0) {
        throw new Exception("Producto ID {$product_id} no existe en la base de datos");
      }

      // Crear código único para el lote
      $batch_code = 'PO' . $order_id . '_P' . $product_id . '_' . time();
      
      // Calcular fecha de expiración (1 año desde ahora)
      $expiry = date('Y-m-d', strtotime('+1 year'));

      // Insertar lote en inventario
      $batchRes = pg_query_params($conn,
        "INSERT INTO inventory_batches 
         (product_id, batch_code, expiry_date, quantity, unit_cost, created_at)
         VALUES ($1, $2, $3, $4, $5, NOW()) 
         RETURNING id",
        [$product_id, $batch_code, $expiry, $qty, $unit_price]
      );
      
      if ($batchRes === false) {
        throw new Exception("Error al crear lote para producto {$product_id}: " . pg_last_error($conn));
      }

      $batch_id = (int)pg_fetch_result($batchRes, 0, 0);
      $itemsProcessed++;

      // Insertar movimiento de stock
      $moveRes = pg_query_params($conn,
        "INSERT INTO stock_movements 
         (batch_id, product_id, movement_type, quantity, reason, ref_table, ref_id, created_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7, NOW())",
        [$batch_id, $product_id, 'IN', $qty, 'PURCHASE_RECEIVE', 'purchase_orders', $order_id]
      );
      
      if ($moveRes === false) {
        throw new Exception("Error al registrar movimiento para lote {$batch_id}: " . pg_last_error($conn));
      }
    }
    
    if ($itemsProcessed === 0) {
      throw new Exception("No se procesó ningún item del pedido");
    }
  }

  // Confirmar transacción
  if (!pg_query($conn, "COMMIT")) {
    throw new Exception("Error al confirmar transacción: " . pg_last_error($conn));
  }
  
  $message = "Estado actualizado correctamente a {$status}";
  if ($status === 'RECEIVED') {
    $message .= ". Se agregaron los lotes al inventario.";
  }
  
  $_SESSION['success'] = $message;
  header("Location: ../modules/pedidos.php");
  exit();

} catch (Exception $e) {
  // Revertir cambios en caso de error
  pg_query($conn, "ROLLBACK");
  
  // Log del error
  error_log("Error en update_order.php (Order #{$order_id}): " . $e->getMessage());
  
  $_SESSION['error'] = "Error: " . $e->getMessage();
  header("Location: ../modules/pedidos.php");
  exit();
}
?>