<?php
// API de solo lectura.
//   api.php            -> la malla completa (áreas y ramos)
//   api.php?ramo=ID    -> detalle de un ramo con sus contenidos
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache");

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require __DIR__ . "/conexion.php";

    $areas = $pdo->query("SELECT id, nombre, color FROM areas ORDER BY id")->fetchAll();
    foreach ($areas as &$a) { $a["id"] = (int)$a["id"]; }
    unset($a);

    $ramos = $pdo->query("SELECT id, semestre, orden, nombre, area_id FROM ramos ORDER BY semestre, orden")->fetchAll();
    foreach ($ramos as &$r) {
        $r["id"] = (int)$r["id"];
        $r["semestre"] = (int)$r["semestre"];
        $r["orden"] = (int)$r["orden"];
        $r["area_id"] = (int)$r["area_id"];
    }
    unset($r);

    // ---- Lista completa ----
    if (!isset($_GET["ramo"])) {
        responder(["areas" => $areas, "ramos" => $ramos]);
    }

    // ---- Detalle de un ramo ----
    $id = filter_var($_GET["ramo"], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        responder(["error" => "Ramo inválido"], 400);
    }

    $pos = null;
    foreach ($ramos as $i => $r) {
        if ($r["id"] === $id) { $pos = $i; break; }
    }
    if ($pos === null) {
        responder(["error" => "Ramo no encontrado"], 404);
    }
    $ramo = $ramos[$pos];

    $st = $pdo->prepare("SELECT descripcion FROM ramos WHERE id = ?");
    $st->execute([$id]);
    $descripcion = $st->fetchColumn();

    $st = $pdo->prepare("SELECT orden, tema FROM contenidos WHERE ramo_id = ? ORDER BY orden");
    $st->execute([$id]);
    $contenidos = $st->fetchAll();

    $area = null;
    foreach ($areas as $a) {
        if ($a["id"] === $ramo["area_id"]) { $area = $a; break; }
    }

    $mismoSemestre = [];
    foreach ($ramos as $r) {
        if ($r["semestre"] === $ramo["semestre"]) {
            $mismoSemestre[] = ["id" => $r["id"], "nombre" => $r["nombre"], "area_id" => $r["area_id"]];
        }
    }

    $corto = function ($r) { return $r ? ["id" => $r["id"], "nombre" => $r["nombre"]] : null; };

    responder([
        "id"          => $ramo["id"],
        "semestre"    => $ramo["semestre"],
        "nombre"      => $ramo["nombre"],
        "descripcion" => $descripcion,
        "area"        => $area,
        "contenidos"  => $contenidos,
        "semestre_ramos" => $mismoSemestre,
        "anterior"    => $corto($ramos[$pos - 1] ?? null),
        "siguiente"   => $corto($ramos[$pos + 1] ?? null),
        "areas"       => $areas,
    ]);
} catch (Throwable $e) {
    responder(["error" => "No se pudo leer la base de datos"], 500);
}
