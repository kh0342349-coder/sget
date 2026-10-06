<?php
/**
 * pruebas/disponibilidad.php
 * -----------------------------------------------------------------------------
 * DISPONIBILIDAD DE CONDUCTORES Y VEHÍCULOS POR VENTANA HORARIA
 * -----------------------------------------------------------------------------
 *     php pruebas/disponibilidad.php
 *
 * No necesita servidor: comprueba la lógica de `DisponibilidadService` y el
 * comportamiento real de `ViajeService` contra la base de datos.
 *
 * Cubre los quince casos exigidos:
 *    1. Mismo conductor, horarios separados      -> permitir
 *    2. Mismo conductor, horarios superpuestos   -> rechazar
 *    3. Mismo vehículo, horarios separados      -> permitir
 *    4. Mismo vehículo, horarios superpuestos   -> rechazar
 *    5. Viajes en días diferentes               -> permitir
 *    6. Viaje cancelado                         -> libera recursos
 *    7. Viaje finalizado manualmente           -> libera recursos
 *    8. Viaje finalizado automáticamente        -> libera recursos
 *    9. Viaje futuro                            -> NO bloquea el recurso
 *   10. Editar sin cambiar conductor/vehículo   -> permitir
 *   11. Editar a un conductor con conflicto     -> rechazar
 *   12. Editar a un vehículo con conflicto      -> rechazar
 *   13. Vehículo en mantenimiento               -> rechazar
 *   14. Vehículo fuera de servicio              -> rechazar
 *   15. Conductor inactivo                      -> rechazar
 *
 * Más: el margen configurable, el orden de prioridad de la duración y que el
 * selector devuelva EXACTAMENTE lo mismo que decide la validación.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/..' . '/core/bootstrap.php';

$ok = 0;
$fallos = 0;

function check(string $titulo, bool $cumple, string $detalle = ''): void
{
    global $ok, $fallos;
    if ($cumple) {
        $ok++;
        echo "  [OK]   $titulo\n";
    } else {
        $fallos++;
        echo "  [FALLA] $titulo" . ($detalle !== '' ? " -> $detalle" : '') . "\n";
    }
}

function seccion(string $titulo): void
{
    echo "\n=== $titulo ===\n";
}

/** Datos de prueba efímeros que se limpian al terminar. */
$limpiar = ['viajes' => [], 'vehiculos' => [], 'usuarios' => []];

function crearVehiculo(string $estado): int
{
    global $limpiar;
    $id = Database::insert(
        'INSERT INTO vehiculo (pla_veh, mode_veh, cap_veh, est_veh) VALUES (?, ?, ?, ?)',
        ['PRB' . random_int(1000, 9999), 'Vehículo de prueba', 12, $estado]
    );
    $limpiar['vehiculos'][] = $id;
    return $id;
}

function crearConductor(bool $activo = true): int
{
    global $limpiar;
    $doc = 'PRB' . random_int(100000, 999999);
    $id = Database::insert(
        "INSERT INTO usuario (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, id_rol_usu, pass_usu, estado, est_con_usu)
         VALUES ('CC', ?, ?, ?, ?, ?, ?, ?)",
        [$doc, 'Conductor de prueba ' . $doc, strtolower($doc) . '@test.local',
         Config::ROL_CONDUCTOR, password_hash('prueba', PASSWORD_DEFAULT),
         $activo ? Config::USU_ACTIVO : Config::USU_INACTIVO, Config::CON_DISPONIBLE]
    );
    $limpiar['usuarios'][] = $id;
    return $id;
}

function crearViaje(int $conductor, int $vehiculo, string $fecha, string $salida, ?string $llegada = null, int $ruta = 0): int
{
    global $limpiar;
    $ruta = $ruta > 0 ? $ruta : (int) Database::scalar('SELECT id_rut FROM rutas ORDER BY id_rut LIMIT 1');
    $val = (float) (Database::scalar('SELECT val_rut FROM rutas WHERE id_rut = ?', [$ruta]) ?? 3500);

    $id = Database::insert(
        'INSERT INTO viaje (nom_via, id_rut_via, id_usu_via, id_veh, fec_via, hor_sal_via, hor_lleg_via,
                            val_via, cup_tot, cup_dis, est_via, salio)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 12, 12, ?, 0)',
        ['Viaje de prueba', $ruta, $conductor, $vehiculo, $fecha, $salida . ':00', $llegada, $val, Config::VIA_PROGRAMADO]
    );
    $limpiar['viajes'][] = $id;
    return $id;
}

function rutaPorDefecto(): int
{
    return (int) Database::scalar('SELECT id_rut FROM rutas ORDER BY id_rut LIMIT 1');
}

/**
 * Intenta crear un viaje por el servicio REAL.
 *
 * @param string $fecha 'Y-m-d'
 */
function intentarGuardar(int $conductor, int $vehiculo, string $fecha, string $salida, ?string $llegada = null): array
{
    return ViajeService::guardar([
        'id_rut_via'   => rutaPorDefecto(),
        'id_usu_via'   => $conductor,
        'id_veh'       => $vehiculo,
        'fec_via'      => $fecha,
        'hor_sal_via'  => $salida,
        'hor_lleg_via' => $llegada,
    ]);
}

/**
 * Pregunta a la lógica de disponibilidad por un hueco del PASADO.
 *
 * `ViajeService::guardar()` rechaza las salidas en el pasado a propósito (es
 * correcto para el alta), así que para comprobar «el horario quedó libre» hay
 * que preguntar directamente a la disponibilidad, que es lo que decide.
 */
function librePara(int $conductor, int $vehiculo, string $fecha, string $salida, ?string $llegada = null): array
{
    $ventana = DisponibilidadService::ventanaDe($fecha, $salida, 0, $llegada);
    return [
        'conductor' => DisponibilidadService::comprobarConductor($conductor, $ventana),
        'vehiculo'  => DisponibilidadService::comprobarVehiculo($vehiculo, $ventana),
    ];
}

function limpiarTodo(): void
{
    global $limpiar;
    foreach ($limpiar['viajes'] as $id) {
        Database::query('DELETE FROM viaje WHERE id_via = ?', [$id]);
    }
    foreach ($limpiar['vehiculos'] as $id) {
        Database::query('DELETE FROM vehiculo WHERE id_veh = ?', [$id]);
    }
    foreach ($limpiar['usuarios'] as $id) {
        Database::query('DELETE FROM usuario WHERE id_usu = ?', [$id]);
    }
}

/* ====================================================================== */
echo str_repeat('=', 72) . "\n";
echo " DISPONIBILIDAD POR VENTANA HORARIA · SGET\n";
echo str_repeat('=', 72) . "\n";

$manana  = date('Y-m-d', strtotime('+30 days'));
$pasado  = date('Y-m-d', strtotime('-30 days'));
$hoy     = date('Y-m-d');

/* ---------------------------------------------------------------------- */
seccion('0. Ventana y margen');

$v = DisponibilidadService::ventanaDe($hoy, '10:00', 120, '12:00');
check('La ventana empieza a la hora de salida', date('Y-m-d H:i', $v['inicio']) === "$hoy 10:00",
    date('Y-m-d H:i', $v['inicio']));
check('La ventana termina a la hora de llegada', date('Y-m-d H:i', $v['fin']) === "$hoy 12:00",
    date('Y-m-d H:i', $v['fin']));
check('El margen sale de Config y es configurable', $v['margen'] === Config::MARGEN_DISPONIBILIDAD_MIN * 60,
    "margen: {$v['margen']}");

$v2 = DisponibilidadService::ventanaDe($hoy, '10:00', 120, null);
check('Sin hora de llegada se usa la duración indicada', $v2['fin'] - $v2['inicio'] === 7200);

$v3 = DisponibilidadService::ventanaDe($hoy, '10:00', 120, null);
$v3b = ['fec_via' => $hoy, 'hor_sal_via' => '10:00:00', 'duracion_min' => 120];
check('La ruta tiene prioridad sobre el defecto del sistema',
    (DisponibilidadService::ventana($v3b)['fin'] - DisponibilidadService::ventana($v3b)['inicio']) === 7200);

$v4 = DisponibilidadService::ventana(['fec_via' => $hoy, 'hor_sal_via' => '22:00:00', 'hor_lleg_via' => '02:00:00']);
check('Un trayecto que cruza la medianoche se cuenta hasta el día siguiente',
    $v4['fin'] > strtotime("$hoy 23:00"), date('Y-m-d H:i', $v4['fin']));

/* ---------------------------------------------------------------------- */
seccion('1-2. Mismo conductor: horarios separados y superpuestos');

$c1 = crearConductor();
$v1 = crearVehiculo(Config::VEH_DISPONIBLE);
crearViaje($c1, $v1, $manana, '10:00', '12:00');

$r = intentarGuardar($c1, crearVehiculo(Config::VEH_DISPONIBLE), $manana, '14:00', '16:00');
check('1. Mismo conductor, horarios separados -> PERMITIR', $r['ok'] === true,
    json_encode($r['errores'] ?? $r['mensaje'] ?? '', JSON_UNESCAPED_UNICODE));
if ($r['ok']) $limpiar['viajes'][] = (int)$r['id'];

$r = intentarGuardar($c1, crearVehiculo(Config::VEH_DISPONIBLE), $manana, '11:30', '13:30');
check('2. Mismo conductor, horarios superpuestos -> RECHAZAR', $r['ok'] === false,
    json_encode($r, JSON_UNESCAPED_UNICODE));
check('2b. El mensaje explica qué viaje choca',
    str_contains((string)($r['errores']['id_usu_via'] ?? ''), 'ya tiene el viaje'),
    (string)($r['errores']['id_usu_via'] ?? 'sin mensaje'));

$r = intentarGuardar($c1, crearVehiculo(Config::VEH_DISPONIBLE), $manana, '12:00', '14:00');
check('2c. Empezar exactamente al terminar choca (el margen no es un Grace period)',
    $r['ok'] === false, json_encode($r, JSON_UNESCAPED_UNICODE));

/* ---------------------------------------------------------------------- */
seccion('3-4. Mismo vehículo: horarios separados y superpuestos');

$v2b = crearVehiculo(Config::VEH_DISPONIBLE);
crearViaje(crearConductor(), $v2b, $manana, '10:00', '12:00');

$r = intentarGuardar(crearConductor(), $v2b, $manana, '14:00', '16:00');
check('3. Mismo vehículo, horarios separados -> PERMITIR', $r['ok'] === true,
    json_encode($r['errores'] ?? $r['mensaje'] ?? '', JSON_UNESCAPED_UNICODE));
if ($r['ok']) $limpiar['viajes'][] = (int)$r['id'];

$r = intentarGuardar(crearConductor(), $v2b, $manana, '11:30', '13:30');
check('4. Mismo vehículo, horarios superpuestos -> RECHAZAR', $r['ok'] === false,
    json_encode($r, JSON_UNESCAPED_UNICODE));

/* ---------------------------------------------------------------------- */
seccion('5. Días diferentes');

$r = intentarGuardar($c1, $v1, date('Y-m-d', strtotime('+31 days')), '10:00', '12:00');
check('5. El mismo conductor y vehículo, OTRO DÍA -> PERMITIR', $r['ok'] === true,
    json_encode($r['errores'] ?? $r['mensaje'] ?? '', JSON_UNESCAPED_UNICODE));
if ($r['ok']) $limpiar['viajes'][] = (int)$r['id'];

/* ---------------------------------------------------------------------- */
seccion('6-8. Liberación de recursos');

$c6 = crearConductor();
$v6 = crearVehiculo(Config::VEH_DISPONIBLE);
$id6 = crearViaje($c6, $v6, $pasado, '10:00', '12:00');

Database::query('UPDATE viaje SET est_via = ? WHERE id_via = ?', [Config::VIA_CANCELADO, $id6]);
$libre = librePara($c6, $v6, $pasado, '10:00', '12:00');
check('6. Un viaje CANCELADO libera el conductor y el vehículo',
    $libre['conductor']['ok'] && $libre['vehiculo']['ok'],
    json_encode([$libre['conductor']['mensaje'], $libre['vehiculo']['mensaje']], JSON_UNESCAPED_UNICODE));

$c7 = crearConductor();
$v7 = crearVehiculo(Config::VEH_DISPONIBLE);
$id7 = crearViaje($c7, $v7, $pasado, '10:00', '12:00');
$res = ViajeService::finalizar($id7);
check('7. Finalizar manualmente un viaje devuelve «ok»', $res['ok'] === true,
    json_encode($res, JSON_UNESCAPED_UNICODE));
$libre = librePara($c7, $v7, $pasado, '10:00', '12:00');
check('7b. Tras finalizar manualmente, el horario queda libre',
    $libre['conductor']['ok'] && $libre['vehiculo']['ok'],
    json_encode([$libre['conductor']['mensaje'], $libre['vehiculo']['mensaje']], JSON_UNESCAPED_UNICODE));

$c8 = crearConductor();
$v8 = crearVehiculo(Config::VEH_DISPONIBLE);
$id8 = crearViaje($c8, $v8, $pasado, '10:00', '12:00');
ViajeService::sincronizarEstado(true);

/* OJO: `est_via` es un ENUM. El driver puede devolverlo como ÍNDICE numérico
   en lugar del texto, así que la comparación se hace en texto y sin castear. */
$estado = (string) Database::scalar('SELECT est_via FROM viaje WHERE id_via = ?', [$id8]);
check('8. La sincronización automática lo marca Finalizado', $estado === Config::VIA_FINALIZADO,
    "estado: $estado");

$libre = librePara($c8, $v8, $pasado, '10:00', '12:00');
check('8b. Tras el cierre automático, el horario queda libre',
    $libre['conductor']['ok'] && $libre['vehiculo']['ok'],
    json_encode([$libre['conductor']['mensaje'], $libre['vehiculo']['mensaje']], JSON_UNESCAPED_UNICODE));

/* ---------------------------------------------------------------------- */
seccion('9. Viaje futuro no bloquea el recurso');

$c9 = crearConductor();
$v9 = crearVehiculo(Config::VEH_DISPONIBLE);
crearViaje($c9, $v9, $manana, '10:00', '12:00');

$mananaTarde = date('Y-m-d', strtotime('+31 days'));
$r = intentarGuardar($c9, $v9, $mananaTarde, '14:00', '16:00');
check('9a. El mismo conductor puede hacer OTRO viaje ese día, mas tarde, sin solaparse',
    $r['ok'] === true, json_encode($r['errores'] ?? $r['mensaje'] ?? '', JSON_UNESCAPED_UNICODE));
if ($r['ok']) { $limpiar['viajes'][] = (int)$r['id']; Database::query('DELETE FROM viaje WHERE id_via = ?', [$r['id']]); }

check('9b. El conductor con viaje mañana NO figura como ocupado ahora',
    !DisponibilidadService::ocupadoAhora('conductor', $c9));
check('9c. El vehículo con viaje mañana NO figura como asignado ahora',
    !DisponibilidadService::ocupadoAhora('vehiculo', $v9));

/* ---------------------------------------------------------------------- */
seccion('10-12. Edición');

$c10 = crearConductor();
$c10b = crearConductor();
$v10 = crearVehiculo(Config::VEH_DISPONIBLE);
$v10b = crearVehiculo(Config::VEH_DISPONIBLE);
$id10 = crearViaje($c10, $v10, $manana, '10:00', '12:00');

$editado = ViajeService::guardar([
    'id_via'      => $id10,
    'id_rut_via'  => rutaPorDefecto(),
    'id_usu_via'  => $c10,
    'id_veh'      => $v10,
    'fec_via'     => $manana,
    'hor_sal_via' => '10:00',
    'hor_lleg_via'=> '12:00',
]);
check('10. Editar sin cambiar conductor ni vehículo -> PERMITIR', $editado['ok'] === true,
    json_encode($editado['errores'] ?? $editado['mensaje'] ?? '', JSON_UNESCAPED_UNICODE));

$otro = crearViaje($c10b, $v10b, $manana, '10:00', '12:00');
$editado = ViajeService::guardar([
    'id_via'      => $otro,
    'id_rut_via'  => rutaPorDefecto(),
    'id_usu_via'  => $c10,                       // entra en conflicto
    'id_veh'      => $v10b,
    'fec_via'     => $manana,
    'hor_sal_via' => '10:00',
    'hor_lleg_via'=> '12:00',
]);
check('11. Editar a un conductor con conflicto -> RECHAZAR', $editado['ok'] === false,
    json_encode($editado, JSON_UNESCAPED_UNICODE));
check('11b. El rechazo no ha modificado el viaje',
    (int) Database::scalar('SELECT id_usu_via FROM viaje WHERE id_via = ?', [$otro]) === $c10b);

$otro2 = crearViaje($c10b, $v10b, $manana, '15:00', '17:00');
$idTercer = crearViaje(crearConductor(), crearVehiculo(Config::VEH_DISPONIBLE), $manana, '15:00', '17:00');
$tercerVeh = (int) Database::scalar('SELECT id_veh FROM viaje WHERE id_via = ?', [$idTercer]);

$editado = ViajeService::guardar([
    'id_via'      => $otro2,
    'id_rut_via'  => rutaPorDefecto(),
    'id_usu_via'  => $c10b,
    'id_veh'      => $tercerVeh,                 // entra en conflicto
    'fec_via'     => $manana,
    'hor_sal_via' => '15:00',
    'hor_lleg_via'=> '17:00',
]);
check('12. Editar a un vehículo con conflicto -> RECHAZAR', $editado['ok'] === false,
    json_encode($editado, JSON_UNESCAPED_UNICODE));

/* ---------------------------------------------------------------------- */
seccion('13-15. Estados no asignables');

$v13 = crearVehiculo(Config::VEH_MANTENIMIENTO);
$r = intentarGuardar(crearConductor(), $v13, $manana, '10:00', '12:00');
check('13. Vehículo en mantenimiento -> RECHAZAR', $r['ok'] === false,
    json_encode($r['errores'] ?? $r['mensaje'] ?? '', JSON_UNESCAPED_UNICODE));

$v14 = crearVehiculo(Config::VEH_FUERA_SERVICIO);
$r = intentarGuardar(crearConductor(), $v14, $manana, '10:00', '12:00');
check('14. Vehículo fuera de servicio -> RECHAZAR', $r['ok'] === false,
    json_encode($r['errores'] ?? $r['mensaje'] ?? '', JSON_UNESCAPED_UNICODE));

$c15 = crearConductor(false);
$v15 = crearVehiculo(Config::VEH_DISPONIBLE);
$r = intentarGuardar($c15, $v15, $manana, '10:00', '12:00');
check('15. Conductor inactivo -> RECHAZAR', $r['ok'] === false,
    json_encode($r['errores'] ?? $r['mensaje'] ?? '', JSON_UNESCAPED_UNICODE));

/* ---------------------------------------------------------------------- */
seccion('16. El selector coincide con la validación');

$cons = DisponibilidadService::conductores($manana, '11:30', 0);
$vehs = DisponibilidadService::vehiculos($manana, '11:30', 0);

$consFallan = 0;
foreach ($cons as $c) {
    $ventana = DisponibilidadService::ventanaDe($manana, '11:30');
    if (!DisponibilidadService::comprobarConductor((int)$c['id_usu'], $ventana)['ok']) {
        $consFallan++;
    }
}
check('16a. Todos los conductores que el selector marca ocupados son rechazados de verdad',
    $consFallan > 0, "coincidencias: $consFallan");

$vehsFallan = 0;
foreach ($vehs as $v) {
    $ventana = DisponibilidadService::ventanaDe($manana, '11:30');
    if (!DisponibilidadService::comprobarVehiculo((int)$v['id_veh'], $ventana)['ok']) {
        $vehsFallan++;
    }
}
check('16b. Todos los vehículos que el selector marca ocupados son rechazados de verdad',
    $vehsFallan > 0, "coincidencias: $vehsFallan");

/* El caso inverso es el importante: lo que el selector dice LIBRE tiene que
   aceptarlo el backend, sin excepciones. */
$libres = array_filter($vehs, static fn($v) => $v['disponible']);
$fallosReales = 0;
foreach ($libres as $v) {
    $ventana = DisponibilidadService::ventanaDe($manana, '11:30');
    if (!DisponibilidadService::comprobarVehiculo((int)$v['id_veh'], $ventana)['ok']) {
        $fallosReales++;
    }
}
check('16c. Todo lo que el selector ofrece como LIBRE lo acepta el backend',
    $fallosReales === 0, "discrepancias: $fallosReales");

/* ---------------------------------------------------------------------- */
seccion('17. Estado de las tablas tras la sincronización');

DisponibilidadService::refrescarEstados();
check('17a. `refrescarEstados()` no toca Mantenimiento ni Fuera de servicio',
    (string) Database::scalar('SELECT est_veh FROM vehiculo WHERE id_veh = ?', [$v13]) === Config::VEH_MANTENIMIENTO
    && (string) Database::scalar('SELECT est_veh FROM vehiculo WHERE id_veh = ?', [$v14]) === Config::VEH_FUERA_SERVICIO);

/* ---------------------------------------------------------------------- */
seccion('18. No se puede terminar un viaje que no ha salido');

$c18 = crearConductor();
$v18 = crearVehiculo(Config::VEH_DISPONIBLE);
$futuro = date('Y-m-d', strtotime('+3 days'));

// Se inserta el viaje saltándose `guardar()` a propósito: `validar()` rechaza
// las salidas futuras solo cuando el alta es NUEVA, pero aquí lo que se quiere
// probar es la regla de «finalizar».
$id18 = crearViaje($c18, $v18, $futuro, '10:00', '12:00');

$puede = ViajeService::puedeFinalizar(ViajeService::porId($id18));
check('18a. Un viaje de mañana NO se puede finalizar', $puede[0] === false,
    'se permitió: ' . $puede[1]);
check('18b. El motivo dice cuándo sale y ofrece cancelarlo',
    str_contains($puede[1], 'todavía no ha salido') && str_contains($puede[1], 'canc'),
    $puede[1]);

$res18 = ViajeService::finalizar($id18);
check('18c. El backend RECHAZA finalizar el viaje de mañana', $res18['ok'] === false,
    json_encode($res18, JSON_UNESCAPED_UNICODE));
check('18d. El viaje sigue Programado en la base de datos',
    (string) Database::scalar('SELECT est_via FROM viaje WHERE id_via = ?', [$id18]) === Config::VIA_PROGRAMADO,
    (string) Database::scalar('SELECT est_via FROM viaje WHERE id_via = ?', [$id18]));

// Un viaje que ya salió SÍ se puede terminar a mano.
$c19 = crearConductor();
$v19 = crearVehiculo(Config::VEH_DISPONIBLE);
$id19 = crearViaje($c19, $v19, $pasado, '10:00', '12:00');
$puede = ViajeService::puedeFinalizar(ViajeService::porId($id19));
check('19. Un viaje que ya salió SÍ se puede finalizar', $puede[0] === true, $puede[1]);

$res19 = ViajeService::finalizar($id19);
check('19b. El backend acepta finalizarlo', $res19['ok'] === true,
    json_encode($res19, JSON_UNESCAPED_UNICODE));

// Un viaje ya cerrado no se puede volver a finalizar.
$puede = ViajeService::puedeFinalizar(ViajeService::porId($id19));
check('20. Un viaje ya finalizado no se vuelve a finalizar', $puede[0] === false, $puede[1]);

/* ---------------------------------------------------------------------- */
seccion('21. El motor de confirmación pinta bien los iconos');

$js = (string)file_get_contents(Config::raiz('assets/js/sget-modal.js'));
check('21a. El icono se normaliza y no se duplica el prefijo fa-',
    !str_contains($js, "'fa-' + (opts.icono || 'fa-triangle-exclamation')"));
check('21b. Se acepta tanto `fa-x` como `x`',
    str_contains($js, "indexOf('fa-') === 0"));
check('21c. Se respeta la variante «peligro»',
    str_contains($js, 'opts.peligro || opts.claseOkIsDanger'));
check('21d. El cuerpo del modal puede desplazarse',
    str_contains($js, 'sget-modal__body sget-scroll'));

/* ---------------------------------------------------------------------- */
limpiarTodo();

echo "\n" . str_repeat('=', 72) . "\n";
echo " RESULTADO: $ok correctas, $fallos fallidas\n";
echo str_repeat('=', 72) . "\n";

exit($fallos > 0 ? 1 : 0);
