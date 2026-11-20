<?php
// API de autenticación y sesiones
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function usuarioActual() {
    if (!isset($_SESSION['usuario'])) return null;
    return $_SESSION['usuario'];
}

function leerEntrada() {
    $raw = file_get_contents("php://input");
    $json = json_decode($raw, true);
    if (is_array($json)) return $json;
    return $_POST;
}

$accion = $_GET['accion'] ?? '';

try {
    require __DIR__ . "/conexion.php";

    if ($accion === 'estado') {
        responder([
            "autenticado" => usuarioActual() !== null,
            "usuario"     => usuarioActual(),
            "csrf_token"  => $_SESSION['csrf_token']
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(["error" => "Método no permitido"], 405);
    }

    $datos = leerEntrada();

    if ($accion === 'registro') {
        $nombre = trim($datos['nombre'] ?? '');
        $email  = mb_strtolower(trim($datos['email'] ?? ''));
        $pass   = $datos['password'] ?? '';

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
            responder(["error" => "El nombre debe tener entre 2 y 80 caracteres."], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
            responder(["error" => "Ingresa un correo electrónico válido."], 400);
        }
        if (strlen($pass) < 6) {
            responder(["error" => "La contraseña debe tener al menos 6 caracteres."], 400);
        }

        $st = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetch()) {
            responder(["error" => "Ese correo ya está registrado."], 409);
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $st = $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash) VALUES (?, ?, ?)");
        $st->execute([$nombre, $email, $hash]);
        $id = (int)$pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            "id"     => $id,
            "nombre" => $nombre,
            "email"  => $email,
            "rol"    => "estudiante"
        ];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        responder([
            "ok"         => true,
            "usuario"    => $_SESSION['usuario'],
            "csrf_token" => $_SESSION['csrf_token']
        ], 201);
    }

    if ($accion === 'login') {
        $email = mb_strtolower(trim($datos['email'] ?? ''));
        $pass  = $datos['password'] ?? '';

        if (!$email || !$pass) {
            responder(["error" => "Completa correo y contraseña."], 400);
        }

        $st = $pdo->prepare("SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email = ?");
        $st->execute([$email]);
        $u = $st->fetch();

        if (!$u || !password_verify($pass, $u['password_hash'])) {
            responder(["error" => "Correo o contraseña incorrectos."], 401);
        }

        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            "id"     => (int)$u['id'],
            "nombre" => $u['nombre'],
            "email"  => $u['email'],
            "rol"    => $u['rol']
        ];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        responder([
            "ok"         => true,
            "usuario"    => $_SESSION['usuario'],
            "csrf_token" => $_SESSION['csrf_token']
        ]);
    }

    if ($accion === 'logout') {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        responder(["ok" => true]);
    }

    responder(["error" => "Acción no válida"], 400);
} catch (Throwable $e) {
    responder(["error" => "Error en el servidor de autenticación"], 500);
}
