<?php
include("../php/middleware.php");
requireRole(['Almacenista','Admin']); 
include("../php/db.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Inventario - Gestor de Farmacia</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <nav class="navbar">
    <span>👤 <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
    <a href="../dashboard.php">Inicio</a>
    <a href="./inventario.php">Inventario</a>
    <a href="../php/logout.php" class="logout">Salir</a>
  </nav>

  <main class="content">
    <h1>Inventario</h1>

    <!-- Mensajes -->
    <?php if (isset($_SESSION['error'])) { echo "<p class='error'>{$_SESSION['error']}</p>"; unset($_SESSION['error']); } ?>
    <?php if (isset($_SESSION['success'])) { echo "<p class='success'>{$_SESSION['success']}</p>"; unset($_SESSION['success']); } ?>

    <!-- Formulario para agregar lote -->
    <section class="chart-section">
      <h2>Agregar nuevo lote</h2>
      <form method="POST" action="../php/add_batch.php" class="form-inline">
        <select name="product_id" required>
          <option value="">Selecciona producto</option>
          <?php
          $products = pg_query($conn, "SELECT id, name FROM products ORDER BY name ASC");
          while ($p = pg_fetch_assoc($products)) {
            echo "<option value='{$p['id']}'>{$p['name']}</option>";
          }
          ?>
        </select>
        <input type="text" name="batch_code" placeholder="Código de lote" required>
        <input type="date" name="expiry_date" required>
        <input type="number" name="quantity" min="1" placeholder="Cantidad" required>
        <input type="number" step="0.01" name="unit_cost" placeholder="Costo unitario" required>
        <button type="submit" class="btn">Agregar lote</button>
      </form>
    </section>

    <!-- Buscador -->
    <div class="table-actions">
      <input type="text" id="search" class="input" placeholder="Buscar producto o lote...">
    </div>

    <!-- Tabla de inventario -->
    <table id="inventarioTable">
      <thead>
        <tr>
          <th>Producto</th>
          <th>Lote</th>
          <th>Cantidad</th>
          <th>Caducidad</th>
          <th>Costo unitario</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $res = pg_query($conn, "
          SELECT p.name, b.batch_code, b.quantity, b.expiry_date, b.unit_cost,
                 (SELECT SUM(quantity) FROM inventory_batches WHERE product_id=p.id) AS stock_total,
                 p.min_stock
          FROM inventory_batches b
          JOIN products p ON p.id = b.product_id
          ORDER BY p.name, b.expiry_date
        ");
        while ($row = pg_fetch_assoc($res)) {
          $low = ($row['stock_total'] <= $row['min_stock']) ? "class='low'" : "";
          echo "<tr $low>
                  <td>{$row['name']}</td>
                  <td>{$row['batch_code']}</td>
                  <td>{$row['quantity']}</td>
                  <td>{$row['expiry_date']}</td>
                  <td>\${$row['unit_cost']}</td>
                </tr>";
        }
        ?>
      </tbody>
    </table>
  </main>

  <script>
    // Búsqueda en tabla
    document.getElementById("search").addEventListener("keyup", function() {
      const filter = this.value.toLowerCase();
      document.querySelectorAll("#inventarioTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
      });
    });
  </script>
</body>
</html>
