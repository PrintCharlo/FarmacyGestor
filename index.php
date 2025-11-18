<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestor de Farmacia</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="welcome">

  <!-- TOPBAR -->
  <div class="topbar">
    <h1 class="logo">PharmaKit</h1>
  </div>

  <!-- HERO -->
  <header class="hero">
    <div class="overlay">
      <div class="hero-content slide-up">
        <div class="hero-text">
          <h2>Optimiza tu farmacia</h2>
          <p>Gestiona inventarios, ventas y pedidos con una plataforma simple y segura.</p>
          <a href="login.php" class="btn">Iniciar sesión</a>
        </div>
        <div class="hero-image">
          <img src="img/software.png" alt="software" />
        </div>
      </div>
    </div>
  </header>

  <!-- FEATURES -->
  <section class="features">
    <h2>¿Qué puedes hacer aquí?</h2>
    <div class="cards">
      <div class="card bg-inventario">
        <div class="card-overlay">
          <h3>Inventario</h3>
          <p>Consulta y gestiona el stock de medicamentos, con alertas de caducidad y bajo inventario.</p>
        </div>
      </div>
      <div class="card bg-ventas">
        <div class="card-overlay">
          <h3>Ventas</h3>
          <p>Registra ventas de forma rápida y precisa, asegurando control de lotes y caducidades.</p>
        </div>
      </div>
      <div class="card bg-pedidos">
        <div class="card-overlay">
          <h3>Pedidos</h3>
          <p>Solicita productos a proveedores y da seguimiento a pedidos pendientes o recibidos.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTACTO -->
  <section class="contact slide-up">
    <h2>Contáctanos</h2>
    <div class="contact-info">
      <p><strong>Farmacia Central</strong></p>
      <p>📍 Dirección: Av. Principal #123, San Pedro Tlaquepaque, Jalisco</p>
      <p>📧 Email: contacto@farmacia.com</p>
      <p>☎ Teléfono: (33) 1234-5678</p>
      <p>🕒 Horario: Lunes a Sábado - 9:00 AM a 8:00 PM</p>
    </div>
  </section>

  <footer>
    <p>© <?php echo date("Y"); ?> Gestor de Farmacia - Proyecto Bases de Datos Equipo 4</p>
  </footer>
</body>
</html>
