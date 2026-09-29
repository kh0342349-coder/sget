<?php
/**
 * pruebas/_noti_test.php
 * -----------------------------------------------------------------------------
 * SEEDS DE DESARROLLO para probar el buzón de notificaciones por rol.
 *
 * Crea notificaciones reales para el usuario en sesión (pasajero o conductor),
 * de cada tipo que el sistema debe saber generar, y devuelve un resumen.
 *
 *     /pruebas/_noti_test.php?rol=3        -> siembra el buzón del pasajero
 *     /pruebas/_noti_test.php?rol=2&limpiar=1
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoto, ['127.0.0.1', '::1'], true) || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$rol = (int)($_GET['rol'] ?? 3);
if (!in_array($rol, [Config::ROL_CONDUCTOR, Config::ROL_PASAJERO], true)) {
    $rol = Config::ROL_PASAJERO;
}

$usuario = Database::one(
    "SELECT id_usu, nom_usu, num_doc_usu FROM usuario
      WHERE id_rol_usu = ? AND estado = 1 ORDER BY id_usu ASC LIMIT 1",
    [$rol]
);
if (!$usuario) {
    http_response_code(409);
    exit('No hay ningún usuario activo con el rol ' . $rol);
}

$_SESSION = [
    'id_usu'         => (int)$usuario['id_usu'],
    'documento'      => (string)$usuario['num_doc_usu'],
    'rol'            => $rol,
    'id_rol_usu'     => $rol,
    'nombre_usuario' => (string)$usuario['nom_usu'],
    'ultimo_acceso'  => time(),
    'sget_idioma'    => 'es',
    'sget_csrf'      => $_SESSION['sget_csrf'] ?? bin2hex(random_bytes(32)),
];

if (isset($_GET['limpiar'])) {
    $borradas = Database::query("DELETE FROM notificacion WHERE id_usu = ?", [(int)$usuario['id_usu']])->rowCount();
    header('Content-Type: text/plain; charset=utf-8');
    echo "buzón vaciado ({$borradas} borradas) para #{$usuario['id_usu']}";
    exit;
}

$id = (int)$usuario['id_usu'];
$esPasajero = $rol === Config::ROL_PASAJERO;

// --- Se limpia el buzón para que la prueba sea reproducible ---
Database::query("DELETE FROM notificacion WHERE id_usu = ?", [$id]);

if ($esPasajero) {
    NotificacionService::enviar($id, NotificacionService::TIPO_RESERVA, 'Reserva confirmada',
        "Hola {$usuario['nom_usu']}, tu reserva quedó CONFIRMADA para el viaje #12 (Silvania-Fusagasuga).\nPuedes consultarlo desde tu historial.");
    NotificacionService::enviar($id, NotificacionService::TIPO_CANCELACION, 'Viaje cancelado: Silvania-Fusagasuga',
        "El viaje #12 ha sido CANCELADO.\n\nMotivo: Falla mecánica\nAnotación del operador: El vehículo presentó una falla y se reprogramará.\nNo se realizó ningún cobro.");
} else {
    NotificacionService::enviar($id, NotificacionService::TIPO_ASIGNACION, 'Nuevo viaje asignado',
        "Se te asignó el viaje #14 (Silvania-Fusagasuga) con salida a las 06:00.\nConfirma que puedes presentarte.");
    NotificacionService::enviar($id, NotificacionService::TIPO_CANCELACION, 'Viaje cancelado: Fusagasuga-Silvania',
        "El viaje #13 fue cancelado por administración.\n\nMotivo: Ruta bloqueada\nLos pasajeros fueron notificados.");
}

header('Content-Type: text/plain; charset=utf-8');
echo 'buzón sembrado para ' . $usuario['nom_usu'] . ' (#' . $id . ', rol ' . $rol . '): '
    . NotificacionService::noLeidas($id) . ' sin leer';
