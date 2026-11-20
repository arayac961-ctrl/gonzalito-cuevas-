<?php
header("Content-Type: application/json; charset=utf-8");

try {
    require __DIR__ . "/conexion.php";
    $personas = $pdo->query("SELECT nombre, cargo, correo FROM personas ORDER BY id")
                    ->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($personas, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "No se pudo leer la base de datos"]);
}
