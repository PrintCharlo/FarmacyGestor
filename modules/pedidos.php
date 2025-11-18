<?php
include("../php/middleware.php");
requireRole(['Almacenista','Proveedor','Admin']);
include("../php/db.php");

$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pedidos - Gestor de Farmacia</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    /* Pequeños ajustes para que la tabla y formularios se vean ordenados */
    .form-inline { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .form-inline select, .form-inline input { padding:6px; }
    table { width:100%; border-collapse:collapse; margin-top:16px; }
    table th, table td { padding:8px 10px; border:1px solid #e0e0e0; text-align:left; }
    .btn { padding:6px 10px; cursor:pointer; }
    .error { color:#b00020; }
    .success { color:#2e7d32; }
  </style>
</head>
<body>
  <nav class="navbar">
    <span>👤 <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
    <a href="../dashboard.php">Inicio</a>
    <a href="./pedidos.php">Pedidos</a>
    <a href="../php/logout.php" class="logout">Salir</a>
  </nav>

  <main class="content">
    <h1>Pedidos</h1>

    <!-- Mensajes -->
    <?php if (!empty($_SESSION['error'])): ?>
      <p class="error"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
      <p class="success"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (in_array($role, ['Almacenista','Admin'])): ?>
      <!-- Formulario para crear pedido -->
      <section class="chart-section">
        <h2>Crear nuevo pedido</h2>
        <form method="POST" action="../php/add_order.php" class="form-inline" onsubmit="return confirm('Crear pedido ahora?');">
          <select name="supplier_id" required>
            <option value="">Selecciona proveedor</option>
            <?php
            $suppliers = pg_query($conn, "SELECT id, name FROM suppliers ORDER BY name ASC");
            while ($s = pg_fetch_assoc($suppliers)) {
              $id = (int)$s['id'];
              $name = htmlspecialchars($s['name']);
              echo "<option value=\"{$id}\">{$name}</option>";
            }
            ?>
          </select>

          <select name="product_id" required>
            <option value="">Selecciona producto</option>
            <?php
            $products = pg_query($conn, "SELECT id, name FROM products ORDER BY name ASC");
            while ($p = pg_fetch_assoc($products)) {
              $pid = (int)$p['id'];
              $pname = htmlspecialchars($p['name']);
              echo "<option value=\"{$pid}\">{$pname}</option>";
            }
            ?>
          </select>

          <input type="number" name="quantity" min="1" placeholder="Cantidad" required>
          <input type="number" step="0.01" name="unit_price" placeholder="Precio unitario" required>
          <input type="date" name="eta_date" required>
          <button type="submit" class="btn">Crear pedido</button>
        </form>
      </section>
    <?php endif; ?>

    <!-- Tabla de pedidos -->
    <table id="pedidosTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Proveedor</th>
          <th>Estado</th>
          <th>Fecha estimada</th>
          <th>Fecha creación</th>
          <?php if (in_array($role, ['Almacenista','Admin'])) echo "<th>Acción</th>"; ?>
          <?php if (in_array($role, ['Proveedor','Admin'])) echo "<th>Actualizar</th>"; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        $res = pg_query($conn, "
          SELECT po.id, s.name AS proveedor, po.status, 
                 to_char(po.eta_date,'YYYY-MM-DD') AS eta_date,
                 to_char(po.created_at,'YYYY-MM-DD') AS fecha
          FROM purchase_orders po
          JOIN suppliers s ON s.id = po.supplier_id
          ORDER BY po.created_at DESC
        ");
        while ($row = pg_fetch_assoc($res)):
          $po_id = (int)$row['id'];
          $proveedor = htmlspecialchars($row['proveedor']);
          $status = htmlspecialchars($row['status']);
          $eta = htmlspecialchars($row['eta_date']);
          $fecha = htmlspecialchars($row['fecha']);
        ?>
          <tr>
            <td><?php echo $po_id; ?></td>
            <td><?php echo $proveedor; ?></td>
            <td><?php echo $status; ?></td>
            <td><?php echo $eta; ?></td>
            <td><?php echo $fecha; ?></td>

            <?php if (in_array($role, ['Almacenista','Admin'])): ?>
              <td>
                <form method="POST" action="../php/update_order.php" onsubmit="return confirm('Actualizar estado del pedido #<?php echo $po_id; ?>?');" style="display:flex;gap:6px;align-items:center;">
                  <input type="hidden" name="order_id" value="<?php echo $po_id; ?>">
                  <select name="status" required>
                    <?php
                    // Opciones completas para almacenista; marcar la actual
                    $options = ['PENDING','APPROVED','RECEIVED','CANCELLED'];
                    foreach ($options as $opt) {
                      $sel = ($opt === $row['status']) ? 'selected' : '';
                      echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.htmlspecialchars($opt).'</option>';
                    }
                    ?>
                  </select>
                  <button type="submit" class="btn">Actualizar</button>
                </form>
              </td>
            <?php elseif (in_array($role, ['Proveedor','Admin'])): ?>
              <td>
                <form method="POST" action="../php/update_order.php" onsubmit="return confirm('Marcar pedido #<?php echo $po_id; ?> como ENVIADO?');" style="display:flex;gap:6px;align-items:center;">
                  <input type="hidden" name="order_id" value="<?php echo $po_id; ?>">
                  <select name="status" required>
                    <?php
                    // Proveedor solo puede cambiar a ENVIADO o dejar PENDING (mostrar el estado actual)
                    $provOptions = ['PENDING','ENVIADO'];
                    foreach ($provOptions as $opt) {
                      $sel = ($opt === $row['status']) ? 'selected' : '';
                      echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.htmlspecialchars($opt).'</option>';
                    }
                    ?>
                  </select>
                  <button type="submit" class="btn">Cambiar</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </main>

  <script>
    // Mejora UX: confirmar cambios (ya agregado en onsubmit), y se puede extender aquí con AJAX si lo deseas.
  </script>
</body>
</html>
