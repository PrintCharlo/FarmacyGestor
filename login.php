<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Gestor de Farmacia</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="centered">
  <div class="card login-card">
    <h2>Iniciar sesión</h2>
    <p class="subtitle">Accede con tu usuario y contraseña</p>
    <form method="POST" action="php/auth.php">
      <label for="username">Usuario</label>
      <input type="text" id="username" name="username" placeholder="Ingresa tu usuario" required>

      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña" required>

      <button type="submit" class="btn">Entrar</button>
    </form>

    <?php 
    if (isset($_SESSION['error'])) { 
      echo "<p class='error'>".$_SESSION['error']."</p>"; 
      unset($_SESSION['error']); 
    } 
    ?>
    <p class="back-link"><a href="index.php">⬅ Volver a inicio</a></p>
  </div>
</body>
</html>
