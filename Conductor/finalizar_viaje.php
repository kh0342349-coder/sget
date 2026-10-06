<?php
/**
 * Conductor/finalizar_viaje.php
 * -----------------------------------------------------------------------------
 * Shim de compatibilidad — la lógica vive en `services/ViajeService::finalizar()`.
 * -----------------------------------------------------------------------------
 * QUÉ HACÍA ANTES
 *   · Una acción que MODIFICA el estado de un viaje se disparaba con un GET:
 *     `finalizar_viaje.php?id=37`. Un simple `<img src>` en cualquier página, o
 *     un enlace precargado, cerraba viajes del conductor.
 *   · No exigía token anti-CSRF (no podía, no había ninguno en el sistema).
 *   · Repetía a mano la liberación del conductor, del vehículo y del conteo de
 *     cupos, con reglas que se habían desincronizado de las del servicio.
 *
 * AHORA
 *   · Solo responde a POST.
 *   · Exige token anti-CSRF.
 *   · Exige rol Conductor.
 *   · Delega en `ViajeService::finalizar()`, que además comprueba que el viaje
 *     sea de ESTE conductor (`Auth::exigirViaje`), avisa a los pasajeros y
 *     libera los recursos con las reglas correctas.
 *
 *   La URL se conserva porque `Conductor/viaje_asignado.php` la enlaza; si llega
 *   un GET (p. ej. desde el historial del navegador), se redirige sin hacer nada.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

$volver = static function (string $url, ?string $mensaje = null, string $tipo = 'error'): void {
    if ($mensaje !== null) {
        Flash::set($tipo, $mensaje);
    }
    sget_redirigir($url);
};

$destino = Config::basePath() . '/Conductor/viajes_conductor.php';

// Un GET ya no ejecuta nada: se avisa y se vuelve al listado.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $volver($destino, 'Para cerrar un viaje usa el botón «Finalizar» del viaje.',
        'aviso');
}

Auth::requerirRol(Config::ROL_CONDUCTOR);

if (!Auth::validarToken((string)($_POST['_token'] ?? ''))) {
    http_response_code(403);
    $volver($destino, 'La sesión del formulario caducó. Vuelve a intentarlo.');
}

$idViaje = (int)($_POST['id'] ?? $_POST['id_viaje'] ?? 0);

// Permiso + propiedad del viaje, en el servidor. Ocultar el botón no protege.
Auth::exigirViaje('finalizar', $idViaje);

$resultado = ViajeService::finalizar($idViaje);

$volver(
    $destino,
    (string)($resultado['mensaje'] ?? 'No se pudo finalizar el viaje.'),
    !empty($resultado['ok']) ? 'exito' : 'error'
);