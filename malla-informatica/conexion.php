<?php
// Valores por defecto (XAMPP / Laragon / WAMP)
$host = "localhost";
$bd   = "malla_db";
$user = "root";
$pass = "";

// En el servidor se crea config.local.php (no se sube a GitHub) para
// cambiar usuario y clave sin tocar este archivo.
if (file_exists(__DIR__ . "/config.local.php")) {
    require __DIR__ . "/config.local.php";
}

$pdo = new PDO("mysql:host=$host;dbname=$bd;charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
