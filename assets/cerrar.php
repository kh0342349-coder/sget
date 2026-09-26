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
