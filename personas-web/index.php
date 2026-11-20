<?php
require "conexion.php";
$personas = $pdo->query("SELECT nombre, cargo, correo FROM personas ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nuestro equipo</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <h1>Nuestro equipo</h1>
    <p>Datos cargados desde MySQL (phpMyAdmin)</p>
  </header>

  <main class="tarjetas">
    <?php foreach ($personas as $p): ?>
      <article class="tarjeta">
        <div class="avatar"><?= htmlspecialchars(mb_substr($p['nombre'], 0, 1)) ?></div>
        <h2><?= htmlspecialchars($p['nombre']) ?></h2>
        <p class="cargo"><?= htmlspecialchars($p['cargo']) ?></p>
        <a href="mailto:<?= htmlspecialchars($p['correo']) ?>"><?= htmlspecialchars($p['correo']) ?></a>
      </article>
    <?php endforeach; ?>
  </main>

  <footer>© 2026 - Proyecto simple PHP + MySQL</footer>
</body>
</html>
