<?php

/**
 * pruebas/_recaudo_check.php
 * -----------------------------------------------------------------------------
 * Estado real de un viaje en el módulo de recaudo: cupos, reservas por estado,
 * importe cobrado y avisos enviados. Es la fuente de verdad de la sonda: mira la
 * base de datos, no lo que la pantalla dice.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);


require_once __DIR__ . '/_guardia.php';
$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoto, ['127.0.0.1', '::1'], true) || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$idViaje = (int)($_GET['id_via'] ?? 0);
if ($idViaje <= 0) {
    echo json_encode(['error' => 'falta id_via']);
    exit;
}

/* --- Modo limpieza: deja el viaje como estaba para que la sonda sea
       repetible. Sin esto, cada ejecución acumula reservas y las
       comparaciones («antes» vs «después») dejan de tener sentido. --- */
if (isset($_GET['limpiar'])) {
    $borradas = Database::query("DELETE FROM reserva WHERE id_via_res = ?", [$idViaje])->rowCount();
    Database::query(
        "DELETE FROM notificacion WHERE id_via = ? AND tipo IN (?, ?, ?)",
        [$idViaje, NotificacionService::TIPO_RESERVA, NotificacionService::TIPO_CANCELACION, NotificacionService::TIPO_SALIDA]
    );
    $libres = ReservaService::recalcularCupos($idViaje);
    echo json_encode(['ok' => true, 'borradas' => $borradas, 'cupos_libres' => $libres], JSON_UNESCAPED_UNICODE);
    exit;
}

$cupos = (int) Database::scalar("SELECT cup_dis FROM viaje WHERE id_via = ?", [$idViaje]);

$porEstado = [];
foreach (Database::all(
    "SELECT estado_pago, COUNT(*) n FROM reserva WHERE id_via_res = ? GROUP BY estado_pago",
    [$idViaje]
) as $f) {
    $porEstado[(string)$f['estado_pago']] = (int)$f['n'];
}

$recaudo = (float) Database::scalar(
    "SELECT COALESCE(SUM(valor_pagado), 0) FROM reserva WHERE id_via_res = ? AND estado_pago = ?",
    [$idViaje, Config::RES_CONFIRMADA]
);

// Avisos de reserva enviados a los pasajeros de este viaje
$avisos = (int) Database::scalar(
    "SELECT COUNT(*) FROM notificacion WHERE id_via = ? AND tipo IN (?, ?)",
    [$idViaje, NotificacionService::TIPO_RESERVA, NotificacionService::TIPO_CANCELACION]
);

// Cualquier estado de pago fuera del ENUM sería un dato corrupto
$validos = [Config::RES_CONFIRMADA, Config::RES_PENDIENTE, Config::RES_CANCELADA];
$malos   = 0;
foreach (array_keys($porEstado) as $e) {
    if (!in_array($e, $validos, true)) $malos++;
}

echo json_encode([
    'cupos_libres'          => $cupos,
    'confirmadas'           => $porEstado[Config::RES_CONFIRMADA] ?? 0,
    'pendientes'            => $porEstado[Config::RES_PENDIENTE] ?? 0,
    'canceladas'            => $porEstado[Config::RES_CANCELADA] ?? 0,
    'recaudo'               => $recaudo,
    'pasajero_notificado'   => $avisos,
    'bad_value'             => $malos > 0,
], JSON_UNESCAPED_UNICODE);
