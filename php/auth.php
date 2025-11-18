<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['error'] = "Usuario y contraseña son requeridos";
    header("Location: ../login.php");
    exit();
}

$query = "
  SELECT u.id, u.username, u.password_hash, r.id AS role_id, r.name AS role_name
  FROM users u
  JOIN roles r ON u.role_id = r.id
  WHERE u.username = $1
  LIMIT 1
";
$res = pg_query_params($conn, $query, [$username]);

if (!$res || pg_num_rows($res) === 0) {
    $_SESSION['error'] = "Usuario o contraseña incorrectos";
    header("Location: ../login.php");
    exit();
}

$user = pg_fetch_assoc($res);
$stored = $user['password_hash'] ?? '';

// Verificación de contraseña:
// 1) Si está hasheada (formato $2y$... u otros), usar password_verify
// 2) Si no, permitir comparación de texto plano (solo temporal, migrar a hashes)
$valid = false;
if (!empty($stored)) {
    // intentar password_verify
    if (password_verify($password, $stored)) {
        $valid = true;
    } else {
        // fallback: comparación directa con texto plano (compatibilidad)
        if ($password === $stored) {
            $valid = true;
            // recomendado: rehash y actualizar DB para migrar al hash
            if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                pg_query_params($conn, "UPDATE users SET password_hash = $1 WHERE id = $2", [$newHash, (int)$user['id']]);
            }
        }
    }
}

if ($valid) {
    // Poblar sesión
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role_name']; // p.ej. "Admin"

    header("Location: ../dashboard.php");
    exit();
} else {
    $_SESSION['error'] = "Usuario o contraseña incorrectos";
    header("Location: ../login.php");
    exit();
}
