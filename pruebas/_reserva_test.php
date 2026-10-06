<?php
require_once __DIR__ . '/_guardia.php';

/*
 * pruebas/_reserva_test.php
 * -----------------------------------------------------------------------------
 * Reglas de negocio de RESERVAS, EMBARQUE Y RECAUDO (no necesita servidor).
 *
 * Comprueba, con datos de verdad y restaurándolos al terminar, los cuatro
 * comportamientos que se pidieron y que antes fallaban:
 *
 *   1. Un pasajero NO puede volver a apartar en un viaje donde ya tiene puesto,
 *      ni en otro que sale a la misma fecha y hora. Si puede, que sea con otra
 *      hora.
 *   2. El cobro guarda el IMPORTE REAL. Antes `crear()` guardaba el valor en
 *      NULL y el botón del recaudo mandaba ese NULL, así que el pago quedaba
 *      confirmado en $0 y los ingresos no sumaban nada.
 *   3. El embarque se guarda por separado del pago, y `embarco = 0` exige un
 *      motivo: sin él el informe no distingue un no-show de una cancelación.
 *   4. El aviso de «tu viaje salió sin ti» se manda una sola vez, no en cada
 *      sincronización de estado.
 * -----------------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';

$ok = 0; $fallos = 0;
function check(string $t, bool $c, string $d = ''): void {
    global $ok, $fallos;
    if ($c) { $ok++;  echo "  [OK]    $t\n"; }
    else    { $fallos++; echo "  [FALLA] $t" . ($d ? " -> $d" : '') . "\n"; }
}

$_SESSION['id_usu'] = 1;
$_SESSION['nombre_usuario'] = 'Prueba smoke';
$_SESSION['rol'] = Config::ROL_ADMIN;

$ruta  = Database::one("SELECT id_rut, val_rut FROM rutas ORDER BY id_rut LIMIT 1");
$cond  = Database::one("SELECT id_usu FROM usuario WHERE id_rol_usu = ? ORDER BY id_usu LIMIT 1", [Config::ROL_CONDUCTOR]);
if (!$ruta || !$cond) {
    echo "  (omitido: no hay ruta o conductor de prueba)\n";
    echo "\n RESULTADO: {$ok} correctas, {$fallos} fallidas\n";
    exit($fallos === 0 ? 0 : 1);
}
$tarifa = (float)($ruta['val_rut'] ?: 3500);

/** Crea un viaje de prueba con cupos de sobra. */
$mkViaje = static function (string $fecha, string $hora) use ($ruta, $cond, $tarifa): int {
    return Database::insert(
        "INSERT INTO viaje (nom_via, fec_via, hor_sal_via, val_via, id_rut_via, id_usu_via, est_via, cup_tot, cup_dis)
         VALUES (?, ?, ?, ?, ?, ?, ?, 20, 20)",
        ['PRUEBA', $fecha, $hora, $tarifa, (int)$ruta['id_rut'], (int)$cond['id_usu'], Config::VIA_PROGRAMADO]
    );
};
$limpiar = static function (int $idViaje): void {
    Database::query("DELETE FROM reserva WHERE id_via_res = ?", [$idViaje]);
    Database::query("DELETE FROM viaje WHERE id_via = ?", [$idViaje]);
};

echo "\n=== 1) Un pasajero no puede apartar dos veces lo mismo ===\n";

$hoy      = date('Y-m-d');
$manana   = date('Y-m-d', strtotime('+1 day'));
$pasajero = (int) Database::scalar("SELECT id_usu FROM usuario WHERE id_rol_usu = ? ORDER BY id_usu LIMIT 1", [Config::ROL_PASAJERO]);
$libre    = (int) Database::scalar("SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND id_usu <> ? ORDER BY id_usu LIMIT 1", [Config::ROL_PASAJERO, $pasajero]);

if (!$pasajero) {
    echo "  (omitido: no hay pasajero de prueba)\n";
} else {
    $vA = $mkViaje($manana, '08:00:00');
    $vB = $mkViaje($manana, '08:00:00');   // MISMA fecha y hora
    $vC = $mkViaje($manana, '17:00:00');   // misma fecha, OTRA hora
    $vD = $mkViaje($hoy,  '08:00:00');    // otra fecha

    $primera = ReservaService::crear($vA, $pasajero, 1, ['metodo' => 'Efectivo al Abordar']);
    check('La primera reserva del viaje se acepta', $primera['ok'] === true, $primera['mensaje']);

    $segunda = ReservaService::crear($vA, $pasajero, 1, ['metodo' => 'Efectivo al Abordar']);
    check('Apartar OTRA VEZ en el MISMO viaje se rechaza', $segunda['ok'] === false, $segunda['mensaje']);
    check('El mensaje de «mismo viaje» explica la regla',
        $segunda['mensaje'] === 'Ya cuentas con una reserva activa para este viaje. No es posible reservar el mismo viaje más de una vez.',
        $segunda['mensaje']);

    $choque = ReservaService::crear($vB, $pasajero, 1, ['metodo' => 'Efectivo al Abordar']);
    check('Apartar en OTRO viaje a la MISMA fecha y hora se rechaza', $choque['ok'] === false, $choque['mensaje']);
    check('El mensaje de «misma hora» explica la regla',
        str_contains($choque['mensaje'], 'MISMA fecha y hora'), $choque['mensaje']);

    $otraHora = ReservaService::crear($vC, $pasajero, 1, ['metodo' => 'Efectivo al Abordar']);
    check('Apartar en el MISMO día pero a OTRA hora SÍ se permite', $otraHora['ok'] === true, $otraHora['mensaje']);

    $otraFecha = ReservaService::crear($vD, $pasajero, 1, ['metodo' => 'Efectivo al Abordar']);
    check('Apartar en OTRA fecha SÍ se permite', $otraFecha['ok'] === true, $otraFecha['mensaje']);

    // Cancelando la reserva del viaje A, vuelve a poder apartar en él.
    ReservaService::cancelar($primera['ids'][0], 'Liberado por la prueba');
    $reintento = ReservaService::crear($vA, $pasajero, 1, ['metodo' => 'Efectivo al Abordar']);
    check('Tras cancelar, el pasajero vuelve a poder apartar en ese viaje', $reintento['ok'] === true, $reintento['mensaje']);

    $limpiar($vA); $limpiar($vB); $limpiar($vC); $limpiar($vD);
}

echo "\n=== 2) El cobro guarda el importe real, no 0 ===\n";

$vCobro = $mkViaje($manana, '06:00:00');
$res    = ReservaService::crear($vCobro, $libre ?: 1, 3, ['metodo' => 'Efectivo al Abordar']);
check('Se crean 3 puestos', $res['ok'] === true && (int)$res['puestos'] === 3, $res['mensaje']);

$pactado = (float) Database::scalar("SELECT valor_pagado FROM reserva WHERE id_res = ?", [(int)$res['ids'][0]]);
check('Cada puesto guarda el VALOR PACTADO (la tarifa)', $pactado > 0, 'valor_pagado=' . $pactado);
check('El valor pactado es el de la tarifa del viaje', abs($pactado - $tarifa) < 0.01,
    'pactado=' . $pactado . ' tarifa=' . $tarifa);

// Cobro de los 3 puestos de golpe, sin pasar importe (0 = usar el pactado).
$sinValor = Database::scalar("SELECT valor_pagado FROM reserva WHERE id_res = ?", [(int)$res['ids'][0]]);
check('Antes de cobrar están en Pendiente',
    (string) Database::scalar("SELECT estado_pago FROM reserva WHERE id_res = ?", [(int)$res['ids'][0]]) === Config::RES_PENDIENTE);

$cobro = ReservaService::confirmarPago((int)$res['ids'][0], 0, 'Efectivo', true);
check('El cobro de 3 puestos a la vez se registra', $cobro['ok'] === true, $cobro['mensaje']);
check('Se cobran los 3 puestos', (int)($cobro['cobradas'] ?? 0) === 3, json_encode($cobro));

$suma = (float) Database::scalar(
    "SELECT COALESCE(SUM(valor_pagado), 0) FROM reserva WHERE id_res IN (" .
    implode(',', array_map('intval', $res['ids'])) . ")"
);
check('La suma cobrada NO es 0 (era el bug reportado)', $suma > 0, 'suma=' . $suma);
check('La suma cobrada es la tarifa x 3', abs($suma - $tarifa * 3) < 0.01, 'suma=' . $suma . ' esperado=' . ($tarifa * 3));

check('Las 3 reservas quedan Confirmadas',
    (int) Database::scalar("SELECT COUNT(*) FROM reserva WHERE id_res IN (" .
        implode(',', array_map('intval', $res['ids'])) . ") AND estado_pago = ?", [Config::RES_CONFIRMADA]) === 3);

// Un importe explícito manda sobre el pactado.
$vOtro = $mkViaje($manana, '06:30:00');
$res2  = ReservaService::crear($vOtro, $libre ?: 1, 1, ['metodo' => 'Efectivo al Abordar']);
ReservaService::confirmarPago((int)$res2['ids'][0], 1234, 'Transferencia');
check('Si el cajero escribe un importe, ese es el que se guarda',
    abs((float) Database::scalar("SELECT valor_pagado FROM reserva WHERE id_res = ?", [(int)$res2['ids'][0]]) - 1234) < 0.01);

// Una reserva ya cobrada no se vuelve a cobrar.
check('No se puede cobrar dos veces la misma reserva',
    ReservaService::confirmarPago((int)$res2['ids'][0], 5000)['ok'] === false);
$limpiar($vOtro);

echo "\n=== 3) El embarque va aparte del pago y exige motivo ===\n";

$vEmb = $mkViaje($manana, '07:00:00');
$res3 = ReservaService::crear($vEmb, $libre ?: 1, 2, ['metodo' => 'Efectivo al Abordar']);
$idA  = (int)$res3['ids'][0];
$idB  = (int)$res3['ids'][1];

$abordajeSinPago = ReservaService::marcarEmbarque($idA, true);
check('No se puede marcar abordaje mientras el pago siga pendiente', $abordajeSinPago['ok'] === false,
    $abordajeSinPago['mensaje']);

ReservaService::confirmarPago($idA, 0, 'Efectivo');
check('Pagar NO marca el embarque por el solo hecho de pagar',
    Database::scalar("SELECT embarco FROM reserva WHERE id_res = ?", [$idA]) === null);

check('Marcar que embarcó funciona', ReservaService::marcarEmbarque($idA, true)['ok'] === true);
check('`embarco` queda en 1', (int) Database::scalar("SELECT embarco FROM reserva WHERE id_res = ?", [$idA]) === 1);

$sinMotivo = ReservaService::marcarEmbarque($idB, false, '');
check('Marcar NO PRESENTACIÓN sin motivo se rechaza', $sinMotivo['ok'] === false, $sinMotivo['mensaje']);
check('El motivo obligatorio se explica en el mensaje',
    str_contains($sinMotivo['mensaje'], 'motivo'), $sinMotivo['mensaje']);

$conMotivo = ReservaService::marcarEmbarque($idB, false, 'No salió de su casa');
check('Marcar NO PRESENTACIÓN con motivo funciona', $conMotivo['ok'] === true, $conMotivo['mensaje']);
check('`embarco` queda en 0', (int) Database::scalar("SELECT embarco FROM reserva WHERE id_res = ?", [$idB]) === 0);
check('El motivo queda guardado para el informe',
    (string) Database::scalar("SELECT motivo_cancelacion FROM reserva WHERE id_res = ?", [$idB]) === 'No salió de su casa');

$manifiesto = ReservaService::manifiesto($vEmb);
check('El manifiesto cuenta 2 puestos', (int)($manifiesto[0]['puestos'] ?? 0) === 2);
check('El manifiesto cuenta 1 embarcado', (int)($manifiesto[0]['embarcaron'] ?? 0) === 1);
check('El manifiesto cuenta 1 no presentado', (int)($manifiesto[0]['no_embarcaron'] ?? 0) === 1);
check('El manifiesto NO cuenta como embarked a los cancelados',
    array_sum(array_column(ReservaService::manifiesto($vEmb), 'embarcaron')) === 1);

$detalle = InformacionService::pasajerosDeViaje($vEmb);
check('El informe distingue viajeros de no-presentados',
    (int)$detalle['totales']['viajeros'] === 1 && (int)$detalle['totales']['no_presentados'] === 1,
    json_encode($detalle['totales']));

$noPresentes = InformacionService::noPresentaciones(['desde' => date('Y-m-d', strtotime('-1 day')), 'hasta' => date('Y-m-d', strtotime('+2 day'))], 50);
check('El informe de no-presentaciones encuentra el caso', count($noPresentes) >= 1, 'n=' . count($noPresentes));

echo "\n=== 4) El aviso de viaje perdido se manda UNA vez ===\n";

$antesAvisos = (int) Database::scalar("SELECT COUNT(*) FROM notificacion WHERE tipo = ?", [NotificacionService::TIPO_VIAJE_PERDIDO]);
$vPerdido = $mkViaje($manana, '09:00:00');
$res4    = ReservaService::crear($vPerdido, $libre ?: 1, 2, ['metodo' => 'Efectivo al Abordar']);
ReservaService::confirmarPago((int)$res4['ids'][0], 0, 'Efectivo', true);

$primero = ReservaService::avisarViajePerdido($vPerdido);
check('Se avisa a quien tenía puesto y no embarcó', (int)($primero['avisados'] ?? 0) === 1, json_encode($primero));

$despuesAvisos = (int) Database::scalar("SELECT COUNT(*) FROM notificacion WHERE tipo = ?", [NotificacionService::TIPO_VIAJE_PERDIDO]);
check('Se creó 1 aviso nuevo', $despuesAvisos - $antesAvisos === 1, 'antes=' . $antesAvisos . ' despues=' . $despuesAvisos);
check('El aviso es de tipo «viaje perdido»',
    (string) Database::scalar("SELECT tipo FROM notificacion WHERE tipo = ? ORDER BY id_not DESC LIMIT 1", [NotificacionService::TIPO_VIAJE_PERDIDO])
        === NotificacionService::TIPO_VIAJE_PERDIDO);

$segundo = ReservaService::avisarViajePerdido($vPerdido);
check('La segunda llamada NO vuelve a avisar (la sincronización corre a cada rato)',
    (int)($segundo['avisados'] ?? 0) === 0, json_encode($segundo));
check('No se duplicó ningún aviso',
    (int) Database::scalar("SELECT COUNT(*) FROM notificacion WHERE tipo = ?", [NotificacionService::TIPO_VIAJE_PERDIDO]) === $despuesAvisos);

echo "\n=== 5) Un conductor solo ve y marca los viajes suyos ===\n";

check('El admin puede ver el manifiesto de cualquier viaje', ViajeService::puedeVerManifiesto($vPerdido) === true);
$_SESSION['rol'] = Config::ROL_CONDUCTOR;
$_SESSION['id_usu'] = (int)$cond['id_usu'];
check('El conductor SÍ puede ver el manifiesto de su viaje', ViajeService::puedeVerManifiesto($vPerdido) === true);

$vAjeno = $mkViaje($manana, '19:00:00');
Database::query("UPDATE viaje SET id_usu_via = ? WHERE id_via = ?", [1, $vAjeno]);
check('El conductor NO puede ver el manifiesto de un viaje ajeno', ViajeService::puedeVerManifiesto($vAjeno) === false);
$limpiar($vAjeno);

$_SESSION['rol'] = Config::ROL_ADMIN;
$_SESSION['id_usu'] = 1;

echo "\n=== 6) El conductor confirma pago y abordaje en una acción ===\n";
$vCobroConductor = $mkViaje($manana, '20:00:00');
$resConductor = ReservaService::crear($vCobroConductor, $pasajero, 1, [
    'metodo' => 'Efectivo al Abordar',
]);
$idReservaConductor = (int)$resConductor['ids'][0];
$_SESSION['rol'] = Config::ROL_CONDUCTOR;
$_SESSION['id_usu'] = (int)$cond['id_usu'];

$confirmacionConductor = ReservaService::cobrarYEmbarcarPasajero($vCobroConductor, $pasajero);
$estadoConfirmacion = Database::one(
    'SELECT estado_pago, fecha_pago, embarco, embarque_por, embarque_fec
       FROM reserva WHERE id_res = ?',
    [$idReservaConductor]
);
check('El conductor confirma el cobro en efectivo', $confirmacionConductor['ok'] === true,
    $confirmacionConductor['mensaje']);
check('El pago queda confirmado y el puesto abordado',
    $estadoConfirmacion['estado_pago'] === Config::RES_CONFIRMADA && (int)$estadoConfirmacion['embarco'] === 1);
check('Se registran las horas de pago y abordaje',
    !empty($estadoConfirmacion['fecha_pago']) && !empty($estadoConfirmacion['embarque_fec']));
check('La confirmación queda atribuida al conductor de sesión',
    (int)$estadoConfirmacion['embarque_por'] === (int)$cond['id_usu']);

$_SESSION['rol'] = Config::ROL_ADMIN;
$_SESSION['id_usu'] = 1;

/* --- limpieza --- */
$limpiar($vCobro);
$limpiar($vEmb);
$limpiar($vPerdido);
Database::query('DELETE FROM notificacion WHERE id_via = ?', [$vCobroConductor]);
$limpiar($vCobroConductor);
Database::query("DELETE FROM notificacion WHERE tipo = ? AND id_not > 0 AND tipo = ?",
    [NotificacionService::TIPO_VIAJE_PERDIDO, NotificacionService::TIPO_VIAJE_PERDIDO]);

echo "\n" . str_repeat('=', 60) . "\n";
echo " RESULTADO: {$ok} correctas, {$fallos} fallidas\n";
echo str_repeat('=', 60) . "\n";
exit($fallos === 0 ? 0 : 1);
