<?php
// API principal: malla curricular, foro de proyectos por asignatura y descarga segura
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

function responder($datos, $codigo = 200) {
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-cache");
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function verificarCsrf() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        responder(["error" => "Token de seguridad inválido. Recarga la página."], 403);
    }
}

function requerirAuth() {
    if (!isset($_SESSION['usuario'])) {
        responder(["error" => "Debes iniciar sesión para realizar esta acción."], 401);
    }
    return $_SESSION['usuario'];
}

try {
    require __DIR__ . "/conexion.php";

    $metodo = $_SERVER['REQUEST_METHOD'];
    $accion = $_GET['accion'] ?? '';

    // ---- Descarga controlada de archivos adjuntos ----
    if ($metodo === 'GET' && $accion === 'descargar') {
        $pid = filter_var($_GET['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$pid || $pid < 1) {
            responder(["error" => "Proyecto inválido"], 400);
        }
        $st = $pdo->prepare("SELECT archivo_nombre, archivo_ruta FROM proyectos WHERE id = ?");
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p || empty($p['archivo_ruta'])) {
            responder(["error" => "Archivo no encontrado"], 404);
        }
        $rutaReal = __DIR__ . "/uploads/" . basename($p['archivo_ruta']);
        if (!is_file($rutaReal)) {
            responder(["error" => "El archivo físico no existe en el servidor"], 404);
        }
        $nombreLimpio = preg_replace('/[^a-zA-Z0-9._-]/', '_', $p['archivo_nombre']);
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename="" . $nombreLimpio . """);
        header("Content-Length: " . filesize($rutaReal));
        readfile($rutaReal);
        exit;
    }

    // ---- Subir nuevo proyecto a un ramo ----
    if ($metodo === 'POST' && $accion === 'subir_proyecto') {
        $usuario = requerirAuth();
        verificarCsrf();

        $ramoId = filter_var($_POST['ramo_id'] ?? null, FILTER_VALIDATE_INT);
        $titulo = trim($_POST['titulo'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        $repo   = trim($_POST['repo_url'] ?? '');

        if (!$ramoId || $ramoId < 1) {
            responder(["error" => "Asignatura inválida."], 400);
        }
        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 160) {
            responder(["error" => "El título debe tener entre 3 y 160 caracteres."], 400);
        }
        if (mb_strlen($desc) < 10) {
            responder(["error" => "La descripción debe tener al menos 10 caracteres."], 400);
        }
        if ($repo !== '' && !filter_var($repo, FILTER_VALIDATE_URL)) {
            responder(["error" => "El enlace de repositorio/demo no es una URL válida."], 400);
        }

        // Verificar que el ramo exista
        $st = $pdo->prepare("SELECT id FROM ramos WHERE id = ?");
        $st->execute([$ramoId]);
        if (!$st->fetch()) {
            responder(["error" => "La asignatura seleccionada no existe."], 404);
        }

        $archivoNombre = null;
        $archivoRuta   = null;
        $archivoTamano = null;

        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['archivo'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                responder(["error" => "Error al subir el archivo (código " . $file['error'] . ")."], 400);
            }
            $maxBytes = 25 * 1024 * 1024; // 25 MB
            if ($file['size'] > $maxBytes) {
                responder(["error" => "El archivo supera el límite máximo de 25 MB."], 400);
            }

            $extPermitidas = [
                'zip', 'rar', '7z', 'tar', 'gz',
                'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'md',
                'py', 'js', 'ts', 'php', 'html', 'css', 'java', 'c', 'cpp', 'sql', 'json',
                'png', 'jpg', 'jpeg', 'webp'
            ];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $extPermitidas, true)) {
                responder(["error" => "Extensión .$ext no permitida. Sube ZIP, PDF, código fuente, documentos o imágenes."], 400);
            }

            $dirUploads = __DIR__ . "/uploads";
            if (!is_dir($dirUploads)) {
                mkdir($dirUploads, 0755, true);
            }

            $nombreGuardado = date('Ymd_His') . "_" . bin2hex(random_bytes(8)) . "." . $ext;
            $destino = $dirUploads . "/" . $nombreGuardado;

            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                responder(["error" => "No se pudo guardar el archivo en el servidor. Revisa permisos en uploads/."], 500);
            }

            $archivoNombre = basename($file['name']);
            $archivoRuta   = $nombreGuardado;
            $archivoTamano = (int)$file['size'];
        }

        if ($repo === '' && $archivoRuta === null) {
            responder(["error" => "Adjunta al menos un archivo o un enlace de repositorio (GitHub, Drive, etc.)."], 400);
        }

        $st = $pdo->prepare("
            INSERT INTO proyectos (ramo_id, usuario_id, titulo, descripcion, repo_url, archivo_nombre, archivo_ruta, archivo_tamano)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $ramoId,
            $usuario['id'],
            $titulo,
            $desc,
            $repo !== '' ? $repo : null,
            $archivoNombre,
            $archivoRuta,
            $archivoTamano
        ]);

        responder(["ok" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }

    // ---- Eliminar proyecto (autor o admin) ----
    if ($metodo === 'POST' && $accion === 'eliminar_proyecto') {
        $usuario = requerirAuth();
        verificarCsrf();

        $raw = json_decode(file_get_contents("php://input"), true) ?: $_POST;
        $pid = filter_var($raw['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$pid || $pid < 1) {
            responder(["error" => "Proyecto inválido."], 400);
        }

        $st = $pdo->prepare("SELECT usuario_id, archivo_ruta FROM proyectos WHERE id = ?");
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p) {
            responder(["error" => "Proyecto no encontrado."], 404);
        }

        if ((int)$p['usuario_id'] !== (int)$usuario['id'] && $usuario['rol'] !== 'admin') {
            responder(["error" => "No tienes permiso para eliminar este proyecto."], 403);
        }

        if (!empty($p['archivo_ruta'])) {
            $ruta = __DIR__ . "/uploads/" . basename($p['archivo_ruta']);
            if (is_file($ruta)) @unlink($ruta);
        }

        $st = $pdo->prepare("DELETE FROM proyectos WHERE id = ?");
        $st->execute([$pid]);
        responder(["ok" => true]);
    }

    // ---- Comentar en un proyecto ----
    if ($metodo === 'POST' && $accion === 'comentar') {
        $usuario = requerirAuth();
        verificarCsrf();

        $raw = json_decode(file_get_contents("php://input"), true) ?: $_POST;
        $pid  = filter_var($raw['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $text = trim($raw['contenido'] ?? '');

        if (!$pid || $pid < 1) {
            responder(["error" => "Proyecto inválido."], 400);
        }
        if (mb_strlen($text) < 2 || mb_strlen($text) > 1000) {
            responder(["error" => "El comentario debe tener entre 2 y 1000 caracteres."], 400);
        }

        $st = $pdo->prepare("SELECT id FROM proyectos WHERE id = ?");
        $st->execute([$pid]);
        if (!$st->fetch()) {
            responder(["error" => "El proyecto no existe."], 404);
        }

        $st = $pdo->prepare("INSERT INTO comentarios (proyecto_id, usuario_id, contenido) VALUES (?, ?, ?)");
        $st->execute([$pid, $usuario['id'], $text]);
        responder(["ok" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }

    // ---- Eliminar comentario (autor o admin) ----
    if ($metodo === 'POST' && $accion === 'eliminar_comentario') {
        $usuario = requerirAuth();
        verificarCsrf();

        $raw = json_decode(file_get_contents("php://input"), true) ?: $_POST;
        $cid = filter_var($raw['comentario_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$cid || $cid < 1) {
            responder(["error" => "Comentario inválido."], 400);
        }

        $st = $pdo->prepare("SELECT usuario_id FROM comentarios WHERE id = ?");
        $st->execute([$cid]);
        $c = $st->fetch();
        if (!$c) {
            responder(["error" => "Comentario no encontrado."], 404);
        }
        if ((int)$c['usuario_id'] !== (int)$usuario['id'] && $usuario['rol'] !== 'admin') {
            responder(["error" => "No tienes permiso para eliminar este comentario."], 403);
        }

        $st = $pdo->prepare("DELETE FROM comentarios WHERE id = ?");
        $st->execute([$cid]);
        responder(["ok" => true]);
    }

    // ---- Lectura de malla y detalle de ramo ----
    $areas = $pdo->query("SELECT id, nombre, color FROM areas ORDER BY id")->fetchAll();
    foreach ($areas as &$a) { $a["id"] = (int)$a["id"]; }
    unset($a);

    // Conteo de proyectos por ramo
    $conteos = [];
    $stCont = $pdo->query("SELECT ramo_id, COUNT(*) AS total FROM proyectos GROUP BY ramo_id");
    foreach ($stCont->fetchAll() as $row) {
        $conteos[(int)$row['ramo_id']] = (int)$row['total'];
    }

    $ramos = $pdo->query("SELECT id, semestre, orden, nombre, area_id FROM ramos ORDER BY semestre, orden")->fetchAll();
    foreach ($ramos as &$r) {
        $r["id"] = (int)$r["id"];
        $r["semestre"] = (int)$r["semestre"];
        $r["orden"] = (int)$r["orden"];
        $r["area_id"] = (int)$r["area_id"];
        $r["proyectos_count"] = $conteos[$r["id"]] ?? 0;
    }
    unset($r);

    // ---- Lista completa ----
    if (!isset($_GET["ramo"])) {
        $totalProyectos = array_sum($conteos);
        responder([
            "areas"           => $areas,
            "ramos"           => $ramos,
            "total_proyectos" => $totalProyectos
        ]);
    }

    // ---- Detalle de un ramo + proyectos + comentarios ----
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

    // Proyectos del ramo
    $st = $pdo->prepare("
        SELECT p.id, p.usuario_id, p.titulo, p.descripcion, p.repo_url,
               p.archivo_nombre, p.archivo_tamano, p.creado_en,
               u.nombre AS autor_nombre
        FROM proyectos p
        JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.ramo_id = ?
        ORDER BY p.creado_en DESC, p.id DESC
    ");
    $st->execute([$id]);
    $proyectos = $st->fetchAll();

    if ($proyectos) {
        $ids = array_column($proyectos, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stCom = $pdo->prepare("
            SELECT c.id, c.proyecto_id, c.usuario_id, c.contenido, c.creado_en,
                   u.nombre AS autor_nombre
            FROM comentarios c
            JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.proyecto_id IN ($placeholders)
            ORDER BY c.creado_en ASC, c.id ASC
        ");
        $stCom->execute($ids);
        $comentariosPorProy = [];
        foreach ($stCom->fetchAll() as $c) {
            $c['id'] = (int)$c['id'];
            $c['proyecto_id'] = (int)$c['proyecto_id'];
            $c['usuario_id'] = (int)$c['usuario_id'];
            $comentariosPorProy[$c['proyecto_id']][] = $c;
        }
        foreach ($proyectos as &$p) {
            $p['id'] = (int)$p['id'];
            $p['usuario_id'] = (int)$p['usuario_id'];
            $p['archivo_tamano'] = $p['archivo_tamano'] !== null ? (int)$p['archivo_tamano'] : null;
            $p['comentarios'] = $comentariosPorProy[$p['id']] ?? [];
        }
        unset($p);
    }

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
        "id"             => $ramo["id"],
        "semestre"       => $ramo["semestre"],
        "nombre"         => $ramo["nombre"],
        "descripcion"    => $descripcion,
        "area"           => $area,
        "contenidos"     => $contenidos,
        "proyectos"      => $proyectos,
        "semestre_ramos" => $mismoSemestre,
        "anterior"       => $corto($ramos[$pos - 1] ?? null),
        "siguiente"      => $corto($ramos[$pos + 1] ?? null),
        "areas"          => $areas,
    ]);
} catch (Throwable $e) {
    responder(["error" => "Error en el servidor: " . $e->getMessage()], 500);
}
