<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$role = $_SESSION['role'];
include("php/db.php");

/* =========================
   CONSULTA: Ventas últimos 7 días
   ========================= */
$query_sales = "
  SELECT TO_CHAR(created_at, 'Dy') AS dia, 
         SUM(total) AS total
  FROM sales
  WHERE created_at >= NOW() - INTERVAL '7 days'
  GROUP BY dia, DATE(created_at)
  ORDER BY DATE(created_at);
";
$result_sales = pg_query($conn, $query_sales);

$labels_sales = [];
$data_sales = [];
while ($row = pg_fetch_assoc($result_sales)) {
    $labels_sales[] = $row['dia'];
    $data_sales[] = (float)$row['total'];
}

/* =========================
   CONSULTA: Productos con stock bajo
   ========================= */
/*
$lowStockQuery = pg_query($conn, "
  SELECT p.name, p.min_stock, COALESCE(SUM(b.quantity), 0) AS stock_total
  FROM products p
  LEFT JOIN inventory_batches b ON b.product_id = p.id
  GROUP BY p.id, p.name, p.min_stock
  HAVING COALESCE(SUM(b.quantity), 0) <= p.min_stock
  ORDER BY stock_total ASC
");

$labels_low = [];
$stock_actual = [];
$stock_minimo = [];

while ($row = pg_fetch_assoc($lowStockQuery)) {
  $labels_low[]   = $row['name'];
  $stock_actual[] = (int)$row['stock_total'];
  $stock_minimo[] = (int)$row['min_stock'];
}
*/
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Farmacia</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <!-- Barra de navegación -->
   <nav class="navbar">
     <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($role); ?>)</span>
     <a href="dashboard.php">Inicio</a>

     <?php if ($role === "Almacenista" || $role === "Admin") { ?>
      <a href="modules/inventario.php">Inventario</a>
      <a href="modules/pedidos.php">Pedidos</a>
    <?php } ?>

    <?php if ($role === "Vendedor" || $role === "Admin") { ?>
      <a href="modules/ventas.php">Ventas</a>
    <?php } ?>

    <?php if ($role === "Proveedor" || $role === "Admin") { ?>
      <a href="modules/pedidos.php">Ver pedidos</a>
    <?php } ?>
    
    <a href="php/logout.php" class="logout">Salir</a>
  </nav>

  <!-- Contenido principal -->
  <main class="content">
    <h1>Bienvenido al panel</h1>
    <p>Aquí tienes un resumen rápido de la farmacia:</p>

    <!-- Tarjetas resumen -->
    <div class="cards">
      <div class="mini-card">
        <h3>Productos</h3>
        <p>
          <?php
          $res = pg_query($conn, "SELECT COUNT(*) FROM products");
          echo pg_fetch_result($res, 0, 0);
          ?>
        </p>
      </div>
      <div class="mini-card">
        <h3>Ventas</h3>
        <p>
          <?php
          $res = pg_query($conn, "SELECT COUNT(*) FROM sales");
          echo pg_fetch_result($res, 0, 0);
          ?>
        </p>
      </div>
      <div class="mini-card">
        <h3>Pedidos pendientes</h3>
        <p>
          <?php
          $res = pg_query($conn, "SELECT COUNT(*) FROM purchase_orders WHERE status='PENDING'");
          echo pg_fetch_result($res, 0, 0);
          ?>
        </p>
      </div>
    </div>

    <!-- Gráfica de ventas -->
    <section class="chart-section">
      <h2>Ventas de la última semana</h2>
      <canvas id="salesChart"></canvas>
    </section>
    <!-- Gráfica de stock bajo --
    <section class="chart-section">
      <h2>Productos con stock bajo</h2>
      <canvas id="stockChart" style="max-height:400px;"></canvas>
    </section>
  </main>
  
  <!-- Scripts de gráficas -->
  <script>
    // Ventas últimos 7 días
    const labelsSales = <?php echo json_encode($labels_sales); ?>;
    const dataSales = <?php echo json_encode($data_sales); ?>;

    new Chart(document.getElementById("salesChart"), {
      type: "bar",
      data: {
        labels: labelsSales,
        datasets: [{
          label: "Ventas ($)",
          data: dataSales,
          backgroundColor: "#b71c1c"
        }]
      },
      options: {
        responsive: true,
        plugins: { 
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => `$${ctx.formattedValue}` } }
        },
        scales: { y: { beginAtZero: true } }
      }
    });

    // Productos con stock bajo
    /*
    const labelsLow = <?php echo json_encode($labels_low); ?>;
    const stockActual = <?php echo json_encode($stock_actual); ?>;
    const stockMinimo = <?php echo json_encode($stock_minimo); ?>;

    if (labelsLow.length > 0) {
      new Chart(document.getElementById("stockChart"), {
        type: "bar",
        data: {
          labels: labelsLow,
          datasets: [
            {
              label: "Stock actual",
              data: stockActual,
              backgroundColor: "#388e3c"
            },
            {
              label: "Stock mínimo",
              data: stockMinimo,
              backgroundColor: "#fbc02d"
            }
          ]
        },
        options: {
          responsive: true,
          plugins: { 
            tooltip: { callbacks: { label: ctx => ctx.dataset.label + ": " + ctx.formattedValue } }
          },
          scales: { y: { beginAtZero: true } }
        }
      });
    } else {
      document.getElementById("stockChart").outerHTML =
        "<p><strong>Todos los productos están por encima del stock mínimo ✅</strong></p>";
    }
    */
  </script>
</body>
</html>
