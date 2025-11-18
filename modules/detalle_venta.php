<?php
include("../php/middleware.php");
requireRole(['Vendedor','Admin']); 
include("../php/db.php");

$sale_id = (int)($_GET['id'] ?? 0);

$res = pg_query_params($conn, "
  SELECT s.id AS sale_id, u.username AS vendedor, s.total, 
         to_char(s.created_at,'YYYY-MM-DD HH24:MI') AS fecha
  FROM sales s
  JOIN users u ON u.id = s.seller_id
  WHERE s.id = $1
", [$sale_id]);

$sale = pg_fetch_assoc($res);

$items = pg_query_params($conn, "
  SELECT p.name, si.quantity, si.unit_price, (si.quantity * si.unit_price) AS subtotal
  FROM sale_items si
  JOIN products p ON p.id = si.product_id
  WHERE si.sale_id = $1
", [$sale_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Detalle de venta #<?php echo $sale_id; ?></title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <nav class="navbar">
    <a href="ventas.php" class="btn">⬅ Volver a ventas</a>
  </nav>

  <main class="content">
    <h1>Detalle de venta #<?php echo $sale['sale_id']; ?></h1>
    <p><strong>Vendedor:</strong> <?php echo $sale['vendedor']; ?></p>
    <p><strong>Fecha:</strong> <?php echo $sale['fecha']; ?></p>
    <p><strong>Total:</strong> $<?php echo $sale['total']; ?></p>

    <h2>Productos vendidos</h2>
    <table>
      <thead>
        <tr>
          <th>Producto</th>
          <th>Cantidad</th>
          <th>Precio unitario</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = pg_fetch_assoc($items)) {
          echo "<tr>
                  <td>{$row['name']}</td>
                  <td>{$row['quantity']}</td>
                  <td>\${$row['unit_price']}</td>
                  <td>\${$row['subtotal']}</td>
                </tr>";
        } ?>
      </tbody>
    </table>
  </main>
</body>
</html>
