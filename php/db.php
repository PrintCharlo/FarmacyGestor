<?php
// php/db.php

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'farmacia_db');
define('DB_USER', 'postgres');
define('DB_PASS', 'postgres');

$conn_str = sprintf(
  "host=%s port=%s dbname=%s user=%s password=%s",
  DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
);

$conn = @pg_connect($conn_str);

if (!$conn) {
  die("Error de conexión a la base de datos");
}
