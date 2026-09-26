<?php
/**
 * pruebas/smoke.php  ·  Ejecución:  php pruebas/smoke.php
 * -----------------------------------------------------------------------------
 * PRUEBAS DE HUMO DE LA CAPA DE DATOS
 * Verifica que la corrección del "Data Default Fallback" realmente se sostiene:
 *   1. No quedan fechas/horas cero en viaje.
 *   2. Fecha y hora se escriben separadas (DATE / TIME) y se leen sin "1970".
 *   3. Los estados tienen una sola definición (0/1, nunca texto).
 *   4. Rutas siempre tienen salida y destino.
 *   5. Cancelar un viaje exige anotación y notifica a los pasajeros.
 *   6. Los servicios validan y rechazan datos mal formados.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

define('SGET_SMOKE', true);
require_once dirname(__DIR__) . '/core/bootstrap.php';

// El login/Auth no aplica en CLI: se simula la sesión del administrador.
$_SESSION = [
    'id_usu' => 1, 'documento' => '000000', 'rol' => Config::ROL_ADMIN,
    'nombre_usuario' => 'Prueba Automática', 'sget_csrf' => 'test-token',
];

$ok = 0; $fallos = 0;
function check(string $titulo, bool $condicion, string $detalle = ''): void
{
    global $ok, $fallos;
    if ($condicion) { $ok++;  echo "  [OK]   {$titulo}\n"; }
    else            { $fallos++; echo "  [FALLA] {$titulo}" . ($detalle ? " -> {$detalle}" : '') . "\n"; }
}

echo "\n=== 1. Integridad de fechas y horas ===\n";
$cero = (int) Database::scalar(
    "SELECT COUNT(*) FROM viaje
      WHERE fec_via = '0000-00-00' OR hor_sal_via = '00:00:00' OR hor_sal_via IS NULL OR fec_via IS NULL"
);
check('No existen fechas/horas cero en viaje', $cero === 0, "{$cero} fila(s) inválida(s)");

$tipoFec = (string) Database::scalar("SELECT DATA_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='viaje' AND COLUMN_NAME='fec_via'");
$tipoSal = (string) Database::scalar("SELECT DATA_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='viaje' AND COLUMN_NAME='hor_sal_via'");
check('viaje.fec_via es DATE',     $tipoFec === 'date', "tipo real: {$tipoFec}");
check('viaje.hor_sal_via es TIME', $tipoSal === 'time', "tipo real: {$tipoSal}");

echo "\n=== 2. Normalización de la capa Fecha ===\n";
check('Fecha "2026-09-26" -> 2026-09-26',   Fecha::fecha('2026-09-26') === '2026-09-26');
check('Hora "06:30" -> 06:30:00',            Fecha::hora('06:30') === '06:30:00');
check('Fecha cero se rechaza',              Fecha::esVacia('0000-00-00 00:00:00'));
try { Fecha::hora('99:00', 'hora', true); check('Hora inválida se rechaza', false); }
catch (ValueError $e) { check('Hora inválida se rechaza (' . $e->getMessage() . ')', true); }
[$salio] = Fecha::yaSalio(date('Y-m-d'), '01:00:00');
check('Un viaje de hoy a la 01:00 ya salió', $salio === true);
[$salio] = Fecha::yaSalio(date('Y-m-d', strtotime('+1 day')), '23:00:00');
check('Un viaje de mañana todavía no salió', $salio === false);

echo "\n=== 3. Estados con definición única ===\n";
$malos = (int) Database::scalar("SELECT COUNT(*) FROM vehiculo WHERE est_veh IS NULL OR est_veh NOT IN (0,1)");
check('vehiculo.est_veh solo admite 0/1', $malos === 0, "{$malos} fila(s)");
$malos = (int) Database::scalar("SELECT COUNT(*) FROM usuario WHERE estado IS NULL");
check('usuario.estado nunca es NULL', $malos === 0, "{$malos} fila(s)");
$malos = (int) Database::scalar("SELECT COUNT(*) FROM usuario WHERE id_rol_usu = 2 AND (est_con_usu IS NULL OR est_con_usu NOT IN (0,1))");
check('Conductores siempre 0/1', $malos === 0, "{$malos} fila(s)");
$malos = (int) Database::scalar("SELECT COUNT(*) FROM viaje WHERE est_via NOT IN ('Programado','En curso','Finalizado','Cancelado')");
check('viaje.est_via es un ENUM válido', $malos === 0, "{$malos} fila(s)");

echo "\n=== 4. Rutas sin origen ni destino ===\n";
$vacias = (int) Database::scalar("SELECT COUNT(*) FROM rutas WHERE TRIM(ori_rut) = '' OR TRIM(des_rut) = ''");
check('Ninguna ruta tiene salida o destino vacío', $vacias === 0, "{$vacias} ruta(s)");

echo "\n=== 5. Validación de Rutas ===\n";
$r = RutaService::validar(['nom_rut' => 'Prueba', 'ori_rut' => 'A', 'des_rut' => 'A', 'val_rut' => '0']);
check('Rechaza tarifa en 0',                 isset($r->errores()['val_rut']));
$r = RutaService::validar(['nom_rut' => 'Prueba', 'ori_rut' => 'Alpha', 'des_rut' => 'Beta']);
check('Rechaza tarifa ausente',              isset($r->errores()['val_rut']));
$r = RutaService::validar(['nom_rut' => 'Prueba', 'ori_rut' => 'Alpha', 'des_rut' => 'Alpha', 'val_rut' => '1000']);
check('Rechaza origen == destino',           isset($r->errores()['des_rut']));
$r = RutaService::validar(['nom_rut' => 'Prueba', 'ori_rut' => 'Alpha', 'des_rut' => 'Beta', 'val_rut' => '1000']);
check('Acepta una ruta válida',              !$r->falla(), json_encode($r->errores()));

echo "\n=== 6. Alta real de ruta (ida y vuelta) ===\n";
$nombre = 'SMOKE-' . bin2hex(random_bytes(3));
$res = RutaService::guardar([
    'nom_rut' => $nombre, 'ori_rut' => 'Prueba_origen', 'des_rut' => 'Prueba_destino',
    'dis_rut' => '88.5', 'val_rut' => '12500.50', 'hora_salida' => '05:45',
]);
check('Ruta creada', $res['ok'] === true, json_encode($res));
$guardada = RutaService::porId($res['id']);
check('Guarda origen',  $guardada['ori_rut'] === 'Prueba_origen',  (string)$guardada['ori_rut']);
check('Guarda destino', $guardada['des_rut'] === 'Prueba_destino', (string)$guardada['des_rut']);
check('Guarda hora de salida como TIME', $guardada['hora_salida'] === '05:45:00', (string)$guardada['hora_salida']);
check('Guarda la distancia', abs((float)$guardada['dis_rut'] - 88.5) < 0.001);

echo "\n=== 7. Validación de Viajes ===\n";
$conductor = Database::one("SELECT id_usu FROM usuario WHERE id_rol_usu = 2 AND estado = 1 LIMIT 1");
$vehiculo  = Database::one("SELECT id_veh, cap_veh, est_veh FROM vehiculo LIMIT 1");
if ($conductor && $vehiculo) {

    // ── Fixture: se liberan los recursos para poder probarlos de forma aislada
    //    y se restauran al final (la prueba no debe dejar basura en producción).
    $estadoConductorPrevio = (int) Database::scalar("SELECT COALESCE(est_con_usu,1) FROM usuario WHERE id_usu = ?", [(int)$conductor['id_usu']]);
    $estadoVehiculoPrevio  = (int) Database::scalar("SELECT est_veh FROM vehiculo WHERE id_veh = ?", [(int)$vehiculo['id_veh']]);
    Database::query("UPDATE usuario SET est_con_usu = 1 WHERE id_usu = ?", [(int)$conductor['id_usu']]);
    Database::query("UPDATE vehiculo SET est_veh = 1 WHERE id_veh = ?", [(int)$vehiculo['id_veh']]);

    // Los viajes preexistentes que ocupen al conductor/unidad se marcan
    // temporalmente como Finalizados (y se restauran al final).
    $viajesBloqueantes = Database::all(
        "SELECT id_via, est_via FROM viaje
          WHERE est_via IN ('Programado','En curso')
            AND (id_usu_via = ? OR id_veh = ?)",
        [(int)$conductor['id_usu'], (int)$vehiculo['id_veh']]
    );
    foreach ($viajesBloqueantes as $vb) {
        Database::query("UPDATE viaje SET est_via = 'Finalizado' WHERE id_via = ?", [(int)$vb['id_via']]);
    }
    $base = [
        'id_rut_via' => $res['id'],
        'id_usu_via' => (int)$conductor['id_usu'],
        'id_veh'     => (int)$vehiculo['id_veh'],
        'fec_via'    => date('Y-m-d', strtotime('+2 days')),
        'hor_sal_via'=> '07:30',
        'val_via'    => '12500',
    ];
    $v = ViajeService::validar($base);
    check('Viaje válido pasa la validación', !$v->falla(), json_encode($v->errores()));

    $malo = $base; $malo['hor_sal_via'] = '';
    check('Rechaza hora vacía', isset(ViajeService::validar($malo)->errores()['hor_sal_via']));

    $pasado = $base; $pasado['fec_via'] = date('Y-m-d', strtotime('-3 days'));
    check('Rechaza salida en el pasado', isset(ViajeService::validar($pasado)->errores()['fec_via']));

    // Alta real
    $alta = ViajeService::guardar($base);
    check('Viaje creado', $alta['ok'] === true, json_encode($alta));
    $viaje = ViajeService::porId($alta['id']);
    check('fec_via queda como Y-m-d',      $viaje['fec_via'] === $base['fec_via'], (string)$viaje['fec_via']);
    check('hor_sal_via queda como H:i:s',  $viaje['hor_sal_via'] === '07:30:00', (string)$viaje['hor_sal_via']);
    check('cupos heredados del vehículo',  (int)$viaje['cup_tot'] === (int)$vehiculo['cap_veh']);

    $ocupado = (int) Database::scalar("SELECT est_con_usu FROM usuario WHERE id_usu = ?", [(int)$conductor['id_usu']]);
    check('Conductor queda OCUPADO (0)', $ocupado === Config::CON_OCUPADO, "valor: {$ocupado}");

    // No se puede reutilizar un conductor ya ocupado
    $repetido = $base; $repetido['id_veh'] = (int)$vehiculo['id_veh'];
    check('Rechaza conductor ya ocupado', isset(ViajeService::validar($repetido)->errores()['id_usu_via'])
                                       || isset(ViajeService::validar($repetido)->errores()['id_veh']));

    echo "\n=== 8. Cancelación con anotación obligatoria ===\n";
    $sinNota = ViajeService::cancelar((int)$alta['id'], 'averia_unidad', 'x');
    check('Rechaza anotación demasiado corta', $sinNota['ok'] === false, (string)$sinNota['mensaje']);

    $sinMotivo = ViajeService::cancelar((int)$alta['id'], '', 'Esta es una anotación suficientemente larga para el sistema.');
    check('Rechaza motivo vacío', $sinMotivo['ok'] === false);

    // Pasajero de prueba: se reutiliza uno existente o se crea uno temporal.
    $pasajero = Database::one("SELECT id_usu FROM usuario WHERE id_rol_usu = 3 LIMIT 1");
    $pasajeroTemporal = false;
    if (!$pasajero) {
        $doc = 'T' . random_int(900000, 999999);
        $pasajeroTemporal = true;
        $pasajero = ['id_usu' => Database::insert(
            "INSERT INTO usuario (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, tel_usu, id_rol_usu, pass_usu, estado)
             VALUES ('CC', ?, 'Pasajero de Prueba', ?, '3000000000', 3, ?, 1)",
            [$doc, "smoke_$doc@test.local", password_hash('prueba123', PASSWORD_DEFAULT)]
        )];
    }
    {
        Database::query("INSERT INTO reserva (id_via_res, id_usu_res, metodo_pago, valor_pagado, estado_pago, fecha_pago)
                         VALUES (?, ?, 'Efectivo', 12500, 'Confirmada', NOW())", [(int)$alta['id'], (int)$pasajero['id_usu']]);
        $antes = NotificacionService::noLeidas((int)$pasajero['id_usu']);
    }

    $ok2 = ViajeService::cancelar((int)$alta['id'], 'averia_unidad',
        'El vehículo presentó una falla mecánica y no puede cumplir la salida programada.');
    check('Cancelación aceptada con anotación válida', $ok2['ok'] === true, (string)$ok2['mensaje']);
    check('Notifica a 1 pasajero', ($ok2['notificados'] ?? 0) === 1, 'notificados: ' . ($ok2['notificados'] ?? 0));
    check('La reserva queda Cancelada',
        (string) Database::scalar("SELECT estado_pago FROM reserva WHERE id_via_res = ?", [(int)$alta['id']]) === Config::RES_CANCELADA);

    if ($pasajero) {
        $despues = NotificacionService::noLeidas((int)$pasajero['id_usu']);
        check('El pasajero recibe la notificación', $despues > $antes, "antes {$antes} / después {$despues}");
        $cuerpo = (string) Database::scalar("SELECT cuerpo FROM notificacion ORDER BY id_not DESC LIMIT 1");
        check('El mensaje incluye el motivo y la anotación',
            str_contains($cuerpo, 'Avería de la unidad') && str_contains($cuerpo, 'falla mecánica'));
    }

    $liberado = (int) Database::scalar("SELECT est_con_usu FROM usuario WHERE id_usu = ?", [(int)$conductor['id_usu']]);
    check('Conductor queda LIBERADO (1) tras cancelar', $liberado === Config::CON_DISPONIBLE, "valor: {$liberado}");

    $noRepetible = ViajeService::cancelar((int)$alta['id'], 'averia_unidad', 'Otra anotación totalmente válida para el sistema.');
    check('No se puede cancelar dos veces', $noRepetible['ok'] === false);

    // Limpieza
    Database::query("DELETE FROM notificacion WHERE id_via = ?", [(int)$alta['id']]);
    Database::query("DELETE FROM reserva WHERE id_via_res = ?", [(int)$alta['id']]);
    Database::query("DELETE FROM viaje WHERE id_via = ?", [(int)$alta['id']]);
    if ($pasajeroTemporal) {
        Database::query("DELETE FROM usuario WHERE id_usu = ?", [(int)$pasajero['id_usu']]);
    }

    // Restaurar el estado original de los recursos y de los viajes bloqueantes
    foreach ($viajesBloqueantes as $vb) {
        Database::query("UPDATE viaje SET est_via = ? WHERE id_via = ?", [$vb['est_via'], (int)$vb['id_via']]);
    }
    Database::query("UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?", [$estadoConductorPrevio, (int)$conductor['id_usu']]);
    Database::query("UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?", [$estadoVehiculoPrevio, (int)$vehiculo['id_veh']]);
    echo "  (fixture restaurado)\n";
} else {
    echo "  (omitido: no hay conductor ni vehículo de prueba)\n";
}

echo "\n=== 9. Limpieza de la ruta de prueba ===\n";
$res2 = RutaService::eliminar($res['id']);
check('Ruta eliminada', $res2['ok'] === true, (string)$res2['mensaje']);

/* -------------------------------------------------------------------------- */
echo "\n" . str_repeat('=', 60) . "\n";
echo " RESULTADO: {$ok} correctas, {$fallos} fallidas\n";
echo str_repeat('=', 60) . "\n\n";
exit($fallos === 0 ? 0 : 1);
