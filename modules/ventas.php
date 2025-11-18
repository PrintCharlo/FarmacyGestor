<?php
include("../php/middleware.php");
requireRole(['Vendedor','Admin']); 
include("../php/db.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ventas - Gestor de Farmacia</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <nav class="navbar">
    <span>👤 <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
    <a href="../dashboard.php">Inicio</a>
    <a href="./ventas.php">Ventas</a>
    <a href="../php/logout.php" class="logout">Salir</a>
  </nav>

  <main class="content">
    <h1>Ventas</h1>

    <!-- Mensajes -->
    <?php if (isset($_SESSION['error'])) { echo "<p class='error'>{$_SESSION['error']}</p>"; unset($_SESSION['error']); } ?>
    <?php if (isset($_SESSION['success'])) { echo "<p class='success'>{$_SESSION['success']}</p>"; unset($_SESSION['success']); } ?>

    <!-- Formulario para nueva venta -->
    <section class="chart-section">
      <h2>Registrar nueva venta</h2>
      <form method="POST" action="../php/add_sale.php" class="form-inline">
        <select name="product_id" required>
          <option value="">Selecciona producto</option>
          <?php
          $products = pg_query($conn, "SELECT id, name FROM products ORDER BY name ASC");
          while ($p = pg_fetch_assoc($products)) {
            echo "<option value='{$p['id']}'>{$p['name']}</option>";
          }
          ?>
        </select>
        <input type="number" name="quantity" min="1" placeholder="Cantidad" required>
        <button type="submit" class="btn">Registrar venta</button>
      </form>
    </section>

    <!-- Tabla de ventas -->
    <div class="table-actions">
      <input type="text" id="search" class="input" placeholder="Buscar por ID, vendedor o fecha...">
    </div>

    <table id="ventasTable">
      <thead>
        <tr>
          <th>ID Venta</th>
          <th>Vendedor</th>
          <th>Total</th>
          <th>Fecha</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $res = pg_query($conn, "
          SELECT s.id AS sale_id, u.username AS vendedor, s.total,
                 to_char(s.created_at,'YYYY-MM-DD HH24:MI') AS fecha
          FROM sales s
          JOIN users u ON u.id = s.seller_id
          ORDER BY s.created_at DESC
        ");
        while ($row = pg_fetch_assoc($res)) {
          echo "<tr>
                  <td>{$row['sale_id']}</td>
                  <td>{$row['vendedor']}</td>
                  <td>\${$row['total']}</td>
                  <td>{$row['fecha']}</td>
                  <td><a href='detalle_venta.php?id={$row['sale_id']}' class='btn'>Ver</a></td>
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
      document.querySelectorAll("#ventasTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
      });
    });
  </script>
</body>
</html>
