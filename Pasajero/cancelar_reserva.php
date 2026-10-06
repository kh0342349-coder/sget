<?php
/**
 * Pasajero/cancelar_reserva.php
 * -----------------------------------------------------------------------------
 * Cancelación de una reserva por parte del pasajero.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirSesion();
Auth::requerirRol(Config::ROL_PASAJERO);

$idReserva = (int)($_GET['id_reserva'] ?? 0);
$idViaje   = (int)($_GET['id_via'] ?? 0);

if ($idReserva <= 0) {
    Flash::error('Datos de reserva no válidos.');
    sget_redirigir(Config::basePath() . '/Pasajero/viajes_pasajero.php');
}

$reserva = ReservaService::porId($idReserva);
if (!$reserva || (int)$reserva['id_usu_res'] !== Auth::id()) {
    Flash::error('No tienes permiso para cancelar esta reserva.');
    sget_redirigir(Config::basePath() . '/Pasajero/viajes_pasajero.php');
}

if ((string)$reserva['estado_pago'] === Config::RES_CANCELADA) {
    Flash::info('Esta reserva ya estaba cancelada.');
    sget_redirigir(Config::basePath() . '/Pasajero/historial_pasajero.php');
}

$r = ReservaService::cancelar($idReserva, 'Cancelada por el pasajero');
if ($r['ok']) {
    Flash::exito('Reserva cancelada correctamente. Los puestos se liberaron al viaje.');
} else {
    Flash::error((string)$r['mensaje']);
}

sget_redirigir(Config::basePath() . '/Pasajero/historial_pasajero.php');
