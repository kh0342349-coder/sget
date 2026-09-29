<?php
/**
 * pruebas/_anuncio_test.php
 * -----------------------------------------------------------------------------
 * Endpoint de apoyo para la sonda del navegador: permite crear un anuncio con
 * una imagen subida en base64, porque desde JavaScript no se puede construir un
 * FormData con un archivo real sin pasar por un input[type=file].
 *
 * SOLO DESARROLLO: exige 127.0.0.1 + pruebas/.habilitar + rol administrador.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoto, ['127.0.0.1', '::1'], true) || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

// La sonda reutiliza la sesión que ya abrió _sesion_admin.php
if (empty($_SESSION['id_usu'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'mensaje' => 'sin sesión']));
}

$accion = (string)($_POST['accion'] ?? 'crear');
header('Content-Type: application/json; charset=utf-8');

if ($accion === 'borrar_todo') {
    $n = Database::query("DELETE FROM anuncio WHERE titulo LIKE 'Anuncio%prueba%' OR titulo LIKE 'Anuncio desde el formulario'")->rowCount();
    echo json_encode(['ok' => true, 'borrados' => $n]);
    exit;
}

// --- Se reconstruye el "archivo" a partir del base64 ---
$base64 = trim((string)($_POST['imagen'] ?? ''));
if ($base64 !== '') {
    $datos = base64_decode($base64, true);
    if ($datos === false) {
        http_response_code(422);
        exit(json_encode(['ok' => false, 'mensaje' => 'base64 inválido']));
    }

    $tmp = tempnam(sys_get_temp_dir(), 'sget-ann');
    file_put_contents($tmp, $datos);

    $archivo = [
        'name'     => (string)($_POST['nombre'] ?? 'anuncio.png'),
        'type'     => (string)($_POST['tipo'] ?? 'image/png'),
        'tmp_name' => $tmp,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($datos),
    ];
} else {
    $archivo = null;
}

$r = AnuncioService::guardar($_POST, $archivo);

if (isset($tmp) && is_file($tmp)) @unlink($tmp);

echo json_encode($r, JSON_UNESCAPED_UNICODE), $r['ok'] ? '' : "\n";
http_response_code($r['ok'] ? 200 : 422);
