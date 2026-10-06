<?php
/**
 * assets/cerrar.php
 * -----------------------------------------------------------------------------
 * CIERRE DE SESIÓN
 * -----------------------------------------------------------------------------
 * Registra el evento en la auditoría antes de destruir la sesión (si el
 * usuario venía de un bloqueo por inactividad, el motivo queda explícito).
 *
 *   assets/cerrar.php                     → cierre manual
 *   assets/cerrar.php?motivo=inactividad  → cierre forzado por inactividad
 *   assets/cerrar.php?volver=Admin/rutas.php
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

$motivo = (string)($_GET['motivo'] ?? $_POST['motivo'] ?? 'manual');
$motivos = [
    'manual'        => 'Cierre de sesión voluntary.',
    'inactividad'   => 'Cierre de sesión por inactividad: la contraseña no se confirmó a tiempo.',
    'expirada'      => 'La sesión expiró por seguridad.',
];

$descripcion = $motivos[$motivo] ?? $motivos['manual'];

/* -----------------------------------------------------------------------------
 * PROTECCIÓN ANTICSRF
 * -----------------------------------------------------------------------------
 * Antes este endpoint aceptaba un GET cualquiera. Eso significa que bastaba una
 * imagen en un correo o una web cualquiera para cerrarle la sesión al usuario.
 * Es un ataque real (y en el caso de SGET, molesto de verdad: pierde el panel
 * de trabajo).
 *
 * Reglas:
 *   · POST  -> exige token válido. Es lo que usan los formularios.
 *   · GET   -> SOLO se admite el cierre por inactividad, que es el que dispara
 *     la propia aplicación. Un cierre manual por GET se rechaza.
 * -------------------------------------------------------------------------- */
$esPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($esPost) {
    if (!Auth::validarToken((string)($_POST['_token'] ?? ''))) {
        http_response_code(403);
        exit('La sesión del formulario caducó. Recarga la página e inténtalo de nuevo.');
    }
} elseif ($motivo !== 'inactividad') {
    http_response_code(405);
    header('Allow: POST');
    exit('El cierre de sesión debe hacerse con un formulario (POST).');
}

if (!Auth::estaLogueado()) {
    header('Location: ' . Config::basePath() . '/index.php');
    exit;
}

if (Auth::estaLogueado()) {
    Logger::registrar(
        Database::pdo(),
        'LOGOUT',
        sprintf('%s Usuario: %s.', $descripcion, Auth::nombre())
    );
}

// Destino permitido: solo rutas internas relativas, para no crear un
// redirector abierto.
$destino = (string)($_GET['volver'] ?? $_POST['volver'] ?? '');
if ($destino === '' || !preg_match('#^[a-zA-Z0-9_\-]+\.php$#', basename($destino)) || str_contains($destino, '..')) {
    $destino = 'index.php';
}

Auth::cerrar();

header('Location: ' . Config::basePath() . '/' . $destino);
exit;
