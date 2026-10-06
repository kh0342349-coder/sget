<?php
/**
 * procesos/guardar_calificacion.php
 * -----------------------------------------------------------------------------
 * Shim de compatibilidad — toda la lógica vive en `services/CalificacionService`.
 * -----------------------------------------------------------------------------
 * QUÉ HACÍA ANTES
 *   INSERT con los valores del POST concatenados directamente en la cadena:
 *
 *       INSERT INTO calificacion (...) VALUES ('$id_via_cal', ..., '$pun_cal', '$com_cal')
 *
 *   Eso era INYECCIÓN SQL directa (`id_via`, `id_cond` y `puntos` venían del
 *   formulario sin ninguna comprobación), no había token anti-CSRF y cualquier
 *   rol autenticado —no solo el pasajero— podía calificar cualquier viaje,
 *   incluido uno ajeno, con elony de sus notas.
 *
 * AHORA
 *   Delega en `CalificacionService::registrar()`, que:
 *     · exige rol Pasajero y que el viaje sea real y suyo;
 *     · valida la puntuación (1–5) y el comentario;
 *     · solo admite un voto por pasajero y viaje;
 *     · queda protegido por CSRF.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

$volver = static function (?string $mensaje, string $tipo = 'error'): void {
    if ($mensaje !== null) {
        Flash::set($tipo, $mensaje);
    }
    sget_redirigir(Config::basePath() . '/Pasajero/pasajero.php');
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $volver(null);
}

Auth::requerirRol(Config::ROL_PASAJERO);

if (!Auth::validarToken((string)($_POST['_token'] ?? ''))) {
    http_response_code(403);
    $volver('La sesión del formulario caducó. Vuelve a intentarlo.');
}

$resultado = CalificacionService::registrar(
    Auth::id(),
    (int)($_POST['id_via'] ?? $_POST['id_via_cal'] ?? 0),
    (int)($_POST['puntos'] ?? $_POST['pun_cal'] ?? 0),
    (string)($_POST['comentario'] ?? $_POST['com_cal'] ?? '')
);

$volver(
    (string)($resultado['mensaje'] ?? 'No se pudo registrar la calificación.'),
    $resultado['ok'] ? 'exito' : 'error'
);